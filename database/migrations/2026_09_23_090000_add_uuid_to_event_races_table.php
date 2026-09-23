<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A public identifier for a race so API clients (preregistration) never
 * need the integer PK. Added nullable, backfilled, then made unique —
 * safe on a table that already has rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_races', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('event_races')->whereNull('uuid')->orderBy('id')->each(function (object $race) {
            DB::table('event_races')->where('id', $race->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('event_races', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('event_races', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
