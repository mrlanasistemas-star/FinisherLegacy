<?php

namespace App\Services\Commerce;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Collection;

/**
 * Picks the best running "oferta" for a product — the one place offers
 * are matched, used by ResolveProductPrice (checkout, cart) and the store
 * cards. Scoped singleton: running offers + their product ids are read
 * once per request.
 *
 * "Todos los productos" never touches the Legacy Plate (event-priced
 * presale) nor photographers' photos (their price, their money) — those
 * only get an offer when explicitly selected.
 */
class PromotionResolver
{
    /** @var Collection<int, array{promotion: Promotion, product_ids: list<int>}>|null */
    private ?Collection $running = null;

    public function bestFor(Product $product, int $amountMinor): ?Promotion
    {
        $best = null;
        $bestDiscount = 0;

        foreach ($this->running() as ['promotion' => $promotion, 'product_ids' => $ids]) {
            $applies = $promotion->applies_to === 'products'
                ? in_array($product->id, $ids, true)
                : ! in_array($product->type, [ProductType::LegacyPlate, ProductType::DigitalPhoto], true);

            if (! $applies) {
                continue;
            }

            $discount = $promotion->discountFor($amountMinor);

            if ($discount > $bestDiscount) {
                $best = $promotion;
                $bestDiscount = $discount;
            }
        }

        return $best;
    }

    public function forget(): void
    {
        $this->running = null;
    }

    /**
     * @return Collection<int, array{promotion: Promotion, product_ids: list<int>}>
     */
    private function running(): Collection
    {
        return $this->running ??= Promotion::query()
            ->running()
            ->with('products:id')
            ->get()
            ->map(fn (Promotion $promotion) => [
                'promotion' => $promotion,
                'product_ids' => $promotion->products->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ]);
    }
}
