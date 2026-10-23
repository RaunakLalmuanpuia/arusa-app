@props(['amount'])
{{ \Illuminate\Support\Number::currency($amount / 100, 'INR', 'en_IN', $amount % 100 ? 2 : 0) }}
