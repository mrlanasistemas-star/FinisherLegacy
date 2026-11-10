<?php

/**
 * Idempotency ledger for customer transactional emails (pago confirmado,
 * reembolso, enviado, entregado). One row per (order, kind, reference) is
 * claimed BEFORE queueing the mail, so webhook retries, status syncs and
 * double clicks can never send the same email twice. See
 * docs/renovacion/correos.md. Additive and reversible.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('reference', 120)->default('');
            $table->string('recipient', 190);
            $table->timestamps();

            $table->unique(['order_id', 'kind', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_email_deliveries');
    }
};
