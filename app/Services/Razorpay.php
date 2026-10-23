<?php

namespace App\Services;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\Error;
use Razorpay\Api\Errors\SignatureVerificationError;
use WpOrg\Requests\Exception as RequestsException;

/**
 * The only class that talks to the Razorpay SDK. Amounts are paise, as Razorpay expects.
 */
class Razorpay
{
    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    public function keyId(): string
    {
        return (string) config('services.razorpay.key_id');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RazorpayException
     */
    public function createOrder(int $amount, string $receipt): array
    {
        return $this->call(fn (Api $api): array => $api->order
            ->create(['amount' => $amount, 'currency' => 'INR', 'receipt' => $receipt])
            ->toArray());
    }

    /**
     * Refund a captured payment.
     *
     * @return array<string, mixed>
     *
     * @throws RazorpayException
     */
    public function refund(string $paymentId, int $amount): array
    {
        return $this->call(fn (Api $api): array => $api->payment
            ->fetch($paymentId)
            ->refund(['amount' => $amount])
            ->toArray());
    }

    /**
     * The signature Razorpay Checkout hands the browser after a successful payment.
     */
    public function isValidPaymentSignature(string $gatewayOrderId, string $paymentId, string $signature): bool
    {
        return $this->verifies(fn (Api $api) => $api->utility->verifyPaymentSignature([
            'razorpay_order_id' => $gatewayOrderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]));
    }

    /**
     * The X-Razorpay-Signature header: HMAC of the raw request body with the webhook secret.
     */
    public function isValidWebhookSignature(string $body, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        return $secret !== '' && $this->verifies(fn (Api $api) => $api->utility->verifyWebhookSignature($body, $signature, $secret));
    }

    /**
     * @param  callable(Api): mixed  $check
     */
    private function verifies(callable $check): bool
    {
        try {
            $check($this->api());

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }

    /**
     * @template TResult
     *
     * @param  callable(Api): TResult  $request
     * @return TResult
     *
     * @throws RazorpayException
     */
    private function call(callable $request): mixed
    {
        try {
            return $request($this->api());
        } catch (Error|RequestsException $exception) {
            throw new RazorpayException($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * The SDK keeps the key and secret in static properties, so build it fresh for every call.
     */
    private function api(): Api
    {
        return new Api((string) config('services.razorpay.key_id'), (string) config('services.razorpay.key_secret'));
    }
}
