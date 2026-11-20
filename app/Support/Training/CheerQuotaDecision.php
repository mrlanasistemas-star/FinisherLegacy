<?php

namespace App\Support\Training;

use App\Models\CheerPricingSetting;
use App\Models\CheerSenderCredit;

/**
 * The answer of App\Services\Training\CheerQuota::evaluate() for one next
 * message on a link / card (docs/renovacion/tarjeta-animo.md):
 *
 *   free     inside the free quota (or unlimited free) → delivered at once
 *   credit   covered by a pack credit of this sender → delivered at once
 *   paid     the sender must pay `priceMinor` (or a pack) first
 *   blocked  not accepted (`reason`: limit | free_used | not_priced)
 */
final readonly class CheerQuotaDecision
{
    public const string FREE = 'free';

    public const string CREDIT = 'credit';

    public const string PAID = 'paid';

    public const string BLOCKED = 'blocked';

    /**
     * @param  list<array{messages: int, amount_minor: int}>  $packs
     */
    public function __construct(
        public string $mode,
        public CheerPricingSetting $setting,
        public ?string $reason = null,
        public ?int $priceMinor = null,
        public array $packs = [],
        public ?int $freeRemaining = null,
        public ?CheerSenderCredit $credit = null,
        public int $paidCount = 0,
    ) {}

    /** Delivered right away, without a payment. */
    public function isFree(): bool
    {
        return $this->mode === self::FREE || $this->mode === self::CREDIT;
    }

    public function requiresPayment(): bool
    {
        return $this->mode === self::PAID;
    }

    public function isBlocked(): bool
    {
        return $this->mode === self::BLOCKED;
    }

    /** Friendly sentence for a blocked sender. */
    public function blockedMessage(): string
    {
        return match ($this->reason) {
            'limit' => __('Esta persona ya recibió todos los mensajes permitidos aquí. ¡Gracias por tu apoyo!'),
            default => __('Este enlace ya recibió todos sus mensajes gratuitos. Pídele uno nuevo para dejarle el tuyo.'),
        };
    }

    /**
     * What the public cheer page shows before recording (no internals).
     *
     * @return array{mode: string, reason: string|null, price_minor: int|null, currency: string, packs: list<array{messages: int, amount_minor: int}>, free_remaining: int|null, credits_remaining: int|null, anonymous_allowed: bool}
     */
    public function toPublic(): array
    {
        return [
            'mode' => $this->mode,
            'reason' => $this->reason,
            'price_minor' => $this->priceMinor,
            'currency' => $this->setting->currency,
            'packs' => $this->packs,
            'free_remaining' => $this->freeRemaining,
            'credits_remaining' => $this->credit?->credits_remaining,
            'anonymous_allowed' => $this->setting->allow_anonymous,
        ];
    }
}
