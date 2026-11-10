<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Enums\ExternalSyncType;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Integrations\SyncRunResource;
use App\Jobs\SyncExternalEventJob;
use App\Models\ExternalEventMapping;
use App\Models\ExternalSyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The API mirror of App\Http\Controllers\Admin\Integrations\SyncController
 * — same job dispatch (App\Jobs\SyncExternalEventJob), same
 * App\Http\Resources\Api\V1\Integrations\SyncRunResource, never a second
 * sync implementation (docs/adr/0005 §39, §88).
 */
class SyncController extends Controller
{
    use ApiResponses;

    /**
     * 202: the provider sync itself runs on the queue, never inline in
     * this request (docs/api/v1.md §Sync). No `ExternalSyncRun` exists yet
     * at this point — the job creates one the moment it starts — so this
     * responds with the mapping reference, not a run id; poll `latestRun`
     * to see it appear.
     */
    public function store(Request $request, ExternalEventMapping $eventMapping): JsonResponse
    {
        $data = $request->validate([
            'sync_type' => ['nullable', Rule::in(['roster', 'results', 'full'])],
            'full' => ['nullable', 'boolean'],
        ]);

        SyncExternalEventJob::dispatch(
            $eventMapping->id,
            ExternalSyncType::from($data['sync_type'] ?? 'full'),
            (bool) ($data['full'] ?? false),
        );

        return $this->respond(['mapping_id' => $eventMapping->id], __('Sincronización en cola.'), status: 202);
    }

    public function latestRun(ExternalEventMapping $eventMapping): JsonResponse
    {
        $run = ExternalSyncRun::query()
            ->where('provider_connection_id', $eventMapping->provider_connection_id)
            ->where('event_edition_id', $eventMapping->event_edition_id)
            ->latest('started_at')
            ->first();

        abort_unless($run !== null, 404, __('Todavía no hay ninguna sincronización para este evento.'));

        $run->load('providerConnection', 'eventEdition.event');

        return $this->respond(new SyncRunResource($run));
    }

    public function show(ExternalSyncRun $syncRun): JsonResponse
    {
        $syncRun->load('providerConnection', 'eventEdition.event');

        return $this->respond(new SyncRunResource($syncRun));
    }
}
