<?php

namespace App\Actions;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    /**
     * Cancel the order and put its stock back. An unpaid online payment is marked Failed.
     * Refunding a paid order is a separate step (RefundPayment).
     *
     * With $onlyIfPending the order is left alone unless it is still Pending (unpaid online, or COD not yet
     * confirmed by phone), so the sweep never cancels an order that was paid or confirmed a moment ago.
     */
    public function handle(Order $order, bool $onlyIfPending = false): Order
    {
        return DB::transaction(function () use ($order, $onlyIfPending): Order {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->fulfilment_status === FulfilmentStatus::Cancelled
                || ($onlyIfPending && $order->fulfilment_status !== FulfilmentStatus::Pending)) {
                return $order;
            }

            if (! $order->fulfilment_status->isCancellable()) {
                throw ValidationException::withMessages(['order' => "This order can't be cancelled once it has been dispatched."]);
            }

            $items = $order->items()->get();
            $variants = ProductVariant::whereKey($items->pluck('product_variant_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $variants->get($item->product_variant_id)?->increment('stock', $item->quantity);
            }

            if ($order->payment_status === PaymentStatus::AwaitingPayment) {
                $order->payments()
                    ->where('status', PaymentStatus::AwaitingPayment->value)
                    ->update(['status' => PaymentStatus::Failed->value]);
                $order->payment_status = PaymentStatus::Failed;
            }

            $order->fulfilment_status = FulfilmentStatus::Cancelled;
            $order->save();

            return $order;
        });
    }
}
