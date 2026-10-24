<?php

use App\Models\Order;

beforeEach(function () {
    $this->order = Order::factory()->create(['customer_phone' => '9876543210']);
});

test('the track page renders', function () {
    $this->get('/track')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Orders/Track'));
});

test('a matching number and phone open the order page', function (string $number) {
    $this->post('/track', ['number' => $number, 'phone' => '9876543210'])
        ->assertRedirect($this->order->trackingUrl());
})->with([
    'full number' => fn () => $this->order->number,
    'lowercase short' => fn () => 'aru-'.$this->order->id,
    'bare id' => fn () => (string) $this->order->id,
]);

test('a wrong phone or unknown number gets one generic error', function (array $input) {
    $this->from('/track')->post('/track', $input)
        ->assertRedirect('/track')
        ->assertSessionHasErrors(['number' => "We couldn't find an order with those details."]);
})->with([
    'wrong phone' => fn () => ['number' => $this->order->number, 'phone' => '9123456780'],
    'unknown number' => fn () => ['number' => 'ARU-999999', 'phone' => '9876543210'],
]);

test('malformed input is rejected by validation', function () {
    $this->from('/track')->post('/track', ['number' => 'hello', 'phone' => '12345'])
        ->assertRedirect('/track')
        ->assertSessionHasErrors(['number', 'phone']);
});

test('lookups are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/track', ['number' => 'ARU-999999', 'phone' => '9876543210']);
    }

    $this->post('/track', ['number' => $this->order->number, 'phone' => '9876543210'])
        ->assertTooManyRequests();
});
