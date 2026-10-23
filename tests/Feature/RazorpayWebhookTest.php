<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Mail::fake();
    $this->order = Order::factory()->razorpay()->create(['customer_email' => 'mami@example.com']);
    $this->payment = Payment::factory()->for($this->order)->create(['gateway_order_id' => 'order_TEST1', 'amount' => $this->order->total]);
});

/**
 * @return array<string, mixed>
 */
function razorpayEvent(string $event, string $paymentId, int $amount, string $gatewayOrderId = 'order_TEST1'): array
{
    return [
        'entity' => 'event',
        'event' => $event,
        'payload' => ['payment' => ['entity' => [
            'id' => $paymentId,
            'entity' => 'payment',
            'order_id' => $gatewayOrderId,
            'amount' => $amount,
            'currency' => 'INR',
            'status' => $event === 'payment.captured' ? 'captured' : 'failed',
        ]]],
    ];
}

function postRazorpayWebhook(array $event, ?string $signature = null): TestResponse
{
    $body = json_encode($event);

    return test()->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, 'test_webhook_secret'),
    ], $body);
}

test('a captured payment confirms the order', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', $this->order->total))->assertNoContent();

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->payment->fresh()->gateway_payment_id)->toBe('pay_TEST1');
    Mail::assertQueued(OrderPlaced::class, 1);
});

test('a replayed capture is recorded once', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', $this->order->total));
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', $this->order->total))->assertNoContent();

    expect(Payment::count())->toBe(1);
    Mail::assertQueued(OrderPlaced::class, 1);
});

test('a bad signature is rejected and changes nothing', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', $this->order->total), 'bad-signature')->assertStatus(400);

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
    Mail::assertNothingQueued();
});

test('a failed attempt is recorded once and the order keeps waiting', function () {
    postRazorpayWebhook(razorpayEvent('payment.failed', 'pay_FAIL1', $this->order->total))->assertNoContent();
    postRazorpayWebhook(razorpayEvent('payment.failed', 'pay_FAIL1', $this->order->total))->assertNoContent();

    expect(Payment::where('gateway_payment_id', 'pay_FAIL1')->sole()->status)->toBe(PaymentStatus::Failed)
        ->and(Payment::count())->toBe(2)
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment)
        ->and($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Pending);
});

test('events for other Razorpay orders and other event types are acknowledged and ignored', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_OTHER', 100, 'order_UNKNOWN'))->assertNoContent();
    postRazorpayWebhook(['entity' => 'event', 'event' => 'order.paid', 'payload' => []])->assertNoContent();

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
});

test('a capture for the wrong amount is reported and ignored', function () {
    Exceptions::fake();

    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', 100))->assertNoContent();

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
    Exceptions::assertReported(UnexpectedValueException::class);
});

test('a capture for an attempt first reported as failed still pays the order', function () {
    postRazorpayWebhook(razorpayEvent('payment.failed', 'pay_LATE1', $this->order->total))->assertNoContent();
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_LATE1', $this->order->total))->assertNoContent();

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->payment->fresh()->gateway_payment_id)->toBe('pay_LATE1')
        ->and($this->order->payments()->count())->toBe(1);
});
