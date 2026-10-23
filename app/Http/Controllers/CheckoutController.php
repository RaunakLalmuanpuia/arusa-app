<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Http\Requests\CheckoutRequest;
use App\Models\Address;
use App\Models\ShippingZone;
use App\Services\Cart;
use App\Services\Razorpay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function create(Request $request, Cart $cart, Razorpay $razorpay): Response|RedirectResponse
    {
        $summary = $cart->summary();

        if ($summary['count'] === 0 || $summary['has_unavailable']) {
            return to_route('cart.index');
        }

        $user = $request->user();
        $address = $user?->addresses()->latest('id')->first();

        return Inertia::render('Checkout/Create', [
            'prefill' => [
                'name' => $address->name ?? $user?->name ?? '',
                'phone' => $address->phone ?? $user?->phone ?? '',
                'email' => $user?->email ?? '',
                'line1' => $address->line1 ?? '',
                'locality' => $address->locality ?? '',
                'district' => $address->district ?? 'Aizawl',
                'pincode' => $address->pincode ?? '',
            ],
            'districts' => Address::DISTRICTS,
            'online_payment' => $razorpay->isConfigured(),
            'quote' => fn (): ?array => $this->quote((string) $request->query('pincode'), $summary['subtotal']),
        ]);
    }

    public function store(CheckoutRequest $request, Cart $cart, PlaceOrder $placeOrder): RedirectResponse
    {
        $lock = Cache::lock('checkout:'.$request->session()->getId(), 10);

        if (! $lock->get()) {
            throw ValidationException::withMessages(['cart' => 'Your order is already being placed.']);
        }

        try {
            $order = $placeOrder->handle($request->user(), $request->validated(), $cart->lines()->pluck('quantity', 'variant_id')->all());
            $cart->clear();
        } finally {
            $lock->release();
        }

        return redirect($order->trackingUrl())->with('order_placed', true);
    }

    /**
     * Shipping and payment options for a pincode, using the same rules PlaceOrder enforces.
     *
     * @return array{pincode: string, deliverable: bool, zone?: string, shipping?: int, total?: int, cod_available?: bool}|null
     */
    private function quote(string $pincode, int $subtotal): ?array
    {
        if (! preg_match('/^\d{6}$/', $pincode)) {
            return null;
        }

        $zone = ShippingZone::forPincode($pincode);

        if (! $zone) {
            return ['pincode' => $pincode, 'deliverable' => false];
        }

        $shipping = $zone->shippingFor($subtotal);

        return [
            'pincode' => $pincode,
            'deliverable' => true,
            'zone' => $zone->name,
            'shipping' => $shipping,
            'total' => $subtotal + $shipping,
            'cod_available' => $zone->allowsCod($subtotal + $shipping),
        ];
    }
}
