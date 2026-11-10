<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrenamientos — senders an athlete blocked on one of their cheer links.
 * Senders have no account, so a block is the HMAC of their IP for that
 * link (`cheer_messages.sender_ip_hash`). New messages from a blocked
 * sender are silently dropped (the sender is never told).
 *
 * Purely additive; down() drops only this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheer_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cheer_link_id')->constrained('cheer_links')->cascadeOnDelete();
            $table->string('sender_ip_hash', 64);
            $table->foreignId('cheer_message_id')->nullable()->constrained('cheer_messages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cheer_link_id', 'sender_ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheer_blocks');
    }
};
