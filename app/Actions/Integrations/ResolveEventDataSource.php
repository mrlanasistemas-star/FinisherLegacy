<?php

namespace App\Actions\Integrations;

use App\Enums\OrganizerDataSourceType;
use App\Exceptions\EventDataSourceNotConfiguredException;
use App\Models\EventEdition;
use App\Support\Integrations\ResolvedDataSource;

/**
 * The one place that decides "how does this event receive data" (brief
 * §18/§21/§65) — an edition's own `data_source_type` wins when set;
 * otherwise it inherits the Organizer's default OrganizerDataSource. Sync
 * code never inspects these columns directly.
 */
class ResolveEventDataSource
{
    public function handle(EventEdition $edition): ResolvedDataSource
    {
        $edition->loadMissing(['dataSourceProviderConnection', 'event.organizer.dataSource']);

        if ($edition->data_source_type !== null) {
            return new ResolvedDataSource(
                type: OrganizerDataSourceType::from($edition->data_source_type),
                providerConnection: $edition->dataSourceProviderConnection,
                isOverride: true,
            );
        }

        $organizerSource = $edition->event?->organizer?->dataSource;

        if ($organizerSource !== null && $organizerSource->active) {
            return new ResolvedDataSource(
                type: $organizerSource->type,
                providerConnection: $organizerSource->providerConnection,
                isOverride: false,
            );
        }

        throw new EventDataSourceNotConfiguredException;
    }
}
