<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Services\Commerce\InventoryService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The store catalog for the Finisher Legacy Tag era: Trisuit, CHILL BAND and
 * RACEPACK (the race-day backpack). The Tag itself is sold per collection
 * (Administración → Colecciones), so it is not seeded here; the historical
 * Legacy Plate and FAST T1 SOCKS were retired on 2026-10-29.
 */
class ProductCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = $this->seedCategories();
        $location = InventoryLocation::query()->firstOrCreate(
            ['slug' => config('finisher.commerce.default_inventory_location_slug', 'main-warehouse')],
            ['name' => 'Main Warehouse', 'active' => true],
        );

        $trisuit = $this->seedProduct(
            name: 'Trisuit',
            slug: 'trisuit',
            categoryId: $categories['apparel'],
            type: ProductType::Apparel,
            tagline: 'Nada, rueda y corre con la misma prenda.',
            description: 'Traje de triatlón de una pieza Finisher Legacy: te acompaña en natación, ciclismo y carrera sin cambiarte. Disponible en varios colores y tallas S a XL.',
        );
        foreach (['Ice Stealth', 'Cool Crimson', 'Frost Pink', 'Glacier Blue', 'Carbon Frost', 'Legacy Gold'] as $color) {
            foreach (['S', 'M', 'L', 'XL'] as $size) {
                $this->seedVariant($trisuit, 'TRI-'.Str::upper(Str::slug($color, '')).'-'.$size, "{$color} / {$size}", 189000, $location, stock: 10, attributes: ['size' => $size, 'color' => $color]);
            }
        }

        $band = $this->seedProduct(
            name: 'CHILL BAND',
            slug: 'chill-band',
            categoryId: $categories['enfriamiento'],
            type: ProductType::Accessory,
            tagline: 'Mójala, póntela y baja la temperatura.',
            description: 'Banda de enfriamiento Finisher Legacy para cuello o muñeca. Mójala, escúrrela y póntela antes o después de correr para refrescarte.',
        );
        $this->seedVariant($band, 'CHILL-STD', 'Única', 29000, $location, stock: 40);

        $racepack = $this->seedProduct(
            name: 'RACEPACK',
            slug: 'racepack',
            categoryId: $categories['equipment'],
            type: ProductType::Equipment,
            tagline: 'La mochila para tu día de carrera.',
            description: 'Mochila deportiva Finisher Legacy para el día de carrera: lleva tu equipo, tu cambio de ropa y tu hidratación en un solo lugar, con un punto para colgar tu Finisher Legacy Tag.',
        );
        $this->seedVariant($racepack, 'RACEPACK-STD', 'Estándar', 59000, $location, stock: 20);
    }

    /**
     * @return array<string, int>
     */
    private function seedCategories(): array
    {
        // Slugs are stable identifiers (existing rows keep them); names and
        // order are what the storefront shows. Admins can rename/reorder or
        // add categories later from Administración → Categorías.
        $categories = [
            'legacy' => ['Finisher Legacy Tag', 'El tag de tu carrera: acércale el celular y se abre tu historia.'],
            'enfriamiento' => ['Enfriamiento', 'Recuperación y control de temperatura en carrera.'],
            'apparel' => ['Textil', 'Trisuits y prendas técnicas para competir.'],
            'accessories' => ['Accesorios', 'Viseras, gorras y accesorios para el día de carrera.'],
            'equipment' => ['Equipo', 'Mochila y equipo para el día de carrera.'],
        ];
        $ids = [];
        $order = 0;

        foreach ($categories as $slug => [$name, $description]) {
            $ids[$slug] = ProductCategory::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'sort_order' => $order++, 'active' => true],
            )->id;
        }

        return $ids;
    }

    private function seedProduct(
        string $name,
        string $slug,
        int $categoryId,
        ProductType $type,
        string $description,
        ?string $tagline = null,
        bool $requiresShipping = true,
        bool $tracksInventory = true,
    ): Product {
        return Product::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'description' => $description,
                'tagline' => $tagline,
                'type' => $type,
                'category_id' => $categoryId,
                'brand' => 'Finisher Legacy',
                'status' => ProductStatus::Active,
                'taxable' => false,
                'requires_shipping' => $requiresShipping,
                'qr_capable' => false,
                'tracks_inventory' => $tracksInventory,
                'active' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedVariant(Product $product, string $sku, string $name, int $priceMinor, InventoryLocation $location, ?int $stock, array $attributes = []): ProductVariant
    {
        $variant = ProductVariant::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'uuid' => (string) Str::uuid(),
                'product_id' => $product->id,
                'name' => $name,
                'attributes' => $attributes === [] ? null : $attributes,
                'base_price_minor' => $priceMinor,
                'currency' => config('finisher.commerce.default_currency', 'MXN'),
                'active' => true,
            ],
        );

        if ($stock !== null && $product->tracks_inventory) {
            $existing = $variant->inventoryLevels()->where('inventory_location_id', $location->id)->first();

            if ($existing === null) {
                app(InventoryService::class)->receive($variant, $location, $stock, 'seed');
            }
        }

        return $variant;
    }
}
