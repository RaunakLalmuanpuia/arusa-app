<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Order status page. The route is signed, so the link itself is the permission.
     */
    public function show(Order $order): Response
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
        ]);
    }
}
