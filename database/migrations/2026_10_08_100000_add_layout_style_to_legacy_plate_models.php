<?php

use App\Support\LegacyPlateLayouts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each of the three layouts gets its own composition style (nucleo /
 * distancia / trayecto) so they really look different. The three default
 * layouts adopt their new, distinct field arrangement once, here — they
 * were introduced days earlier with an identical placeholder arrangement.
 * Layouts not created by the seeder only get a style by slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_plate_models', function (Blueprint $table) {
            $table->string('layout_style', 20)->nullable()->after('layout_slot');
        });

        $definitions = LegacyPlateLayouts::definitions();

        foreach (DB::table('legacy_plate_models')->get() as $model) {
            $definition = $definitions[$model->slug] ?? null;
            $style = $definition['style'] ?? (LegacyPlateLayouts::STYLES[($model->layout_slot ?? 1) - 1] ?? 'nucleo');

            DB::table('legacy_plate_models')->where('id', $model->id)->update(['layout_style' => $style]);

            if ($definition === null) {
                continue;
            }

            [$w, $h] = [$definition['width'], $definition['height']];
            DB::table('legacy_plate_models')->where('id', $model->id)->update([
                'width_mm' => $w,
                'height_mm' => $h,
                'engraving_area' => json_encode(['x' => 3, 'y' => 3, 'width' => $w - 6, 'height' => $h - 6]),
                'back_area' => json_encode(['x' => 3, 'y' => 3, 'width' => $w - 6, 'height' => $h - 6]),
                'front_background' => $definition['front_background'],
                'front_text_color' => $definition['front_text_color'],
                'back_background' => $definition['back_background'],
                'back_text_color' => $definition['back_text_color'],
            ]);

            foreach ($definition['fields'] as $sort => [$key, $face, $x, $y, $width, $height, $size, $align, $visible]) {
                DB::table('legacy_plate_model_fields')->updateOrInsert(
                    ['legacy_plate_model_id' => $model->id, 'field_key' => $key],
                    [
                        'face' => $face, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height,
                        'font_size' => $size, 'alignment' => $align, 'visible' => $visible, 'sort_order' => $sort,
                        'required' => $key === 'athlete_name', 'updated_at' => now(),
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('legacy_plate_models', function (Blueprint $table) {
            $table->dropColumn('layout_style');
        });
    }
};
