<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Compartir seguimiento" (SPEC §81-§82). The access token is stored only
 * as SHA-256; `channel_key` is a separate random value used as the public
 * broadcast channel name, so knowing the channel never grants access to
 * the live page or its history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_shares', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('channel_key', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->index(['event_participant_id', 'revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_shares');
    }
};
