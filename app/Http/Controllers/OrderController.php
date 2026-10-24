<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Razorpay;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Order status page. The route is signed, so the link itself is the permission.
     */
    public function show(Order $order, Razorpay $razorpay): Response
    {
        $order->load('items');

        return Inertia::render('Orders/Show', [
            'order' => [
                'number' => $order->number,
                'placed_at' => $order->placed_at->format('j M Y, g:i a'),
                'payment_method' => $order->payment_method->label(),
                'payment_status' => $order->payment_status->label(),
                'fulfilment_status' => $order->fulfilment_status->label(),
                'tracking_note' => $order->tracking_note,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'address' => [$order->ship_line1, $order->ship_locality, "{$order->ship_district} {$order->ship_pincode}"],
                'subtotal' => $order->subtotal,
                'shipping' => $order->shipping,
                'total' => $order->total,
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'name' => $item->product_name,
                    'variant' => collect([$item->colour, $item->size])->filter()->implode(' / '),
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->lineTotal(),
                ]),
            ],
            'just_placed' => (bool) session('order_placed'),
            'pays_on_delivery' => $order->payment_method === PaymentMethod::Cod,
            'payment' => $order->payment_method === PaymentMethod::Razorpay && $order->payment_status === PaymentStatus::AwaitingPayment
                ? [
                    'key' => $razorpay->keyId(),
                    'gateway_order_id' => $order->placementPayment()?->gateway_order_id,
                    'amount' => $order->total,
                    'verify_url' => URL::signedRoute('orders.payment.store', $order),
                    'prefill' => ['name' => $order->customer_name, 'contact' => $order->customer_phone, 'email' => (string) $order->customer_email],
                ]
                : null,
            'payment_confirmed' => (bool) session('payment_confirmed'),
            'payment_error' => session('payment_error'),
        ]);
    }
}
