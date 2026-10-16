<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RegisterPushDevice now deactivates the same physical token under other
 * accounts — a lookup by `token_hash` alone, which the existing
 * (user_id, token_hash) unique index can't serve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_devices', function (Blueprint $table) {
            $table->index('token_hash', 'push_devices_token_hash_index');
        });
    }

    public function down(): void
    {
        Schema::table('push_devices', function (Blueprint $table) {
            $table->dropIndex('push_devices_token_hash_index');
        });
    }
};
