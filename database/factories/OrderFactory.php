<?php

namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'order_number' => CodeGenerator::generate('FL', 8),
            'user_id' => null,
            'athlete_id' => null,
            'event_edition_id' => null,
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal_minor' => 100000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 100000,
            'currency' => 'MXN',
            'customer_snapshot' => null,
        ];
    }
}
