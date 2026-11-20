<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Familia y contactos autorizados" (docs/renovacion/familia.md). One row
 * per invitation from an athlete (user) to a person they trust.
 *
 *  - Invite by email of an existing account → `contact_user_id` is known
 *    from the start, the row waits as `pending` until that person accepts.
 *  - Invite by link → `contact_user_id` stays null and only the SHA-256 of
 *    the one-time token is stored; whoever opens it (logged in) and accepts
 *    becomes the contact, and the token hash is cleared.
 *
 * A link alone never exposes location: it only makes the person eligible
 * to receive an explicit tracking share (tracking_shares.audience=family).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_family_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('athlete_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('contact_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('invite_token_hash', 64)->nullable()->unique();
            $table->timestamp('invite_expires_at')->nullable();
            $table->string('label', 60)->nullable();
            // pending | accepted | declined | revoked (App\Enums\FamilyLinkStatus)
            $table->string('status', 16)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['athlete_user_id', 'status']);
            $table->index(['contact_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_family_links');
    }
};
