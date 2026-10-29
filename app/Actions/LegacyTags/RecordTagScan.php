<?php

namespace App\Actions\LegacyTags;

use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Models\LegacyTag;
use App\Models\LegacyTagScan;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * tag_opened · activation_started · activation_completed · legacy_viewed ·
 * share_opened — privacy-first: no IP or user agent is stored, only a
 * keyed hash that rotates daily (unique visitors per day, nothing more).
 * Opening a tag also bumps its open counters (first/last opened, count).
 */
class RecordTagScan
{
    public function handle(LegacyTag $tag, TagScanEvent $event, TagAccessSource $source, ?User $user = null, ?Request $request = null, bool $isOwner = false): void
    {
        $visitor = $request !== null
            ? substr(hash_hmac('sha256', ($request->ip() ?? '').'|'.now()->toDateString(), (string) config('app.key')), 0, 32)
            : null;

        LegacyTagScan::create([
            'legacy_tag_id' => $tag->id,
            'event' => $event,
            'source' => $source,
            'user_id' => $user?->id,
            'visitor_hash' => $visitor,
            'is_owner' => $isOwner,
            'created_at' => now(),
        ]);

        if ($event === TagScanEvent::TagOpened) {
            $tag->newQuery()->whereKey($tag->id)->update([
                'open_count' => $tag->open_count + 1,
                'last_opened_at' => now(),
                'first_opened_at' => $tag->first_opened_at ?? now(),
            ]);
        }
    }

    /**
     * Anything that reaches /t/{code} without `?src` is the NFC chip. The tag
     * no longer has a printed QR, but old `?src=qr` links are still accepted.
     */
    public static function sourceFrom(Request $request): TagAccessSource
    {
        return TagAccessSource::tryFrom((string) $request->query('src', 'nfc')) ?? TagAccessSource::Nfc;
    }
}
