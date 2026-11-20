<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping charged on an Order (home delivery), frozen at checkout like
 * every other amount. Additive: existing orders keep 0, so
 * total = subtotal − discount + tax + shipping still holds for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_minor')->default(0)->after('tax_minor');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_minor');
        });
    }
};
