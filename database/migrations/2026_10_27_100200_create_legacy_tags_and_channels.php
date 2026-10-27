<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The physical unit (docs/architecture/commerce-os.md §Tag): one row per
 * finished Finisher Legacy Tag. `public_code` is the only thing the NFC chip
 * and the backup QR carry (/t/{code}) — random, never the incremental id.
 * `serial` is the internal, human-sortable id printed on packaging lists.
 *
 * Orders keep the channel they were sold through (direct, ally, own event,
 * online) plus delivery data; price schedules may target a channel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('sales_channel', 16)->default('online')->after('fulfillment_status');
            $table->foreignId('ally_id')->nullable()->after('sales_channel')->constrained('allies')->nullOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->after('ally_id')->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('seller_user_id')->nullable()->after('inventory_location_id')->constrained('users')->nullOnDelete();
            $table->string('delivery_method', 16)->nullable();
            $table->json('shipping_address')->nullable();
            $table->string('carrier', 60)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            // POS speed KPI: when the seller opened the sale screen.
            $table->timestamp('sale_started_at')->nullable();

            $table->index(['sales_channel', 'created_at']);
        });

        Schema::table('product_price_schedules', function (Blueprint $table) {
            $table->string('sales_channel', 16)->nullable()->after('price_type');
            $table->foreignId('ally_id')->nullable()->after('sales_channel')->constrained('allies')->cascadeOnDelete();
        });

        Schema::create('legacy_tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('serial', 32)->unique();
            $table->string('public_code', 16)->unique();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('nfc_tag_id')->nullable()->unique()->constrained('nfc_tags')->nullOnDelete();
            $table->string('nfc_uid_hash', 64)->nullable();
            $table->string('status', 16)->default('manufactured');
            $table->string('sales_channel', 16)->nullable();
            $table->foreignId('ally_id')->nullable()->constrained('allies')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('owner_athlete_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->foreignId('athlete_owned_product_id')->nullable()->constrained('athlete_owned_products')->nullOnDelete();
            $table->foreignId('replaces_tag_id')->nullable()->constrained('legacy_tags')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->string('disabled_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'product_variant_id']);
            $table->index(['inventory_location_id', 'status']);
            $table->index(['owner_athlete_id']);
            $table->index(['sold_at']);
        });

        Schema::create('legacy_tag_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_tag_id')->constrained('legacy_tags')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('athlete_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['legacy_tag_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        // Privacy-first analytics: no IP, no UA string — a keyed hash only
        // to de-duplicate, pruned after config('legacy.scans.retention_days').
        Schema::create('legacy_tag_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_tag_id')->constrained('legacy_tags')->cascadeOnDelete();
            $table->string('event', 24);
            $table->string('source', 8);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_hash', 32)->nullable();
            $table->boolean('is_owner')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['legacy_tag_id', 'event']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_tag_scans');
        Schema::dropIfExists('legacy_tag_events');
        Schema::dropIfExists('legacy_tags');

        Schema::table('product_price_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ally_id');
            $table->dropColumn('sales_channel');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['sales_channel', 'created_at']);
            $table->dropConstrainedForeignId('ally_id');
            $table->dropConstrainedForeignId('inventory_location_id');
            $table->dropConstrainedForeignId('seller_user_id');
            $table->dropColumn(['sales_channel', 'delivery_method', 'shipping_address', 'carrier', 'tracking_number', 'shipped_at', 'delivered_at', 'notes', 'sale_started_at']);
        });
    }
};
