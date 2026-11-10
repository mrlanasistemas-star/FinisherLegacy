<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventPhotoStatus;
use App\Enums\LegacyTagStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReportStatus;
use App\Enums\SupportCaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\EventEdition;
use App\Models\EventPhoto;
use App\Models\LegacyTag;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Report;
use App\Models\SupportCase;
use App\Queries\Admin\CommerceDashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Dashboard. Every number is a live aggregate over the
 * commerce / tag / support tables — nothing here is a sample figure, and
 * an empty table renders an empty state, never a made-up value.
 *
 * The metal-plate production widgets (pipeline, preparation queue, plates
 * in production, event incidents) were retired with the production
 * modules (docs/renovacion/retiro-modulos.md).
 */
class DashboardController extends Controller
{
    public function index(Request $request, CommerceDashboardMetrics $metrics): Response
    {
        $range = in_array($request->query('rango'), CommerceDashboardMetrics::RANGES, true) ? (string) $request->query('rango') : '30d';
        [$from, $to] = CommerceDashboardMetrics::window($range);
        $user = $request->user();

        return Inertia::render('admin/Dashboard', [
            // Commerce + Activation OS (docs/architecture/commerce-os.md §Administración comercial).
            'range' => $range,
            'ranges' => [
                ['value' => 'hoy', 'label' => 'Hoy'],
                ['value' => '7d', 'label' => '7 días'],
                ['value' => '30d', 'label' => '30 días'],
                ['value' => 'mes', 'label' => 'Este mes'],
            ],
            'commerce' => $metrics->handle($range),
            'tags' => $this->tagTotals(),
            'payments' => $user->can('payments.view') ? $this->paymentSummary($from, $to) : null,
            'kpis' => [
                'pending_orders' => Order::query()
                    ->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed])
                    ->whereNotIn('payment_status', [OrderPaymentStatus::Failed, OrderPaymentStatus::Cancelled])
                    ->count(),
                'photos_pending' => EventPhoto::query()->where('status', EventPhotoStatus::Review)->count(),
            ],
            'today' => $this->todayOverview(),
            'recentOrders' => $this->recentOrders(),
            'general' => [
                'new_messages' => ContactMessage::query()->where('status', 'new')->count(),
            ],
            // Only for whoever can open the destination lists.
            'recentActivations' => $user->can('tags.view') ? $this->recentActivations() : null,
            'support' => $user->can('support.view') ? $this->supportSummary() : null,
        ]);
    }

    /**
     * Lifetime Finisher Legacy Tag units by lifecycle bucket.
     *
     * @return array{total: int, in_stock: int, reserved: int, sold: int, activated: int}
     */
    private function tagTotals(): array
    {
        $byStatus = LegacyTag::query()->toBase()
            ->groupBy('status')
            ->pluck(DB::raw('count(*)'), 'status');
        $count = fn (LegacyTagStatus ...$statuses) => (int) collect($statuses)->sum(fn (LegacyTagStatus $s) => (int) ($byStatus[$s->value] ?? 0));

        return [
            'total' => (int) $byStatus->sum(),
            'in_stock' => $count(LegacyTagStatus::InStock),
            'reserved' => $count(LegacyTagStatus::Reserved),
            // Sold and handed over, still waiting for its owner to activate it.
            'sold' => $count(LegacyTagStatus::Sold, LegacyTagStatus::Delivered),
            'activated' => $count(LegacyTagStatus::Activated),
        ];
    }

    /**
     * Payments created in the window (default currency): received,
     * refunded, failed and still pending.
     *
     * @return array{currency: string, paid: array{count: int, amount_minor: int}, refunded: array{count: int, amount_minor: int}, failed: int, pending: int}
     */
    private function paymentSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $currency = (string) config('finisher.commerce.default_currency', 'MXN');

        $rows = Payment::query()->toBase()
            ->where('currency', $currency)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')
            ->get(['status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(amount_minor), 0) as amount')])
            ->keyBy('status');

        $count = fn (PaymentStatus ...$s) => (int) collect($s)->sum(fn (PaymentStatus $x) => (int) ($rows[$x->value]->total ?? 0));
        $amount = fn (PaymentStatus ...$s) => (int) collect($s)->sum(fn (PaymentStatus $x) => (int) ($rows[$x->value]->amount ?? 0));

        return [
            'currency' => $currency,
            'paid' => [
                'count' => $count(PaymentStatus::Paid, PaymentStatus::PartiallyRefunded),
                'amount_minor' => $amount(PaymentStatus::Paid, PaymentStatus::PartiallyRefunded),
            ],
            'refunded' => [
                'count' => $count(PaymentStatus::Refunded),
                'amount_minor' => $amount(PaymentStatus::Refunded),
            ],
            'failed' => $count(PaymentStatus::Failed, PaymentStatus::Cancelled),
            'pending' => $count(PaymentStatus::Pending, PaymentStatus::Authorized),
        ];
    }

    /**
     * Latest tag activations (who activated which piece, and when).
     *
     * @return list<array<string, mixed>>
     */
    private function recentActivations(): array
    {
        return array_values(LegacyTag::query()
            ->with(['productVariant:id,product_id', 'productVariant.product:id,name', 'owner:id,full_name'])
            ->whereNotNull('activated_at')
            ->latest('activated_at')
            ->limit(6)
            ->get()
            ->map(fn (LegacyTag $tag) => [
                'uuid' => $tag->uuid,
                'display_code' => $tag->displayCode(),
                'product' => $tag->productVariant?->product?->name,
                'owner' => $tag->owner?->full_name,
                'activated_at' => $tag->activated_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Open support cases: counts by status and the most recent ones.
     *
     * @return array<string, mixed>
     */
    private function supportSummary(): array
    {
        $open = [SupportCaseStatus::Open->value, SupportCaseStatus::InProgress->value];
        $counts = SupportCase::query()->toBase()->whereIn('status', $open)
            ->groupBy('status')->pluck(DB::raw('count(*)'), 'status');

        return [
            'open' => (int) ($counts[SupportCaseStatus::Open->value] ?? 0),
            'in_progress' => (int) ($counts[SupportCaseStatus::InProgress->value] ?? 0),
            'recent' => array_values(SupportCase::query()
                ->whereIn('status', $open)
                ->latest('id')
                ->limit(5)
                ->get(['id', 'uuid', 'number', 'subject', 'type', 'status', 'priority', 'created_at'])
                ->map(fn (SupportCase $case) => [
                    'uuid' => $case->uuid,
                    'number' => $case->number,
                    'subject' => $case->subject,
                    'type_label' => $case->type->label(),
                    'status' => $case->status->value,
                    'status_label' => $case->status->label(),
                    'priority' => $case->priority,
                    'created_at' => $case->created_at?->toIso8601String(),
                ])
                ->all()),
        ];
    }

    /**
     * "Hoy": events happening today or within the next 7 days (max 6) and
     * open community reports. Read-only, bounded aggregates.
     *
     * @return array<string, mixed>
     */
    private function todayOverview(): array
    {
        $today = CarbonImmutable::today();

        return [
            'date' => $today->toDateString(),
            'events' => EventEdition::query()
                ->with('event:id,name')
                ->withCount('participants')
                ->whereDate('event_date', '>=', $today->toDateString())
                ->whereDate('event_date', '<=', $today->addDays(7)->toDateString())
                ->orderBy('event_date')
                ->limit(6)
                ->get(['id', 'event_id', 'name', 'event_date', 'city'])
                ->map(fn (EventEdition $edition) => [
                    'id' => $edition->id,
                    'name' => $edition->event->name,
                    'edition' => $edition->name,
                    'event_date' => $edition->event_date->toDateString(),
                    'is_today' => $edition->event_date->isToday(),
                    'city' => $edition->city,
                    'participants' => $edition->participants_count,
                ])->all(),
            'community_reports' => Report::query()->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return array_values(Order::query()
            ->with('user:id,first_name,last_name')
            ->withCount('items')
            ->latest()
            ->limit(8)
            ->get()
            ->values()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'number' => $order->order_number,
                'customer' => $order->customer_snapshot['name'] ?? data_get($order, 'user.name') ?? 'Cliente',
                'total_minor' => (int) $order->total_minor,
                'currency' => $order->currency,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'items' => $order->items_count,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all());
    }
}
