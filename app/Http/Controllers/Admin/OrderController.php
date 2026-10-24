<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdvanceOrder;
use App\Actions\CancelOrder;
use App\Actions\RefundPayment;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\ShippingZone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                'shipping_zone_id' => ['nullable', 'integer', 'exists:shipping_zones,id'],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
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
                    'payment_method_label' => $order->payment_method->label(),
                    'payment_status_label' => $order->payment_status->label(),
                    'payment_status_value' => $order->payment_status->value,
                    'status' => $order->fulfilment_status->label(),
                    'status_value' => $order->fulfilment_status->value,
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

    public function show(Order $order): Response
    {
        $order->load(['items', 'payments', 'events.user:id,name', 'shippingZone:id,name']);
        $nextStatus = $order->fulfilment_status->next();
        $canAdvance = $nextStatus && ($order->payment_method === PaymentMethod::Cod || $order->payment_status === PaymentStatus::Paid);
        $message = 'Hi '.$order->customer_name.', this is '.Setting::values()['store_name']." about your order {$order->number}.";

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'placed_at' => $order->placed_at->format('j M Y, g:i a'),
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'address' => [$order->ship_line1, $order->ship_locality, "{$order->ship_district} {$order->ship_pincode}"],
                'zone' => $order->shippingZone->name,
                'payment_method' => $order->payment_method->label(),
                'payment_status' => $order->payment_status->label(),
                'fulfilment_status' => $order->fulfilment_status->label(),
                'payment_status_value' => $order->payment_status->value,
                'fulfilment_status_value' => $order->fulfilment_status->value,
                'tracking_note' => $order->tracking_note,
                'subtotal' => $order->subtotal,
                'shipping' => $order->shipping,
                'total' => $order->total,
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'variant' => collect([$item->colour, $item->size])->filter()->implode(' / '),
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->lineTotal(),
                ]),
            ],
            'payments' => $order->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'gateway_payment_id' => $payment->gateway_payment_id,
                'gateway_order_id' => $payment->gateway_order_id,
                'amount' => $payment->amount,
                'status' => $payment->status->label(),
                'status_value' => $payment->status->value,
                'created_at' => $payment->created_at->format('j M Y, g:i a'),
                'refundable' => $order->isRefundable($payment),
            ]),
            'events' => $order->events->map(fn (OrderEvent $event): array => [
                'id' => $event->id,
                'at' => $event->created_at->format('j M Y, g:i a'),
                'fulfilment_status' => $event->fulfilment_status->label(),
                'payment_status' => $event->payment_status->label(),
                'fulfilment_status_value' => $event->fulfilment_status->value,
                'payment_status_value' => $event->payment_status->value,
                'by' => $event->user?->name,
            ]),
            'next_status' => $canAdvance ? ['value' => $nextStatus->value, 'label' => $nextStatus->label()] : null,
            'can_cancel' => $order->fulfilment_status->isCancellable(),
            'whatsapp_url' => "https://wa.me/91{$order->customer_phone}?text=".rawurlencode($message),
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, AdvanceOrder $advanceOrder): RedirectResponse
    {
        $status = $request->enum('status', FulfilmentStatus::class);
        $advanceOrder->handle($order, $status, $request->validated('tracking_note'));

        return back()->with('status', "Order marked {$status->label()}.");
    }

    /**
     * Cancel and put the stock back, then refund a paid online order.
     * If the refund fails the cancel still stands, and the admin retries from the payment row.
     */
    public function cancel(Order $order, CancelOrder $cancelOrder, RefundPayment $refundPayment): RedirectResponse
    {
        $order = $cancelOrder->handle($order);

        if ($order->payment_method === PaymentMethod::Razorpay && $order->payment_status === PaymentStatus::Paid) {
            $refundPayment->handle($order);

            return back()->with('status', 'Order cancelled and payment refunded.');
        }

        return back()->with('status', 'Order cancelled.');
    }

    /**
     * Packing slip and receipt in one plain page, printed from the browser.
     */
    public function slip(Order $order): View
    {
        return view('admin.orders.slip', ['order' => $order->load('items'), 'settings' => Setting::values()]);
    }

    public function refund(Order $order, Payment $payment, RefundPayment $refundPayment): RedirectResponse
    {
        if (! $order->isRefundable($payment)) {
            throw ValidationException::withMessages(['payment' => 'Only a cancelled order or a duplicate payment can be refunded.']);
        }

        $refundPayment->handle($order, $payment->is($order->mainPayment()) ? null : $payment);

        return back()->with('status', 'Payment refunded.');
    }
}
