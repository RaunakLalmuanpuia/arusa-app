<?php

namespace App\Actions;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\OrderDelivered;
use App\Mail\OrderDispatched;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdvanceOrder
{
    /**
     * Move the order one step along Pending → Confirmed → Packed → Dispatched → Delivered.
     * An online order must be paid first; a COD order becomes paid when it is delivered.
     * The customer is emailed when the order is dispatched and when it is delivered.
     */
    public function handle(Order $order, FulfilmentStatus $status, ?string $trackingNote = null): Order
    {
        $order = DB::transaction(function () use ($order, $status, $trackingNote): Order {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->fulfilment_status->next() !== $status) {
                throw ValidationException::withMessages(['status' => "A {$order->fulfilment_status->label()} order can't be marked {$status->label()}."]);
            }

            if ($order->payment_method === PaymentMethod::Razorpay && $order->payment_status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages(['status' => "This order isn't paid yet."]);
            }

            $order->fulfilment_status = $status;

            if ($status === FulfilmentStatus::Dispatched && $trackingNote !== null) {
                $order->tracking_note = $trackingNote;
            }

            if ($status === FulfilmentStatus::Delivered && $order->payment_method === PaymentMethod::Cod) {
                $order->payment_status = PaymentStatus::Paid;
            }

            $order->save();

            return $order;
        });

        $mail = match ($status) {
            FulfilmentStatus::Dispatched => new OrderDispatched($order),
            FulfilmentStatus::Delivered => new OrderDelivered($order),
            default => null,
        };

        if ($mail && $order->customer_email) {
            Mail::to($order->customer_email)->queue($mail);
        }

        return $order;
    }
}
