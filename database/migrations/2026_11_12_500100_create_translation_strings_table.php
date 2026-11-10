<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `translation_strings` — the catalog every lang/*.json and mobile catalog
 * is BUILT from (docs/renovacion/idiomas.md), so a correction made by a
 * person is never overwritten by the machine.
 *
 * One row per locale + group + key. For `ui` / `mobile` / `email` the key
 * is the Spanish text itself; `content` rows use an explicit key.
 * status: missing (not translated yet) · machine · reviewed (edited by a
 * person, never re-translated) · stale (source changed, to re-translate).
 * `needs_review` flags a reviewed row whose source changed afterwards.
 * `obsolete_at` marks strings no longer found in the code (kept, not deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_strings', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 12);
            $table->string('group', 16);
            $table->char('key_hash', 64);
            $table->text('key')->nullable();
            $table->text('source_text');
            $table->text('translated_text')->nullable();
            $table->string('status', 16)->default('missing');
            $table->char('source_hash', 64);
            $table->boolean('needs_review')->default(false);
            $table->json('origins')->nullable();
            $table->timestamp('obsolete_at')->nullable();
            $table->timestamp('translated_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['locale', 'group', 'key_hash'], 'translation_strings_unique');
            $table->index(['locale', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_strings');
    }
};
