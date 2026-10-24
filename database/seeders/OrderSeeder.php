<?php

namespace Database\Seeders;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

class OrderSeeder extends Seeder
{
    use SeedsOrders;

    private Randomizer $random;

    /**
     * Regular shoppers: name, phone, address line, locality, district, pincode, and an email when they have an account.
     * Pincodes fall inside the demo zones from ShippingZoneSeeder.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string|null}>
     */
    private const SHOPPERS = [
        ['Lalremruati Sailo', '9862031457', 'House No. 14, Main Road', 'Chanmari', 'Aizawl', '796007', 'lalremruati@example.com'],
        ['Vanlalhriatpuia', '8794112360', 'Near Dawrpui Church', 'Dawrpui', 'Aizawl', '796001', 'hriatpuia@example.com'],
        ['Zothanpuii Ralte', '9436198742', 'House No. 102, Upper Ramhlun', 'Ramhlun North', 'Aizawl', '796012', 'zothanpuii@example.com'],
        ['Rebecca Vanlalruati', '9089654321', 'Second floor, above the pharmacy', 'Mission Vengthlang', 'Aizawl', '796005', 'rebecca.vl@example.com'],
        ['Lalhmingsangi', '8731905562', 'House No. 7, Bazar Veng', 'Bazar Veng', 'Lunglei', '796701', 'lalhmingsangi@example.com'],
        ['Malsawmtluanga Hnamte', '7005843219', 'Bawngkawn Bus Stand Road', 'Bawngkawn', 'Aizawl', '796014', null],
        ['Esther Lalhmangaihi', '9612457830', 'House No. 51, Chaltlang Lily Veng', 'Chaltlang', 'Aizawl', '796012', null],
        ['C. Lalrinfela', '9774321568', 'Opposite the Khatla playground', 'Khatla', 'Aizawl', '796001', null],
        ['Lalnunmawia Khiangte', '8414967203', 'House No. 230, Tlangnuam', 'Tlangnuam', 'Aizawl', '796005', null],
        ['H. Lalthanzuala', '9863217754', 'Durtlang Leitan, near the school', 'Durtlang', 'Aizawl', '796015', null],
        ['Lalbiakdiki', '6909123487', 'Zarkawt Main Street', 'Zarkawt', 'Aizawl', '796001', null],
        ['Joseph Lalramliana', '9856034412', 'House No. 3, Vengthlang', 'Vengthlang', 'Champhai', '796321', null],
        ['Rosangpuii Pachuau', '9402187365', 'Diakkawn, behind the post office', 'Diakkawn', 'Kolasib', '796181', null],
    ];

    /**
     * About two months of ordinary trade on the CatalogSeeder products: 40 orders, more of them recent, plus three
     * from the last few hours of today. Statuses follow each order's age (old ones delivered, new ones still waiting on the admin),
     * with the odd cancellation, abandoned online payment and refund. Stock goes down for every order that wasn't cancelled.
     * Its own seeded randomizer gives the same orders on every fresh seed, whatever ran before; only today's three move with the clock.
     */
    public function run(): void
    {
        $this->random = new Randomizer(new Mt19937(796001));

        $zones = ShippingZone::with('pincodes')->get();
        $variants = ProductVariant::with('product')->where('is_active', true)->orderBy('id')->get();
        $accounts = $this->seedAccounts();

        $minutesSinceMidnight = max(1, (int) today()->diffInMinutes(now()));
        $ages = [
            // Skewed towards recent days (about a quarter in the last four), like a shop that is picking up.
            ...array_map(fn (): int => (int) ($this->random->getFloat(0, 1) ** 2 * 60 * 24 * 60), range(1, 40)),
            // Today, still waiting on the shopper or the admin. Kept after midnight so the dashboard counts them as today's.
            ...array_map(fn (int $minutes): int => min($minutes, $minutesSinceMidnight - 1), [12, 45, 150]),
        ];
        rsort($ages);

        foreach ($ages as $minutesAgo) {
            [$name, $phone, $line1, $locality, $district, $pincode, $email] = $this->pick(self::SHOPPERS);
            $zone = $zones->first(fn (ShippingZone $zone): bool => $zone->pincodes->contains('pincode', $pincode));
            $lines = $this->pickLines($variants);

            $subtotal = array_sum(array_map(fn (array $line): int => $line[0]->effectivePrice() * $line[1], $lines));
            $total = $subtotal + $zone->shippingFor($subtotal);
            $isCod = $this->chance(55) && $zone->allowsCod($total);
            $timeline = $this->timeline($isCod, $minutesAgo);
            [$fulfilmentStatus, $paymentStatus] = end($timeline);

            $this->order($zone, [
                'user_id' => $email ? $accounts[$email]->id : null,
                'customer_name' => $name,
                'customer_phone' => $phone,
                'customer_email' => $email,
                'ship_line1' => $line1,
                'ship_locality' => $locality,
                'ship_district' => $district,
                'ship_pincode' => $pincode,
                'payment_method' => $isCod ? PaymentMethod::Cod : PaymentMethod::Razorpay,
                'tracking_note' => in_array($fulfilmentStatus, [FulfilmentStatus::Dispatched, FulfilmentStatus::Delivered], true)
                    ? ($district === 'Aizawl' ? 'With our rider, who will call before arriving.' : 'India Post speed post EM'.$this->random->getInt(100000000, 999999999).'IN')
                    : null,
            ], $lines, $timeline, $isCod ? [] : [$paymentStatus], $minutesAgo, minutesPerStep: 60 * 20);

            if ($fulfilmentStatus !== FulfilmentStatus::Cancelled) {
                foreach ($lines as [$variant, $quantity]) {
                    $variant->decrement('stock', $quantity);
                }
            }
        }
    }

    /**
     * Accounts for the shoppers who signed up, each with their address saved the way checkout saves it.
     *
     * @return array<string, User> keyed by email
     */
    private function seedAccounts(): array
    {
        $accounts = [];

        foreach (self::SHOPPERS as [$name, $phone, $line1, $locality, $district, $pincode, $email]) {
            if ($email === null) {
                continue;
            }

            $accounts[$email] = User::factory()->create(['name' => $name, 'email' => $email, 'phone' => $phone]);
            Address::factory()->for($accounts[$email])->create(compact('name', 'phone', 'line1', 'locality', 'district', 'pincode'));
        }

        return $accounts;
    }

    /**
     * One to three different in-stock variants, mostly one of each.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return list<array{0: ProductVariant, 1: int}>
     */
    private function pickLines(Collection $variants): array
    {
        $inStock = $variants->filter(fn (ProductVariant $variant): bool => $variant->stock > 0)->values()->all();
        $picked = array_map(fn (int $key): ProductVariant => $inStock[$key], $this->random->pickArrayKeys($inStock, min(count($inStock), $this->pick([1, 1, 1, 2, 2, 3]))));

        return array_map(fn (ProductVariant $variant): array => [$variant, min($variant->stock, $this->pick([1, 1, 1, 1, 2]))], $picked);
    }

    /**
     * Where an order of this age has got to. COD waits for a phone confirmation; an online order is confirmed
     * the moment it is paid. After that it moves on roughly a day per step until it is delivered.
     *
     * @return non-empty-list<array{0: FulfilmentStatus, 1: PaymentStatus}>
     */
    private function timeline(bool $isCod, int $minutesAgo): array
    {
        $path = $isCod ? self::COD_PATH : self::ONLINE_PATH;

        // Online, still on the payment page.
        if (! $isCod && $minutesAgo < 20) {
            return $this->steps($path, 1);
        }

        // Online, never paid: the sweep cancelled it after 30 minutes.
        if (! $isCod && $minutesAgo >= 30 && $this->chance(8)) {
            return [[FulfilmentStatus::Pending, PaymentStatus::AwaitingPayment], [FulfilmentStatus::Cancelled, PaymentStatus::Failed]];
        }

        // The shopper cancelled after confirming; an online payment was refunded.
        if ($minutesAgo > 60 * 24 * 3 && $this->chance(8)) {
            $paid = $path[1][1];

            return [...$this->steps($path, 2), [FulfilmentStatus::Cancelled, $paid], ...($isCod ? [] : [[FulfilmentStatus::Cancelled, PaymentStatus::Refunded]])];
        }

        $hours = $minutesAgo / 60;

        return $this->steps($path, match (true) {
            $hours < 3 => $isCod ? 1 : 2,
            $hours < 24 => 2,
            $hours < 48 => 3,
            $hours < 96 => 4,
            default => 5,
        });
    }

    /**
     * True $percent times in a hundred.
     */
    private function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }

    /**
     * One item of a list, at random.
     *
     * @template T
     *
     * @param  non-empty-array<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->random->pickArrayKeys($items, 1)[0]];
    }
}
