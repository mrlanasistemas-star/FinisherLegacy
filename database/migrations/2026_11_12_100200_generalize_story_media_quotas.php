<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limits per media kind (docs/renovacion/cuotas-y-precios.md):
 *
 * - photo_quota_rules: videos / audio counts, max seconds per video and
 *   per audio, and an optional hard cap per story for each kind. Every
 *   value is nullable = "inherit from the next scope" (product → product
 *   type → `global` rule → config); `included_photos` becomes nullable for
 *   the same reason.
 * - photo_expansion_packs.kind: photos | videos | audio (`photos` keeps
 *   holding the units the pack adds).
 * - media_entitlements: extra_audio, plus who/why for capacity granted by
 *   hand from admin (source `manual_grant`) and for its revocation.
 *
 * Additive; down() drops exactly what up() adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_quota_rules', function (Blueprint $table) {
            $table->unsignedSmallInteger('included_photos')->nullable()->change();
            $table->unsignedSmallInteger('included_videos')->nullable()->after('included_photos');
            $table->unsignedSmallInteger('included_audio')->nullable()->after('included_videos');
            $table->unsignedInteger('max_video_seconds')->nullable()->after('included_audio');
            $table->unsignedInteger('max_audio_seconds')->nullable()->after('max_video_seconds');
            $table->unsignedSmallInteger('cap_photos')->nullable()->after('max_audio_seconds');
            $table->unsignedSmallInteger('cap_videos')->nullable()->after('cap_photos');
            $table->unsignedSmallInteger('cap_audio')->nullable()->after('cap_videos');
        });

        Schema::table('photo_expansion_packs', function (Blueprint $table) {
            $table->string('kind', 16)->default('photos')->after('uuid');
            $table->index(['kind', 'active', 'archived_at'], 'photo_expansion_packs_kind_index');
        });

        Schema::table('media_entitlements', function (Blueprint $table) {
            $table->unsignedInteger('extra_audio')->default(0)->after('extra_videos');
            $table->foreignId('granted_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->string('grant_reason', 255)->nullable()->after('granted_by');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 255)->nullable()->after('revoked_by');
            $table->index(['user_id', 'source', 'revoked_at'], 'media_entitlements_user_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('media_entitlements', function (Blueprint $table) {
            $table->dropIndex('media_entitlements_user_source_index');
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropConstrainedForeignId('granted_by');
            $table->dropColumn(['extra_audio', 'grant_reason', 'revocation_reason']);
        });

        Schema::table('photo_expansion_packs', function (Blueprint $table) {
            $table->dropIndex('photo_expansion_packs_kind_index');
            $table->dropColumn('kind');
        });

        Schema::table('photo_quota_rules', function (Blueprint $table) {
            $table->dropColumn([
                'included_videos', 'included_audio', 'max_video_seconds', 'max_audio_seconds',
                'cap_photos', 'cap_videos', 'cap_audio',
            ]);
        });

        // NOT NULL comes back only when no rule would break it — rolling
        // back never deletes a configured limit.
        if (! DB::table('photo_quota_rules')->whereNull('included_photos')->exists()) {
            Schema::table('photo_quota_rules', function (Blueprint $table) {
                $table->unsignedSmallInteger('included_photos')->nullable(false)->change();
            });
        }
    }
};
