<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base photos a Legacy story includes, configured by admin per product
 * (most specific) or per product type (default). Without any rule the
 * config fallback `legacy.stories.max_photos` applies. `scope_key`
 * ("product:12" / "type:legacy_tag") keeps one rule per scope.
 * See App\Services\Media\StoryPhotoQuota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_quota_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 80)->unique();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->string('product_type', 40)->nullable();
            $table->unsignedSmallInteger('included_photos');
            $table->string('notes', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_quota_rules');
    }
};
