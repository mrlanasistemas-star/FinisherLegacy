<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per provider webhook event ever accepted — the unique constraint
 * IS the idempotency guarantee for ProcessPaymentWebhook (brief §69/§77).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_receipts');
    }
};
