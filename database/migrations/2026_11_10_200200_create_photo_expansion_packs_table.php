<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sellable photo-capacity packs for Legacy stories. Scope: a product,
 * a product type, or every story (both null). Prices live in
 * product_price_schedules (photo_expansion_pack_id) so price history uses
 * the same schedule mechanism the store already has. Packs are archived,
 * never deleted, once they have a price or a sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_expansion_packs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('photos');
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('product_type', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_expansion_packs');
    }
};
