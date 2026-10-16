<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Dispositivo de seguimiento" (SPEC §75-§78) — a phone or a future GPS
 * band paired to one participation. Pairing code and device token are
 * stored only as SHA-256 hashes; plaintext is returned exactly once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 20); // App\Enums\TrackingSource
            $table->string('public_name', 80);
            $table->string('status', 20)->index(); // App\Enums\TrackingDeviceStatus
            $table->unsignedTinyInteger('battery')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_participant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pairing_code_hash', 64)->nullable()->unique();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('paired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_devices');
    }
};
