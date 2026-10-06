<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo gallery for "Nosotros" (team at events, product development,
 * behind the scenes). Images live on the public disk, never in the DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title', 150)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_gallery_items');
    }
};
