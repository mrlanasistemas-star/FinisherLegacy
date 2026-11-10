<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable photographer commission terms, append-only (every change
 * is a new row, so the table IS the history). Two scopes:
 *
 *  - `global`: the default Finisher Legacy commission % plus the payment
 *    gateway (OpenPay) fee and who absorbs it (photographer | finisher).
 *    Gateway fee fields are nullable: empty means "not configured" and the
 *    system falls back to the env estimate (config finisher.photos).
 *  - `photographer`: a per-photographer override of the commission %;
 *    a row with `commission_percent = null` ends the override (back to
 *    the global rate) from its effective date.
 *
 * The terms in force at instant T are the latest non-cancelled row with
 * `effective_from <= T`. Only a row that has not taken effect yet can be
 * cancelled. Past PhotoSales never change: they keep their own snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographer_commission_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('scope', 20);
            $table->foreignId('photographer_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('gateway_fee_percent', 6, 3)->nullable();
            $table->unsignedInteger('gateway_fee_fixed_minor')->nullable();
            $table->string('gateway_fee_absorbed_by', 20)->nullable();
            $table->timestamp('effective_from');
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'photographer_profile_id', 'effective_from'], 'pcs_scope_profile_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_commission_settings');
    }
};
