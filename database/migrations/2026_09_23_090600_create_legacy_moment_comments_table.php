<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Mensajes de apoyo" under a Moment — flat (no nested replies), never a
 * private chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_moment_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('legacy_moment_id')->constrained('legacy_moments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['legacy_moment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_moment_comments');
    }
};
