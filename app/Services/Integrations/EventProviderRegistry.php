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
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::ADAPTERS);
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
