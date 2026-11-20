<?php

namespace App\Services\Training;

use App\Enums\CheerAfterFree;
use App\Enums\CheerChannel;
use App\Enums\CheerMessageBilling;
use App\Enums\CheerMessageStatus;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\CheerPricingSetting;
use App\Models\CheerSenderCredit;
use App\Models\LegacyTag;
use App\Support\Training\CheerQuotaDecision;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one place that decides whether the next cheer on a link / card is
 * free, covered by a pack credit, paid by its sender or blocked
 * (docs/renovacion/tarjeta-animo.md §Reglas). Used by the public page (to
 * show the price before recording), by SubmitCheerMessage (again, under the
 * link row lock) and by the athlete's counters.
 *
 * Scopes: the shared link counts its `source = link` messages; each card
 * counts its own `source = nfc_card` messages (re-binding a card to another
 * link never resets it). "Delivered" = visible or hidden by the athlete
 * (hiding never frees a slot); unpaid pending messages still in their
 * payment window hold a slot against the hard limit so it is never oversold.
 */
class CheerQuota
{
    /**
     * @return Builder<CheerMessage>
     */
    public function scope(CheerLink $link, CheerChannel $channel, ?LegacyTag $card): Builder
    {
        $query = CheerMessage::query()->where('source', $channel->value);

        return $channel === CheerChannel::NfcCard && $card !== null
            ? $query->where('legacy_tag_id', $card->id)
            : $query->where('cheer_link_id', $link->id);
    }

    /**
     * @return array{delivered: int, free: int, paid: int, pending: int}
     */
    public function counters(CheerLink $link, CheerChannel $channel, ?LegacyTag $card): array
    {
        $delivered = [CheerMessageStatus::Visible->value, CheerMessageStatus::Hidden->value];

        $rows = $this->scope($link, $channel, $card)
            ->where(fn (Builder $q) => $q->whereIn('status', $delivered)
                ->orWhere(fn (Builder $p) => $p->where('status', CheerMessageStatus::PendingPayment->value)->where('payment_expires_at', '>', now())))
            ->selectRaw('status, billing, count(*) as total')
            ->groupBy('status', 'billing')
            ->get();

        $counters = ['delivered' => 0, 'free' => 0, 'paid' => 0, 'pending' => 0];

        foreach ($rows as $row) {
            $total = (int) $row->getAttribute('total');
            $status = $row->getAttribute('status');
            $status = $status instanceof CheerMessageStatus ? $status->value : (string) $status;
            $billing = $row->getAttribute('billing');
            $billing = $billing instanceof CheerMessageBilling ? $billing->value : (string) $billing;

            if ($status === CheerMessageStatus::PendingPayment->value) {
                $counters['pending'] += $total;

                continue;
            }

            $counters['delivered'] += $total;
            $counters[$billing === CheerMessageBilling::Free->value ? 'free' : 'paid'] += $total;
        }

        return $counters;
    }

    /**
     * What the next message on this link / card costs.
     *
     * @param  string|null  $senderKeyHash  HMAC of the sender's `cheer_sender` cookie (pack credits)
     */
    public function evaluate(CheerLink $link, CheerChannel $channel, ?LegacyTag $card, ?string $senderKeyHash = null): CheerQuotaDecision
    {
        $setting = CheerPricingSetting::for($channel);
        $counters = $this->counters($link, $channel, $card);
        $held = $counters['delivered'] + $counters['pending'];
        $roomLeft = $setting->hard_limit !== null ? max(0, $setting->hard_limit - $held) : null;

        if ($roomLeft === 0) {
            return new CheerQuotaDecision(CheerQuotaDecision::BLOCKED, $setting, 'limit', paidCount: $counters['paid']);
        }

        if ($counters['free'] < $setting->free_messages) {
            return new CheerQuotaDecision(CheerQuotaDecision::FREE, $setting, freeRemaining: $setting->free_messages - $counters['free'], paidCount: $counters['paid']);
        }

        if ($setting->after_free === CheerAfterFree::Free) {
            return new CheerQuotaDecision(CheerQuotaDecision::FREE, $setting, paidCount: $counters['paid']);
        }

        if ($setting->after_free === CheerAfterFree::Blocked) {
            return new CheerQuotaDecision(CheerQuotaDecision::BLOCKED, $setting, 'free_used', freeRemaining: 0, paidCount: $counters['paid']);
        }

        // Paid — but never at an invented price: unpriced stays blocked.
        if (! $setting->sellsMessages()) {
            return new CheerQuotaDecision(CheerQuotaDecision::BLOCKED, $setting, 'not_priced', freeRemaining: 0, paidCount: $counters['paid']);
        }

        $credit = $senderKeyHash !== null ? $this->creditFor($link, $channel, $card, $senderKeyHash) : null;

        if ($credit !== null) {
            return new CheerQuotaDecision(CheerQuotaDecision::CREDIT, $setting, freeRemaining: 0, credit: $credit, paidCount: $counters['paid']);
        }

        // A pack never sells more messages than the hard limit still allows.
        $packs = array_values(array_filter($setting->validPacks(), fn (array $p) => $roomLeft === null || $p['messages'] <= $roomLeft));

        return new CheerQuotaDecision(
            CheerQuotaDecision::PAID,
            $setting,
            priceMinor: self::priceFor($setting, $counters['paid'] + 1),
            packs: $packs,
            freeRemaining: 0,
            paidCount: $counters['paid'],
        );
    }

    /** Price of the `$nth` paid message: the base price, replaced by the last step it reached. */
    public static function priceFor(CheerPricingSetting $setting, int $nth): int
    {
        $amount = (int) $setting->price_minor;

        foreach ($setting->validSteps() as $step) {
            if ($nth >= $step['from_paid']) {
                $amount = $step['amount_minor'];
            }
        }

        return $amount;
    }

    public function creditFor(CheerLink $link, CheerChannel $channel, ?LegacyTag $card, string $senderKeyHash): ?CheerSenderCredit
    {
        return CheerSenderCredit::query()
            ->where('cheer_link_id', $link->id)
            ->where('channel', $channel->value)
            ->when($card !== null, fn ($q) => $q->where('legacy_tag_id', $card?->id), fn ($q) => $q->whereNull('legacy_tag_id'))
            ->where('sender_key_hash', $senderKeyHash)
            ->where('credits_remaining', '>', 0)
            ->oldest('id')
            ->first();
    }
}
