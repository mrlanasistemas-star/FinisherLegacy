<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user, per-category notification switches
 * (docs/renovacion/notificaciones.md §Preferencias). A missing row means
 * "use the category default" (App\Support\Notifications\NotificationPreferences),
 * so nothing has to be backfilled.
 *
 *  - scope `customer`: App\Enums\NotificationCategory (in_app + push).
 *  - scope `admin`:    App\Enums\AdminAlertCategory (email + push; in-app always on).
 *
 * Purely additive; down() drops only this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16);
            $table->string('category', 32);
            $table->boolean('in_app')->default(true);
            $table->boolean('push')->default(true);
            $table->boolean('email')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'scope', 'category']);
            $table->index(['scope', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
