<?php

namespace App\Actions\Nfc;

use App\Exceptions\Nfc\NfcTransferInvalidException;
use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use App\Models\NfcAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hand a connected product (or profile keychain) to another athlete —
 * DOMAIN ONLY, no marketplace and no UI yet (spec §34, docs/NFC.md
 * §Transferencia). Two steps:
 *
 * 1. `request()` — the current owner names the recipient; a random
 *    confirmation token is returned once (only its hash is stored) with a
 *    `nfc.transfer_ttl_hours` expiry.
 * 2. `confirm()` — the recipient presents the token. The current
 *    assignment is closed (`end_reason = transferred`) and a new one opens
 *    for the recipient, so the ownership history stays intact. A linked
 *    AthleteOwnedProduct moves with it.
 *
 * Legacy Plates and event tags are never transferable.
 */
class TransferNfcAssignment
{
    public function request(NfcAssignment $assignment, User $owner, Athlete $recipient): string
    {
        return DB::transaction(function () use ($assignment, $owner, $recipient) {
            /** @var NfcAssignment $assignment */
            $assignment = NfcAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $ownerAthlete = $owner->athlete()->first();

            if ($assignment->ended_at !== null
                || ! $assignment->isClaimed()
                || ! $assignment->target_type->isTransferable()
                || $ownerAthlete === null
                || $assignment->athlete_id !== $ownerAthlete->id) {
                throw new NfcTransferInvalidException('Solo el dueño puede transferir este producto.');
            }

            if ($recipient->id === $ownerAthlete->id) {
                throw new NfcTransferInvalidException('Elige a otra persona para transferir.');
            }

            $token = Str::random(40);

            $assignment->update([
                'transfer_to_athlete_id' => $recipient->id,
                'transfer_token_hash' => hash('sha256', $token),
                'transfer_expires_at' => now()->addHours((int) config('nfc.transfer_ttl_hours', 72)),
            ]);

            activity()
                ->causedBy($owner)
                ->performedOn($assignment)
                ->withProperties(['to_athlete_id' => $recipient->id])
                ->log('nfc.transfer.requested');

            return $token;
        });
    }

    public function confirm(NfcAssignment $assignment, string $token, User $recipientUser): NfcAssignment
    {
        return DB::transaction(function () use ($assignment, $token, $recipientUser) {
            /** @var NfcAssignment $assignment */
            $assignment = NfcAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $recipient = $recipientUser->athlete()->first();

            $valid = $assignment->ended_at === null
                && $assignment->transfer_token_hash !== null
                && $assignment->transfer_expires_at?->isFuture()
                && $recipient !== null
                && $assignment->transfer_to_athlete_id === $recipient->id
                && hash_equals($assignment->transfer_token_hash, hash('sha256', $token));

            if (! $valid) {
                throw new NfcTransferInvalidException;
            }

            $fromAthleteId = $assignment->athlete_id;

            $assignment->update([
                'ended_at' => now(),
                'end_reason' => 'transferred',
                'transfer_token_hash' => null,
                'transfer_expires_at' => null,
            ]);

            $next = NfcAssignment::create([
                'nfc_tag_id' => $assignment->nfc_tag_id,
                'target_type' => $assignment->target_type,
                'plate_id' => $assignment->plate_id,
                'product_id' => $assignment->product_id,
                'product_variant_id' => $assignment->product_variant_id,
                'athlete_owned_product_id' => $assignment->athlete_owned_product_id,
                'event_id' => $assignment->event_id,
                'athlete_id' => $recipient->id,
                'assigned_by_user_id' => $assignment->assigned_by_user_id,
                'assigned_at' => now(),
                'activated_at' => now(),
            ]);

            if ($assignment->athlete_owned_product_id !== null) {
                AthleteOwnedProduct::query()
                    ->whereKey($assignment->athlete_owned_product_id)
                    ->lockForUpdate()
                    ->first()
                    ?->update(['athlete_id' => $recipient->id]);
            }

            activity()
                ->causedBy($recipientUser)
                ->performedOn($next)
                ->withProperties(['from_athlete_id' => $fromAthleteId, 'to_athlete_id' => $recipient->id, 'previous_assignment_id' => $assignment->id])
                ->log('nfc.transfer.completed');

            return $next;
        });
    }

    public function cancel(NfcAssignment $assignment, User $owner): void
    {
        $ownerAthlete = $owner->athlete()->first();

        if ($ownerAthlete === null || $assignment->athlete_id !== $ownerAthlete->id) {
            throw new NfcTransferInvalidException('Solo el dueño puede cancelar la transferencia.');
        }

        $assignment->update(['transfer_to_athlete_id' => null, 'transfer_token_hash' => null, 'transfer_expires_at' => null]);

        activity()->causedBy($owner)->performedOn($assignment)->log('nfc.transfer.cancelled');
    }
}
