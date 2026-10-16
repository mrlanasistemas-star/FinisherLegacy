<?php

namespace App\Actions\Nfc;

use App\Models\NfcTag;
use App\Models\NfcTap;
use App\Support\Nfc\NfcToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One row per tap, no PII: keyed hash of the IP, user agent cut to 120
 * chars, optional user id. Rows are pruned (NfcTap::prunable()).
 */
class RecordNfcTap
{
    public function handle(NfcTag $tag, Request $request, string $channel = 'web', ?int $userId = null): void
    {
        NfcTap::create([
            'nfc_tag_id' => $tag->id,
            'user_id' => $userId,
            'ip_hash' => NfcToken::hashIp($request->ip()),
            'user_agent' => Str::limit((string) $request->userAgent(), 117),
            'channel' => $channel,
            'created_at' => now(),
        ]);

        NfcTag::query()->whereKey($tag->id)->incrementEach(['tap_count' => 1], ['last_tapped_at' => now()]);
    }
}
