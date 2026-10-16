<?php

namespace App\Actions\Nfc;

use App\Enums\NfcTagStatus;
use App\Models\NfcAssignment;
use App\Models\NfcTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Disable a lost/damaged/revoked tag (tapping it then shows an honest
 * page) or bring it back. The assignment is left intact, so re-enabling
 * restores exactly what the tag opened before. Audited.
 */
class SetNfcTagStatus
{
    public function disable(NfcTag $tag, User $actor): NfcTag
    {
        return $this->apply($tag, $actor, fn () => NfcTagStatus::Disabled, 'nfc.disabled');
    }

    public function enable(NfcTag $tag, User $actor): NfcTag
    {
        return $this->apply(
            $tag,
            $actor,
            fn (NfcTag $locked) => NfcAssignment::query()->open()->where('nfc_tag_id', $locked->id)->exists()
                ? NfcTagStatus::Assigned
                : NfcTagStatus::Available,
            'nfc.enabled',
        );
    }

    /**
     * @param  callable(NfcTag): NfcTagStatus  $status
     */
    private function apply(NfcTag $tag, User $actor, callable $status, string $event): NfcTag
    {
        return DB::transaction(function () use ($tag, $actor, $status, $event) {
            /** @var NfcTag $locked */
            $locked = NfcTag::query()->lockForUpdate()->findOrFail($tag->id);
            $locked->update(['status' => $status($locked)]);

            activity()->causedBy($actor)->performedOn($locked)->withProperties(['tag' => $locked->shortToken()])->log($event);

            return $locked;
        });
    }
}
