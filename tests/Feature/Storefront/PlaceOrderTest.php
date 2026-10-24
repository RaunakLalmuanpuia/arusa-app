<?php

use App\Actions\PlaceOrder;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Razorpay;
use App\Services\RazorpayException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

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
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::Pending)
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

test('a shortage found at order time rolls the whole order back', function () {
    $mug = ProductVariant::factory()->create(['stock' => 1]);

    expect(fn () => app(PlaceOrder::class)->handle(null, checkoutPayload(), [$this->tee->id => 1, $mug->id => 3]))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0)
        ->and($this->tee->fresh()->stock)->toBe(5)
        ->and($mug->fresh()->stock)->toBe(1);
});

test('a bag quantity above stock is ordered at the capped quantity the shopper sees', function () {
    $this->tee->update(['stock' => 2]);

    $this->withSession(['cart' => [$this->tee->id => 3]])
        ->post('/checkout', checkoutPayload())
        ->assertSessionHasNoErrors();

    expect(Order::sole()->total)->toBe(199800)
        ->and(Order::sole()->items()->sole()->quantity)->toBe(2)
        ->and($this->tee->fresh()->stock)->toBe(0);
});

test('a sold-out line in the bag is refused at checkout', function () {
    $this->tee->update(['stock' => 0]);

    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload())
        ->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0);
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

test('an online payment order reserves the stock and waits for payment', function () {
    Mail::fake();
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('createOrder')
        ->once()
        ->with(103900, Mockery::pattern('/^ARU-\d{6}$/'))
        ->andReturn(['id' => 'order_TEST1', 'amount' => 103900, 'status' => 'created']));

    $response = $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']));

    $order = Order::sole();
    $response->assertRedirect($order->trackingUrl())->assertSessionMissing('cart');

    $payment = $order->payments()->sole();

    expect($order->payment_status)->toBe(PaymentStatus::AwaitingPayment)
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::Pending)
        ->and($payment->only('gateway_order_id', 'amount'))->toBe(['gateway_order_id' => 'order_TEST1', 'amount' => 103900])
        ->and($payment->status)->toBe(PaymentStatus::AwaitingPayment)
        ->and($payment->payload['order']['id'])->toBe('order_TEST1')
        ->and($this->tee->fresh()->stock)->toBe(4);
    Mail::assertNothingQueued();
});

test('a Razorpay failure rolls the whole order back', function () {
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('createOrder')
        ->once()
        ->andThrow(new RazorpayException('Server error')));

    $this->withSession(['cart' => [$this->tee->id => 2]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']))
        ->assertSessionHasErrors('payment_method')
        ->assertSessionHas('cart');

    expect(Order::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and($this->tee->fresh()->stock)->toBe(5);
});

test('a Razorpay order without an id rolls the whole order back', function () {
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('createOrder')
        ->once()
        ->andReturn(['status' => 'created']));

    $this->withSession(['cart' => [$this->tee->id => 2]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']))
        ->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0)
        ->and($this->tee->fresh()->stock)->toBe(5);
});

test('online payment is offered beyond the cash on delivery limit', function () {
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('createOrder')
        ->once()
        ->andReturn(['id' => 'order_TEST1', 'amount' => 399600, 'status' => 'created']));

    $this->withSession(['cart' => [$this->tee->id => 4]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']))
        ->assertSessionHasNoErrors();

    expect(Order::sole()->total)->toBe(399600);
});

test('online payment is refused when Razorpay is not set up', function () {
    config(['services.razorpay.key_id' => null]);
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldNotReceive('createOrder'));

    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']))
        ->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
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

test('the order page shows the fulfilment timeline without payment-only changes', function () {
    $order = Order::factory()->create();
    $order->update(['payment_status' => PaymentStatus::Paid]);
    $order->update(['fulfilment_status' => FulfilmentStatus::Packed]);

    $this->get($order->trackingUrl())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('timeline', 2)
            ->where('timeline.0.status', 'Confirmed')
            ->where('timeline.1.status', 'Packed'));
});

test('a checkout is refused while another one is in flight for the same session', function () {
    $sessionId = Str::random(40);
    $lock = Cache::lock('checkout:'.$sessionId, 10);
    $lock->get();

    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->withCookie(config('session.cookie'), $sessionId)
        ->post('/checkout', checkoutPayload())->assertSessionHasErrors(['cart' => 'Your order is already being placed.']);

    expect(Order::count())->toBe(0)->and($this->tee->fresh()->stock)->toBe(5);
    $lock->release();
});
