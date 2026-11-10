<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A new price of the same pack usually keeps the same in-app products for
 * its steps (the stores own those prices), so a store product id may
 * repeat across the successive schedules of ONE pack. The global unique
 * indexes become plain lookup indexes; "one product id → one pack" is
 * enforced by ExpansionPriceController instead. Reversible while no id is
 * repeated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_expansion_price_steps', function (Blueprint $table) {
            $table->dropUnique(['apple_product_id']);
            $table->dropUnique(['google_product_id']);
            $table->index('apple_product_id', 'photo_expansion_price_steps_apple_index');
            $table->index('google_product_id', 'photo_expansion_price_steps_google_index');
        });
    }

    public function down(): void
    {
        Schema::table('photo_expansion_price_steps', function (Blueprint $table) {
            $table->dropIndex('photo_expansion_price_steps_apple_index');
            $table->dropIndex('photo_expansion_price_steps_google_index');
            $table->unique('apple_product_id');
            $table->unique('google_product_id');
        });
    }
};
