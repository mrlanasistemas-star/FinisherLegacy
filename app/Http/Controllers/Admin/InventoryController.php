<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Commerce\Inventory\AdjustStock;
use App\Enums\InventoryAdjustmentReason;
use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Queries\Commerce\InventoryStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comercio → Inventario (spec §12-§15). The table answers PRODUCTO,
 * VARIANTE, TALLA, COLOR, STOCK, RESERVADO, DISPONIBLE, VENDIDO, ALERTA
 * without opening products one by one; the matrix shows apparel as
 * colour × size. Every change goes through App\Actions\Commerce\Inventory\
 * AdjustStock (row lock + audited movement with user and reason) — this
 * controller never writes a quantity.
 *
 * Served at /admin/comercio/inventario and, unchanged, /admin/inventory.
 */
class InventoryController extends Controller
{
    public function index(Request $request, InventoryStock $stock): Response
    {
        $state = in_array($request->string('estado')->toString(), ['low', 'out', 'attention', 'ok'], true)
            ? $request->string('estado')->toString()
            : null;
        $view = $request->string('vista')->toString() === 'matriz' ? 'matrix' : 'table';
        $search = trim($request->string('q')->toString());

        return Inertia::render('admin/commerce/Inventory', [
            'view' => $view,
            'filters' => ['q' => $search, 'estado' => $state],
            'counts' => $stock->counts(),
            'rows' => $view === 'table' ? $stock->table($search, $state) : null,
            'matrix' => $view === 'matrix' ? $stock->matrix($search) : null,
            'reasons' => InventoryAdjustmentReason::options(),
            'defaultMinimum' => (int) config('finisher.commerce.default_minimum_stock', 5),
        ]);
    }

    public function adjust(Request $request, AdjustStock $adjust): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'inventory_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            // `type` is the original field name (receive|adjustment); `mode`
            // adds the physical count.
            'mode' => ['nullable', 'string', Rule::in(AdjustStock::MODES)],
            'type' => ['nullable', 'string', Rule::in(['receive', 'adjustment'])],
            'quantity' => ['required', 'integer', 'between:-100000,100000'],
            'reason' => ['nullable', 'string', Rule::enum(InventoryAdjustmentReason::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $mode = $data['mode'] ?? (($data['type'] ?? 'adjustment') === 'receive' ? 'receive' : 'adjust');
        $reason = isset($data['reason'])
            ? InventoryAdjustmentReason::from($data['reason'])
            : match ($mode) {
                'receive' => InventoryAdjustmentReason::Receipt,
                'count' => InventoryAdjustmentReason::Count,
                default => InventoryAdjustmentReason::Correction,
            };

        if ($mode === 'adjust' && (int) $data['quantity'] === 0) {
            return back()->withErrors(['quantity' => 'Indica cuántas unidades sumar o restar.']);
        }

        $level = $adjust->handle(
            ProductVariant::query()->findOrFail($data['product_variant_id']),
            isset($data['inventory_location_id']) ? InventoryLocation::query()->findOrFail($data['inventory_location_id']) : null,
            $mode,
            (int) $data['quantity'],
            $request->user(),
            $reason,
            $data['notes'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventario actualizado: '.$level->availableQuantity().' disponibles.']);

        return back();
    }

    /**
     * Last movements of one variant, for the adjust drawer's history.
     */
    public function movements(ProductVariant $variant): JsonResponse
    {
        $movements = InventoryMovement::query()
            ->where('product_variant_id', $variant->id)
            ->with('actor:id,first_name,last_name')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'reason' => $m->reason !== null ? (InventoryAdjustmentReason::tryFrom($m->reason)?->label() ?? $m->reason) : null,
                'quantity' => $m->quantity,
                'notes' => $m->notes,
                'actor' => $m->actor?->name,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $movements]);
    }

    public function updateMinimum(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate(['minimum_stock' => ['nullable', 'integer', 'min:0', 'max:100000']]);
        $variant->update(['minimum_stock' => $data['minimum_stock']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mínimo de reposición actualizado.']);

        return back();
    }
}
