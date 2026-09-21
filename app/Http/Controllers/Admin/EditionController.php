<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateEvent;
use App\Actions\Integrations\ResolveEventDataSource;
use App\Enums\DataSourcePurpose;
use App\Enums\OrganizerDataSourceType;
use App\Exceptions\EventDataSourceNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Models\EventEdition;
use App\Models\ExternalEventMapping;
use App\Models\LegacyPlateModel;
use App\Models\Organizer;
use App\Models\OrganizerDataSource;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProviderConnection;
use App\Models\Sport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manual event creation + detail (brief §6-§7/§47) — thin, delegates to
 * App\Actions\CreateEvent (the same Action the API's EventController uses,
 * see docs/api/v1.md) rather than duplicating the transaction here.
 */
class EditionController extends Controller
{
    public function index(Request $request): Response
    {
        $editions = EventEdition::query()
            ->with('event')
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->whereHas(
                'event',
                fn ($eq) => $eq->where('name', 'like', "%{$search}%"),
            ))
            ->orderByDesc('event_date')
            ->paginate(25)
            ->withQueryString();

        $editions->through(fn (EventEdition $edition) => [
            'id' => $edition->id,
            'event' => $edition->event->name,
            'edition' => $edition->name,
            'event_date' => $edition->event_date->toDateString(),
            'status' => $edition->status->value,
            'phase' => $edition->phase,
        ]);

        return Inertia::render('admin/editions/Index', [
            'editions' => $editions,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/editions/Create', [
            'sports' => Sport::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'organizers' => Organizer::query()->orderBy('name')->get(['id', 'name']),
            'providerConnections' => ProviderConnection::query()->selectable()->orderBy('name')->get(['id', 'name', 'provider_key']),
        ]);
    }

    public function store(Request $request, CreateEvent $createEvent): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'organizer_id' => ['nullable', 'integer', 'exists:organizers,id'],
            'edition_name' => ['required', 'string', 'max:150'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'event_date' => ['required', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'data_source_type' => ['nullable', 'string', Rule::in(['manual', 'file', 'api'])],
            'data_source_provider_connection_id' => ['nullable', 'integer', 'exists:provider_connections,id'],
            'races' => ['required', 'array', 'min:1'],
            'races.*.name' => ['required', 'string', 'max:100'],
            'races.*.distance_value' => ['nullable', 'numeric', 'min:0'],
            'races.*.distance_unit' => ['nullable', 'string', 'max:10'],
        ]);

        $edition = $createEvent->handle($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento creado.']);

        return redirect()->route('admin.editions.show', $edition->id);
    }

    public function show(EventEdition $eventEdition, ResolveEventDataSource $resolveDataSource): Response
    {
        $eventEdition->loadMissing([
            'event.organizer.dataSources.providerConnection',
            'races', 'dataSourceProviderConnection', 'participantsDataSource.providerConnection', 'resultsDataSource.providerConnection',
        ]);

        $priceSchedules = ProductPriceSchedule::query()
            ->where('event_edition_id', $eventEdition->id)
            ->with('product')
            ->get()
            ->map(fn (ProductPriceSchedule $schedule) => [
                'id' => $schedule->id,
                'product' => $schedule->product->name,
                'price_type' => $schedule->price_type->value,
                'amount_minor' => $schedule->amount_minor,
                'currency' => $schedule->currency,
                'starts_at' => $schedule->starts_at?->toDateTimeString(),
                'ends_at' => $schedule->ends_at?->toDateTimeString(),
                'active' => $schedule->active,
            ]);

        $organizerSources = $eventEdition->event->organizer?->dataSources->where('active', true) ?? collect();
        $syncMapping = ExternalEventMapping::query()->where('event_edition_id', $eventEdition->id)->with('providerConnection')->first();

        return Inertia::render('admin/editions/Show', [
            'edition' => [
                'id' => $eventEdition->id,
                'name' => $eventEdition->name,
                'year' => $eventEdition->year,
                'event_date' => $eventEdition->event_date->toDateString(),
                'city' => $eventEdition->city,
                'state' => $eventEdition->state,
                'country' => $eventEdition->country,
                'timezone' => $eventEdition->timezone,
                'status' => $eventEdition->status->value,
                'phase' => $eventEdition->phase,
                'event' => [
                    'id' => $eventEdition->event->id,
                    'name' => $eventEdition->event->name,
                    'slug' => $eventEdition->event->slug,
                    'organizer' => $eventEdition->event->organizer?->name,
                ],
                'races' => $eventEdition->races->map(fn ($race) => [
                    'id' => $race->id,
                    'name' => $race->name,
                    'distance_value' => $race->distance_value,
                    'distance_unit' => $race->distance_unit,
                ]),
                'data_source' => [
                    'type' => $eventEdition->data_source_type,
                    'provider_connection' => $eventEdition->dataSourceProviderConnection?->name,
                    'inherited' => $eventEdition->data_source_type === null,
                    'participants_data_source_id' => $eventEdition->participants_data_source_id,
                    'results_data_source_id' => $eventEdition->results_data_source_id,
                    'organizer_sources' => $organizerSources->map(fn (OrganizerDataSource $source) => [
                        'id' => $source->id,
                        'name' => $source->name,
                        'type' => $source->type->value,
                        'purpose' => $source->purpose->value,
                        'is_default' => $source->is_default,
                    ])->values(),
                    'resolved_participants' => $this->resolvedSourcePayload($eventEdition, $resolveDataSource, DataSourcePurpose::Participants),
                    'resolved_results' => $this->resolvedSourcePayload($eventEdition, $resolveDataSource, DataSourcePurpose::Results),
                ],
                'sync_mapping' => $syncMapping ? [
                    'id' => $syncMapping->id,
                    'provider_connection' => $syncMapping->providerConnection->name,
                ] : null,
            ],
            'priceSchedules' => $priceSchedules,
            'legacyPlateModels' => LegacyPlateModel::query()->where('active', true)->get(['id', 'name', 'slug']),
            'dataSourceTypes' => array_map(fn ($case) => $case->value, OrganizerDataSourceType::cases()),
            'products' => Product::query()->where('active', true)->get(['id', 'name', 'type']),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function resolvedSourcePayload(EventEdition $edition, ResolveEventDataSource $resolveDataSource, DataSourcePurpose $purpose): ?array
    {
        try {
            $resolved = $resolveDataSource->handle($edition, $purpose);
        } catch (EventDataSourceNotConfiguredException) {
            return null;
        }

        return [
            'type' => $resolved->type->value,
            'provider_connection' => $resolved->providerConnection?->name,
            'is_override' => $resolved->isOverride,
        ];
    }

    /**
     * Per-purpose source selection (brief §28/§39) — null means "inherit
     * the Organizer's default for that purpose", exactly the same
     * semantics App\Actions\Integrations\ResolveEventDataSource already
     * applies when reading these columns.
     */
    public function updateDataSources(Request $request, EventEdition $eventEdition): RedirectResponse
    {
        $data = $request->validate([
            'participants_data_source_id' => ['nullable', 'integer', 'exists:organizer_data_sources,id'],
            'results_data_source_id' => ['nullable', 'integer', 'exists:organizer_data_sources,id'],
        ]);

        $eventEdition->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fuentes de datos del evento actualizadas.']);

        return back();
    }

    /**
     * One of the three Legacy Plate price windows for this event (brief
     * §17/§24/§77) — a plain create against ProductPriceSchedule, no
     * dedicated Action needed for this simple case (matches
     * AdminOrganizerController's own direct-model-write pattern).
     */
    public function storePriceSchedule(Request $request, EventEdition $eventEdition): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'price_type' => ['required', 'string', Rule::in(['early_presale', 'kit_pickup', 'event_day', 'standard'])],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        ProductPriceSchedule::create([
            'product_id' => $data['product_id'],
            'event_edition_id' => $eventEdition->id,
            'price_type' => $data['price_type'],
            'amount_minor' => $data['amount_minor'],
            'currency' => $data['currency'] ?? config('finisher.commerce.default_currency', 'MXN'),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Precio agregado.']);

        return back();
    }
}
