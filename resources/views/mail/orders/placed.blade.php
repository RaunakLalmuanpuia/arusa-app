<x-mail::message>
# Thank you, {{ $order->customer_name }}

Your order **{{ $order->number }}** is confirmed. {{ $order->payment_method === \App\Enums\PaymentMethod::Cod ? 'You pay cash on delivery' : 'Your online payment is received' }}, and we'll call {{ $order->customer_phone }} before we deliver.

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }}@if ($item->colour || $item->size) ({{ collect([$item->colour, $item->size])->filter()->implode(' / ') }})@endif | {{ $item->quantity }} | <x-paise :amount="$item->lineTotal()" /> |
@endforeach
| Shipping | | @if ($order->shipping) <x-paise :amount="$order->shipping" /> @else Free @endif |
| **Total** | | **<x-paise :amount="$order->total" />** |
</x-mail::table>

**Delivering to:** {{ $order->ship_line1 }}, {{ $order->ship_locality }}, {{ $order->ship_district }} {{ $order->ship_pincode }}

<x-mail::button :url="$trackingUrl">
Track your order
</x-mail::button>

{{ \App\Models\Setting::values()['store_name'] }}
</x-mail::message>
