<?php

namespace App\Console\Commands;

use App\Enums\BillingPeriod;
use App\Enums\ProductPriceType;
use App\Enums\ProductType;
use App\Enums\StoryQuotaKind;
use App\Models\PhotoExpansionPack;
use App\Models\PhotoQuotaRule;
use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Starting limits and example tariffs for Límites y tarifas
 * (docs/renovacion/cuotas-y-precios.md §Valores iniciales): a global
 * rule, the Finisher Legacy Tag rule, the Portarretrato rule (when that
 * product exists) and a few expansion packs per kind with MXN prices and
 * price steps. They are a starting point for the owner to edit in admin.
 *
 * Idempotent and safe on production: it only CREATES what is missing — a
 * rule whose scope already has one, a pack with the same kind + name
 * (even archived) and any existing price are never touched.
 */
#[Signature('quotas:seed-defaults {--dry-run : Mostrar lo que se crearía sin escribir nada}')]
#[Description('Crea los límites y tarifas iniciales que falten (nunca sobrescribe los existentes).')]
class SeedQuotaDefaults extends Command
{
    /** Slug of the Portarretrato product, when it exists. */
    public const string PORTRAIT_SLUG = 'portarretrato-inteligente';

    /**
     * @return list<array{kind: StoryQuotaKind, period: BillingPeriod, name: string, description: string, units: int, sort: int, amount: int, steps: array<int, int>}>
     */
    public static function packs(): array
    {
        return [
            [
                'kind' => StoryQuotaKind::Photos, 'period' => BillingPeriod::OneTime, 'name' => '+10 fotos', 'units' => 10, 'sort' => 10,
                'description' => 'Diez fotos más para tu recuerdo. Se quedan para siempre.',
                'amount' => 5900, 'steps' => [31 => 7900, 61 => 9900],
            ],
            [
                'kind' => StoryQuotaKind::Photos, 'period' => BillingPeriod::Annual, 'name' => '+20 fotos', 'units' => 20, 'sort' => 20,
                'description' => 'Veinte fotos más durante un año. Renuévalo cuando quieras; tus fotos nunca se borran.',
                'amount' => 9900, 'steps' => [41 => 12900, 81 => 15900],
            ],
            [
                'kind' => StoryQuotaKind::Videos, 'period' => BillingPeriod::OneTime, 'name' => '+1 video', 'units' => 1, 'sort' => 10,
                'description' => 'Un video más para tu recuerdo. Se queda para siempre.',
                'amount' => 7900, 'steps' => [],
            ],
            [
                'kind' => StoryQuotaKind::Videos, 'period' => BillingPeriod::Annual, 'name' => '+3 videos', 'units' => 3, 'sort' => 20,
                'description' => 'Tres videos más durante un año. Renuévalo cuando quieras; tus videos nunca se borran.',
                'amount' => 14900, 'steps' => [7 => 19900],
            ],
            [
                'kind' => StoryQuotaKind::Audio, 'period' => BillingPeriod::OneTime, 'name' => '+2 mensajes de voz', 'units' => 2, 'sort' => 10,
                'description' => 'Dos mensajes de voz más para tu recuerdo. Se quedan para siempre.',
                'amount' => 3900, 'steps' => [],
            ],
        ];
    }

    /**
     * @return array<string, array{product_id: int|null, product_type: string|null, limits: array<string, int>, notes: string}>
     */
    public static function rules(): array
    {
        $rules = [
            PhotoQuotaRule::GLOBAL_KEY => [
                'product_id' => null, 'product_type' => null,
                'limits' => [
                    'included_photos' => 10, 'included_videos' => 1, 'included_audio' => 1,
                    'max_video_seconds' => 60, 'max_audio_seconds' => 180,
                    'cap_photos' => 200, 'cap_videos' => 20, 'cap_audio' => 10,
                ],
                'notes' => 'Valores iniciales para todos los recuerdos.',
            ],
            PhotoQuotaRule::typeKey(ProductType::LegacyTag) => [
                'product_id' => null, 'product_type' => ProductType::LegacyTag->value,
                'limits' => ['included_photos' => 10, 'included_videos' => 1, 'included_audio' => 1, 'max_video_seconds' => 60, 'max_audio_seconds' => 180],
                'notes' => 'Finisher Legacy Tag: 10 fotos, 1 video de hasta 60 s y 1 mensaje de voz de hasta 3 min.',
            ],
        ];

        $portrait = Product::query()->where('slug', self::PORTRAIT_SLUG)->value('id');

        if ($portrait !== null) {
            $rules[PhotoQuotaRule::productKey((int) $portrait)] = [
                'product_id' => (int) $portrait, 'product_type' => null,
                'limits' => ['included_photos' => 5],
                'notes' => 'Portarretrato: 5 fotos incluidas (videos y voz heredan del valor general).',
            ];
        }

        return $rules;
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $productId = Product::query()->where('slug', PhotoExpansionPack::PRODUCT_SLUG)->value('id');

        if ($productId === null) {
            $this->error('Falta el producto interno de ampliaciones. Ejecuta primero: php artisan migrate');

            return self::FAILURE;
        }

        $created = ['rules' => 0, 'packs' => 0];

        DB::transaction(function () use ($dry, $productId, &$created) {
            foreach (self::rules() as $key => $rule) {
                if (PhotoQuotaRule::query()->where('scope_key', $key)->exists()) {
                    $this->line("  = límite «{$key}» ya existe: sin cambios");

                    continue;
                }

                $this->info("  + límite «{$key}»: ".json_encode($rule['limits']));
                $created['rules']++;

                if (! $dry) {
                    PhotoQuotaRule::create(['scope_key' => $key, 'product_id' => $rule['product_id'], 'product_type' => $rule['product_type'], ...$rule['limits'], 'notes' => $rule['notes']]);
                }
            }

            foreach (self::packs() as $spec) {
                if (PhotoExpansionPack::query()->where('kind', $spec['kind']->value)->where('name', $spec['name'])->exists()) {
                    $this->line("  = ampliación «{$spec['name']}» ({$spec['kind']->value}) ya existe: sin cambios");

                    continue;
                }

                $this->info(sprintf('  + ampliación «%s» (%s, %s): $%s MXN%s', $spec['name'], $spec['kind']->value, $spec['period']->value, number_format($spec['amount'] / 100, 2), $spec['steps'] === [] ? '' : ' + '.count($spec['steps']).' banderas'));
                $created['packs']++;

                if ($dry) {
                    continue;
                }

                $pack = PhotoExpansionPack::create([
                    'kind' => $spec['kind'],
                    'billing_period' => $spec['period'],
                    'name' => $spec['name'],
                    'description' => $spec['description'],
                    'photos' => $spec['units'],
                    'active' => true,
                    'sort_order' => $spec['sort'],
                ]);

                $schedule = $pack->prices()->create([
                    'product_id' => $productId,
                    'price_type' => ProductPriceType::Standard,
                    'amount_minor' => $spec['amount'],
                    'currency' => 'MXN',
                    'tax_rate' => 0.16,
                    'tax_included' => true,
                    'starts_at' => now(),
                    'active' => true,
                ]);

                foreach ($spec['steps'] as $from => $amount) {
                    $schedule->steps()->create(['from_units' => $from, 'amount_minor' => $amount]);
                }
            }
        });

        $this->newLine();
        $this->info(($dry ? '[simulación] ' : '')."Límites creados: {$created['rules']}. Ampliaciones creadas: {$created['packs']}.");
        $this->line('Son valores iniciales: edítalos en Administración → Límites y tarifas.');

        return self::SUCCESS;
    }
}
