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
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        // described (paginator says 2 pages, 0 rows shown): the legacy_plate
        // filter used to do exactly that (filter the mapped page in PHP,
        // after paginate() already fixed the total/last_page from the
        // *unfiltered* count) — see tests/Feature/Admin/
        // PreregistrationPaginationTest.php.
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

        // Counters always reflect search/status/event filters only, never
        // the legacy_plate view filter below (unchanged behavior).
        $countersQuery = clone $query;

        $query->when($legacyPlateFilter !== 'all', function ($q) use ($legacyPlateFilter) {
            match ($legacyPlateFilter) {
                'linked' => $q->whereNotNull('matched_participant_id'),
                'unlinked' => $q->whereNull('matched_participant_id'),
                'none' => $q->whereNotExists(fn ($sub) => $this->entitlementExistsSubquery($sub)),
                'pending' => $q->whereExists(fn ($sub) => $this->entitlementExistsSubquery($sub, [LegacyPlateEntitlementStatus::PendingPayment->value])),
                'paid' => $q->whereExists(fn ($sub) => $this->entitlementExistsSubquery($sub, ['paid', 'linked', 'queued', 'produced', 'delivered'])),
                default => null,
            };
        });

        $preregistrations = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $entitlementsByKey = $this->entitlementsFor($preregistrations->getCollection());

        $preregistrations->through(function (EventPreregistration $preregistration) use ($entitlementsByKey) {
            $athleteId = $preregistration->user?->athlete?->id;
            $entitlement = $athleteId !== null ? $entitlementsByKey->get("{$athleteId}:{$preregistration->event_edition_id}") : null;

            return $this->rowPayload($preregistration, $entitlement);
        });

        return Inertia::render('admin/preregistrations/Index', [
            'preregistrations' => $preregistrations,
            'statuses' => array_map(fn (PreregistrationStatus $s) => $s->value, PreregistrationStatus::cases()),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $status ?: null,
                'legacy_plate' => $legacyPlateFilter,
            ],
            'counters' => $this->counters($countersQuery),
        ]);
    }

    /**
     * A correlated EXISTS against legacy_plate_entitlements, matched the
     * same way entitlementsFor() matches in PHP: by the preregistration's
     * linked Athlete (via its User) + event_edition_id. Optionally
     * constrained to a set of entitlement statuses.
     *
     * @param  list<string>  $statuses
     */
    private function entitlementExistsSubquery(QueryBuilder $sub, array $statuses = []): QueryBuilder
    {
        $sub->select(DB::raw(1))
            ->from('legacy_plate_entitlements')
            ->join('athletes', 'athletes.id', '=', 'legacy_plate_entitlements.athlete_id')
            ->whereColumn('athletes.user_id', 'event_preregistrations.user_id')
            ->whereColumn('legacy_plate_entitlements.event_edition_id', 'event_preregistrations.event_edition_id');

        if ($statuses !== []) {
            $sub->whereIn('legacy_plate_entitlements.status', $statuses);
        }

        return $sub;
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
