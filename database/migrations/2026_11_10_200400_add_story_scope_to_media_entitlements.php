<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * media_entitlements also carries purchased photo capacity for a Legacy
 * story (source `photo_expansion`, extra_images = photos bought).
 * event_participant_id becomes nullable for those rows; existing Memory
 * Pack rows are untouched. At most one entitlement per order item, so a
 * replayed payment confirmation can never grant twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_entitlements', function (Blueprint $table) {
            $table->foreignId('event_participant_id')->nullable()->change();
            $table->foreignId('legacy_story_id')->nullable()->after('event_participant_id')
                ->constrained('legacy_stories')->cascadeOnDelete();
            $table->foreignId('photo_expansion_pack_id')->nullable()->after('legacy_story_id')
                ->constrained('photo_expansion_packs')->restrictOnDelete();
            $table->foreignId('product_price_schedule_id')->nullable()->after('photo_expansion_pack_id')
                ->constrained('product_price_schedules')->restrictOnDelete();
            $table->unique('order_item_id', 'media_entitlements_order_item_unique');
            $table->index(['legacy_story_id', 'revoked_at'], 'media_entitlements_story_index');
        });
    }

    public function down(): void
    {
        Schema::table('media_entitlements', function (Blueprint $table) {
            $table->dropIndex('media_entitlements_story_index');
            $table->dropUnique('media_entitlements_order_item_unique');
            $table->dropConstrainedForeignId('product_price_schedule_id');
            $table->dropConstrainedForeignId('photo_expansion_pack_id');
            $table->dropConstrainedForeignId('legacy_story_id');
        });

        // NOT NULL is restored only when no story-scoped row would break it —
        // rolling back never deletes purchased rights.
        if (! DB::table('media_entitlements')->whereNull('event_participant_id')->exists()) {
            Schema::table('media_entitlements', function (Blueprint $table) {
                $table->foreignId('event_participant_id')->nullable(false)->change();
            });
        }
    }
};
