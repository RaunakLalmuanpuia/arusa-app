<?php

namespace Database\Factories;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('98########'),
            'customer_email' => null,
            'ship_line1' => fake()->streetAddress(),
            'ship_locality' => 'Zarkawt',
            'ship_district' => 'Aizawl',
            'ship_pincode' => '796001',
            'shipping_zone_id' => ShippingZone::factory(),
            'subtotal' => 99900,
            'shipping' => 5000,
            'total' => 104900,
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => PaymentStatus::Pending,
            'fulfilment_status' => FulfilmentStatus::Confirmed,
            'placed_at' => now(),
        ];
    }

    public function razorpay(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Razorpay,
            'payment_status' => PaymentStatus::AwaitingPayment,
            'fulfilment_status' => FulfilmentStatus::Pending,
        ]);
    }
}
