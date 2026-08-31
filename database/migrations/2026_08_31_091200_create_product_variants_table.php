<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `attributes` holds free-form JSON (size, color, ...) instead of dedicated
 * columns per attribute — brief §50: "No columnas específicas para cada
 * producto." `base_price_minor` is the fallback the price resolver falls
 * back to when no ProductPriceSchedule applies (brief §203).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->json('attributes')->nullable();
            $table->unsignedBigInteger('base_price_minor');
            $table->char('currency', 3)->default('MXN');
            $table->unsignedBigInteger('cost_minor')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
