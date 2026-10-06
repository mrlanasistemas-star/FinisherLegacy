<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photographer marketplace: photographers upload event photos for free and
 * set their price; athletes buy them through the regular Order/Payment
 * pipeline; each paid photo becomes a PhotoSale with the exact split
 * (Finisher commission, card processor fee, photographer net).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographer_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 120);
            $table->string('slug', 140)->unique();
            $table->text('bio')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('default_price_minor')->nullable();
            $table->string('payout_holder', 150)->nullable();
            $table->string('payout_bank', 120)->nullable();
            $table->text('payout_clabe')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('photographer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_edition_id')->constrained('event_editions')->cascadeOnDelete();
            $table->string('original_path');
            $table->string('preview_path');
            $table->string('thumb_path');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('price_minor');
            $table->char('currency', 3)->default('MXN');
            $table->string('status', 20)->default('review');
            $table->string('rejection_reason')->nullable();
            $table->json('bib_numbers')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['event_edition_id', 'status']);
            $table->index(['photographer_profile_id', 'status']);
        });

        Schema::create('event_photo_bibs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_edition_id')->constrained('event_editions')->cascadeOnDelete();
            $table->string('bib_number', 20);

            $table->unique(['event_photo_id', 'bib_number']);
            $table->index(['event_edition_id', 'bib_number']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('event_photo_id')->nullable()->after('product_variant_id')->constrained('event_photos')->nullOnDelete();
        });

        Schema::create('photo_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_photo_id')->constrained()->restrictOnDelete();
            $table->foreignId('photographer_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('gross_minor');
            $table->unsignedInteger('platform_fee_minor');
            $table->unsignedInteger('processor_fee_minor');
            $table->unsignedInteger('photographer_net_minor');
            $table->decimal('commission_percent', 5, 2);
            $table->char('currency', 3)->default('MXN');
            $table->string('payout_status', 20)->default('pending');
            $table->timestamp('paid_out_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['photographer_profile_id', 'payout_status']);
            $table->index('buyer_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_sales');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_photo_id');
        });

        Schema::dropIfExists('event_photo_bibs');
        Schema::dropIfExists('event_photos');
        Schema::dropIfExists('photographer_profiles');
    }
};
