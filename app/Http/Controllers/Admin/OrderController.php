<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => null, 'status' => null, 'payment_method' => null, 'shipping_zone_id' => null, 'from' => null, 'to' => null,
            ...$request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', Rule::in([...array_column(FulfilmentStatus::cases(), 'value'), 'refund_owed'])],
                'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
                'shipping_zone_id' => ['nullable', 'integer'],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d'],
            ]),
        ];
        $filters['shipping_zone_id'] = $filters['shipping_zone_id'] ? (int) $filters['shipping_zone_id'] : null;

        return Inertia::render('Admin/Orders/Index', [
            'orders' => Order::query()
                ->with('shippingZone:id,name')
                ->when($filters['search'], fn (Builder $query, string $search) => preg_match('/^ARU-?(\d+)$/i', $search, $match)
                    ? $query->whereKey((int) $match[1])
                    : $query->where(fn (Builder $query) => $query->whereLike('customer_name', "%{$search}%")->orWhereLike('customer_phone', "%{$search}%")))
                ->when($filters['status'] === 'refund_owed', fn (Builder $query) => $query->refundOwed())
                ->when($filters['status'] && $filters['status'] !== 'refund_owed', fn (Builder $query) => $query->where('fulfilment_status', $filters['status']))
                ->when($filters['payment_method'], fn (Builder $query, string $method) => $query->where('payment_method', $method))
                ->when($filters['shipping_zone_id'], fn (Builder $query, $zoneId) => $query->where('shipping_zone_id', $zoneId))
                ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('placed_at', '>=', $from))
                ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('placed_at', '<=', $to))
                ->latest('placed_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Order $order): array => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'placed_at' => $order->placed_at->format('j M, g:i a'),
                    'customer' => $order->customer_name,
                    'phone' => $order->customer_phone,
                    'zone' => $order->shippingZone->name,
                    'total' => $order->total,
                    'payment' => "{$order->payment_method->label()} · {$order->payment_status->label()}",
                    'status' => $order->fulfilment_status->label(),
                ]),
            'filters' => $filters,
            'statuses' => [
                ...array_map(fn (FulfilmentStatus $status): array => ['value' => $status->value, 'label' => $status->label()], FulfilmentStatus::cases()),
                ['value' => 'refund_owed', 'label' => 'Refund owed'],
            ],
            'payment_methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
            'zones' => ShippingZone::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
