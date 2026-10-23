<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;

beforeEach(function () {
    $this->tee = ProductVariant::factory()
        ->for(Product::factory()->create(['name' => 'Wolf Tee', 'base_price' => 99900]))
        ->create(['size' => 'M', 'colour' => 'Black', 'stock' => 5]);
    $this->zone = ShippingZone::factory()->withPincodes('796001')->create(['rate' => 4000, 'free_above' => 150000, 'cod_max' => 300000]);
});

function checkoutPayload(array $overrides = []): array
{
    return [
        'name' => 'Lalremruata', 'phone' => '9876543210', 'email' => '',
        'line1' => 'House 12, Main Road', 'locality' => 'Zarkawt', 'district' => 'Aizawl', 'pincode' => '796001',
        'payment_method' => 'cod',
        ...$overrides,
    ];
}

test('a guest places a cash on delivery order', function () {
    $response = $this->withSession(['cart' => [$this->tee->id => 2]])->post('/checkout', checkoutPayload());

    $order = Order::sole();
    $response->assertRedirect($order->trackingUrl())->assertSessionMissing('cart');

    expect($order->only('subtotal', 'shipping', 'total', 'customer_phone', 'ship_pincode', 'user_id'))
        ->toBe(['subtotal' => 199800, 'shipping' => 0, 'total' => 199800, 'customer_phone' => '9876543210', 'ship_pincode' => '796001', 'user_id' => null])
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($order->items()->sole()->only('product_name', 'size', 'colour', 'unit_price', 'quantity'))
        ->toBe(['product_name' => 'Wolf Tee', 'size' => 'M', 'colour' => 'Black', 'unit_price' => 99900, 'quantity' => 2])
        ->and($this->tee->fresh()->stock)->toBe(3);
});

test('a deleted variant in a guest bag is dropped and the rest still checks out', function () {
    $this->withSession(['cart' => [$this->tee->id => 1, 999999 => 2]])
        ->post('/checkout', checkoutPayload())
        ->assertSessionHasNoErrors();

    expect(Order::sole()->items)->toHaveCount(1);
});

test('shipping is charged below the free-above amount', function () {
    $this->withSession(['cart' => [$this->tee->id => 1]])->post('/checkout', checkoutPayload());

    expect(Order::sole()->only('shipping', 'total'))->toBe(['shipping' => 4000, 'total' => 103900]);
});

test('a shortage rolls the whole order back', function () {
    $mug = ProductVariant::factory()->create(['stock' => 1]);

    $this->withSession(['cart' => [$this->tee->id => 1, $mug->id => 3]])
        ->post('/checkout', checkoutPayload())
        ->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0)
        ->and($this->tee->fresh()->stock)->toBe(5)
        ->and($mug->fresh()->stock)->toBe(1);
});

test('an item that was hidden after it was added cannot be ordered', function () {
    $this->tee->product->update(['is_active' => false]);

    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload())
        ->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0);
});

test('an empty cart cannot be checked out', function () {
    $this->post('/checkout', checkoutPayload())->assertSessionHasErrors('cart');
});

test('a pincode outside every zone is rejected', function () {
    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload(['pincode' => '110001']))
        ->assertSessionHasErrors(['pincode' => "We don't deliver to this pincode yet."]);
});

test('cash on delivery is refused above the zone limit or when switched off', function () {
    $this->withSession(['cart' => [$this->tee->id => 4]])
        ->post('/checkout', checkoutPayload())
        ->assertSessionHasErrors('payment_method');

    Setting::create(['key' => 'cod_enabled', 'value' => '0']);

    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload())
        ->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
});

test('the phone must be a 10-digit Indian mobile number', function (string $phone) {
    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload(['phone' => $phone]))
        ->assertSessionHasErrors('phone');
})->with(['12345', '5123456789', '+919876543210', '98765432101']);

test('online payment is not offered yet', function () {
    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']))
        ->assertSessionHasErrors('payment_method');
});

test('a signed-in shopper gets the order, a saved address and a phone on file', function () {
    $user = User::factory()->create();
    $user->cartItems()->create(['product_variant_id' => $this->tee->id, 'quantity' => 1]);

    $this->actingAs($user)->post('/checkout', checkoutPayload());

    expect(Order::sole()->user_id)->toBe($user->id)
        ->and($user->cartItems()->count())->toBe(0)
        ->and($user->addresses()->sole()->line1)->toBe('House 12, Main Road')
        ->and($user->fresh()->phone)->toBe('9876543210');

    $user->cartItems()->create(['product_variant_id' => $this->tee->id, 'quantity' => 1]);
    $this->actingAs($user)->post('/checkout', checkoutPayload());

    expect($user->addresses()->count())->toBe(1);
});

test('the order page needs a valid signature', function () {
    $order = Order::factory()->create();

    $this->get("/orders/{$order->id}")->assertForbidden();
    $this->get($order->trackingUrl().'x')->assertForbidden();
    $this->get($order->trackingUrl())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Orders/Show')
            ->where('order.number', $order->number)
            ->where('order.fulfilment_status', 'Confirmed')
            ->where('just_placed', false));
});
