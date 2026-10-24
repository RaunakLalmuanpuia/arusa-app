<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the dashboard shows today\'s orders', function () {
    $today = Order::factory()->create();
    Order::factory()->create(['placed_at' => now()->subDays(2)]);

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('today.count', 1)
            ->where('today.orders.0.id', $today->id));
});

test('the dashboard counts orders that need action', function () {
    Order::factory()->count(2)->create();
    Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Packed]);
    Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Delivered]);
    $owed = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    Payment::factory()->paid()->for($owed)->create();

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page->where('needs_action', [
            ['label' => 'To pack', 'status' => 'confirmed', 'count' => 2],
            ['label' => 'To dispatch', 'status' => 'packed', 'count' => 1],
            ['label' => 'To deliver', 'status' => 'dispatched', 'count' => 0],
            ['label' => 'Refund owed', 'status' => 'refund_owed', 'count' => 1],
        ]));
});

test('the dashboard lists active variants with three or fewer in stock, lowest first', function () {
    $low = ProductVariant::factory()->create(['stock' => 1]);
    $edge = ProductVariant::factory()->create(['stock' => 3]);
    ProductVariant::factory()->create(['stock' => 4]);
    ProductVariant::factory()->create(['stock' => 0, 'is_active' => false]);
    ProductVariant::factory()->for(Product::factory()->create(['is_active' => false]))->create(['stock' => 1]);

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->has('low_stock', 2)
            ->where('low_stock.0.id', $low->id)
            ->where('low_stock.1.id', $edge->id));
});

test('revenue counts paid orders placed in the last 7 and 30 days', function () {
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 10000]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 20000, 'placed_at' => now()->subDays(6)]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 40000, 'placed_at' => now()->subDays(20)]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 80000, 'placed_at' => now()->subDays(40)]);
    Order::factory()->create(['payment_status' => PaymentStatus::Refunded, 'total' => 160000]);
    Order::factory()->create(['total' => 320000]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Cancelled, 'total' => 640000]);

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->where('revenue.last_7_days', 30000)
            ->where('revenue.last_30_days', 70000));
});
