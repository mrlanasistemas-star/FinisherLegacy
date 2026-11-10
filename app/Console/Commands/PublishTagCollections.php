<?php

namespace App\Console\Commands;

use App\Enums\ProductAvailability;
use App\Enums\ProductMediaType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Collection;
use App\Models\CollectionDesign;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Publishes the tag collections seeded by `collections:seed-tag-art` and
 * creates one Finisher Legacy Tag product per design (main design + every
 * collection_designs row), with its artwork as the primary image.
 *
 * Idempotent and safe to run on the VPS: existing products are matched by
 * slug and never get their price, name or status overwritten (the admin
 * owns those after the first run). The price is only a starting value —
 * edit it in Admin → Productos.
 *
 *   php artisan collections:seed-tag-art
 *   php artisan catalog:publish-tag-collections --price=400
 */
class PublishTagCollections extends Command
{
    protected $signature = 'catalog:publish-tag-collections
        {--price=400 : Precio inicial en MXN para productos nuevos (editable en el admin)}
        {--collections=veracruz,cozumel,valle-de-bravo : Slugs de colección separados por coma}
        {--dry-run : Muestra lo que haría sin guardar}';

    protected $description = 'Publica las colecciones del Tag y crea un producto por diseño con su imagen';

    public function handle(): int
    {
        $price = (float) $this->option('price');
        if ($price <= 0) {
            $this->error('El precio debe ser mayor a 0.');

            return self::FAILURE;
        }

        $slugs = array_filter(array_map('trim', explode(',', (string) $this->option('collections'))));
        $collections = Collection::query()->whereIn('slug', $slugs)->get();

        if ($collections->isEmpty()) {
            $this->error('No hay colecciones con esos slugs. Corre primero: php artisan collections:seed-tag-art');

            return self::FAILURE;
        }

        $category = ProductCategory::query()->where('slug', 'legacy')->first();
        $dry = (bool) $this->option('dry-run');
        $rows = [];

        foreach ($collections as $collection) {
            $designs = [[null, $collection->tag_front_art, $collection->name, ''], ...CollectionDesign::query()
                ->where('collection_id', $collection->id)->orderBy('sort_order')->get()
                ->map(fn (CollectionDesign $d) => [$d, $d->front_art_path, $d->name, '-'.$d->slug])->all()];

            foreach ($designs as [$design, $art, $label, $suffix]) {
                $slug = 'tag-'.$collection->slug.$suffix;
                $exists = Product::query()->where('slug', $slug)->exists();
                $rows[] = [$slug, 'Finisher Legacy Tag · '.$label, $exists ? 'ya existe (sin cambios de precio)' : 'nuevo · $'.number_format($price, 2).' MXN'];

                if ($dry) {
                    continue;
                }

                DB::transaction(function () use ($collection, $design, $art, $label, $slug, $exists, $price, $category) {
                    $product = Product::query()->firstOrNew(['slug' => $slug]);

                    if (! $exists) {
                        $product->fill([
                            'uuid' => (string) Str::uuid(),
                            'name' => 'Finisher Legacy Tag · '.$label,
                            'tagline' => 'Acerca tu celular y revive tu carrera.',
                            'description' => "Tag NFC de la colección {$collection->name}. Se activa desde la app o la web y abre tu recuerdo: fotos, historia, resultado y mensaje de voz.",
                            'type' => ProductType::LegacyTag,
                            'category_id' => $category?->id,
                            'brand' => 'Finisher Legacy',
                            'status' => ProductStatus::Active,
                            'availability' => ProductAvailability::Available,
                            'taxable' => false,
                            'requires_shipping' => true,
                            'qr_capable' => false,
                            'tracks_inventory' => true,
                            'tracks_units' => true,
                            'active' => true,
                            'strap_type' => 'Loop de silicona negro',
                        ]);
                    }

                    // Keep the design link and collection in sync on every run.
                    $product->fill([
                        'collection_id' => $collection->id,
                        'collection_design_id' => $design?->id,
                    ]);
                    $product->save();

                    if (! $exists) {
                        ProductVariant::query()->create([
                            'uuid' => (string) Str::uuid(),
                            'product_id' => $product->id,
                            'sku' => Str::upper(Str::limit(str_replace('-', '', $slug), 20, '')),
                            'name' => 'Única',
                            'base_price_minor' => (int) round($price * 100),
                            'currency' => 'MXN',
                            'active' => true,
                        ]);
                    }

                    $this->attachImage($product, $art, $label);
                });
            }

            if (! $dry && $collection->status !== ProductStatus::Active) {
                $collection->update(['status' => ProductStatus::Active, 'launch_at' => $collection->launch_at ?? now()]);
            }
        }

        $this->table(['Producto', 'Nombre', 'Estado'], $rows);
        $this->info($dry ? 'Simulación: no se guardó nada.' : 'Listo. Cambia precios en Admin → Productos. Para vender, recibe un lote de tags en Inventario.');

        return self::SUCCESS;
    }

    /** Copies the design art (public/…) to the public disk as the primary image, once. */
    private function attachImage(Product $product, ?string $art, string $label): void
    {
        if (! $art || ProductMedia::query()->where('product_id', $product->id)->exists()) {
            return;
        }

        $source = public_path(ltrim(parse_url($art, PHP_URL_PATH) ?: $art, '/'));
        if (! is_file($source)) {
            $source = Storage::disk('public')->path($art);
        }
        if (! is_file($source)) {
            $this->warn("Sin imagen para {$product->slug}: no se encontró {$art}");

            return;
        }

        $path = 'products/tags/'.$product->slug.'.'.pathinfo($source, PATHINFO_EXTENSION);
        Storage::disk('public')->put($path, (string) file_get_contents($source));

        ProductMedia::query()->create([
            'product_id' => $product->id,
            'type' => ProductMediaType::Image,
            'disk' => 'public',
            'path' => $path,
            'mime' => mime_content_type($source) ?: 'image/webp',
            'size' => filesize($source) ?: null,
            'sort_order' => 0,
            'is_primary' => true,
            'alt_text' => 'Finisher Legacy Tag '.$label,
        ]);
    }
}
