<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentSettingsRequest;
use App\Services\Commerce\Payments\PaymentSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/configuracion/pagos — active gateway + OpenPay credentials.
 * The private key is write-only: it is stored encrypted and the page only
 * ever receives a masked hint ("••••1234").
 */
class PaymentSettingsController extends Controller
{
    public function index(PaymentSettings $settings): Response
    {
        return Inertia::render('admin/settings/Payments', [
            'settings' => $settings->adminSummary(),
        ]);
    }

    public function update(UpdatePaymentSettingsRequest $request, PaymentSettings $settings): RedirectResponse
    {
        $settings->save($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configuración de pagos guardada.']);

        return to_route('admin.settings.payments');
    }

    public function test(PaymentSettings $settings): JsonResponse
    {
        return response()->json($settings->testOpenPayConnection());
    }
}
