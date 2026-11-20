<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One physical chip = one Finisher Legacy Tag, enforced by the database.
 *
 * StartTagActivation::assertUidFits() already refuses a chip UID that is
 * bound to another tag, but two activations of different tags with the same
 * chip running at the same moment could both pass that check before either
 * writes. A unique index on `legacy_tags.nfc_uid_hash` closes that race
 * (NULLs stay allowed — unbound tags are the normal state).
 *
 * Additive and defensive: if production already holds duplicated hashes the
 * index is skipped (and logged) instead of failing the deploy, so support
 * can untangle those tags first and re-run this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('legacy_tags')
            ->whereNotNull('nfc_uid_hash')
            ->select('nfc_uid_hash')
            ->groupBy('nfc_uid_hash')
            ->havingRaw('count(*) > 1')
            ->count();

        if ($duplicates > 0) {
            Log::warning('make_legacy_tag_nfc_uid_hash_unique: skipped, duplicated chip UIDs exist', ['groups' => $duplicates]);

            return;
        }

        Schema::table('legacy_tags', function (Blueprint $table) {
            $table->unique('nfc_uid_hash', 'legacy_tags_nfc_uid_hash_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('legacy_tags', 'legacy_tags_nfc_uid_hash_unique')) {
            return;
        }

        Schema::table('legacy_tags', function (Blueprint $table) {
            $table->dropUnique('legacy_tags_nfc_uid_hash_unique');
        });
    }
};
