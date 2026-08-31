<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\ProductVariant;
use App\Services\Commerce\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock per SKU/variant (brief §19/§37-§38) — every mutation goes through
 * App\Services\Commerce\InventoryService, never a direct column write, so
 * the movement ledger always stays truthful.
 */
class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $levels = InventoryLevel::query()
            ->with(['productVariant.product', 'inventoryLocation'])
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->whereHas(
                'productVariant',
                fn ($vq) => $vq->where('sku', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"),
            ))
            ->paginate(30)
            ->withQueryString();

        $levels->through(fn (InventoryLevel $level) => [
            'id' => $level->id,
            'product' => $level->productVariant->product->name,
            'variant' => $level->productVariant->name,
            'sku' => $level->productVariant->sku,
            'location' => $level->inventoryLocation->name,
            'quantity_on_hand' => $level->quantity_on_hand,
            'quantity_reserved' => $level->quantity_reserved,
            'available' => $level->availableQuantity(),
        ]);

        return Inertia::render('admin/inventory/Index', [
            'levels' => $levels,
            'filters' => ['q' => $request->string('q')->toString()],
            'variants' => ProductVariant::query()->with('product')->orderBy('sku')->get()
                ->map(fn (ProductVariant $v) => ['id' => $v->id, 'label' => "{$v->product->name} — {$v->name} ({$v->sku})"]),
            'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function adjust(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'inventory_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'type' => ['required', 'string', Rule::in(['receive', 'adjustment'])],
            'quantity' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $variant = ProductVariant::query()->whereKey($data['product_variant_id'])->firstOrFail();
        $location = InventoryLocation::query()->whereKey($data['inventory_location_id'])->firstOrFail();
        $quantity = (int) $data['quantity'];

        if (InventoryMovementType::from($data['type']) === InventoryMovementType::Receive) {
            $inventory->receive($variant, $location, abs($quantity), $data['notes'] ?? null, $request->user());
        } else {
            $inventory->adjust($variant, $location, $quantity, $data['notes'] ?? null, $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventario actualizado.']);

        return back();
    }
}
