<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing live-tracking shares instead of a parallel table:
 *
 *  - `audience`: `link` (the public `/live/{token}` link, as before) or
 *    `family` (one row per accepted family link, viewed signed-in in the
 *    app through the API — its token is random and never handed out);
 *  - `athlete_family_link_id`: the recipient of a `family` share;
 *  - `ends_with_session` + `ended_at`: explicit shares stop the moment the
 *    session stops ("hasta que termine"), so no location is served after.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded column by column so a half-applied run (MariaDB DDL is not
        // transactional) can simply be migrated again.
        Schema::table('tracking_shares', function (Blueprint $table) {
            if (! Schema::hasColumn('tracking_shares', 'audience')) {
                $table->string('audience', 16)->default('link')->after('tracking_session_id');
            }
            if (! Schema::hasColumn('tracking_shares', 'athlete_family_link_id')) {
                $table->foreignId('athlete_family_link_id')->nullable()->after('audience')
                    ->constrained('athlete_family_links')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('tracking_shares', 'ends_with_session')) {
                $table->boolean('ends_with_session')->default(false)->after('expires_at');
            }
            if (! Schema::hasColumn('tracking_shares', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('ends_with_session');
            }
        });

        Schema::table('tracking_shares', function (Blueprint $table) {
            // Short explicit names: the generated ones exceed MariaDB's 64 chars.
            $table->index(['athlete_family_link_id', 'revoked_at', 'expires_at'], 'tracking_shares_family_usable_idx');
            $table->index(['tracking_session_id', 'audience'], 'tracking_shares_session_audience_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_shares', function (Blueprint $table) {
            $table->dropIndex('tracking_shares_family_usable_idx');
            $table->dropIndex('tracking_shares_session_audience_idx');
            $table->dropConstrainedForeignId('athlete_family_link_id');
            $table->dropColumn(['audience', 'ends_with_session', 'ended_at']);
        });
    }
};
