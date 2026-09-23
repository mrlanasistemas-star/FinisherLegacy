<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moderation foundation: what was reported, by whom, why, and where the
 * review stands. `target_id` is the target's internal id — the API accepts
 * public identifiers (username / uuid) and resolves them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->string('reason', 32);
            $table->text('details')->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index(['status', 'created_at']);
            $table->unique(['reporter_id', 'target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
