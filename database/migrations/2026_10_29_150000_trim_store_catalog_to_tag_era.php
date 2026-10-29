<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Store clean-up for the Finisher Legacy Tag era (2026-10-29):
 *
 * - FAST T1 SOCKS and the historical Legacy Plate product leave the store.
 *   The Tag (sold per collection) is the only Legacy product now.
 * - "Backpack FL" duplicated RACEPACK (which is the backpack) and the
 *   "Llavero NFC" concept competed with the Tag — both removed.
 * - RACEPACK / Trisuit / CHILL BAND get clear, accurate store copy.
 *
 * A product that already has orders, owned items, lots or units can't be
 * deleted (restrictOnDelete) — it is archived instead, so history stays intact.
 */
return new class extends Migration
{
    private const REMOVE = ['fast-t1-socks', 'legacy-plate', 'backpack-fl', 'llavero-nfc'];

    private const COPY = [
        'racepack' => [
            'tagline' => 'La mochila para tu día de carrera.',
            'description' => 'Mochila deportiva Finisher Legacy para el día de carrera: lleva tu equipo, tu cambio de ropa y tu hidratación en un solo lugar, con un punto para colgar tu Finisher Legacy Tag.',
        ],
        'trisuit' => [
            'tagline' => 'Nada, rueda y corre con la misma prenda.',
            'description' => 'Traje de triatlón de una pieza Finisher Legacy: te acompaña en natación, ciclismo y carrera sin cambiarte. Disponible en varios colores y tallas S a XL.',
        ],
        'chill-band' => [
            'tagline' => 'Mójala, póntela y baja la temperatura.',
            'description' => 'Banda de enfriamiento Finisher Legacy para cuello o muñeca. Mójala, escúrrela y póntela antes o después de correr para refrescarte.',
        ],
    ];

    private const CATEGORIES = [
        'legacy' => ['Finisher Legacy Tag', 'El tag de tu carrera: acércale el celular y se abre tu historia.'],
        'apparel' => ['Textil', 'Trisuits y prendas técnicas para competir.'],
        'accessories' => ['Accesorios', 'Viseras, gorras y accesorios para el día de carrera.'],
        'equipment' => ['Equipo', 'Mochila y equipo para el día de carrera.'],
    ];

    public function up(): void
    {
        foreach (self::REMOVE as $slug) {
            $id = DB::table('products')->where('slug', $slug)->value('id');

            if ($id === null) {
                continue;
            }

            try {
                DB::transaction(fn () => DB::table('products')->where('id', $id)->delete());
            } catch (QueryException) {
                DB::table('products')->where('id', $id)->update([
                    'status' => 'archived',
                    'active' => false,
                    'is_featured' => false,
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (self::COPY as $slug => $copy) {
            DB::table('products')->where('slug', $slug)->update([...$copy, 'qr_capable' => false, 'updated_at' => now()]);
        }

        foreach (self::CATEGORIES as $slug => [$name, $description]) {
            DB::table('product_categories')->where('slug', $slug)->update([
                'name' => $name,
                'description' => $description,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data clean-up: removed products are not restored (re-seed if needed).
    }
};
