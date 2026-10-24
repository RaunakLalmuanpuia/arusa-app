<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // shortcut: loads all of today's orders to count and list them; fine at tens per day, paginate if that grows to hundreds.
        $todayOrders = Order::whereDate('placed_at', today())->latest('placed_at')->latest('id')->get();

        return Inertia::render('Admin/Dashboard', [
            'today' => [
                'count' => $todayOrders->count(),
                'orders' => $todayOrders->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'customer' => $order->customer_name,
                    'total' => $order->total,
                    'status' => $order->fulfilment_status->label(),
                    'status_value' => $order->fulfilment_status->value,
                ]),
            ],
            'needs_action' => [
                [
                    'label' => 'To confirm by phone',
                    'filters' => ['status' => FulfilmentStatus::Pending->value, 'payment_method' => PaymentMethod::Cod->value],
                    'count' => Order::where('fulfilment_status', FulfilmentStatus::Pending)->where('payment_method', PaymentMethod::Cod)->count(),
                ],
                ['label' => 'To pack', 'filters' => ['status' => FulfilmentStatus::Confirmed->value], 'count' => Order::where('fulfilment_status', FulfilmentStatus::Confirmed)->count()],
                ['label' => 'To dispatch', 'filters' => ['status' => FulfilmentStatus::Packed->value], 'count' => Order::where('fulfilment_status', FulfilmentStatus::Packed)->count()],
                ['label' => 'To deliver', 'filters' => ['status' => FulfilmentStatus::Dispatched->value], 'count' => Order::where('fulfilment_status', FulfilmentStatus::Dispatched)->count()],
                ['label' => 'Refund owed', 'filters' => ['status' => 'refund_owed'], 'count' => Order::refundOwed()->count()],
            ],
            'low_stock' => ProductVariant::with('product:id,name')
                ->where('is_active', true)
                ->whereRelation('product', 'is_active', true)
                ->where('stock', '<=', 3)
                ->orderBy('stock')
                ->orderBy('id')
                ->limit(20)
                ->get()
                ->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'name' => $variant->product->name,
                    'variant' => collect([$variant->colour, $variant->size])->filter()->implode(' / '),
                    'sku' => $variant->sku,
                    'stock' => $variant->stock,
                ]),
            'revenue' => [
                'last_7_days' => $this->revenueSince(6),
                'last_30_days' => $this->revenueSince(29),
                'daily' => $this->dailyRevenue(30),
            ],
        ]);
    }

    /**
     * Money received: paid orders placed from $days days before today up to now.
     */
    private function revenueSince(int $days): int
    {
        return (int) Order::where('payment_status', PaymentStatus::Paid)
            ->where('fulfilment_status', '!=', FulfilmentStatus::Cancelled)
            ->where('placed_at', '>=', today()->subDays($days))
            ->sum('total');
    }

    /**
     * Money received per day for the last $days days, oldest first, with empty days as zero.
     *
     * @return list<array{date: string, label: string, value: int}>
     */
    private function dailyRevenue(int $days): array
    {
        $totals = Order::where('payment_status', PaymentStatus::Paid)
            ->where('fulfilment_status', '!=', FulfilmentStatus::Cancelled)
            ->where('placed_at', '>=', today()->subDays($days - 1))
            ->selectRaw('DATE(placed_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range($days - 1, 0))
            ->map(function (int $daysAgo) use ($totals): array {
                $day = today()->subDays($daysAgo);

                return [
                    'date' => $day->format('Y-m-d'),
                    'label' => $day->format('j M'),
                    'value' => (int) ($totals[$day->format('Y-m-d')] ?? 0),
                ];
            })
            ->all();
    }
}
