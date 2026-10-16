<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Commerce consolidation (spec §14-§15/§23):
 * - `minimum_stock` per variant, with a product-level fallback (null on
 *   both → config('finisher.commerce.default_minimum_stock')).
 * - `reason` on inventory movements so every manual adjustment is audited
 *   with a structured reason, not just free-text notes.
 * - `uuid` on product media (public identity for the store/API shape) —
 *   `type` stays a string column, now also `model_3d`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('minimum_stock')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('minimum_stock')->nullable();
            $table->boolean('is_featured')->default(false);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('reason', 40)->nullable();
            $table->index(['type', 'created_at'], 'inv_mov_type_created_idx');
        });

        Schema::table('product_media', function (Blueprint $table) {
            $table->uuid('uuid')->nullable();
        });

        DB::table('product_media')->whereNull('uuid')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('product_media')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        Schema::table('product_media', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('inv_mov_type_created_idx');
            $table->dropColumn('reason');
        });

        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['minimum_stock', 'is_featured']));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('minimum_stock'));
    }
};
