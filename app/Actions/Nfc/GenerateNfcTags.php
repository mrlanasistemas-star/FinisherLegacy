<?php

namespace App\Actions\Nfc;

use App\Enums\NfcTagStatus;
use App\Models\NfcTag;
use App\Models\User;
use App\Support\Nfc\NfcToken;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bulk-creates blank tags for the encoder (docs/NFC.md §Encoder): each gets
 * a fresh random token; staff export the batch as CSV of URLs and write
 * them to the physical chips.
 */
class GenerateNfcTags
{
    /**
     * @return Collection<int, NfcTag>
     */
    public function handle(int $count, User $actor, ?string $batchCode = null): Collection
    {
        $max = (int) config('nfc.max_batch', 500);

        if ($count < 1 || $count > $max) {
            throw new InvalidArgumentException("Puedes generar entre 1 y {$max} etiquetas por lote.");
        }

        $batchCode = $batchCode !== null && trim($batchCode) !== ''
            ? mb_substr(trim($batchCode), 0, 40)
            : 'L'.now()->format('ymd-His');

        // One audit entry for the whole batch (below), not one per blank tag.
        $tags = activity()->withoutLogging(fn () => DB::transaction(function () use ($count, $actor, $batchCode) {
            $tags = new Collection;

            for ($i = 0; $i < $count; $i++) {
                $tags->push(NfcTag::create([
                    'token' => NfcToken::generate(),
                    'status' => NfcTagStatus::Available,
                    'batch_code' => $batchCode,
                    'created_by_user_id' => $actor->id,
                ]));
            }

            return $tags;
        }));

        activity()
            ->causedBy($actor)
            ->withProperties(['count' => $count, 'batch_code' => $batchCode])
            ->log('nfc.tags.generated');

        return $tags;
    }
}
