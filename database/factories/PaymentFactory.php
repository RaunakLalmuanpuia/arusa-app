<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->razorpay(),
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_'.Str::random(14),
            'gateway_payment_id' => null,
            'amount' => 104900,
            'status' => PaymentStatus::AwaitingPayment,
            'payload' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway_payment_id' => 'pay_'.Str::random(14),
            'status' => PaymentStatus::Paid,
        ]);
    }
}
