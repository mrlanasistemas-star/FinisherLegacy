<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-edition override of the Organizer's default data source (brief §18) —
 * null means "inherit from Organizer", resolved centrally by
 * App\Actions\Integrations\ResolveEventDataSource.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_editions', function (Blueprint $table) {
            $table->string('data_source_type')->nullable()->after('results_status');
            $table->foreignId('data_source_provider_connection_id')->nullable()->after('data_source_type')
                ->constrained('provider_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_editions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('data_source_provider_connection_id');
            $table->dropColumn('data_source_type');
        });
    }
};
