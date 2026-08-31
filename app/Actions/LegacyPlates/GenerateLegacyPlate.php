<?php

namespace App\Actions\LegacyPlates;

use App\Enums\LegacyPlateEntitlementStatus;
use App\Exceptions\LegacyPlateAlreadyExistsException;
use App\Exceptions\LegacyPlateNotPaidException;
use App\Models\LegacyPlateEntitlement;
use App\Models\Plate;
use App\Services\PlateEligibilityService;
use App\Services\PlateGenerationService;
use Illuminate\Support\Facades\DB;

/**
 * The only entry point that turns a paid, linked LegacyPlateEntitlement
 * into an actual Plate + ProductionJob (brief §100-§101/§127-§129) — an
 * operator always presses "producir" explicitly, this never runs
 * automatically off a payment or sync event.
 */
class GenerateLegacyPlate
{
    public function __construct(
        private readonly PlateEligibilityService $eligibility,
        private readonly PlateGenerationService $generation,
    ) {}

    public function handle(LegacyPlateEntitlement $entitlement, ?string $engravingDisplayName = null): Plate
    {
        if ($entitlement->plate_id !== null) {
            throw new LegacyPlateAlreadyExistsException;
        }

        $result = $this->eligibility->checkForEntitlement($entitlement);

        if (! $result->eligible) {
            if (in_array('LEGACY_PLATE_NOT_PAID', $result->reasons, true)) {
                throw new LegacyPlateNotPaidException;
            }

            throw new \RuntimeException('Esta Legacy Plate no cumple los requisitos de producción: '.implode(', ', $result->reasons));
        }

        return DB::transaction(function () use ($entitlement, $engravingDisplayName) {
            $plate = $this->generation->generateForLegacyPlateModel(
                $entitlement->eventParticipant,
                $entitlement->legacyPlateModel,
                $engravingDisplayName,
            );

            $entitlement->update([
                'plate_id' => $plate->id,
                'status' => LegacyPlateEntitlementStatus::Queued,
            ]);

            return $plate;
        });
    }
}
