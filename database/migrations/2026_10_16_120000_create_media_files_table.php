<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per stored media file processed by the upload pipeline
 * (App\Services\Media\MediaStorage, docs/SYNOLOGY.md). Paths are RELATIVE
 * to `disk` — never an absolute NAS path. The owning domain row
 * (AthleteEventMedia, LegacyMomentMedia, ProductMedia…) is `mediable`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->nullableMorphs('mediable');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('kind', 16);
            $table->string('status', 24)->default('processing');
            $table->string('visibility', 16)->default('private');

            $table->string('disk', 64);
            $table->string('directory', 512);
            $table->string('path', 512)->nullable();
            $table->string('original_path', 512)->nullable();
            $table->string('poster_path', 512)->nullable();
            $table->json('variants')->nullable();

            $table->string('mime', 128);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Where the bytes currently wait for the next pipeline step
            // (temp disk while processing; the original on the media disk
            // afterwards, so a failed/pending transcode can be retried).
            $table->string('source_disk', 64)->nullable();
            $table->string('source_path', 512)->nullable();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('directory');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
