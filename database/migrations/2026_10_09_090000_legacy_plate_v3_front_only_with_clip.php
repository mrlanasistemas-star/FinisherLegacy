<?php

use App\Support\LegacyPlateLayouts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy Plate V3: 70 × 45 mm, FRONT-ONLY design, stainless money clip on
 * the back (never printed). Additive and history-preserving:
 *
 *  1. `plates.layout_snapshot` — every already-produced manufactured plate
 *     first gets a frozen copy of the layout it was made with (front AND
 *     back fields, colors, 90 × 34 …), so old orders keep rendering exactly
 *     as produced no matter what the layouts become.
 *  2. Physical spec columns on `legacy_plate_models` (clip size, depth,
 *     materials, spec_version) — editable in admin, defaults = V3.
 *  3. The three default layouts adopt their V3 front-only composition.
 *     Any other (admin-created) layout keeps its fields; fields that were
 *     on the back move to the front HIDDEN, so nothing is lost and nothing
 *     is ever printed on the back again.
 *
 * Columns that only made sense for a printed back (back_area,
 * back_artwork_path, back_background, back_text_color, field `face`) stay
 * in the table for historical reads — the UI no longer exposes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plates', function (Blueprint $table) {
            $table->json('layout_snapshot')->nullable()->after('layout_version');
        });

        Schema::table('legacy_plate_models', function (Blueprint $table) {
            $table->string('spec_version', 10)->default('v3')->after('layout_style');
            $table->decimal('clip_length_mm', 6, 2)->default(52)->after('height_mm');
            $table->decimal('clip_height_mm', 6, 2)->default(20)->after('clip_length_mm');
            $table->decimal('clip_thickness_mm', 6, 2)->default(1)->after('clip_height_mm');
            $table->decimal('total_depth_mm', 6, 2)->default(6)->after('clip_thickness_mm');
            $table->string('body_material', 80)->default('Zamak niquelado')->after('total_depth_mm');
            $table->string('clip_material', 80)->default('Acero inoxidable estampado')->after('body_material');
        });

        $this->snapshotProducedPlates();
        $this->applyV3Layouts();
    }

    private function snapshotProducedPlates(): void
    {
        $models = DB::table('legacy_plate_models')->get()->keyBy('id');
        $fields = DB::table('legacy_plate_model_fields')->orderBy('sort_order')->get()->groupBy('legacy_plate_model_id');

        DB::table('plates')
            ->whereNotNull('legacy_plate_model_id')
            ->whereNull('layout_snapshot')
            ->orderBy('id')
            ->each(function ($plate) use ($models, $fields) {
                $model = $models->get($plate->legacy_plate_model_id);

                if ($model === null) {
                    return;
                }

                $snapshot = [
                    'spec_version' => 'v2',
                    'name' => $model->name,
                    'slug' => $model->slug,
                    'layout_style' => $model->layout_style ?? 'nucleo',
                    'width_mm' => (float) $model->width_mm,
                    'height_mm' => (float) $model->height_mm,
                    'engraving_area' => json_decode((string) $model->engraving_area, true),
                    'back_area' => $model->back_area ? json_decode((string) $model->back_area, true) : null,
                    'front_background' => $model->front_background,
                    'back_background' => $model->back_background,
                    'front_text_color' => $model->front_text_color,
                    'back_text_color' => $model->back_text_color,
                    'front_artwork_path' => $model->front_artwork_path,
                    'back_artwork_path' => $model->back_artwork_path,
                    'fields' => ($fields->get($model->id) ?? collect())->map(fn ($f) => [
                        'field_key' => $f->field_key,
                        'face' => $f->face ?? 'front',
                        'x' => (float) $f->x,
                        'y' => (float) $f->y,
                        'width' => (float) $f->width,
                        'height' => (float) $f->height,
                        'font_size' => $f->font_size !== null ? (float) $f->font_size : null,
                        'alignment' => $f->alignment,
                        'visible' => (bool) $f->visible,
                    ])->values()->all(),
                ];

                DB::table('plates')->where('id', $plate->id)->update(['layout_snapshot' => json_encode($snapshot)]);
            });
    }

    private function applyV3Layouts(): void
    {
        $definitions = LegacyPlateLayouts::v3();
        $spec = LegacyPlateLayouts::SPEC;

        foreach (DB::table('legacy_plate_models')->get() as $model) {
            $definition = $definitions[$model->slug] ?? null;

            if ($definition === null) {
                DB::table('legacy_plate_model_fields')
                    ->where('legacy_plate_model_id', $model->id)
                    ->where('face', 'back')
                    ->update(['face' => 'front', 'visible' => false, 'updated_at' => now()]);

                continue;
            }

            [$w, $h] = [$definition['width'], $definition['height']];
            DB::table('legacy_plate_models')->where('id', $model->id)->update([
                'spec_version' => LegacyPlateLayouts::SPEC_VERSION,
                'width_mm' => $w,
                'height_mm' => $h,
                'clip_length_mm' => $spec['clip_length_mm'],
                'clip_height_mm' => $spec['clip_height_mm'],
                'clip_thickness_mm' => $spec['clip_thickness_mm'],
                'total_depth_mm' => $spec['total_depth_mm'],
                'engraving_area' => json_encode(['x' => 3, 'y' => 3, 'width' => $w - 6, 'height' => $h - 6]),
                'front_background' => $definition['front_background'],
                'front_text_color' => $definition['front_text_color'],
                'description' => $definition['description'],
                'updated_at' => now(),
            ]);

            $keys = [];
            foreach ($definition['fields'] as $sort => [$key, $face, $x, $y, $width, $height, $size, $align, $visible]) {
                $keys[] = $key;
                DB::table('legacy_plate_model_fields')->updateOrInsert(
                    ['legacy_plate_model_id' => $model->id, 'field_key' => $key],
                    [
                        'face' => 'front', 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height,
                        'font_size' => $size, 'alignment' => $align, 'visible' => $visible, 'sort_order' => $sort,
                        'required' => $key === 'athlete_name', 'updated_at' => now(),
                    ],
                );
            }

            DB::table('legacy_plate_model_fields')
                ->where('legacy_plate_model_id', $model->id)
                ->whereNotIn('field_key', $keys)
                ->update(['face' => 'front', 'visible' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Layout positions are not restored (the historical copy of each
        // produced plate lives in layout_snapshot until this drops it).
        Schema::table('legacy_plate_models', function (Blueprint $table) {
            $table->dropColumn([
                'spec_version', 'clip_length_mm', 'clip_height_mm', 'clip_thickness_mm',
                'total_depth_mm', 'body_material', 'clip_material',
            ]);
        });

        Schema::table('plates', function (Blueprint $table) {
            $table->dropColumn('layout_snapshot');
        });
    }
};
