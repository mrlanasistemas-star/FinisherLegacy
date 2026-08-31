<?php

namespace App\Actions\Commerce;

use App\Enums\ProductPriceType;
use App\Enums\ProductType;
use App\Exceptions\PriceNotAvailableException;
use App\Models\EventEdition;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProductVariant;
use App\Support\Commerce\ResolvedPrice;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single place price is decided — Web and API both call this, never a
 * client-supplied amount (brief §42-§44/§93/§202-§203). Legacy Plate
 * requires an active event-scoped schedule and never falls back to a base
 * price; general store products fall back to the variant's
 * base_price_minor when no schedule applies.
 */
class ResolveProductPrice
{
    /**
     * Higher wins a tie among schedules simultaneously active at $at —
     * a real, explicit rule (not incidental row order, brief §43).
     *
     * @var array<string, int>
     */
    private const TYPE_RANK = [
        'event_day' => 4,
        'kit_pickup' => 3,
        'early_presale' => 2,
        'standard' => 1,
    ];

    public function handle(Product $product, ?ProductVariant $variant = null, ?EventEdition $eventEdition = null, ?DateTimeInterface $at = null): ResolvedPrice
    {
        $at = Carbon::instance($at ?? now());

        $candidates = ProductPriceSchedule::query()
            ->where('product_id', $product->id)
            ->where('active', true)
            ->where(function ($query) use ($variant) {
                $query->whereNull('product_variant_id');

                if ($variant !== null) {
                    $query->orWhere('product_variant_id', $variant->id);
                }
            })
            ->where(function ($query) use ($eventEdition) {
                $query->whereNull('event_edition_id');

                if ($eventEdition !== null) {
                    $query->orWhere('event_edition_id', $eventEdition->id);
                }
            })
            ->get()
            ->filter(fn (ProductPriceSchedule $schedule) => $schedule->isActiveAt($at));

        $best = $this->pickBest($candidates, $variant);

        if ($best !== null) {
            return new ResolvedPrice($best->amount_minor, $best->currency, $best->price_type, $best->id, false);
        }

        if ($product->type === ProductType::LegacyPlate) {
            throw new PriceNotAvailableException;
        }

        if ($variant === null) {
            throw new PriceNotAvailableException;
        }

        return new ResolvedPrice($variant->base_price_minor, $variant->currency, ProductPriceType::Standard, null, true);
    }

    /**
     * @param  Collection<int, ProductPriceSchedule>  $candidates
     */
    private function pickBest($candidates, ?ProductVariant $variant): ?ProductPriceSchedule
    {
        return $candidates
            ->sortByDesc(fn (ProductPriceSchedule $s) => $this->score($s, $variant))
            ->first();
    }

    /**
     * Zero-padded, lexicographically-sortable composite key — avoids
     * relying on PHP's element-wise array comparison for tie-breaking.
     */
    private function score(ProductPriceSchedule $schedule, ?ProductVariant $variant): string
    {
        $variantSpecific = $schedule->product_variant_id !== null && $variant !== null && $schedule->product_variant_id === $variant->id ? 1 : 0;
        $eventSpecific = $schedule->event_edition_id !== null ? 1 : 0;
        $typeRank = self::TYPE_RANK[$schedule->price_type->value];

        return sprintf('%d%d%d%010d', $variantSpecific, $eventSpecific, $typeRank, $schedule->id);
    }
}
