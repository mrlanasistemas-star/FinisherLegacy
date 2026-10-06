<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable company information for the public "Nosotros" / "Contacto"
 * pages (description, location, email, phone, social links) — a plain
 * key/value store so admins change copy without touching Vue. Ships
 * EMPTY: nothing here is invented; pages only render what exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
