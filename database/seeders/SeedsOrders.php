<?php

namespace Database\Seeders;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use Illuminate\Support\Str;

/**
 * Writes finished orders straight to the database, with the item snapshots, totals, timeline and payments
 * checkout and the admin would have produced along the way.
 */
trait SeedsOrders
{
    /**
     * Status steps of a COD order, and of an online order once it is paid.
     *
     * @var list<array{0: FulfilmentStatus, 1: PaymentStatus}>
     */
    private const COD_PATH = [[FulfilmentStatus::Pending, PaymentStatus::Pending], [FulfilmentStatus::Confirmed, PaymentStatus::Pending], [FulfilmentStatus::Packed, PaymentStatus::Pending], [FulfilmentStatus::Dispatched, PaymentStatus::Pending], [FulfilmentStatus::Delivered, PaymentStatus::Paid]];

    /**
     * @var list<array{0: FulfilmentStatus, 1: PaymentStatus}>
     */
    private const ONLINE_PATH = [[FulfilmentStatus::Pending, PaymentStatus::AwaitingPayment], [FulfilmentStatus::Confirmed, PaymentStatus::Paid], [FulfilmentStatus::Packed, PaymentStatus::Paid], [FulfilmentStatus::Dispatched, PaymentStatus::Paid], [FulfilmentStatus::Delivered, PaymentStatus::Paid]];

    /**
     * The first $count steps of a status path.
     *
     * @param  list<array{0: FulfilmentStatus, 1: PaymentStatus}>  $path
     * @return list<array{0: FulfilmentStatus, 1: PaymentStatus}>
     */
    private function steps(array $path, int $count): array
    {
        return array_slice($path, 0, $count);
    }

    /**
     * An order with item snapshots, totals priced the way checkout prices them, a timeline,
     * and one Razorpay payment row per status in $payments (the first being the placement payment).
     * Timeline steps are $minutesPerStep apart, squeezed closer when the order is too recent for that.
     * Written without model events so the timeline is the one given, under DatabaseSeeder or on its own.
     * Stock is left alone.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: ProductVariant, 1: int, 2?: int}>  $lines  variant, quantity, and an older unit price to snapshot
     * @param  non-empty-list<array{0: FulfilmentStatus, 1: PaymentStatus}>  $timeline
     * @param  list<PaymentStatus>  $payments
     */
    private function order(ShippingZone $zone, array $attributes, array $lines, array $timeline, array $payments = [], int $minutesAgo = 0, int $minutesPerStep = 60): Order
    {
        $placedAt = now()->subMinutes($minutesAgo);
        $items = array_map(fn (array $line): array => [
            'product_variant_id' => $line[0]->id,
            'product_name' => $line[0]->product->name,
            'size' => $line[0]->size,
            'colour' => $line[0]->colour,
            'unit_price' => $line[2] ?? $line[0]->effectivePrice(),
            'quantity' => $line[1],
        ], $lines);
        $subtotal = array_sum(array_map(fn (array $item): int => $item['unit_price'] * $item['quantity'], $items));
        $shipping = $zone->shippingFor($subtotal);
        // The order's current statuses are the last step of its timeline.
        [$fulfilmentStatus, $paymentStatus] = end($timeline);
        // Keep the last step from landing in the future.
        $gap = count($timeline) > 1 ? min($minutesPerStep, intdiv($minutesAgo, count($timeline) - 1)) : 0;

        return Order::withoutEvents(function () use ($zone, $attributes, $items, $timeline, $payments, $placedAt, $subtotal, $shipping, $fulfilmentStatus, $paymentStatus, $gap): Order {
            $order = Order::factory()->create([
                // Ship to a pincode the zone covers, unless the caller gives one.
                'ship_pincode' => $zone->pincodes->first()->pincode,
                ...$attributes,
                'shipping_zone_id' => $zone->id,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $subtotal + $shipping,
                'fulfilment_status' => $fulfilmentStatus,
                'payment_status' => $paymentStatus,
                'placed_at' => $placedAt,
                'created_at' => $placedAt,
            ]);

            $order->items()->createMany($items);

            foreach ($timeline as $step => [$fulfilment, $payment]) {
                $at = $placedAt->copy()->addMinutes($step * $gap);
                $order->events()->make(['fulfilment_status' => $fulfilment, 'payment_status' => $payment])
                    ->forceFill(['created_at' => $at, 'updated_at' => $at])
                    ->save();
            }

            // Every payment row shares the first one's Razorpay order id, as a second capture would.
            // Only a captured payment (Paid or Refunded) has a Razorpay payment id.
            foreach ($payments as $status) {
                $order->payments()->create([
                    'gateway' => 'razorpay',
                    'gateway_order_id' => $order->payments()->value('gateway_order_id') ?? 'order_'.Str::random(14),
                    'gateway_payment_id' => in_array($status, [PaymentStatus::Paid, PaymentStatus::Refunded], true) ? 'pay_'.Str::random(14) : null,
                    'amount' => $order->total,
                    'status' => $status,
                ]);
            }

            return $order;
        });
    }
}
