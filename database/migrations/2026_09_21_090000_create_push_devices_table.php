<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backend-only (product consolidation brief §32): a place for a future
 * mobile app to register/unregister its push token. Which push provider
 * (Expo/FCM/APNs) actually sends anything is decided in that other repo —
 * this app only stores the token and never calls a push provider itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform');
            $table->string('provider');
            $table->string('token');
            $table->string('device_name')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['user_id']);
            $table->unique(['user_id', 'token'], 'push_devices_user_token_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
