<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

test('the packing slip lists the order snapshot for printing', function () {
    $order = Order::factory()->create(['customer_name' => 'Mami', 'total' => 104900]);
    OrderItem::factory()->for($order)->create(['product_name' => 'Wolf Tee', 'size' => 'M', 'colour' => 'Black']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.orders.slip', $order))
        ->assertOk()
        ->assertSee($order->number)
        ->assertSee('Mami')
        ->assertSee('Wolf Tee')
        ->assertSee('Black / M')
        ->assertSee('₹1,049')
        ->assertSee('Cash on delivery');
});

test('non-admins cannot open a packing slip', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('admin.orders.slip', $order))->assertForbidden();
});

test('the packing slip shows free shipping and items without a variant', function () {
    $order = Order::factory()->create(['shipping' => 0]);
    OrderItem::factory()->for($order)->create(['product_name' => 'Tote Bag', 'size' => null, 'colour' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.orders.slip', $order))
        ->assertOk()
        ->assertSee('Tote Bag')
        ->assertDontSee('<span class="muted">(', false)
        ->assertSee('Free');
});
