<?php

use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;

beforeEach(function () {
    $this->variant = ProductVariant::factory()->for(Product::factory()->create(['base_price' => 100000]))->create();
    $this->cart = ['cart' => [$this->variant->id => 1]];
    ShippingZone::factory()->withPincodes('796001')->create([
        'name' => 'Aizawl', 'rate' => 4000, 'free_above' => 150000, 'cod_allowed' => true, 'cod_max' => 120000,
    ]);
});

test('an empty cart goes back to the cart page', function () {
    $this->get('/checkout')->assertRedirect('/cart');
});

test('a cart with an unavailable line goes back to the cart page', function () {
    $this->variant->update(['is_active' => false]);

    $this->withSession($this->cart)->get('/checkout')->assertRedirect('/cart');
});

test('the quote prices shipping and cash on delivery for a known pincode', function () {
    $this->withSession($this->cart)
        ->get('/checkout?pincode=796001')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/Create')
            ->where('quote.pincode', '796001')
            ->where('quote.deliverable', true)
            ->where('quote.zone', 'Aizawl')
            ->where('quote.shipping', 4000)
            ->where('quote.total', 104000)
            ->where('quote.cod_available', true));
});

test('shipping is free and cod is withheld above the zone limits', function () {
    $this->withSession(['cart' => [$this->variant->id => 2]])
        ->get('/checkout?pincode=796001')
        ->assertInertia(fn ($page) => $page
            ->where('quote.shipping', 0)
            ->where('quote.total', 200000)
            ->where('quote.cod_available', false));
});

test('an unknown pincode is not deliverable and no pincode gives no quote', function () {
    $this->withSession($this->cart)
        ->get('/checkout?pincode=110001')
        ->assertInertia(fn ($page) => $page->where('quote.deliverable', false));

    $this->withSession($this->cart)
        ->get('/checkout')
        ->assertInertia(fn ($page) => $page->where('quote', null));
});

test('a signed-in shopper gets their last address filled in', function () {
    $user = User::factory()->create();
    $user->cartItems()->create(['product_variant_id' => $this->variant->id, 'quantity' => 1]);
    Address::factory()->for($user)->create(['line1' => 'Old house']);
    Address::factory()->for($user)->create(['line1' => 'New house', 'phone' => '9876543210']);

    $this->actingAs($user)
        ->get('/checkout')
        ->assertInertia(fn ($page) => $page
            ->where('prefill.line1', 'New house')
            ->where('prefill.phone', '9876543210')
            ->where('prefill.email', $user->email)
            ->has('districts', 11));
});
