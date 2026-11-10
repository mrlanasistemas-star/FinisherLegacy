<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Resultado deportivo" of a memory typed by hand: besides time and
 * distance, the athlete's place ("12 de 340", "3º en su categoría").
 * Free text on purpose — official places come from event_results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_stories', function (Blueprint $table) {
            $table->string('result_position', 60)->nullable()->after('result_time');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_stories', function (Blueprint $table) {
            $table->dropColumn('result_position');
        });
    }
};
