<?php

namespace App\Support\Quotas;

use App\Models\PhotoExpansionPriceStep;
use App\Models\ProductPriceSchedule;

/**
 * The price of an expansion pack for one story at one moment: the
 * schedule row in force, the price step ("bandera") that matched what the
 * story already held, and the resulting amount. Checkout freezes all of
 * it on the order item, so later tariff changes never touch a purchase.
 */
final readonly class ResolvedExpansionPrice
{
    public function __construct(
        public ProductPriceSchedule $schedule,
        public ?PhotoExpansionPriceStep $step,
        public int $amountMinor,
        /** What the story held when the price was resolved. */
        public int $heldUnits,
        /** Inclusive lower bound of the matched band. */
        public int $fromUnits,
        /** Inclusive upper bound of the matched band (null = open-ended). */
        public ?int $toUnits,
    ) {}

    public static function for(ProductPriceSchedule $schedule, int $heldUnits): self
    {
        $steps = $schedule->steps()->get();
        $matched = null;
        $next = null;

        foreach ($steps as $step) {
            if ($step->from_units <= $heldUnits) {
                $matched = $step;
            } elseif ($next === null) {
                $next = $step;
            }
        }

        return new self(
            $schedule,
            $matched,
            $matched !== null ? (int) $matched->amount_minor : (int) $schedule->amount_minor,
            $heldUnits,
            $matched !== null ? (int) $matched->from_units : 0,
            $next !== null ? $next->from_units - 1 : null,
        );
    }

    public function hasSteps(): bool
    {
        return $this->step !== null || $this->toUnits !== null;
    }

    /** Store product for the matched step, else null (use the pack's own). */
    public function storeProductId(string $platform): ?string
    {
        return $this->step?->storeProductId($platform);
    }

    /**
     * @return null|array{id: ?int, from: int, to: ?int, held: int}
     */
    public function stepPayload(): ?array
    {
        return $this->hasSteps()
            ? ['id' => $this->step?->id, 'from' => $this->fromUnits, 'to' => $this->toUnits, 'held' => $this->heldUnits]
            : null;
    }
}
