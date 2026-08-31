<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy Plate v2 (brief §3-§8): the physical piece is pre-manufactured,
 * Finisher Legacy only engraves dynamic fields. `legacy_plate_model_id` is
 * nullable and `layout_type` defaults to the historical value so every
 * Plate generated before this migration keeps rendering exactly as before
 * — PlateTemplate/PlateTemplateVersion are untouched, see
 * docs/architecture/legacy-plate-v2.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plates', function (Blueprint $table) {
            $table->foreignId('legacy_plate_model_id')->nullable()->after('plate_template_version_id')
                ->constrained('legacy_plate_models')->nullOnDelete();
            // Operator-editable physical snapshot — never mutates
            // Athlete::full_name, see brief §9/§24.
            $table->string('engraving_display_name')->nullable()->after('athlete_name');
            $table->string('layout_type')->default('legacy_template')->after('dynamic_fields');
            $table->string('layout_version')->nullable()->after('layout_type');
        });
    }

    public function down(): void
    {
        Schema::table('plates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legacy_plate_model_id');
            $table->dropColumn(['engraving_display_name', 'layout_type', 'layout_version']);
        });
    }
};
