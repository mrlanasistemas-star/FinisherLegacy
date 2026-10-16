<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NFC as the physical-digital link (docs/NFC.md):
 *
 * - nfc_tags: one physical chip. `token` is the random public identifier
 *   written to the chip as https://<domain>/n/{token} — never PII, never
 *   sequential. `uid_hash` is an optional HMAC of the chip's hardware UID
 *   (never stored or shown in clear).
 * - nfc_assignments: what a tag opens (legacy_plate | product | profile |
 *   event) and who owns it. Explicit FKs per target type instead of a
 *   free morph, so every target is a real constrained row. History is kept:
 *   a transfer/unassign ends the row (`ended_at`) and opens a new one.
 * - nfc_taps: a short-lived tap log (hashed IP, short UA), pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfc_tags', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('uid_hash', 64)->nullable()->unique();
            $table->string('status', 20)->default('available')->index();
            $table->string('batch_code', 40)->nullable()->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_tapped_at')->nullable();
            $table->unsignedInteger('tap_count')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('nfc_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nfc_tag_id')->constrained('nfc_tags')->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->foreignId('plate_id')->nullable()->constrained('plates')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('athlete_owned_product_id')->nullable()->constrained('athlete_owned_products')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('athlete_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 20)->nullable();
            // Pending transfer (domain only — no marketplace, docs/NFC.md §Transfer).
            $table->foreignId('transfer_to_athlete_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->string('transfer_token_hash', 64)->nullable();
            $table->timestamp('transfer_expires_at')->nullable();
            $table->timestamps();

            $table->index(['nfc_tag_id', 'ended_at']);
            $table->index(['athlete_id', 'ended_at']);
            $table->index(['plate_id', 'ended_at']);
            $table->index(['athlete_owned_product_id', 'ended_at']);
        });

        Schema::create('nfc_taps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nfc_tag_id')->constrained('nfc_tags')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 120)->nullable();
            $table->string('channel', 10)->default('web');
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['nfc_tag_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfc_taps');
        Schema::dropIfExists('nfc_assignments');
        Schema::dropIfExists('nfc_tags');
    }
};
