<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy Plate's three price windows (early_presale/kit_pickup/event_day)
 * plus a general `standard` type for non-event store pricing — resolved by
 * App\Actions\Commerce\ResolveProductPrice (brief §40-§44).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('event_edition_id')->nullable()->constrained('event_editions')->cascadeOnDelete();
            $table->string('price_type');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('MXN');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'event_edition_id', 'price_type'], 'price_schedules_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_schedules');
    }
};
