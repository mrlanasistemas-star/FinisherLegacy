<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag designs (strategy deck pp. 7–8): the front of a Finisher Legacy Tag
 * is full-bleed destination artwork under a header (FL monogram, gold rule,
 * collection name in spaced caps, "TRIATLÓN 2026"); the back is carbon
 * black with "TU HISTORIA SIGUE VIVA" and the NFC mark.
 *
 * - `collections.tag_*` is the collection's primary design.
 * - `collection_designs` holds extra designs of the same collection
 *   (Cozumel has a reef and a raccoon front). Variants only model
 *   size/color attributes, so a design is its own small row instead.
 * - `products.collection_design_id` lets a tag product point at one of
 *   those designs; without it the product uses the collection's primary.
 *
 * Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('tag_front_art', 512)->nullable()->after('hero_path');
            $table->string('tag_accent', 9)->nullable()->after('tag_front_art');
            $table->string('tag_discipline_line', 60)->nullable()->after('tag_accent');
            $table->string('tag_back_line', 80)->default('TU HISTORIA SIGUE VIVA')->after('tag_discipline_line');
        });

        Schema::create('collection_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->string('front_art_path', 512)->nullable();
            $table->string('accent', 9)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['collection_id', 'slug']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('collection_design_id')->nullable()->after('collection_id')
                ->constrained('collection_designs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_design_id');
        });

        Schema::dropIfExists('collection_designs');

        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['tag_front_art', 'tag_accent', 'tag_discipline_line', 'tag_back_line']);
        });
    }
};
