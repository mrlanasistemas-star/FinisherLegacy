<?php

namespace App\Http\Controllers\Admin;

use App\Actions\LegacyPlates\GenerateLegacyPlate;
use App\Http\Controllers\Controller;
use App\Models\LegacyPlateEntitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

/**
 * The old "Producción" board now lives in Admin → Legacy + NFC →
 * "Preparación y entrega" (docs/NFC.md §Preparación); its URL redirects
 * there. "Generar" still goes through App\Actions\LegacyPlates\
 * GenerateLegacyPlate — the same Action the REST API uses.
 */
class LegacyPlateProductionController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return to_route('admin.legacy-nfc.preparation.index', array_filter([
            'event_edition_id' => $request->integer('event_edition_id') ?: null,
        ]));
    }

    public function produce(LegacyPlateEntitlement $legacyPlateEntitlement, GenerateLegacyPlate $generate): RedirectResponse
    {
        try {
            $generate->handle($legacyPlateEntitlement);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Legacy Plate generada: ya está en Pendiente.']);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
