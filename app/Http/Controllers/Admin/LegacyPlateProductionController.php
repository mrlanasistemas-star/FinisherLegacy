<?php

namespace App\Http\Controllers\Admin;

use App\Actions\LegacyPlates\GenerateLegacyPlate;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Http\Controllers\Controller;
use App\Models\EventEdition;
use App\Models\LegacyPlateEntitlement;
use App\Services\PlateEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * "Selecciona evento → cola" (brief §17-§19/§55): eligibility is always
 * read from App\Services\PlateEligibilityService::checkForEntitlement(),
 * never re-derived in Vue — the "producir" action always goes through
 * App\Actions\LegacyPlates\GenerateLegacyPlate, the same Action the REST
 * API uses.
 */
class LegacyPlateProductionController extends Controller
{
    public function __construct(private readonly PlateEligibilityService $eligibility) {}

    public function index(Request $request): Response
    {
        $eventEditionId = $request->integer('event_edition_id') ?: null;
        $edition = $eventEditionId ? EventEdition::with('event')->find($eventEditionId) : null;

        $queue = [];

        if ($edition !== null) {
            $entitlements = LegacyPlateEntitlement::query()
                ->where('event_edition_id', $edition->id)
                ->whereNull('plate_id')
                ->with(['eventParticipant.result', 'eventParticipant.eventRace', 'legacyPlateModel'])
                ->orderByDesc('created_at')
                ->get();

            $queue = $entitlements->map(function (LegacyPlateEntitlement $entitlement) {
                $check = $this->eligibility->checkForEntitlement($entitlement);
                $participant = $entitlement->eventParticipant;
                $result = $participant?->result;
                $athleteName = null;

                if ($participant !== null) {
                    $athleteName = $participant->full_name ?: trim("{$participant->first_name} {$participant->last_name}");
                }

                return [
                    'id' => $entitlement->id,
                    'bib_number' => $participant?->bib_number,
                    'athlete_name' => $athleteName,
                    'race' => $participant?->eventRace?->name,
                    'model' => $entitlement->legacyPlateModel?->name,
                    'status' => $entitlement->status->value,
                    'paid' => $entitlement->isPaid(),
                    'official_time' => $result?->official_time,
                    'pace' => $result?->pace,
                    'eligible' => $check->eligible,
                    'reasons' => $check->reasons,
                ];
            })->values();
        }

        // Kanban: every entitlement by its real status (all events, or the
        // selected one) — Por pagar → Lista para imprimir → En impresión →
        // Impresa → Entregada.
        $lanes = [
            'pending_payment' => [LegacyPlateEntitlementStatus::PendingPayment],
            'ready' => [LegacyPlateEntitlementStatus::Paid, LegacyPlateEntitlementStatus::Linked],
            'printing' => [LegacyPlateEntitlementStatus::Queued],
            'printed' => [LegacyPlateEntitlementStatus::Produced],
            'delivered' => [LegacyPlateEntitlementStatus::Delivered],
        ];

        $board = collect($lanes)->map(function (array $statuses, string $key) use ($edition) {
            $query = LegacyPlateEntitlement::query()
                ->whereIn('status', $statuses)
                ->when($edition, fn ($q) => $q->where('event_edition_id', $edition->id));

            return [
                'key' => $key,
                'count' => (clone $query)->count(),
                'items' => $query
                    ->with(['eventParticipant.result', 'eventParticipant.eventRace', 'eventEdition.event', 'legacyPlateModel', 'plate'])
                    ->latest('updated_at')
                    ->limit(40)
                    ->get()
                    ->map(function (LegacyPlateEntitlement $entitlement) use ($key) {
                        $participant = $entitlement->eventParticipant;
                        $check = $key === 'ready' ? $this->eligibility->checkForEntitlement($entitlement) : null;

                        return [
                            'id' => $entitlement->id,
                            'athlete_name' => $participant?->full_name ?: ($participant ? trim("{$participant->first_name} {$participant->last_name}") : null),
                            'bib_number' => $participant?->bib_number,
                            'event' => $entitlement->eventEdition?->event?->name,
                            'race' => $participant?->eventRace?->name,
                            'official_time' => $participant?->result?->official_time,
                            'model' => $entitlement->legacyPlateModel?->name,
                            'plate_id' => $entitlement->plate_id,
                            'serial_number' => $entitlement->plate?->serial_number,
                            'eligible' => $check?->eligible ?? false,
                            'reasons' => $check?->reasons ?? [],
                            'updated_at' => $entitlement->updated_at?->toIso8601String(),
                        ];
                    }),
            ];
        })->values();

        return Inertia::render('admin/legacy-plates/Production', [
            'board' => $board,
            'events' => EventEdition::with('event')->orderByDesc('event_date')->limit(100)->get()
                ->map(fn (EventEdition $e) => ['id' => $e->id, 'name' => $e->event->name.' — '.$e->name]),
            'selectedEventEditionId' => $edition?->id,
            'queue' => $queue,
        ]);
    }

    public function produce(LegacyPlateEntitlement $legacyPlateEntitlement, GenerateLegacyPlate $generate): RedirectResponse
    {
        try {
            $generate->handle($legacyPlateEntitlement);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Legacy Plate en cola de producción.']);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
