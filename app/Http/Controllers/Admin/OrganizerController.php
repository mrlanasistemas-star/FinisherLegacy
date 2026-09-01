<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Integrations\TestProviderConnection;
use App\Enums\OrganizerStatus;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\OrganizerDataSource;
use App\Models\ProviderConnection;
use App\Services\ImageProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organizer stops being a bare CRUD (brief §8-§10): the detail page ties
 * Eventos + Datos/Integración together instead of two disconnected
 * screens. Data source writes go straight to the model here (matches this
 * controller's own existing pattern), and connection testing reuses
 * App\Actions\Integrations\TestProviderConnection — the same Action the
 * REST API and the Integrations admin page call, never a second
 * implementation.
 */
class OrganizerController extends Controller
{
    public function __construct(private readonly ImageProcessingService $images) {}

    public function index(Request $request): Response
    {
        $organizers = Organizer::query()
            ->withCount('events')
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $organizers->through(fn (Organizer $organizer) => [
            'id' => $organizer->id,
            'name' => $organizer->name,
            'legal_name' => $organizer->legal_name,
            'email' => $organizer->email ?? '—',
            'phone' => $organizer->phone,
            'website' => $organizer->website ?? '—',
            'status' => $organizer->status->value,
            'events_count' => $organizer->events_count,
            'logo_url' => $organizer->logo_path ? Storage::disk('public')->url($organizer->logo_path) : null,
        ]);

        return Inertia::render('admin/organizers/Index', [
            'organizers' => $organizers,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function show(Organizer $organizer): Response
    {
        $organizer->loadMissing(['events.sport', 'dataSource.providerConnection']);

        return Inertia::render('admin/organizers/Show', [
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'legal_name' => $organizer->legal_name,
                'email' => $organizer->email,
                'phone' => $organizer->phone,
                'website' => $organizer->website,
                'status' => $organizer->status->value,
                'logo_url' => $organizer->logo_path ? Storage::disk('public')->url($organizer->logo_path) : null,
            ],
            'events' => $organizer->events->map(fn ($event) => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'sport' => $event->sport->name,
                'status' => $event->status->value,
            ]),
            'dataSource' => $organizer->dataSource ? [
                'type' => $organizer->dataSource->type->value,
                'active' => $organizer->dataSource->active,
                'provider_connection' => $organizer->dataSource->providerConnection ? [
                    'id' => $organizer->dataSource->providerConnection->id,
                    'name' => $organizer->dataSource->providerConnection->name,
                    'provider_key' => $organizer->dataSource->providerConnection->provider_key,
                    'status' => $organizer->dataSource->providerConnection->status->value,
                    'last_tested_at' => $organizer->dataSource->providerConnection->last_tested_at?->diffForHumans(),
                    'last_successful_sync_at' => $organizer->dataSource->providerConnection->last_successful_sync_at?->diffForHumans(),
                ] : null,
            ] : null,
            'providerConnections' => ProviderConnection::query()->selectable()->orderBy('name')->get(['id', 'name', 'provider_key']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(5));

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $this->images->process($request->file('logo'), 'organizers', withThumbnail: false, cropSquare: 512)['display_path'];
        }

        Organizer::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organizador creado.']);

        return back();
    }

    public function update(Request $request, Organizer $organizer): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('logo')) {
            if ($organizer->logo_path) {
                $this->images->delete([$organizer->logo_path]);
            }

            $data['logo_path'] = $this->images->process($request->file('logo'), 'organizers', withThumbnail: false, cropSquare: 512)['display_path'];
        }

        $organizer->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organizador actualizado.']);

        return back();
    }

    public function updateDataSource(Request $request, Organizer $organizer): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['manual', 'file', 'api'])],
            'provider_connection_id' => ['nullable', 'integer', 'exists:provider_connections,id'],
        ]);

        OrganizerDataSource::query()->updateOrCreate(
            ['organizer_id' => $organizer->id],
            [
                'type' => $data['type'],
                'provider_connection_id' => $data['provider_connection_id'] ?? null,
                'active' => true,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fuente de datos actualizada.']);

        return back();
    }

    /**
     * "Fuentes de datos" nav entry (brief §5/§9) — a flat, at-a-glance list
     * of every Organizer's current data source, each linking into its own
     * Organizer detail "Datos" tab to actually change it (no separate CRUD
     * surface for OrganizerDataSource itself).
     */
    public function dataSources(): Response
    {
        $organizers = Organizer::query()
            ->with('dataSource.providerConnection')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/data-sources/Index', [
            'organizers' => $organizers->map(fn (Organizer $organizer) => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'data_source' => $organizer->dataSource ? [
                    'type' => $organizer->dataSource->type->value,
                    'provider_connection' => $organizer->dataSource->providerConnection?->name,
                    'status' => $organizer->dataSource->providerConnection?->status->value,
                ] : null,
            ]),
        ]);
    }

    public function testConnection(ProviderConnection $providerConnection, TestProviderConnection $action): RedirectResponse
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

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::enum(OrganizerStatus::class)],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ]);
    }
}
