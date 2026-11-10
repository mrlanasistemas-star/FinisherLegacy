<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrenamientos — moderation (docs/renovacion/entrenamientos.md §Moderación).
 *
 * - `cheer_message_reports`: the athlete reports one specific cheer; only a
 *   reported message becomes reviewable (and its audio playable) by staff.
 * - `cheer_rate_limit_hits`: one row per throttled public submit, so staff
 *   see abuse signals without ever reading messages. Only the IP HMAC.
 * - `cheer_links.moderation_disabled_at/_by`: staff disabling a link,
 *   distinct from the athlete's own revoke.
 *
 * Purely additive; down() drops only what this migration adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheer_message_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Nullable: a purge deletes the message but keeps the report as history.
            $table->foreignId('cheer_message_id')->nullable()->constrained('cheer_messages')->nullOnDelete();
            $table->foreignId('cheer_link_id')->constrained('cheer_links')->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 32);
            $table->string('details', 500)->nullable();
            $table->string('sender_ip_hash', 64)->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('resolution_notes', 1000)->nullable();
            $table->timestamps();

            $table->unique(['cheer_message_id', 'reporter_user_id']);
            $table->index(['status', 'created_at']);
            $table->index('sender_ip_hash');
        });

        Schema::create('cheer_rate_limit_hits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheer_link_id')->nullable()->constrained('cheer_links')->cascadeOnDelete();
            $table->string('scope', 16);
            $table->string('sender_ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['cheer_link_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('cheer_links', function (Blueprint $table) {
            $table->timestamp('moderation_disabled_at')->nullable()->after('revoked_at');
            $table->foreignId('moderation_disabled_by')->nullable()->after('moderation_disabled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cheer_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderation_disabled_by');
            $table->dropColumn('moderation_disabled_at');
        });

        Schema::dropIfExists('cheer_rate_limit_hits');
        Schema::dropIfExists('cheer_message_reports');
    }
};
