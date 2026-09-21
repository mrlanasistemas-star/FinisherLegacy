<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an EventEdition pick a *specific* OrganizerDataSource per purpose
 * (brief §28/§39) instead of only "use the Organizer default" — additive,
 * both nullable, both null means "inherit the Organizer's default source
 * for that purpose" exactly as before. The older data_source_type /
 * data_source_provider_connection_id override columns are untouched and
 * still win first (App\Actions\Integrations\ResolveEventDataSource keeps
 * that precedence), so nothing existing changes behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_editions', function (Blueprint $table) {
            $table->foreignId('participants_data_source_id')->nullable()->after('data_source_provider_connection_id')
                ->constrained('organizer_data_sources')->nullOnDelete();
            $table->foreignId('results_data_source_id')->nullable()->after('participants_data_source_id')
                ->constrained('organizer_data_sources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_editions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('participants_data_source_id');
            $table->dropConstrainedForeignId('results_data_source_id');
        });
    }
};
