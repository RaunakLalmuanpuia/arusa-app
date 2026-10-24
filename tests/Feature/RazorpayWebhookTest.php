<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Razorpay;
use App\Services\RazorpayException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;

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

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment)
        ->and(Payment::count())->toBe(1);
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

test('a second captured payment on a paid order is recorded and refunded once', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_A', $this->order->total))->assertNoContent();
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->with('pay_B', $this->order->total)
        ->andReturn(['id' => 'rfnd_B', 'status' => 'processed']));

    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_B', $this->order->total))->assertNoContent();
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_B', $this->order->total))->assertNoContent();

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->payment->fresh()->gateway_payment_id)->toBe('pay_A')
        ->and(Payment::where('gateway_payment_id', 'pay_B')->sole()->status)->toBe(PaymentStatus::Refunded);
    Mail::assertQueued(OrderPlaced::class, 1);
});

test('a duplicate capture reported as failed first is upgraded and refunded', function () {
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->with('pay_B', $this->order->total)
        ->andReturn(['id' => 'rfnd_B', 'status' => 'processed']));

    postRazorpayWebhook(razorpayEvent('payment.failed', 'pay_B', $this->order->total))->assertNoContent();
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_A', $this->order->total))->assertNoContent();
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_B', $this->order->total))->assertNoContent();

    expect(Payment::where('gateway_payment_id', 'pay_B')->sole()->status)->toBe(PaymentStatus::Refunded)
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a failed refund of a duplicate capture is retried on redelivery', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_A', $this->order->total))->assertNoContent();
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->andThrow(new RazorpayException('Refund failed')));

    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_B', $this->order->total))->assertStatus(503);

    expect(Payment::where('gateway_payment_id', 'pay_B')->sole()->status)->toBe(PaymentStatus::Paid);

    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->with('pay_B', $this->order->total)
        ->andReturn(['id' => 'rfnd_B', 'status' => 'processed']));

    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_B', $this->order->total))->assertNoContent();

    expect(Payment::where('gateway_payment_id', 'pay_B')->sole()->status)->toBe(PaymentStatus::Refunded);
});

test('a capture after the order was cancelled is refunded and the order stays cancelled', function () {
    $this->order->update(['payment_status' => PaymentStatus::Failed, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    $this->payment->update(['status' => PaymentStatus::Failed]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->with('pay_LATE', $this->order->total)
        ->andReturn(['id' => 'rfnd_LATE', 'status' => 'processed']));

    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_LATE', $this->order->total))->assertNoContent();

    expect($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

test('a late capture whose refund fails is answered 503 so Razorpay redelivers', function () {
    $this->order->update(['payment_status' => PaymentStatus::Failed, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    $this->payment->update(['status' => PaymentStatus::Failed]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->andThrow(new RazorpayException('Refund failed')));

    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_LATE', $this->order->total))->assertStatus(503);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

/**
 * @return array<string, mixed>
 */
function razorpayRefundEvent(string $event, string $paymentId): array
{
    return [
        'entity' => 'event',
        'event' => $event,
        'payload' => ['refund' => ['entity' => [
            'id' => 'rfnd_TEST1',
            'entity' => 'refund',
            'payment_id' => $paymentId,
            'amount' => 104900,
            'status' => $event === 'refund.processed' ? 'processed' : 'failed',
        ]]],
    ];
}

test('a failed refund puts the money back as owed so the admin can retry', function () {
    $this->order->update(['payment_status' => PaymentStatus::Refunded, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    $this->payment->update(['gateway_payment_id' => 'pay_A', 'status' => PaymentStatus::Refunded]);

    postRazorpayWebhook(razorpayRefundEvent('refund.failed', 'pay_A'))->assertNoContent();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->payment->fresh()->payload['refund']['status'])->toBe('failed')
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(Order::refundOwed()->pluck('id')->all())->toBe([$this->order->id]);
});

test('a failed refund of a duplicate capture leaves the order paid', function () {
    $this->order->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    $this->payment->update(['gateway_payment_id' => 'pay_A', 'status' => PaymentStatus::Paid]);
    $duplicate = Payment::factory()->for($this->order)->create(['gateway_payment_id' => 'pay_B', 'status' => PaymentStatus::Refunded]);

    postRazorpayWebhook(razorpayRefundEvent('refund.failed', 'pay_B'))->assertNoContent();

    expect($duplicate->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a processed refund is recorded on the payment', function () {
    $this->order->update(['payment_status' => PaymentStatus::Refunded, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    $this->payment->update(['gateway_payment_id' => 'pay_A', 'status' => PaymentStatus::Refunded]);

    postRazorpayWebhook(razorpayRefundEvent('refund.processed', 'pay_A'))->assertNoContent();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($this->payment->fresh()->payload['refund']['status'])->toBe('processed');
});

test('a refund event for an unknown payment is acknowledged and ignored', function () {
    postRazorpayWebhook(razorpayRefundEvent('refund.failed', 'pay_UNKNOWN'))->assertNoContent();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::AwaitingPayment);
});
