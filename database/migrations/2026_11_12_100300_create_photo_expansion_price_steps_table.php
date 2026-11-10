<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price steps ("banderas") of an expansion price: the amount depends on
 * how much the story already holds of that kind. The schedule row's own
 * amount is the price from 0; each step replaces it from `from_units` on
 * ("hasta 20: X; de 21 a 50: Y; mas de 50: Z" = amount X + steps 21 → Y
 * and 51 → Z). Steps are written together with their schedule row and,
 * like it, never edited afterwards — a new tariff is a new schedule with
 * its own steps. Optional store product ids let the app sell each step as
 * its own in-app product (the stores own that price).
 * See App\Services\Media\StoryMediaQuota::resolvePrice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_expansion_price_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_price_schedule_id')->constrained('product_price_schedules')->restrictOnDelete();
            $table->unsignedInteger('from_units');
            $table->unsignedBigInteger('amount_minor');
            $table->string('apple_product_id', 100)->nullable()->unique();
            $table->string('google_product_id', 100)->nullable()->unique();
            $table->timestamps();

            $table->unique(['product_price_schedule_id', 'from_units'], 'photo_expansion_price_steps_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_expansion_price_steps');
    }
};
