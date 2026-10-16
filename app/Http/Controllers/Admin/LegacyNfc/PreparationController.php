<?php

namespace App\Http\Controllers\Admin\LegacyNfc;

use App\Actions\Nfc\AssignNfcTag;
use App\Actions\Preparation\AdvancePlatePreparation;
use App\Enums\NfcTagStatus;
use App\Enums\PreparationStage;
use App\Exceptions\Api\ApiException;
use App\Exceptions\Devices\DeviceApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nfc\AssignPlateNfcRequest;
use App\Models\EventEdition;
use App\Models\NfcTag;
use App\Models\Plate;
use App\Models\ProductionJob;
use App\Queries\Preparation\GetPreparationBoard;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Admin → Legacy + NFC → "Preparación y entrega" (docs/NFC.md
 * §Preparación). Replaces the laser-centric /production kanban in the web
 * UI: no USB, driver, power, frequency, flip or QR concepts here — the
 * external machine software keeps those through the Device API.
 */
class PreparationController extends Controller
{
    public function __construct(private readonly AdvancePlatePreparation $preparation) {}

    public function index(Request $request, GetPreparationBoard $board): Response
    {
        $eventEditionId = $request->integer('event_edition_id') ?: null;
        $search = trim((string) $request->string('q')) ?: null;
        $canGenerate = (bool) $request->user()->can('legacyplates.produce');

        return Inertia::render('admin/legacy-nfc/Preparation', [
            ...$board->handle($eventEditionId, $search, $canGenerate),
            'filters' => ['event_edition_id' => $eventEditionId, 'q' => $search],
            'events' => EventEdition::query()->with('event')->orderByDesc('event_date')->limit(100)->get()
                ->map(fn (EventEdition $e) => ['id' => $e->id, 'name' => $e->event->name.' — '.$e->name]),
            'availableTags' => NfcTag::query()->where('status', NfcTagStatus::Available)->orderBy('id')->limit(50)->get()
                ->map(fn (NfcTag $tag) => ['id' => $tag->id, 'label' => $tag->shortToken(), 'batch' => $tag->batch_code]),
            'can' => [
                'manage' => (bool) $request->user()->can('production.manage'),
                'generate' => $canGenerate,
            ],
        ]);
    }

    public function start(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->preparation->start($job, $request->user()), 'Preparación iniciada.');
    }

    public function personalize(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->preparation->markPersonalized($job, $request->user()), 'Marcada como personalizada.');
    }

    public function assignNfc(AssignPlateNfcRequest $request, Plate $plate, AssignNfcTag $assign): RedirectResponse
    {
        $tag = $request->resolveTag();

        return $this->run(
            fn () => $assign->toPlate($tag, $plate, $request->user(), $request->boolean('replace')),
            'Etiqueta NFC asignada.',
        );
    }

    public function ready(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->preparation->markReady($job, $request->user()), 'Lista para entregar.');
    }

    public function deliver(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->preparation->deliver($job, $request->user()), 'Entregada.');
    }

    /**
     * "Datos de personalización" for one plate — the machine contract
     * (values + frozen layout), downloaded as JSON.
     */
    public function data(Plate $plate): JsonResponse
    {
        return response()->json(GetPreparationBoard::engravingData($plate), 200, [
            'Content-Disposition' => 'attachment; filename="'.$plate->serial_number.'-datos.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * CSV of every plate in "Preparando" (optionally one event) — what the
     * machine software imports for a batch.
     */
    public function export(Request $request): StreamedResponse
    {
        $eventEditionId = $request->integer('event_edition_id') ?: null;

        $plates = Plate::query()
            ->whereHas('latestProductionJob', fn ($q) => $q->whereIn('status', ['queued', 'assigned', 'preparing', 'engraving_front']))
            ->when($eventEditionId, fn ($q) => $q->where('event_edition_id', $eventEditionId))
            ->with(['legacyPlateModel', 'eventEdition.event', 'latestProductionJob', 'activeNfcAssignment'])
            ->orderBy('id')
            ->limit(1000)
            ->get()
            ->filter(fn (Plate $plate) => in_array(
                PreparationStage::fromJob($plate->latestProductionJob, $plate->activeNfcAssignment !== null),
                [PreparationStage::Pending, PreparationStage::Preparing],
                true,
            ));

        return response()->streamDownload(function () use ($plates) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['serial', 'layout', 'nombre', 'evento', 'distancia', 'fecha', 'tiempo', 'ritmo', 'dorsal', 'posicion']);

            foreach ($plates as $plate) {
                $values = GetPreparationBoard::engravingData($plate)['values'];
                fputcsv($out, [
                    $plate->serial_number,
                    $plate->legacyPlateModel?->name,
                    $values['athlete_name'],
                    $values['event_name'],
                    $values['distance'] ?? $values['race_name'],
                    $values['event_date'],
                    $values['official_time'],
                    $values['pace'],
                    $values['bib_number'],
                    $values['overall_position'] ?? null,
                ]);
            }

            fclose($out);
        }, 'preparacion-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function run(Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (DeviceApiException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Este paso no corresponde a la etapa actual. Actualiza el tablero.']);

            return back();
        } catch (ApiException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        } catch (HttpException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage() ?: 'No se pudo completar este paso.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $success]);

        return back();
    }
}
