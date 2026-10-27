<?php

namespace App\Support\LegacyTags;

use App\Enums\LegacyTagStatus;
use App\Exceptions\LegacyTags\TagNotActivatableException;
use App\Models\LegacyTag;
use App\Models\User;

/**
 * Read-only answer to "what should opening this tag do for this person?"
 * — the same rules ActivateTag enforces (it stays the only writer), so /t,
 * /activar and the API never promise an activation that would fail.
 *
 *   activatable · mine · taken · unavailable (+ stable reason)
 */
final readonly class TagActivationState
{
    public const ACTIVATABLE = 'activatable';

    public const MINE = 'mine';

    public const TAKEN = 'taken';

    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public string $state,
        public ?string $reason = null,
    ) {}

    public static function for(LegacyTag $tag, ?User $user): self
    {
        if ($tag->status === LegacyTagStatus::Activated) {
            $athleteId = $user?->athlete()->value('id');

            return new self($athleteId !== null && $athleteId === $tag->owner_athlete_id ? self::MINE : self::TAKEN);
        }

        return match ($tag->status) {
            LegacyTagStatus::Sold, LegacyTagStatus::Delivered => new self(self::ACTIVATABLE),
            LegacyTagStatus::InStock => self::fieldStock($tag),
            LegacyTagStatus::Disabled => new self(self::UNAVAILABLE, 'disabled'),
            LegacyTagStatus::Replaced => new self(self::UNAVAILABLE, 'replaced'),
            LegacyTagStatus::Returned => new self(self::UNAVAILABLE, 'returned'),
            default => new self(self::UNAVAILABLE, 'not_sold'),
        };
    }

    /** Unregistered sale at an ally / event / stand (see ActivateTag::inferFieldSale). */
    private static function fieldStock(LegacyTag $tag): self
    {
        $tag->loadMissing('location');

        return $tag->location !== null && $tag->location->isFieldLocation() && config('legacy.tags.activate_unreported_field_sales', true)
            ? new self(self::ACTIVATABLE)
            : new self(self::UNAVAILABLE, 'not_sold');
    }

    public function canActivate(): bool
    {
        return $this->state === self::ACTIVATABLE;
    }

    public function message(): ?string
    {
        return match ($this->state) {
            self::TAKEN => 'Este Finisher Legacy ya fue activado por otra persona. Si es tuyo, escríbenos y lo revisamos.',
            self::UNAVAILABLE => TagNotActivatableException::messageFor((string) $this->reason),
            default => null,
        };
    }
}
