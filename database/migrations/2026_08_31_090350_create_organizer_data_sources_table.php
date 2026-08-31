<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An Organizer's default "how we receive data" (brief §16-§17) — manual,
 * file, or api (with a ProviderConnection). One per Organizer for now; an
 * EventEdition can override via its own data_source_type columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_data_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->unique()->constrained('organizers')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('provider_connection_id')->nullable()->constrained('provider_connections')->nullOnDelete();
            $table->json('config')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_data_sources');
    }
};
