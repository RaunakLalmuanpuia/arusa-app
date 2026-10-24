<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->number }} · {{ $settings['store_name'] }}</title>
    <style>
        body { font-family: system-ui, sans-serif; color: #111; max-width: 720px; margin: 24px auto; padding: 0 16px; font-size: 14px; }
        h1 { font-size: 20px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #ddd; }
        .right { text-align: right; }
        .muted { color: #555; }
        .columns { display: flex; justify-content: space-between; gap: 24px; margin-top: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button type="button" class="no-print" onclick="window.print()">Print</button>

    <div class="columns">
        <div>
            <h1>{{ $settings['store_name'] }}</h1>
            @if ($settings['contact_phone'])
                <div class="muted">{{ $settings['contact_phone'] }}</div>
            @endif
        </div>
        <div class="right">
            <h1>{{ $order->number }}</h1>
            <div class="muted">{{ $order->placed_at->format('j M Y, g:i a') }}</div>
        </div>
    </div>

    <div class="columns">
        <div>
            <strong>Deliver to</strong><br>
            {{ $order->customer_name }} · {{ $order->customer_phone }}<br>
            {{ $order->ship_line1 }}<br>
            {{ $order->ship_locality }}<br>
            {{ $order->ship_district }} {{ $order->ship_pincode }}
        </div>
        <div class="right">
            <strong>Payment</strong><br>
            {{ $order->payment_method->label() }}<br>
            {{ $order->payment_status->label() }}
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Total</th></tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}
                        @if ($item->colour || $item->size)
                            <span class="muted">({{ collect([$item->colour, $item->size])->filter()->implode(' / ') }})</span>
                        @endif
                    </td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right"><x-paise :amount="$item->unit_price" /></td>
                    <td class="right"><x-paise :amount="$item->lineTotal()" /></td>
                </tr>
            @endforeach
            <tr><td colspan="3">Subtotal</td><td class="right"><x-paise :amount="$order->subtotal" /></td></tr>
            <tr><td colspan="3">Shipping</td><td class="right">@if ($order->shipping) <x-paise :amount="$order->shipping" /> @else Free @endif</td></tr>
            <tr><td colspan="3"><strong>Total</strong></td><td class="right"><strong><x-paise :amount="$order->total" /></strong></td></tr>
        </tbody>
    </table>

    <p class="muted">Thank you for shopping with {{ $settings['store_name'] }}.</p>
</body>
</html>
