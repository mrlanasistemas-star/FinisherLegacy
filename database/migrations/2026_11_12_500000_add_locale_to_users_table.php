<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.locale` — the language a person chose (docs/renovacion/idiomas.md).
 * Null = never chosen: the cookie / browser / Spanish default decides.
 * Emails, notifications and push are sent in this language
 * (User::preferredLocale). Additive and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'locale')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 12)->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
