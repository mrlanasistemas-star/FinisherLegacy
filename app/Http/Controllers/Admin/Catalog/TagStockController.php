<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\LegacyTagStatus;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inventario → Existencias: serialized stock by SKU × location counted
 * from the units themselves (in stock / reserved / sold / activated), with
 * the low-stock flag from the variant's minimum. The aggregate matrix at
 * /admin/comercio/inventario stays untouched.
 */
class TagStockController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'collection' => $request->integer('collection') ?: null,
            'location' => $request->integer('location') ?: null,
            'low' => $request->boolean('low'),
        ];

        $variants = ProductVariant::query()
            ->whereHas('product', fn ($q) => $q->where('tracks_units', true)
                ->when($filters['collection'], fn ($c, $id) => $c->where('collection_id', $id)))
            ->with('product:id,name,minimum_stock,collection_id')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('sku', 'like', "%{$filters['q']}%")
                ->orWhere('name', 'like', "%{$filters['q']}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$filters['q']}%"))))
            ->orderBy('sku')
            ->get();

        $counts = LegacyTag::query()->toBase()
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->when($filters['location'], fn ($q, $id) => $q->where('inventory_location_id', $id))
            ->selectRaw('product_variant_id, inventory_location_id, status, count(*) as c')
            ->groupBy('product_variant_id', 'inventory_location_id', 'status')
            ->get();

        $bucket = fn (string $status): ?string => match ($status) {
            LegacyTagStatus::InStock->value => 'in_stock',
            LegacyTagStatus::Reserved->value => 'reserved',
            LegacyTagStatus::Sold->value, LegacyTagStatus::Delivered->value => 'sold',
            LegacyTagStatus::Activated->value => 'activated',
            default => null,
        };

        $locationNames = InventoryLocation::query()->pluck('name', 'id');
        $empty = ['in_stock' => 0, 'reserved' => 0, 'sold' => 0, 'activated' => 0];

        $rows = $variants->map(function (ProductVariant $v) use ($counts, $bucket, $locationNames, $empty) {
            $byLocation = [];
            $totals = $empty;

            foreach ($counts->where('product_variant_id', $v->id) as $row) {
                $key = $bucket((string) $row->status);

                if ($key === null) {
                    continue;
                }

                $loc = (int) $row->inventory_location_id;
                $byLocation[$loc] ??= ['location_id' => $loc ?: null, 'location' => $locationNames[$loc] ?? 'Sin ubicación', ...$empty];
                $byLocation[$loc][$key] += (int) $row->c;
                $totals[$key] += (int) $row->c;
            }

            $minimum = $v->minimumStock();

            return [
                'variant_id' => $v->id,
                'product_id' => $v->product_id,
                'product' => $v->product?->name,
                'variant' => $v->name,
                'sku' => $v->sku,
                'minimum' => $minimum,
                'totals' => $totals,
                'low' => $totals['in_stock'] <= $minimum,
                'locations' => array_values($byLocation),
            ];
        });

        if ($filters['low']) {
            $rows = $rows->where('low', true)->values();
        }

        return Inertia::render('admin/inventory/Stock', [
            'rows' => $rows->values(),
            'filters' => $filters,
            'collections' => Collection::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name'])->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])->all(),
        ]);
    }
}
