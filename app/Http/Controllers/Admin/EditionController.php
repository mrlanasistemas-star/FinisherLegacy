<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateEvent;
use App\Http\Controllers\Controller;
use App\Models\EventEdition;
use App\Models\LegacyPlateModel;
use App\Models\Organizer;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProviderConnection;
use App\Models\Sport;
use App\Queries\Admin\GetEventWorkspaceHeader;
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
    /**
     * Events workspace entry (spec §9): every event, newest first, with the
     * two numbers an operator looks for before opening one.
     */
    public function index(Request $request): Response
    {
        $phase = in_array($request->string('phase')->toString(), ['upcoming', 'ongoing', 'finished'], true)
            ? $request->string('phase')->toString()
            : null;
        $today = now()->toDateString();

        $editions = EventEdition::query()
            ->with('event')
            ->withCount(['participants', 'preregistrations'])
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->whereHas(
                'event',
                fn ($eq) => $eq->where('name', 'like', "%{$search}%"),
            ))
            ->when($phase === 'upcoming', fn ($q) => $q->whereDate('event_date', '>', $today))
            ->when($phase === 'ongoing', fn ($q) => $q->whereDate('event_date', $today))
            ->when($phase === 'finished', fn ($q) => $q->whereDate('event_date', '<', $today))
            ->orderByDesc('event_date')
            ->paginate(25)
            ->withQueryString();

        $editions->through(fn (EventEdition $edition) => [
            'id' => $edition->id,
            'event' => $edition->event->name,
            'edition' => $edition->name,
            'event_date' => $edition->event_date->toDateString(),
            'city' => $edition->city,
            'status' => $edition->status->value,
            'phase' => $edition->phase,
            'participants_count' => $edition->participants_count,
            'preregistrations_count' => $edition->preregistrations_count,
        ]);

        return Inertia::render('admin/editions/Index', [
            'editions' => $editions,
            'filters' => ['q' => $request->string('q')->toString(), 'phase' => $phase],
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

        return redirect()->route('admin.events.show', $edition->id);
    }

    /**
     * "Resumen" tab of the event workspace (spec §9). Data sources moved to
     * the "Datos" tab (EventWorkspaceController::data).
     */
    public function show(EventEdition $eventEdition, GetEventWorkspaceHeader $header): Response
    {
        $eventEdition->loadMissing(['event.organizer', 'races']);

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

        return Inertia::render('admin/editions/Show', [
            'workspace' => $header->handle($eventEdition),
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
            ],
            'priceSchedules' => $priceSchedules,
            'legacyPlateModels' => LegacyPlateModel::query()->where('active', true)->get(['id', 'name', 'slug']),
            'products' => Product::query()->where('active', true)->get(['id', 'name', 'type']),
        ]);
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
