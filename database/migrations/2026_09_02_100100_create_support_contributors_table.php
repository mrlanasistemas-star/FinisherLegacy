<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A family member/friend contributing to a support session — never
 * required to have an account (product UX consolidation brief §26).
 * Minimal PII by design: a display name and, optionally, an email and
 * relationship label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_contributors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_support_session_id')->constrained()->cascadeOnDelete();

            $table->string('display_name');
            $table->string('email')->nullable();
            $table->string('relationship')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_contributors');
    }
};
