<?php

namespace App\Http\Controllers\Admin\Quotas;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\PhotoExpansionPack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Photo expansion packs. A pack with a price or a sale is archived, never
 * deleted — its price history and the rights it granted stay readable.
 * Changing `photos` only affects future purchases: each entitlement froze
 * its own photo count.
 */
class ExpansionPackController extends Controller
{
    public function store(Request $request, ExpansionPriceController $prices): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $prices, $data) {
            $pack = PhotoExpansionPack::create([...$data, 'created_by' => $request->user()->id]);

            // Optional first price in the same form (all-or-nothing).
            if ($request->filled('price.amount')) {
                $prices->createSchedule($request, $pack, (array) $request->input('price'));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ampliación creada.']);

        return back();
    }

    public function update(Request $request, PhotoExpansionPack $pack): RedirectResponse
    {
        $pack->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ampliación actualizada.']);

        return back();
    }

    public function archive(PhotoExpansionPack $pack): RedirectResponse
    {
        $pack->update(['archived_at' => now(), 'active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ampliación archivada. Quien ya la compró la conserva.']);

        return back();
    }

    public function restore(PhotoExpansionPack $pack): RedirectResponse
    {
        $pack->update(['archived_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ampliación restaurada (sigue pausada hasta que la actives).']);

        return back();
    }

    public function destroy(PhotoExpansionPack $pack): RedirectResponse
    {
        if ($pack->prices()->exists() || $pack->entitlements()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Esta ampliación ya tiene precios o ventas: archívala en lugar de borrarla.']);

            return back();
        }

        $pack->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ampliación eliminada.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'photos' => ['required', 'integer', 'min:1', 'max:1000'],
            'scope' => ['required', Rule::in(['global', 'product', 'type'])],
            'product_id' => ['required_if:scope,product', 'nullable', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('type', '!=', ProductType::DigitalPhoto->value))],
            'product_type' => ['required_if:scope,type', 'nullable', Rule::enum(ProductType::class)->except([ProductType::DigitalPhoto])],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'photos' => (int) $data['photos'],
            'product_id' => $data['scope'] === 'product' ? (int) $data['product_id'] : null,
            'product_type' => $data['scope'] === 'type' ? $data['product_type'] : null,
            'active' => (bool) ($data['active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
