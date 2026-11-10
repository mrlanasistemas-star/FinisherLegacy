<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationCategory;
use App\Models\PushCampaign;
use App\Support\Notifications\PushMessage;
use Illuminate\Support\Str;

/**
 * Builds the App\Support\Notifications\PushMessage a campaign sends
 * (docs/renovacion/campanas-push.md §Contenido). Title and body are plain
 * text (validated without HTML, trimmed to the platform limits again here);
 * routing data is `{type: campaign, campaign_id, target, action_url}` — the
 * app reports a tap with POST /api/v1/notifications/{campaign_id}/opened.
 *
 * Image: the uploaded big picture, or — with "Incluir logo" and no custom
 * image — the official monogram (Android shows it as the big picture and,
 * collapsed, as the large icon; iOS needs the Notification Service
 * Extension). `data.large_icon` always carries the monogram when the logo
 * is on, for app builds that render their own large icon.
 */
final class CampaignMessage
{
    public const string LOGO_PATH = 'images/brand/logo/fl-monograma.png';

    public static function for(PushCampaign $campaign, bool $test = false): PushMessage
    {
        $destination = CampaignDestination::resolve($campaign);
        $logo = $campaign->include_logo ? asset(self::LOGO_PATH) : null;
        $image = $campaign->image_path !== null ? $campaign->imageUrl() : $logo;

        $title = Str::limit(self::plain($campaign->title), 65, '…');

        return PushMessage::make($test ? Str::limit('[Prueba] '.$title, 65, '…') : $title, Str::limit(self::plain($campaign->body), 240, '…'))
            ->withData(array_filter([
                'type' => 'campaign',
                'campaign_id' => $campaign->uuid,
                'target' => $destination['target'],
                'large_icon' => $logo,
                'test' => $test ?: null,
            ], fn ($value) => $value !== null))
            ->withDeepLink($destination['deep_link'])
            ->withImage($image)
            ->withColor($campaign->color)
            ->withChannel($campaign->channel)
            ->withCategory($test ? null : NotificationCategory::from($campaign->category));
    }

    /** Defensive: content is validated without tags, but never trust storage. */
    public static function plain(string $value): string
    {
        return trim(preg_replace('/[^\S\n]+/u', ' ', strip_tags($value)) ?? '');
    }
}
