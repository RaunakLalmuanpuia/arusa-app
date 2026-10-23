<?php

namespace App\Actions;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class MarkOrderPaid
{
    public function __construct(
        private SendOrderPlacedEmails $sendOrderPlacedEmails,
        private RefundPayment $refundPayment,
    ) {}

    /**
     * The one place an online order becomes paid. The browser handler and the webhook both call it,
     * so it is idempotent. A payment that lands after the order was cancelled is refunded at once.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws UnexpectedValueException when the payment does not belong to this order or the amount differs
     */
    public function handle(Order $order, string $gatewayOrderId, string $paymentId, int $amount, array $payload = []): void
    {
        $outcome = DB::transaction(function () use ($order, $gatewayOrderId, $paymentId, $amount, $payload): string {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = $order->payments()->where('gateway_order_id', $gatewayOrderId)->oldest('id')->first();

            if (! $payment || $payment->amount !== $amount) {
                throw new UnexpectedValueException("Payment {$paymentId} does not match order {$order->number}.");
            }

            if ($payment->status === PaymentStatus::Refunded) {
                return 'already recorded';
            }

            if ($payment->status === PaymentStatus::Paid) {
                // A late payment whose refund failed last time: try the refund again.
                return $order->fulfilment_status === FulfilmentStatus::Cancelled ? 'order cancelled' : 'already recorded';
            }

            $payment->update([
                'gateway_payment_id' => $paymentId,
                'status' => PaymentStatus::Paid,
                'payload' => [...($payment->payload ?? []), 'payment' => $payload],
            ]);

            if ($order->fulfilment_status === FulfilmentStatus::Cancelled) {
                return 'order cancelled';
            }

            $order->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);

            return 'paid';
        });

        if ($outcome === 'paid') {
            $this->sendOrderPlacedEmails->handle($order->fresh());
        }

        if ($outcome === 'order cancelled') {
            $this->refundPayment->handle($order);
        }
    }
}
