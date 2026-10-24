<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackOrderRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TrackOrderController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Orders/Track');
    }

    /**
     * Order number + phone is the credential; a match goes to the signed status page.
     */
    public function store(TrackOrderRequest $request): RedirectResponse
    {
        $order = Order::whereKey($request->orderId())
            ->where('customer_phone', $request->validated('phone'))
            ->first();

        if ($order === null) {
            return back()->withErrors(['number' => "We couldn't find an order with those details."])->onlyInput('number', 'phone');
        }

        return redirect()->to($order->trackingUrl());
    }
}
