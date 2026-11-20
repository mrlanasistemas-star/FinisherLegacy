<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tarjeta de ánimo NFC + configurable cheer pricing
 * (docs/renovacion/tarjeta-animo.md). Purely additive:
 *
 * - `legacy_tags.purpose` (story | cheer) + `cheer_link_id`: a cheer card is
 *   a LegacyTag whose `/t/{code}` opens the bound "Envíame ánimo" link, so
 *   the existing chip writer, activation intents and UID anti-duplicate
 *   rules are reused as-is.
 * - `legacy_tag_activation_intents.cheer_link_id`: the link chosen in the
 *   app while activating a card.
 * - `cheer_messages`: where it came from (`source` link | nfc_card +
 *   `legacy_tag_id`), anonymity, and the paid-message state (billing,
 *   order, amount, payment window, paid_at) — a paid message stays
 *   `pending_payment` (invisible to the athlete) until MarkOrderPaid.
 * - `cheer_pricing_settings`: one row per channel (link / nfc_card); a
 *   missing row means the config/training.php defaults (current behavior).
 * - `cheer_sender_credits`: messages left from a pack a sender bought.
 * - The internal, never-listed product every paid-message OrderItem points
 *   at (same pattern as `ampliacion-fotos-recuerdo`). No price is created.
 * The admin pricing page reuses `quotas.manage` (Límites y tarifas).
 */
return new class extends Migration
{
    private const string PRODUCT_SLUG = 'mensaje-de-animo';

    public function up(): void
    {
        Schema::table('legacy_tags', function (Blueprint $table) {
            $table->string('purpose', 16)->default('story')->after('status');
            $table->foreignId('cheer_link_id')->nullable()->after('purpose')->constrained('cheer_links')->nullOnDelete();
            $table->timestamp('cheer_bound_at')->nullable()->after('cheer_link_id');
            $table->index(['purpose', 'owner_athlete_id']);
        });

        Schema::table('legacy_tag_activation_intents', function (Blueprint $table) {
            $table->foreignId('cheer_link_id')->nullable()->constrained('cheer_links')->nullOnDelete();
        });

        Schema::table('cheer_messages', function (Blueprint $table) {
            $table->string('source', 16)->default('link')->after('cheer_link_id');
            $table->foreignId('legacy_tag_id')->nullable()->after('source')->constrained('legacy_tags')->nullOnDelete();
            $table->boolean('anonymous')->default(false)->after('sender_name');
            $table->string('billing', 16)->default('free')->after('status');
            $table->foreignId('order_id')->nullable()->after('billing')->constrained('orders')->nullOnDelete();
            $table->unsignedInteger('amount_minor')->nullable()->after('order_id');
            $table->char('currency', 3)->nullable()->after('amount_minor');
            $table->timestamp('payment_expires_at')->nullable()->after('currency');
            $table->timestamp('paid_at')->nullable()->after('payment_expires_at');
            $table->string('sender_key_hash', 64)->nullable()->after('sender_ip_hash');

            $table->index(['cheer_link_id', 'source', 'billing']);
            $table->index(['legacy_tag_id', 'billing']);
            $table->index(['status', 'payment_expires_at']);
        });

        Schema::create('cheer_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16)->unique();
            $table->unsignedInteger('free_messages');
            $table->string('after_free', 16)->default('blocked');
            $table->unsignedInteger('hard_limit')->nullable();
            $table->unsignedInteger('price_minor')->nullable();
            $table->char('currency', 3)->default('MXN');
            $table->json('price_steps')->nullable();
            $table->json('packs')->nullable();
            $table->boolean('allow_anonymous')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cheer_sender_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheer_link_id')->constrained('cheer_links')->cascadeOnDelete();
            $table->foreignId('legacy_tag_id')->nullable()->constrained('legacy_tags')->nullOnDelete();
            $table->string('channel', 16);
            $table->string('sender_key_hash', 64);
            $table->unsignedInteger('credits_total');
            $table->unsignedInteger('credits_remaining');
            $table->foreignId('order_item_id')->nullable()->unique()->constrained('order_items')->nullOnDelete();
            $table->timestamps();

            $table->index(['cheer_link_id', 'channel', 'sender_key_hash']);
        });

        if (! DB::table('products')->where('slug', self::PRODUCT_SLUG)->exists()) {
            DB::table('products')->insert([
                'uuid' => (string) Str::uuid(),
                'name' => 'Mensaje de ánimo',
                'slug' => self::PRODUCT_SLUG,
                'description' => 'Producto interno: mensajes de ánimo adicionales pagados por quien los envía. Sus precios se configuran en Límites y tarifas → Mensajes de ánimo.',
                'type' => 'digital_photo',
                'brand' => 'Finisher Legacy',
                'status' => 'archived',
                'availability' => 'available',
                'sort_order' => 9999,
                'taxable' => false,
                'requires_shipping' => false,
                'qr_capable' => false,
                'tracks_inventory' => false,
                'active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $productId = DB::table('products')->where('slug', self::PRODUCT_SLUG)->value('id');
        if ($productId !== null && ! DB::table('order_items')->where('product_id', $productId)->exists()) {
            DB::table('products')->where('id', $productId)->delete();
        }

        Schema::dropIfExists('cheer_sender_credits');
        Schema::dropIfExists('cheer_pricing_settings');

        Schema::table('cheer_messages', function (Blueprint $table) {
            $table->dropIndex(['cheer_link_id', 'source', 'billing']);
            $table->dropIndex(['legacy_tag_id', 'billing']);
            $table->dropIndex(['status', 'payment_expires_at']);
            $table->dropConstrainedForeignId('legacy_tag_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['source', 'anonymous', 'billing', 'amount_minor', 'currency', 'payment_expires_at', 'paid_at', 'sender_key_hash']);
        });

        Schema::table('legacy_tag_activation_intents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cheer_link_id');
        });

        Schema::table('legacy_tags', function (Blueprint $table) {
            $table->dropIndex(['purpose', 'owner_athlete_id']);
            $table->dropConstrainedForeignId('cheer_link_id');
            $table->dropColumn(['purpose', 'cheer_bound_at']);
        });
    }
};
