<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Native Google / Apple sign-in links. `provider_user_id` is the stable
 * `sub` claim — never the email, which Apple may hide or relay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->boolean('email_is_private_relay')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_accounts');
    }
};
