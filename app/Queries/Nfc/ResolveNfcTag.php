<?php

namespace App\Queries\Nfc;

use App\Enums\NfcLinkStatus;
use App\Enums\NfcTagStatus;
use App\Enums\NfcTargetType;
use App\Models\Athlete;
use App\Models\AthleteProfile;
use App\Models\NfcAssignment;
use App\Models\NfcTag;
use App\Models\User;
use App\Support\Nfc\NfcResolution;
use App\Support\Nfc\NfcToken;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a tag into "what does this tap mean for THIS viewer" (docs/NFC.md
 * §Resolución). Read-only. Never exposes the owner's name/email — only a
 * public profile username, and only when that profile is public.
 */
class ResolveNfcTag
{
    public const string PLATE_IMAGE = '/media/brand/plate/legacy-plate-front-800.webp';

    public function handle(NfcTag $tag, ?User $viewer): NfcResolution
    {
        if ($tag->status === NfcTagStatus::Disabled) {
            return new NfcResolution(NfcLinkStatus::Disabled, false, false, null, null, null, 'disabled');
        }

        $assignment = NfcAssignment::query()
            ->open()
            ->where('nfc_tag_id', $tag->id)
            ->with([
                'plate.legacyCode', 'plate.eventEdition.event',
                'product.media', 'productVariant', 'ownedProduct',
                'event', 'athlete.user.athleteProfile',
            ])
            ->latest('id')
            ->first();

        if ($assignment === null) {
            return new NfcResolution(NfcLinkStatus::Disabled, false, false, null, null, null, 'not_ready');
        }

        $viewerAthlete = $viewer?->athlete()->first();
        $type = $assignment->target_type;
        $claimed = ! $type->isOwnable() || $assignment->isClaimed();
        $ownedByMe = $viewerAthlete !== null && $assignment->athlete_id === $viewerAthlete->id;

        $target = $this->target($tag, $assignment, $ownedByMe);
        $status = $claimed ? NfcLinkStatus::Claimed : NfcLinkStatus::Unclaimed;

        return new NfcResolution(
            status: $status,
            ownedByMe: $ownedByMe,
            canClaim: $status === NfcLinkStatus::Unclaimed && $this->viewerMayClaim($assignment, $viewerAthlete),
            targetType: $type,
            target: $target,
            redirectUrl: $claimed ? $target['web_url'] : null,
        );
    }

    /**
     * A guest may always try (they log in first). A signed-in viewer is
     * blocked up-front only when the target already belongs to a different
     * athlete (a gifted unit already assigned, an integrated plate).
     */
    private function viewerMayClaim(NfcAssignment $assignment, ?Athlete $viewerAthlete): bool
    {
        if ($viewerAthlete === null) {
            return true;
        }

        $reserved = match ($assignment->target_type) {
            NfcTargetType::LegacyPlate => $assignment->plate?->athlete_id,
            NfcTargetType::Product => $assignment->ownedProduct?->athlete_id,
            default => null,
        };

        return $reserved === null || $reserved === $viewerAthlete->id;
    }

    /**
     * @return array{type: string, title: string, subtitle: ?string, image_url: ?string, web_url: string, profile_username: ?string, event_slug: ?string}
     */
    private function target(NfcTag $tag, NfcAssignment $assignment, bool $ownedByMe): array
    {
        $profile = $this->publicProfile($assignment);
        $fallbackUrl = NfcToken::url($tag->token);

        return match ($assignment->target_type) {
            NfcTargetType::LegacyPlate => (function () use ($assignment, $profile, $fallbackUrl) {
                $plate = $assignment->plate;
                $event = $plate?->eventEdition?->event;

                return [
                    'type' => NfcTargetType::LegacyPlate->value,
                    'title' => $plate?->event_name ?: 'Legacy Plate',
                    'subtitle' => collect([$plate?->race_name, $plate?->official_time])->filter()->implode(' · ') ?: null,
                    'image_url' => asset(self::PLATE_IMAGE),
                    'web_url' => $plate?->legacyCode ? route('legacy-code.show', $plate->legacyCode->code) : $fallbackUrl,
                    'profile_username' => $profile?->username,
                    'event_slug' => $event?->slug,
                ];
            })(),
            NfcTargetType::Product => [
                'type' => NfcTargetType::Product->value,
                'title' => $assignment->product->name ?? 'Producto Finisher Legacy',
                'subtitle' => $assignment->productVariant?->name,
                'image_url' => $assignment->product?->primaryImageUrl(),
                'web_url' => match (true) {
                    $ownedByMe => route('dashboard.my-gear'),
                    $profile !== null => route('profile.public', $profile->username),
                    $assignment->product !== null => route('store.products.show', $assignment->product->slug),
                    default => $fallbackUrl,
                },
                'profile_username' => $profile?->username,
                'event_slug' => null,
            ],
            NfcTargetType::Profile => [
                'type' => NfcTargetType::Profile->value,
                'title' => $profile !== null ? '@'.$profile->username : 'Perfil Finisher Legacy',
                'subtitle' => $profile?->city,
                'image_url' => $profile?->profile_photo_path ? Storage::disk('public')->url($profile->profile_photo_path) : null,
                'web_url' => match (true) {
                    $profile !== null => route('profile.public', $profile->username),
                    $ownedByMe => route('dashboard.profile.show'),
                    default => $fallbackUrl,
                },
                'profile_username' => $profile?->username,
                'event_slug' => null,
            ],
            NfcTargetType::Event => [
                'type' => NfcTargetType::Event->value,
                'title' => $assignment->event->name ?? 'Evento',
                'subtitle' => null,
                'image_url' => $assignment->event?->cover_path ? Storage::disk('public')->url($assignment->event->cover_path) : null,
                'web_url' => $assignment->event ? route('events.show', $assignment->event->slug) : $fallbackUrl,
                'profile_username' => null,
                'event_slug' => $assignment->event?->slug,
            ],
        };
    }

    private function publicProfile(NfcAssignment $assignment): ?AthleteProfile
    {
        $profile = $assignment->athlete?->user?->athleteProfile;

        return $profile !== null && $profile->profile_visibility->value === 'public' ? $profile : null;
    }
}
