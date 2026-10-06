<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo sales record WHICH gateway processed the payment, and whether the
 * processing fee is the configured estimate or a real, reconciled fee
 * reported by that gateway. Existing rows keep their frozen numbers and
 * are flagged as estimates (they always were).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_sales', function (Blueprint $table) {
            $table->string('payment_provider', 20)->nullable()->after('currency');
            $table->boolean('processor_fee_estimated')->default(true)->after('processor_fee_minor');
            $table->unsignedInteger('processor_fee_actual_minor')->nullable()->after('processor_fee_estimated');
            $table->timestamp('processor_fee_reconciled_at')->nullable()->after('processor_fee_actual_minor');
        });
    }

    public function down(): void
    {
        Schema::table('photo_sales', function (Blueprint $table) {
            $table->dropColumn(['payment_provider', 'processor_fee_estimated', 'processor_fee_actual_minor', 'processor_fee_reconciled_at']);
        });
    }
};
