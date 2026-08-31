<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventEdition;
use App\Models\LegacyPlateEntitlement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Legacy Plate presales — separate from Event Preregistration (brief §16/
 * §23): this lists every LegacyPlateEntitlement for an event, paid or not,
 * linked to a participant or not (a presale can exist before a bib does).
 */
class LegacyPlatePresaleController extends Controller
{
    public function index(Request $request): Response
    {
        $eventEditionId = $request->integer('event_edition_id') ?: null;
        $edition = $eventEditionId ? EventEdition::with('event')->find($eventEditionId) : null;

        $presales = [];

        if ($edition !== null) {
            $presales = LegacyPlateEntitlement::query()
                ->where('event_edition_id', $edition->id)
                ->with(['athlete', 'eventParticipant', 'legacyPlateModel', 'orderItem.order'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (LegacyPlateEntitlement $entitlement) => [
                    'id' => $entitlement->id,
                    'athlete' => $entitlement->athlete?->full_name,
                    'bib_number' => $entitlement->eventParticipant?->bib_number,
                    'model' => $entitlement->legacyPlateModel?->name,
                    'status' => $entitlement->status->value,
                    'price_type' => $entitlement->price_type,
                    'paid_at' => $entitlement->paid_at?->toDateTimeString(),
                    'linked' => $entitlement->event_participant_id !== null,
                    // Payment always happens on the funding Order (brief
                    // §81/§100: "paid" is set by MarkOrderPaid, never
                    // re-derived here) — the operator registers PAGO EN
                    // LÍNEA / TERMINAL / EFECTIVO from that Order's screen.
                    'order_uuid' => $entitlement->orderItem?->order?->uuid,
                    'payment_status' => $entitlement->orderItem?->order?->payment_status->value,
                ]);
        }

        return Inertia::render('admin/legacy-plates/Presales', [
            'events' => EventEdition::with('event')->orderByDesc('event_date')->limit(100)->get()
                ->map(fn (EventEdition $e) => ['id' => $e->id, 'name' => $e->event->name.' — '.$e->name]),
            'selectedEventEditionId' => $edition?->id,
            'presales' => $presales,
        ]);
    }
}
