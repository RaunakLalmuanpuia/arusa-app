<?php

namespace Database\Seeders;

use App\Enums\FulfilmentStatus;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EdgeCaseSeeder extends Seeder
{
    /**
     * Status steps of a COD order, and of an online order once it is paid.
     *
     * @var list<array{0: FulfilmentStatus, 1: PaymentStatus}>
     */
    private const COD_PATH = [[FulfilmentStatus::Pending, PaymentStatus::Pending], [FulfilmentStatus::Confirmed, PaymentStatus::Pending], [FulfilmentStatus::Packed, PaymentStatus::Pending], [FulfilmentStatus::Dispatched, PaymentStatus::Pending], [FulfilmentStatus::Delivered, PaymentStatus::Paid]];

    /**
     * @var list<array{0: FulfilmentStatus, 1: PaymentStatus}>
     */
    private const ONLINE_PATH = [[FulfilmentStatus::Pending, PaymentStatus::AwaitingPayment], [FulfilmentStatus::Confirmed, PaymentStatus::Paid], [FulfilmentStatus::Packed, PaymentStatus::Paid], [FulfilmentStatus::Dispatched, PaymentStatus::Paid], [FulfilmentStatus::Delivered, PaymentStatus::Paid]];

    /**
     * Awkward but valid data for checking every storefront and admin state by hand: hidden and sold-out products,
     * odd zones, a customer with a stale bag, and an order in every fulfilment and payment state (with its timeline).
     * Self-contained, so it runs on its own or after the other seeders. Products here have no photos.
     */
    public function run(): void
    {
        $variants = $this->seedCatalog();
        [$lunglei, $retiredZone] = $this->seedZones();
        $customer = $this->seedCustomers($variants);

        $guest = ['user_id' => null, 'customer_email' => null];
        $member = ['user_id' => $customer->id, 'customer_name' => $customer->name, 'customer_phone' => $customer->phone, 'customer_email' => $customer->email];
        $tee = [[$variants['price-range-tee-m'], 1]];

        // COD
        $this->order($lunglei, ['customer_name' => 'Fresh COD Guest', ...$guest], $tee, $this->steps(self::COD_PATH, 1), minutesAgo: 10);
        $this->order($lunglei, ['customer_name' => 'Stale COD Guest', ...$guest], $tee, $this->steps(self::COD_PATH, 1), minutesAgo: 60 * 48);
        $this->order($lunglei, $member, $tee, $this->steps(self::COD_PATH, 2), minutesAgo: 60 * 20);
        $this->order($lunglei, ['customer_name' => 'Bulk Buyer', 'customer_email' => 'bulk@example.com'], [[$variants['price-range-tee-s'], 2]], $this->steps(self::COD_PATH, 3), minutesAgo: 60 * 30);
        $this->order($lunglei, [...$member, 'tracking_note' => "India Post EM123456789IN\nLeft Aizawl on Monday."], $tee, $this->steps(self::COD_PATH, 4), minutesAgo: 60 * 72);
        // Snapshot prices and names that no longer match the live catalog, including a product since taken down.
        $this->order($lunglei, $member, [[$variants['price-range-tee-m'], 2, 89900], [$variants['draft-tee-m'], 1]], $this->steps(self::COD_PATH, 5), minutesAgo: 60 * 24 * 10);
        // Placed in a zone that has since been switched off.
        $this->order($retiredZone, ['customer_name' => 'Cancelled COD Guest', ...$guest], $tee, [...$this->steps(self::COD_PATH, 3), [FulfilmentStatus::Cancelled, PaymentStatus::Pending]], minutesAgo: 60 * 24 * 5);

        // Online
        $online = ['payment_method' => PaymentMethod::Razorpay];
        $this->order($lunglei, ['customer_name' => 'Paying Right Now', ...$guest, ...$online], $tee, $this->steps(self::ONLINE_PATH, 1), [PaymentStatus::AwaitingPayment], minutesAgo: 5);
        $this->order($lunglei, ['customer_name' => 'Closed The Tab', ...$guest, ...$online], $tee, $this->steps(self::ONLINE_PATH, 1), [PaymentStatus::AwaitingPayment], minutesAgo: 120);
        $this->order($lunglei, [...$member, ...$online], $tee, $this->steps(self::ONLINE_PATH, 2), [PaymentStatus::Paid], minutesAgo: 60 * 3);
        $this->order($lunglei, ['customer_name' => 'Abandoned Online', ...$guest, ...$online], $tee, [[FulfilmentStatus::Pending, PaymentStatus::AwaitingPayment], [FulfilmentStatus::Cancelled, PaymentStatus::Failed]], [PaymentStatus::Failed], minutesAgo: 60 * 24 * 2);
        // Paid after the sweep cancelled it, and the automatic refund failed: refund owed.
        $this->order($lunglei, ['customer_name' => 'Late Payer', ...$guest, ...$online], $tee, [[FulfilmentStatus::Pending, PaymentStatus::AwaitingPayment], [FulfilmentStatus::Cancelled, PaymentStatus::Failed]], [PaymentStatus::Paid], minutesAgo: 60 * 24 * 3);
        // Cancelled by the admin after payment, refund not yet sent: refund owed.
        $this->order($lunglei, ['customer_name' => 'Changed Their Mind', 'customer_email' => 'refund-me@example.com', ...$online], $tee, [...$this->steps(self::ONLINE_PATH, 3), [FulfilmentStatus::Cancelled, PaymentStatus::Paid]], [PaymentStatus::Paid], minutesAgo: 60 * 24 * 4);
        $this->order($lunglei, [...$member, ...$online], $tee, [...$this->steps(self::ONLINE_PATH, 2), [FulfilmentStatus::Cancelled, PaymentStatus::Paid], [FulfilmentStatus::Cancelled, PaymentStatus::Refunded]], [PaymentStatus::Refunded], minutesAgo: 60 * 24 * 6);
        // Captured twice on one Razorpay order: the second capture is owed back.
        $this->order($lunglei, ['customer_name' => 'Double Tapped Pay', 'customer_email' => 'double@example.com', ...$online], $tee, $this->steps(self::ONLINE_PATH, 5), [PaymentStatus::Paid, PaymentStatus::Paid], minutesAgo: 60 * 24 * 8);
        // Subtotal over the zone's free-shipping threshold.
        $this->order($lunglei, [...$member, ...$online], [[$variants['price-range-tee-xl'], 3]], $this->steps(self::ONLINE_PATH, 4), [PaymentStatus::Paid], minutesAgo: 60 * 24);
    }

    /**
     * @return array<string, ProductVariant> keyed "{product slug}-{lowercase size}"
     */
    private function seedCatalog(): array
    {
        $edge = Category::factory()->create(['name' => 'Edge Cases', 'slug' => 'edge-cases', 'sort_order' => 9]);
        Category::factory()->create(['name' => 'Empty Shelf', 'slug' => 'empty-shelf', 'parent_id' => $edge->id]);
        $retired = Category::factory()->inactive()->create(['name' => 'Retired', 'slug' => 'retired', 'sort_order' => 10]);
        $retiredTees = Category::factory()->create(['name' => 'Retired Tees', 'slug' => 'retired-tees', 'parent_id' => $retired->id]);

        $products = [
            // slug, name, category, product active, [size => [stock, price override, variant active]]
            ['sold-out-tee', 'Sold Out Tee', $edge, true, ['S' => [0], 'M' => [0]]],
            ['last-one-hoodie', 'Last One Hoodie', $edge, true, ['M' => [1], 'L' => [0]]],
            ['price-range-tee', 'Price Range Tee', $edge, true, ['S' => [20, 79900], 'M' => [20], 'XL' => [20, 129900]]],
            ['half-hidden-tee', 'Half Hidden Tee', $edge, true, ['S' => [5], 'M' => [5, null, false]]],
            ['all-variants-off-tee', 'All Variants Off Tee', $edge, true, ['M' => [5, null, false]]],
            ['draft-tee', 'Draft Tee', $edge, false, ['M' => [5]]],
            ['retired-tee', 'Retired Tee', $retired, true, ['M' => [5]]],
            ['orphaned-tee', 'Orphaned Tee', $retiredTees, true, ['M' => [5]]],
            ['long-name-wrap', 'Hand-Loomed Mizo Puan Wrap Skirt With Extra-Long Tassels & Contrast Border – Limited Festive Run (Ṭawng Edition)', $edge, true, ['M' => [5]]],
            ['kids-tee', "Pu Lala's Kid's Tee <b>\"Ngaihtuahna\"</b>", $edge, true, ['XS' => [5]]],
        ];

        $variants = [];

        foreach ($products as [$slug, $name, $category, $isActive, $sizes]) {
            $product = Product::factory()->for($category)->create([
                'name' => $name, 'slug' => $slug, 'gender' => Gender::Unisex, 'base_price' => 99900, 'is_active' => $isActive,
            ]);

            foreach ($sizes as $size => $spec) {
                [$stock, $priceOverride, $isVariantActive] = $spec + [1 => null, 2 => true];

                $variants["{$slug}-".strtolower($size)] = $product->variants()->create([
                    'sku' => strtoupper("EDGE-{$slug}-{$size}"),
                    'size' => $size,
                    'price_override' => $priceOverride,
                    'stock' => $stock,
                    'is_active' => $isVariantActive,
                ]);
            }
        }

        return $variants;
    }

    /**
     * A zone with a COD limit and free shipping, and a switched-off zone whose pincode no longer delivers.
     *
     * @return array{0: ShippingZone, 1: ShippingZone}
     */
    private function seedZones(): array
    {
        return [
            ShippingZone::factory()->withPincodes('796891', '796901')
                ->create(['name' => 'Lunglei (COD up to ₹3,000)', 'rate' => 15000, 'free_above' => 299900, 'cod_allowed' => true, 'cod_max' => 300000]),
            ShippingZone::factory()->withPincodes('796999')
                ->create(['name' => 'Retired zone', 'is_active' => false]),
        ];
    }

    /**
     * A regular customer with two addresses and a bag gone stale, an unverified sign-up, and one with nothing yet.
     *
     * @param  array<string, ProductVariant>  $variants
     */
    private function seedCustomers(array $variants): User
    {
        $customer = User::factory()->create(['name' => 'Lalremruata Customer', 'email' => 'customer@example.com', 'phone' => '9862012345']);
        Address::factory()->for($customer)->create(['name' => $customer->name, 'phone' => $customer->phone]);
        Address::factory()->for($customer)->create(['name' => 'Office', 'phone' => '7005012345', 'locality' => 'Bazar', 'district' => 'Lunglei', 'pincode' => '796891']);

        // In stock, now sold out, a hidden variant, and more than the one left.
        foreach (['price-range-tee-s' => 2, 'sold-out-tee-m' => 1, 'half-hidden-tee-m' => 1, 'last-one-hoodie-m' => 3] as $key => $quantity) {
            $customer->cartItems()->create(['product_variant_id' => $variants[$key]->id, 'quantity' => $quantity]);
        }

        User::factory()->unverified()->create(['name' => 'Unverified Sign-up', 'email' => 'unverified@example.com']);
        User::factory()->create(['name' => 'Brand New Customer', 'email' => 'new@example.com']);

        return $customer;
    }

    /**
     * The first $count steps of a status path.
     *
     * @param  list<array{0: FulfilmentStatus, 1: PaymentStatus}>  $path
     * @return list<array{0: FulfilmentStatus, 1: PaymentStatus}>
     */
    private function steps(array $path, int $count): array
    {
        return array_slice($path, 0, $count);
    }

    /**
     * An order with item snapshots, totals priced the way checkout prices them, a timeline an hour per step,
     * and one Razorpay payment row per status in $payments (the first being the placement payment).
     * Written without model events so the timeline is the one given, under DatabaseSeeder or on its own.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: ProductVariant, 1: int, 2?: int}>  $lines  variant, quantity, and an older unit price to snapshot
     * @param  non-empty-list<array{0: FulfilmentStatus, 1: PaymentStatus}>  $timeline
     * @param  list<PaymentStatus>  $payments
     */
    private function order(ShippingZone $zone, array $attributes, array $lines, array $timeline, array $payments = [], int $minutesAgo = 0): void
    {
        $placedAt = now()->subMinutes($minutesAgo);
        $items = array_map(fn (array $line): array => [
            'product_variant_id' => $line[0]->id,
            'product_name' => $line[0]->product->name,
            'size' => $line[0]->size,
            'colour' => $line[0]->colour,
            'unit_price' => $line[2] ?? $line[0]->effectivePrice(),
            'quantity' => $line[1],
        ], $lines);
        $subtotal = array_sum(array_map(fn (array $item): int => $item['unit_price'] * $item['quantity'], $items));
        $shipping = $zone->shippingFor($subtotal);
        [$fulfilmentStatus, $paymentStatus] = end($timeline);

        Order::withoutEvents(function () use ($zone, $attributes, $items, $timeline, $payments, $placedAt, $subtotal, $shipping, $fulfilmentStatus, $paymentStatus): void {
            $order = Order::factory()->create([
                'ship_pincode' => $zone->pincodes->first()->pincode,
                ...$attributes,
                'shipping_zone_id' => $zone->id,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $subtotal + $shipping,
                'fulfilment_status' => $fulfilmentStatus,
                'payment_status' => $paymentStatus,
                'placed_at' => $placedAt,
                'created_at' => $placedAt,
            ]);

            $order->items()->createMany($items);

            foreach ($timeline as $step => [$fulfilment, $payment]) {
                $at = $placedAt->copy()->addHours($step);
                $order->events()->make(['fulfilment_status' => $fulfilment, 'payment_status' => $payment])
                    ->forceFill(['created_at' => $at, 'updated_at' => $at])
                    ->save();
            }

            foreach ($payments as $status) {
                $order->payments()->create([
                    'gateway' => 'razorpay',
                    'gateway_order_id' => $order->payments()->value('gateway_order_id') ?? 'order_'.Str::random(14),
                    'gateway_payment_id' => in_array($status, [PaymentStatus::Paid, PaymentStatus::Refunded], true) ? 'pay_'.Str::random(14) : null,
                    'amount' => $order->total,
                    'status' => $status,
                ]);
            }
        });
    }
}
