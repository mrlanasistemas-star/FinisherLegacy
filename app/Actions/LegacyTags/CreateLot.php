<?php

namespace App\Actions\LegacyTags;

use App\Enums\InventoryLotStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Models\InventoryLot;
use App\Models\LegacyTag;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\LegacyTags\TagLifecycle;
use App\Support\LegacyTags\TagCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Registers a manufacturing lot and generates one `manufactured` tag per
 * piece — the codes must exist BEFORE fabrication so the factory can encode
 * the NFC chips (export: tags CSV of the lot). The tag has no printed QR.
 * Stock is not counted until the lot is received (ReceiveLot).
 *
 * Each tag also gets its secret activation code (proof of purchase printed
 * on the card inside the packaging; only its HMAC is stored). The codes in
 * clear are written ONCE to a private CSV for the printer
 * (storage/app/private/exports/activation-codes/…, path in the activity
 * log `tags.lot.activation_codes_generated` and in `$lastActivationCodesCsv`).
 */
class CreateLot
{
    public const int MAX_QUANTITY = 5000;

    /** Private-disk path of the activation-codes CSV written by the last handle() call. */
    public ?string $lastActivationCodesCsv = null;

    public function __construct(
        private readonly TagLifecycle $lifecycle,
        private readonly GenerateActivationCodes $activationCodes,
    ) {}

    /**
     * @param  array{supplier_id?: ?int, inventory_location_id?: ?int, unit_cost_minor?: ?int, currency?: ?string, ordered_at?: mixed, reference?: ?string, notes?: ?string, code?: ?string}  $data
     */
    public function handle(ProductVariant $variant, int $quantity, array $data, User $actor): InventoryLot
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException('La cantidad del lote debe estar entre 1 y '.self::MAX_QUANTITY.'.');
        }

        $this->lastActivationCodesCsv = null;

        [$lot, $codes] = DB::transaction(function () use ($variant, $quantity, $data, $actor) {
            $lot = InventoryLot::create([
                'uuid' => (string) Str::uuid(),
                'code' => $data['code'] ?? $this->nextLotCode(),
                'product_variant_id' => $variant->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'inventory_location_id' => $data['inventory_location_id'] ?? null,
                'quantity' => $quantity,
                'unit_cost_minor' => $data['unit_cost_minor'] ?? $variant->cost_minor,
                'currency' => $data['currency'] ?? $variant->currency ?? 'MXN',
                'status' => InventoryLotStatus::Ordered,
                'ordered_at' => $data['ordered_at'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            // Serial sequence is global and monotonic (internal only).
            $sequence = (int) LegacyTag::query()->lockForUpdate()->max('id');

            $tags = [];

            for ($i = 1; $i <= $quantity; $i++) {
                $tags[] = $tag = new LegacyTag([
                    'uuid' => (string) Str::uuid(),
                    'serial' => TagCode::serial($sequence + $i),
                    'public_code' => TagCode::generate(),
                    'product_variant_id' => $variant->id,
                    'inventory_lot_id' => $lot->id,
                ]);
                $tag->status = LegacyTagStatus::Manufactured;

                $this->lifecycle->transition($tag, LegacyTagEventType::Created, LegacyTagStatus::Manufactured, [], $actor, null, ['lot' => $lot->code]);
            }

            activity()->causedBy($actor)->performedOn($lot)
                ->withProperties(['quantity' => $quantity, 'sku' => $variant->sku])
                ->log('tags.lot.created');

            return [$lot, $this->activationCodes->handle($tags)];
        });

        // After commit: the codes in clear leave the process exactly once.
        $this->lastActivationCodesCsv = $this->activationCodes->writeCsv($codes, 'lote-'.$lot->code);

        activity()->causedBy($actor)->performedOn($lot)
            ->withProperties(['count' => count($codes), 'path' => $this->lastActivationCodesCsv])
            ->log('tags.lot.activation_codes_generated');

        return $lot;
    }

    private function nextLotCode(): string
    {
        $prefix = 'LOT-'.now()->format('ym').'-';
        $count = InventoryLot::query()->where('code', 'like', $prefix.'%')->count();

        do {
            $code = $prefix.str_pad((string) ++$count, 3, '0', STR_PAD_LEFT);
        } while (InventoryLot::query()->where('code', $code)->exists());

        return $code;
    }
}
