<?php

namespace App\Actions\Training;

use App\Models\CheerLink;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create and revoke "Envíame ánimo" links (Entrenamientos). Same model as
 * ManageStoryShares: a random token (32 chars, ≈190 bits) that never
 * reveals the id, revocable instantly and permanently. Web
 * (TrainingController) and API (Api\V1\Training\TrainingLinkController)
 * both call this.
 */
class ManageCheerLinks
{
    /**
     * @throws ValidationException
     */
    public function create(User $user, string $title, ?int $expiresInDays = null): CheerLink
    {
        $active = CheerLink::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        if ($active >= (int) config('training.cheers.max_active_links', 20)) {
            throw ValidationException::withMessages(['title' => 'Ya tienes muchos enlaces activos. Desactiva alguno para crear otro.']);
        }

        do {
            $token = Str::random(32);
        } while (CheerLink::query()->where('token', $token)->exists());

        $cap = (int) config('training.cheers.max_messages_per_link', 200);

        $link = CheerLink::create([
            'user_id' => $user->id,
            'token' => $token,
            'title' => trim($title),
            'expires_at' => $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
            'max_messages' => $cap > 0 ? $cap : null,
            'messages_count' => 0,
        ]);

        activity()->causedBy($user)->performedOn($link)->log('training.cheer_link_created');

        return $link;
    }

    public function revoke(CheerLink $link, User $user): void
    {
        if ($link->revoked_at !== null) {
            return;
        }

        $link->forceFill(['revoked_at' => now()])->save();

        activity()->causedBy($user)->performedOn($link)->log('training.cheer_link_revoked');
    }
}
