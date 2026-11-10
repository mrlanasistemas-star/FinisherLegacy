<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App → OpenPay web checkout (docs/renovacion/pagos.md).
 *
 *  - `checkout_handoff_tokens`: the short-lived, single-use tokens the
 *    mobile app gets from `POST /api/v1/orders/{uuid}/checkout-session`.
 *    Only the SHA-256 of the token is stored; the raw value lives only in
 *    the URL handed to the in-app browser. One token = one Order.
 *  - `payment_settings.openpay_webhook_*`: optional HTTP Basic credentials
 *    for the OpenPay webhook (the only authentication OpenPay supports for
 *    webhooks). The password is written through the `encrypted` cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_handoff_tokens', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('created_ip', 45)->nullable();
            $table->string('used_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'used_at']);
        });

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->string('openpay_webhook_user', 100)->nullable()->after('openpay_verified_at');
            $table->text('openpay_webhook_password')->nullable()->after('openpay_webhook_user');
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn(['openpay_webhook_user', 'openpay_webhook_password']);
        });

        Schema::dropIfExists('checkout_handoff_tokens');
    }
};
