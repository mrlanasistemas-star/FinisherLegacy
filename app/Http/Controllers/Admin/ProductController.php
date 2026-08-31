<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with('category')
            ->withCount('variants')
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $products->through(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'type' => $product->type->value,
            'category' => $product->category === null ? '—' : $product->category->name,
            'status' => $product->status->value,
            'qr_capable' => $product->qr_capable,
            'tracks_inventory' => $product->tracks_inventory,
            'active' => $product->active,
            'variants_count' => $product->variants_count,
        ]);

        return Inertia::render('admin/products/Index', [
            'products' => $products,
            'filters' => ['q' => $request->string('q')->toString()],
            'categories' => ProductCategory::query()->orderBy('name')->get(['id', 'name']),
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

    public function show(Product $product): Response
    {
        $product->loadMissing(['category', 'variants.inventoryLevels.inventoryLocation']);

        return Inertia::render('admin/products/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'type' => $product->type->value,
                'category_id' => $product->category_id,
                'brand' => $product->brand,
                'status' => $product->status->value,
                'qr_capable' => $product->qr_capable,
                'requires_shipping' => $product->requires_shipping,
                'tracks_inventory' => $product->tracks_inventory,
                'active' => $product->active,
                'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
            ],
            'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'attributes' => $variant->attributes,
                'base_price_minor' => $variant->base_price_minor,
                'currency' => $variant->currency,
                'active' => $variant->active,
                'stock' => $variant->inventoryLevels->sum('quantity_on_hand'),
                'reserved' => $variant->inventoryLevels->sum('quantity_reserved'),
            ]),
            'categories' => ProductCategory::query()->orderBy('name')->get(['id', 'name']),
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
        ]);

        $product->variants()->create([
            'uuid' => (string) Str::uuid(),
            'sku' => $data['sku'],
            'name' => $data['name'],
            'attributes' => $data['attributes'] ?? null,
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
        ]);

        $variant->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Variante actualizada.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
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
        ]);
    }
}
