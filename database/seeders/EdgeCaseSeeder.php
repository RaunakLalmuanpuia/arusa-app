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

class EdgeCaseSeeder extends Seeder
{
    use SeedsOrders;

    /**
     * Product page copy, keyed by slug: each says what the product is there to check.
     *
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'sold-out-tee' => 'Test product: every size is out of stock, so the card and this page should say sold out.',
        'last-one-hoodie' => 'Test product: one left in M and none in L. Adding more than one M should be capped at one.',
        'price-range-tee' => 'Test product: S is on sale at ₹799, M is the ₹999 base price and XL costs ₹1,299. The card should read "from ₹799".',
        'half-hidden-tee' => 'Test product: only S is on sale. M is switched off and should not be offered.',
        'all-variants-off-tee' => 'Test product: every size is switched off, so shoppers should never see this page.',
        'draft-tee' => 'Test product: switched off. Hidden from shoppers, but still listed in admin and in old orders.',
        'retired-tee' => 'Test product: its category is switched off, so shoppers should never see this page.',
        'orphaned-tee' => 'Test product: its own category is on but the parent category is off, so shoppers should never see this page.',
        'long-name-wrap' => 'Test product: a very long name with accented characters, to check wrapping on product cards, in the bag and on the packing slip. This description is long on purpose too. It should wrap cleanly on a narrow phone screen without pushing the price, the size picker or the add-to-bag button off the edge, and the page should still read well when the text runs to several lines.',
        'kids-tee' => 'Test product: the name has apostrophes, quotes and HTML tags. They should show as plain text, never as bold or broken markup.',
    ];

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

        // Who placed the order: a guest with no account and no email (so no emails go out), or the seeded customer.
        $guest = ['user_id' => null, 'customer_email' => null];
        $member = ['user_id' => $customer->id, 'customer_name' => $customer->name, 'customer_phone' => $customer->phone, 'customer_email' => $customer->email];
        // The default basket: one ₹999 tee, so a COD total (₹1,149 with shipping) stays inside the zone's COD limit.
        $tee = [[$variants['price-range-tee-m'], 1]];

        // Cash on delivery. Payment stays Pending until the order is delivered, then becomes Paid.

        // Placed 10 minutes ago, waiting for the admin to confirm by phone. The sweep leaves it alone (under 24 hours).
        $this->order($lunglei, ['customer_name' => 'Fresh COD Guest', ...$guest], $tee, $this->steps(self::COD_PATH, 1), minutesAgo: 10);
        // Unconfirmed for 2 days: the next orders:cancel-abandoned run cancels it and puts the stock back.
        $this->order($lunglei, ['customer_name' => 'Stale COD Guest', ...$guest], $tee, $this->steps(self::COD_PATH, 1), minutesAgo: 60 * 48);
        // Confirmed, ready to pack. Shows on the customer's account page.
        $this->order($lunglei, $member, $tee, $this->steps(self::COD_PATH, 2), minutesAgo: 60 * 20);
        // Packed. A guest who left an email, buying more than one of a sale-priced size.
        $this->order($lunglei, ['customer_name' => 'Bulk Buyer', 'customer_email' => 'bulk@example.com'], [[$variants['price-range-tee-s'], 2]], $this->steps(self::COD_PATH, 3), minutesAgo: 60 * 30);
        // Dispatched, with a multi-line tracking note for the admin page and the tracking page.
        $this->order($lunglei, [...$member, 'tracking_note' => "India Post EM123456789IN\nLeft Aizawl on Monday."], $tee, $this->steps(self::COD_PATH, 4), minutesAgo: 60 * 72);
        // Delivered and paid. Its saved prices and names no longer match the live catalog: the tee was ₹899 then,
        // and the Draft Tee has since been taken down. Order pages must show the saved values.
        $this->order($lunglei, $member, [[$variants['price-range-tee-m'], 2, 89900], [$variants['draft-tee-m'], 1]], $this->steps(self::COD_PATH, 5), minutesAgo: 60 * 24 * 10);
        // Cancelled after packing, in a zone that has since been switched off. Old orders still show their zone.
        $this->order($retiredZone, ['customer_name' => 'Cancelled COD Guest', ...$guest], $tee, [...$this->steps(self::COD_PATH, 3), [FulfilmentStatus::Cancelled, PaymentStatus::Pending]], minutesAgo: 60 * 24 * 5);

        // Online (Razorpay). The order stays Pending until the payment is captured, then becomes Confirmed and Paid.
        $online = ['payment_method' => PaymentMethod::Razorpay];

        // In the middle of paying (5 minutes ago). The sweep leaves it alone (under 30 minutes).
        $this->order($lunglei, ['customer_name' => 'Paying Right Now', ...$guest, ...$online], $tee, $this->steps(self::ONLINE_PATH, 1), [PaymentStatus::AwaitingPayment], minutesAgo: 5);
        // Left the payment unfinished 2 hours ago: the next sweep cancels it and marks the payment Failed.
        $this->order($lunglei, ['customer_name' => 'Closed The Tab', ...$guest, ...$online], $tee, $this->steps(self::ONLINE_PATH, 1), [PaymentStatus::AwaitingPayment], minutesAgo: 120);
        // Paid and confirmed, the normal happy path.
        $this->order($lunglei, [...$member, ...$online], $tee, $this->steps(self::ONLINE_PATH, 2), [PaymentStatus::Paid], minutesAgo: 60 * 3);
        // Never paid, already cancelled by the sweep. Nothing is owed.
        $this->order($lunglei, ['customer_name' => 'Abandoned Online', ...$guest, ...$online], $tee, [[FulfilmentStatus::Pending, PaymentStatus::AwaitingPayment], [FulfilmentStatus::Cancelled, PaymentStatus::Failed]], [PaymentStatus::Failed], minutesAgo: 60 * 24 * 2);
        // Paid after the sweep cancelled it, and the automatic refund failed. The order still reads Failed,
        // but its payment row is Paid: a refund is owed.
        $this->order($lunglei, ['customer_name' => 'Late Payer', ...$guest, ...$online], $tee, [[FulfilmentStatus::Pending, PaymentStatus::AwaitingPayment], [FulfilmentStatus::Cancelled, PaymentStatus::Failed]], [PaymentStatus::Paid], minutesAgo: 60 * 24 * 3);
        // Paid and packed, then cancelled by the admin. The refund hasn't been sent yet: a refund is owed.
        $this->order($lunglei, ['customer_name' => 'Changed Their Mind', 'customer_email' => 'refund-me@example.com', ...$online], $tee, [...$this->steps(self::ONLINE_PATH, 3), [FulfilmentStatus::Cancelled, PaymentStatus::Paid]], [PaymentStatus::Paid], minutesAgo: 60 * 24 * 4);
        // The same story, after the admin sent the refund. Nothing is owed.
        $this->order($lunglei, [...$member, ...$online], $tee, [...$this->steps(self::ONLINE_PATH, 2), [FulfilmentStatus::Cancelled, PaymentStatus::Paid], [FulfilmentStatus::Cancelled, PaymentStatus::Refunded]], [PaymentStatus::Refunded], minutesAgo: 60 * 24 * 6);
        // Delivered, but the shopper's payment was captured twice on one Razorpay order.
        // The first capture paid for the order; the second is owed back.
        $this->order($lunglei, ['customer_name' => 'Double Tapped Pay', 'customer_email' => 'double@example.com', ...$online], $tee, $this->steps(self::ONLINE_PATH, 5), [PaymentStatus::Paid, PaymentStatus::Paid], minutesAgo: 60 * 24 * 8);
        // Dispatched. Three ₹1,299 tees come to ₹3,897, over the zone's ₹2,999 threshold, so shipping is free.
        $this->order($lunglei, [...$member, ...$online], [[$variants['price-range-tee-xl'], 3]], $this->steps(self::ONLINE_PATH, 4), [PaymentStatus::Paid], minutesAgo: 60 * 24);
    }

    /**
     * @return array<string, ProductVariant> keyed "{product slug}-{lowercase size}"
     */
    private function seedCatalog(): array
    {
        // A visible top-level category for the odd products, with a subcategory that has no products at all.
        $edge = Category::factory()->create(['name' => 'Edge Cases', 'slug' => 'edge-cases', 'sort_order' => 9]);
        Category::factory()->create(['name' => 'Empty Shelf', 'slug' => 'empty-shelf', 'parent_id' => $edge->id]);
        // A switched-off category, and an active subcategory under it: both hide their products from shoppers.
        $retired = Category::factory()->inactive()->create(['name' => 'Retired', 'slug' => 'retired', 'sort_order' => 10]);
        $retiredTees = Category::factory()->create(['name' => 'Retired Tees', 'slug' => 'retired-tees', 'parent_id' => $retired->id]);

        // Every product is unisex at ₹999 unless a size overrides the price.
        $products = [
            // slug, name, category, product active, [size => [stock, price override, variant active]]
            // Visible but every size is out of stock: the card and product page say sold out.
            ['sold-out-tee', 'Sold Out Tee', $edge, true, ['S' => [0], 'M' => [0]]],
            // One left in M, none in L: the bag caps the quantity at 1.
            ['last-one-hoodie', 'Last One Hoodie', $edge, true, ['M' => [1], 'L' => [0]]],
            // S on sale at ₹799, M at the ₹999 base price, XL at ₹1,299: the card shows "from ₹799".
            ['price-range-tee', 'Price Range Tee', $edge, true, ['S' => [20, 79900], 'M' => [20], 'XL' => [20, 129900]]],
            // M is switched off: shoppers only see S, and an M already in a bag shows as unavailable.
            ['half-hidden-tee', 'Half Hidden Tee', $edge, true, ['S' => [5], 'M' => [5, null, false]]],
            // The product is on but no size is: hidden from shoppers.
            ['all-variants-off-tee', 'All Variants Off Tee', $edge, true, ['M' => [5, null, false]]],
            // The product itself is switched off: hidden from shoppers, still in admin and in old orders.
            ['draft-tee', 'Draft Tee', $edge, false, ['M' => [5]]],
            // In a switched-off category: hidden.
            ['retired-tee', 'Retired Tee', $retired, true, ['M' => [5]]],
            // In an active category whose parent is switched off: also hidden.
            ['orphaned-tee', 'Orphaned Tee', $retiredTees, true, ['M' => [5]]],
            // A very long name with non-ASCII characters, for wrapping on cards, in the bag and on the packing slip.
            ['long-name-wrap', 'Hand-Loomed Mizo Puan Wrap Skirt With Extra-Long Tassels & Contrast Border – Limited Festive Run (Ṭawng Edition)', $edge, true, ['M' => [5]]],
            // Apostrophes, quotes and HTML in a name: it must show as plain text, never as markup.
            ['kids-tee', "Pu Lala's Kid's Tee <b>\"Ngaihtuahna\"</b>", $edge, true, ['XS' => [5]]],
        ];

        $variants = [];

        foreach ($products as [$slug, $name, $category, $isActive, $sizes]) {
            $product = Product::factory()->for($category)->create([
                'name' => $name, 'slug' => $slug, 'description' => self::DESCRIPTIONS[$slug], 'gender' => Gender::Unisex, 'base_price' => 99900, 'is_active' => $isActive,
            ]);

            foreach ($sizes as $size => $spec) {
                // Rows may stop after the stock: no price override, variant switched on.
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
            // ₹150 shipping, free from ₹2,999; COD allowed up to a ₹3,000 total. Every seeded order except one ships here.
            ShippingZone::factory()->withPincodes('796891', '796901')
                ->create(['name' => 'Lunglei (COD up to ₹3,000)', 'rate' => 15000, 'free_above' => 299900, 'cod_allowed' => true, 'cod_max' => 300000]),
            // Switched off: checkout with 796999 says "we don't deliver", but its old order still shows it.
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
}
