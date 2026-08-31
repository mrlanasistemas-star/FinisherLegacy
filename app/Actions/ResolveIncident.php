<?php

namespace App\Actions;

use App\Enums\IncidentResolutionType;
use App\Enums\IncidentStatus;
use App\Models\EventIncident;
use App\Models\User;

/**
 * "Resolver" now always records what actually happened — status=resolved
 * with no resolution_type doesn't help anyone (brief §136-§138). Never
 * mutates the underlying entity itself; `before_data`/`after_data` are
 * just a snapshot the caller supplies for audit, this Action only updates
 * the incident row.
 */
class ResolveIncident
{
    /**
     * @param  array<string, mixed>|null  $beforeData
     * @param  array<string, mixed>|null  $afterData
     */
    public function handle(
        EventIncident $incident,
        IncidentResolutionType $resolutionType,
        User $resolvedBy,
        ?string $notes = null,
        ?array $beforeData = null,
        ?array $afterData = null,
    ): EventIncident {
        $incident->update([
            'status' => IncidentStatus::Resolved,
            'resolution_type' => $resolutionType,
            'resolution_notes' => $notes,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'resolved_by' => $resolvedBy->id,
            'resolved_at' => now(),
        ]);

        return $incident->fresh();
    }
}
