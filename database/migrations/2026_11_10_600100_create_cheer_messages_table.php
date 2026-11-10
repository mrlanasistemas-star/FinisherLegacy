<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrenamientos — the cheer messages family and friends leave through a
 * cheer link: a voice note (a private MediaFile) and/or a short text, plus
 * the sender's name, relationship and an icon. `recipient_user_id` is
 * denormalized from the link so the athlete's playlist is one indexed
 * query. `sender_ip_hash` is an HMAC (never the raw IP) used only for
 * rate limiting and blocking.
 *
 * Purely additive; down() drops only this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheer_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cheer_link_id')->constrained('cheer_links')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('sender_name', 60);
            $table->string('relationship', 40)->nullable();
            $table->string('body', 500)->nullable();
            $table->string('icon', 16)->default('heart');
            $table->foreignId('audio_media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->unsignedSmallInteger('duration_seconds')->default(0);
            $table->string('status', 16)->default('visible');
            $table->timestamp('listened_at')->nullable();
            $table->boolean('favorite')->default(false);
            $table->string('sender_ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'status', 'created_at']);
            $table->index(['cheer_link_id', 'sender_ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheer_messages');
    }
};
