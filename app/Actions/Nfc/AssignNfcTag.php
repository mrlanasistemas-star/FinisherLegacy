<?php

namespace App\Actions\Nfc;

use App\Enums\NfcTagStatus;
use App\Enums\NfcTargetType;
use App\Exceptions\Nfc\NfcTagInUseException;
use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use App\Models\Event;
use App\Models\NfcAssignment;
use App\Models\NfcTag;
use App\Models\Plate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff-side: attach a blank tag to what it will open (docs/NFC.md
 * §Asignación). The public caller can never choose or change a target —
 * only this audited action (staff) and ClaimNfcTag/TransferNfcAssignment
 * (owner) ever write an assignment.
 */
class AssignNfcTag
{
    /**
     * "NFC asignado" step of Preparación y entrega. If the plate's Legacy
     * Code was already claimed, the tag is born connected to that athlete.
     * `$replace` swaps a damaged chip: the old tag is closed and disabled,
     * ownership carries over.
     */
    public function toPlate(NfcTag $tag, Plate $plate, User $actor, bool $replace = false): NfcAssignment
    {
        return DB::transaction(function () use ($tag, $plate, $actor, $replace) {
            $tag = $this->lockAvailable($tag);
            $plate->loadMissing('legacyCode');

            $previous = NfcAssignment::query()->open()->where('plate_id', $plate->id)->lockForUpdate()->first();

            if ($previous !== null && ! $replace) {
                throw new NfcTagInUseException('Esta placa ya tiene una etiqueta NFC.');
            }

            $owner = $previous?->athlete_id;
            $activatedAt = $previous?->activated_at;

            if ($owner === null && $plate->legacyCode?->claimed_by_user_id !== null) {
                $owner = $plate->athlete_id;
                $activatedAt = $owner !== null ? now() : null;
            }

            if ($previous !== null) {
                $this->close($previous, 'replaced');
                $previous->tag()->update(['status' => NfcTagStatus::Disabled]);
            }

            return $this->open($tag, $actor, [
                'target_type' => NfcTargetType::LegacyPlate,
                'plate_id' => $plate->id,
                'athlete_id' => $owner,
                'activated_at' => $activatedAt,
            ]);
        });
    }

    /**
     * A unit sold/handed out without a known owner (gift, event-day sale):
     * the first athlete to tap and claim it gets an AthleteOwnedProduct.
     */
    public function toProduct(NfcTag $tag, Product $product, ?ProductVariant $variant, User $actor): NfcAssignment
    {
        return DB::transaction(function () use ($tag, $product, $variant, $actor) {
            $tag = $this->lockAvailable($tag);

            return $this->open($tag, $actor, [
                'target_type' => NfcTargetType::Product,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ]);
        });
    }

    /**
     * A unit that already belongs to an athlete (fulfilled order) — the tag
     * is connected immediately to its owner.
     */
    public function toOwnedProduct(NfcTag $tag, AthleteOwnedProduct $owned, User $actor): NfcAssignment
    {
        return DB::transaction(function () use ($tag, $owned, $actor) {
            $tag = $this->lockAvailable($tag);

            if (NfcAssignment::query()->open()->where('athlete_owned_product_id', $owned->id)->lockForUpdate()->exists()) {
                throw new NfcTagInUseException('Este producto ya tiene una etiqueta NFC.');
            }

            return $this->open($tag, $actor, [
                'target_type' => NfcTargetType::Product,
                'product_id' => $owned->product_id,
                'product_variant_id' => $owned->product_variant_id,
                'athlete_owned_product_id' => $owned->id,
                'athlete_id' => $owned->athlete_id,
                'activated_at' => now(),
            ]);
        });
    }

    /** Venue/poster tags: ownerless, active from the start. */
    public function toEvent(NfcTag $tag, Event $event, User $actor): NfcAssignment
    {
        return DB::transaction(function () use ($tag, $event, $actor) {
            $tag = $this->lockAvailable($tag);

            return $this->open($tag, $actor, [
                'target_type' => NfcTargetType::Event,
                'event_id' => $event->id,
                'activated_at' => now(),
            ]);
        });
    }

    /** Profile keychain — pre-connected when the owner is known. */
    public function toProfile(NfcTag $tag, ?Athlete $owner, User $actor): NfcAssignment
    {
        return DB::transaction(function () use ($tag, $owner, $actor) {
            $tag = $this->lockAvailable($tag);

            return $this->open($tag, $actor, [
                'target_type' => NfcTargetType::Profile,
                'athlete_id' => $owner?->id,
                'activated_at' => $owner !== null ? now() : null,
            ]);
        });
    }

    /**
     * Undo a mistaken assignment: the tag goes back to the available pool.
     */
    public function release(NfcAssignment $assignment, User $actor): void
    {
        DB::transaction(function () use ($assignment, $actor) {
            $assignment = NfcAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ($assignment->ended_at !== null) {
                return;
            }

            $this->close($assignment, 'unassigned');
            $assignment->tag()->where('status', NfcTagStatus::Assigned)->update(['status' => NfcTagStatus::Available]);

            activity()->causedBy($actor)->performedOn($assignment)->log('nfc.unassigned');
        });
    }

    private function lockAvailable(NfcTag $tag): NfcTag
    {
        /** @var NfcTag $locked */
        $locked = NfcTag::query()->lockForUpdate()->findOrFail($tag->id);

        $hasOpen = NfcAssignment::query()->open()->where('nfc_tag_id', $locked->id)->exists();

        if ($locked->status !== NfcTagStatus::Available || $hasOpen) {
            throw new NfcTagInUseException;
        }

        return $locked;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function open(NfcTag $tag, User $actor, array $attributes): NfcAssignment
    {
        $assignment = NfcAssignment::create([
            ...$attributes,
            'nfc_tag_id' => $tag->id,
            'assigned_by_user_id' => $actor->id,
            'assigned_at' => now(),
        ]);

        $tag->update([
            'status' => NfcTagStatus::Assigned,
            'activated_at' => $tag->activated_at ?? now(),
        ]);

        activity()
            ->causedBy($actor)
            ->performedOn($assignment)
            ->withProperties(['tag' => $tag->shortToken(), 'target_type' => $assignment->target_type->value])
            ->log('nfc.assigned');

        return $assignment;
    }

    private function close(NfcAssignment $assignment, string $reason): void
    {
        $assignment->update(['ended_at' => now(), 'end_reason' => $reason]);
    }
}
