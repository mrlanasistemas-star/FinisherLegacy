<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App\Queries\Operations\GetEventParticipantMetrics filters orders by
 * `event_edition_id` AND `payment_status` together for every per-event
 * revenue/sales figure — the existing bare `event_edition_id` index can't
 * serve that filter as efficiently as a composite one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['event_edition_id', 'payment_status'], 'orders_edition_payment_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_edition_payment_status_index');
        });
    }
};
