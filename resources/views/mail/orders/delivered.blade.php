<x-mail::message>
# Delivered

Hi {{ $order->customer_name }}, order **{{ $order->number }}** has been delivered. Thank you for shopping with us!

<x-mail::button :url="$trackingUrl">
View your order
</x-mail::button>

{{ \App\Models\Setting::values()['store_name'] }}
</x-mail::message>
