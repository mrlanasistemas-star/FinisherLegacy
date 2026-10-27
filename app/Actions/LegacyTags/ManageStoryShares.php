<?php

namespace App\Actions\LegacyTags;

use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Models\LegacyStory;
use App\Models\LegacyStoryShare;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Recipient links ("Para mamá", "Mi entrenador"): a 40-char random token
 * (≈238 bits) that opens the story without an account or the app, even
 * when the story is private. Revoking is instant and permanent.
 */
class ManageStoryShares
{
    public function __construct(private readonly RecordTagScan $scans) {}

    public function create(LegacyStory $story, User $user, string $label): LegacyStoryShare
    {
        if ($story->shares()->whereNull('revoked_at')->count() >= (int) config('legacy.stories.max_shares', 20)) {
            throw ValidationException::withMessages(['label' => 'Ya tienes muchos enlaces activos. Revoca alguno para crear otro.']);
        }

        do {
            $token = Str::random(40);
        } while (LegacyStoryShare::query()->where('token', $token)->exists());

        $share = $story->shares()->create(['token' => $token, 'label' => trim($label)]);

        activity()->causedBy($user)->performedOn($story)
            ->withProperties(['share_id' => $share->id])
            ->log('legacy_story.share_created');

        return $share;
    }

    public function revoke(LegacyStoryShare $share, User $user): void
    {
        if ($share->revoked_at !== null) {
            return;
        }

        $share->forceFill(['revoked_at' => now()])->save();

        activity()->causedBy($user)->performedOn($share->story)
            ->withProperties(['share_id' => $share->id])
            ->log('legacy_story.share_revoked');
    }

    /** A recipient opened the link: counters + privacy-first analytics. */
    public function opened(LegacyStoryShare $share, Request $request): void
    {
        $share->newQuery()->whereKey($share->id)->update([
            'open_count' => $share->open_count + 1,
            'last_opened_at' => now(),
        ]);

        $tag = $share->story?->tag;

        if ($tag !== null) {
            $this->scans->handle($tag, TagScanEvent::ShareOpened, TagAccessSource::Link, $request->user(), $request, $share->story->isOwnedBy($request->user()));
        }
    }
}
