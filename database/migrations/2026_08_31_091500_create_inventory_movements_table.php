<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable ledger — `quantity` is a signed delta so summing it always
 * reconstructs quantity_on_hand (brief §52-§54). No `updated_at`: a
 * movement, once recorded, is never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('type');
            $table->integer('quantity');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Explicit short names: MySQL's default naming convention
            // (`{table}_{col1}_{col2}_index`) produces
            // `inventory_movements_product_variant_id_inventory_location_id_index`
            // (66 chars) for the first one, over MySQL's 64-char identifier
            // limit — SQLite has no such limit, which is why this only
            // surfaced against real MySQL.
            $table->index(['product_variant_id', 'inventory_location_id'], 'inv_mov_variant_location_idx');
            $table->index(['reference_type', 'reference_id'], 'inv_mov_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
