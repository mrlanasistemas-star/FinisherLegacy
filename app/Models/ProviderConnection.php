<?php

namespace App\Models;

use App\Enums\ProviderConnectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configured connection to an external event/timing provider —
 * credentials + bookkeeping only. The provider *implementation* (how to
 * talk to it) is code, resolved by `provider_key` through
 * App\Services\Integrations\EventProviderRegistry. See
 * docs/adr/0005-unified-event-ingestion.md.
 */
#[Fillable(['uuid', 'provider_key', 'name', 'base_url', 'credentials', 'settings', 'status', 'last_tested_at', 'last_successful_sync_at'])]
class ProviderConnection extends Model
{
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted',
            'settings' => 'encrypted:array',
            'status' => ProviderConnectionStatus::class,
            'last_tested_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
        ];
    }

    /** @return HasMany<ExternalEventMapping, $this> */
    public function eventMappings(): HasMany
    {
        return $this->hasMany(ExternalEventMapping::class);
    }

    /** @return HasMany<ExternalSyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(ExternalSyncRun::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsArray(): array
    {
        return $this->settings ?? [];
    }

    /**
     * Whether this connection talks to a real provider — a "Mock Event
     * Provider" is a dev/test fixture (App\Services\Integrations\Providers\
     * MockEventProviderAdapter), never something an organizer's real data
     * should come from (product UX consolidation brief §39-§41).
     */
    public function isMock(): bool
    {
        return $this->provider_key === 'mock';
    }

    /**
     * Every real connection, plus mock ones only where they're actually
     * useful (local/testing) — outside those environments a mock
     * connection must not appear as a selectable "real" data source
     * anywhere an organizer/event picks one (brief §40). Existing mock
     * connections are never deleted by this — it's a query scope, not a
     * cleanup — so fixtures tests rely on stay intact (brief §40: "No
     * borrar fixtures").
     *
     * @param  Builder<ProviderConnection>  $query
     * @return Builder<ProviderConnection>
     */
    public function scopeSelectable(Builder $query): Builder
    {
        if (app()->environment(['local', 'testing'])) {
            return $query;
        }

        return $query->where('provider_key', '!=', 'mock');
    }
}
