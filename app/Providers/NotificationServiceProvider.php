<?php

namespace App\Providers;

use App\Listeners\AdminAlerts\ModelAlerts;
use App\Models\CheerMessageReport;
use App\Models\ContactMessage;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\LegacyTagEvent;
use App\Models\Payment;
use App\Models\PhotoSale;
use App\Models\Report;
use App\Models\SupportCase;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the model-driven notifications (App\Listeners\AdminAlerts\ModelAlerts)
 * — admin alerts and the customer notifications that have no domain event.
 * Domain events (App\Events\Commerce\*) are picked up by listener discovery
 * (App\Listeners\Notifications\*, App\Listeners\AdminAlerts\*).
 * docs/renovacion/notificaciones.md.
 */
class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $alerts = fn (): ModelAlerts => $this->app->make(ModelAlerts::class);

        LegacyTagEvent::created(fn (LegacyTagEvent $e) => $alerts()->tagEventCreated($e));
        InventoryLot::updated(fn (InventoryLot $l) => $alerts()->lotUpdated($l));
        InventoryMovement::created(fn (InventoryMovement $m) => $alerts()->movementCreated($m));
        Payment::updated(fn (Payment $p) => $alerts()->paymentUpdated($p));
        SupportCase::created(fn (SupportCase $c) => $alerts()->supportCaseCreated($c));
        SupportCase::updated(fn (SupportCase $c) => $alerts()->supportCaseUpdated($c));
        ContactMessage::created(fn (ContactMessage $m) => $alerts()->contactMessageCreated($m));
        Report::created(fn (Report $r) => $alerts()->reportCreated($r));
        CheerMessageReport::created(fn (CheerMessageReport $r) => $alerts()->cheerReportCreated($r));
        PhotoSale::created(fn (PhotoSale $s) => $alerts()->photoSaleCreated($s));
    }
}
