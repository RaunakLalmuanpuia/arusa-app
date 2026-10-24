<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Razorpay;

test('payment signatures are checked against the key secret', function () {
    $razorpay = app(Razorpay::class);
    $signature = hash_hmac('sha256', 'order_TEST1|pay_TEST1', 'test_secret');

    expect($razorpay->isValidPaymentSignature('order_TEST1', 'pay_TEST1', $signature))->toBeTrue()
        ->and($razorpay->isValidPaymentSignature('order_TEST1', 'pay_OTHER', $signature))->toBeFalse();

    config(['services.razorpay.key_secret' => null]);

    expect($razorpay->isValidPaymentSignature('order_TEST1', 'pay_TEST1', hash_hmac('sha256', 'order_TEST1|pay_TEST1', '')))->toBeFalse();
});

test('webhook signatures are checked against the webhook secret', function () {
    $razorpay = app(Razorpay::class);
    $body = '{"event":"payment.captured"}';

    expect($razorpay->isValidWebhookSignature($body, hash_hmac('sha256', $body, 'test_webhook_secret')))->toBeTrue()
        ->and($razorpay->isValidWebhookSignature($body.' ', hash_hmac('sha256', $body, 'test_webhook_secret')))->toBeFalse();

    config(['services.razorpay.webhook_secret' => null]);

    expect($razorpay->isValidWebhookSignature($body, hash_hmac('sha256', $body, '')))->toBeFalse();
});

test('Razorpay counts as configured only with both keys', function () {
    expect(app(Razorpay::class)->isConfigured())->toBeTrue();

    config(['services.razorpay.key_secret' => null]);

    expect(app(Razorpay::class)->isConfigured())->toBeFalse();

    config(['services.razorpay.key_secret' => 'test_secret', 'services.razorpay.key_id' => null]);

    expect(app(Razorpay::class)->isConfigured())->toBeFalse();
});

test('an order keeps its payment attempts in order', function () {
    $order = Order::factory()->razorpay()->create();
    Payment::factory()->for($order)->create();
    Payment::factory()->for($order)->paid()->create(['payload' => ['payment' => ['id' => 'pay_TEST1']]]);

    expect($order->payments)->toHaveCount(2)
        ->and($order->payments->last()->status)->toBe(PaymentStatus::Paid)
        ->and($order->payments->last()->payload)->toBe(['payment' => ['id' => 'pay_TEST1']]);
});
