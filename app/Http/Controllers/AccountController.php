<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Show', [
            'orders' => $user->orders()->latest('placed_at')->get()->map(fn (Order $order): array => [
                'number' => $order->number,
                'placed_at' => $order->placed_at->format('j M Y'),
                'total' => $order->total,
                'status' => $order->fulfilment_status->label(),
                'url' => $order->trackingUrl(),
            ]),
            'addresses' => $user->addresses()->latest('id')->get(['id', 'name', 'phone', 'line1', 'locality', 'district', 'pincode']),
        ]);
    }
}
