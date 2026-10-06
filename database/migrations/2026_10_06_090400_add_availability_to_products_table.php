<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial availability, orthogonal to `status` (draft/active/archived
 * = is it published at all): an ACTIVE product can still be "coming soon"
 * or a "concept" — visible in the catalog, never purchasable. Existing
 * rows default to available, so nothing currently on sale changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('availability', 20)->default('available')->after('status');
            $table->string('tagline', 160)->nullable()->after('description');
            $table->unsignedInteger('sort_order')->default(0)->after('availability');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['availability', 'tagline', 'sort_order']);
        });
    }
};
