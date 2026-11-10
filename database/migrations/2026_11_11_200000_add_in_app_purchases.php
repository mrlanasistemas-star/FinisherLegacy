<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app purchases of photo expansions (docs/renovacion/compras-dentro-de-app.md).
 *
 * - photo_expansion_packs.apple_product_id / google_product_id: the
 *   consumable product id configured in App Store Connect / Play Console
 *   (the store owns the price; the web keeps its OpenPay price schedule).
 * - store_purchases: one row per verified store transaction. The unique
 *   (platform, transaction_key) is the idempotency key — a replayed
 *   transaction never creates a second Order nor a second grant.
 *
 * Additive only; down() drops exactly what up() adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_expansion_packs', function (Blueprint $table) {
            $table->string('apple_product_id', 100)->nullable()->unique()->after('product_type');
            $table->string('google_product_id', 100)->nullable()->unique()->after('apple_product_id');
        });

        Schema::create('store_purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // ios | android
            $table->string('platform', 16);
            // iOS: transactionId. Android: sha256(purchaseToken).
            $table->string('transaction_key', 128);
            // iOS: transactionId. Android: Play orderId (GPA.…), when present.
            $table->string('transaction_id', 128)->nullable();
            $table->string('original_transaction_id', 128)->nullable();
            $table->text('purchase_token')->nullable();
            $table->string('product_id', 100);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('legacy_story_id')->nullable()->constrained('legacy_stories')->nullOnDelete();
            $table->foreignId('photo_expansion_pack_id')->nullable()->constrained('photo_expansion_packs')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            // granted | revoked
            $table->string('status', 16)->default('granted');
            // Production | Sandbox (Apple) — Play: production | test.
            $table->string('environment', 16)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            // store | client_reported | null
            $table->string('amount_source', 24)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'transaction_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['platform', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_purchases');

        Schema::table('photo_expansion_packs', function (Blueprint $table) {
            $table->dropUnique(['apple_product_id']);
            $table->dropUnique(['google_product_id']);
            $table->dropColumn(['apple_product_id', 'google_product_id']);
        });
    }
};
