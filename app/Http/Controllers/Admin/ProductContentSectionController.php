<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductContentSectionType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductContentSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * "Cómo funciona / Características / Guía de uso / FAQ" editor (product UX
 * consolidation brief §111-§114) — a handful of typed sections, not a CMS.
 */
class ProductContentSectionController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request);

        $product->contentSections()->create([
            ...$data,
            'sort_order' => ($product->contentSections()->max('sort_order') ?? -1) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sección agregada.']);

        return back();
    }

    public function update(Request $request, ProductContentSection $section): RedirectResponse
    {
        $section->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sección actualizada.']);

        return back();
    }

    public function destroy(ProductContentSection $section): RedirectResponse
    {
        $section->delete();

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::enum(ProductContentSectionType::class)],
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'array'],
        ]);
    }
}
