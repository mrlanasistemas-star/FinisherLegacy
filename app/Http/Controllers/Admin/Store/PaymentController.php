<?php

namespace App\Http\Controllers\Admin\Store;

use App\Actions\Commerce\RegisterManualPayment;
use App\Enums\PaymentMethod;
use App\Exceptions\Api\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payments admin — list (brief §41/§80) + manual registration (terminal/
 * cash, brief §22/§81), gated by `payments.record_manual`
 * (App\Http\Requests never involved here — this is Web, not the REST
 * API's RegisterManualPaymentRequest — the permission check happens via
 * the `can:` route middleware, same enforcement, no duplicated rule).
 */
class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::query()
            ->with('order.user')
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->whereHas(
                'order',
                fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"),
            ))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $payments->through(fn (Payment $payment) => [
            'id' => $payment->id,
            'order_number' => $payment->order->order_number,
            'order_uuid' => $payment->order->uuid,
            'customer' => $payment->order->user === null ? '—' : $payment->order->user->name,
            'provider' => $payment->provider->value,
            'method' => $payment->method->value,
            'status' => $payment->status->value,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
        ]);

        return Inertia::render('admin/payments/Index', [
            'payments' => $payments,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function registerManual(Request $request, Order $order, RegisterManualPayment $register): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', Rule::in(['cash', 'mercado_pago_terminal'])],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $register->handle(
                $order,
                PaymentMethod::from($data['method']),
                $data['amount_minor'],
                $request->user(),
                $data['reference'] ?? null,
                $data['notes'] ?? null,
            );

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Pago registrado.']);
        } catch (ApiException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
