<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\LegacyTagStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Event;
use App\Models\LegacyTag;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo → Colecciones (docs/architecture/commerce-os.md §Administración).
 * Plain catalog CRUD: a collection groups Finisher Legacy Tag designs. It is
 * never hard-deleted while it has products — it is archived instead.
 */
class CollectionController extends Controller
{
    private const array SORTS = ['sort_order', 'name', 'year', 'launch_at', 'created_at'];

    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => $request->string('status')->toString() ?: null,
            'discipline' => $request->string('discipline')->toString() ?: null,
            'year' => $request->integer('year') ?: null,
            'sort' => in_array($request->string('sort')->toString(), self::SORTS, true) ? $request->string('sort')->toString() : 'sort_order',
            'dir' => $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc',
        ];

        $collections = Collection::query()
            ->withCount('products')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$filters['q']}%")
                ->orWhere('destination', 'like', "%{$filters['q']}%")
                ->orWhere('slug', 'like', "%{$filters['q']}%")))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['discipline'], fn ($q, $discipline) => $q->where('discipline', $discipline))
            ->when($filters['year'], fn ($q, $year) => $q->where('year', $year))
            ->orderBy($filters['sort'], $filters['dir'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Collection $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'tagline' => $c->tagline,
                'discipline' => $c->discipline,
                'destination' => $c->destination,
                'year' => $c->year,
                'status' => $c->status->value,
                'launch_at' => $c->launch_at?->toIso8601String(),
                'cover_url' => $c->coverUrl(),
                'accent_color' => $c->accent_color,
                'products_count' => $c->products_count,
            ]);

        return Inertia::render('admin/catalog/collections/Index', [
            'collections' => $collections,
            'filters' => $filters,
            'disciplines' => Collection::query()->whereNotNull('discipline')->distinct()->orderBy('discipline')->pluck('discipline'),
            'years' => Collection::query()->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/catalog/collections/Form', [
            'collection' => null,
            'events' => $this->eventOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['uuid'] = (string) Str::uuid();
        $data = $this->withImages($request, $data);

        $collection = Collection::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Colección creada.']);

        return redirect()->route('admin.catalog.collections.show', $collection->id);
    }

    public function show(Collection $collection): Response
    {
        $collection->load('event:id,name');

        $products = Product::query()
            ->where('collection_id', $collection->id)
            ->with(['variants.inventoryLevels'])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $variantIds = $products->flatMap(fn (Product $p) => $p->variants->pluck('id'))->all();

        $units = LegacyTag::query()->toBase()
            ->whereIn('product_variant_id', $variantIds)
            ->selectRaw('product_variant_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as activated, sum(case when status = ? then 1 else 0 end) as in_stock', [
                LegacyTagStatus::Activated->value, LegacyTagStatus::InStock->value,
            ])
            ->groupBy('product_variant_id')
            ->get()
            ->keyBy('product_variant_id');

        return Inertia::render('admin/catalog/collections/Show', [
            'collection' => [
                ...$this->payload($collection),
                'event' => $collection->event?->only(['id', 'name']),
                'cover_url' => $collection->coverUrl(),
                'hero_url' => $collection->hero_path ? $collection->heroUrl() : null,
                'is_published' => $collection->isPublished(),
            ],
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type->value,
                'status' => $p->status->value,
                'tracks_units' => (bool) $p->tracks_units,
                'variants' => $p->variants->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'name' => $v->name,
                    'stock' => (int) $v->inventoryLevels->sum('quantity_on_hand') - (int) $v->inventoryLevels->sum('quantity_reserved'),
                    'units' => (int) ($units[$v->id]->total ?? 0),
                    'units_in_stock' => (int) ($units[$v->id]->in_stock ?? 0),
                    'activated' => (int) ($units[$v->id]->activated ?? 0),
                ])->values(),
            ])->values(),
        ]);
    }

    public function edit(Collection $collection): Response
    {
        return Inertia::render('admin/catalog/collections/Form', [
            'collection' => [
                ...$this->payload($collection),
                'cover_url' => $collection->coverUrl(),
                'hero_url' => $collection->hero_path ? $collection->heroUrl() : null,
            ],
            'events' => $this->eventOptions(),
        ]);
    }

    public function update(Request $request, Collection $collection): RedirectResponse
    {
        $data = $this->withImages($request, $this->validated($request, $collection), $collection);

        $collection->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Colección actualizada.']);

        return redirect()->route('admin.catalog.collections.show', $collection->id);
    }

    /**
     * Deletes an empty collection; one that still has products is archived
     * (its products keep pointing at it and stay traceable).
     */
    public function destroy(Request $request, Collection $collection): RedirectResponse
    {
        if ($collection->products()->exists()) {
            $collection->update(['status' => ProductStatus::Archived]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'La colección tiene productos: se archivó en lugar de eliminarse.']);

            return redirect()->route('admin.catalog.collections.show', $collection->id);
        }

        activity()->causedBy($request->user())->performedOn($collection)
            ->withProperties(['name' => $collection->name, 'slug' => $collection->slug])
            ->log('collections.deleted');

        foreach ([$collection->cover_path, $collection->hero_path] as $path) {
            $this->deleteUpload($path);
        }

        $collection->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Colección eliminada.']);

        return redirect()->route('admin.catalog.collections.index');
    }

    /** @return array<string, mixed> */
    private function payload(Collection $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'tagline' => $c->tagline,
            'description' => $c->description,
            'discipline' => $c->discipline,
            'destination' => $c->destination,
            'year' => $c->year,
            'edition' => $c->edition,
            'season' => $c->season,
            'event_id' => $c->event_id,
            'status' => $c->status->value,
            'launch_at' => $c->launch_at?->toDateString(),
            'accent_color' => $c->accent_color,
            'sort_order' => $c->sort_order,
            'seo_title' => $c->seo_title,
            'seo_description' => $c->seo_description,
        ];
    }

    /** @return list<array{value: int, label: string}> */
    private function eventOptions(): array
    {
        return array_values(Event::query()->orderBy('name')->limit(500)->get(['id', 'name'])
            ->map(fn (Event $e) => ['value' => $e->id, 'label' => $e->name])->all());
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Collection $collection = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:160', 'alpha_dash:ascii', Rule::unique('collections', 'slug')->ignore($collection?->id)],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'discipline' => ['nullable', 'string', 'max:60'],
            'destination' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'edition' => ['nullable', 'string', 'max:120'],
            'season' => ['nullable', 'string', 'max:60'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'status' => ['required', 'string', Rule::enum(ProductStatus::class)],
            'launch_at' => ['nullable', 'date'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'hero' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            'remove_cover' => ['sometimes', 'boolean'],
            'remove_hero' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name'], $collection);
        $data['sort_order'] ??= 0;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withImages(Request $request, array $data, ?Collection $collection = null): array
    {
        foreach (['cover' => 'cover_path', 'hero' => 'hero_path'] as $field => $column) {
            if ($request->hasFile($field)) {
                $this->deleteUpload($collection?->{$column});
                $data[$column] = $request->file($field)->store('collections', 'public');
            } elseif ($request->boolean("remove_{$field}") && $collection !== null) {
                $this->deleteUpload($collection->{$column});
                $data[$column] = null;
            }

            unset($data[$field], $data["remove_{$field}"]);
        }

        return $data;
    }

    private function deleteUpload(?string $path): void
    {
        // Seeded art (/media/…, http…) is not ours to delete.
        if ($path !== null && $path !== '' && ! str_starts_with($path, '/') && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function uniqueSlug(?string $slug, string $name, ?Collection $collection): string
    {
        $base = Str::slug($slug ?: $name) ?: 'coleccion';
        $candidate = $base;
        $i = 2;

        while (Collection::query()->where('slug', $candidate)->when($collection, fn ($q) => $q->whereKeyNot($collection->id))->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }
}
