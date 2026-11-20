<?php

namespace App\Models;

use App\Enums\CheerAfterFree;
use App\Enums\CheerChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cheer-message pricing for one channel — the shared link or the Tarjeta de
 * ánimo NFC (Administración → Límites y tarifas → Mensajes de ánimo,
 * docs/renovacion/tarjeta-animo.md):
 *
 *   free_messages   free messages per link / per card
 *   after_free      blocked | free (unlimited) | paid (the SENDER pays)
 *   hard_limit      optional total cap per link / card (null = no cap)
 *   price_minor     price of one extra message (MXN cents). Never invented:
 *                   null means "not priced", and a paid channel without a
 *                   price behaves as blocked
 *   price_steps     [{from_paid, amount_minor}] — "banderas": from the Nth
 *                   paid message of that link / card on, one message costs
 *                   amount_minor instead of price_minor
 *   packs           [{messages, amount_minor}] — buy several at once
 *   allow_anonymous sender name becomes optional ("Anónimo")
 *
 * No row = defaults(), which keep today's behavior (the config cap of free
 * messages, then blocked, names required). The amount charged is frozen on
 * the OrderItem, so editing a price never touches a pending payment.
 *
 * @property int $id
 * @property CheerChannel $channel
 * @property int $free_messages
 * @property CheerAfterFree $after_free
 * @property int|null $hard_limit
 * @property int|null $price_minor
 * @property string $currency
 * @property list<array<string, mixed>>|null $price_steps Stored JSON; read through validSteps()
 * @property list<array<string, mixed>>|null $packs Stored JSON; read through validPacks()
 * @property bool $allow_anonymous
 * @property int|null $updated_by
 */
#[Fillable(['channel', 'free_messages', 'after_free', 'hard_limit', 'price_minor', 'currency', 'price_steps', 'packs', 'allow_anonymous', 'updated_by'])]
class CheerPricingSetting extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => CheerChannel::class,
            'after_free' => CheerAfterFree::class,
            'free_messages' => 'integer',
            'hard_limit' => 'integer',
            'price_minor' => 'integer',
            'price_steps' => 'array',
            'packs' => 'array',
            'allow_anonymous' => 'boolean',
        ];
    }

    /** The saved settings of a channel, or the (unsaved) defaults. */
    public static function for(CheerChannel $channel): self
    {
        return self::query()->where('channel', $channel->value)->first() ?? self::defaults($channel);
    }

    /** Today's behavior: the config cap of free messages, then blocked; names required. */
    public static function defaults(CheerChannel $channel): self
    {
        return new self([
            'channel' => $channel,
            'free_messages' => max(0, (int) config('training.cheers.max_messages_per_link', 200)),
            'after_free' => CheerAfterFree::Blocked,
            'hard_limit' => null,
            'price_minor' => null,
            'currency' => 'MXN',
            'price_steps' => [],
            'packs' => [],
            'allow_anonymous' => false,
        ]);
    }

    /** Paid mode with a real price set by the admin. */
    public function sellsMessages(): bool
    {
        return $this->after_free === CheerAfterFree::Paid && ($this->price_minor ?? 0) > 0;
    }

    /**
     * Packs with a positive price and at least 2 messages, smallest first.
     *
     * @return list<array{messages: int, amount_minor: int}>
     */
    public function validPacks(): array
    {
        $packs = array_values(array_filter(
            array_map(fn ($p) => ['messages' => (int) ($p['messages'] ?? 0), 'amount_minor' => (int) ($p['amount_minor'] ?? 0)], (array) ($this->packs ?? [])),
            fn (array $p) => $p['messages'] >= 2 && $p['amount_minor'] > 0,
        ));
        usort($packs, fn ($a, $b) => $a['messages'] <=> $b['messages']);

        return $packs;
    }

    /**
     * Price steps sorted by `from_paid` (a step from the 1st paid message
     * would just replace the base price, so steps start at 2).
     *
     * @return list<array{from_paid: int, amount_minor: int}>
     */
    public function validSteps(): array
    {
        $steps = array_values(array_filter(
            array_map(fn ($s) => ['from_paid' => (int) ($s['from_paid'] ?? 0), 'amount_minor' => (int) ($s['amount_minor'] ?? 0)], (array) ($this->price_steps ?? [])),
            fn (array $s) => $s['from_paid'] >= 2 && $s['amount_minor'] > 0,
        ));
        usort($steps, fn ($a, $b) => $a['from_paid'] <=> $b['from_paid']);

        return $steps;
    }
}
