<?php

use App\Actions\CancelOrder;
use App\Actions\RefundPayment;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Services\Razorpay;
use App\Services\RazorpayException;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

beforeEach(function () {
    $this->variant = ProductVariant::factory()->create(['stock' => 2]);
    $this->order = Order::factory()->razorpay()->create();
    OrderItem::factory()->for($this->order)->create(['product_variant_id' => $this->variant->id, 'quantity' => 3]);
    $this->payment = Payment::factory()->for($this->order)->create(['amount' => $this->order->total]);
});

test('cancelling an unpaid online order puts the stock back and fails the payment', function () {
    app(CancelOrder::class)->handle($this->order);

    $order = $this->order->fresh();

    expect($order->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($order->payment_status)->toBe(PaymentStatus::Failed)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->variant->fresh()->stock)->toBe(5);
});

test('cancelling twice puts the stock back once', function () {
    app(CancelOrder::class)->handle($this->order);
    app(CancelOrder::class)->handle($this->order);

    expect($this->variant->fresh()->stock)->toBe(5);
});

test('a dispatched order cannot be cancelled', function () {
    $this->order->update(['fulfilment_status' => FulfilmentStatus::Dispatched]);

    expect(fn () => app(CancelOrder::class)->handle($this->order))->toThrow(ValidationException::class);

    expect($this->variant->fresh()->stock)->toBe(2);
});

test('the unpaid-order sweep leaves an order that was just paid', function () {
    $this->order->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);

    app(CancelOrder::class)->handle($this->order, onlyIfAwaitingPayment: true);

    expect($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->variant->fresh()->stock)->toBe(2);
});

test('a captured online payment is refunded in full', function () {
    $this->order->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    $this->payment->update(['gateway_payment_id' => 'pay_TEST1', 'status' => PaymentStatus::Paid]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->with('pay_TEST1', $this->order->total)
        ->andReturn(['id' => 'rfnd_TEST1', 'status' => 'processed']));

    app(RefundPayment::class)->handle($this->order);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($this->payment->fresh()->payload['refund']['id'])->toBe('rfnd_TEST1')
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

test('a refund is only sent once', function () {
    $this->order->update(['payment_status' => PaymentStatus::Paid]);
    $this->payment->update(['gateway_payment_id' => 'pay_TEST1', 'status' => PaymentStatus::Paid]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->with('pay_TEST1', $this->order->total)
        ->andReturn(['id' => 'rfnd_TEST1', 'status' => 'processed']));

    app(RefundPayment::class)->handle($this->order);
    app(RefundPayment::class)->handle($this->order);
});

test('a failed refund leaves the payment as paid', function () {
    $this->order->update(['payment_status' => PaymentStatus::Paid]);
    $this->payment->update(['gateway_payment_id' => 'pay_TEST1', 'status' => PaymentStatus::Paid]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->andThrow(new RazorpayException('Refund failed')));

    expect(fn () => app(RefundPayment::class)->handle($this->order))->toThrow(ValidationException::class);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('an order without a captured payment cannot be refunded', function () {
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldNotReceive('refund'));

    expect(fn () => app(RefundPayment::class)->handle($this->order))->toThrow(ValidationException::class);
});
