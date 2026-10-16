<?php

namespace App\Actions\Commerce\Inventory;

use App\Enums\InventoryAdjustmentReason;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use InvalidArgumentException;

/**
 * The admin's one way to change stock by hand (spec §12/§14): every mode
 * lands in InventoryService under a row lock and leaves an audited
 * movement with the user and a structured reason. Controllers never write
 * quantities themselves.
 *
 *   receive → +quantity (entrada de mercancía)
 *   adjust  → ±quantity delta
 *   count   → on hand becomes exactly `quantity` (conteo físico)
 */
class AdjustStock
{
    public const array MODES = ['receive', 'adjust', 'count'];

    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(
        ProductVariant $variant,
        ?InventoryLocation $location,
        string $mode,
        int $quantity,
        User $actor,
        InventoryAdjustmentReason $reason,
        ?string $notes = null,
    ): InventoryLevel {
        $location ??= $this->inventory->defaultLocation();

        return match ($mode) {
            'receive' => $this->inventory->receive($variant, $location, abs($quantity), $notes, $actor, $reason->value),
            'adjust' => $this->inventory->adjust($variant, $location, $quantity, $notes, $actor, $reason->value),
            'count' => $this->inventory->setCount($variant, $location, max(0, $quantity), $notes, $actor, $reason->value),
            default => throw new InvalidArgumentException("Modo de ajuste desconocido: {$mode}"),
        };
    }
}
