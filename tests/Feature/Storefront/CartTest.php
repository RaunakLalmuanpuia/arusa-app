<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

function cartVariant(array $attributes = []): ProductVariant
{
    return ProductVariant::factory()->for(Product::factory()->create(['base_price' => 99900]))->create($attributes);
}

test('a guest can add an in-stock variant to the session cart', function () {
    $variant = cartVariant();

    $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 2])
        ->assertRedirect()
        ->assertSessionHas("cart.{$variant->id}", 2);
});

test('quantities are capped at stock and at ten per line', function () {
    $low = cartVariant(['stock' => 3]);
    $plenty = cartVariant(['stock' => 50]);

    $this->withSession(['cart' => [$low->id => 2]])
        ->post('/cart', ['variant_id' => $low->id, 'quantity' => 5])
        ->assertSessionHas("cart.{$low->id}", 3);

    $this->withSession(['cart' => [$plenty->id => 8]])
        ->post('/cart', ['variant_id' => $plenty->id, 'quantity' => 5])
        ->assertSessionHas("cart.{$plenty->id}", 10);
});

test('unavailable variants cannot be added', function (ProductVariant $variant) {
    $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1])
        ->assertSessionHasErrors('variant_id');
})->with([
    'sold out' => fn () => cartVariant(['stock' => 0]),
    'inactive variant' => fn () => cartVariant(['is_active' => false]),
    'hidden product' => fn () => ProductVariant::factory()->for(Product::factory()->inactive())->create(),
]);

test('the cart page shows live prices and totals from the server', function () {
    $variant = cartVariant();
    $variant->update(['price_override' => 79900]);

    $this->withSession(['cart' => [$variant->id => 2]])
        ->get('/cart')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cart/Index')
            ->where('cart.count', 2)
            ->where('cart.subtotal', 159800)
            ->where('cart.lines.0.unit_price', 79900)
            ->where('cart.lines.0.available', true));
});

test('a line that became unavailable is flagged and left out of the totals', function () {
    $available = cartVariant();
    $gone = cartVariant();
    $gone->update(['is_active' => false]);

    $this->withSession(['cart' => [$available->id => 1, $gone->id => 1]])
        ->get('/cart')
        ->assertInertia(fn ($page) => $page
            ->where('cart.count', 1)
            ->where('cart.subtotal', 99900)
            ->where('cart.has_unavailable', true));
});

test('a signed-in shopper keeps the cart in the database', function () {
    $user = User::factory()->create();
    $variant = cartVariant();

    $this->actingAs($user)->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
    $this->actingAs($user)->patch("/cart/{$variant->id}", ['quantity' => 4]);

    expect($user->cartItems()->sole()->quantity)->toBe(4);

    $this->actingAs($user)->delete("/cart/{$variant->id}");

    expect($user->cartItems()->count())->toBe(0);
});

test('updating a variant that is not in the cart is not found', function () {
    $this->patch('/cart/'.cartVariant()->id, ['quantity' => 2])->assertNotFound();
});

test('the guest cart merges into the saved cart on login', function () {
    $user = User::factory()->create();
    $both = cartVariant(['stock' => 4]);
    $guestOnly = cartVariant();
    $user->cartItems()->create(['product_variant_id' => $both->id, 'quantity' => 3]);

    $this->withSession(['cart' => [$both->id => 2, $guestOnly->id => 1]])
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionMissing('cart');

    expect($user->cartItems()->pluck('quantity', 'product_variant_id')->all())
        ->toEqual([$both->id => 4, $guestOnly->id => 1]);
});
