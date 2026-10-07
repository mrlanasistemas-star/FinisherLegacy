<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legacy Plate V3 is printed on the front and clipped to the medal ribbon:
 * a product description that still talks about engraving ("grabado",
 * "láser") contradicts the product. Only such descriptions are replaced —
 * any other admin-written copy is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('type', 'legacy_plate')
            ->where(fn ($q) => $q->where('description', 'like', '%grabad%')
                ->orWhere('description', 'like', '%láser%')
                ->orWhere('description', 'like', '%laser%'))
            ->update([
                'description' => 'La pieza física que conecta tu logro con tu Legacy: Zamak niquelado de 70 × 45 mm con tu nombre, tiempo, distancia y ritmo al frente, NFC integrado y un clip de acero inoxidable atrás que la sujeta al listón de tu medalla.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Copy-only change.
    }
};
