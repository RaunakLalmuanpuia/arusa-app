<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->variant = ProductVariant::factory()->create(['stock' => 0]);
});

function unpaidOrder(int $minutesAgo): Order
{
    $order = Order::factory()->razorpay()->create(['placed_at' => now()->subMinutes($minutesAgo)]);
    OrderItem::factory()->for($order)->create(['product_variant_id' => test()->variant->id, 'quantity' => 2]);
    Payment::factory()->for($order)->create(['amount' => $order->total]);

    return $order;
}

test('online orders left unpaid for 30 minutes are cancelled and their stock comes back', function () {
    $order = unpaidOrder(31);

    $this->artisan('orders:cancel-abandoned')->assertSuccessful();

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Failed)
        ->and($this->variant->fresh()->stock)->toBe(2);
});

test('recent, paid and cash on delivery orders are left alone', function () {
    $recent = unpaidOrder(29);
    $paid = unpaidOrder(45);
    $paid->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    $cashOnDelivery = Order::factory()->create(['placed_at' => now()->subHour()]);

    $this->artisan('orders:cancel-abandoned')->assertSuccessful();

    expect($recent->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Pending)
        ->and($paid->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($cashOnDelivery->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->variant->fresh()->stock)->toBe(0);
});

test('the sweep is scheduled every five minutes', function () {
    expect(Artisan::call('schedule:list'))->toBe(0);

    expect(Artisan::output())
        ->toContain('*/5 * * * *')
        ->toContain('orders:cancel-abandoned');
});
