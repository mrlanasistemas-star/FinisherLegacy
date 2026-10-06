<?php

namespace Database\Seeders;

use App\Enums\ProductAvailability;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catalog-only pieces of the Finisher Legacy line (Concepto / Próximamente)
 * so the store shows the full product vision. None of them can be bought:
 * availability is never `available` here and they carry no variants/price.
 * An admin can publish one for sale later (add variants + set Disponible)
 * and upload real photos, which replace the conceptual render.
 *
 * Idempotent; never touches a product an admin already edited (firstOrCreate).
 */
class ConceptProductsSeeder extends Seeder
{
    public function run(): void
    {
        $accessories = ProductCategory::query()->where('slug', 'accessories')->value('id');
        $apparel = ProductCategory::query()->where('slug', 'apparel')->value('id');

        $products = [
            ['visera-fl', 'Visera FL', ProductType::Accessory, $accessories, ProductAvailability::ComingSoon, 'Visera de running ligera, banda técnica y monograma FL discreto.'],
            ['gorra-fl', 'Gorra FL', ProductType::Accessory, $accessories, ProductAvailability::Concept, 'Gorra deportiva de cinco paneles en negro con FL dorado.'],
            ['jersey-fl', 'Jersey FL', ProductType::Apparel, $apparel, ProductAvailability::Concept, 'Jersey técnico de alto rendimiento con vivos champagne.'],
            ['backpack-fl', 'Backpack FL', ProductType::Accessory, $accessories, ProductAvailability::Concept, 'Mochila deportiva para día de carrera con compartimento frontal.'],
            ['llavero-nfc', 'Llavero NFC', ProductType::Accessory, $accessories, ProductAvailability::ComingSoon, 'Llavero metálico con panel negro, monograma FL y NFC integrado que abre tu Legacy.'],
        ];

        foreach ($products as [$slug, $name, $type, $categoryId, $availability, $tagline]) {
            Product::query()->firstOrCreate(['slug' => $slug], [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'tagline' => $tagline,
                'description' => $tagline,
                'type' => $type,
                'category_id' => $categoryId,
                'brand' => 'Finisher Legacy',
                'status' => ProductStatus::Active,
                'availability' => $availability,
                'taxable' => false,
                'requires_shipping' => true,
                'qr_capable' => false,
                'tracks_inventory' => true,
                'active' => true,
                // After the products that are actually for sale.
                'sort_order' => 100,
            ]);
        }
    }
}
