<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission snapshot + refund reversal on photo sales, and clawbacks on
 * payouts. The money columns already exist and keep their names:
 *   commission rate   → photo_sales.commission_percent
 *   commission amount → photo_sales.platform_fee_minor
 *   gateway fee       → photo_sales.processor_fee_minor
 *   photographer net  → photo_sales.photographer_net_minor
 * (PhotoSale exposes commission_rate / commission_minor / gateway_fee_minor
 * as aliases). New here: which terms produced the snapshot, who absorbed
 * the gateway fee, and the reversal of the net when the order is refunded.
 *
 * A reversal of a sale that was already paid out is a clawback: it is
 * deducted from the next payout (`reversal_payout_id` marks it settled).
 * Existing rows: the photographer always absorbed the fee until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_sales', function (Blueprint $table) {
            $table->string('commission_source', 20)->nullable()->after('commission_percent');
            $table->foreignId('commission_setting_id')->nullable()->after('commission_source')
                ->constrained('photographer_commission_settings')->nullOnDelete();
            $table->string('gateway_fee_absorbed_by', 20)->default('photographer')->after('processor_fee_minor');
            $table->timestamp('refunded_at')->nullable()->after('paid_out_at');
            $table->unsignedInteger('reversed_net_minor')->nullable()->after('refunded_at');
            $table->unsignedInteger('reversed_commission_minor')->nullable()->after('reversed_net_minor');
            $table->string('reversal_reason', 255)->nullable()->after('reversed_commission_minor');
            $table->foreignId('reversal_payout_id')->nullable()->after('reversal_reason')
                ->constrained('photographer_payouts')->nullOnDelete();
        });

        Schema::table('photographer_payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('reversals_minor')->default(0)->after('sales_count');
            $table->unsignedInteger('reversals_count')->default(0)->after('reversals_minor');
        });
    }

    public function down(): void
    {
        Schema::table('photographer_payouts', function (Blueprint $table) {
            $table->dropColumn(['reversals_minor', 'reversals_count']);
        });

        Schema::table('photo_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_payout_id');
            $table->dropConstrainedForeignId('commission_setting_id');
            $table->dropColumn([
                'commission_source', 'gateway_fee_absorbed_by', 'refunded_at',
                'reversed_net_minor', 'reversed_commission_minor', 'reversal_reason',
            ]);
        });
    }
};
