<?php

use App\Actions\AdvanceOrder;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderDelivered;
use App\Mail\OrderDispatched;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Mail::fake();
});

test('a COD order moves one step at a time from phone confirmation and is paid on delivery', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Pending]);

    foreach ([FulfilmentStatus::Confirmed, FulfilmentStatus::Packed, FulfilmentStatus::Dispatched] as $status) {
        app(AdvanceOrder::class)->handle($order, $status);
        expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
    }

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Delivered);

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Delivered)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a step cannot be skipped', function () {
    $order = Order::factory()->create();

    expect(fn () => app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Dispatched))
        ->toThrow(ValidationException::class);

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed);
});

test('a cancelled order cannot move on', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Cancelled]);

    expect(fn () => app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Confirmed))
        ->toThrow(ValidationException::class);
});

test('an unpaid online order cannot be confirmed by hand', function () {
    $order = Order::factory()->razorpay()->create();

    expect(fn () => app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Confirmed))
        ->toThrow(ValidationException::class);

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Pending);
});

test('a paid online order keeps its payment status on delivery', function () {
    $order = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Dispatched]);

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Delivered);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('dispatching saves the tracking note and emails the customer', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Packed, 'customer_email' => 'mami@example.com']);

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Dispatched, 'Bus parcel, receipt 4411');

    expect($order->fresh()->tracking_note)->toBe('Bus parcel, receipt 4411');
    Mail::assertQueued(OrderDispatched::class, fn (OrderDispatched $mail) => $mail->hasTo('mami@example.com'));
});

test('delivering emails the customer', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Dispatched, 'customer_email' => 'mami@example.com']);

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Delivered);

    Mail::assertQueued(OrderDelivered::class, fn (OrderDelivered $mail) => $mail->hasTo('mami@example.com'));
});

test('no email is sent without a customer email', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Packed]);

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Dispatched);

    Mail::assertNothingQueued();
});

test('the emails render with the tracking link', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Dispatched, 'tracking_note' => 'Bus parcel']);

    expect((new OrderDispatched($order))->render())->toContain($order->number)->toContain('Bus parcel')->toContain(e($order->trackingUrl()))
        ->and((new OrderDelivered($order))->render())->toContain($order->number);
});
