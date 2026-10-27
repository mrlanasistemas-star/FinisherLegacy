<?php

namespace App\Actions\LegacyTags;

use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use App\Services\LegacyTags\TagLifecycle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Central → ally / event / stand (and back): moves in-stock tags between
 * locations. Either explicit units (scanned codes) or "N of this SKU from
 * there" (the oldest received first). Aggregate stock moves through
 * InventoryService::transfer so both kardexes explain the change.
 */
class TransferTags
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly TagLifecycle $lifecycle,
    ) {}

    /**
     * @param  list<int>  $tagIds
     * @return Collection<int, LegacyTag>
     */
    public function units(array $tagIds, InventoryLocation $to, User $actor, ?string $notes = null): Collection
    {
        return DB::transaction(function () use ($tagIds, $to, $actor, $notes) {
            $tags = LegacyTag::query()->whereIn('id', $tagIds)->lockForUpdate()->get();

            if ($tags->count() !== count(array_unique($tagIds))) {
                throw new InvalidArgumentException('Alguna pieza no existe.');
            }

            $bad = $tags->first(fn (LegacyTag $t) => $t->status !== LegacyTagStatus::InStock);

            if ($bad !== null) {
                throw new InvalidArgumentException("La pieza {$bad->serial} no está en inventario ({$bad->status->label()}).");
            }

            return $this->move($tags, $to, $actor, $notes);
        });
    }

    /**
     * @return Collection<int, LegacyTag>
     */
    public function quantity(ProductVariant $variant, InventoryLocation $from, InventoryLocation $to, int $quantity, User $actor, ?string $notes = null): Collection
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($variant, $from, $to, $quantity, $actor, $notes) {
            $tags = LegacyTag::query()
                ->where('product_variant_id', $variant->id)
                ->where('inventory_location_id', $from->id)
                ->where('status', LegacyTagStatus::InStock->value)
                ->orderBy('received_at')->orderBy('id')
                ->limit($quantity)
                ->lockForUpdate()->get();

            if ($tags->count() < $quantity) {
                throw new InvalidArgumentException("Solo hay {$tags->count()} piezas disponibles de {$variant->sku} en {$from->name}.");
            }

            return $this->move($tags, $to, $actor, $notes);
        });
    }

    /**
     * @param  Collection<int, LegacyTag>  $tags
     * @return Collection<int, LegacyTag>
     */
    private function move(Collection $tags, InventoryLocation $to, User $actor, ?string $notes): Collection
    {
        $tags->loadMissing('productVariant.product', 'location');

        $groups = $tags->groupBy(fn (LegacyTag $t) => $t->product_variant_id.':'.$t->inventory_location_id);

        foreach ($groups as $group) {
            /** @var LegacyTag $first */
            $first = $group->first();

            if ($first->inventory_location_id === $to->id) {
                continue;
            }

            $this->inventory->transfer($first->productVariant, $first->location, $to, $group->count(), $actor, $notes);

            foreach ($group as $tag) {
                $this->lifecycle->transition($tag, LegacyTagEventType::Transferred, null, ['inventory_location_id' => $to->id], $actor, $notes);
            }
        }

        activity()->causedBy($actor)
            ->withProperties(['to' => $to->slug, 'count' => $tags->count(), 'serials' => $tags->pluck('serial')->take(50)->all()])
            ->log('tags.transferred');

        return $tags->map->fresh();
    }
}
