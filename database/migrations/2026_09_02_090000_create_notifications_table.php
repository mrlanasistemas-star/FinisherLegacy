<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard `notifications` table shape (Notifiable/
 * DatabaseNotification, `id` uuid primary) — deliberately not this app's
 * usual bigint-id-plus-separate-uuid pattern, since this table is
 * framework-owned infrastructure the Notifiable trait and
 * DatabaseNotification model already know how to read/write; changing its
 * shape would mean re-implementing what Laravel already gives us for free.
 * The business layer never touches this table directly — see
 * App\Actions\Notifications\SendAthleteNotification and
 * App\Notifications\AthleteAlert (product UX consolidation brief §36-§38).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
