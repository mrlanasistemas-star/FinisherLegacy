<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $this->sanctumUser($request)->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->respond(OrderResource::collection($orders));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $this->sanctumUser($request)->id, 403);

        return $this->respond(new OrderResource($order->loadMissing('items')));
    }
}
