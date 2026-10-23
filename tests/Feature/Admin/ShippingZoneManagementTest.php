<?php

use App\Models\Order;
use App\Models\ShippingZone;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function zonePayload(array $overrides = []): array
{
    return [
        'name' => 'Aizawl',
        'rate' => 4000,
        'free_above' => 149900,
        'cod_allowed' => true,
        'cod_max' => null,
        'is_active' => true,
        'pincodes' => "796001, 796005\n796005 796007",
        ...$overrides,
    ];
}

test('admin can create a zone and pincodes are parsed and de-duplicated', function () {
    $this->actingAs($this->admin)->post('/admin/shipping-zones', zonePayload())->assertRedirect('/admin/shipping-zones');

    $zone = ShippingZone::firstWhere('name', 'Aizawl');
    expect($zone->pincodes()->pluck('pincode')->all())->toBe(['796001', '796005', '796007'])
        ->and($zone->rate)->toBe(4000);
});

test('a pincode can belong to only one zone', function () {
    ShippingZone::factory()->withPincodes('796005')->create();

    $this->actingAs($this->admin)
        ->post('/admin/shipping-zones', zonePayload())
        ->assertSessionHasErrors('pincodes.1');
});

test('pincodes must be six digits', function () {
    $this->actingAs($this->admin)
        ->post('/admin/shipping-zones', zonePayload(['pincodes' => '79600']))
        ->assertSessionHasErrors('pincodes.0');
});

test('updating a zone keeps its own pincodes valid and replaces the list', function () {
    $zone = ShippingZone::factory()->withPincodes('796001', '796002')->create();

    $this->actingAs($this->admin)
        ->put("/admin/shipping-zones/{$zone->id}", zonePayload(['pincodes' => '796002 796003']))
        ->assertRedirect('/admin/shipping-zones')
        ->assertSessionHasNoErrors();

    expect($zone->pincodes()->pluck('pincode')->all())->toBe(['796002', '796003']);
});

test('admin can find a zone by pincode', function () {
    ShippingZone::factory()->withPincodes('796701')->create(['name' => 'Lunglei']);
    ShippingZone::factory()->withPincodes('796001')->create(['name' => 'Aizawl']);

    $this->actingAs($this->admin)
        ->get('/admin/shipping-zones?search=796701')
        ->assertInertia(fn ($page) => $page
            ->component('Admin/ShippingZones/Index')
            ->has('zones.data', 1)
            ->where('zones.data.0.name', 'Lunglei'));
});

test('a zone with orders cannot be deleted', function () {
    $order = Order::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/shipping-zones/{$order->shipping_zone_id}")
        ->assertSessionHasErrors('zone');

    expect(ShippingZone::count())->toBe(1);
});

test('non-admins cannot manage zones', function () {
    $this->actingAs(User::factory()->create())->post('/admin/shipping-zones', zonePayload())->assertForbidden();
});
