<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memory Pack foundation — extra event media on top of the free tier,
 * per participation, optionally traced to the order item that paid for it.
 * Only read while `finisher.memory_packs.enabled` (off until a real
 * product exists).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_entitlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_participant_id')->constrained('event_participants')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedSmallInteger('extra_images')->default(0);
            $table->unsignedSmallInteger('extra_videos')->default(0);
            $table->string('source', 32)->default('manual');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['event_participant_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_entitlements');
    }
};
