<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\PaymentStatus;
use App\Enums\PreregistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\EventPreregistration;
use App\Models\LegacyPlateEntitlement;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Prerregistro al evento" and "Preventa Legacy Plate" stay two different
 * things (brief §16/§23), but the operator needs to see both together
 * (brief §17-§20) — this never queries an Order/Payment/Entitlement per
 * row: every LegacyPlateEntitlement the current page could possibly need
 * is fetched in one batch query and matched in memory by athlete+edition.
 */
class PreregistrationController extends Controller
{
    public function index(Request $request): Response
    {
        // Every filter below runs in SQL before paginate() — never on the
        // already-paginated ->data collection. Filtering the paged
        // collection instead is the exact bug the "PAGO PENDIENTE" report
        // described (paginator says 2 pages, 0 rows shown): it doesn't
        // reproduce against this controller's code today (there was no
        // status filter here at all until this change), but this is built
        // so that failure mode can't happen here regardless — see
        // tests/Feature/Admin/PreregistrationPaginationTest.php.
        $status = $request->string('status')->toString();
        $legacyPlateFilter = $request->string('legacy_plate')->toString() ?: 'all';

        $query = EventPreregistration::query()
            ->with(['eventEdition.event', 'eventRace', 'user.athlete', 'matchedParticipant'])
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->where(function ($inner) use ($search) {
                $inner->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('bib_number', 'like', "%{$search}%");
            }))
            ->when($status !== '' && PreregistrationStatus::tryFrom($status) !== null, fn ($q) => $q->where('status', $status))
            ->when($request->integer('event_edition_id') ?: null, fn ($q, $editionId) => $q->where('event_edition_id', $editionId));

        $preregistrations = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $entitlementsByKey = $this->entitlementsFor($preregistrations->getCollection());

        $preregistrations->through(function (EventPreregistration $preregistration) use ($entitlementsByKey) {
            $athleteId = $preregistration->user?->athlete?->id;
            $entitlement = $athleteId !== null ? $entitlementsByKey->get("{$athleteId}:{$preregistration->event_edition_id}") : null;

            return $this->rowPayload($preregistration, $entitlement);
        });

        // Legacy Plate filter applies after the read model is built (its
        // states are derived, not a single column) — the page is small
        // (25 rows), so filtering the mapped collection is simpler than a
        // second SQL round-trip and just as correct.
        if ($legacyPlateFilter !== 'all') {
            $preregistrations->setCollection(
                $preregistrations->getCollection()->filter(fn ($row) => match ($legacyPlateFilter) {
                    'none' => $row['legacy_plate_status'] === 'none',
                    'pending' => $row['legacy_plate_status'] === 'pending_payment',
                    'paid' => in_array($row['legacy_plate_status'], ['paid', 'linked', 'queued', 'produced', 'delivered'], true),
                    'linked' => $row['participant_linked'],
                    'unlinked' => ! $row['participant_linked'],
                    default => true,
                })->values(),
            );
        }

        return Inertia::render('admin/preregistrations/Index', [
            'preregistrations' => $preregistrations,
            'statuses' => array_map(fn (PreregistrationStatus $s) => $s->value, PreregistrationStatus::cases()),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $status ?: null,
                'legacy_plate' => $legacyPlateFilter,
            ],
            'counters' => $this->counters(clone $query),
        ]);
    }

    /**
     * @param  Collection<int, EventPreregistration>  $preregistrations
     * @return Collection<string, LegacyPlateEntitlement>
     */
    private function entitlementsFor(Collection $preregistrations): Collection
    {
        $athleteIds = $preregistrations->map(fn (EventPreregistration $p) => $p->user?->athlete?->id)->filter()->unique()->values();

        if ($athleteIds->isEmpty()) {
            return new Collection;
        }

        return LegacyPlateEntitlement::query()
            ->whereIn('athlete_id', $athleteIds)
            ->whereIn('event_edition_id', $preregistrations->pluck('event_edition_id')->unique())
            ->with(['legacyPlateModel', 'orderItem.order.payments'])
            ->get()
            ->keyBy(fn (LegacyPlateEntitlement $e) => "{$e->athlete_id}:{$e->event_edition_id}");
    }

    /** @return array<string, mixed> */
    private function rowPayload(EventPreregistration $preregistration, ?LegacyPlateEntitlement $entitlement): array
    {
        $payment = $entitlement?->orderItem?->order?->payments->first(fn (Payment $p) => $p->status === PaymentStatus::Paid);

        return [
            'id' => $preregistration->id,
            'name' => trim("{$preregistration->first_name} {$preregistration->last_name}"),
            'email' => $preregistration->email,
            'event' => $preregistration->eventEdition?->event?->name,
            'race' => $preregistration->eventRace?->name,
            'bib_number' => $preregistration->bib_number,
            'status' => $preregistration->status->value,
            'created_at' => $preregistration->created_at?->toDateString(),
            'legacy_plate_status' => $entitlement?->status->value ?? 'none',
            'legacy_plate_model' => $entitlement?->legacyPlateModel?->name,
            'legacy_plate_price_minor' => $payment?->amount_minor,
            'legacy_plate_currency' => $payment?->currency,
            'legacy_plate_method' => $payment?->method->value,
            'legacy_plate_paid_at' => $payment?->paid_at?->toDateString(),
            'order_number' => $entitlement?->orderItem?->order?->order_number,
            'order_uuid' => $entitlement?->orderItem?->order?->uuid,
            'athlete_linked' => $preregistration->user?->athlete !== null,
            'participant_linked' => $preregistration->matched_participant_id !== null,
        ];
    }

    /**
     * @param  Builder<EventPreregistration>  $query
     * @return array<string, int|float>
     */
    private function counters(Builder $query): array
    {
        $total = (clone $query)->count();

        $preregistrationIds = (clone $query)->pluck('id');
        $preregistrations = EventPreregistration::query()->whereIn('id', $preregistrationIds)->with('user.athlete')->get();
        $athleteEditionPairs = $preregistrations
            ->filter(fn (EventPreregistration $p) => $p->user?->athlete !== null)
            ->map(fn (EventPreregistration $p) => ['athlete_id' => $p->user->athlete->id, 'event_edition_id' => $p->event_edition_id]);

        $entitlements = $athleteEditionPairs->isEmpty()
            ? new Collection
            : LegacyPlateEntitlement::query()
                ->whereIn('athlete_id', $athleteEditionPairs->pluck('athlete_id')->unique())
                ->whereIn('event_edition_id', $athleteEditionPairs->pluck('event_edition_id')->unique())
                ->get()
                ->filter(fn (LegacyPlateEntitlement $e) => $athleteEditionPairs->contains(fn ($pair) => $pair['athlete_id'] === $e->athlete_id && $pair['event_edition_id'] === $e->event_edition_id));

        $presales = $entitlements->count();
        $paid = $entitlements->filter(fn (LegacyPlateEntitlement $e) => $e->status !== LegacyPlateEntitlementStatus::PendingPayment)->count();
        $pending = $presales - $paid;

        return [
            'preregistrations' => $total,
            'presales' => $presales,
            'paid' => $paid,
            'pending' => $pending,
            'conversion_rate' => $total > 0 ? round(($presales / $total) * 100, 1) : 0.0,
        ];
    }
}
