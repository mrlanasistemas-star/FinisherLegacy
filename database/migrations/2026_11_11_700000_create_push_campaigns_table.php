<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones → Campañas push (docs/renovacion/campanas-push.md).
 *
 * `push_campaigns`: one row per campaign an admin composes — content
 * (title ≤65, body ≤240, optional big picture, accent color, channel), the
 * deep-link destination, the audience definition, timing and the
 * aggregated delivery counters.
 *
 * `push_campaign_recipients`: the audience snapshot frozen when the
 * campaign starts sending (one row per user) — makes batches idempotent
 * (a retried batch never notifies anyone twice) and records when the app
 * reports the push was opened (POST /api/v1/notifications/{id}/opened).
 *
 * Purely additive; down() drops only these two tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120)->nullable();

            $table->string('title', 65);
            $table->string('body', 240);
            $table->string('image_path')->nullable();
            $table->boolean('include_logo')->default(true);
            $table->string('color', 7)->nullable();
            $table->string('channel', 40)->default('avisos');
            $table->string('category', 20)->default('promociones');
            $table->boolean('save_to_inbox')->default(true);

            $table->string('link_type', 20)->default('home');
            $table->string('link_value', 500)->nullable();
            $table->string('link_label', 191)->nullable();

            $table->string('audience_type', 20);
            $table->json('audience_params')->nullable();

            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedInteger('audience_users_count')->default(0);
            $table->unsignedInteger('audience_devices_count')->default(0);
            $table->unsignedInteger('batches_total')->default(0);
            $table->unsignedInteger('batches_done')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('inbox_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('last_error', 500)->nullable();

            $table->foreignId('duplicated_from_id')->nullable()->constrained('push_campaigns')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['audience_type', 'started_at']);
        });

        Schema::create('push_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_campaign_id')->constrained('push_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('batch')->default(0);
            $table->unsignedSmallInteger('devices_count')->default(0);
            $table->string('status', 16)->default('pending');
            $table->string('error', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['push_campaign_id', 'user_id']);
            $table->index(['push_campaign_id', 'batch', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_campaign_recipients');
        Schema::dropIfExists('push_campaigns');
    }
};
