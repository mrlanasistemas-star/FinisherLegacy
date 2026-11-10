<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrenamientos — "Envíame ánimo" links (docs/renovacion/entrenamientos.md).
 * One row per link an athlete shares with family and friends. The 32-char
 * random token IS the capability (never sequential, never the id); the
 * owner can revoke it, let it expire or cap how many messages it takes.
 *
 * Purely additive; down() drops only this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheer_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('title', 80);
            $table->foreignId('legacy_story_id')->nullable()->constrained('legacy_stories')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('max_messages')->nullable();
            $table->unsignedInteger('messages_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheer_links');
    }
};
