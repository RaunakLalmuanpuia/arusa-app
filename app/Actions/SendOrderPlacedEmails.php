<?php

namespace App\Actions;

use App\Mail\NewOrderReceived;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SendOrderPlacedEmails
{
    /**
     * Queue the receipt (when the customer gave an email) and the admin alert.
     * COD orders send these when placed; online orders when paid.
     */
    public function handle(Order $order): void
    {
        $order->loadMissing('items');

        if ($order->customer_email) {
            Mail::to($order->customer_email)->queue(new OrderPlaced($order));
        }

        $adminEmails = User::where('is_admin', true)->pluck('email');

        if ($adminEmails->isNotEmpty()) {
            Mail::to($adminEmails)->queue(new NewOrderReceived($order));
        }
    }
}
