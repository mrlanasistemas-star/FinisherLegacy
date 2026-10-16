<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Integrations\ResolveEventDataSource;
use App\Enums\DataSourcePurpose;
use App\Enums\ResultStatus;
use App\Exceptions\EventDataSourceNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Models\EventEdition;
use App\Models\EventImport;
use App\Models\EventParticipant;
use App\Models\EventResult;
use App\Models\ExternalEventMapping;
use App\Models\OrganizerDataSource;
use App\Queries\Admin\GetEventWorkspaceHeader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two event-workspace tabs that had no page of their own before
 * (spec §9): "Resultados" and "Datos / Integración". Resumen, Participantes,
 * Prerregistros and Media reuse their existing controllers (forEvent()).
 */
class EventWorkspaceController extends Controller
{
    private const RESULTS_PER_PAGE = 50;

    public function results(Request $request, EventEdition $eventEdition, GetEventWorkspaceHeader $header): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'event_race_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_column(ResultStatus::cases(), 'value'))],
        ]);

        $participantIds = $eventEdition->participants()->select('id');

        $results = EventResult::query()
            ->whereIn('event_participant_id', $participantIds)
            ->with(['eventParticipant:id,event_race_id,full_name,bib_number', 'eventParticipant.eventRace:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['event_race_id'] ?? null, fn ($q, $raceId) => $q->whereHas(
                'eventParticipant',
                fn ($p) => $p->where('event_race_id', $raceId),
            ))
            ->when(trim((string) ($filters['q'] ?? '')), fn ($q, $search) => $q->whereHas(
                'eventParticipant',
                fn ($p) => $p->where(fn ($inner) => $inner
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('bib_number', 'like', "%{$search}%")),
            ))
            // Ranked first (by position), then everyone without a position.
            ->orderByRaw('overall_position is null')
            ->orderBy('overall_position')
            ->orderBy('id')
            ->paginate(self::RESULTS_PER_PAGE)
            ->withQueryString()
            ->through(fn (EventResult $result) => [
                'id' => $result->id,
                'participant_id' => $result->event_participant_id,
                'name' => $result->eventParticipant?->full_name,
                'bib_number' => $result->eventParticipant?->bib_number,
                'race' => $result->eventParticipant?->eventRace?->name,
                'status' => $result->status->value,
                'official_time' => $result->official_time,
                'chip_time' => $result->chip_time,
                'pace' => $result->pace,
                'overall_position' => $result->overall_position,
                'gender_position' => $result->gender_position,
                'category_position' => $result->category_position,
                'verified' => $result->verified_at !== null,
            ]);

        $byStatus = EventResult::query()
            ->whereIn('event_participant_id', $eventEdition->participants()->select('id'))
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $participants = EventParticipant::query()->where('event_edition_id', $eventEdition->id)->count();
        $withResult = (int) $byStatus->sum();

        return Inertia::render('admin/events/Results', [
            'workspace' => $header->handle($eventEdition),
            'results' => $results,
            'races' => $eventEdition->races()->orderBy('id')->get(['id', 'name']),
            'statuses' => array_column(ResultStatus::cases(), 'value'),
            'summary' => [
                'participants' => $participants,
                'with_result' => $withResult,
                'without_result' => max(0, $participants - $withResult),
                'finishers' => (int) (($byStatus[ResultStatus::Finished->value] ?? 0) + ($byStatus[ResultStatus::Verified->value] ?? 0)),
                'dnf' => (int) (($byStatus[ResultStatus::Dnf->value] ?? 0) + ($byStatus[ResultStatus::Dns->value] ?? 0) + ($byStatus[ResultStatus::Dq->value] ?? 0)),
            ],
            'resultsStatus' => $eventEdition->results_status->value,
            'filters' => [
                'q' => $filters['q'] ?? null,
                'event_race_id' => isset($filters['event_race_id']) ? (int) $filters['event_race_id'] : null,
                'status' => $filters['status'] ?? null,
            ],
        ]);
    }

    /**
     * "Datos / Integración": where this event's participants and results
     * come from, the last file imports and the sync shortcut — everything
     * that used to live under Importaciones / Fuentes de datos /
     * Integraciones as separate menu entries.
     */
    public function data(EventEdition $eventEdition, GetEventWorkspaceHeader $header, ResolveEventDataSource $resolveDataSource): Response
    {
        $eventEdition->loadMissing(['event.organizer.dataSources', 'dataSourceProviderConnection']);

        $organizer = $eventEdition->event->organizer;
        $organizerSources = $organizer?->dataSources->where('active', true) ?? collect();
        $syncMapping = ExternalEventMapping::query()->where('event_edition_id', $eventEdition->id)->with('providerConnection')->first();

        return Inertia::render('admin/events/Data', [
            'workspace' => $header->handle($eventEdition),
            'organizer' => $organizer ? ['id' => $organizer->id, 'name' => $organizer->name] : null,
            'dataSource' => [
                'type' => $eventEdition->data_source_type,
                'provider_connection' => $eventEdition->dataSourceProviderConnection?->name,
                'inherited' => $eventEdition->data_source_type === null,
                'participants_data_source_id' => $eventEdition->participants_data_source_id,
                'results_data_source_id' => $eventEdition->results_data_source_id,
                'organizer_sources' => $organizerSources->map(fn (OrganizerDataSource $source) => [
                    'id' => $source->id,
                    'name' => $source->name,
                    'type' => $source->type->value,
                    'purpose' => $source->purpose->value,
                    'is_default' => $source->is_default,
                ])->values(),
                'resolved_participants' => $this->resolvedSourcePayload($eventEdition, $resolveDataSource, DataSourcePurpose::Participants),
                'resolved_results' => $this->resolvedSourcePayload($eventEdition, $resolveDataSource, DataSourcePurpose::Results),
            ],
            'syncMapping' => $syncMapping ? [
                'id' => $syncMapping->id,
                'provider_connection' => $syncMapping->providerConnection->name,
            ] : null,
            'imports' => EventImport::query()
                ->where('event_edition_id', $eventEdition->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (EventImport $import) => [
                    'id' => $import->id,
                    'filename' => $import->original_filename,
                    'status' => $import->status->value,
                    'total_rows' => $import->total_rows,
                    'successful_rows' => $import->successful_rows,
                    'failed_rows' => $import->failed_rows,
                    'created_at' => $import->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function resolvedSourcePayload(EventEdition $edition, ResolveEventDataSource $resolveDataSource, DataSourcePurpose $purpose): ?array
    {
        try {
            $resolved = $resolveDataSource->handle($edition, $purpose);
        } catch (EventDataSourceNotConfiguredException) {
            return null;
        }

        return [
            'type' => $resolved->type->value,
            'provider_connection' => $resolved->providerConnection?->name,
            'is_override' => $resolved->isOverride,
        ];
    }
}
