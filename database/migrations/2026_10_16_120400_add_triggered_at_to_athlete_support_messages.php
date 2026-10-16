<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live tracking marks a support message "triggered" the moment the athlete
 * crosses its start/distance/finish condition (SPEC §84) — separate from
 * `consumed_at`, which is when the phone actually played it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_support_messages', function (Blueprint $table) {
            $table->timestamp('triggered_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('athlete_support_messages', function (Blueprint $table) {
            $table->dropColumn('triggered_at');
        });
    }
};
