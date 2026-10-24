<x-mail::message>
# Your order is on its way

Hi {{ $order->customer_name }}, order **{{ $order->number }}** has left us and is on its way to {{ $order->ship_locality }}, {{ $order->ship_district }}.
@if ($order->tracking_note)

**Delivery details:** {{ $order->tracking_note }}
@endif
@if ($order->payment_method === \App\Enums\PaymentMethod::Cod)

Please keep <x-paise :amount="$order->total" /> ready to pay on delivery.
@endif

<x-mail::button :url="$trackingUrl">
Track your order
</x-mail::button>

{{ \App\Models\Setting::values()['store_name'] }}
</x-mail::message>
