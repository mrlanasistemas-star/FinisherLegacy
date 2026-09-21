<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 coupon domain (brief §30-§34) — `value` is always a whole
 * number: a percentage (1-100) for `percentage`, minor currency units for
 * `fixed_amount`. `currency` is nullable — null means "any currency this
 * store sells in" rather than forcing every coupon to pick one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->unsignedInteger('value');
            $table->char('currency', 3)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedBigInteger('minimum_order_minor')->nullable();
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active'], 'coupons_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
