<?php

namespace App\Http\Controllers;

use App\Actions\MarkOrderPaid;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Razorpay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $payment = $order->payments()->where('gateway_order_id', $data['razorpay_order_id'])->oldest('id')->first();

        if (! $payment || ! $razorpay->isValidPaymentSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return redirect($order->trackingUrl())
                ->with('payment_error', "We couldn't confirm that payment. If money left your account, this page will update within a few minutes.");
        }

        $markOrderPaid->handle($order, $data['razorpay_order_id'], $data['razorpay_payment_id'], $payment->amount, $data);

        return redirect($order->trackingUrl())
            ->with('payment_confirmed', $order->fresh()->payment_status === PaymentStatus::Paid);
    }
}
