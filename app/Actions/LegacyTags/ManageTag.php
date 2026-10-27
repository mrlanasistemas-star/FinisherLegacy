<?php

namespace App\Actions\LegacyTags;

use App\Enums\AthleteOwnedProductStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use App\Services\LegacyTags\TagLifecycle;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Staff-only support operations on one unit — every one locked, audited
 * (activity_log) and written to the unit history:
 *
 *   replace  — defective/lost piece: a new in-stock unit inherits owner and
 *              story; the old one becomes `replaced` (keeps its history).
 *   return   — refund/return: ownership revoked, story detached (never
 *              deleted); optionally back to stock at a location.
 *   disable / enable — lost, stolen, revoked.
 *   revokeOwnership — wrong claim resolved by support: back to `delivered`.
 */
class ManageTag
{
    public function __construct(
        private readonly TagLifecycle $lifecycle,
        private readonly InventoryService $inventory,
    ) {}

    public function replace(LegacyTag $old, LegacyTag $new, User $actor, string $reason): LegacyTag
    {
        return DB::transaction(function () use ($old, $new, $actor, $reason) {
            /** @var LegacyTag $old */
            $old = LegacyTag::query()->lockForUpdate()->findOrFail($old->id);
            /** @var LegacyTag $new */
            $new = LegacyTag::query()->lockForUpdate()->findOrFail($new->id);

            if ($old->status->isTerminal()) {
                throw new InvalidArgumentException('Esta pieza ya fue reemplazada o desactivada.');
            }

            if ($new->status !== LegacyTagStatus::InStock) {
                throw new InvalidArgumentException("La pieza de reemplazo {$new->serial} no está en inventario.");
            }

            $new->loadMissing('productVariant.product', 'location');

            if ($new->location !== null) {
                $this->inventory->sellDirect($new->productVariant, $new->location, 1, LegacyTag::class, $old->id, $actor, 'replacement');
            }

            $wasActivated = $old->status === LegacyTagStatus::Activated;

            $this->lifecycle->transition($new, LegacyTagEventType::Sold, $wasActivated ? LegacyTagStatus::Activated : LegacyTagStatus::Delivered, [
                'replaces_tag_id' => $old->id,
                'order_id' => $old->order_id,
                'order_item_id' => $old->order_item_id,
                'sales_channel' => $old->sales_channel,
                'ally_id' => $old->ally_id,
                'owner_athlete_id' => $old->owner_athlete_id,
                'athlete_owned_product_id' => $old->athlete_owned_product_id,
                'sold_at' => $old->sold_at ?? now(),
                'delivered_at' => now(),
                'activated_at' => $wasActivated ? now() : null,
            ], $actor, "Reemplazo de {$old->serial}: {$reason}", ['replaces' => $old->serial]);

            $this->lifecycle->transition($old, LegacyTagEventType::Replaced, LegacyTagStatus::Replaced, [
                'disabled_reason' => $reason,
            ], $actor, "Reemplazado por {$new->serial}", ['replacement' => $new->serial]);

            // The story follows the athlete's object, not the broken chip.
            $old->story()->update(['legacy_tag_id' => $new->id]);

            activity()->causedBy($actor)->performedOn($old)
                ->withProperties(['old' => $old->serial, 'new' => $new->serial, 'reason' => $reason])
                ->log('tags.replaced');

            return $new->fresh();
        });
    }

    public function return(LegacyTag $tag, User $actor, string $reason, ?InventoryLocation $restockAt = null): LegacyTag
    {
        return DB::transaction(function () use ($tag, $actor, $reason, $restockAt) {
            /** @var LegacyTag $tag */
            $tag = LegacyTag::query()->lockForUpdate()->findOrFail($tag->id);

            if (! $tag->status->isSold()) {
                throw new InvalidArgumentException('Solo una pieza vendida puede devolverse.');
            }

            $this->detachOwner($tag);

            $this->lifecycle->transition($tag, LegacyTagEventType::Returned, LegacyTagStatus::Returned, [
                'owner_athlete_id' => null,
                'athlete_owned_product_id' => null,
            ], $actor, $reason);

            if ($restockAt !== null) {
                $tag->loadMissing('productVariant.product');
                $this->inventory->returnStock($tag->productVariant, $restockAt, 1, LegacyTag::class, $tag->id, $actor, $reason);

                $this->lifecycle->transition($tag, LegacyTagEventType::Restocked, LegacyTagStatus::InStock, [
                    'inventory_location_id' => $restockAt->id,
                    'order_id' => null,
                    'order_item_id' => null,
                    'sales_channel' => null,
                    'ally_id' => null,
                    'sold_at' => null,
                    'delivered_at' => null,
                    'activated_at' => null,
                ], $actor);
            }

            activity()->causedBy($actor)->performedOn($tag)
                ->withProperties(['serial' => $tag->serial, 'reason' => $reason, 'restocked' => $restockAt?->slug])
                ->log('tags.returned');

            return $tag->fresh();
        });
    }

    public function revokeOwnership(LegacyTag $tag, User $actor, string $reason): LegacyTag
    {
        return DB::transaction(function () use ($tag, $actor, $reason) {
            /** @var LegacyTag $tag */
            $tag = LegacyTag::query()->lockForUpdate()->findOrFail($tag->id);

            if ($tag->status !== LegacyTagStatus::Activated) {
                throw new InvalidArgumentException('La pieza no está activada.');
            }

            $this->detachOwner($tag);

            $this->lifecycle->transition($tag, LegacyTagEventType::OwnershipRevoked, LegacyTagStatus::Delivered, [
                'owner_athlete_id' => null,
                'athlete_owned_product_id' => null,
                'activated_at' => null,
            ], $actor, $reason);

            activity()->causedBy($actor)->performedOn($tag)->withProperties(['serial' => $tag->serial, 'reason' => $reason])->log('tags.ownership_revoked');

            return $tag->fresh();
        });
    }

    public function disable(LegacyTag $tag, User $actor, string $reason): LegacyTag
    {
        return DB::transaction(function () use ($tag, $actor, $reason) {
            /** @var LegacyTag $tag */
            $tag = LegacyTag::query()->lockForUpdate()->findOrFail($tag->id);

            if ($tag->status === LegacyTagStatus::Disabled) {
                return $tag;
            }

            if ($tag->status->isSellable()) {
                $tag->loadMissing('productVariant.product', 'location');

                if ($tag->location !== null) {
                    $this->inventory->adjust($tag->productVariant, $tag->location, -1, "Pieza {$tag->serial} desactivada: {$reason}", $actor, 'damage');
                }
            }

            $this->lifecycle->transition($tag, LegacyTagEventType::Disabled, LegacyTagStatus::Disabled, [
                'disabled_reason' => $reason,
                'metadata' => [...($tag->metadata ?? []), 'status_before_disable' => $tag->status->value],
            ], $actor, $reason);

            activity()->causedBy($actor)->performedOn($tag)->withProperties(['serial' => $tag->serial, 'reason' => $reason])->log('tags.disabled');

            return $tag->fresh();
        });
    }

    public function enable(LegacyTag $tag, User $actor): LegacyTag
    {
        return DB::transaction(function () use ($tag, $actor) {
            /** @var LegacyTag $tag */
            $tag = LegacyTag::query()->lockForUpdate()->findOrFail($tag->id);

            if ($tag->status !== LegacyTagStatus::Disabled) {
                return $tag;
            }

            $previous = LegacyTagStatus::tryFrom((string) ($tag->metadata['status_before_disable'] ?? '')) ?? LegacyTagStatus::Delivered;

            if ($previous->isSellable()) {
                $tag->loadMissing('productVariant.product', 'location');

                if ($tag->location !== null) {
                    $this->inventory->adjust($tag->productVariant, $tag->location, 1, "Pieza {$tag->serial} reactivada", $actor, 'correction');
                }
            }

            $this->lifecycle->transition($tag, LegacyTagEventType::Enabled, $previous, ['disabled_reason' => null], $actor);

            activity()->causedBy($actor)->performedOn($tag)->withProperties(['serial' => $tag->serial])->log('tags.enabled');

            return $tag->fresh();
        });
    }

    private function detachOwner(LegacyTag $tag): void
    {
        if ($tag->athlete_owned_product_id !== null) {
            $tag->ownedProduct()->update(['status' => AthleteOwnedProductStatus::Revoked]);
        }

        // The memory stays the athlete's (never deleted) — it just no longer opens from this piece.
        $tag->story()->update(['legacy_tag_id' => null]);
    }
}
