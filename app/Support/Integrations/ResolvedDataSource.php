<?php

namespace App\Support\Integrations;

use App\Enums\OrganizerDataSourceType;
use App\Models\ProviderConnection;

final class ResolvedDataSource
{
    public function __construct(
        public readonly OrganizerDataSourceType $type,
        public readonly ?ProviderConnection $providerConnection,
        /** Whether this came from the edition's own override or the Organizer's default. */
        public readonly bool $isOverride,
    ) {}
}
