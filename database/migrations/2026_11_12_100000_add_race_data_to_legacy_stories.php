<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Race data the athlete can show on the memory a tag opens: bib number,
 * display name, pace, category and its positions, finish status, elevation
 * gain and sport. All nullable — when the story points at a participation
 * with official results those win in presentation
 * (App\Support\LegacyTags\StoryRaceData). Additive; down() drops exactly
 * what up() adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_stories', function (Blueprint $table) {
            $table->string('bib_number', 12)->nullable()->after('result_position');
            $table->string('athlete_display_name', 80)->nullable()->after('bib_number');
            $table->string('pace', 20)->nullable()->after('athlete_display_name');
            $table->string('category', 40)->nullable()->after('pace');
            $table->string('category_position', 20)->nullable()->after('category');
            $table->string('gender_position', 20)->nullable()->after('category_position');
            $table->string('finish_status', 20)->nullable()->after('gender_position');
            $table->unsignedInteger('elevation_gain_m')->nullable()->after('finish_status');
            $table->foreignId('sport_id')->nullable()->after('elevation_gain_m')->constrained('sports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legacy_stories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_id');
            $table->dropColumn([
                'bib_number', 'athlete_display_name', 'pace', 'category', 'category_position',
                'gender_position', 'finish_status', 'elevation_gain_m',
            ]);
        });
    }
};
