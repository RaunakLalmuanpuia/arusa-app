<?php

use App\Mail\NewOrderReceived;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Razorpay;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->admin()->create(['email' => 'owner@arusa.test']);
    $this->variant = ProductVariant::factory()->create();
    ShippingZone::factory()->withPincodes('796001')->create();
});

function placeOrderWithEmail(string $email): void
{
    test()->withSession(['cart' => [test()->variant->id => 1]])->post('/checkout', [
        'name' => 'Mami', 'phone' => '9876543210', 'email' => $email,
        'line1' => 'House 1', 'locality' => 'Chanmari', 'district' => 'Aizawl', 'pincode' => '796001',
        'payment_method' => 'cod',
    ]);
}

test('the customer and the admins are emailed when an order is placed', function () {
    placeOrderWithEmail('mami@example.com');

    Mail::assertQueued(OrderPlaced::class, fn (OrderPlaced $mail) => $mail->hasTo('mami@example.com'));
    Mail::assertQueued(NewOrderReceived::class, fn (NewOrderReceived $mail) => $mail->hasTo('owner@arusa.test'));
});

test('without an email only the admins hear about the order', function () {
    placeOrderWithEmail('');

    Mail::assertNotQueued(OrderPlaced::class);
    Mail::assertQueued(NewOrderReceived::class);
});

test('the receipt shows the order number, prices in rupees and the tracking link', function () {
    $order = Order::factory()->create(['subtotal' => 129900, 'shipping' => 4000, 'total' => 133900]);
    OrderItem::factory()->for($order)->create(['product_name' => 'Wolf Tee', 'unit_price' => 129900, 'quantity' => 1]);

    (new OrderPlaced($order))
        ->assertSeeInHtml($order->number)
        ->assertSeeInHtml('Wolf Tee')
        ->assertSeeInHtml('₹1,339')
        ->assertSeeInHtml(e($order->trackingUrl()), false);
});

test('the receipt table renders one row per line and a bold total', function () {
    $order = Order::factory()->create(['subtotal' => 129900, 'shipping' => 4000, 'total' => 133900]);
    OrderItem::factory()->for($order)->create(['unit_price' => 129900, 'quantity' => 1]);

    foreach ([new OrderPlaced($order), new NewOrderReceived($order)] as $mail) {
        $html = $mail->render();

        expect($html)->not->toContain('**')->toMatch('/<strong[^>]*>₹1,339<\/strong>/');
    }
});

test('an online order sends no email until it is paid', function () {
    $this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('createOrder')
        ->once()
        ->andReturn(['id' => 'order_TEST1', 'status' => 'created']));

    test()->withSession(['cart' => [test()->variant->id => 1]])->post('/checkout', [
        'name' => 'Mami', 'phone' => '9876543210', 'email' => 'mami@example.com',
        'line1' => 'House 1', 'locality' => 'Chanmari', 'district' => 'Aizawl', 'pincode' => '796001',
        'payment_method' => 'razorpay',
    ])->assertSessionHasNoErrors();

    Mail::assertNothingQueued();
});
