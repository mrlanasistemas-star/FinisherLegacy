<?php

namespace App\Console\Commands;

use App\Enums\ProductStatus;
use App\Models\Collection;
use App\Models\CollectionDesign;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Initial tag artwork from the strategy deck (docs/renovacion/
 * FL_Cambio_de_Estrategia.pdf pp. 7–8). The images under
 * public/images/collections/tag-art/ are CONCEPT ART cropped from the deck,
 * used only as starting data until the final artwork is approved.
 *
 * Idempotent: matches existing collections by slug (or destination), only
 * fills empty tag fields unless --force, and creates a missing collection
 * ONLY as a draft — no prices, no products, no stock.
 */
#[Signature('collections:seed-tag-art {--force : Overwrite tag fields an admin already filled}')]
#[Description('Seed the concept tag artwork (Veracruz, Cozumel, Valle de Bravo) into collections')]
class SeedCollectionTagArt extends Command
{
    private const string ART = '/images/collections/tag-art/';

    /**
     * @var list<array{slug: string, name: string, destination: string, discipline: string, year: int, art: string, accent: string, designs: list<array{slug: string, name: string, art: string, accent: string|null}>}>
     */
    private const array COLLECTIONS = [
        [
            'slug' => 'veracruz', 'name' => 'Veracruz', 'destination' => 'Veracruz', 'discipline' => 'Triatlón', 'year' => 2026,
            'art' => 'veracruz.webp', 'accent' => '#E0B36A', 'designs' => [],
        ],
        [
            'slug' => 'cozumel', 'name' => 'Cozumel', 'destination' => 'Cozumel', 'discipline' => 'Triatlón', 'year' => 2026,
            'art' => 'cozumel-arrecife.webp', 'accent' => '#3FA7A3',
            'designs' => [
                ['slug' => 'mapache', 'name' => 'Cozumel · Mapache', 'art' => 'cozumel-mapache.webp', 'accent' => '#C9AE8B'],
            ],
        ],
        [
            'slug' => 'valle-de-bravo', 'name' => 'Valle de Bravo', 'destination' => 'Valle de Bravo', 'discipline' => 'Triatlón', 'year' => 2026,
            'art' => 'valle-de-bravo.webp', 'accent' => '#C9AE8B', 'designs' => [],
        ],
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        foreach (self::COLLECTIONS as $seed) {
            if (! is_file(public_path(ltrim(self::ART.$seed['art'], '/')))) {
                $this->warn("Falta el arte {$seed['art']}; se omite {$seed['name']}.");

                continue;
            }

            $collection = $this->find($seed);
            $created = false;

            if ($collection === null) {
                $collection = Collection::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => $seed['name'],
                    'slug' => $seed['slug'],
                    'destination' => $seed['destination'],
                    'discipline' => $seed['discipline'],
                    'year' => $seed['year'],
                    // Never published by a seeder: an admin decides.
                    'status' => ProductStatus::Draft,
                    'sort_order' => 0,
                ]);
                $created = true;
            }

            $fill = [
                'tag_front_art' => self::ART.$seed['art'],
                'tag_accent' => $seed['accent'],
            ];

            foreach ($fill as $column => $value) {
                if ($force || blank($collection->{$column})) {
                    $collection->{$column} = $value;
                }
            }

            $collection->save();

            foreach ($seed['designs'] as $i => $design) {
                $row = CollectionDesign::query()->firstOrNew(['collection_id' => $collection->id, 'slug' => $design['slug']]);

                if (! $row->exists || $force) {
                    $row->fill([
                        'name' => $design['name'],
                        'front_art_path' => self::ART.$design['art'],
                        'accent' => $design['accent'],
                        'sort_order' => $i + 1,
                    ])->save();
                }
            }

            $this->line(sprintf(
                '%s %s (%s, %s) · %d diseño(s) extra',
                $created ? 'Creada (borrador)' : 'Actualizada',
                $collection->name,
                $collection->slug,
                $collection->status->value,
                count($seed['designs']),
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{slug: string, destination: string}  $seed
     */
    private function find(array $seed): ?Collection
    {
        return Collection::query()->where('slug', $seed['slug'])->first()
            ?? Collection::query()->where('slug', 'like', $seed['slug'].'-%')->orderBy('id')->first()
            ?? Collection::query()->where('destination', $seed['destination'])->orderBy('id')->first();
    }
}
