<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\EventProviderAdapter;
use App\Services\Integrations\Providers\GenericRestEventProvider;
use App\Services\Integrations\Providers\MockEventProviderAdapter;
use InvalidArgumentException;

/**
 * Resolves a `provider_key` (data, on ProviderConnection) to its adapter
 * (code) — no switch statement scattered across controllers/actions
 * (docs/adr/0005 §Provider registry). Adding a real provider later means
 * adding one line here, not touching the sync pipeline.
 */
class EventProviderRegistry
{
    /**
     * @var array<string, class-string<EventProviderAdapter>>
     */
    private const ADAPTERS = [
        'mock' => MockEventProviderAdapter::class,
        'generic_rest' => GenericRestEventProvider::class,
    ];

    /**
     * Every adapter except 'mock' outside local/testing (product UX
     * consolidation brief §39-§40) — an admin creating a new connection in
     * production should never be offered "mock" as if it were a real
     * provider choice. `get()` below is untouched: an existing mock
     * connection (however it got created) still works everywhere, this
     * only hides the option from *new* connections.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_keys(self::ADAPTERS);

        if (app()->environment(['local', 'testing'])) {
            return $keys;
        }

        return array_values(array_filter($keys, fn (string $key) => $key !== 'mock'));
    }

    public function get(string $providerKey): EventProviderAdapter
    {
        $class = self::ADAPTERS[$providerKey] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Proveedor desconocido: {$providerKey}");
        }

        return app($class);
    }
}
