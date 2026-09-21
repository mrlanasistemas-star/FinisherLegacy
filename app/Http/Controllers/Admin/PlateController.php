<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlateStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\ReprintStatus;
use App\Http\Controllers\Controller;
use App\Models\EventResultSplit;
use App\Models\Plate;
use App\Models\PlateReprint;
use App\Models\ProductionJob;
use App\Models\User;
use App\Services\PlateExportService;
use App\Services\PlateSnapshotBuilder;
use App\Services\PlateTemplateRenderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class PlateController extends Controller
{
    public function __construct(
        private readonly PlateExportService $exports,
        private readonly PlateSnapshotBuilder $snapshotBuilder,
    ) {}

    public function index(Request $request): Response
    {
        $plates = Plate::query()
            ->with(['legacyCode', 'eventEdition.event'])
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('athlete_name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('bib_number', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $plates->through(fn (Plate $plate) => [
            'id' => $plate->id,
            'serial_number' => $plate->serial_number,
            'athlete_name' => $plate->athlete_name,
            'event' => $plate->eventEdition?->event->name ?? $plate->event_name,
            'status' => $plate->status->value,
            'generation_mode' => $plate->generation_mode->value,
            'legacy_code' => $plate->legacyCode?->code,
            'owner' => $plate->user_id ? 'Vinculada' : 'Sin reclamar',
        ]);

        return Inertia::render('admin/plates/Index', [
            'plates' => $plates,
            'filters' => ['q' => $request->string('q')->toString()],
            'batchExportLimit' => (int) config('plate-studio.batch_export_limit'),
        ]);
    }

    public function show(Plate $plate): Response
    {
        $plate->load([
            'legacyCode', 'eventEdition.event', 'eventParticipant.result.splits', 'user',
            'plateTemplate', 'plateTemplateVersion', 'latestProductionJob', 'legacyPlateModel.fields',
            'reprints' => fn ($q) => $q->latest()->with(['requestedBy', 'approvedBy']),
        ]);

        $currentResult = $plate->eventParticipant?->result;
        $resultChanged = $currentResult !== null
            && ($currentResult->official_time !== $plate->official_time || $currentResult->pace !== $plate->pace);

        $activities = Activity::query()
            ->where('subject_type', Plate::class)
            ->where('subject_id', $plate->id)
            ->with('causer')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'event' => match ($activity->event) {
                    'created' => 'Creado',
                    'updated' => 'Actualizado',
                    'deleted' => 'Eliminado',
                    'restored' => 'Restaurado',
                    default => $activity->event ?? '—',
                },
                'causer' => $activity->causer instanceof User ? $activity->causer->name : 'Sistema',
                'created_at' => $activity->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('admin/plates/Show', [
            'plate' => [
                'id' => $plate->id,
                'serial_number' => $plate->serial_number,
                'athlete_name' => $plate->athlete_name,
                'bib_number' => $plate->bib_number,
                'event_name' => $plate->eventEdition?->event->name ?? $plate->event_name,
                'race_name' => $plate->race_name,
                'official_time' => $plate->official_time,
                'pace' => $plate->pace,
                'status' => $plate->status->value,
                'generation_mode' => $plate->generation_mode->value,
                'legacy_code' => $plate->legacyCode?->code,
                'qr_url' => $plate->legacyCode ? route('legacy-code.qr', $plate->legacyCode->code) : null,
                'owner' => $plate->user?->name,
                'template' => $plate->plateTemplate ? [
                    'name' => $plate->plateTemplate->name,
                    'version' => $plate->plateTemplateVersion?->version,
                ] : null,
                'production_job' => $plate->latestProductionJob ? [
                    'status' => $plate->latestProductionJob->status->value,
                    'queued_at' => $plate->latestProductionJob->queued_at->format('d/m/Y H:i'),
                ] : null,
                'can_reprint' => in_array($plate->status, [PlateStatus::Ready, PlateStatus::Delivered], true),
            ],
            'legacyPlateModel' => $plate->legacyPlateModel?->toViewerArray(),
            'personalization' => [
                'athlete_name' => $plate->engraving_display_name,
                'race_label' => $plate->race_name,
                'official_time' => $plate->official_time,
                'pace' => $plate->pace,
                'qr_url' => $plate->legacyCode ? route('legacy-code.qr', $plate->legacyCode->code) : null,
            ],
            'resultComparison' => $resultChanged ? [
                'original' => ['official_time' => $plate->official_time, 'pace' => $plate->pace],
                'current' => ['official_time' => $currentResult->official_time, 'pace' => $currentResult->pace],
            ] : null,
            'splits' => $currentResult?->splits
                ->sortBy('sequence')
                ->map(fn (EventResultSplit $split) => [
                    'label' => $split->label,
                    'distance' => $split->distance_value !== null
                        ? rtrim(rtrim((string) $split->distance_value, '0'), '.').' '.($split->distance_unit ?? 'km')
                        : null,
                    'segment_time' => $split->segment_time,
                    'elapsed_time' => $split->elapsed_time,
                    'pace' => $split->pace,
                ])
                ->values() ?? [],
            'reprints' => $plate->reprints->map(fn (PlateReprint $reprint) => [
                'id' => $reprint->id,
                'reason' => $reprint->reason,
                'status' => $reprint->status->value,
                'requested_by' => $reprint->requestedBy?->name,
                'created_at' => $reprint->created_at?->format('d/m/Y H:i'),
            ]),
            'activities' => $activities,
        ]);
    }

    public function reprint(Request $request, Plate $plate): RedirectResponse
    {
        abort_unless(in_array($plate->status, [PlateStatus::Ready, PlateStatus::Delivered], true), 422, 'Solo se pueden reimprimir placas listas o entregadas.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'use_original' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($plate, $data, $request) {
            if (! $data['use_original']) {
                $this->refreshSnapshotFromSource($plate);
            }

            PlateReprint::create([
                'plate_id' => $plate->id,
                'requested_by' => $request->user()->id,
                'reason' => $data['reason'],
                'approved_by' => $request->user()->id,
                'status' => ReprintStatus::Approved,
            ]);

            // Reprinting always reuses the plate's existing legacy_code_id — a new
            // Legacy Code is only ever created for an explicit administrative
            // exception, never as a side effect of a reprint. The brief
            // Reprint -> Queued dance is a simple product-status marker,
            // not a ProductionJob state — see docs/adr/0003 §Reprint.
            $plate->update(['status' => PlateStatus::Reprint]);
            $plate->update(['status' => PlateStatus::Queued]);

            ProductionJob::create([
                'plate_id' => $plate->id,
                'event_edition_id' => $plate->event_edition_id,
                'priority' => 0,
                'status' => ProductionJobStatus::Queued,
                'queued_at' => now(),
                'attempts' => 0,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reimpresión registrada. Mismo Legacy Code, nuevo trabajo de producción.']);

        return back();
    }

    /**
     * Single-face production file — always generated on the fly from the plate's
     * frozen snapshot + the exact template version it was created with, never cached
     * to disk. Downloading twice always yields the same file.
     */
    public function export(Request $request, Plate $plate, string $face, string $format): HttpResponse
    {
        abort_unless(in_array($face, ['front', 'back'], true), 404);

        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['product', 'production'])],
            'dpi' => ['nullable', 'integer', Rule::in([300, 600])],
            'text_as_paths' => ['nullable', 'boolean'],
        ]);

        $file = $this->exports->exportFace(
            $plate,
            $face,
            $format,
            $data['mode'] ?? PlateTemplateRenderService::MODE_PRODUCTION,
            $data['dpi'] ?? 300,
            (bool) ($data['text_as_paths'] ?? false),
        );

        return $this->download($file['content'], $file['mime'], "{$plate->serial_number}-{$face}.{$file['extension']}");
    }

    public function exportQr(Plate $plate, string $format = 'svg'): HttpResponse
    {
        $file = $this->exports->exportQr($plate, $format);

        return $this->download($file['content'], $file['mime'], "{$plate->serial_number}-qr.{$file['extension']}");
    }

    public function exportPackage(Plate $plate): HttpResponse
    {
        $content = $this->exports->exportPackage($plate);

        return $this->download($content, 'application/zip', "{$plate->serial_number}.zip");
    }

    public function exportBatch(Request $request): HttpResponse
    {
        $limit = (int) config('plate-studio.batch_export_limit');

        $data = $request->validate([
            'plate_ids' => ['required', 'array', 'min:1', "max:{$limit}"],
            'plate_ids.*' => ['integer', 'exists:plates,id'],
        ]);

        $plates = Plate::query()->with('plateTemplateVersion.plateTemplate')->whereIn('id', $data['plate_ids'])->get();
        $content = $this->exports->exportBatch($plates);

        return $this->download($content, 'application/zip', 'lote-'.now()->format('Ymd-His').'.zip');
    }

    private function refreshSnapshotFromSource(Plate $plate): void
    {
        $plate->loadMissing('eventParticipant.result.splits');
        $participant = $plate->eventParticipant;

        if (! $participant) {
            return;
        }

        $result = $participant->result;

        $plate->update([
            'official_time' => $result?->official_time,
            'pace' => $result?->pace,
            'dynamic_fields' => array_merge($plate->dynamic_fields ?? [], $this->snapshotBuilder->build($participant)),
        ]);
    }

    private function download(string $content, string $mime, string $filename): HttpResponse
    {
        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => (string) strlen($content),
        ]);
    }
}
