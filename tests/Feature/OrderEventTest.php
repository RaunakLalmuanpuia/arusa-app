<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;

test('placing an order records its first status', function () {
    $order = Order::factory()->create();

    $event = $order->events()->sole();

    expect($event->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($event->payment_status)->toBe(PaymentStatus::Pending)
        ->and($event->user_id)->toBeNull();
});

test('a status change records the new statuses and who made it', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create();

    $this->actingAs($admin);
    $order->update(['fulfilment_status' => FulfilmentStatus::Packed]);

    $event = $order->events->last();

    expect($order->events()->count())->toBe(2)
        ->and($event->fulfilment_status)->toBe(FulfilmentStatus::Packed)
        ->and($event->payment_status)->toBe(PaymentStatus::Pending)
        ->and($event->user_id)->toBe($admin->id);
});

test('other changes do not add to the timeline', function () {
    $order = Order::factory()->create();

    $order->update(['tracking_note' => 'Bus parcel']);

    expect($order->events()->count())->toBe(1);
});
