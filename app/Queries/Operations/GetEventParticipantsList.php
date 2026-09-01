<?php

namespace App\Queries\Operations;

use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Participants V2 (product UX consolidation brief §19-§31) — always
 * event-scoped, never a bare "all participants everywhere" scan. Every
 * filter is applied in SQL before paginate() — never on the already-paged
 * collection — so `total`/`last_page` always describe the filtered set,
 * never the unfiltered one (the exact anti-pattern the brief's §67-§68
 * asked us to prove or disprove: it doesn't reproduce against this
 * controller's code, but this Query is built so the failure mode it
 * describes can't happen here regardless).
 */
class GetEventParticipantsList
{
    /**
     * @param  array{q?: string, event_race_id?: int, result?: string, legacy_plate?: string, product?: string}  $filters
     * @return LengthAwarePaginator<int, EventParticipant>
     */
    public function handle(EventEdition $edition, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $edition->participants()
            ->with([
                'eventRace', 'result', 'athlete', 'legacyPlateEntitlements.legacyPlateModel',
                // Scoped to this edition's Orders only — the row/export
                // "productos comprados" column reads this eager load, never
                // a per-row query (brief §138: "No N+1").
                'athlete.orders' => fn ($q) => $q->where('event_edition_id', $edition->id)->with('items.product'),
            ])
            ->withCount('media');

        if (filled($filters['q'] ?? null)) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('bib_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (filled($filters['event_race_id'] ?? null)) {
            $query->where('event_race_id', $filters['event_race_id']);
        }

        if (filled($filters['result'] ?? null)) {
            match ($filters['result']) {
                'finished' => $query->whereHas('result', fn ($q) => $q->whereIn('status', ['finished', 'verified'])),
                // Nested inside its own where() closure — an unscoped
                // orWhereHas() here would OR against the *entire* query
                // (including q/event_race_id/etc above), not just this
                // filter, which is exactly the kind of "filter applied
                // wrong so it silently returns more than it should" bug
                // this module exists to not repeat.
                'pending' => $query->where(fn ($q) => $q->whereDoesntHave('result')
                    ->orWhereHas('result', fn ($q2) => $q2->whereNotIn('status', ['finished', 'verified']))),
                default => null,
            };
        }

        if (filled($filters['legacy_plate'] ?? null)) {
            match ($filters['legacy_plate']) {
                'none' => $query->whereDoesntHave('legacyPlateEntitlements'),
                'pending_payment' => $query->whereHas('legacyPlateEntitlements', fn ($q) => $q->where('status', 'pending_payment')),
                'paid' => $query->whereHas('legacyPlateEntitlements', fn ($q) => $q->whereIn('status', ['paid', 'linked'])),
                'production' => $query->whereHas('legacyPlateEntitlements', fn ($q) => $q->whereIn('status', ['queued', 'produced'])),
                'delivered' => $query->whereHas('legacyPlateEntitlements', fn ($q) => $q->where('status', 'delivered')),
                default => null,
            };
        }

        if (filled($filters['product'] ?? null)) {
            $slug = $filters['product'];

            // A flat "which athlete IDs bought this" subquery instead of a
            // whereHas('athlete', fn => whereHas('orders', fn => ...)) chain
            // four relations deep — that chain reproduced a genuine Larastan
            // limitation (couldn't resolve the nested whereHas() generic)
            // even without dot-notation; this both sidesteps it and reads
            // as one clear query instead of a pyramid of closures.
            $athleteIds = Athlete::query()
                ->whereHas(
                    'orders',
                    fn (Builder $q) => $q->where('event_edition_id', $edition->id)
                        ->whereHas('items.product', fn (Builder $q2) => $q2->where('slug', $slug)),
                )
                ->pluck('id');

            $query->whereIn('athlete_id', $athleteIds);
        }

        return $query->orderBy('bib_number')
            ->paginate($perPage)
            ->withQueryString();
    }
}
