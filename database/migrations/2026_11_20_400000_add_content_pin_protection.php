<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional content PIN (NIP) — App\Services\Privacy\ContentPin.
 *
 * users: the PIN is only ever stored as a password hash; `content_pin_set_at`
 * doubles as the "epoch" every unlock grant is bound to (changing, resetting
 * or disabling the PIN invalidates all outstanding grants at once). The
 * failed-attempt counter and lockout live on the row so the lockout is
 * enforced server-side across devices and survives cache flushes.
 *
 * pin_protected: one per-item flag on the three kinds of private content the
 * owner can lock (memories, moments, event photos/videos). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('content_pin_hash')->nullable();
            $table->timestamp('content_pin_set_at')->nullable();
            $table->unsignedSmallInteger('content_pin_failed_attempts')->default(0);
            $table->timestamp('content_pin_locked_until')->nullable();
        });

        foreach (['legacy_stories', 'legacy_moments', 'athlete_event_media'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('pin_protected')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['legacy_stories', 'legacy_moments', 'athlete_event_media'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['pin_protected']);
                $table->dropColumn('pin_protected');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['content_pin_hash', 'content_pin_set_at', 'content_pin_failed_attempts', 'content_pin_locked_until']);
        });
    }
};
