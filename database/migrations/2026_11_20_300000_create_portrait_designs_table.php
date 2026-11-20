<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved Portarretrato personalization (docs/renovacion/portarretrato.md
 * §Personalización): which photo of the memory goes in the frame, its crop,
 * the print template and the words. It never copies the photo — it points
 * at the MediaFile already in the story. When it is ordered, the cart line
 * carries `portrait_design_id` and the OrderItem keeps a frozen snapshot
 * (metadata.portrait_design) so later edits never change what production
 * prints. Additive; down() drops exactly what up() adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portrait_designs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_story_id')->constrained('legacy_stories')->cascadeOnDelete();
            // The main photo (a file of the story). Null when that file was removed.
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('title', 80)->nullable();
            $table->string('template', 40);
            $table->json('crop')->nullable();
            $table->json('fields')->nullable();
            $table->timestamp('last_ordered_at')->nullable();
            $table->timestamps();

            $table->index(['legacy_story_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portrait_designs');
    }
};
