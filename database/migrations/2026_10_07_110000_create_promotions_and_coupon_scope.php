<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Ofertas (promotions): automatic discounts, no code, applied to the
 *   unit price of every product or of a selected set; shown as a sale
 *   price with a badge in the store.
 * - Coupons get a scope too: the whole cart (default, unchanged) or only
 *   the cart lines of selected products.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('badge_label', 40)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->unsignedInteger('value');
            $table->string('applies_to', 20)->default('all');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'starts_at', 'ends_at']);
        });

        Schema::create('product_promotion', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'product_id']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->string('applies_to', 20)->default('all')->after('minimum_order_minor');
        });

        Schema::create('coupon_product', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_product');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });

        Schema::dropIfExists('product_promotion');
        Schema::dropIfExists('promotions');
    }
};
