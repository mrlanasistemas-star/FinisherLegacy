<?php

namespace App\Console\Commands;

use App\Enums\ProductAvailability;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Support\Training\CheerCards;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the "Tarjeta de ánimo NFC" in the catalog
 * (docs/renovacion/tarjeta-animo.md): a card with a blank NTAG213 chip that,
 * once written with `/t/{code}` and bound to one of the athlete's
 * "Envíame ánimo" links, opens that link in any phone's browser — family
 * taps it and leaves a voice message, no account, no app. Serialized like a
 * Finisher Legacy Tag (`tracks_units`): every card is one LegacyTag row
 * (purpose `cheer`) received by lot, so the existing chip writer, activation
 * and anti-duplicate rules apply.
 *
 * Idempotent and safe on the VPS: an existing product (matched by slug)
 * never gets its price, name, texts or status overwritten. Without
 * `--price` it is created as a DRAFT so the owner sets the price in
 * Admin > Productos and publishes it — no price is ever invented.
 *
 *   php artisan catalog:publish-cheer-card            # borrador
 *   php artisan catalog:publish-cheer-card --price=…  # publicado
 */
class PublishCheerCard extends Command
{
    protected $signature = 'catalog:publish-cheer-card
        {--price= : Precio inicial en MXN. Sin precio el producto queda como borrador (el dueño lo fija y publica en el admin)}
        {--dry-run : Muestra lo que haría sin guardar}';

    protected $description = 'Crea la Tarjeta de ánimo NFC (abre un enlace «Envíame ánimo») en el catálogo';

    public function handle(): int
    {
        $priceOption = $this->option('price');
        $price = $priceOption !== null && $priceOption !== '' ? (float) $priceOption : null;

        if ($price !== null && $price <= 0) {
            $this->error('El precio debe ser mayor a 0.');

            return self::FAILURE;
        }

        $existing = Product::query()->where('slug', CheerCards::PRODUCT_SLUG)->first();
        $publish = $price !== null;

        $state = match (true) {
            $existing !== null => 'ya existe (sin cambios de precio, nombre ni estado)',
            $publish => 'nuevo · publicado · $'.number_format((float) $price, 2).' MXN',
            default => 'nuevo · BORRADOR sin precio',
        };

        if ($this->option('dry-run')) {
            $this->table(['Producto', 'Nombre', 'Estado'], [[CheerCards::PRODUCT_SLUG, 'Tarjeta de ánimo NFC', $state]]);
            $this->info('Simulación: no se guardó nada.');

            return self::SUCCESS;
        }

        $product = DB::transaction(function () use ($existing, $publish, $price): Product {
            if ($existing !== null) {
                $this->ensureVariant($existing, null);

                return $existing;
            }

            $product = new Product;
            $product->fill([
                'uuid' => (string) Str::uuid(),
                'slug' => CheerCards::PRODUCT_SLUG,
                'name' => 'Tarjeta de ánimo NFC',
                'tagline' => 'Tu familia la acerca al celular y te deja un mensaje de voz para tu entrenamiento.',
                'description' => implode("\n\n", [
                    'Una tarjeta con chip NFC ligada a tu enlace «Envíame ánimo». Quien la tiene solo la acerca a su celular: se abre la página para grabarte un mensaje de voz, sin cuenta y sin app. Tú lo escuchas durante tu entrenamiento.',
                    'Cada vez que la acercan pueden dejarte otro mensaje. Ideal para regalarla a mamá, a tu pareja o a tu equipo; la enviamos a la dirección que elijas.',
                    'Al comprarla eliges el enlace que abrirá (o creas uno, por ejemplo «Para mamá») y llega lista. Desde la app puedes cambiarla a otro enlace o desactivarla cuando quieras.',
                ]),
                'type' => ProductType::Accessory,
                'category_id' => ProductCategory::query()->where('slug', 'accessories')->value('id'),
                'brand' => 'Finisher Legacy',
                'status' => $publish ? ProductStatus::Active : ProductStatus::Draft,
                'availability' => ProductAvailability::Available,
                'taxable' => false,
                'requires_shipping' => true,
                'qr_capable' => false,
                'tracks_inventory' => true,
                // One LegacyTag row (purpose `cheer`) per card: /t/{code} + activation code.
                'tracks_units' => true,
                'active' => $publish,
                'material' => 'Tarjeta PVC con chip NFC NTAG213',
                'dimensions' => 'Tamaño tarjeta de crédito (85.6 × 54 mm)',
                'instructions' => 'Si la compraste con un enlace elegido, llega lista: solo acércala a un celular. Si no, actívala en la app: Activar → Tarjeta de ánimo → elige el enlace. Para dejar un mensaje, acerca la parte trasera del celular a la tarjeta. Evita superficies metálicas: debilitan la lectura NFC.',
            ]);
            $product->save();

            $this->ensureVariant($product, $price);

            return $product;
        });

        $this->table(['Producto', 'Nombre', 'Estado'], [[$product->slug, $product->name, $state]]);

        if ($existing !== null) {
            $this->info('Sin cambios: el producto ya existía. Edítalo en Admin > Productos.');
        } elseif ($publish) {
            $this->info('Listo: publicado. Sube sus fotos y cambia el precio en Admin > Productos. Para venderla, recibe un lote de tarjetas en Inventario > Lotes.');
        } else {
            $this->info('Listo: quedó como BORRADOR. Fija el precio, sube sus fotos y publícala en Admin > Productos; después recibe un lote de tarjetas en Inventario > Lotes.');
        }

        $this->line('Precios de los mensajes: Admin > Límites y tarifas > Mensajes de ánimo (ver docs/renovacion/tarjeta-animo.md).');

        return self::SUCCESS;
    }

    /** The single variant, created once. A null price leaves it at 0 for the admin to set. */
    private function ensureVariant(Product $product, ?float $price): void
    {
        if (ProductVariant::query()->where('product_id', $product->id)->exists()) {
            return;
        }

        ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'sku' => CheerCards::SKU,
            'name' => 'Tarjeta NFC',
            'base_price_minor' => (int) round(($price ?? 0) * 100),
            'currency' => 'MXN',
            'active' => true,
        ]);
    }
}
