<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `model_translations` — translations of admin-entered content (product
 * names, collection texts…), one row per model + locale + field
 * (docs/renovacion/idiomas.md §Contenido dinámico). The Spanish original
 * stays in the model's own column; `source_hash` tells when it changed.
 * status: machine · reviewed (edited by a person) · stale (machine text whose
 * source changed) · needs_review (a person's text whose source changed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_translations', function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 120);
            $table->unsignedBigInteger('model_id');
            $table->string('locale', 12);
            $table->string('field', 64);
            $table->text('value')->nullable();
            $table->string('status', 16)->default('machine');
            $table->char('source_hash', 64);
            $table->timestamps();

            $table->unique(['model_type', 'model_id', 'locale', 'field'], 'model_translations_unique');
            $table->index(['locale', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_translations');
    }
};
