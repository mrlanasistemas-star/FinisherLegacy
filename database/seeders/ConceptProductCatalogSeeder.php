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
 * OPTIONAL — run explicitly when the full product vision should be
 * visible in the storefront (presentations to suppliers/partners):
 *
 *     php artisan db:seed --class=ConceptProductCatalogSeeder
 *
 * Never called by DatabaseSeeder or migrations. Creates catalog entries
 * marked "Concepto" / "Próximamente" — no variants, no prices, no stock,
 * so nothing here can be purchased (AddCartItem rejects them). Product
 * photos are uploaded later from Administración → Productos and replace
 * the storefront placeholders automatically. Idempotent by slug, and
 * never touches a product that already exists.
 */
class ConceptProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ProductCategory::query()->pluck('id', 'slug');

        $concepts = [
            ['Visera Finisher Legacy', 'visera', 'accessories', ProductType::Accessory, ProductAvailability::ComingSoon, 'Ligera, transpirable y lista para el día de carrera.'],
            ['Jersey de ciclismo', 'jersey-ciclismo', 'apparel', ProductType::Apparel, ProductAvailability::Concept, 'Diseño técnico para rodadas largas.'],
            ['Playera técnica', 'playera-tecnica', 'apparel', ProductType::Apparel, ProductAvailability::Concept, 'Tejido técnico para entrenar y competir.'],
            ['Gorra Finisher Legacy', 'gorra', 'accessories', ProductType::Accessory, ProductAvailability::Concept, 'La gorra para antes y después de la meta.'],
            ['Backpack Finisher Legacy', 'backpack', 'accessories', ProductType::Accessory, ProductAvailability::Concept, 'Lleva tu equipo de carrera organizado.'],
            ['Llavero NFC', 'llavero-nfc', 'accessories', ProductType::Accessory, ProductAvailability::ComingSoon, 'Tu perfil Finisher Legacy en tu llavero, con tecnología NFC.'],
        ];

        foreach ($concepts as $index => [$name, $slug, $category, $type, $availability, $tagline]) {
            if (Product::query()->where('slug', $slug)->exists()) {
                continue;
            }

            Product::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'slug' => $slug,
                'tagline' => $tagline,
                'description' => null,
                'type' => $type,
                'category_id' => $categories[$category] ?? null,
                'brand' => 'Finisher Legacy',
                'status' => ProductStatus::Active,
                'availability' => $availability,
                'sort_order' => 100 + $index,
                'taxable' => false,
                'requires_shipping' => true,
                'qr_capable' => $slug === 'llavero-nfc',
                'tracks_inventory' => true,
                'active' => true,
            ]);
        }
    }
}
