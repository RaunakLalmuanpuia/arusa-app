<?php

namespace App\Actions;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\NewOrderReceived;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    /**
     * Create the order in one transaction: lock and re-check every variant, price from current data,
     * snapshot the items and address, and decrement stock. Any failure rolls everything back.
     *
     * @param  array{name: string, phone: string, email: ?string, line1: string, locality: string, district: string, pincode: string, payment_method: string}  $customer
     * @param  array<int, int>  $quantities  variant id => quantity
     */
    public function handle(?User $user, array $customer, array $quantities): Order
    {
        if ($quantities === []) {
            throw ValidationException::withMessages(['cart' => 'Your bag is empty.']);
        }

        $zone = ShippingZone::forPincode($customer['pincode'])
            ?? throw ValidationException::withMessages(['pincode' => "We don't deliver to this pincode yet."]);

        $paymentMethod = PaymentMethod::from($customer['payment_method']);

        $order = DB::transaction(function () use ($user, $customer, $quantities, $zone, $paymentMethod): Order {
            $variants = ProductVariant::with('product')
                ->whereKey(array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $visibleProductIds = Product::visible()->whereKey($variants->pluck('product_id'))->pluck('id');
            $subtotal = 0;

            foreach ($quantities as $variantId => $quantity) {
                $variant = $variants->get($variantId);

                if (! $variant || ! $variant->is_active || ! $visibleProductIds->contains($variant->product_id)) {
                    throw ValidationException::withMessages(['cart' => 'An item in your bag is no longer available.']);
                }

                if ($variant->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Only {$variant->stock} left of {$variant->product->name}".$this->variantSuffix($variant).'.',
                    ]);
                }

                $subtotal += $variant->effectivePrice() * $quantity;
            }

            $shipping = $zone->shippingFor($subtotal);
            $total = $subtotal + $shipping;

            if ($paymentMethod === PaymentMethod::Cod && ! $zone->allowsCod($total)) {
                throw ValidationException::withMessages(['payment_method' => "Cash on delivery isn't available for this order."]);
            }

            $order = Order::create([
                'user_id' => $user?->id,
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'customer_email' => $customer['email'] ?? null,
                'ship_line1' => $customer['line1'],
                'ship_locality' => $customer['locality'],
                'ship_district' => $customer['district'],
                'ship_pincode' => $customer['pincode'],
                'shipping_zone_id' => $zone->id,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'payment_status' => PaymentStatus::Pending,
                'fulfilment_status' => FulfilmentStatus::Confirmed,
                'placed_at' => now(),
            ]);

            foreach ($quantities as $variantId => $quantity) {
                $variant = $variants->get($variantId);

                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'size' => $variant->size,
                    'colour' => $variant->colour,
                    'unit_price' => $variant->effectivePrice(),
                    'quantity' => $quantity,
                ]);

                $variant->decrement('stock', $quantity);
            }

            if ($user) {
                $user->addresses()->firstOrCreate(Arr::only($customer, ['name', 'phone', 'line1', 'locality', 'district', 'pincode']));

                if (! $user->phone) {
                    $user->forceFill(['phone' => $customer['phone']])->save();
                }
            }

            return $order;
        });

        $order->load('items');

        if ($order->customer_email) {
            Mail::to($order->customer_email)->queue(new OrderPlaced($order));
        }

        $adminEmails = User::where('is_admin', true)->pluck('email');

        if ($adminEmails->isNotEmpty()) {
            Mail::to($adminEmails)->queue(new NewOrderReceived($order));
        }

        return $order;
    }

    private function variantSuffix(ProductVariant $variant): string
    {
        $label = collect([$variant->colour, $variant->size])->filter()->implode(' / ');

        return $label === '' ? '' : " ({$label})";
    }
}
