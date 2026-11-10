<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepaid terms for expansions (App\Enums\BillingPeriod): a pack is sold
 * `one_time` (permanent capacity), `semiannual` or `annual`. The period
 * and its dates are frozen on each entitlement at payment time, so
 * changing a pack's period never touches what was already sold. Expired
 * capacity stops counting; no content is deleted. `expiry_reminders` keeps
 * which reminders (30 / 7 / 1 days, expired) were already sent.
 * Additive; down() drops exactly what up() adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_expansion_packs', function (Blueprint $table) {
            $table->string('billing_period', 16)->default('one_time')->after('kind');
        });

        Schema::table('media_entitlements', function (Blueprint $table) {
            $table->string('billing_period', 16)->default('one_time')->after('source');
            $table->timestamp('starts_at')->nullable()->after('billing_period');
            $table->timestamp('expires_at')->nullable()->after('starts_at');
            $table->json('expiry_reminders')->nullable()->after('expires_at');
            $table->index(['expires_at', 'revoked_at'], 'media_entitlements_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::table('media_entitlements', function (Blueprint $table) {
            $table->dropIndex('media_entitlements_expiry_index');
            $table->dropColumn(['billing_period', 'starts_at', 'expires_at', 'expiry_reminders']);
        });

        Schema::table('photo_expansion_packs', function (Blueprint $table) {
            $table->dropColumn('billing_period');
        });
    }
};
