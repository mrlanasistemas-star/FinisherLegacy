<?php

namespace App\Services;

use App\Enums\LegacyCodeStatus;
use App\Enums\PlateGenerationMode;
use App\Enums\PlateLayoutType;
use App\Enums\PlateStatus;
use App\Enums\ProductionJobStatus;
use App\Exceptions\PlateAlreadyExistsException;
use App\Exceptions\PlateTemplateMissingException;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\LegacyCode;
use App\Models\LegacyPlateModel;
use App\Models\Plate;
use App\Models\PlateTemplateVersion;
use App\Models\ProductionJob;
use App\Support\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only place a Plate is ever created — both Event OS "integrated" (has
 * a participant/result) and "quick" (no participant yet) paths funnel
 * through here, so a Legacy Code + QR + ProductionJob are always created
 * together with the plate, never as an afterthought.
 */
class PlateGenerationService
{
    public function __construct(
        private readonly PlateSnapshotBuilder $snapshotBuilder,
    ) {}

    public function generateIntegrated(EventParticipant $participant, ?PlateTemplateVersion $version = null): Plate
    {
        // The one place this idempotency guard lives — Web and API both
        // rely on it instead of each re-checking `Plate::exists()`
        // themselves (docs/adr/0006-event-operations.md §5).
        if (Plate::where('event_participant_id', $participant->id)->exists()) {
            throw new PlateAlreadyExistsException;
        }

        $participant->loadMissing(['eventEdition.event', 'eventRace', 'result.splits']);
        $edition = $participant->eventEdition;
        $result = $participant->result;
        $version ??= $edition->defaultPlateTemplateVersion($participant->event_race_id);

        if ($version === null) {
            throw new PlateTemplateMissingException;
        }

        $dynamicFields = $this->snapshotBuilder->build($participant);

        return DB::transaction(function () use ($participant, $edition, $result, $version, $dynamicFields) {
            $plate = Plate::create([
                'user_id' => $participant->user_id,
                'athlete_id' => $participant->athlete_id,
                'event_edition_id' => $edition->id,
                'event_participant_id' => $participant->id,
                'plate_template_id' => $version->plate_template_id,
                'plate_template_version_id' => $version->id,
                'serial_number' => CodeGenerator::generate('PLT', 8),
                'generation_mode' => PlateGenerationMode::Integrated,
                'athlete_name' => $participant->full_name ?: trim("{$participant->first_name} {$participant->last_name}"),
                'bib_number' => $participant->bib_number,
                'event_name' => $edition->event->name,
                'race_name' => $participant->eventRace->name,
                'official_time' => $result?->official_time,
                'pace' => $result?->pace,
                'event_date' => $edition->event_date,
                'dynamic_fields' => $dynamicFields,
                'status' => PlateStatus::Draft,
                'linked_at' => $participant->user_id ? now() : null,
            ]);

            $this->attachLegacyCode($plate, $participant->user_id);
            $this->queueProduction($plate);

            return $plate->fresh(['legacyCode', 'latestProductionJob']);
        });
    }

    /**
     * @param  array{athlete_name: string, bib_number?: ?string, event_name?: ?string, race_name?: ?string, official_time?: ?string, pace?: ?string, event_race_id?: ?int, swim_time?: ?string, bike_time?: ?string, run_time?: ?string, personal_phrase?: ?string}  $data
     */
    public function generateQuick(EventEdition $edition, array $data, ?PlateTemplateVersion $version = null): Plate
    {
        $edition->loadMissing('event');
        $version ??= $edition->defaultPlateTemplateVersion($data['event_race_id'] ?? null);

        if ($version === null) {
            throw new PlateTemplateMissingException;
        }

        return DB::transaction(function () use ($edition, $data, $version) {
            $plate = Plate::create([
                'user_id' => null,
                'event_edition_id' => $edition->id,
                'plate_template_id' => $version->plate_template_id,
                'plate_template_version_id' => $version->id,
                'serial_number' => CodeGenerator::generate('PLT', 8),
                'generation_mode' => PlateGenerationMode::Quick,
                'athlete_name' => $data['athlete_name'],
                'bib_number' => $data['bib_number'] ?? null,
                'event_name' => $data['event_name'] ?? $edition->event->name,
                'race_name' => $data['race_name'] ?? null,
                'official_time' => $data['official_time'] ?? null,
                'pace' => $data['pace'] ?? null,
                'event_date' => $edition->event_date,
                'dynamic_fields' => [
                    'swim_time' => $data['swim_time'] ?? null,
                    'bike_time' => $data['bike_time'] ?? null,
                    'run_time' => $data['run_time'] ?? null,
                    'personal_phrase' => $data['personal_phrase'] ?? null,
                ],
                'status' => PlateStatus::Draft,
            ]);

            $this->attachLegacyCode($plate, null);
            $this->queueProduction($plate);

            return $plate->fresh(['legacyCode', 'latestProductionJob']);
        });
    }

    /**
     * Legacy Plate v2 (brief §127-§129): the pre-manufactured model only
     * needs the same dynamic fields as generateIntegrated(), plus the
     * model reference and the operator-editable engraving name — the
     * physical piece itself (shape/relief/decoration) isn't rendered here
     * at all, it already exists. Reuses this class's own Legacy Code +
     * ProductionJob wiring rather than a second pipeline (brief §128).
     */
    public function generateForLegacyPlateModel(
        EventParticipant $participant,
        LegacyPlateModel $model,
        ?string $engravingDisplayName = null,
        string $layoutVersion = 'v1',
    ): Plate {
        if (Plate::where('event_participant_id', $participant->id)->exists()) {
            throw new PlateAlreadyExistsException;
        }

        $participant->loadMissing(['eventEdition.event', 'eventRace', 'result.splits']);
        $edition = $participant->eventEdition;
        $result = $participant->result;
        $athleteName = $participant->full_name ?: trim("{$participant->first_name} {$participant->last_name}");

        $dynamicFields = $this->snapshotBuilder->build($participant);

        return DB::transaction(function () use ($participant, $edition, $result, $model, $athleteName, $engravingDisplayName, $layoutVersion, $dynamicFields) {
            $plate = Plate::create([
                'user_id' => $participant->user_id,
                'athlete_id' => $participant->athlete_id,
                'event_edition_id' => $edition->id,
                'event_participant_id' => $participant->id,
                'legacy_plate_model_id' => $model->id,
                'serial_number' => CodeGenerator::generate('PLT', 8),
                'generation_mode' => PlateGenerationMode::Integrated,
                'athlete_name' => $athleteName,
                'engraving_display_name' => $engravingDisplayName ?: $athleteName,
                'bib_number' => $participant->bib_number,
                'event_name' => $edition->event->name,
                'race_name' => $participant->eventRace->name,
                'official_time' => $result?->official_time,
                'pace' => $result?->pace,
                'event_date' => $edition->event_date,
                'dynamic_fields' => $dynamicFields,
                'layout_type' => PlateLayoutType::ManufacturedDynamic,
                'layout_version' => $layoutVersion,
                'status' => PlateStatus::Draft,
                'linked_at' => $participant->user_id ? now() : null,
            ]);

            $this->attachLegacyCode($plate, $participant->user_id);
            $this->queueProduction($plate);

            return $plate->fresh(['legacyCode', 'latestProductionJob', 'legacyPlateModel']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function previewPayload(Plate $plate): array
    {
        $plate->loadMissing(['legacyCode', 'plateTemplateVersion']);

        return [
            'id' => $plate->id,
            'serial_number' => $plate->serial_number,
            'athlete_name' => $plate->athlete_name,
            'bib_number' => $plate->bib_number,
            'event_name' => $plate->event_name,
            'race_name' => $plate->race_name,
            'official_time' => $plate->official_time,
            'pace' => $plate->pace,
            'event_date' => $plate->event_date?->toDateString(),
            'status' => $plate->status->value,
            'legacy_code' => $plate->legacyCode?->code,
            'qr_url' => $plate->legacyCode ? route('legacy-code.qr', $plate->legacyCode->code) : null,
            'plate_template_version_id' => $plate->plate_template_version_id,
        ];
    }

    private function attachLegacyCode(Plate $plate, ?int $userId): void
    {
        $legacyCode = LegacyCode::create([
            'code' => CodeGenerator::unique('FL', fn (string $c) => LegacyCode::query()->where('code', $c)->exists()),
            'uuid' => Str::uuid(),
            'plate_id' => $plate->id,
            'user_id' => $userId,
            'status' => LegacyCodeStatus::Assigned,
            'assigned_at' => now(),
        ]);

        $plate->update(['legacy_code_id' => $legacyCode->id]);
    }

    private function queueProduction(Plate $plate): void
    {
        ProductionJob::create([
            'plate_id' => $plate->id,
            'event_edition_id' => $plate->event_edition_id,
            'priority' => 0,
            'status' => ProductionJobStatus::Queued,
            'queued_at' => now(),
            'attempts' => 0,
        ]);

        $plate->update(['status' => PlateStatus::Queued]);
    }
}
