<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive follow-up to 2026_09_21_090000 — push tokens are sensitive,
 * device-identifying bearer values, so they are now stored encrypted at
 * rest (App\Models\PushDevice casts `token` as `encrypted`). Lookups and
 * the per-user uniqueness guarantee move to `token_hash` (a plain SHA-256
 * digest of the plaintext) because two encryptions of the same plaintext
 * never produce the same ciphertext, so the old unique(user_id, token)
 * constraint can no longer do its job once `token` holds ciphertext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_devices', function (Blueprint $table) {
            $table->char('token_hash', 64)->nullable()->after('token');
        });

        foreach (DB::table('push_devices')->select('id', 'token')->get() as $device) {
            DB::table('push_devices')->where('id', $device->id)->update([
                'token_hash' => hash('sha256', $device->token),
                'token' => Crypt::encryptString($device->token),
            ]);
        }

        Schema::table('push_devices', function (Blueprint $table) {
            $table->dropUnique('push_devices_user_token_unique');
        });

        Schema::table('push_devices', function (Blueprint $table) {
            $table->text('token')->change();
            $table->char('token_hash', 64)->nullable(false)->change();
        });

        Schema::table('push_devices', function (Blueprint $table) {
            $table->unique(['user_id', 'token_hash'], 'push_devices_user_token_hash_unique');
        });
    }

    public function down(): void
    {
        foreach (DB::table('push_devices')->select('id', 'token')->get() as $device) {
            DB::table('push_devices')->where('id', $device->id)->update([
                'token' => Crypt::decryptString($device->token),
            ]);
        }

        Schema::table('push_devices', function (Blueprint $table) {
            $table->dropUnique('push_devices_user_token_hash_unique');
        });

        Schema::table('push_devices', function (Blueprint $table) {
            $table->string('token')->change();
            $table->dropColumn('token_hash');
        });

        Schema::table('push_devices', function (Blueprint $table) {
            $table->unique(['user_id', 'token'], 'push_devices_user_token_unique');
        });
    }
};
