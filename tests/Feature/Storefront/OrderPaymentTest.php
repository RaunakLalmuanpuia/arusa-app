<?php

use App\Actions\MarkOrderPaid;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\NewOrderReceived;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Razorpay;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;

beforeEach(function () {
    Mail::fake();
    User::factory()->admin()->create();
    $this->order = Order::factory()->razorpay()->create(['customer_email' => 'mami@example.com']);
    $this->payment = Payment::factory()->for($this->order)->create(['gateway_order_id' => 'order_TEST1', 'amount' => $this->order->total]);
});

function confirmPayment(Order $order, string $paymentId = 'pay_TEST1', ?string $signature = null): TestResponse
{
    return test()->post(URL::signedRoute('orders.payment.store', $order), [
        'razorpay_order_id' => 'order_TEST1',
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature' => $signature ?? hash_hmac('sha256', "order_TEST1|{$paymentId}", 'test_secret'),
    ]);
}

test('a verified payment confirms the order and sends the emails', function () {
    confirmPayment($this->order)
        ->assertRedirect($this->order->trackingUrl())
        ->assertSessionHas('payment_confirmed', true);

    $order = $this->order->fresh();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->payment->fresh()->gateway_payment_id)->toBe('pay_TEST1')
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Paid);
    Mail::assertQueued(OrderPlaced::class, 1);
    Mail::assertQueued(NewOrderReceived::class, 1);
});

test('a tampered signature changes nothing', function () {
    confirmPayment($this->order, signature: 'not-a-real-signature')
        ->assertRedirect($this->order->trackingUrl())
        ->assertSessionHas('payment_error');

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
    Mail::assertNothingQueued();
});

test('the payment endpoint needs a signed link', function () {
    $this->post("/orders/{$this->order->id}/payment", [
        'razorpay_order_id' => 'order_TEST1',
        'razorpay_payment_id' => 'pay_TEST1',
        'razorpay_signature' => hash_hmac('sha256', 'order_TEST1|pay_TEST1', 'test_secret'),
    ])->assertForbidden();
});

test('confirming the same payment twice sends the emails once', function () {
    confirmPayment($this->order);
    confirmPayment($this->order);

    Mail::assertQueued(OrderPlaced::class, 1);
});

test('a payment that lands after the order was cancelled is refunded', function () {
    $this->order->update(['payment_status' => PaymentStatus::Failed, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    $this->payment->update(['status' => PaymentStatus::Failed]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->with('pay_TEST1', $this->order->total)
        ->andReturn(['id' => 'rfnd_TEST1', 'status' => 'processed']));

    app(MarkOrderPaid::class)->handle($this->order, 'order_TEST1', 'pay_TEST1', $this->order->total);

    $order = $this->order->fresh();

    expect($order->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded);
    Mail::assertNothingQueued();
});

test('a late payment whose refund failed is refunded on the next delivery', function () {
    $this->order->update(['payment_status' => PaymentStatus::Failed, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    $this->payment->update(['gateway_payment_id' => 'pay_TEST1', 'status' => PaymentStatus::Paid]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->andReturn(['id' => 'rfnd_TEST1', 'status' => 'processed']));

    app(MarkOrderPaid::class)->handle($this->order, 'order_TEST1', 'pay_TEST1', $this->order->total);

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

test('a payment for a different amount is rejected', function () {
    expect(fn () => app(MarkOrderPaid::class)->handle($this->order, 'order_TEST1', 'pay_TEST1', 100))
        ->toThrow(UnexpectedValueException::class);

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
});

test('the order page offers payment only while the order is unpaid', function () {
    $this->get($this->order->trackingUrl())
        ->assertInertia(fn ($page) => $page
            ->where('pays_on_delivery', false)
            ->where('payment.key', 'rzp_test_fake')
            ->where('payment.gateway_order_id', 'order_TEST1')
            ->where('payment.amount', $this->order->total)
            ->where('payment.prefill.contact', $this->order->customer_phone)
            ->has('payment.verify_url')
            ->where('payment_confirmed', false)
            ->where('payment_error', null));

    $this->order->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);

    $this->get($this->order->trackingUrl())
        ->assertInertia(fn ($page) => $page->where('payment', null));
});

test('the receipt for an online order says the payment is received', function () {
    $html = (new OrderPlaced($this->order->load('items')))->render();

    expect($html)->toContain('Your online payment is received')
        ->not->toContain('cash on delivery');
});
