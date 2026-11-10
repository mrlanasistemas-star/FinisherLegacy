<?php

namespace App\Console\Commands;

use App\Enums\ProductAvailability;
use App\Enums\ProductMediaType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates the "Portarretrato inteligente" in the catalog
 * (docs/renovacion/portarretrato.md): an acrylic frame whose base carries
 * an NFC chip that opens the digital album. It is serialized exactly like
 * a Finisher Legacy Tag (`tracks_units`): every frame is one LegacyTag row
 * with its `/t/{code}` link and its activation code, received by lot and
 * activated from the app or the web.
 *
 * Idempotent and safe to run on the VPS: an existing product (matched by
 * slug) never gets its price, name, texts or status overwritten — the
 * admin owns those after the first run. Without `--price` the product is
 * created as a DRAFT so the owner sets the price in Admin > Productos and
 * publishes it; with `--price` it is published right away.
 *
 *   php artisan catalog:publish-portrait-frame            # borrador
 *   php artisan catalog:publish-portrait-frame --price=1290
 */
class PublishPortraitFrame extends Command
{
    public const string SLUG = 'portarretrato-inteligente';

    public const string SKU = 'PORTARRETRATO-20X25';

    /** Concept render (strategy deck p. 9), relative to public/. */
    private const array IMAGES = [
        'images/products/portarretrato/portarretrato-inteligente.webp' => 'Portarretrato inteligente Finisher Legacy: marco de acrílico con base negra que dice «Acerca tu celular»',
        'images/products/portarretrato/portarretrato-inteligente-escena.webp' => 'Portarretrato inteligente Finisher Legacy sobre un escritorio',
    ];

    protected $signature = 'catalog:publish-portrait-frame
        {--price= : Precio inicial en MXN. Sin precio el producto queda como borrador (el dueño lo fija y publica en el admin)}
        {--dry-run : Muestra lo que haría sin guardar}';

    protected $description = 'Crea el Portarretrato inteligente (marco con NFC, 20×25 cm) en el catálogo';

    public function handle(): int
    {
        $priceOption = $this->option('price');
        $price = $priceOption !== null && $priceOption !== '' ? (float) $priceOption : null;

        if ($price !== null && $price <= 0) {
            $this->error('El precio debe ser mayor a 0.');

            return self::FAILURE;
        }

        $existing = Product::query()->where('slug', self::SLUG)->first();
        $publish = $price !== null;

        $state = match (true) {
            $existing !== null => 'ya existe (sin cambios de precio, nombre ni estado)',
            $publish => 'nuevo · publicado · $'.number_format((float) $price, 2).' MXN',
            default => 'nuevo · BORRADOR sin precio',
        };

        if ($this->option('dry-run')) {
            $this->table(['Producto', 'Nombre', 'Estado'], [[self::SLUG, 'Portarretrato inteligente', $state]]);
            $this->info('Simulación: no se guardó nada.');

            return self::SUCCESS;
        }

        $product = DB::transaction(function () use ($existing, $publish, $price): Product {
            if ($existing !== null) {
                // Only fill what is still missing; never touch what the admin owns.
                $this->ensureVariant($existing, null);
                $this->attachImages($existing);

                return $existing;
            }

            $product = new Product;
            $product->fill([
                'uuid' => (string) Str::uuid(),
                'slug' => self::SLUG,
                'name' => 'Portarretrato inteligente',
                'tagline' => 'Tu foto de meta en casa. Acerca el celular y se abre el álbum.',
                'description' => implode("\n\n", [
                    'Un marco de acrílico de 20 × 25 cm para tu foto de exhibición. No tiene pantalla, batería ni cables: en la base lleva un chip NFC y basta acercar el celular para abrir tu álbum digital con fotos y mensaje de voz.',
                    'Tú diseñas la foto: eliges la imagen, la plantilla y tus datos de carrera, y descargas el archivo al tamaño exacto del marco, listo para imprimir.',
                    'Se activa una sola vez, desde la app o desde la web, con el código que viene en la caja. Tú decides quién puede ver el álbum.',
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
                // One LegacyTag row per frame: public link + activation code.
                'tracks_units' => true,
                'active' => $publish,
                'material' => 'Acrílico transparente con base negra y chip NFC',
                'dimensions' => 'Foto de 20 × 25 cm',
                'instructions' => 'Activa tu portarretrato desde la app o en la web con el código de la caja. Diseña e imprime tu foto de 20 × 25 cm y colócala en el acrílico. Para abrir el álbum, acerca el celular a la base. Evita apoyarlo sobre superficies metálicas: debilitan la lectura NFC.',
            ]);
            $product->save();

            $this->ensureVariant($product, $price);
            $this->attachImages($product);

            return $product;
        });

        $this->table(['Producto', 'Nombre', 'Estado'], [[$product->slug, $product->name, $state]]);

        if ($existing !== null) {
            $this->info('Sin cambios: el producto ya existía. Edítalo en Admin > Productos.');
        } elseif ($publish) {
            $this->info('Listo: publicado. Cambia el precio en Admin > Productos. Para venderlo, recibe un lote de marcos en Inventario > Lotes.');
        } else {
            $this->info('Listo: quedó como BORRADOR. Fija el precio y publícalo en Admin > Productos; después recibe un lote de marcos en Inventario > Lotes.');
        }

        $this->line('Fotos incluidas del álbum: Admin > Límites y tarifas > regla para este producto (ver docs/renovacion/portarretrato.md).');

        return self::SUCCESS;
    }

    /** The single 20 × 25 cm variant, created once. A null price leaves it at 0 for the admin to set. */
    private function ensureVariant(Product $product, ?float $price): void
    {
        if (ProductVariant::query()->where('product_id', $product->id)->exists()) {
            return;
        }

        ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'sku' => self::SKU,
            'name' => '20 × 25 cm',
            'base_price_minor' => (int) round(($price ?? 0) * 100),
            'currency' => 'MXN',
            'active' => true,
        ]);
    }

    /** Copies the concept renders (public/…) to the public disk, once; the first is the primary image. */
    private function attachImages(Product $product): void
    {
        if (ProductMedia::query()->where('product_id', $product->id)->exists()) {
            return;
        }

        $order = 0;

        foreach (self::IMAGES as $relative => $alt) {
            $source = public_path($relative);

            if (! is_file($source)) {
                $this->warn("Sin imagen: no se encontró public/{$relative}");

                continue;
            }

            $path = 'products/portarretrato/'.basename($relative);
            Storage::disk('public')->put($path, (string) file_get_contents($source));

            ProductMedia::query()->create([
                'product_id' => $product->id,
                'type' => ProductMediaType::Image,
                'disk' => 'public',
                'path' => $path,
                'mime' => mime_content_type($source) ?: 'image/webp',
                'size' => filesize($source) ?: null,
                'sort_order' => $order,
                'is_primary' => $order === 0,
                'alt_text' => $alt,
            ]);

            $order++;
        }
    }
}
