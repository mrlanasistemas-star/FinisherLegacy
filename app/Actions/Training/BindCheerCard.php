<?php

namespace App\Actions\Training;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\AthleteOwnedProductStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Enums\TagAccessSource;
use App\Exceptions\LegacyTags\TagAlreadyActivatedException;
use App\Exceptions\LegacyTags\TagNotActivatableException;
use App\Models\AthleteOwnedProduct;
use App\Models\CheerLink;
use App\Models\LegacyTag;
use App\Models\User;
use App\Services\LegacyTags\TagLifecycle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of a Tarjeta de ánimo NFC's binding
 * (docs/renovacion/tarjeta-animo.md):
 *
 *   bind()   claims the card for the LINK OWNER the first time (same
 *            ownership rules as ActivateTag: sold / delivered units only,
 *            one owner, AthleteOwnedProduct + `activated` event — but no
 *            Legacy story) and points it at the link. Used by admin
 *            fulfillment (card arrives ready), by the app activation
 *            (ConfirmTagActivation) and by "re-bind" in the app.
 *   unbind() deactivates it: no link → `/t/{code}` shows a friendly
 *            inactive page. The owner keeps the card and can bind it again.
 *
 * Exactly one owner under concurrency: Cache lock + row lock, like ActivateTag.
 */
class BindCheerCard
{
    public function __construct(
        private readonly EnsureAthleteForUser $ensureAthlete,
        private readonly TagLifecycle $lifecycle,
    ) {}

    /**
     * @param  string  $via  fulfillment | app | rebind (event context only)
     *
     * @throws TagNotActivatableException|TagAlreadyActivatedException|ValidationException
     */
    public function bind(LegacyTag $tag, CheerLink $link, ?User $actor, string $via = 'app', TagAccessSource $source = TagAccessSource::Nfc): LegacyTag
    {
        return Cache::lock("legacy-tag-activate:{$tag->id}", 10)->block(5, fn () => DB::transaction(function () use ($tag, $link, $actor, $via, $source) {
            /** @var LegacyTag $tag */
            $tag = LegacyTag::query()->lockForUpdate()->findOrFail($tag->id);
            $link->loadMissing('user');

            if (! $tag->isCheerCard()) {
                throw new TagNotActivatableException('not_cheer_card');
            }

            if ($link->state() === CheerLink::STATE_REVOKED) {
                throw ValidationException::withMessages(['link' => __('Ese enlace ya no está activo. Elige otro o crea uno nuevo.')]);
            }

            $athlete = $this->ensureAthlete->handle($link->user, 'cheer_card');

            if ($tag->status === LegacyTagStatus::Activated) {
                if ($tag->owner_athlete_id !== $athlete->id) {
                    throw new TagAlreadyActivatedException;
                }
            } else {
                $this->claim($tag, $athlete->id, $actor ?? $link->user, $source, $via);
            }

            if ($tag->cheer_link_id !== $link->id) {
                $this->lifecycle->transition($tag, LegacyTagEventType::CheerBound, null, [
                    'cheer_link_id' => $link->id,
                    'cheer_bound_at' => now(),
                ], $actor, null, ['link' => $link->uuid, 'via' => $via]);
            }

            return $tag;
        }));
    }

    /** The owner deactivates the card (no link). Idempotent. */
    public function unbind(LegacyTag $tag, ?User $actor): LegacyTag
    {
        return DB::transaction(function () use ($tag, $actor) {
            /** @var LegacyTag $tag */
            $tag = LegacyTag::query()->lockForUpdate()->findOrFail($tag->id);

            if ($tag->cheer_link_id !== null) {
                $this->lifecycle->transition($tag, LegacyTagEventType::CheerUnbound, null, [
                    'cheer_link_id' => null,
                    'cheer_bound_at' => null,
                ], $actor);
            }

            return $tag;
        });
    }

    /**
     * First binding = activation for the link owner (sold / delivered only;
     * lost, stolen, disabled or warehouse stock never).
     */
    private function claim(LegacyTag $tag, int $athleteId, User $actor, TagAccessSource $source, string $via): void
    {
        match ($tag->status) {
            LegacyTagStatus::Sold, LegacyTagStatus::Delivered => null,
            LegacyTagStatus::Disabled, LegacyTagStatus::Replaced, LegacyTagStatus::Returned,
            LegacyTagStatus::Lost, LegacyTagStatus::Stolen => throw new TagNotActivatableException($tag->status->value),
            default => throw new TagNotActivatableException('not_sold'),
        };

        $tag->loadMissing('productVariant');

        $owned = AthleteOwnedProduct::create([
            'uuid' => (string) Str::uuid(),
            'athlete_id' => $athleteId,
            'order_item_id' => $tag->order_item_id,
            'product_id' => $tag->productVariant->product_id,
            'product_variant_id' => $tag->product_variant_id,
            'serial_code' => $tag->serial,
            'status' => AthleteOwnedProductStatus::Active,
            'acquired_at' => $tag->sold_at ?? now(),
            'activated_at' => now(),
            'metadata' => ['source' => 'cheer_card', 'legacy_tag_uuid' => $tag->uuid],
        ]);

        $this->lifecycle->transition($tag, LegacyTagEventType::Activated, LegacyTagStatus::Activated, [
            'owner_athlete_id' => $athleteId,
            'athlete_owned_product_id' => $owned->id,
            'activated_at' => now(),
        ], $actor, null, ['source' => $source->value, 'via' => $via]);

        activity()->causedBy($actor)->performedOn($tag)
            ->withProperties(['serial' => $tag->serial, 'source' => $source->value, 'via' => $via])
            ->log('tags.cheer_card_activated');
    }
}
