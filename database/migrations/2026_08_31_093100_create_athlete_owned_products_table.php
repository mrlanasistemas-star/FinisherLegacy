<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A concrete physical unit of a QR-capable product (Trisuit, FAST T1 Socks,
 * Chill Band, Racepack) owned by an Athlete (brief §84-§93). `asset_code`
 * is the unguessable, no-PII identifier a public `/gear/{code}` lookup
 * resolves — see App\Services\AssetCodeService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_owned_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('serial_code')->nullable();
            $table->string('asset_code')->nullable()->unique();
            $table->string('status')->default('assigned');
            $table->timestamp('acquired_at');
            $table->timestamp('activated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['athlete_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_owned_products');
    }
};
