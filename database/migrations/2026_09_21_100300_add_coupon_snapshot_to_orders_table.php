<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `discount_minor` already exists on orders (it was always part of the
 * total breakdown, just never populated by anything until now). This adds
 * the frozen coupon identity — code/name — so an Order's history reads
 * correctly even if the Coupon is later renamed or deleted (brief §38:
 * "Order snapshot debe congelar coupon code, coupon name").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('event_edition_id')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code')->nullable()->after('coupon_id');
            $table->string('coupon_name')->nullable()->after('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'coupon_name']);
        });
    }
};
