<?php

namespace App\Http\Controllers;

use App\Actions\Production\CancelProductionJob;
use App\Actions\Production\CompleteBackEngraving;
use App\Actions\Production\CompleteFrontEngraving;
use App\Actions\Production\ConfirmPlateFlip;
use App\Actions\Production\DeliverProductionPlate;
use App\Actions\Production\FailProductionJob;
use App\Actions\Production\StartBackEngraving;
use App\Actions\Production\StartFrontEngraving;
use App\Actions\Production\StartProductionPreparation;
use App\Actions\Production\VerifyProductionQr;
use App\Exceptions\Devices\DeviceApiException;
use App\Models\ProductionJob;
use App\Support\Production\FailProductionJobData;
use App\Support\Production\VerifyQrData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The manual/web fallback for the exact same physical workflow the Device
 * API drives — every method here calls the same Action classes as
 * App\Http\Controllers\Api\V1\Devices\ProductionJobController, never a
 * second implementation of the rules. See
 * docs/adr/0003-production-state-machine.md §8, §45 Web fallback.
 */
class ProductionController extends Controller
{
    public function __construct(
        private readonly StartProductionPreparation $prepareAction,
        private readonly StartFrontEngraving $startFrontAction,
        private readonly CompleteFrontEngraving $completeFrontAction,
        private readonly ConfirmPlateFlip $confirmFlipAction,
        private readonly StartBackEngraving $startBackAction,
        private readonly CompleteBackEngraving $completeBackAction,
        private readonly VerifyProductionQr $verifyQrAction,
        private readonly DeliverProductionPlate $deliverAction,
        private readonly FailProductionJob $failAction,
        private readonly CancelProductionJob $cancelAction,
    ) {}

    /**
     * The laser kanban (front/flip/back/QR) is no longer web UI — staff use
     * Admin → Legacy + NFC → "Preparación y entrega" (docs/NFC.md). The
     * job endpoints below stay for backward compatibility; the machine
     * software uses the Device API.
     */
    public function index(): RedirectResponse
    {
        return to_route('admin.legacy-nfc.preparation.index');
    }

    public function prepare(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->prepareAction->handle($job, $request->user()));
    }

    public function frontStart(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->startFrontAction->handle($job, $request->user()));
    }

    public function frontComplete(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->completeFrontAction->handle($job, $request->user()));
    }

    public function flipConfirm(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->confirmFlipAction->handle($job, $request->user()));
    }

    public function backStart(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->startBackAction->handle($job, $request->user()));
    }

    public function backComplete(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->completeBackAction->handle($job, $request->user()));
    }

    public function qrVerify(Request $request, ProductionJob $job): RedirectResponse
    {
        $data = VerifyQrData::fromRequest($request);

        return $this->run(fn () => $this->verifyQrAction->handle($job, $request->user(), $data));
    }

    public function deliver(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->deliverAction->handle($job, $request->user()));
    }

    public function fail(Request $request, ProductionJob $job): RedirectResponse
    {
        $data = FailProductionJobData::fromRequest($request);

        return $this->run(fn () => $this->failAction->handle($job, $request->user(), $data));
    }

    public function cancel(Request $request, ProductionJob $job): RedirectResponse
    {
        return $this->run(fn () => $this->cancelAction->handle($job, $request->user()));
    }

    private function run(callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (DeviceApiException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        return back();
    }
}
