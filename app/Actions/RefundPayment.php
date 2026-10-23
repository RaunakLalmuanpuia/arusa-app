<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Razorpay;
use App\Services\RazorpayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundPayment
{
    public function __construct(private Razorpay $razorpay) {}

    /**
     * Refund the order's captured online payment in full and record the result.
     * Calling it again after a successful refund changes nothing.
     */
    public function handle(Order $order): Payment
    {
        return DB::transaction(function () use ($order): Payment {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $payment = $order->payments()
                ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::Refunded->value])
                ->whereNotNull('gateway_payment_id')
                ->latest('id')
                ->first()
                ?? throw ValidationException::withMessages(['payment' => 'This order has no online payment to refund.']);

            if ($payment->status === PaymentStatus::Refunded) {
                return $payment;
            }

            try {
                $refund = $this->razorpay->refund($payment->gateway_payment_id, $payment->amount);
            } catch (RazorpayException $exception) {
                report($exception);

                throw ValidationException::withMessages(['payment' => "Razorpay couldn't refund this payment. Try again in a few minutes."]);
            }

            $payment->update([
                'status' => PaymentStatus::Refunded,
                'payload' => [...($payment->payload ?? []), 'refund' => $refund],
            ]);
            $order->update(['payment_status' => PaymentStatus::Refunded]);

            return $payment;
        });
    }
}
