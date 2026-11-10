<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real delivery numbers for push campaigns (docs/renovacion/campanas-push.md
 * §Envío). Until now a campaign only knew how many messages it had QUEUED
 * with the provider; a push ticket did not say which campaign it came from.
 *
 *  - `push_tickets.push_campaign_id`: set when the send comes from a
 *    campaign (PushMessage data `campaign_id`), null for everything else.
 *  - `push_campaigns.delivered_count` / `delivery_failed_count`: aggregated
 *    by `push:check-receipts` when the receipt arrives (and by the gateway
 *    for messages Expo rejects straight away) — counters, because ticket
 *    rows are pruned after two days and the panel must keep its history.
 *
 * Purely additive; down() drops only these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_tickets', function (Blueprint $table) {
            $table->foreignId('push_campaign_id')->nullable()->after('push_device_id')
                ->constrained('push_campaigns')->nullOnDelete();
        });

        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->unsignedInteger('delivered_count')->default(0)->after('failed_count');
            $table->unsignedInteger('delivery_failed_count')->default(0)->after('delivered_count');
        });
    }

    public function down(): void
    {
        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->dropColumn(['delivered_count', 'delivery_failed_count']);
        });

        Schema::table('push_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('push_campaign_id');
        });
    }
};
