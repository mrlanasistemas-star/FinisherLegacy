<?php

namespace App\Actions\Quotas;

use App\Enums\StoryQuotaKind;
use App\Models\LegacyStory;
use App\Models\MediaEntitlement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Liberarle más": an admin gives extra capacity by hand — to ONE story,
 * or to a user (then it counts on every story that user owns) — without
 * a purchase. It is a MediaEntitlement with source `manual_grant`, the
 * reason and who granted it; optional `expires_at`. Counted by
 * StoryMediaQuota exactly like a paid expansion, listed in Límites y
 * tarifas and revocable there. Both the grant and the revocation are
 * written to the activity log; revoking never deletes content.
 */
class GrantStoryCapacity
{
    public function handle(User $admin, User $recipient, ?LegacyStory $story, StoryQuotaKind $kind, int $units, string $reason, ?CarbonInterface $expiresAt = null): MediaEntitlement
    {
        if ($units < 1) {
            throw ValidationException::withMessages(['units' => 'Indica cuánto espacio quieres liberar.']);
        }

        if ($story !== null && ! $story->isOwnedBy($recipient)) {
            throw ValidationException::withMessages(['story' => 'Ese recuerdo no es de esa persona.']);
        }

        return DB::transaction(function () use ($admin, $recipient, $story, $kind, $units, $reason, $expiresAt) {
            $entitlement = MediaEntitlement::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $recipient->id,
                'legacy_story_id' => $story?->id,
                'extra_images' => $kind === StoryQuotaKind::Photos ? $units : 0,
                'extra_videos' => $kind === StoryQuotaKind::Videos ? $units : 0,
                'extra_audio' => $kind === StoryQuotaKind::Audio ? $units : 0,
                'source' => MediaEntitlement::SOURCE_MANUAL_GRANT,
                'granted_by' => $admin->id,
                'grant_reason' => mb_substr(trim($reason), 0, 255),
                'starts_at' => $expiresAt !== null ? now() : null,
                'expires_at' => $expiresAt,
            ]);

            activity('quotas')
                ->causedBy($admin)
                ->performedOn($entitlement)
                ->event('created')
                ->withProperties([
                    'user_id' => $recipient->id,
                    'story' => $story?->public_id,
                    'kind' => $kind->value,
                    'units' => $units,
                    'reason' => $entitlement->grant_reason,
                    'expires_at' => $expiresAt?->toIso8601String(),
                ])
                ->log('Liberó espacio manualmente');

            return $entitlement;
        });
    }

    public function revoke(MediaEntitlement $entitlement, User $admin, ?string $reason = null): MediaEntitlement
    {
        if (! $entitlement->isManualGrant()) {
            throw ValidationException::withMessages(['entitlement' => 'Solo se puede revocar aquí el espacio liberado a mano. Una compra se revierte con su reembolso.']);
        }

        if ($entitlement->revoked_at !== null) {
            return $entitlement;
        }

        $entitlement->update([
            'revoked_at' => now(),
            'revoked_by' => $admin->id,
            'revocation_reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null,
        ]);

        activity('quotas')
            ->causedBy($admin)
            ->performedOn($entitlement)
            ->event('updated')
            ->withProperties(['reason' => $entitlement->revocation_reason])
            ->log('Revocó espacio liberado manualmente');

        return $entitlement;
    }
}
