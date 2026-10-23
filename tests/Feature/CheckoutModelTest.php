<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\ShippingZone;

test('a zone is found by pincode only while active', function () {
    $aizawl = ShippingZone::factory()->withPincodes('796001', '796005')->create();
    ShippingZone::factory()->withPincodes('796701')->create(['is_active' => false]);

    expect(ShippingZone::forPincode('796005')?->id)->toBe($aizawl->id)
        ->and(ShippingZone::forPincode('796701'))->toBeNull()
        ->and(ShippingZone::forPincode('110001'))->toBeNull();
});

test('shipping is free at or above the free-above amount', function () {
    $zone = ShippingZone::factory()->make(['rate' => 5000, 'free_above' => 100000]);
    $noFreeShipping = ShippingZone::factory()->make(['rate' => 5000, 'free_above' => null]);

    expect($zone->shippingFor(99999))->toBe(5000)
        ->and($zone->shippingFor(100000))->toBe(0)
        ->and($noFreeShipping->shippingFor(10000000))->toBe(5000);
});

test('cash on delivery needs the zone flag, the zone limit and the store setting', function () {
    $zone = ShippingZone::factory()->make(['cod_allowed' => true, 'cod_max' => 300000]);

    expect($zone->allowsCod(300000))->toBeTrue()
        ->and($zone->allowsCod(300001))->toBeFalse()
        ->and(ShippingZone::factory()->make(['cod_allowed' => false])->allowsCod(100))->toBeFalse()
        ->and(ShippingZone::factory()->make(['cod_max' => null])->allowsCod(99999999))->toBeTrue();

    Setting::create(['key' => 'cod_enabled', 'value' => '0']);

    expect($zone->allowsCod(100))->toBeFalse();
});

test('order numbers are the padded id', function () {
    $order = Order::factory()->create();

    expect($order->number)->toBe('ARU-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT));
});

test('an order item line total is unit price times quantity', function () {
    expect(OrderItem::factory()->make(['unit_price' => 99900, 'quantity' => 3])->lineTotal())->toBe(299700);
});
