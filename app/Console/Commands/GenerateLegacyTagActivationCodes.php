<?php

namespace App\Console\Commands;

use App\Actions\LegacyTags\GenerateActivationCodes;
use App\Enums\LegacyTagStatus;
use App\Models\InventoryLot;
use App\Models\LegacyTag;
use Illuminate\Console\Command;

/**
 * Backfills the secret activation code (proof of purchase card) for tags
 * that do not have one yet — units still in our hands or sold but not
 * activated. Never rotates an existing code. The codes in clear go ONLY to
 * the private CSV written here (docs/renovacion/manual-activacion-nfc.md).
 */
class GenerateLegacyTagActivationCodes extends Command
{
    protected $signature = 'legacy-tags:generate-activation-codes {--lot= : Lot code (LOT-2611-001) or id}';

    protected $description = 'Generate missing activation codes (proof of purchase) for in-stock / sold Finisher Legacy Tags and export them to a private CSV.';

    public function handle(GenerateActivationCodes $generator): int
    {
        $query = LegacyTag::query()
            ->whereNull('activation_code_hash')
            ->whereIn('status', [
                LegacyTagStatus::Manufactured->value,
                LegacyTagStatus::InStock->value,
                LegacyTagStatus::Reserved->value,
                LegacyTagStatus::Sold->value,
                LegacyTagStatus::Delivered->value,
            ])
            ->orderBy('id');

        $label = 'todas';
        $lotOption = $this->option('lot');

        if (is_string($lotOption) && $lotOption !== '') {
            $lot = InventoryLot::query()->where('code', $lotOption)->first()
                ?? (ctype_digit($lotOption) ? InventoryLot::query()->find((int) $lotOption) : null);

            if ($lot === null) {
                $this->error("No existe el lote {$lotOption}.");

                return self::FAILURE;
            }

            $query->where('inventory_lot_id', $lot->id);
            $label = 'lote-'.$lot->code;
        }

        $rows = [];
        $query->chunkById(500, function ($tags) use ($generator, &$rows) {
            array_push($rows, ...$generator->handle($tags));
        });

        if ($rows === []) {
            $this->info('No hay piezas sin código de activación.');

            return self::SUCCESS;
        }

        $path = $generator->writeCsv($rows, $label);

        activity()->withProperties(['count' => count($rows), 'path' => $path, 'lot' => $label])
            ->log('tags.activation_codes_generated');

        $this->info(count($rows).' códigos generados.');
        $this->line('CSV (privado, bórralo después de imprimir): storage/app/private/'.$path);

        return self::SUCCESS;
    }
}
