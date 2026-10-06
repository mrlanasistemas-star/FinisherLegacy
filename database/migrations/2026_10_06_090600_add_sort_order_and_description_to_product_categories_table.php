<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store navigation order + a one-line description per category, both
 * editable from admin (the storefront reads categories from here only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('slug');
            $table->unsignedInteger('sort_order')->default(0)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn(['description', 'sort_order']);
        });
    }
};
