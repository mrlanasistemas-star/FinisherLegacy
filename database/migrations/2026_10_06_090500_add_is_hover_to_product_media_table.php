<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets admins pick which gallery image a product card swaps to on hover
 * (falls back to the second image when none is marked).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->boolean('is_hover')->default(false)->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropColumn('is_hover');
        });
    }
};
