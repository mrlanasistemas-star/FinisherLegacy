<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distribution: suppliers (who manufactures), allies (who resells — a
 * company, not a user; ally_user links its staff accounts), typed inventory
 * locations (warehouse, ally, event, stand, transit) and manufacturing lots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('country', 80)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->unsignedInteger('moq')->nullable();
            $table->string('products_supplied')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('allies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 24)->default('store');
            $table->string('status', 16)->default('prospect');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('address')->nullable();
            // Commercial terms are data, never constants in code.
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->text('commercial_terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('ally_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ally_id')->constrained('allies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24)->default('seller');
            $table->timestamps();

            $table->unique(['ally_id', 'user_id']);
        });

        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->string('type', 16)->default('warehouse')->after('slug');
            $table->foreignId('ally_id')->nullable()->after('type')->constrained('allies')->nullOnDelete();
            $table->foreignId('event_edition_id')->nullable()->after('ally_id')->constrained('event_editions')->nullOnDelete();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
        });

        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 40)->unique();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedBigInteger('unit_cost_minor')->nullable();
            $table->char('currency', 3)->default('MXN');
            $table->string('status', 16)->default('ordered');
            $table->date('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('reference')->nullable();
            $table->text('issues')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');

        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ally_id');
            $table->dropConstrainedForeignId('event_edition_id');
            $table->dropColumn(['type', 'address', 'notes']);
        });

        Schema::dropIfExists('ally_user');
        Schema::dropIfExists('allies');
        Schema::dropIfExists('suppliers');
    }
};
