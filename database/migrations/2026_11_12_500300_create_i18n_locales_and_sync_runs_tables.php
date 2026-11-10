<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `i18n_locales` — whether a configured language is offered to the public
 * (no row = enabled). `i18n_sync_runs` — history of "Sincronizar y
 * traducir" runs with their counters and the token usage of the translator,
 * so the admin page can show what each run cost (docs/renovacion/idiomas.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('i18n_locales', function (Blueprint $table) {
            $table->string('locale', 12)->primary();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('i18n_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->default('queued');
            $table->string('trigger', 16)->default('cli');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('locales')->nullable();
            $table->json('stats')->nullable();
            $table->string('driver', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 4)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('i18n_sync_runs');
        Schema::dropIfExists('i18n_locales');
    }
};
