<?php

namespace App\Models;

use App\Enums\DataSourcePurpose;
use App\Enums\OrganizerDataSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of an Organizer's ways to receive data (brief §16-§17/§23-§27) — an
 * Organizer can now have many of these (Manual + File + one or more API
 * connections), each scoped to a purpose and at most one marked default
 * per (organizer, purpose). An EventEdition can pick one of these
 * directly, or fall back to the Organizer's default for that purpose; see
 * App\Actions\Integrations\ResolveEventDataSource.
 */
#[Fillable(['organizer_id', 'name', 'type', 'purpose', 'provider_connection_id', 'config', 'active', 'is_default'])]
class OrganizerDataSource extends Model
{
    protected function casts(): array
    {
        return [
            'type' => OrganizerDataSourceType::class,
            'purpose' => DataSourcePurpose::class,
            'config' => 'array',
            'active' => 'boolean',
            'is_default' => 'boolean',
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
