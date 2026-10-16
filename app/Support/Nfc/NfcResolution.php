<?php

namespace App\Support\Nfc;

use App\Enums\NfcLinkStatus;
use App\Enums\NfcTargetType;

/**
 * What a tap resolves to for one viewer — shared by `GET /n/{token}` (web)
 * and `GET /api/v1/nfc/{token}` so both answer identically.
 *
 * @phpstan-type Target array{type: string, title: string, subtitle: ?string, image_url: ?string, web_url: string, profile_username: ?string, event_slug: ?string}
 */
final readonly class NfcResolution
{
    /**
     * @param  Target|null  $target
     */
    public function __construct(
        public NfcLinkStatus $status,
        public bool $ownedByMe,
        public bool $canClaim,
        public ?NfcTargetType $targetType,
        public ?array $target,
        /** Where a claimed tap sends this viewer on the web. */
        public ?string $redirectUrl,
        /** 'disabled' | 'not_ready' | null — why a tag is unusable. */
        public ?string $reason = null,
    ) {}

    /**
     * The exact `data` object of the API contract (docs/NFC.md §API).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'owned_by_me' => $this->ownedByMe,
            'can_claim' => $this->canClaim,
            'target' => $this->target,
        ];
    }
}
