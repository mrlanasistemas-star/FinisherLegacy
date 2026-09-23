<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-to-user follows (an athlete's public face is the User's
 * AthleteProfile). Self-follow is rejected in App\Actions\Social\FollowAthlete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']);
            $table->index(['following_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_follows');
    }
};
