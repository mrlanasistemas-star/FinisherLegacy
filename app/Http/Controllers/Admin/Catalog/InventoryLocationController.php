<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagStatus;
use App\Http\Controllers\Controller;
use App\Models\Ally;
use App\Models\EventEdition;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\LegacyTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inventario → Ubicaciones: warehouse, ally shop, event, stand, transit.
 * A location with stock or history is never deleted — it is deactivated.
 */
class InventoryLocationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'type' => $request->string('type')->toString() ?: null,
            'active' => in_array($request->string('active')->toString(), ['1', '0'], true) ? $request->string('active')->toString() : null,
        ];

        $locations = InventoryLocation::query()
            ->with(['ally:id,name', 'eventEdition:id,name'])
            ->withSum('levels as on_hand', 'quantity_on_hand')
            ->withCount(['tags as units_in_stock' => fn ($q) => $q->where('status', LegacyTagStatus::InStock->value)])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$filters['q']}%")->orWhere('slug', 'like', "%{$filters['q']}%")))
            ->when($filters['type'], fn ($q, $type) => $q->where('type', $type))
            ->when($filters['active'] !== null, fn ($q) => $q->where('active', $filters['active'] === '1'))
            ->orderByDesc('active')->orderBy('name')
            ->paginate(24)->withQueryString()
            ->through(fn (InventoryLocation $l) => $this->payload($l) + [
                'on_hand' => (int) $l->getAttribute('on_hand'),
                'units_in_stock' => (int) $l->getAttribute('units_in_stock'),
            ]);

        return Inertia::render('admin/inventory/locations/Index', [
            'locations' => $locations,
            'filters' => $filters,
            ...$this->formOptions(),
        ]);
    }

    public function show(InventoryLocation $location): Response
    {
        $location->load(['ally:id,name', 'eventEdition:id,name']);

        $stock = InventoryLevel::query()
            ->where('inventory_location_id', $location->id)
            ->with('productVariant.product:id,name')
            ->get()
            ->sortBy(fn (InventoryLevel $level) => ($level->productVariant->product->name ?? '').'|'.$level->productVariant->sku)
            ->values();

        $units = LegacyTag::query()
            ->where('inventory_location_id', $location->id)
            ->where('status', LegacyTagStatus::InStock->value)
            ->selectRaw('product_variant_id, count(*) as c')
            ->groupBy('product_variant_id')
            ->pluck('c', 'product_variant_id');

        $movements = InventoryMovement::query()
            ->where('inventory_location_id', $location->id)
            ->with(['actor:id,first_name,last_name', 'productVariant:id,sku'])
            ->latest('id')->limit(25)->get();

        return Inertia::render('admin/inventory/locations/Show', [
            'location' => $this->payload($location),
            'stock' => $stock->map(fn (InventoryLevel $level) => [
                'variant_id' => $level->product_variant_id,
                'product_id' => $level->productVariant->product_id,
                'product' => $level->productVariant->product?->name,
                'variant' => $level->productVariant->name,
                'sku' => $level->productVariant->sku,
                'on_hand' => $level->quantity_on_hand,
                'reserved' => $level->quantity_reserved,
                'units_in_stock' => (int) ($units[$level->product_variant_id] ?? 0),
            ])->values()->all(),
            'movements' => $movements->map(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'sku' => $m->productVariant?->sku,
                'quantity' => $m->quantity,
                'reason' => $m->reason,
                'notes' => $m->notes,
                'actor' => $m->actor?->name,
                'created_at' => $m->created_at->toIso8601String(),
            ])->values(),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        $location = InventoryLocation::create($data);

        activity()->causedBy($request->user())->performedOn($location)->withProperties($data)->log('inventory.location.created');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ubicación creada.']);

        return redirect()->route('admin.catalog.locations.show', $location->id);
    }

    public function update(Request $request, InventoryLocation $location): RedirectResponse
    {
        $data = $this->validated($request);
        $location->update($data);

        activity()->causedBy($request->user())->performedOn($location)->withProperties($data)->log('inventory.location.updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ubicación actualizada.']);

        return back();
    }

    public function destroy(Request $request, InventoryLocation $location): RedirectResponse
    {
        $inUse = $location->tags()->exists()
            || $location->levels()->where(fn ($q) => $q->where('quantity_on_hand', '!=', 0)->orWhere('quantity_reserved', '!=', 0))->exists()
            || InventoryMovement::query()->where('inventory_location_id', $location->id)->exists();

        if ($inUse) {
            $location->update(['active' => false]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'La ubicación tiene historial: se desactivó en lugar de eliminarse.']);

            return back();
        }

        activity()->causedBy($request->user())->withProperties(['name' => $location->name, 'slug' => $location->slug])->log('inventory.location.deleted');
        $location->levels()->delete();
        $location->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ubicación eliminada.']);

        return redirect()->route('admin.catalog.locations.index');
    }

    /** @return array<string, mixed> */
    private function payload(InventoryLocation $l): array
    {
        return [
            'id' => $l->id,
            'name' => $l->name,
            'slug' => $l->slug,
            'type' => $l->type->value,
            'type_label' => $l->type->label(),
            'ally_id' => $l->ally_id,
            'ally' => $l->ally?->name,
            'event_edition_id' => $l->event_edition_id,
            'event_edition' => $l->eventEdition?->name,
            'address' => $l->address,
            'notes' => $l->notes,
            'active' => (bool) $l->active,
        ];
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'types' => InventoryLocationType::options(),
            'allies' => Ally::query()->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (Ally $a) => ['value' => $a->id, 'label' => $a->name])->all(),
            'editions' => EventEdition::query()->latest('id')->limit(300)->get(['id', 'name', 'year'])
                ->map(fn (EventEdition $e) => ['value' => $e->id, 'label' => trim($e->name.' '.($e->year ?? ''))])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::enum(InventoryLocationType::class)],
            'ally_id' => ['nullable', 'integer', 'exists:allies,id', 'required_if:type,ally'],
            'event_edition_id' => ['nullable', 'integer', 'exists:event_editions,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ], ['ally_id.required_if' => 'Una ubicación de aliado necesita el aliado.']);

        $data['active'] ??= true;

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'ubicacion';
        $candidate = $base;
        $i = 2;

        while (InventoryLocation::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }
}
