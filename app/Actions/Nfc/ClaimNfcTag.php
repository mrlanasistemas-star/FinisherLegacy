<?php

namespace App\Actions\Nfc;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\AthleteOwnedProductStatus;
use App\Enums\NfcTagStatus;
use App\Enums\NfcTargetType;
use App\Exceptions\LegacyCodeClaimConflictException;
use App\Exceptions\LegacyCodeUnavailableException;
use App\Exceptions\Nfc\NfcAlreadyClaimedException;
use App\Exceptions\Nfc\NfcNotClaimableException;
use App\Models\AthleteOwnedProduct;
use App\Models\NfcAssignment;
use App\Models\NfcTag;
use App\Models\User;
use App\Services\ClaimLegacyCodeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tap → activation page → "Conectar a mi Legacy" (docs/NFC.md §Claim).
 * One owner per tag, guaranteed by a Cache lock per tag plus row locks
 * (`lockForUpdate`) inside the transaction — two simultaneous claims
 * resolve to exactly one owner; the loser gets NFC_ALREADY_CLAIMED.
 *
 * Legacy Plates delegate to ClaimLegacyCodeService (the single plate-claim
 * implementation: athlete identity resolution, medal, audit) so /l/{code}
 * and /n/{token} can never disagree.
 */
class ClaimNfcTag
{
    public function __construct(
        private readonly EnsureAthleteForUser $ensureAthlete,
        private readonly ClaimLegacyCodeService $legacyClaims,
    ) {}

    public function handle(NfcTag $tag, User $user, ?string $ip = null): NfcAssignment
    {
        return Cache::lock("nfc-claim:{$tag->id}", 10)->block(5, function () use ($tag, $user, $ip) {
            return DB::transaction(function () use ($tag, $user, $ip) {
                /** @var NfcTag $tag */
                $tag = NfcTag::query()->lockForUpdate()->findOrFail($tag->id);

                if ($tag->status === NfcTagStatus::Disabled) {
                    throw new NfcNotClaimableException('Esta etiqueta está desactivada.');
                }

                /** @var NfcAssignment|null $assignment */
                $assignment = NfcAssignment::query()->open()->where('nfc_tag_id', $tag->id)->lockForUpdate()->first();

                if ($assignment === null || ! $assignment->target_type->isOwnable()) {
                    throw new NfcNotClaimableException;
                }

                $athlete = $this->ensureAthlete->handle($user, 'nfc_claim');

                if ($assignment->isClaimed()) {
                    if ($assignment->athlete_id === $athlete->id) {
                        return $assignment; // idempotent re-claim by the owner
                    }

                    throw new NfcAlreadyClaimedException;
                }

                $attributes = match ($assignment->target_type) {
                    NfcTargetType::LegacyPlate => $this->claimPlate($assignment, $user, $ip),
                    NfcTargetType::Product => $this->claimProduct($assignment, $athlete->id),
                    NfcTargetType::Profile => ['athlete_id' => $athlete->id],
                    NfcTargetType::Event => throw new NfcNotClaimableException,
                };

                $assignment->update([...$attributes, 'activated_at' => now()]);

                activity()
                    ->causedBy($user)
                    ->performedOn($assignment)
                    ->withProperties(array_filter(['tag' => $tag->shortToken(), 'ip_hash' => $ip ? substr(hash('sha256', $ip), 0, 16) : null]))
                    ->log('nfc.claimed');

                return $assignment->fresh();
            });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function claimPlate(NfcAssignment $assignment, User $user, ?string $ip): array
    {
        $assignment->loadMissing('plate.legacyCode');
        $code = $assignment->plate?->legacyCode?->code;

        if ($code === null) {
            throw new NfcNotClaimableException('Esta Legacy Plate todavía no está lista para conectarse.');
        }

        try {
            $this->legacyClaims->claim($code, $user, $ip);
        } catch (LegacyCodeClaimConflictException) {
            throw new NfcAlreadyClaimedException;
        } catch (LegacyCodeUnavailableException) {
            throw new NfcNotClaimableException('Esta Legacy Plate no está disponible.');
        }

        return ['athlete_id' => $assignment->plate->fresh()->athlete_id];
    }

    /**
     * @return array<string, mixed>
     */
    private function claimProduct(NfcAssignment $assignment, int $athleteId): array
    {
        if ($assignment->athlete_owned_product_id !== null) {
            /** @var AthleteOwnedProduct $owned */
            $owned = AthleteOwnedProduct::query()->lockForUpdate()->findOrFail($assignment->athlete_owned_product_id);

            if ($owned->athlete_id !== $athleteId) {
                throw new NfcAlreadyClaimedException;
            }

            $owned->update(['status' => AthleteOwnedProductStatus::Active, 'activated_at' => $owned->activated_at ?? now()]);

            return ['athlete_id' => $athleteId];
        }

        if ($assignment->product_id === null) {
            throw new NfcNotClaimableException;
        }

        // The physical unit becomes part of "Mi equipo" — no order behind it
        // (gift / event-day sale), so it is created here, not by
        // FulfillOrderItem. The NFC token is its link; no QR asset code.
        $owned = AthleteOwnedProduct::create([
            'uuid' => (string) Str::uuid(),
            'athlete_id' => $athleteId,
            'product_id' => $assignment->product_id,
            'product_variant_id' => $assignment->product_variant_id,
            'status' => AthleteOwnedProductStatus::Active,
            'acquired_at' => now(),
            'activated_at' => now(),
            'metadata' => ['source' => 'nfc_claim'],
        ]);

        return ['athlete_id' => $athleteId, 'athlete_owned_product_id' => $owned->id];
    }
}
