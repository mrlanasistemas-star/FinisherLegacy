<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comunidad v1 (spec §52–59):
 *  - `event_edition_id`: the event a post relates to, even without a
 *    participation of the author (feed "Eventos" tab + event relevance).
 *    Backfilled from the linked participation.
 *  - `hidden_at` / `hidden_by`: moderation hides content without deleting
 *    it (restorable); the author still sees it, nobody else does.
 *  - Feed indexes: newest-first keyset scans and per-event lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_moments', function (Blueprint $table) {
            $table->foreignId('event_edition_id')->nullable()->after('event_participant_id')
                ->constrained('event_editions')->nullOnDelete();
            $table->timestamp('hidden_at')->nullable()->after('metrics');
            $table->foreignId('hidden_by')->nullable()->after('hidden_at')->constrained('users')->nullOnDelete();

            $table->index(['event_edition_id', 'id']);
            $table->index(['hidden_at', 'id']);
            $table->index('created_at');
        });

        Schema::table('legacy_moment_comments', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('body');
            $table->foreignId('hidden_by')->nullable()->after('hidden_at')->constrained('users')->nullOnDelete();
        });

        DB::table('legacy_moments')
            ->whereNotNull('event_participant_id')
            ->whereNull('event_edition_id')
            ->orderBy('id')
            ->chunkById(500, function ($moments) {
                $editions = DB::table('event_participants')
                    ->whereIn('id', $moments->pluck('event_participant_id'))
                    ->pluck('event_edition_id', 'id');

                foreach ($moments as $moment) {
                    if (isset($editions[$moment->event_participant_id])) {
                        DB::table('legacy_moments')->where('id', $moment->id)
                            ->update(['event_edition_id' => $editions[$moment->event_participant_id]]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('legacy_moment_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropColumn('hidden_at');
        });

        Schema::table('legacy_moments', function (Blueprint $table) {
            $table->dropForeign(['event_edition_id']);
            $table->dropForeign(['hidden_by']);
            $table->dropIndex(['event_edition_id', 'id']);
            $table->dropIndex(['hidden_at', 'id']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['event_edition_id', 'hidden_at', 'hidden_by']);
        });
    }
};
