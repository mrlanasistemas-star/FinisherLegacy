<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Nuestro camino" — the company's real trajectory (events, races,
 * milestones), managed from admin. Ships empty; never seeded in
 * production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_milestones', function (Blueprint $table) {
            $table->id();
            // Free text on purpose: "2019", "2021–2023", "Mar 2024"...
            $table->string('period', 40);
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_milestones');
    }
};
