<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Submissions from the public /contact form (general, events, suppliers,
 * brands, photographers, support). Stored and reviewed from admin — no
 * outbound email is sent from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('company', 150)->nullable();
            $table->string('email', 190);
            $table->string('type', 30);
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
