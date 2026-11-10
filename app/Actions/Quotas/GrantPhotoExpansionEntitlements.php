<?php

namespace App\Actions\Quotas;

use App\Enums\BillingPeriod;
use App\Enums\FulfillmentStatus;
use App\Enums\StoryQuotaKind;
use App\Models\LegacyStory;
use App\Models\MediaEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PhotoExpansionPack;
use App\Services\Media\StoryMediaQuota;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Called by App\Actions\Commerce\MarkOrderPaid — i.e. only after the
 * backend confirmed the money (verified payment webhook, the
 * server-to-server status sync, an admin-registered manual payment, or a
 * store transaction verified by RecordStorePurchase). A client "success"
 * screen can never reach this. Every expansion OrderItem becomes one
 * MediaEntitlement for its story with the kind, units and billing period
 * frozen at purchase time. Idempotent per order item (unique index +
 * existence check): a replayed confirmation never grants twice.
 *
 * Prepaid terms (billing_period semiannual / annual): the entitlement
 * gets starts_at / expires_at here, at payment time. Paying for the same
 * pack again while its current term ends within
 * StoryMediaQuota::RENEWAL_WINDOW_DAYS is a RENEWAL — the new term starts
 * when the current one ends, so no paid day is lost. Otherwise (no term,
 * a term already over, or one still far from ending) it starts now.
 */
class GrantPhotoExpansionEntitlements
{
    public const string ITEM_KIND = 'photo_expansion';

    public function handle(Order $order): void
    {
        $order->loadMissing('items');

        $items = $order->items->filter(fn (OrderItem $item) => ($item->metadata['kind'] ?? null) === self::ITEM_KIND);

        if ($items->isEmpty()) {
            return;
        }

        foreach ($items as $item) {
            $this->grant($order, $item);
        }

        // Digital delivery: an order made only of fulfilled items is done.
        if ($order->items()->whereNull('fulfilled_at')->doesntExist()) {
            $order->update(['fulfillment_status' => FulfillmentStatus::Fulfilled]);
        }
    }

    private function grant(Order $order, OrderItem $item): void
    {
        if (MediaEntitlement::query()->where('order_item_id', $item->id)->exists()) {
            return;
        }

        $meta = $item->metadata;
        $storyId = (int) ($meta['legacy_story_id'] ?? 0);
        $units = (int) ($meta['units'] ?? $meta['photos'] ?? 0) * max(1, (int) $item->quantity);
        $kind = StoryQuotaKind::tryFrom((string) ($meta['expansion_kind'] ?? '')) ?? StoryQuotaKind::Photos;
        $period = BillingPeriod::tryFrom((string) ($meta['billing_period'] ?? '')) ?? BillingPeriod::OneTime;
        $packId = isset($meta['photo_expansion_pack_id']) ? (int) $meta['photo_expansion_pack_id'] : null;

        // Soft-deleted stories still get the right they paid for (restorable).
        if ($units <= 0 || ! LegacyStory::withTrashed()->whereKey($storyId)->exists() || $order->user_id === null) {
            return;
        }

        try {
            DB::transaction(function () use ($order, $item, $meta, $storyId, $units, $kind, $period, $packId) {
                // Serialize grants of one story so two renewals never share a start.
                LegacyStory::withTrashed()->whereKey($storyId)->lockForUpdate()->first();

                $startsAt = $period->isTerm() ? $this->termStart($storyId, $packId) : null;

                MediaEntitlement::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $order->user_id,
                    'legacy_story_id' => $storyId,
                    'photo_expansion_pack_id' => $packId,
                    'product_price_schedule_id' => $meta['product_price_schedule_id'] ?? null,
                    'order_item_id' => $item->id,
                    'extra_images' => $kind === StoryQuotaKind::Photos ? $units : 0,
                    'extra_videos' => $kind === StoryQuotaKind::Videos ? $units : 0,
                    'extra_audio' => $kind === StoryQuotaKind::Audio ? $units : 0,
                    'source' => PhotoExpansionPack::ENTITLEMENT_SOURCE,
                    'billing_period' => $period,
                    'starts_at' => $startsAt,
                    'expires_at' => $startsAt !== null ? $period->expiresAt($startsAt) : null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent confirmation already granted this item.
            return;
        }

        if ($item->fulfilled_at === null) {
            $item->update(['fulfilled_at' => now()]);
        }
    }

    /**
     * When the new term begins: at the end of the running term of the same
     * pack when that end is inside the renewal window and nobody renewed
     * it yet; otherwise now.
     */
    private function termStart(int $storyId, ?int $packId): CarbonInterface
    {
        $now = now();

        if ($packId === null) {
            return $now;
        }

        $terms = MediaEntitlement::query()
            ->where('legacy_story_id', $storyId)
            ->where('photo_expansion_pack_id', $packId)
            ->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->orderBy('expires_at')
            ->get();

        foreach ($terms as $term) {
            $expiresAt = $term->expires_at;

            if ($expiresAt === null || $expiresAt->greaterThan($now->copy()->addDays(StoryMediaQuota::RENEWAL_WINDOW_DAYS))) {
                continue;
            }

            $renewed = $terms->contains(fn (MediaEntitlement $next) => $next->id !== $term->id
                && $next->starts_at !== null && $next->starts_at->equalTo($expiresAt));

            if (! $renewed) {
                return $expiresAt->copy();
            }
        }

        return $now;
    }
}
