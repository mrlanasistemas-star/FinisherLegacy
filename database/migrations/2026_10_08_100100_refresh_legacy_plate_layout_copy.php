<?php

use App\Support\LegacyPlateLayouts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The default layouts' descriptions still described an engraved product
 * ("zona de grabado", "relieve", "láser"). The plate is printed and
 * resin-coated now: replace only descriptions that still carry that
 * wording — an admin's own text is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (LegacyPlateLayouts::definitions() as $slug => $definition) {
            DB::table('legacy_plate_models')
                ->where('slug', $slug)
                ->where(fn ($q) => $q->where('description', 'like', '%grabad%')
                    ->orWhere('description', 'like', '%relieve%')
                    ->orWhere('description', 'like', '%láser%')
                    ->orWhere('description', 'like', '%laser%')
                    ->orWhereNull('description'))
                ->update(['description' => $definition['description']]);
        }
    }

    public function down(): void
    {
        // Copy-only change; nothing to restore.
    }
};
