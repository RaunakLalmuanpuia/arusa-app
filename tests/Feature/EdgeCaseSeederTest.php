<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\EdgeCaseSeeder;
use Illuminate\Support\Facades\Storage;

function edgeOrder(string $customerName): Order
{
    return Order::where('customer_name', $customerName)->sole();
}

test('the edge cases seed after the full database seeder and every order timeline ends at its statuses', function () {
    Storage::fake('public');

    $this->seed();
    $seededOrderIds = Order::pluck('id');
    $this->seed(EdgeCaseSeeder::class);

    $orders = Order::with(['items', 'events'])->whereKeyNot($seededOrderIds)->get();

    expect($orders)->toHaveCount(16);

    $orders->each(function (Order $order): void {
        expect($order->subtotal)->toBe($order->items->sum(fn ($item) => $item->unit_price * $item->quantity))
            ->and($order->total)->toBe($order->subtotal + $order->shipping)
            ->and($order->events->first()->fulfilment_status)->toBe(FulfilmentStatus::Pending)
            ->and($order->events->last()->only('fulfilment_status', 'payment_status'))
            ->toBe($order->only('fulfilment_status', 'payment_status'));
    });
});

test('orders cover every fulfilment and payment status', function () {
    $this->seed(EdgeCaseSeeder::class);

    expect(Order::distinct()->pluck('fulfilment_status')->map->value->sort()->values()->all())
        ->toBe(['cancelled', 'confirmed', 'delivered', 'dispatched', 'packed', 'pending'])
        ->and(Order::distinct()->pluck('payment_status')->map->value->sort()->values()->all())
        ->toBe(['awaiting_payment', 'failed', 'paid', 'pending', 'refunded']);
});

test('shoppers see sold-out and partly hidden products but not drafts or products in switched-off categories', function () {
    $this->seed(EdgeCaseSeeder::class);

    expect(Product::visible()->orderBy('slug')->pluck('slug')->all())->toBe([
        'half-hidden-tee', 'kids-tee', 'last-one-hoodie', 'long-name-wrap', 'price-range-tee', 'sold-out-tee',
    ]);

    $this->get('/products/sold-out-tee')->assertOk();
    $this->get('/products/orphaned-tee')->assertNotFound();
});

test('the abandoned-order sweep cancels only the stale unpaid orders and puts their stock back', function () {
    $this->seed(EdgeCaseSeeder::class);
    $stockBefore = edgeOrder('Stale COD Guest')->items->first()->variant->stock;

    $this->artisan('orders:cancel-abandoned')->assertSuccessful();

    expect(Order::where('fulfilment_status', FulfilmentStatus::Cancelled)->whereIn('customer_name', ['Stale COD Guest', 'Closed The Tab'])->count())->toBe(2)
        ->and(edgeOrder('Fresh COD Guest')->fulfilment_status)->toBe(FulfilmentStatus::Pending)
        ->and(edgeOrder('Paying Right Now')->fulfilment_status)->toBe(FulfilmentStatus::Pending)
        ->and(edgeOrder('Closed The Tab')->payment_status)->toBe(PaymentStatus::Failed)
        ->and(edgeOrder('Stale COD Guest')->items->first()->variant->stock)->toBe($stockBefore + 2);
});

test('refunds are owed for the late payment, the paid order the admin cancelled and the double capture', function () {
    $this->seed(EdgeCaseSeeder::class);

    expect(Order::refundOwed()->orderBy('customer_name')->pluck('customer_name')->all())
        ->toBe(['Changed Their Mind', 'Double Tapped Pay', 'Late Payer']);
});

test('the seeded zones decline a switched-off pincode, cap COD and ship big orders free', function () {
    $this->seed(EdgeCaseSeeder::class);
    $lunglei = ShippingZone::forPincode('796891');

    expect(ShippingZone::forPincode('796999'))->toBeNull()
        ->and($lunglei->allowsCod(300000))->toBeTrue()
        ->and($lunglei->allowsCod(300001))->toBeFalse()
        ->and(Order::where('subtotal', '>=', 299900)->pluck('shipping')->all())->toBe([0]);
});

test('the customer bag flags its unavailable lines and leaves them out of the totals', function () {
    $this->seed(EdgeCaseSeeder::class);

    $this->actingAs(User::firstWhere('email', 'customer@example.com'))
        ->get('/cart')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cart.has_unavailable', true)
            ->where('cart.subtotal', 79900 * 2 + 99900));
});

test('an admin can open every seeded order, its packing slip and its customer tracking page', function () {
    $this->seed(EdgeCaseSeeder::class);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/orders')->assertOk();

    Order::all()->each(function (Order $order) use ($admin): void {
        $this->actingAs($admin)->get("/admin/orders/{$order->id}")->assertOk();
        $this->actingAs($admin)->get("/admin/orders/{$order->id}/slip")->assertOk();
        $this->get($order->trackingUrl())->assertOk();
    });
});
