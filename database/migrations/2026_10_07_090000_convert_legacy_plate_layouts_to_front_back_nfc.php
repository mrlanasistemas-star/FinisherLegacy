<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy Plate layouts become printed front + back pieces with an NFC
 * chip (no laser engraving, no printed QR):
 *  - each layout field gets a `face` (front|back);
 *  - a layout gets artwork + text color per face and a fixed `layout_slot`
 *    (1..3 — the product offers exactly three layouts);
 *  - existing `qr` layout fields are removed (configuration only — every
 *    produced Plate keeps its own snapshot untouched).
 * Missing back-face fields are created for existing layouts so the editor
 * can show both faces immediately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_plate_model_fields', function (Blueprint $table) {
            $table->string('face', 5)->default('front')->after('field_key');
        });

        Schema::table('legacy_plate_models', function (Blueprint $table) {
            $table->unsignedTinyInteger('layout_slot')->nullable()->unique()->after('slug');
            $table->json('back_area')->nullable()->after('engraving_area');
            $table->string('front_artwork_path')->nullable()->after('preview_image_path');
            $table->string('back_artwork_path')->nullable()->after('front_artwork_path');
            $table->string('front_background', 9)->default('#F3F2EE')->after('back_artwork_path');
            $table->string('back_background', 9)->default('#171714')->after('front_background');
            $table->string('front_text_color', 9)->default('#171714')->after('back_background');
            $table->string('back_text_color', 9)->default('#F4EEDF')->after('front_text_color');
        });

        DB::table('legacy_plate_model_fields')->where('field_key', 'qr')->delete();

        $backDefaults = [
            'event_name' => ['y' => 0.18, 'h' => 0.2, 'size' => 4.5, 'align' => 'center', 'visible' => true],
            'event_date' => ['y' => 0.42, 'h' => 0.14, 'size' => 3.2, 'align' => 'center', 'visible' => true],
            'distance' => ['y' => 0.6, 'h' => 0.16, 'size' => 4, 'align' => 'center', 'visible' => true],
            'overall_position' => ['y' => 0.78, 'h' => 0.12, 'size' => 3, 'align' => 'center', 'visible' => false],
            'bib_number' => ['y' => 0.78, 'h' => 0.12, 'size' => 3, 'align' => 'center', 'visible' => false],
        ];

        $slot = 1;
        foreach (DB::table('legacy_plate_models')->orderBy('id')->get() as $model) {
            $width = (float) $model->width_mm;
            $height = (float) $model->height_mm;

            DB::table('legacy_plate_models')->where('id', $model->id)->update([
                'back_area' => json_encode(['x' => 6, 'y' => 4, 'width' => $width - 12, 'height' => $height - 8]),
                'layout_slot' => $slot <= 3 ? $slot : null,
            ]);
            $slot++;

            $sort = 10;
            foreach ($backDefaults as $key => $d) {
                $exists = DB::table('legacy_plate_model_fields')
                    ->where('legacy_plate_model_id', $model->id)
                    ->where('field_key', $key)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('legacy_plate_model_fields')->insert([
                    'legacy_plate_model_id' => $model->id,
                    'field_key' => $key,
                    'face' => 'back',
                    'x' => 8,
                    'y' => round($height * $d['y'], 2),
                    'width' => $width - 16,
                    'height' => round($height * $d['h'], 2),
                    'font_size' => $d['size'],
                    'alignment' => $d['align'],
                    'required' => false,
                    'visible' => $d['visible'],
                    'sort_order' => $sort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('legacy_plate_models', function (Blueprint $table) {
            $table->dropUnique(['layout_slot']);
            $table->dropColumn([
                'layout_slot', 'back_area', 'front_artwork_path', 'back_artwork_path',
                'front_background', 'back_background', 'front_text_color', 'back_text_color',
            ]);
        });

        Schema::table('legacy_plate_model_fields', function (Blueprint $table) {
            $table->dropColumn('face');
        });
    }
};
