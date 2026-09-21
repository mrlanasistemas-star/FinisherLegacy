<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evolves organizer_data_sources from "one per Organizer" to "many per
 * Organizer" (brief §23-§27: "no me deja realmente agregar más") —
 * additive and safe: every existing row predates this migration and was
 * therefore the only row for its organizer, so it becomes that
 * organizer's default source under the new model for free, with no
 * backfill needed (`is_default` defaults to true, `purpose` to 'both').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_data_sources', function (Blueprint $table) {
            // Same MySQL/InnoDB quirk as production_jobs' FK index (see
            // 2026_08_24_090100's down()): the unique index is
            // organizer_id_foreign's only supporting index, so it needs a
            // replacement in place before it can be dropped, or MySQL
            // rejects it with error 1553.
            $table->index('organizer_id', 'org_data_sources_organizer_idx');
            $table->dropUnique('organizer_data_sources_organizer_id_unique');
        });

        Schema::table('organizer_data_sources', function (Blueprint $table) {
            $table->string('name')->nullable()->after('organizer_id');
            $table->string('purpose')->default('both')->after('type');
            $table->boolean('is_default')->default(true)->after('active');
            $table->index(['organizer_id', 'purpose', 'is_default'], 'org_data_sources_default_idx');
        });
    }

    public function down(): void
    {
        Schema::table('organizer_data_sources', function (Blueprint $table) {
            $table->dropIndex('org_data_sources_default_idx');
            $table->dropColumn(['name', 'purpose', 'is_default']);
        });

        Schema::table('organizer_data_sources', function (Blueprint $table) {
            $table->unique('organizer_id', 'organizer_data_sources_organizer_id_unique');
            $table->dropIndex('org_data_sources_organizer_idx');
        });
    }
};
