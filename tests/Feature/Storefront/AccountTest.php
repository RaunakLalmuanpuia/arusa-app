<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\User;

test('guests are sent to log in', function () {
    $this->get('/account')->assertRedirect('/login');
});

test('the dashboard link now goes to the account page', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/account');
});

test('the account page lists only the shopper\'s own orders, with signed links', function () {
    $user = User::factory()->create();
    $mine = Order::factory()->for($user)->create();
    Order::factory()->for(User::factory())->create();
    Order::factory()->create();

    $this->actingAs($user)
        ->get('/account')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Account/Show')
            ->has('orders', 1)
            ->where('orders.0.number', $mine->number)
            ->where('orders.0.url', $mine->trackingUrl()));
});

test('a shopper can delete their own saved address but not someone else\'s', function () {
    $user = User::factory()->create();
    $mine = Address::factory()->for($user)->create();
    $theirs = Address::factory()->create();

    $this->actingAs($user)->delete("/account/addresses/{$theirs->id}")->assertNotFound();
    $this->actingAs($user)->delete("/account/addresses/{$mine->id}")->assertRedirect();

    expect(Address::pluck('id')->all())->toBe([$theirs->id]);
});
