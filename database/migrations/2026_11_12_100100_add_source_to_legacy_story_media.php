<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a story file came from: `upload` (the owner's device) or
 * `purchased` (an event photo bought on the site, photo_sale_id). A sale
 * can be imported into a story only once; when the sale is refunded the
 * imported row is removed (App\Actions\LegacyTags\RemoveRefundedStoryPhotos).
 * Additive; down() drops exactly what up() adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_story_media', function (Blueprint $table) {
            $table->string('source', 16)->default('upload')->after('role');
            $table->foreignId('photo_sale_id')->nullable()->after('source');
            $table->index('photo_sale_id', 'legacy_story_media_sale_index');
            $table->foreign('photo_sale_id', 'legacy_story_media_sale_foreign')->references('id')->on('photo_sales')->nullOnDelete();
            $table->unique(['legacy_story_id', 'photo_sale_id'], 'legacy_story_media_sale_unique');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_story_media', function (Blueprint $table) {
            $table->dropForeign('legacy_story_media_sale_foreign');
            $table->dropUnique('legacy_story_media_sale_unique');
            $table->dropIndex('legacy_story_media_sale_index');
            $table->dropColumn(['photo_sale_id', 'source']);
        });
    }
};
