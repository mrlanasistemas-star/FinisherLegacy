<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog for the finished Finisher Legacy Tag (docs/architecture/commerce-os.md):
 * a Collection groups designs (destination, discipline, edition…) and each
 * design is a Product with variants/SKUs. `tracks_units` marks products whose
 * every sold piece is an individual, traceable LegacyTag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('discipline', 60)->nullable();
            $table->string('destination', 120)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('edition', 120)->nullable();
            $table->string('season', 60)->nullable();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('status', 16)->default('draft');
            $table->timestamp('launch_at')->nullable();
            $table->string('cover_path', 512)->nullable();
            $table->string('hero_path', 512)->nullable();
            $table->string('accent_color', 9)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->after('category_id')->constrained('collections')->nullOnDelete();
            $table->boolean('tracks_units')->default(false)->after('tracks_inventory');
            $table->string('material')->nullable();
            $table->string('strap_type')->nullable();
            $table->string('dimensions', 120)->nullable();
            $table->text('instructions')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_id');
            $table->dropColumn(['tracks_units', 'material', 'strap_type', 'dimensions', 'instructions', 'seo_title', 'seo_description']);
        });

        Schema::dropIfExists('collections');
    }
};
