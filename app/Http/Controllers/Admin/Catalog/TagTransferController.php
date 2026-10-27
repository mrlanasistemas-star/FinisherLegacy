<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\LegacyTags\TransferTags;
use App\Enums\LegacyTagStatus;
use App\Exceptions\ProductOutOfStockException;
use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\ProductVariant;
use App\Support\LegacyTags\TagCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Inventario → Transferir: N pieces of a SKU (oldest first) or exact
 * scanned pieces between locations, through TransferTags (paired
 * transfer_out / transfer_in movements + per-unit history).
 */
class TagTransferController extends Controller
{
    public function create(): Response
    {
        $available = LegacyTag::query()->toBase()->where('status', LegacyTagStatus::InStock->value)
            ->selectRaw('inventory_location_id, product_variant_id, count(*) as c')
            ->groupBy('inventory_location_id', 'product_variant_id')->get();

        return Inertia::render('admin/inventory/Transfer', [
            'locations' => InventoryLocation::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'type'])
                ->map(fn (InventoryLocation $l) => ['value' => $l->id, 'label' => $l->name, 'description' => $l->type->label()])->all(),
            'variants' => ProductVariant::query()->whereHas('product', fn ($q) => $q->where('tracks_units', true))
                ->with('product:id,name')->orderBy('sku')->limit(1000)->get(['id', 'sku', 'name', 'product_id'])
                ->map(fn (ProductVariant $v) => ['value' => $v->id, 'label' => $v->sku, 'description' => $v->product?->name])->all(),
            // "location:variant" => in-stock units, so the form can say how many are available.
            'available' => $available->mapWithKeys(fn ($r) => [$r->inventory_location_id.':'.$r->product_variant_id => (int) $r->c]),
            'result' => session('transfer_result'),
        ]);
    }

    public function store(Request $request, TransferTags $transfer): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['quantity', 'units'])],
            'to_location_id' => ['required', 'integer', Rule::exists('inventory_locations', 'id')->where('active', true)],
            'from_location_id' => ['required_if:mode,quantity', 'nullable', 'integer', 'exists:inventory_locations,id', 'different:to_location_id'],
            'product_variant_id' => ['required_if:mode,quantity', 'nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required_if:mode,quantity', 'nullable', 'integer', 'min:1', 'max:5000'],
            'codes' => ['required_if:mode,units', 'nullable', 'string', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'from_location_id.different' => 'El origen y el destino deben ser distintos.',
        ]);

        $to = InventoryLocation::query()->whereKey($data['to_location_id'])->firstOrFail();

        try {
            if ($data['mode'] === 'quantity') {
                $moved = $transfer->quantity(
                    ProductVariant::query()->whereKey($data['product_variant_id'])->firstOrFail(),
                    InventoryLocation::query()->whereKey($data['from_location_id'])->firstOrFail(),
                    $to,
                    (int) $data['quantity'],
                    $request->user(),
                    $data['notes'] ?? null,
                );
            } else {
                $moved = $transfer->units($this->resolveCodes((string) $data['codes'], $data['from_location_id'] ?? null), $to, $request->user(), $data['notes'] ?? null);
            }
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$data['mode'] === 'quantity' ? 'quantity' : 'codes' => $e->getMessage()]);
        } catch (ProductOutOfStockException) {
            // Units and aggregate stock disagree at the origin (a manual adjust) — nothing moved.
            throw ValidationException::withMessages([$data['mode'] === 'quantity' ? 'quantity' : 'codes' => 'El stock registrado en el origen no alcanza; revisa el inventario de esa ubicación.']);
        }

        $summary = $this->summary($moved, $to);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$summary['count']} piezas transferidas a {$to->name}."]);

        return back()->with('transfer_result', $summary);
    }

    /**
     * Pasted / scanned codes, URLs or serials → tag ids. Unknown entries and
     * pieces not at the chosen origin are rejected as a validation error.
     *
     * @return list<int>
     */
    private function resolveCodes(string $raw, ?int $fromLocationId): array
    {
        $entries = collect(preg_split('/[\s,;]+/', $raw) ?: [])->map(fn ($v) => trim((string) $v))->filter()->unique()->values();

        if ($entries->isEmpty()) {
            throw ValidationException::withMessages(['codes' => 'Escanea o pega al menos un código.']);
        }

        $codes = $entries->map(fn ($v) => TagCode::normalize($v))->filter()->values();
        $serials = $entries->map(fn ($v) => strtoupper($v))->values();

        $tags = LegacyTag::query()
            ->where(fn ($q) => $q->whereIn('public_code', $codes)->orWhereIn('serial', $serials))
            ->get(['id', 'serial', 'public_code', 'inventory_location_id']);

        $unknown = $entries->reject(fn ($v) => $tags->contains(fn ($t) => $t->serial === strtoupper($v) || $t->public_code === TagCode::normalize($v)));

        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages(['codes' => 'Códigos no encontrados: '.$unknown->take(10)->implode(', ')]);
        }

        if ($fromLocationId !== null) {
            $elsewhere = $tags->where('inventory_location_id', '!=', $fromLocationId);

            if ($elsewhere->isNotEmpty()) {
                throw ValidationException::withMessages(['codes' => 'No están en el origen elegido: '.$elsewhere->pluck('serial')->take(10)->implode(', ')]);
            }
        }

        return array_values($tags->map(fn (LegacyTag $t) => $t->id)->all());
    }

    /**
     * @param  Collection<int, LegacyTag>  $moved
     * @return array<string, mixed>
     */
    private function summary(Collection $moved, InventoryLocation $to): array
    {
        $moved = LegacyTag::query()->whereIn('id', $moved->pluck('id'))->with('productVariant:id,sku')->orderBy('id')->get();

        return [
            'count' => $moved->count(),
            'to' => $to->name,
            'by_sku' => $moved->groupBy(fn (LegacyTag $t) => $t->productVariant->sku ?? '—')->map->count()->all(),
            'serials' => $moved->pluck('serial')->take(100)->values()->all(),
        ];
    }
}
