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
 * The five initial ecosystem products (brief §48/§75/§190) — Legacy Plate
 * is the entry point, not the whole product. Legacy Plate itself has no
 * seeded ProductPriceSchedule here: its price is always event-scoped
 * (brief §202-§203), so a real price only exists once an event sells it.
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

        $legacyPlate = $this->seedProduct(
            name: 'Legacy Plate',
            slug: 'legacy-plate',
            categoryId: $categories['legacy'],
            type: ProductType::LegacyPlate,
            description: 'La pieza física que conecta tu logro con tu Legacy Profile — grabado dinámico sobre un modelo pre-manufacturado.',
            qrCapable: true,
            requiresShipping: false,
            tracksInventory: false,
        );
        $this->seedVariant($legacyPlate, 'LP-STD', 'Estándar', 130000, $location, stock: null);

        $trisuit = $this->seedProduct(
            name: 'Trisuit',
            slug: 'trisuit',
            categoryId: $categories['apparel'],
            type: ProductType::Apparel,
            description: 'Traje técnico premium Finisher Legacy.',
            qrCapable: true,
        );
        foreach (['Ice Stealth', 'Cool Crimson', 'Frost Pink', 'Glacier Blue', 'Carbon Frost', 'Legacy Gold'] as $color) {
            foreach (['S', 'M', 'L', 'XL'] as $size) {
                $this->seedVariant($trisuit, 'TRI-'.Str::upper(Str::slug($color, '')).'-'.$size, "{$color} / {$size}", 189000, $location, stock: 10, attributes: ['size' => $size, 'color' => $color]);
            }
        }

        $socks = $this->seedProduct(
            name: 'FAST T1 SOCKS',
            slug: 'fast-t1-socks',
            categoryId: $categories['accessories'],
            type: ProductType::Accessory,
            description: 'Calcetines deportivos técnicos FAST T1.',
            qrCapable: true,
        );
        foreach (['S/M', 'L/XL'] as $size) {
            $this->seedVariant($socks, 'SOCK-'.Str::slug($size), $size, 39000, $location, stock: 30, attributes: ['size' => $size]);
        }

        $band = $this->seedProduct(
            name: 'CHILL BAND',
            slug: 'chill-band',
            categoryId: $categories['accessories'],
            type: ProductType::Accessory,
            description: 'Banda de enfriamiento Finisher Legacy.',
            qrCapable: true,
        );
        $this->seedVariant($band, 'CHILL-STD', 'Única', 29000, $location, stock: 40);

        $racepack = $this->seedProduct(
            name: 'RACEPACK',
            slug: 'racepack',
            categoryId: $categories['equipment'],
            type: ProductType::Equipment,
            description: 'Kit de accesorios para día de carrera — catálogo inicial, contenido final por definir.',
            qrCapable: true,
        );
        $this->seedVariant($racepack, 'RACEPACK-STD', 'Estándar', 59000, $location, stock: 20);
    }

    /**
     * @return array<string, int>
     */
    private function seedCategories(): array
    {
        $names = ['Legacy' => 'legacy', 'Apparel' => 'apparel', 'Accessories' => 'accessories', 'Equipment' => 'equipment'];
        $ids = [];

        foreach ($names as $name => $slug) {
            $ids[$slug] = ProductCategory::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'active' => true])->id;
        }

        return $ids;
    }

    private function seedProduct(
        string $name,
        string $slug,
        int $categoryId,
        ProductType $type,
        string $description,
        bool $qrCapable,
        bool $requiresShipping = true,
        bool $tracksInventory = true,
    ): Product {
        return Product::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'category_id' => $categoryId,
                'brand' => 'Finisher Legacy',
                'status' => ProductStatus::Active,
                'taxable' => false,
                'requires_shipping' => $requiresShipping,
                'qr_capable' => $qrCapable,
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
