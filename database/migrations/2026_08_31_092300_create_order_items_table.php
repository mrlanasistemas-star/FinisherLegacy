<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Full commercial snapshot at purchase time (brief §58) — never depends on
 * Product/ProductVariant pricing changing later. `product_id` is
 * restrictOnDelete so a Product can never be hard-deleted out from under
 * historical order history (brief §152); archiving is the real deletion
 * path. `fulfilled_at` is this project's deliberately simple stand-in for a
 * dedicated Fulfillment model (brief §105 allows "modelo sencillo si el
 * scope lo permite") — see docs/architecture/commerce.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency', 3)->default('MXN');
            $table->json('metadata')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
