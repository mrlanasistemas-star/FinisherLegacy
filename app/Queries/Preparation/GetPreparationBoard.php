<?php

namespace App\Queries\Preparation;

use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\PreparationStage;
use App\Enums\ProductionJobStatus;
use App\Models\LegacyPlateEntitlement;
use App\Models\Plate;
use App\Models\ProductionJob;
use App\Services\PlateEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Preparación y entrega" board (docs/NFC.md §Preparación): the latest
 * ProductionJob per plate mapped to PreparationStage, plus paid Legacy
 * Plates still waiting to be generated. Bounded queries, eager-loaded —
 * the query count never grows with the number of cards.
 */
class GetPreparationBoard
{
    private const int JOB_LIMIT = 300;

    private const int PENDING_ENTITLEMENT_LIMIT = 60;

    private const int DELIVERED_DAYS = 30;

    public function __construct(private readonly PlateEligibilityService $eligibility) {}

    /**
     * @return array{lanes: list<array<string, mixed>>, attention: list<array<string, mixed>>}
     */
    public function handle(?int $eventEditionId, ?string $search, bool $includeEntitlements): array
    {
        $latestIds = ProductionJob::query()->selectRaw('max(id)')->groupBy('plate_id');

        $jobs = ProductionJob::query()
            ->whereIn('id', $latestIds)
            ->where(fn (Builder $q) => $q
                ->where('status', '!=', ProductionJobStatus::Delivered)
                ->orWhere('updated_at', '>=', now()->subDays(self::DELIVERED_DAYS)))
            ->whereHas('plate', function (Builder $plate) use ($eventEditionId, $search) {
                $plate->when($eventEditionId, fn (Builder $q) => $q->where('event_edition_id', $eventEditionId))
                    ->when($search, fn (Builder $q) => $q->where(fn (Builder $s) => $s
                        ->where('athlete_name', 'like', "%{$search}%")
                        ->orWhere('engraving_display_name', 'like', "%{$search}%")
                        ->orWhere('bib_number', $search)
                        ->orWhere('serial_number', $search)));
            })
            ->with(['plate.eventEdition.event', 'plate.legacyPlateModel', 'plate.activeNfcAssignment.tag'])
            ->orderByDesc('updated_at')
            ->limit(self::JOB_LIMIT)
            ->get();

        $cards = $jobs->map(fn (ProductionJob $job) => $this->jobCard($job));

        if ($includeEntitlements && $search === null) {
            $cards = $this->pendingEntitlements($eventEditionId)->concat($cards);
        }

        $lanes = collect(PreparationStage::lanes())->map(fn (PreparationStage $stage) => [
            'key' => $stage->value,
            'title' => $stage->label(),
            'count' => $cards->where('stage', $stage->value)->count(),
            'items' => array_values($cards->where('stage', $stage->value)->take(60)->all()),
        ])->all();

        return [
            'lanes' => array_values($lanes),
            'attention' => array_values($cards->where('stage', PreparationStage::Attention->value)->take(20)->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobCard(ProductionJob $job): array
    {
        $plate = $job->plate;
        $nfc = $plate->activeNfcAssignment;
        $stage = PreparationStage::fromJob($job, $nfc !== null);

        return [
            'key' => 'job-'.$job->id,
            'job_id' => $job->id,
            'plate_id' => $plate->id,
            'entitlement_id' => null,
            'stage' => $stage->value,
            'next_action' => $this->nextAction($stage, $job),
            'athlete_name' => $plate->engraving_display_name ?: $plate->athlete_name,
            'bib_number' => $plate->bib_number,
            'event' => $plate->eventEdition?->event->name ?? $plate->event_name,
            'race' => $plate->race_name,
            'official_time' => $plate->official_time,
            'serial_number' => $plate->serial_number,
            'model' => $plate->legacyPlateModel?->name,
            'historical' => $plate->plate_template_version_id !== null,
            'nfc' => $nfc !== null ? [
                'tag' => $nfc->tag?->shortToken(),
                'connected' => $nfc->isClaimed(),
            ] : null,
            'eligible' => true,
            'reasons' => [],
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];
    }

    private function nextAction(PreparationStage $stage, ProductionJob $job): ?string
    {
        return match ($stage) {
            PreparationStage::Pending => 'start',
            PreparationStage::Preparing => 'personalize',
            PreparationStage::Personalized => 'assign_nfc',
            PreparationStage::NfcAssigned => 'ready',
            PreparationStage::Ready => $job->status === ProductionJobStatus::Ready ? 'deliver' : null,
            default => null,
        };
    }

    /**
     * Paid Legacy Plates without a generated plate yet — "Pendiente" with a
     * "Generar" action (GenerateLegacyPlate, same rules as the API).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingEntitlements(?int $eventEditionId): Collection
    {
        return LegacyPlateEntitlement::query()
            ->whereNull('plate_id')
            ->whereIn('status', [LegacyPlateEntitlementStatus::Paid, LegacyPlateEntitlementStatus::Linked])
            ->when($eventEditionId, fn (Builder $q) => $q->where('event_edition_id', $eventEditionId))
            ->with(['eventParticipant.result', 'eventParticipant.eventRace', 'eventEdition.event', 'legacyPlateModel'])
            ->latest('updated_at')
            ->limit(self::PENDING_ENTITLEMENT_LIMIT)
            ->get()
            ->map(fn (LegacyPlateEntitlement $entitlement) => $this->entitlementCard($entitlement));
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlementCard(LegacyPlateEntitlement $entitlement): array
    {
        $participant = $entitlement->eventParticipant;
        $check = $this->eligibility->checkForEntitlement($entitlement);

        return [
            'key' => 'entitlement-'.$entitlement->id,
            'job_id' => null,
            'plate_id' => null,
            'entitlement_id' => $entitlement->id,
            'stage' => PreparationStage::Pending->value,
            'next_action' => 'generate',
            'athlete_name' => $participant?->full_name ?: ($participant ? trim("{$participant->first_name} {$participant->last_name}") : null),
            'bib_number' => $participant?->bib_number,
            'event' => $entitlement->eventEdition?->event?->name,
            'race' => $participant?->eventRace?->name,
            'official_time' => $participant?->result?->official_time,
            'serial_number' => null,
            'model' => $entitlement->legacyPlateModel?->name,
            'historical' => false,
            'nfc' => null,
            'eligible' => $check->eligible,
            'reasons' => $check->reasons,
            'updated_at' => $entitlement->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Engraving data the external machine software consumes (docs/NFC.md
     * §Contrato con la máquina) — values only, no hardware parameters.
     *
     * @return array<string, mixed>
     */
    public static function engravingData(Plate $plate): array
    {
        $plate->loadMissing(['legacyPlateModel', 'eventEdition.event']);
        $layout = $plate->layout_snapshot ?: $plate->legacyPlateModel?->loadMissing('fields')->toSnapshotArray();

        return [
            'serial' => $plate->serial_number,
            'spec_version' => $layout['spec_version'] ?? null,
            'layout' => $layout,
            'values' => array_merge([
                'athlete_name' => $plate->engraving_display_name ?: $plate->athlete_name,
                'event_name' => $plate->eventEdition?->event->name ?? $plate->event_name,
                'race_name' => $plate->race_name,
                'event_date' => $plate->event_date?->toDateString(),
                'official_time' => $plate->official_time,
                'pace' => $plate->pace,
                'bib_number' => $plate->bib_number,
            ], (array) $plate->dynamic_fields),
        ];
    }
}
