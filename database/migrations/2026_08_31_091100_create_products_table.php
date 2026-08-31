<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finisher Legacy's ecosystem catalog (brief §45-§48): Legacy Plate,
 * Trisuit, FAST T1 Socks, Chill Band, Racepack. `type` is a coarse product
 * kind used for domain branching (e.g. Legacy Plate requires an
 * event_edition_id, apparel doesn't — brief §201); `category_id` is purely
 * catalog organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('brand')->default('Finisher Legacy');
            $table->string('status')->default('draft');
            $table->boolean('taxable')->default(false);
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('qr_capable')->default(false);
            $table->boolean('tracks_inventory')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
