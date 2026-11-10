<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CollectionDesign;
use App\Support\Collections\TagDesign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Catálogo → Colecciones → Diseños: the extra Finisher Legacy Tag fronts of
 * one collection (`collection_designs`), managed from the collection edit
 * page. Front art follows the `tag_art` upload pattern (public disk,
 * collections/designs/). A design that a product prints is never deleted —
 * it is archived (hidden from the store, references intact).
 */
class CollectionDesignController extends Controller
{
    /**
     * Admin payload of every design of a collection, archived included.
     *
     * @return list<array<string, mixed>>
     */
    public static function listFor(Collection $collection): array
    {
        return array_values($collection->allDesigns()->withCount('products')->get()
            ->map(fn (CollectionDesign $d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'accent' => $d->accent,
                'sort_order' => $d->sort_order,
                'front_art_url' => TagDesign::absoluteUrl($d->front_art_path),
                'archived' => $d->archived_at !== null,
                'products_count' => (int) $d->products_count,
            ])->all());
    }

    public function store(Request $request, Collection $collection): RedirectResponse
    {
        $data = $this->validated($request, $collection);

        $design = $collection->allDesigns()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name'], $collection),
            'accent' => $data['accent'] ?? null,
            'front_art_path' => $request->hasFile('art') ? $request->file('art')->store('collections/designs', 'public') : null,
            'sort_order' => (int) $collection->allDesigns()->max('sort_order') + 1,
        ]);

        $this->audit($request, $design, 'collection_designs.created');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Diseño agregado.']);

        return back();
    }

    public function update(Request $request, Collection $collection, CollectionDesign $design): RedirectResponse
    {
        $this->ensureBelongs($collection, $design);
        $data = $this->validated($request, $collection, $design);

        $attributes = [
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name'], $collection, $design),
            'accent' => $data['accent'] ?? null,
        ];

        if ($request->hasFile('art')) {
            $this->deleteUpload($design->front_art_path);
            $attributes['front_art_path'] = $request->file('art')->store('collections/designs', 'public');
        } elseif ($request->boolean('remove_art')) {
            $this->deleteUpload($design->front_art_path);
            $attributes['front_art_path'] = null;
        }

        $design->update($attributes);

        $this->audit($request, $design, 'collection_designs.updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Diseño actualizado.']);

        return back();
    }

    /** Persists a new order: `ids` lists every design of the collection. */
    public function reorder(Request $request, Collection $collection): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $owned = $collection->allDesigns()->pluck('id')->all();
        $ids = array_map('intval', $data['ids']);

        if (count($ids) !== count($owned) || array_diff($ids, $owned) !== []) {
            return back()->withErrors(['ids' => 'El orden debe incluir todos los diseños de la colección.']);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                CollectionDesign::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Orden de diseños guardado.']);

        return back();
    }

    /** Archives (hides from the store) or restores a design. */
    public function archive(Request $request, Collection $collection, CollectionDesign $design): RedirectResponse
    {
        $this->ensureBelongs($collection, $design);
        $archive = $request->boolean('archived', true);

        $design->update(['archived_at' => $archive ? now() : null]);

        $this->audit($request, $design, $archive ? 'collection_designs.archived' : 'collection_designs.restored');
        Inertia::flash('toast', ['type' => 'success', 'message' => $archive ? 'Diseño archivado.' : 'Diseño restaurado.']);

        return back();
    }

    /** Deletes a design only when no product references it. */
    public function destroy(Request $request, Collection $collection, CollectionDesign $design): RedirectResponse
    {
        $this->ensureBelongs($collection, $design);

        if ($design->products()->exists()) {
            return back()->withErrors(['design' => 'Hay productos que usan este diseño: archívalo en lugar de eliminarlo.']);
        }

        $this->audit($request, $design, 'collection_designs.deleted');
        $this->deleteUpload($design->front_art_path);
        $design->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Diseño eliminado.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Collection $collection, ?CollectionDesign $design = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable', 'string', 'max:80', 'alpha_dash:ascii', 'not_in:principal',
                Rule::unique('collection_designs', 'slug')->where('collection_id', $collection->id)->ignore($design?->id),
            ],
            'accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'art' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'remove_art' => ['sometimes', 'boolean'],
        ]);
    }

    private function ensureBelongs(Collection $collection, CollectionDesign $design): void
    {
        abort_unless($design->collection_id === $collection->id, 404);
    }

    private function audit(Request $request, CollectionDesign $design, string $event): void
    {
        activity()->causedBy($request->user())->performedOn($design)
            ->withProperties(['collection_id' => $design->collection_id, 'name' => $design->name, 'slug' => $design->slug])
            ->log($event);
    }

    private function deleteUpload(?string $path): void
    {
        // Seeded art (/media/…, http…) is not ours to delete.
        if ($path !== null && $path !== '' && ! str_starts_with($path, '/') && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }

    /** "principal" is reserved for the collection's own design (TagDesign). */
    private function uniqueSlug(?string $slug, string $name, Collection $collection, ?CollectionDesign $design = null): string
    {
        $base = Str::limit(Str::slug($slug ?: $name) ?: 'diseno', 70, '');
        $base = $base === 'principal' ? 'principal-2' : $base;
        $candidate = $base;
        $i = 2;

        while ($collection->allDesigns()->where('slug', $candidate)->when($design, fn ($q) => $q->whereKeyNot($design->id))->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }
}
