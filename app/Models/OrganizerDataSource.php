<?php

namespace App\Models;

use App\Enums\OrganizerDataSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Organizer's default "how we receive data" (brief §16-§17) — an
 * EventEdition can override it via its own data_source_type columns; see
 * App\Actions\Integrations\ResolveEventDataSource.
 */
#[Fillable(['organizer_id', 'type', 'provider_connection_id', 'config', 'active'])]
class OrganizerDataSource extends Model
{
    protected function casts(): array
    {
        return [
            'type' => OrganizerDataSourceType::class,
            'config' => 'array',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organizer, $this> */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /** @return BelongsTo<ProviderConnection, $this> */
    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class);
    }
}
