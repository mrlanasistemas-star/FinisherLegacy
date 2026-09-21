<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the "premature consumption" bug (consolidation brief §32-§38):
 * CheckoutCart used to create a CouponRedemption the moment a (still
 * unpaid) Order was created, so a pending order already counted the
 * coupon as used. Every existing row backfills to `redeemed` — they were
 * all created for Orders that predate this migration, and treating them
 * as already-consumed is the safe default (never silently frees up a use
 * that may already be reflected in a merchant's expectations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->string('status')->default('redeemed')->after('code');
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->timestamp('redeemed_at')->nullable()->after('expires_at');
            $table->timestamp('released_at')->nullable()->after('redeemed_at');
        });

        DB::table('coupon_redemptions')->update(['redeemed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->dropColumn(['status', 'expires_at', 'redeemed_at', 'released_at']);
        });
    }
};
