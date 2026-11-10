<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| One attempt to write + confirm the permanent URL on a blank NFC chip
| (start → write → read back → confirm). Kept for support: a failed or
| unconfirmed write is visible here and in legacy_tag_events. The chip UID
| is never stored in clear (HMAC only).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_tag_activation_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('legacy_tag_id')->constrained('legacy_tags')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 8);
            $table->string('nfc_uid_hash', 64)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('write_url', 255);
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('failure_reason', 120)->nullable();
            $table->timestamps();

            $table->index(['legacy_tag_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_tag_activation_intents');
    }
};
