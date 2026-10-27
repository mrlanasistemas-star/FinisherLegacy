<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Actions\LegacyTags\AssignTagsToOrderItem;
use App\Enums\DeliveryMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Pedidos → shipping data and delivery confirmation (traceability
 * SKU → unit → order → buyer → activation). The order list/detail stay in
 * App\Http\Controllers\Admin\Store\OrderController.
 */
class OrderOperationsController extends Controller
{
    public function updateShipping(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'delivery_method' => ['nullable', Rule::enum(DeliveryMethod::class)],
            'carrier' => ['nullable', 'string', 'max:60'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'shipped_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datos de envío actualizados.']);

        return back();
    }

    /** Shipping confirmed: every shipped piece becomes `delivered` (ready to activate). */
    public function markDelivered(Request $request, Order $order, AssignTagsToOrderItem $assign): RedirectResponse
    {
        if ($order->delivered_at !== null) {
            throw ValidationException::withMessages(['delivered' => 'Este pedido ya está marcado como entregado.']);
        }

        DB::transaction(function () use ($order, $assign, $request) {
            foreach ($order->items as $item) {
                $assign->markDelivered($item, $request->user());
            }

            $order->update(['delivered_at' => now()]);

            activity()->causedBy($request->user())->performedOn($order)->log('orders.delivered');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pedido marcado como entregado.']);

        return back();
    }
}
