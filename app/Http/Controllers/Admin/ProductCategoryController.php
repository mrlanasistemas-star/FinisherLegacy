<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Store categories (Placa Legacy, Enfriamiento, Textil, Accesorios…) —
 * the storefront's category bar reads only from here, never from Vue.
 */
class ProductCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/product-categories/Index', [
            'categories' => ProductCategory::query()
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (ProductCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'sort_order' => $category->sort_order,
                    'active' => $category->active,
                    'products_count' => $category->products_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name']);

        ProductCategory::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría creada.']);

        return back();
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $category->slug, $data['name'], $category);

        $category->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría actualizada.']);

        return back();
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        // Never orphan products silently — deactivate instead.
        if ($category->products()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'La categoría tiene productos. Desactívala en lugar de eliminarla.']);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría eliminada.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ProductCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', Rule::unique('product_categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'active' => ['boolean'],
        ]);

        $data['sort_order'] ??= 0;

        return $data;
    }

    private function uniqueSlug(?string $slug, string $name, ?ProductCategory $ignore = null): string
    {
        $base = Str::slug($slug ?: $name) ?: 'categoria';
        $candidate = $base;
        $suffix = 2;

        while (ProductCategory::query()->where('slug', $candidate)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
