<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An auditable record of each transfer made to a photographer (done
 * outside the system — bank transfer to their CLABE). Registering one
 * settles the photographer's pending PhotoSales and links them to it, so
 * the admin can see WHICH sales each payment covered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographer_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('photographer_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('MXN');
            $table->unsignedInteger('sales_count')->default(0);
            $table->string('reference', 120)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['photographer_profile_id', 'paid_at']);
        });

        Schema::table('photo_sales', function (Blueprint $table) {
            $table->foreignId('photographer_payout_id')->nullable()->after('paid_out_at')->constrained('photographer_payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('photo_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photographer_payout_id');
        });

        Schema::dropIfExists('photographer_payouts');
    }
};
