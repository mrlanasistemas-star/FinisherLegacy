<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\OrderPaymentStatus;
use App\Enums\ProductAvailability;
use App\Enums\ProductMediaType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductContentSection;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Queries\Commerce\CommerceMetrics;
use App\Queries\Commerce\InventoryStock;
use App\Support\Commerce\VariantStockStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Store admin — Products (brief §35-§36/§82). Thin: no pricing/inventory
 * logic lives here (that's App\Actions\Commerce\ResolveProductPrice /
 * App\Services\Commerce\InventoryService), just catalog CRUD.
 */
class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with(['category', 'media', 'variants.inventoryLevels'])
            ->withCount('variants')
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $products->through(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'type' => $product->type->value,
            'category' => $product->category === null ? '—' : $product->category->name,
            'status' => $product->status->value,
            'availability' => ($product->availability ?? ProductAvailability::Available)->value,
            'qr_capable' => $product->qr_capable,
            'tracks_inventory' => $product->tracks_inventory,
            'active' => $product->active,
            'variants_count' => $product->variants_count,
            'slug' => $product->slug,
            'tagline' => $product->tagline,
            // Visual card: primary first, then up to 4 more gallery images.
            'gallery' => $product->media
                ->where('type', ProductMediaType::Image)
                ->sortByDesc('is_primary')
                ->take(5)
                ->values()
                ->map(fn (ProductMedia $m) => ['id' => $m->id, 'url' => $m->url(), 'is_primary' => $m->is_primary]),
            'media_count' => $product->media->count(),
            // Admin-only: the store is showing a conceptual render because
            // no real photo has been uploaded yet.
            'concept_image_url' => $product->imageSource() === 'concept' ? $product->conceptImageUrl(480) : null,
            'from_price_minor' => $product->variants->min('base_price_minor'),
            'currency' => $product->variants->first()?->currency ?? 'MXN',
            'stock' => $product->tracks_inventory
                ? (int) $product->variants->sum(fn (ProductVariant $v) => $v->inventoryLevels->sum('quantity_on_hand') - $v->inventoryLevels->sum('quantity_reserved'))
                : null,
        ]);

        return Inertia::render('admin/products/Index', [
            'products' => $products,
            'filters' => ['q' => $request->string('q')->toString()],
            'categories' => ProductCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['uuid'] = (string) Str::uuid();
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(5));

        unset($data['image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto creado.']);

        return redirect()->route('admin.products.show', $product->id);
    }

    public function show(Product $product, InventoryStock $stock, CommerceMetrics $metrics): Response
    {
        $product->loadMissing(['category', 'variants.inventoryLevels.inventoryLocation', 'media', 'contentSections']);

        return Inertia::render('admin/products/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'tagline' => $product->tagline,
                'type' => $product->type->value,
                'availability' => ($product->availability ?? ProductAvailability::Available)->value,
                'sort_order' => $product->sort_order,
                'public_url' => route('store.products.show', $product->slug),
                'category_id' => $product->category_id,
                'brand' => $product->brand,
                'status' => $product->status->value,
                'qr_capable' => $product->qr_capable,
                'requires_shipping' => $product->requires_shipping,
                'tracks_inventory' => $product->tracks_inventory,
                'minimum_stock' => $product->minimum_stock,
                'is_featured' => (bool) $product->is_featured,
                'active' => $product->active,
                'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
                'concept_gallery' => $product->imageSource() === 'concept' ? $product->conceptGallery() : [],
            ],
            'variants' => $product->variants->map(function (ProductVariant $variant) use ($product) {
                $variant->setRelation('product', $product);
                $onHand = (int) $variant->inventoryLevels->sum('quantity_on_hand');
                $reserved = (int) $variant->inventoryLevels->sum('quantity_reserved');
                $available = $onHand - $reserved;
                $minimum = $variant->minimumStock();

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'attributes' => $variant->attributes,
                    ...VariantStockStatus::swatch($variant),
                    'base_price_minor' => $variant->base_price_minor,
                    'currency' => $variant->currency,
                    'active' => $variant->active,
                    'stock' => $onHand,
                    'reserved' => $reserved,
                    'available' => $available,
                    'minimum_stock' => $variant->minimum_stock,
                    'minimum' => $minimum,
                    'alert' => $available <= 0 ? 'out' : ($available <= $minimum ? 'low' : 'ok'),
                ];
            }),
            'matrix' => $product->tracks_inventory ? $this->matrixFor($product, $stock) : null,
            'sales' => $this->salesFor($product, $metrics),
            'recentOrders' => $this->recentOrdersFor($product),
            'reasons' => InventoryAdjustmentReason::options(),
            'categories' => ProductCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'media' => $product->media->map(fn (ProductMedia $m) => [
                'id' => $m->id,
                'uuid' => $m->uuid,
                'type' => $m->type->value,
                'url' => $m->url(),
                'poster_url' => $m->posterUrl(),
                'is_primary' => $m->is_primary,
                'is_hover' => $m->is_hover,
                'alt_text' => $m->alt_text,
                'mime' => $m->mime,
                'size' => $m->size,
                'sort_order' => $m->sort_order,
            ]),
            'contentSections' => $product->contentSections->map(fn (ProductContentSection $s) => [
                'id' => $s->id,
                'type' => $s->type->value,
                'title' => $s->title,
                'content' => $s->content,
                'sort_order' => $s->sort_order,
            ]),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request);

        unset($data['image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto actualizado.']);

        return back();
    }

    public function storeVariant(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:60', Rule::unique('product_variants', 'sku')],
            'name' => ['required', 'string', 'max:120'],
            'attributes' => ['nullable', 'array'],
            'base_price_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'active' => ['boolean'],
            'minimum_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'size' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:40'],
        ]);

        $attributes = array_filter([
            ...($data['attributes'] ?? []),
            'size' => $data['size'] ?? null,
            'color' => $data['color'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $product->variants()->create([
            'uuid' => (string) Str::uuid(),
            'sku' => $data['sku'],
            'name' => $data['name'],
            'minimum_stock' => $data['minimum_stock'] ?? null,
            'attributes' => $attributes === [] ? null : $attributes,
            'base_price_minor' => $data['base_price_minor'],
            'currency' => $data['currency'] ?? config('finisher.commerce.default_currency', 'MXN'),
            'active' => $data['active'] ?? true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Variante agregada.']);

        return back();
    }

    public function updateVariant(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_price_minor' => ['required', 'integer', 'min:1'],
            'active' => ['boolean'],
            'minimum_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'size' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:40'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // size/color live in the free JSON `attributes` (brief §50).
        $attributes = is_array($variant->getAttribute('attributes')) ? $variant->getAttribute('attributes') : [];
        foreach (['size', 'color', 'color_hex'] as $key) {
            if ($request->exists($key)) {
                $attributes[$key] = $data[$key] ?? null;
            }
            unset($data[$key]);
        }

        $variant->update([...$data, 'attributes' => array_filter($attributes, fn ($v) => $v !== null && $v !== '')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Variante actualizada.']);

        return back();
    }

    /**
     * Colour × size grid of this product (spec §13), same read model as
     * Comercio → Inventario.
     *
     * @return array<string, mixed>|null
     */
    private function matrixFor(Product $product, InventoryStock $stock): ?array
    {
        $rows = $stock->query()->where('products.id', $product->id)->get()->map(fn (ProductVariant $v) => $stock->row($v));

        if ($rows->isEmpty()) {
            return null;
        }

        $sizes = $stock->sortSizes($rows->pluck('size')->map(fn ($s) => $s ?? '—')->unique()->values()->all());
        $colors = $rows->pluck('color')->map(fn ($c) => $c ?? 'Único')->unique()->values()->all();
        $cells = [];

        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                $match = $rows->first(fn ($r) => ($r['color'] ?? 'Único') === $color && ($r['size'] ?? '—') === $size);
                $cells[$color][$size] = $match === null ? null : collect($match)->only(['variant_id', 'available', 'reserved', 'minimum', 'alert', 'sku', 'label'])->all();
            }
        }

        return ['sizes' => $sizes, 'colors' => $colors, 'cells' => $cells];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesFor(Product $product, CommerceMetrics $metrics): array
    {
        $paid = [OrderPaymentStatus::Paid->value, OrderPaymentStatus::PartiallyRefunded->value];
        $base = fn () => OrderItem::query()
            ->toBase()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $product->id)
            ->whereIn('orders.payment_status', $paid)
            ->where('orders.currency', $metrics->currency());

        $from = CarbonImmutable::now()->startOfDay()->subDays(29);
        $sum = fn ($q) => $q->selectRaw('coalesce(sum(order_items.quantity), 0) as units, coalesce(sum(order_items.line_total_minor), 0) as revenue, count(distinct order_items.order_id) as orders')->first();
        $all = $sum($base());
        $last30 = $sum($base()->where('orders.created_at', '>=', $from));

        $daily = $base()
            ->where('orders.created_at', '>=', $from)
            ->groupBy(DB::raw('date(orders.created_at)'))
            ->get([DB::raw('date(orders.created_at) as day'), DB::raw('sum(order_items.line_total_minor) as total_minor'), DB::raw('sum(order_items.quantity) as units')])
            ->keyBy(fn (object $r) => substr((string) $r->day, 0, 10));

        $byVariant = $base()
            ->groupBy('order_items.product_variant_id')
            ->orderByDesc(DB::raw('sum(order_items.quantity)'))
            ->limit(20)
            ->get(['order_items.product_variant_id', DB::raw('max(order_items.name) as name'), DB::raw('sum(order_items.quantity) as units'), DB::raw('sum(order_items.line_total_minor) as revenue')]);

        return [
            'currency' => $metrics->currency(),
            'all_time' => ['units' => (int) $all->units, 'revenue_minor' => (int) $all->revenue, 'orders' => (int) $all->orders],
            'last_30' => ['units' => (int) $last30->units, 'revenue_minor' => (int) $last30->revenue, 'orders' => (int) $last30->orders],
            'series' => collect(range(0, 29))->map(fn (int $i) => [
                'date' => $d = $from->addDays($i)->toDateString(),
                'total_minor' => (int) ($daily[$d]->total_minor ?? 0),
                'units' => (int) ($daily[$d]->units ?? 0),
            ])->all(),
            'by_variant' => $byVariant->map(fn (object $r) => [
                'variant_id' => $r->product_variant_id,
                'name' => $r->name,
                'units' => (int) $r->units,
                'revenue_minor' => (int) $r->revenue,
            ])->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrdersFor(Product $product): array
    {
        return array_values(Order::query()
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->with(['user:id,first_name,last_name', 'items' => fn ($q) => $q->where('product_id', $product->id)])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Order $order) => [
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'customer' => $order->customer_snapshot['name'] ?? $order->user?->name ?? 'Cliente',
                'units' => (int) $order->items->sum('quantity'),
                'lines' => $order->items->pluck('name')->values(),
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'fulfillment_status' => $order->fulfillment_status->value,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'created_at' => $order->created_at->toIso8601String(),
            ])
            ->all());
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::enum(ProductType::class)],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::enum(ProductStatus::class)],
            'qr_capable' => ['boolean'],
            'requires_shipping' => ['boolean'],
            'tracks_inventory' => ['boolean'],
            'active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
            'availability' => ['nullable', 'string', Rule::enum(ProductAvailability::class)],
            'tagline' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'minimum_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        // Optional on the wire (older clients/tests don't send them): an
        // omitted value keeps the column's current value / its default.
        foreach (['availability', 'sort_order', 'is_featured'] as $optional) {
            if (! isset($data[$optional])) {
                unset($data[$optional]);
            }
        }

        return $data;
    }
}
