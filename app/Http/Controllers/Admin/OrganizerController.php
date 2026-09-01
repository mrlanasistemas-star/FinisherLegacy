<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Integrations\TestProviderConnection;
use App\Enums\DataSourcePurpose;
use App\Enums\OrganizerStatus;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\OrganizerDataSource;
use App\Models\ProviderConnection;
use App\Services\ImageProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organizer stops being a bare CRUD (brief §8-§10): the detail page ties
 * Eventos + Datos/Integración together instead of two disconnected
 * screens. An Organizer can have several data sources now (brief §23-§27:
 * "no me deja realmente agregar más") — Manual + File + one or more API
 * connections, each scoped to a purpose, at most one default per purpose.
 * Connection testing reuses App\Actions\Integrations\TestProviderConnection
 * — the same Action the REST API and the Integrations admin page call,
 * never a second implementation.
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
        $organizer->loadMissing(['events.sport', 'dataSources.providerConnection']);

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
            'dataSources' => $organizer->dataSources->map(fn (OrganizerDataSource $source) => $this->dataSourcePayload($source)),
            'providerConnections' => ProviderConnection::query()->orderBy('name')->get(['id', 'name', 'provider_key']),
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

    /**
     * Legacy single-source endpoint — kept for the one caller that still
     * only ever needs "the" default source (brief compat, mirrors
     * Api\V1\Admin\OrganizerDataSourceController::update()). New UI uses
     * storeDataSource()/updateDataSourceEntry() instead.
     */
    public function updateDataSource(Request $request, Organizer $organizer): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['manual', 'file', 'api'])],
            'provider_connection_id' => ['nullable', 'integer', 'exists:provider_connections,id'],
        ]);

        OrganizerDataSource::query()->updateOrCreate(
            ['organizer_id' => $organizer->id, 'is_default' => true, 'purpose' => DataSourcePurpose::Both->value],
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
     * "+ AGREGAR FUENTE DE DATOS" (brief §29) — an Organizer can now have
     * as many sources as it needs, each scoped to a purpose.
     */
    public function storeDataSource(Request $request, Organizer $organizer): RedirectResponse
    {
        $data = $this->validatedSource($request);

        DB::transaction(function () use ($organizer, $data) {
            if ($data['is_default']) {
                $this->clearOtherDefaults($organizer, DataSourcePurpose::from($data['purpose']));
            }

            $organizer->dataSources()->create($data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fuente de datos agregada.']);

        return back();
    }

    public function updateDataSourceEntry(Request $request, OrganizerDataSource $dataSource): RedirectResponse
    {
        $data = $this->validatedSource($request);

        DB::transaction(function () use ($dataSource, $data) {
            if ($data['is_default']) {
                $this->clearOtherDefaults($dataSource->organizer, DataSourcePurpose::from($data['purpose']), except: $dataSource->id);
            }

            $dataSource->update($data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fuente de datos actualizada.']);

        return back();
    }

    /**
     * Never a hard delete (brief §38: "no hard delete si ya tiene
     * historial") — a source that already synced data stays in the
     * record, just stops being usable.
     */
    public function deactivateDataSource(OrganizerDataSource $dataSource): RedirectResponse
    {
        $dataSource->update(['active' => false, 'is_default' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fuente de datos desactivada.']);

        return back();
    }

    /**
     * "Fuentes de datos" nav entry (brief §5/§9) — a flat, at-a-glance list
     * of every Organizer's data sources, each linking into its own
     * Organizer detail "Datos" tab to manage them.
     */
    public function dataSources(): Response
    {
        $organizers = Organizer::query()
            ->with('dataSources.providerConnection')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/data-sources/Index', [
            'organizers' => $organizers->map(fn (Organizer $organizer) => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'sources' => $organizer->dataSources->map(fn (OrganizerDataSource $source) => $this->dataSourcePayload($source)),
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
    private function dataSourcePayload(OrganizerDataSource $source): array
    {
        return [
            'id' => $source->id,
            'name' => $source->name,
            'type' => $source->type->value,
            'purpose' => $source->purpose->value,
            'is_default' => $source->is_default,
            'active' => $source->active,
            'provider_connection' => $source->providerConnection ? [
                'id' => $source->providerConnection->id,
                'name' => $source->providerConnection->name,
                'provider_key' => $source->providerConnection->provider_key,
                'status' => $source->providerConnection->status->value,
                'last_tested_at' => $source->providerConnection->last_tested_at?->diffForHumans(),
                'last_successful_sync_at' => $source->providerConnection->last_successful_sync_at?->diffForHumans(),
            ] : null,
        ];
    }

    /**
     * "Solo una fuente default por purpose" (brief §27) — enforced here at
     * write time, not a DB constraint, since 'both' and a purpose-specific
     * default are allowed to coexist as two separate defaults.
     */
    private function clearOtherDefaults(Organizer $organizer, DataSourcePurpose $purpose, ?int $except = null): void
    {
        $organizer->dataSources()
            ->where('purpose', $purpose->value)
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except))
            ->update(['is_default' => false]);
    }

    /** @return array<string, mixed> */
    private function validatedSource(Request $request): array
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(['manual', 'file', 'api'])],
            'purpose' => ['required', 'string', Rule::in(['participants', 'results', 'both'])],
            'provider_connection_id' => ['nullable', 'integer', 'exists:provider_connections,id'],
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['active'] = $request->boolean('active', true);

        return $data;
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
