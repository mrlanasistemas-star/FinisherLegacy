<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Actions\Commerce\SyncOnlinePayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class OrderController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /**
     * Standard Laravel resource pagination — `{data, links, meta}` — like
     * every other paginated list in /api/v1 (medals, events). It used to go
     * through respond(), which silently dropped links/meta.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $this->sanctumUser($request)->id)
            ->with(['items.productVariant', 'items.product.media', 'latestPayment'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return OrderResource::collection($orders)->response();
    }

    /**
     * What the app polls after the in-app OpenPay checkout closes. While the
     * latest OpenPay attempt is still pending, the charge is re-read from
     * OpenPay (server to server, at most every few seconds per Order) and
     * applied through the webhook path — so the answer never depends on the
     * browser redirect, nor on the webhook being configured yet.
     */
    public function show(Request $request, Order $order, SyncOnlinePayment $sync): JsonResponse
    {
        abort_unless($order->user_id === $this->sanctumUser($request)->id, 403);

        $latest = $order->latestPayment()->first();

        if ($latest !== null
            && $latest->provider->value === 'openpay'
            && filled($latest->provider_reference)
            && in_array($latest->status, [PaymentStatus::Pending, PaymentStatus::Authorized], true)
            && Cache::add("api.orders.openpay-sync.{$order->id}", true, now()->addSeconds(4))) {
            try {
                $order = $sync->handle($order);
            } catch (Throwable $e) {
                // The webhook (or the next poll) settles it.
                report($e);
            }
        }

        return $this->respond(new OrderResource($order->loadMissing(['items.productVariant', 'items.product.media', 'latestPayment'])));
    }
}
