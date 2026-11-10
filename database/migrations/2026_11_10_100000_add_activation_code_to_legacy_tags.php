<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Proof of purchase for self-activated NFC tags (docs/renovacion/manual-activacion-nfc.md):
| the secret activation code printed on the card inside the packaging. Only
| its HMAC (keyed by APP_KEY) is stored — never the code itself — plus the
| last 4 characters so support can confirm a card by phone. Additive.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_tags', function (Blueprint $table) {
            $table->string('activation_code_hash', 64)->nullable()->after('nfc_uid_hash');
            $table->string('activation_code_last4', 4)->nullable()->after('activation_code_hash');

            $table->unique('activation_code_hash');
            $table->index('nfc_uid_hash');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_tags', function (Blueprint $table) {
            $table->dropUnique(['activation_code_hash']);
            $table->dropIndex(['nfc_uid_hash']);
            $table->dropColumn(['activation_code_hash', 'activation_code_last4']);
        });
    }
};
