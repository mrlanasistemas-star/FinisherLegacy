<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw GPS points (SPEC §79). No PII: only the session reference and the
 * measurement. Unique (session, recorded_at) makes a retrying phone's
 * duplicate batch idempotent at the database level too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy', 7, 2)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->decimal('speed', 6, 2)->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable()->index();

            $table->unique(['tracking_session_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_points');
    }
};
