<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingZone;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function listedOrderIds(array $query = []): array
{
    $ids = [];

    test()->actingAs(test()->admin)
        ->get(route('admin.orders.index', $query))
        ->assertOk()
        ->assertInertia(function ($page) use (&$ids) {
            $page->component('Admin/Orders/Index');
            $ids = collect($page->toArray()['props']['orders']['data'])->pluck('id')->all();
        });

    return $ids;
}

test('non-admins cannot see orders', function () {
    $this->actingAs(User::factory()->create())->get('/admin/orders')->assertForbidden();
});

test('orders are listed newest first', function () {
    $older = Order::factory()->create(['placed_at' => now()->subDay()]);
    $newer = Order::factory()->create();

    expect(listedOrderIds())->toBe([$newer->id, $older->id]);
});

test('orders filter by fulfilment status', function () {
    $packed = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Packed]);
    Order::factory()->create();

    expect(listedOrderIds(['status' => 'packed']))->toBe([$packed->id]);
});

test('orders filter by payment method and zone', function () {
    $zone = ShippingZone::factory()->create();
    $match = Order::factory()->razorpay()->create(['shipping_zone_id' => $zone->id]);
    Order::factory()->create(['shipping_zone_id' => $zone->id]);
    Order::factory()->razorpay()->create();

    expect(listedOrderIds(['payment_method' => 'razorpay', 'shipping_zone_id' => $zone->id]))->toBe([$match->id]);
});

test('orders filter by date range', function () {
    $inRange = Order::factory()->create(['placed_at' => '2026-10-10 12:00']);
    Order::factory()->create(['placed_at' => '2026-10-01 12:00']);

    expect(listedOrderIds(['from' => '2026-10-05', 'to' => '2026-10-10']))->toBe([$inRange->id]);
});

test('search finds an order by number or by phone', function () {
    $order = Order::factory()->create(['customer_phone' => '9876500001']);
    Order::factory()->create(['customer_phone' => '9876500002']);

    expect(listedOrderIds(['search' => $order->number]))->toBe([$order->id])
        ->and(listedOrderIds(['search' => '500001']))->toBe([$order->id]);
});

test('the refund owed filter finds cancelled orders still holding a payment', function () {
    $owed = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    Payment::factory()->paid()->for($owed)->create();
    $fine = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    Payment::factory()->paid()->for($fine)->create();

    expect(listedOrderIds(['status' => 'refund_owed']))->toBe([$owed->id]);
});

test('the refund owed filter finds a second capture on one order', function () {
    $order = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    Payment::factory()->paid()->for($order)->count(2)->create();

    expect(listedOrderIds(['status' => 'refund_owed']))->toBe([$order->id]);
});

test('an unknown status filter is rejected', function () {
    $this->actingAs($this->admin)->get('/admin/orders?status=lost')->assertSessionHasErrors('status');
});

test('a date range that ends before it starts is rejected', function () {
    $this->actingAs($this->admin)->get('/admin/orders?from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
});

test('an unknown zone filter is rejected', function () {
    $this->actingAs($this->admin)->get('/admin/orders?shipping_zone_id=999999')->assertSessionHasErrors('shipping_zone_id');
});

test('each listed order carries its status values for badges', function () {
    Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Packed]);

    $this->actingAs($this->admin)->get(route('admin.orders.index'))
        ->assertInertia(fn ($page) => $page
            ->where('orders.data.0.status_value', 'packed')
            ->where('orders.data.0.payment_status_value', 'paid')
            ->where('orders.data.0.payment_status_label', PaymentStatus::Paid->label())
            ->where('orders.data.0.payment_method_label', PaymentMethod::Razorpay->label()));
});
