<?php

namespace App\Queries\Commerce;

use App\Enums\OrderPaymentStatus;
use App\Enums\ProductType;
use App\Models\InventoryLevel;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\InventoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The admin's stock read model (spec §12-§15): one row per variant with
 * STOCK (on hand), RESERVADO, DISPONIBLE, VENDIDO (units on paid orders)
 * and an ALERTA against its restock threshold. Balances are read at the
 * checkout location (InventoryService::defaultLocation()) — the same place
 * checkout reserves from, so "disponible" here and in the store agree.
 * Products that don't track inventory (Legacy Plate, digital photos) are
 * left out. One query per page — no N+1.
 */
class InventoryStock
{
    public const array SIZE_ORDER = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', 'CH', 'MED', 'G', 'EG', 'U', 'UNI', 'UNITALLA'];

    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @return Builder<ProductVariant>
     */
    public function query(): Builder
    {
        $locationId = $this->inventory->defaultLocation()->id;
        $default = (int) config('finisher.commerce.default_minimum_stock', 5);

        $levels = InventoryLevel::query()
            ->toBase()
            ->where('inventory_location_id', $locationId)
            ->groupBy('product_variant_id')
            ->select('product_variant_id', DB::raw('sum(quantity_on_hand) as on_hand'), DB::raw('sum(quantity_reserved) as reserved'));

        $sold = OrderItem::query()
            ->toBase()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', [OrderPaymentStatus::Paid->value, OrderPaymentStatus::PartiallyRefunded->value])
            ->whereNotNull('order_items.product_variant_id')
            ->groupBy('order_items.product_variant_id')
            ->select('order_items.product_variant_id', DB::raw('sum(order_items.quantity) as sold'));

        return ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoinSub($levels, 'lv', 'lv.product_variant_id', '=', 'product_variants.id')
            ->leftJoinSub($sold, 'sd', 'sd.product_variant_id', '=', 'product_variants.id')
            ->where('products.tracks_inventory', true)
            ->where('products.type', '!=', ProductType::DigitalPhoto->value)
            ->select([
                'product_variants.*',
                'products.name as product_name',
                'products.type as product_type',
                DB::raw('coalesce(lv.on_hand, 0) as stock_on_hand'),
                DB::raw('coalesce(lv.reserved, 0) as stock_reserved'),
                DB::raw('coalesce(lv.on_hand, 0) - coalesce(lv.reserved, 0) as stock_available'),
                DB::raw('coalesce(sd.sold, 0) as stock_sold'),
                DB::raw("coalesce(product_variants.minimum_stock, products.minimum_stock, {$default}) as stock_minimum"),
            ]);
    }

    /**
     * @param  Builder<ProductVariant>  $query
     * @return Builder<ProductVariant>
     */
    public function filter(Builder $query, ?string $search, ?string $state, ?int $productId = null): Builder
    {
        $default = (int) config('finisher.commerce.default_minimum_stock', 5);
        $available = 'coalesce(lv.on_hand, 0) - coalesce(lv.reserved, 0)';
        $minimum = "coalesce(product_variants.minimum_stock, products.minimum_stock, {$default})";

        return $query
            ->when($search !== null && $search !== '', function (Builder $q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $search).'%';
                $q->where(fn (Builder $inner) => $inner
                    ->where('products.name', 'like', $like)
                    ->orWhere('product_variants.name', 'like', $like)
                    ->orWhere('product_variants.sku', 'like', $like));
            })
            ->when($productId !== null, fn (Builder $q) => $q->where('products.id', $productId))
            ->when($state === 'out', fn (Builder $q) => $q->whereRaw("{$available} <= 0"))
            ->when($state === 'low', fn (Builder $q) => $q->whereRaw("{$available} > 0")->whereRaw("{$available} <= {$minimum}"))
            ->when($state === 'attention', fn (Builder $q) => $q->whereRaw("{$available} <= {$minimum}"))
            ->when($state === 'ok', fn (Builder $q) => $q->whereRaw("{$available} > {$minimum}"));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function table(?string $search, ?string $state, int $perPage = 50): LengthAwarePaginator
    {
        $paginator = $this->filter($this->query(), $search, $state)
            ->with('product.media')
            ->orderByRaw('case when coalesce(lv.on_hand, 0) - coalesce(lv.reserved, 0) <= 0 then 0 else 1 end')
            ->orderBy('products.name')
            ->orderBy('product_variants.sku')
            ->paginate($perPage)
            ->withQueryString();

        return $paginator->through(fn (ProductVariant $variant) => $this->row($variant));
    }

    /**
     * @return array{total: int, low: int, out: int, attention: int, on_hand: int, reserved: int}
     */
    public function counts(): array
    {
        $rows = DB::query()->fromSub($this->query()->toBase(), 's')->selectRaw(
            'count(*) as total,
             coalesce(sum(case when stock_available <= 0 then 1 else 0 end), 0) as out_count,
             coalesce(sum(case when stock_available > 0 and stock_available <= stock_minimum then 1 else 0 end), 0) as low_count,
             coalesce(sum(stock_on_hand), 0) as on_hand,
             coalesce(sum(stock_reserved), 0) as reserved',
        )->first();

        $out = (int) ($rows->out_count ?? 0);
        $low = (int) ($rows->low_count ?? 0);

        return [
            'total' => (int) ($rows->total ?? 0),
            'low' => $low,
            'out' => $out,
            'attention' => $low + $out,
            'on_hand' => (int) ($rows->on_hand ?? 0),
            'reserved' => (int) ($rows->reserved ?? 0),
        ];
    }

    /**
     * Variants that need restocking, worst first (dashboard lists).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function needingRestock(int $limit = 8, bool $onlyOut = false): Collection
    {
        return $this->filter($this->query(), null, $onlyOut ? 'out' : 'attention')
            ->with('product.media')
            ->orderByRaw('coalesce(lv.on_hand, 0) - coalesce(lv.reserved, 0) asc')
            ->limit($limit)
            ->get()
            ->map(fn (ProductVariant $variant) => $this->row($variant));
    }

    /**
     * Apparel matrix (spec §13): one card per product, rows = colour,
     * columns = size, each cell the available units + its alert level.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function matrix(?string $search, int $perPage = 8): LengthAwarePaginator
    {
        $products = Product::query()
            ->where('tracks_inventory', true)
            ->where('type', '!=', ProductType::DigitalPhoto->value)
            ->whereHas('variants')
            ->when($search !== null && $search !== '', fn (Builder $q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $search).'%'))
            ->with('media')
            ->orderByRaw('case when type = ? then 0 else 1 end', [ProductType::Apparel->value])
            ->orderBy('name')
            ->paginate($perPage, pageName: 'mpage')
            ->withQueryString();

        $variants = $this->query()
            ->whereIn('products.id', $products->getCollection()->pluck('id'))
            ->get()
            ->groupBy('product_id');

        return $products->through(function (Product $product) use ($variants) {
            $rows = ($variants->get($product->id) ?? collect())->map(fn (ProductVariant $v) => $this->row($v));
            $sizes = $this->sortSizes($rows->pluck('size')->map(fn ($s) => $s ?? '—')->unique()->values()->all());
            $colors = $rows->pluck('color')->map(fn ($c) => $c ?? 'Único')->unique()->values()->all();

            $cells = [];
            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $match = $rows->first(fn ($r) => ($r['color'] ?? 'Único') === $color && ($r['size'] ?? '—') === $size);
                    $cells[$color][$size] = $match === null ? null : [
                        'variant_id' => $match['variant_id'],
                        'available' => $match['available'],
                        'reserved' => $match['reserved'],
                        'minimum' => $match['minimum'],
                        'alert' => $match['alert'],
                        'sku' => $match['sku'],
                        'label' => $match['label'],
                    ];
                }
            }

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->value,
                'image_url' => $product->primaryImageUrl(),
                'sizes' => $sizes,
                'colors' => $colors,
                'cells' => $cells,
                'available' => (int) $rows->sum('available'),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function row(ProductVariant $variant): array
    {
        ['size' => $size, 'color' => $color] = $variant->sizeAndColor();
        $available = (int) $variant->getAttribute('stock_available');
        $minimum = (int) $variant->getAttribute('stock_minimum');

        return [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'product' => (string) $variant->getAttribute('product_name'),
            'product_type' => (string) $variant->getAttribute('product_type'),
            'variant' => $variant->name,
            'label' => trim((string) $variant->getAttribute('product_name').' — '.$variant->name),
            'sku' => $variant->sku,
            'size' => $size,
            'color' => $color,
            'image_url' => $variant->relationLoaded('product') ? $variant->product?->primaryImageUrl() : null,
            'on_hand' => (int) $variant->getAttribute('stock_on_hand'),
            'reserved' => (int) $variant->getAttribute('stock_reserved'),
            'available' => $available,
            'sold' => (int) $variant->getAttribute('stock_sold'),
            'minimum' => $minimum,
            'minimum_own' => $variant->minimum_stock,
            'alert' => match (true) {
                $available <= 0 => 'out',
                $available <= $minimum => 'low',
                default => 'ok',
            },
            'active' => (bool) $variant->active,
        ];
    }

    /**
     * @param  list<string>  $sizes
     * @return list<string>
     */
    public function sortSizes(array $sizes): array
    {
        usort($sizes, function (string $a, string $b) {
            $ia = array_search(strtoupper($a), self::SIZE_ORDER, true);
            $ib = array_search(strtoupper($b), self::SIZE_ORDER, true);

            return match (true) {
                $ia !== false && $ib !== false => $ia <=> $ib,
                $ia !== false => -1,
                $ib !== false => 1,
                is_numeric($a) && is_numeric($b) => (float) $a <=> (float) $b,
                default => strnatcasecmp($a, $b),
            };
        });

        return array_values($sizes);
    }
}
