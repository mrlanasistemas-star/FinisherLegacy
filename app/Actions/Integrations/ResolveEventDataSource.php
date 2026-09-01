<?php

namespace App\Actions\Integrations;

use App\Enums\DataSourcePurpose;
use App\Enums\OrganizerDataSourceType;
use App\Exceptions\EventDataSourceNotConfiguredException;
use App\Models\EventEdition;
use App\Models\OrganizerDataSource;
use App\Support\Integrations\ResolvedDataSource;
use Illuminate\Support\Collection;

/**
 * The one place that decides "how does this event receive data" (brief
 * §18/§21/§28/§65) — precedence, highest first:
 *   1. The edition's own legacy data_source_type override (unchanged
 *      behavior for every existing caller that doesn't pass a $purpose).
 *   2. The edition's specific participants_data_source_id /
 *      results_data_source_id pick (brief §28/§39), when $purpose is given.
 *   3. The Organizer's default OrganizerDataSource for that purpose,
 *      falling back to its 'both' default.
 * Sync code never inspects these columns directly.
 */
class ResolveEventDataSource
{
    public function handle(EventEdition $edition, ?DataSourcePurpose $purpose = null): ResolvedDataSource
    {
        $edition->loadMissing(['dataSourceProviderConnection', 'participantsDataSource.providerConnection', 'resultsDataSource.providerConnection', 'event.organizer.dataSources']);

        if ($edition->data_source_type !== null) {
            return new ResolvedDataSource(
                type: OrganizerDataSourceType::from($edition->data_source_type),
                providerConnection: $edition->dataSourceProviderConnection,
                isOverride: true,
            );
        }

        $picked = match ($purpose) {
            DataSourcePurpose::Participants => $edition->participantsDataSource,
            DataSourcePurpose::Results => $edition->resultsDataSource,
            default => null,
        };

        if ($picked !== null && $picked->active) {
            return new ResolvedDataSource(
                type: $picked->type,
                providerConnection: $picked->providerConnection,
                isOverride: true,
            );
        }

        $organizerSource = $this->organizerDefault($edition, $purpose);

        if ($organizerSource !== null) {
            return new ResolvedDataSource(
                type: $organizerSource->type,
                providerConnection: $organizerSource->providerConnection,
                isOverride: false,
            );
        }

        throw new EventDataSourceNotConfiguredException;
    }

    private function organizerDefault(EventEdition $edition, ?DataSourcePurpose $purpose): ?OrganizerDataSource
    {
        $organizer = $edition->event?->organizer;

        /** @var Collection<int, OrganizerDataSource> $sources */
        $sources = $organizer === null ? new Collection : $organizer->dataSources;
        $active = $sources->where('active', true)->where('is_default', true);

        if ($purpose !== null) {
            $exact = $active->first(fn ($s) => $s->purpose === $purpose);

            if ($exact !== null) {
                return $exact;
            }
        }

        return $active->first(fn ($s) => $s->purpose === DataSourcePurpose::Both);
    }
}
