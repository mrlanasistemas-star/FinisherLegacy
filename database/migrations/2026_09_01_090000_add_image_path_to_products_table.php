<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Small, directly-required addition found while building the public store
 * (brief §59/§94: "consumir media del catálogo, no hardcodear rutas") —
 * Product had no image field at all, which a storefront card/detail page
 * can't function without. Frontend-branch backend fix per the brief's own
 * rule 74 (small, directly required).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
