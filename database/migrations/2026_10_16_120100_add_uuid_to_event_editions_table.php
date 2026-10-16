<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Media folders are keyed by a stable event uuid
 * (`athletes/{athlete}/events/{event_uuid}/…`, spec §63) — never the
 * sequential id or the editable slug. Additive + guarded so it is a no-op
 * if another branch already added the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('event_editions', 'uuid')) {
            Schema::table('event_editions', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            });
        }

        DB::table('event_editions')->whereNull('uuid')->orderBy('id')->select('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('event_editions')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        // Additive only — the column is kept (media folders reference it).
    }
};
