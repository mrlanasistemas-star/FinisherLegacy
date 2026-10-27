<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Legacy a tag opens (docs/architecture/commerce-os.md §Story): one
 * story per activated tag, owned by the athlete, optionally tied to a real
 * participation (event_participants) or described by hand. Media reuses
 * media_files (MediaStorage) through legacy_story_media. Recipient links
 * ("Para mamá") are legacy_story_shares — a token, never a second profile.
 *
 * support_cases: activation help, NFC/QR failures, defects, returns,
 * replacements — tied to the unit and/or order they are about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_stories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 16)->unique();
            $table->foreignId('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->foreignId('legacy_tag_id')->nullable()->unique()->constrained('legacy_tags')->nullOnDelete();
            $table->foreignId('event_participant_id')->nullable()->constrained('event_participants')->nullOnDelete();
            $table->foreignId('event_edition_id')->nullable()->constrained('event_editions')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('event_name')->nullable();
            $table->string('distance', 60)->nullable();
            $table->string('result_time', 20)->nullable();
            $table->date('race_date')->nullable();
            $table->string('location')->nullable();
            $table->string('recipient_label', 80)->nullable();
            $table->text('dedication')->nullable();
            $table->text('body')->nullable();
            $table->string('external_url', 512)->nullable();
            $table->string('visibility', 16)->default('unlisted');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['athlete_id', 'race_date']);
        });

        Schema::create('legacy_story_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_story_id')->constrained('legacy_stories')->cascadeOnDelete();
            $table->foreignId('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->string('role', 16)->default('photo');
            $table->string('caption', 280)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['legacy_story_id', 'media_file_id']);
        });

        Schema::create('legacy_story_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_story_id')->constrained('legacy_stories')->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->string('label', 80);
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_cases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 24)->unique();
            $table->string('type', 24);
            $table->string('status', 16)->default('open');
            $table->string('priority', 8)->default('normal');
            $table->foreignId('legacy_tag_id')->nullable()->constrained('legacy_tags')->nullOnDelete();
            $table->foreignId('replacement_tag_id')->nullable()->constrained('legacy_tags')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('ally_id')->nullable()->constrained('allies')->nullOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_email')->nullable();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->text('resolution')->nullable();
            $table->unsignedBigInteger('refund_minor')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_cases');
        Schema::dropIfExists('legacy_story_shares');
        Schema::dropIfExists('legacy_story_media');
        Schema::dropIfExists('legacy_stories');
    }
};
