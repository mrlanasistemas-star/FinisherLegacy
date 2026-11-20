<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Actions\Commerce\ResolveProductPrice;
use App\Actions\Training\BindCheerCard;
use App\Enums\LegacyTagPurpose;
use App\Enums\TagAccessSource;
use App\Exceptions\LegacyTags\TagAlreadyActivatedException;
use App\Exceptions\LegacyTags\TagNotActivatableException;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\LegacyTag;
use App\Models\User;
use App\Support\Training\CheerCards;
use App\Support\Training\CheerPresenter;
use App\Support\Training\ResolvesOwnCheers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * `/api/v1/training/cards` — the athlete's Tarjetas de ánimo NFC
 * (docs/renovacion/tarjeta-animo.md):
 *
 *   GET    /training/cards              cards I own (+ meta.offer: the product to buy one)
 *   GET    /training/card-offer         the card product, price and variant to add to the cart
 *   PUT    /training/cards/{uuid}       {link_uuid} → bind / re-bind to one of MY links
 *   DELETE /training/cards/{uuid}/link  deactivate (no link → friendly inactive page)
 *
 * Owner-only: another athlete's card or link is a 404, never a 403.
 * Activating a blank card goes through the NFC activation API
 * (`purpose: cheer` + `cheer_link_uuid`, LegacyTagActivationController).
 */
class TrainingCardController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;
    use ResolvesOwnCheers;

    public function index(Request $request, CheerPresenter $presenter, ResolveProductPrice $prices): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $athleteId = $user->athlete()->value('id');

        $cards = $athleteId === null ? collect() : LegacyTag::query()
            ->where('purpose', LegacyTagPurpose::Cheer->value)
            ->where('owner_athlete_id', $athleteId)
            ->with('cheerLink')
            ->latest('activated_at')
            ->limit(100)
            ->get();

        return $this->respond(
            $cards->map(fn (LegacyTag $card) => $presenter->card($card))->values(),
            meta: ['offer' => $this->offerData($prices)],
        );
    }

    public function offer(ResolveProductPrice $prices): JsonResponse
    {
        return $this->respond($this->offerData($prices));
    }

    public function update(Request $request, string $uuid, BindCheerCard $bind, CheerPresenter $presenter): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $request->validate(['link_uuid' => ['required', 'uuid']]);
        $card = $this->ownCard($user, $uuid);
        $link = $this->ownLink($user, $data['link_uuid']);

        try {
            $card = $bind->bind($card, $link, $user, 'rebind', TagAccessSource::Link);
        } catch (TagAlreadyActivatedException|TagNotActivatableException $e) {
            throw ValidationException::withMessages(['link_uuid' => $e->getMessage()]);
        }

        return $this->respond($presenter->card($card->fresh(['cheerLink'])), __('Listo: la tarjeta ahora abre «:title».', ['title' => $link->title]));
    }

    public function unbind(Request $request, string $uuid, BindCheerCard $bind, CheerPresenter $presenter): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $card = $bind->unbind($this->ownCard($user, $uuid), $user);

        return $this->respond($presenter->card($card->fresh(['cheerLink'])), __('Tarjeta desactivada. Puedes volver a vincularla cuando quieras.'));
    }

    private function ownCard(User $user, string $uuid): LegacyTag
    {
        return LegacyTag::query()
            ->where('uuid', $uuid)
            ->where('purpose', LegacyTagPurpose::Cheer->value)
            ->where('owner_athlete_id', $user->athlete()->value('id') ?? 0)
            ->firstOrFail();
    }

    /**
     * The card product as the app needs it to add one to the cart
     * (`POST /cart/items` with `product_variant_uuid` + `cheer_link_uuid`).
     *
     * @return array{available: bool, product_slug: string, product_variant_uuid: string|null, name: string|null, price_minor: int|null, currency: string|null, image_url: string|null}
     */
    private function offerData(ResolveProductPrice $prices): array
    {
        $product = CheerCards::product();
        $variant = $product?->variants->firstWhere('active', true);
        $price = null;

        if ($product !== null && $variant !== null) {
            try {
                $price = $prices->handle($product, $variant);
            } catch (Throwable) {
                $price = null;
            }
        }

        return [
            // Sellable only when priced AND in stock, so the app never offers a
            // "buy" button that the cart would then reject as sold out.
            'available' => $variant !== null && $price !== null && $price->amountMinor > 0 && $variant->isAvailable(),
            'in_stock' => $variant?->isAvailable() ?? false,
            'product_slug' => CheerCards::PRODUCT_SLUG,
            'product_variant_uuid' => $variant?->uuid,
            'name' => $product?->name,
            'price_minor' => $price?->amountMinor,
            'currency' => $price?->currency,
            'image_url' => $product?->primaryImageUrl(),
        ];
    }
}
