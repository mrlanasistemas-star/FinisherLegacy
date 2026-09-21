<?php

namespace App\Http\Controllers\Admin\Integrations;

use App\Actions\Integrations\CreateEventFromExternalData;
use App\Actions\Integrations\LinkExternalEvent;
use App\Actions\Integrations\TestProviderConnection;
use App\Enums\ProviderConnectionStatus;
use App\Http\Controllers\Controller;
use App\Models\EventEdition;
use App\Models\ProviderConnection;
use App\Models\Sport;
use App\Services\Integrations\EventProviderRegistry;
use App\Support\Integrations\RejectsPrivateNetworkUrls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin — validate, call an Action, render/redirect (docs/adr/0005 §Web
 * controllers). Never touches `credentials` in a response payload (§20-21).
 */
class ProviderConnectionController extends Controller
{
    public function index(EventProviderRegistry $registry): Response
    {
        $connections = ProviderConnection::query()
            ->withCount('eventMappings')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ProviderConnection $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'provider_key' => $c->provider_key,
                'status' => $c->status->value,
                'last_tested_at' => $c->last_tested_at?->diffForHumans(),
                'last_successful_sync_at' => $c->last_successful_sync_at?->diffForHumans(),
                'event_mappings_count' => $c->event_mappings_count,
            ]);

        return Inertia::render('admin/integrations/Index', [
            'connections' => $connections,
            'providerKeys' => $registry->keys(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedConnection($request);

        ProviderConnection::create([
            'uuid' => (string) Str::uuid(),
            'provider_key' => $data['provider_key'],
            'name' => $data['name'],
            'base_url' => $data['base_url'],
            'credentials' => $data['api_key'],
            'settings' => $data['settings'] + ['chunk_size' => 250],
            'status' => ProviderConnectionStatus::Untested,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conexión creada.']);

        return back();
    }

    /**
     * Never a required field: an empty `api_key` means "keep the current
     * credential" (brief §34: "nunca devolver secrets al frontend", so the
     * frontend can't send back what it never received) — only a non-empty
     * value replaces it.
     */
    public function update(Request $request, ProviderConnection $providerConnection): RedirectResponse
    {
        $data = $this->validatedConnection($request);

        $providerConnection->update([
            'name' => $data['name'],
            'base_url' => $data['base_url'],
            'settings' => $data['settings'] + Arr::only($providerConnection->settingsArray(), ['chunk_size']),
            ...($data['api_key'] !== null ? ['credentials' => $data['api_key']] : []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conexión actualizada.']);

        return back();
    }

    public function test(ProviderConnection $providerConnection, TestProviderConnection $action): RedirectResponse
    {
        $result = $action->handle($providerConnection);

        Inertia::flash('toast', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->success
                ? "Conexión OK ({$result->latencyMs}ms)."
                : ($result->message ?? 'La conexión falló.'),
        ]);

        return back();
    }

    public function show(ProviderConnection $providerConnection, EventProviderRegistry $registry): Response
    {
        $adapter = $registry->get($providerConnection->provider_key);

        $availableEvents = [];
        $listError = null;

        try {
            $availableEvents = collect($adapter->listEvents($providerConnection))->map(fn ($e) => [
                'external_id' => $e->externalId,
                'name' => $e->name,
                'date' => $e->date,
                'city' => $e->city,
            ])->all();
        } catch (\Throwable $e) {
            $listError = 'No se pudo listar eventos del proveedor.';
        }

        $mappings = $providerConnection->eventMappings()->with('eventEdition.event')->get()
            ->map(function ($mapping) use ($providerConnection) {
                $lastRun = $providerConnection->syncRuns()
                    ->where('event_edition_id', $mapping->event_edition_id)
                    ->latest('started_at')
                    ->first();

                return [
                    'id' => $mapping->id,
                    'external_event_id' => $mapping->external_event_id,
                    'event' => $mapping->eventEdition?->event?->name,
                    'edition' => $mapping->eventEdition?->name,
                    'event_edition_id' => $mapping->event_edition_id,
                    'last_sync' => $lastRun ? [
                        'id' => $lastRun->id,
                        'status' => $lastRun->status->value,
                        'started_at' => $lastRun->started_at?->diffForHumans(),
                        'participants_received' => $lastRun->participants_received,
                        'results_received' => $lastRun->results_received,
                        'errors_count' => $lastRun->errors_count,
                    ] : null,
                ];
            });

        return Inertia::render('admin/integrations/Show', [
            'connection' => [
                'id' => $providerConnection->id,
                'name' => $providerConnection->name,
                'provider_key' => $providerConnection->provider_key,
                'status' => $providerConnection->status->value,
                'base_url' => $providerConnection->base_url,
                'last_tested_at' => $providerConnection->last_tested_at?->diffForHumans(),
                'last_successful_sync_at' => $providerConnection->last_successful_sync_at?->diffForHumans(),
                // `settings` (brief §33: auth type, endpoints, field
                // mapping) is config, not a secret — safe to echo back for
                // the edit form to prefill. `credentials` never appears
                // here; `has_credentials` only says whether one is stored.
                'settings' => $providerConnection->settingsArray(),
                'has_credentials' => filled($providerConnection->credentials),
            ],
            'availableEvents' => $availableEvents,
            'listError' => $listError,
            'mappings' => $mappings,
            'editions' => EventEdition::with('event')->orderByDesc('event_date')->limit(100)->get()
                ->map(fn (EventEdition $edition) => ['id' => $edition->id, 'name' => $edition->event->name.' — '.$edition->name]),
            'sports' => Sport::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function linkEvent(
        Request $request,
        ProviderConnection $providerConnection,
        LinkExternalEvent $linkEvent,
        CreateEventFromExternalData $createEvent,
        EventProviderRegistry $registry,
    ): RedirectResponse {
        $data = $request->validate([
            'external_event_id' => ['required', 'string'],
            'mode' => ['required', Rule::in(['link', 'create'])],
            'event_edition_id' => ['required_if:mode,link', 'nullable', 'integer', 'exists:event_editions,id'],
            'sport_id' => ['required_if:mode,create', 'nullable', 'integer', 'exists:sports,id'],
        ]);

        DB::transaction(function () use ($data, $providerConnection, $linkEvent, $createEvent, $registry) {
            if ($data['mode'] === 'link') {
                $edition = EventEdition::findOrFail((int) $data['event_edition_id']);
                $linkEvent->handle($providerConnection, $data['external_event_id'], $edition);

                return;
            }

            $adapter = $registry->get($providerConnection->provider_key);
            $eventData = $adapter->fetchEvent($providerConnection, $data['external_event_id']);
            $sport = Sport::findOrFail((int) $data['sport_id']);
            $createEvent->handle($eventData, $providerConnection, $sport);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento vinculado.']);

        return back();
    }

    /**
     * Generic REST's config lives in `settings` (brief §33), never hardcoded
     * per-provider fields — everything except name/base_url/api_key is
     * only ever meaningful for provider_key=generic_rest, so it's optional
     * here and simply omitted from `settings` for any other provider.
     *
     * @return array{provider_key: string, name: string, base_url: ?string, api_key: ?string, settings: array<string, mixed>}
     */
    private function validatedConnection(Request $request): array
    {
        $data = $request->validate([
            'provider_key' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'auth_type' => ['nullable', 'string', Rule::in(['none', 'bearer', 'api_key_header', 'basic'])],
            'api_key_header' => ['nullable', 'string', 'max:100'],
            'basic_username' => ['nullable', 'string', 'max:100'],
            'test_endpoint' => ['nullable', 'string', 'max:255'],
            'events_endpoint' => ['nullable', 'string', 'max:255'],
            'event_endpoint' => ['nullable', 'string', 'max:255'],
            'participants_endpoint' => ['nullable', 'string', 'max:255'],
            'results_endpoint' => ['nullable', 'string', 'max:255'],
            'event_field_mapping' => ['nullable', 'array'],
            'participant_field_mapping' => ['nullable', 'array'],
            'result_field_mapping' => ['nullable', 'array'],
        ]);

        // Never trust an admin-typed URL as automatically safe to fetch
        // (brief §41-§42) — same guard GenericRestEventProvider re-checks
        // on every outbound request, applied here too so a bad config is
        // rejected immediately instead of only failing at first sync.
        if (filled($data['base_url'] ?? null) && ! RejectsPrivateNetworkUrls::isSafe($data['base_url'])) {
            throw ValidationException::withMessages([
                'base_url' => 'Esta URL no está permitida: debe ser http/https pública y no puede apuntar a una red privada o local.',
            ]);
        }

        $settings = $data['provider_key'] === 'generic_rest'
            ? array_filter([
                'auth_type' => $data['auth_type'] ?? 'none',
                'api_key_header' => $data['api_key_header'] ?? null,
                'basic_username' => $data['basic_username'] ?? null,
                'test_endpoint' => $data['test_endpoint'] ?? null,
                'events_endpoint' => $data['events_endpoint'] ?? null,
                'event_endpoint' => $data['event_endpoint'] ?? null,
                'participants_endpoint' => $data['participants_endpoint'] ?? null,
                'results_endpoint' => $data['results_endpoint'] ?? null,
                'event_field_mapping' => $data['event_field_mapping'] ?? null,
                'participant_field_mapping' => $data['participant_field_mapping'] ?? null,
                'result_field_mapping' => $data['result_field_mapping'] ?? null,
            ], fn ($value) => $value !== null)
            : [];

        return [
            'provider_key' => $data['provider_key'],
            'name' => $data['name'],
            'base_url' => $data['base_url'] ?? null,
            'api_key' => filled($data['api_key'] ?? null) ? $data['api_key'] : null,
            'settings' => $settings,
        ];
    }
}
