<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Razorpay;
use App\Services\RazorpayException;
use Mockery\MockInterface;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->variant = ProductVariant::factory()->create(['stock' => 2]);
});

function orderWithItem(array $attributes = [], bool $online = false): Order
{
    $factory = $online ? Order::factory()->razorpay() : Order::factory();
    $order = $factory->create(['customer_phone' => '9876543210', ...$attributes]);
    OrderItem::factory()->for($order)->create(['product_variant_id' => test()->variant->id, 'quantity' => 3]);

    return $order;
}

function paidOnlineOrder(array $attributes = []): Order
{
    $order = orderWithItem(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed, ...$attributes], online: true);
    Payment::factory()->paid()->for($order)->create(['gateway_payment_id' => 'pay_MAIN', 'amount' => $order->total]);

    return $order;
}

test('the detail page shows the order, its payments, its timeline and the next step', function () {
    $order = paidOnlineOrder();

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Show')
            ->where('order.number', $order->number)
            ->has('order.items', 1)
            ->has('payments', 1)
            ->where('payments.0.refundable', false)
            ->has('events', 1)
            ->where('next_status.value', 'packed')
            ->where('can_cancel', true)
            ->where('whatsapp_url', fn (string $url) => str_starts_with($url, 'https://wa.me/919876543210?text=')));
});

test('an unpaid online order offers no next step', function () {
    $order = orderWithItem(online: true);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('next_status', null));
});

test('the admin dispatches a packed order with a tracking note', function () {
    $order = orderWithItem(['fulfilment_status' => FulfilmentStatus::Packed]);

    $this->actingAs($this->admin)
        ->put(route('admin.orders.status.update', $order), ['status' => 'dispatched', 'tracking_note' => 'Bus parcel'])
        ->assertRedirect()
        ->assertSessionHas('status', 'Order marked Dispatched.');

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Dispatched)
        ->and($order->fresh()->tracking_note)->toBe('Bus parcel')
        ->and($order->events->last()->user_id)->toBe($this->admin->id);
});

test('a skipped step is refused', function () {
    $order = orderWithItem();

    $this->actingAs($this->admin)
        ->from(route('admin.orders.show', $order))
        ->put(route('admin.orders.status.update', $order), ['status' => 'delivered'])
        ->assertRedirect(route('admin.orders.show', $order))
        ->assertSessionHasErrors('status');

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed);
});

test('cancelling a COD order puts the stock back', function () {
    $order = orderWithItem();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.cancel', $order))
        ->assertSessionHas('status', 'Order cancelled.');

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($this->variant->fresh()->stock)->toBe(5);
});

test('cancelling a paid online order refunds it in full', function () {
    $order = paidOnlineOrder(['fulfilment_status' => FulfilmentStatus::Packed]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->with('pay_MAIN', $order->total)
        ->andReturn(['id' => 'rfnd_1', 'status' => 'processed']));

    $this->actingAs($this->admin)
        ->post(route('admin.orders.cancel', $order))
        ->assertSessionHas('status', 'Order cancelled and payment refunded.');

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($this->variant->fresh()->stock)->toBe(5);
});

test('a dispatched order cannot be cancelled', function () {
    $order = orderWithItem(['fulfilment_status' => FulfilmentStatus::Dispatched]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.cancel', $order))
        ->assertSessionHasErrors('order');

    expect($this->variant->fresh()->stock)->toBe(2);
});

test('a refund that fails during cancel leaves the order cancelled and can be retried', function () {
    $order = paidOnlineOrder();
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->andThrow(new RazorpayException('Razorpay is down')));

    $this->actingAs($this->admin)
        ->post(route('admin.orders.cancel', $order))
        ->assertSessionHasErrors('payment');

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);

    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->andReturn(['id' => 'rfnd_1', 'status' => 'processed']));

    $this->actingAs($this->admin)
        ->post(route('admin.orders.payments.refund', [$order, $order->mainPayment()]))
        ->assertSessionHas('status', 'Payment refunded.');

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

test('the main payment of an order still being fulfilled cannot be refunded', function () {
    $order = paidOnlineOrder();
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldNotReceive('refund'));

    $this->actingAs($this->admin)
        ->post(route('admin.orders.payments.refund', [$order, $order->mainPayment()]))
        ->assertSessionHasErrors('payment');
});

test('a duplicate capture is refunded on its own and the order stays paid', function () {
    $order = paidOnlineOrder();
    $duplicate = Payment::factory()->paid()->for($order)->create(['gateway_payment_id' => 'pay_DUP', 'amount' => $order->total]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')
        ->once()
        ->with('pay_DUP', $order->total)
        ->andReturn(['id' => 'rfnd_2', 'status' => 'processed']));

    $this->actingAs($this->admin)
        ->post(route('admin.orders.payments.refund', [$order, $duplicate]))
        ->assertSessionHas('status', 'Payment refunded.');

    expect($duplicate->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a payment from another order is not found', function () {
    $order = paidOnlineOrder();
    $other = Payment::factory()->paid()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.payments.refund', [$order, $other]))
        ->assertNotFound();
});

test('non-admins cannot change, cancel or refund orders', function () {
    $order = paidOnlineOrder(['fulfilment_status' => FulfilmentStatus::Cancelled]);
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('admin.orders.status.update', $order), ['status' => 'packed'])->assertForbidden();
    $this->actingAs($user)->post(route('admin.orders.cancel', $order))->assertForbidden();
    $this->actingAs($user)->post(route('admin.orders.payments.refund', [$order, $order->mainPayment()]))->assertForbidden();
});
