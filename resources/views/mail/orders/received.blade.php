<x-mail::message>
# New order {{ $order->number }}

**{{ $order->customer_name }}**, {{ $order->customer_phone }}@if ($order->customer_email), {{ $order->customer_email }}@endif

{{ $order->ship_line1 }}, {{ $order->ship_locality }}, {{ $order->ship_district }} {{ $order->ship_pincode }}

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }}@if ($item->colour || $item->size) ({{ collect([$item->colour, $item->size])->filter()->implode(' / ') }})@endif | {{ $item->quantity }} | <x-paise :amount="$item->lineTotal()" /> |
@endforeach
| **Total ({{ $order->payment_method->label() }})** | | **<x-paise :amount="$order->total" />** |
</x-mail::table>

<x-mail::button :url="$trackingUrl">
View order
</x-mail::button>
</x-mail::message>
