<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Seed the catalog and zones, then the orders. Returns each variant's stock from before the orders.
 *
 * @return Collection<int, int>
 */
function seedRealisticOrders(): Collection
{
    Storage::fake('public');
    test()->seed([CatalogSeeder::class, ShippingZoneSeeder::class]);
    $stockBefore = ProductVariant::pluck('stock', 'id');
    test()->seed(OrderSeeder::class);

    return $stockBefore;
}

test('orders are priced from the catalog and their timeline and payments match their statuses', function () {
    seedRealisticOrders();

    $orders = Order::with(['items.variant.product', 'events', 'payments', 'shippingZone'])->get();

    expect($orders)->toHaveCount(43);

    $orders->each(function (Order $order): void {
        expect($order->items)->not->toBeEmpty();

        $order->items->each(fn ($item) => expect($item->unit_price)->toBe($item->variant->effectivePrice())
            ->and($item->product_name)->toBe($item->variant->product->name));

        expect($order->subtotal)->toBe($order->items->sum(fn ($item) => $item->unit_price * $item->quantity))
            ->and($order->shipping)->toBe($order->shippingZone->shippingFor($order->subtotal))
            ->and($order->total)->toBe($order->subtotal + $order->shipping)
            ->and($order->events->first()->only('fulfilment_status'))->toBe(['fulfilment_status' => FulfilmentStatus::Pending])
            ->and($order->events->last()->only('fulfilment_status', 'payment_status'))->toBe($order->only('fulfilment_status', 'payment_status'))
            ->and($order->events->last()->created_at->lte(now()))->toBeTrue();

        if ($order->payment_method === PaymentMethod::Cod) {
            expect($order->payments)->toBeEmpty();
        } else {
            expect($order->payments)->toHaveCount(1)
                ->and($order->payments->first()->only('status', 'amount'))->toBe(['status' => $order->payment_status, 'amount' => $order->total]);
        }
    });
});

test('stock goes down by what was ordered, except on cancelled orders', function () {
    $stockBefore = seedRealisticOrders();

    $ordered = Order::where('fulfilment_status', '!=', FulfilmentStatus::Cancelled)->with('items')->get()
        ->flatMap->items
        ->groupBy('product_variant_id')
        ->map->sum('quantity');

    ProductVariant::all()->each(fn (ProductVariant $variant) => expect($variant->stock)
        ->toBe($stockBefore[$variant->id] - ($ordered[$variant->id] ?? 0))
        ->toBeGreaterThanOrEqual(0));
});

test('orders keep to the store rules for COD, payment and fulfilment', function () {
    seedRealisticOrders();
    $orders = Order::with('shippingZone')->get();

    $orders->where('payment_method', PaymentMethod::Cod)->each(fn (Order $order) => expect($order->shippingZone->cod_allowed)->toBeTrue()
        ->and($order->total)->toBeLessThanOrEqual($order->shippingZone->cod_max ?? PHP_INT_MAX)
        ->and($order->payment_status)->toBe($order->fulfilment_status === FulfilmentStatus::Delivered ? PaymentStatus::Paid : PaymentStatus::Pending));

    // An online order is never packed or shipped unpaid.
    $orders->where('payment_method', PaymentMethod::Razorpay)
        ->whereNotIn('fulfilment_status', [FulfilmentStatus::Pending, FulfilmentStatus::Cancelled])
        ->each(fn (Order $order) => expect($order->payment_status)->toBe(PaymentStatus::Paid));

    expect(Order::refundOwed()->count())->toBe(0)
        ->and($orders->pluck('fulfilment_status')->unique()->count())->toBe(count(FulfilmentStatus::cases()));
});

test('shoppers with an account have their orders and address saved', function () {
    seedRealisticOrders();

    Order::whereNotNull('user_id')->with('user.addresses')->get()->each(fn (Order $order) => expect($order->customer_email)->toBe($order->user->email)
        ->and($order->user->phone)->toBe($order->customer_phone)
        ->and($order->user->addresses->pluck('pincode'))->toContain($order->ship_pincode));

    expect(Order::whereNotNull('user_id')->exists())->toBeTrue()
        ->and(Order::whereNull('user_id')->exists())->toBeTrue();
});

test('the admin dashboard shows today\'s orders and revenue from the seeded orders', function () {
    seedRealisticOrders();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('today.count', fn (int $count) => $count >= 3)
            ->where('revenue.last_30_days', fn (int $revenue) => $revenue > 0));
});
