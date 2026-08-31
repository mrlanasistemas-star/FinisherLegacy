<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Resolver" without recording what actually happened doesn't help anyone
 * (brief §136-§138) — ResolveIncident now requires a resolution_type and
 * can snapshot before/after state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_incidents', function (Blueprint $table) {
            $table->string('entity_type')->nullable()->after('type');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            $table->string('resolution_type')->nullable()->after('status');
            $table->text('resolution_notes')->nullable()->after('resolution_type');
            $table->json('before_data')->nullable()->after('resolution_notes');
            $table->json('after_data')->nullable()->after('before_data');
        });
    }

    public function down(): void
    {
        Schema::table('event_incidents', function (Blueprint $table) {
            $table->dropColumn(['entity_type', 'entity_id', 'resolution_type', 'resolution_notes', 'before_data', 'after_data']);
        });
    }
};
