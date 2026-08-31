<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizerDataSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes `ProviderConnection.credentials` (brief §50/§181).
 *
 * @mixin OrganizerDataSource
 */
class OrganizerDataSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'active' => $this->active,
            'provider_connection' => $this->providerConnection ? [
                'id' => $this->providerConnection->id,
                'name' => $this->providerConnection->name,
                'provider_key' => $this->providerConnection->provider_key,
                'status' => $this->providerConnection->status->value,
                'last_tested_at' => $this->providerConnection->last_tested_at?->toIso8601String(),
                'last_successful_sync_at' => $this->providerConnection->last_successful_sync_at?->toIso8601String(),
            ] : null,
        ];
    }
}
