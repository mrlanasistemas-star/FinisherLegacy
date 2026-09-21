<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per Order a coupon was actually applied to — the source of
 * truth ApplyCouponToCart/CheckoutCart count against for usage_limit_total
 * and usage_limit_per_user (brief §31/§39), never a derived counter column
 * that could drift. `code` is denormalized so a coupon that's later
 * renamed doesn't rewrite this history. Insert-only: no `updated_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('coupon_id')->constrained('coupons')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('code');
            $table->unsignedBigInteger('discount_minor');
            $table->timestamp('created_at')->nullable();

            $table->index(['coupon_id', 'user_id'], 'coupon_redemptions_coupon_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
