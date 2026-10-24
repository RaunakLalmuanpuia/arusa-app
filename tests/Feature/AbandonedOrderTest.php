<?php

use App\Actions\CancelOrder;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Mockery\MockInterface;

beforeEach(function () {
    $this->variant = ProductVariant::factory()->create(['stock' => 0]);
});

function abandonedSweepOrder(int $minutesAgo): Order
{
    $order = Order::factory()->razorpay()->create(['placed_at' => now()->subMinutes($minutesAgo)]);
    OrderItem::factory()->for($order)->create(['product_variant_id' => test()->variant->id, 'quantity' => 2]);
    Payment::factory()->for($order)->create(['amount' => $order->total]);

    return $order;
}

test('online orders left unpaid for 30 minutes are cancelled and their stock comes back', function () {
    $order = abandonedSweepOrder(31);

    $this->artisan('orders:cancel-abandoned')->assertSuccessful();

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Failed)
        ->and($this->variant->fresh()->stock)->toBe(2);
});

test('recent, paid and cash on delivery orders are left alone', function () {
    $recent = abandonedSweepOrder(29);
    $paid = abandonedSweepOrder(45);
    $paid->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    $cashOnDelivery = Order::factory()->create(['placed_at' => now()->subHour()]);

    $this->artisan('orders:cancel-abandoned')->assertSuccessful();

    expect($recent->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Pending)
        ->and($paid->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($cashOnDelivery->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->variant->fresh()->stock)->toBe(0);
});

test('cash on delivery orders nobody confirmed within 24 hours are cancelled and their stock comes back', function () {
    $unconfirmed = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Pending, 'placed_at' => now()->subHours(25)]);
    OrderItem::factory()->for($unconfirmed)->create(['product_variant_id' => $this->variant->id, 'quantity' => 2]);
    $recent = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Pending, 'placed_at' => now()->subHours(23)]);
    $confirmed = Order::factory()->create(['placed_at' => now()->subDays(2)]);

    $this->artisan('orders:cancel-abandoned')->assertSuccessful();

    expect($unconfirmed->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($unconfirmed->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($this->variant->fresh()->stock)->toBe(2)
        ->and($recent->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Pending)
        ->and($confirmed->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed);
});

test('one order that fails to cancel does not stop the rest of the sweep', function () {
    Exceptions::fake();
    abandonedSweepOrder(31);
    abandonedSweepOrder(40);
    $this->mock(CancelOrder::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')
        ->twice()
        ->andReturnUsing(fn () => throw new RuntimeException('Lock timeout'), fn (Order $order) => $order));

    $this->artisan('orders:cancel-abandoned')->assertSuccessful();

    Exceptions::assertReported(RuntimeException::class);
});

test('the sweep is scheduled every five minutes', function () {
    expect(Artisan::call('schedule:list'))->toBe(0);

    expect(Artisan::output())
        ->toContain('*/5 * * * *')
        ->toContain('orders:cancel-abandoned');
});
