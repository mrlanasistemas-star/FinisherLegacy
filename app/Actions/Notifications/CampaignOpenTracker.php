<?php

namespace App\Actions\Notifications;

use App\Models\PushCampaign;
use App\Models\PushCampaignRecipient;
use App\Models\User;

/**
 * Records that a user opened a push campaign (docs/renovacion/campanas-push.md
 * §Aperturas) — what POST /api/v1/notifications/{id}/opened calls when the
 * app handles a tap. `{id}` is the push `data.campaign_id` (campaign uuid)
 * or the id of the user's notification-center copy of the campaign.
 *
 * Only the caller's own recipient row is ever touched, the first open
 * wins (idempotent), and the answer never reveals whether someone else
 * got the campaign.
 */
final class CampaignOpenTracker
{
    public function record(User $user, string $id): bool
    {
        $campaignId = PushCampaign::query()->where('uuid', $id)->value('id')
            ?? $this->campaignFromNotification($user, $id);

        if ($campaignId === null) {
            return false;
        }

        return PushCampaignRecipient::query()
            ->where('push_campaign_id', $campaignId)
            ->where('user_id', $user->id)
            ->whereNull('opened_at')
            ->update(['opened_at' => now(), 'updated_at' => now()]) > 0;
    }

    private function campaignFromNotification(User $user, string $id): ?int
    {
        $notification = $user->notifications()->whereKey($id)->first();
        $uuid = $notification?->data['metadata']['campaign_uuid'] ?? null;

        return is_string($uuid) ? PushCampaign::query()->where('uuid', $uuid)->value('id') : null;
    }
}
