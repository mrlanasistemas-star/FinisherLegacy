<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The whitelist of dynamic fields a LegacyPlateModel's engraving_area
 * accepts, and where each sits — no arbitrary decoration, see brief §6/§133.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_plate_model_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_plate_model_id')->constrained('legacy_plate_models')->cascadeOnDelete();
            $table->string('field_key');
            $table->decimal('x', 6, 2);
            $table->decimal('y', 6, 2);
            $table->decimal('width', 6, 2);
            $table->decimal('height', 6, 2);
            $table->decimal('font_size', 5, 2)->nullable();
            $table->string('alignment')->default('left');
            $table->unsignedSmallInteger('max_chars')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['legacy_plate_model_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_plate_model_fields');
    }
};
