<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_moment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_moment_id')->constrained('legacy_moments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            $table->timestamps();

            $table->unique(['legacy_moment_id', 'user_id', 'type']);
            $table->index(['legacy_moment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_moment_reactions');
    }
};
