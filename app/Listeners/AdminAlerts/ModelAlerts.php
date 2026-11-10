<?php

namespace App\Listeners\AdminAlerts;

use App\Actions\Notifications\NotifyAdmins;
use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\AdminAlertCategory;
use App\Enums\ContactMessageType;
use App\Enums\InventoryLotStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\SupportCaseStatus;
use App\Jobs\CheckVariantStockJob;
use App\Models\CheerMessageReport;
use App\Models\ContactMessage;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\LegacyTagEvent;
use App\Models\Payment;
use App\Models\PhotoSale;
use App\Models\Report;
use App\Models\SupportCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Notifications that come from plain model writes rather than domain events
 * (registered as Eloquent listeners in App\Providers\NotificationServiceProvider).
 * Every hook runs after the surrounding transaction commits and never throws,
 * so a failing alert can't roll back or break the action that caused it.
 *
 *  - LegacyTagEvent `activated` → customer "Tag activado" + admin Activaciones;
 *    `reported_missing` → admin Inventario.
 *  - InventoryLot → `received` → admin Inventario.
 *  - InventoryMovement created → queued low-stock check for that variant.
 *  - Payment → `failed` → admin Ventas.
 *  - SupportCase created → admin Soporte; status/resolution changed →
 *    customer "Soporte respondió".
 *  - ContactMessage created → admin Soporte (account deletion flagged).
 *  - Report created → admin Comunidad. CheerMessageReport created (a
 *    reported cheer in Entrenamientos) → admin Comunidad too.
 *  - PhotoSale created → admin Fotografía.
 */
class ModelAlerts
{
    public function __construct(
        private readonly NotifyAdmins $admins,
        private readonly SendAthleteNotification $customer,
    ) {}

    public function tagEventCreated(LegacyTagEvent $event): void
    {
        $this->afterCommit(function () use ($event) {
            $event->loadMissing('tag.owner.user', 'tag.productVariant.product');
            $tag = $event->tag;

            if ($tag === null) {
                return;
            }

            $product = $tag->productVariant->product->name ?? 'Finisher Legacy Tag';

            if ($event->type === LegacyTagEventType::Activated) {
                $owner = $tag->owner?->user;

                if ($owner !== null) {
                    $this->customer->handle(
                        $owner,
                        // Closures: written in the owner's language (SendAthleteNotification).
                        fn (): string => __('Tu Tag está activo'),
                        fn (): string => __('Activaste tu :product. Ya puedes contar tu historia desde la app.', ['product' => $product]),
                        NotificationType::TagActivated,
                        '/t/'.$tag->public_code,
                        ['tag_uuid' => $tag->uuid],
                        push: true,
                    );
                }

                $this->admins->handle(
                    AdminAlertCategory::Activaciones,
                    'tag_activated',
                    'Tag activado',
                    "{$product} · serie {$tag->serial}".($tag->owner ? ' · '.$tag->owner->full_name : ''),
                    $this->adminUrl('admin.tags.show', $tag),
                    ['tag_uuid' => $tag->uuid],
                    level: 'success',
                );
            }

            if ($event->type === LegacyTagEventType::ReportedMissing) {
                $stolen = (bool) ($event->properties['stolen'] ?? false);

                $this->admins->handle(
                    AdminAlertCategory::Inventario,
                    $stolen ? 'tag_stolen' : 'tag_lost',
                    $stolen ? 'Tag reportado como robado' : 'Tag reportado como extraviado',
                    "{$product} · serie {$tag->serial}".(filled($event->notes) ? ' · '.mb_strimwidth((string) $event->notes, 0, 80, '…') : ''),
                    $this->adminUrl('admin.tags.show', $tag),
                    ['tag_uuid' => $tag->uuid],
                    level: 'warning',
                );
            }
        });
    }

    public function lotUpdated(InventoryLot $lot): void
    {
        if (! $lot->wasChanged('status') || $lot->status !== InventoryLotStatus::Received) {
            return;
        }

        $this->afterCommit(function () use ($lot) {
            $lot->loadMissing('productVariant.product');

            $this->admins->handle(
                AdminAlertCategory::Inventario,
                'lot_received',
                'Lote recibido',
                "Lote {$lot->code} · {$lot->quantity_received} de {$lot->quantity} piezas · ".($lot->productVariant->product->name ?? 'Producto'),
                $this->adminUrl('admin.catalog.lots.show', $lot),
                ['lot_uuid' => $lot->uuid],
                level: 'success',
            );
        });
    }

    public function movementCreated(InventoryMovement $movement): void
    {
        $variantId = (int) $movement->product_variant_id;

        $this->afterCommit(fn () => CheckVariantStockJob::dispatch($variantId));
    }

    public function paymentUpdated(Payment $payment): void
    {
        if (! $payment->wasChanged('status') || $payment->status !== PaymentStatus::Failed) {
            return;
        }

        $this->afterCommit(function () use ($payment) {
            $order = $payment->order;

            $this->admins->handle(
                AdminAlertCategory::Ventas,
                'payment_failed',
                'Pago rechazado',
                ($order->order_number ?? 'Pedido').' · '.NotifyAdmins::money((int) $payment->amount_minor, (string) $payment->currency).' · '.($payment->provider->value ?? 'pasarela'),
                $order !== null ? $this->adminUrl('admin.orders.show', $order) : $this->adminUrl('admin.payments.index'),
                ['payment_id' => $payment->id],
                throttleKey: 'payment:'.$payment->id,
                throttleMinutes: 60 * 24,
                level: 'warning',
            );
        });
    }

    public function supportCaseCreated(SupportCase $case): void
    {
        $this->afterCommit(fn () => $this->admins->handle(
            AdminAlertCategory::Soporte,
            'support_case_created',
            "Nuevo caso de soporte {$case->number}",
            $case->subject ?: mb_strimwidth((string) $case->description, 0, 120, '…'),
            $this->adminUrl('admin.support.show', $case),
            ['support_case_uuid' => $case->uuid],
        ));
    }

    public function supportCaseUpdated(SupportCase $case): void
    {
        if (! $case->wasChanged('status') && ! $case->wasChanged('resolution')) {
            return;
        }

        $this->afterCommit(function () use ($case) {
            $case->loadMissing('reporter');
            $reporter = $case->reporter;

            if ($reporter === null) {
                return;
            }

            $resolved = in_array($case->status, [SupportCaseStatus::Resolved, SupportCaseStatus::Closed], true);

            $this->customer->handle(
                $reporter,
                fn (): string => $resolved ? __('Resolvimos tu solicitud') : __('Actualizamos tu solicitud'),
                fn (): string => $resolved && filled($case->resolution)
                    ? __('Caso :number: :resolution', ['number' => $case->number, 'resolution' => mb_strimwidth((string) $case->resolution, 0, 140, '…')])
                    // One whole sentence per status (never a label dropped mid-sentence).
                    : match ($case->status) {
                        SupportCaseStatus::Open => __('Tu caso :number está abierto.', ['number' => $case->number]),
                        SupportCaseStatus::InProgress => __('Tu caso :number ya está en proceso.', ['number' => $case->number]),
                        SupportCaseStatus::Resolved => __('Tu caso :number quedó resuelto.', ['number' => $case->number]),
                        SupportCaseStatus::Closed => __('Tu caso :number quedó cerrado.', ['number' => $case->number]),
                    },
                NotificationType::SupportReply,
                null,
                ['target' => ['type' => 'support', 'uuid' => $case->uuid]],
                push: true,
            );
        });
    }

    public function contactMessageCreated(ContactMessage $message): void
    {
        $this->afterCommit(function () use ($message) {
            $deletion = $message->type === ContactMessageType::AccountDeletion;

            $this->admins->handle(
                AdminAlertCategory::Soporte,
                $deletion ? 'account_deletion_requested' : 'contact_message',
                $deletion ? 'Solicitud de eliminación de cuenta' : 'Nuevo mensaje de contacto',
                trim(($message->name ?? '').' · '.($message->email ?? '')).' · '.mb_strimwidth((string) $message->message, 0, 100, '…'),
                $this->adminUrl('admin.contact-messages.index'),
                ['contact_message_id' => $message->id],
                level: $deletion ? 'warning' : 'info',
            );
        });
    }

    public function reportCreated(Report $report): void
    {
        $this->afterCommit(fn () => $this->admins->handle(
            AdminAlertCategory::Comunidad,
            'report_created',
            'Nuevo reporte en la comunidad',
            ucfirst($report->target_type->value).' · '.mb_strimwidth((string) $report->reason, 0, 100, '…'),
            $this->adminUrl('admin.community.index'),
            ['report_uuid' => $report->uuid],
            level: 'warning',
        ));
    }

    /**
     * Cheers are private between sender and athlete: the alert names only the
     * reason, never the message text — staff read it from the moderation
     * screen, where every access is audited.
     */
    public function cheerReportCreated(CheerMessageReport $report): void
    {
        $this->afterCommit(fn () => $this->admins->handle(
            AdminAlertCategory::Comunidad,
            'cheer_report_created',
            'Nuevo reporte de un mensaje de ánimo',
            'Entrenamientos · '.$report->reason->label(),
            $this->adminUrl('admin.training.index'),
            ['cheer_report_uuid' => $report->uuid],
            level: 'warning',
        ));
    }

    public function photoSaleCreated(PhotoSale $sale): void
    {
        $this->afterCommit(fn () => $this->admins->handle(
            AdminAlertCategory::Fotografia,
            'photo_sold',
            'Venta de foto',
            NotifyAdmins::money((int) $sale->gross_minor, (string) $sale->currency).' · neto fotógrafo '.NotifyAdmins::money((int) $sale->photographer_net_minor, (string) $sale->currency),
            $this->adminUrl('admin.photographer-commissions.index'),
            ['photo_sale_uuid' => $sale->uuid],
            level: 'success',
        ));
    }

    private function afterCommit(callable $callback): void
    {
        DB::afterCommit(function () use ($callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    private function adminUrl(string $name, mixed $parameters = []): string
    {
        return Route::has($name) ? route($name, $parameters, absolute: false) : '/admin';
    }
}
