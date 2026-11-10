<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reuses product_price_schedules for photo expansion prices (one row per
 * priced window, never edited once it started — a new price is a new
 * row). Adds the tax snapshot the expansion price needs; every new
 * column is nullable/defaulted so existing schedules keep behaving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_schedules', function (Blueprint $table) {
            $table->foreignId('photo_expansion_pack_id')->nullable()->after('product_variant_id')
                ->constrained('photo_expansion_packs')->restrictOnDelete();
            $table->decimal('tax_rate', 6, 4)->nullable()->after('currency');
            $table->boolean('tax_included')->default(true)->after('tax_rate');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_price_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['tax_rate', 'tax_included']);
            $table->dropConstrainedForeignId('photo_expansion_pack_id');
        });
    }
};
