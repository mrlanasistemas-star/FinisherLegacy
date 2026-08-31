<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A physical Legacy Plate reference arriving pre-manufactured (shape,
 * mechanism, relief, decoration already fixed) — Finisher Legacy only ever
 * engraves inside `engraving_area`. See docs/architecture/legacy-plate-v2.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_plate_models', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->decimal('width_mm', 6, 2);
            $table->decimal('height_mm', 6, 2);
            // {x, y, width, height} in mm, relative to the model's own
            // width_mm/height_mm — the only region a layout field may sit in.
            $table->json('engraving_area');
            $table->boolean('active')->default(true);
            $table->string('preview_image_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_plate_models');
    }
};
