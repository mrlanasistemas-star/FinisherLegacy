<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin → Colecciones → Diseños: an extra tag design that a product still
 * prints cannot be deleted, so it is archived instead. `archived_at`
 * hides it from the store (Collection::designs()) while keeping the row
 * and every product reference intact. Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_designs', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('collection_designs', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
