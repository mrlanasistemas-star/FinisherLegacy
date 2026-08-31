<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slice 6 DB index audit (docs/api/v1.md §Performance) — `production_jobs`
     * already indexed `status` and `(status, production_device_id)`, but
     * every "next job for THIS event" query
     * (App\Services\Devices\ProductionJobClaimService::availableJobsQuery(),
     * App\Queries\Operations\GetEventOperationsDashboard::productionStatus())
     * filters by `event_edition_id` + `status` together — a composite
     * covers that pattern directly instead of relying on the `status`
     * index alone plus a row-by-row filter.
     */
    public function up(): void
    {
        Schema::table('production_jobs', function (Blueprint $table) {
            $table->index(['event_edition_id', 'status'], 'production_jobs_event_edition_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('production_jobs', function (Blueprint $table) {
            // MySQL/InnoDB silently drops a foreign key's own auto-generated
            // supporting index once another index covers the same leftmost
            // column — this composite index became `event_edition_id_foreign`'s
            // only supporting index the moment up() ran. Dropping it directly
            // fails with error 1553 ("needed in a foreign key constraint");
            // a replacement single-column index must exist first so the FK
            // is never left without one, even for the instant between the
            // two statements.
            $table->index('event_edition_id', 'production_jobs_event_edition_id_index');
            $table->dropIndex('production_jobs_event_edition_status_index');
        });
    }
};
