<?php

namespace App\Actions\Notifications;

use App\Models\PushCampaign;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * "No more than N broadcasts to Todos per hour" (default 1,
 * config('push_campaigns.all_audience_per_hour')) — checked when a
 * campaign is scheduled or sent now, and again right before a scheduled
 * one starts. A campaign counts if it already started within an hour of
 * the intended moment, or is scheduled to start within that hour.
 * Cancelled campaigns and drafts never count. Other audiences are not
 * limited here.
 */
final class CampaignRateGuard
{
    public function limit(): int
    {
        return max(1, (int) config('push_campaigns.all_audience_per_hour', 1));
    }

    /** Whether `$campaign` may start at `$at` without breaking the limit. */
    public function allows(PushCampaign $campaign, CarbonInterface $at): bool
    {
        if ($campaign->audience_type !== 'all') {
            return true;
        }

        $from = $at->copy()->subHour();
        $to = $at->copy()->addHour();

        $nearby = PushCampaign::query()
            ->where('audience_type', 'all')
            ->when($campaign->exists, fn ($q) => $q->whereKeyNot($campaign->getKey()))
            ->where(function ($q) use ($from, $to) {
                $q->where(fn ($started) => $started
                    ->whereIn('status', [PushCampaign::STATUS_SENDING, PushCampaign::STATUS_SENT, PushCampaign::STATUS_FAILED])
                    ->whereBetween('started_at', [$from, $to]))
                    ->orWhere(fn ($scheduled) => $scheduled
                        ->where('status', PushCampaign::STATUS_SCHEDULED)
                        ->whereBetween('scheduled_at', [$from, $to]));
            })
            ->count();

        return $nearby < $this->limit();
    }

    /**
     * @throws ValidationException
     */
    public function ensure(PushCampaign $campaign, CarbonInterface $at): void
    {
        if (! $this->allows($campaign, $at)) {
            $limit = $this->limit();

            throw ValidationException::withMessages([
                'audience_type' => $limit === 1
                    ? 'Ya hay una campaña a todos los usuarios en esa hora. Para no saturar a nadie, solo se permite una por hora: elige otra hora o una audiencia más precisa.'
                    : "Ya hay {$limit} campañas a todos los usuarios en esa hora. Elige otra hora o una audiencia más precisa.",
            ]);
        }
    }
}
