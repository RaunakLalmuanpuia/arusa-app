<?php

namespace App\Http\Controllers;

use App\Actions\MarkOrderPaid;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Razorpay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderPaymentController extends Controller
{
    /**
     * Razorpay Checkout's success handler posts here. The signature proves the payment belongs to
     * this order's Razorpay order; the webhook confirms the same payment independently.
     */
    public function store(Request $request, Order $order, Razorpay $razorpay, MarkOrderPaid $markOrderPaid): RedirectResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:40'],
            'razorpay_payment_id' => ['required', 'string', 'max:40'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
        ]);

        $payment = $order->placementPayment();

        if (! $payment || $payment->gateway_order_id !== $data['razorpay_order_id'] || ! $razorpay->isValidPaymentSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return redirect($order->trackingUrl())
                ->with('payment_error', "We couldn't confirm that payment. If money left your account, this page will update within a few minutes.");
        }

        try {
            $markOrderPaid->handle($order, $data['razorpay_order_id'], $data['razorpay_payment_id'], $payment->amount, $data);
        } catch (ValidationException) {
            // Refunding a late or duplicate payment failed. It is already reported, and the webhook redelivery retries it.
        }

        $order->refresh();

        if ($order->fulfilment_status === FulfilmentStatus::Cancelled) {
            return redirect($order->trackingUrl())
                ->with('payment_confirmed', false)
                ->with('payment_error', 'This order was cancelled before your payment arrived, so the full amount is being refunded. Refunds reach your account in 5–7 working days.');
        }

        return redirect($order->trackingUrl())
            ->with('payment_confirmed', $order->payment_status === PaymentStatus::Paid);
    }
}
