<?php

namespace App\Support\Training;

use App\Enums\CheerChannel;
use App\Enums\LegacyTagStatus;
use App\Enums\ProfileVisibility;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\CheerPricingSetting;
use App\Models\LegacyTag;
use App\Models\User;
use App\Services\Training\CheerQuota;
use Illuminate\Support\Facades\Storage;

/**
 * One JSON shape per Entrenamientos object, shared by the web pages and
 * /api/v1/training (the mobile app codes against `message()` and `link()`
 * exactly — see docs/renovacion/entrenamientos.md). Audio only ever leaves
 * as a short-lived signed URL; the sender's IP hash never leaves at all.
 */
class CheerPresenter
{
    public function __construct(private readonly CheerQuota $quota) {}

    /**
     * `sender_name` is "Anónimo" (translated) for an anonymous sender;
     * `anonymous` and `source` (link | nfc_card) are additive.
     *
     * @return array{uuid: string, sender_name: string, anonymous: bool, source: string, relationship: string|null, body: string|null, icon: string, audio_url: string|null, duration_seconds: int, listened_at: string|null, favorite: bool, created_at: string, link: array{uuid: string, title: string}}
     */
    public function message(CheerMessage $message): array
    {
        return [
            'uuid' => $message->uuid,
            'sender_name' => $message->displaySenderName(),
            'anonymous' => $message->anonymous,
            'source' => $message->source->value,
            'relationship' => $message->relationship,
            'body' => $message->body,
            'icon' => $message->icon->value,
            'audio_url' => $message->audioUrl(),
            'duration_seconds' => $message->duration_seconds,
            'listened_at' => $message->listened_at?->toIso8601String(),
            'favorite' => $message->favorite,
            'created_at' => $message->created_at->toIso8601String(),
            'link' => ['uuid' => $message->link->uuid, 'title' => $message->link->title],
        ];
    }

    /**
     * @return array{uuid: string, title: string, url: string, expires_at: string|null, revoked_at: string|null, messages_count: int}
     */
    public function link(CheerLink $link): array
    {
        return [
            'uuid' => $link->uuid,
            'title' => $link->title,
            'url' => $link->url(),
            'expires_at' => $link->expires_at?->toIso8601String(),
            'revoked_at' => $link->revoked_at?->toIso8601String(),
            'messages_count' => $link->messages_count,
        ];
    }

    /**
     * link() + its counters for the athlete (docs/renovacion/tarjeta-animo.md):
     * free messages used / included, paid ones, and the Tarjetas de ánimo
     * bound to it. Additive to the mobile contract.
     *
     * @return array<string, mixed>
     */
    public function linkWithStats(CheerLink $link): array
    {
        $linkSetting = CheerPricingSetting::for(CheerChannel::Link);
        $counters = $this->quota->counters($link, CheerChannel::Link, null);
        $cardMessages = $link->messages()->where('source', CheerChannel::NfcCard->value)
            ->whereIn('status', ['visible', 'hidden'])->count();

        return [
            ...$this->link($link),
            'state' => $link->state(),
            'stats' => [
                'free_used' => $counters['free'],
                'free_limit' => $linkSetting->free_messages,
                'after_free' => $linkSetting->after_free->value,
                'paid_count' => $counters['paid'],
                'cards_count' => $link->cards()->where('status', LegacyTagStatus::Activated->value)->count(),
                'card_messages_count' => $cardMessages,
            ],
        ];
    }

    /**
     * One Tarjeta de ánimo NFC of the athlete (GET /api/v1/training/cards).
     *
     * @return array<string, mixed>
     */
    public function card(LegacyTag $card): array
    {
        $card->loadMissing('cheerLink');
        $link = $card->cheerLink;
        $setting = CheerPricingSetting::for(CheerChannel::NfcCard);
        $counters = $link !== null ? $this->quota->counters($link, CheerChannel::NfcCard, $card) : null;

        return [
            'uuid' => $card->uuid,
            'code' => $card->displayCode(),
            'active' => $card->status === LegacyTagStatus::Activated && $link !== null,
            'link' => $link === null ? null : ['uuid' => $link->uuid, 'title' => $link->title, 'state' => $link->state()],
            'stats' => [
                'messages_count' => $counters['delivered'] ?? 0,
                'free_used' => $counters['free'] ?? 0,
                'free_limit' => $setting->free_messages,
                'paid_count' => $counters['paid'] ?? 0,
            ],
            'activated_at' => $card->activated_at?->toIso8601String(),
            'bound_at' => $card->cheer_bound_at?->toIso8601String(),
        ];
    }

    /**
     * Web-only extras (state badge, cap).
     *
     * @return array<string, mixed>
     */
    public function linkForWeb(CheerLink $link): array
    {
        return [
            ...$this->link($link),
            'state' => $link->state(),
            'max_messages' => $link->max_messages,
            'created_at' => $link->created_at?->toIso8601String(),
        ];
    }

    /**
     * What a sender sees about the athlete: account display name, and the
     * profile photo only when the athlete's profile is public.
     *
     * @return array{name: string, avatar_url: string|null}
     */
    public function athlete(User $user): array
    {
        $profile = $user->athleteProfile;
        $photo = $profile !== null
            && $profile->profile_visibility === ProfileVisibility::Public
            && is_string($profile->profile_photo_path) && $profile->profile_photo_path !== ''
                ? Storage::disk('public')->url($profile->profile_photo_path)
                : null;

        return ['name' => trim((string) $user->name) ?: __('Atleta'), 'avatar_url' => $photo];
    }
}
