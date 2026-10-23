# Phase 3: Cart & Checkout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Shoppers can fill a cart, check out as a guest or with an account, and place cash-on-delivery orders for delivery inside Mizoram. They get a confirmation page, a signed tracking link and emails; admins manage shipping zones.

**Architecture:** A `Cart` service keeps guest carts in the session and account carts in `cart_items`, merging the two on login. It is shared to every storefront page, so the header drawer and the cart page read the same data. Shipping is a zone → pincode table with rate, free-above and COD limits. The checkout page asks the server for a quote by pincode through an Inertia partial reload. A single `PlaceOrder` action does the order work in one DB transaction:
- locks the variant rows (`lockForUpdate`) and re-checks stock;
- recomputes every price;
- writes order and item snapshots and decrements stock;
- queues the emails afterwards.

Order pages use `URL::signedRoute`.

**Tech Stack:** Laravel 13.38, PHP 8.4 (intl), Inertia v2 (inertia-laravel 2.0.28, @inertiajs/vue3 2.3) + Vue 3, Tailwind 3, Pest 5 on PostgreSQL (`arusa_app_testing`), database queue, Markdown mailables.

**Spec:** `docs/superpowers/specs/2026-10-23-arusa-ecommerce-design.md` (sections 3, 4.1–4.4, 4.8, 5, 6.5, 7, 9)

## Global Constraints

- No new PHP/JS dependencies without approval. **No Lucide**; no animation library; no icon package.
- All money is stored as **integer paise**. Admin forms show rupees and convert with `toPaise()` before submit; the server only ever receives and validates paise. Format with `formatPaise()` (JS) or `<x-paise>` (Blade).
- **Prices are always recomputed server-side** from current variant prices (`ProductVariant::effectivePrice()`). The client never sends a price or a total.
- `order_items` and the order's shipping address are **snapshots**: copy values, never read live product data for past orders.
- Stock changes run in a DB transaction with `lockForUpdate()` on variant rows, locked in ascending `id` order.
- Indian mobile numbers validate with `/^[6-9]\d{9}$/` (10 digits, no +91).
- Phase 3 accepts **COD only**. COD is offered only when the store setting `cod_enabled` is on, the zone has `cod_allowed`, and the total ≤ the zone's `cod_max` (when set).
- An unknown pincode is rejected with "We don't deliver to this pincode yet."
- Emails are queued (`ShouldQueue`), never sent inline.
- Icons: inline SVG Vue components in `resources/js/Components/Icons/`.
- Motion: CSS transitions / Vue `<Transition>` only, 150–300 ms, respects `prefers-reduced-motion`.
- Design skills apply to **storefront pages only** (Tasks 4–6, 8): ui-ux-pro-max is primary; design-motion-principles for motion; impeccable for audit/polish only. Admin stays plain and functional.
- Mobile-first; storefront JS budget < ~200 KB.
- Admin routes: `routes/admin.php` behind `auth` + `can:access-admin`; every admin Form Request `authorize()` returns `$this->user()->can('access-admin')`.
- PHP: curly braces always, explicit return types, constructor promotion, PHPDoc over inline comments, TitleCase enum keys.
- After PHP changes: `vendor/bin/pint --dirty --format agent`. Run narrowest tests with `php artisan test --compact <path>`.
- Tests that need a guest cart seed the session with `->withSession(['cart' => [$variantId => $quantity]])`; the test client does not carry the session cookie between requests.
- Activate project skills while working: `arusa-commerce` (always), `laravel-best-practices` (PHP), `inertia-vue-development` (Vue pages), `tailwindcss-development` (styling), `testing-best-practices` (tests).
- Node: run `source ~/.nvm/nvm.sh && nvm use` before any npm command.

## Decisions made in this plan

- **New folders, the Laravel defaults:** `app/Enums` (`make:enum`), `app/Services` (for `Cart`), `app/Listeners` (`make:listener`, auto-discovered). *Needs owner approval per CLAUDE.md.*
- **No `carts` table.** `cart_items` carries `user_id` directly, since one user has exactly one cart. The spec lists `carts` / `cart_items`.
- **The order number is derived, not stored:** `ARU-` + the id zero-padded to 6 digits (`ARU-000123`), via an accessor. Admin search in Phase 5 parses it back to an id.
- **`addresses.user_id` is NOT NULL.** Guests don't get saved addresses; their address lives only on the order snapshot.
- **No `payments` table yet.** COD orders need none; Phase 4 adds it with Razorpay. The `PaymentMethod` / `PaymentStatus` / `FulfilmentStatus` enums are created now with all spec cases. The fulfilment transition rules come in Phase 5, with the admin order workflow.
- **Phase 3 emails are "order placed" only:** to the customer when they gave an email, and to every admin. "Dispatched" and "Delivered" need the Phase 5 workflow.
- **Districts are a fixed list** of Mizoram's 11 districts (`Address::DISTRICTS`).
- **`/dashboard` becomes a redirect to `/account`.** Breeze's controllers and tests keep using `route('dashboard')`, and the Breeze `Dashboard.vue` page is deleted.
- **App timezone becomes `Asia/Kolkata`.** It is a single-region store, and order times show local time.
- **Cart drawer state** is one shared `ref` in `resources/js/cartDrawer.js`. The product page opens the drawer after a successful add.
- **Production needs the PHP `intl` extension** (`Number::currency`). Note for Phase 6.
- **Phase 2 follow-ups folded in (Task 9):**
  - deleting a product that has orders is blocked with a friendly error;
  - the variant grid rejects a save when stock changed since the page loaded (an order came in);
  - a fully sold-out product says "Sold out" (Task 4).

## File Map

| File | Responsibility |
|---|---|
| `database/migrations/*_create_checkout_tables.php` | users.phone, shipping_zones, shipping_zone_pincodes, addresses, cart_items, orders, order_items |
| `app/Enums/{PaymentMethod,PaymentStatus,FulfilmentStatus}.php` | Order enums with `label()` |
| `app/Models/{ShippingZone,ShippingZonePincode,Address,CartItem,Order,OrderItem}.php`, `app/Models/User.php` | Models, zone pricing rules, order number, tracking URL |
| `database/factories/{ShippingZone,Address,Order,OrderItem}Factory.php`, `database/seeders/ShippingZoneSeeder.php` | Test + demo data |
| `config/app.php` | timezone |
| `app/Http/Controllers/Admin/ShippingZoneController.php`, `app/Http/Requests/Admin/ShippingZoneRequest.php`, `resources/js/Pages/Admin/ShippingZones/*` | Zone CRUD |
| `app/Services/Cart.php` | Session/DB cart, lines with live prices, login merge |
| `app/Listeners/MergeGuestCart.php` | Merge on `Login` |
| `app/Http/Controllers/CartController.php`, `resources/js/Pages/Cart/Index.vue` | Cart page + add/update/remove |
| `resources/js/Components/Store/CartDrawer.vue`, `resources/js/cartDrawer.js`, `resources/js/Components/Icons/IconBag.vue` | Drawer + header bag |
| `app/Http/Controllers/CheckoutController.php`, `app/Http/Requests/CheckoutRequest.php`, `resources/js/Pages/Checkout/Create.vue` | Checkout page, quote, submit |
| `app/Actions/PlaceOrder.php` | The order transaction |
| `app/Http/Controllers/OrderController.php`, `resources/js/Pages/Orders/Show.vue` | Signed confirmation/tracking page |
| `app/Mail/{OrderPlaced,NewOrderReceived}.php`, `resources/views/mail/orders/{placed,received}.blade.php`, `resources/views/components/paise.blade.php` | Emails |
| `app/Http/Controllers/{AccountController,AddressController}.php`, `resources/js/Pages/Account/Show.vue` | Account page |
| `app/Http/Middleware/HandleInertiaRequests.php` | + shared `cart` |
| `resources/js/Layouts/{StoreLayout,AdminLayout}.vue` | Bag button + drawer, account link, zones nav |
| `resources/js/Pages/Catalog/Product.vue` | Add to cart, sold-out label |
| `app/Http/Controllers/Admin/{ProductController,ProductVariantController}.php`, `app/Http/Requests/Admin/UpdateProductVariantsRequest.php`, `resources/js/Pages/Admin/Products/{Index.vue,Partials/VariantGrid.vue}` | Phase 2 follow-ups |
| `routes/web.php`, `routes/admin.php` | Routes |

---

### Task 1: Checkout schema, enums and models

**Files:**
- Create: `database/migrations/*_create_checkout_tables.php`
- Create: `app/Enums/PaymentMethod.php`, `app/Enums/PaymentStatus.php`, `app/Enums/FulfilmentStatus.php`
- Create: `app/Models/ShippingZone.php`, `ShippingZonePincode.php`, `Address.php`, `CartItem.php`, `Order.php`, `OrderItem.php`
- Modify: `app/Models/User.php`, `config/app.php`
- Create: `database/factories/ShippingZoneFactory.php`, `AddressFactory.php`, `OrderFactory.php`, `OrderItemFactory.php`
- Create: `database/seeders/ShippingZoneSeeder.php`; Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/CheckoutModelTest.php`

**Interfaces:**
- Produces:
  - Enums (string-backed, `label(): string`):
    - `PaymentMethod::{Cod='cod', Razorpay='razorpay'}`
    - `PaymentStatus::{Pending, AwaitingPayment, Paid, Failed, Refunded}` with snake_case values
    - `FulfilmentStatus::{Pending, Confirmed, Packed, Dispatched, Delivered, Cancelled}` with lowercase values
  - `ShippingZone`:
    - fillable `name, rate, free_above, cod_allowed, cod_max, is_active`
    - relations `pincodes(): HasMany`, `orders(): HasMany`
    - `static forPincode(string $pincode): ?ShippingZone` (active zones only)
    - `shippingFor(int $subtotal): int`
    - `allowsCod(int $total): bool`
  - `Address`: fillable `name, phone, line1, locality, district, pincode`; `const DISTRICTS` (list of 11); `user()`.
  - `CartItem`: fillable `product_variant_id, quantity`; `user()`, `variant()`.
  - `Order`:
    - fillable: every column except id and timestamps
    - casts: enums + `placed_at` datetime
    - `number` accessor (`ARU-000123`)
    - relations `items()`, `user()`, `shippingZone()`
    - `trackingUrl(): string`. It calls the signed route `orders.show`, which Task 6 adds; until then calling it throws.
  - `OrderItem`: fillable `product_variant_id, product_name, size, colour, unit_price, quantity`; `order()`, `variant()`, `lineTotal(): int`.
  - `User` gains `addresses()`, `cartItems()`, `orders()`.
  - Factories:
    - `ShippingZoneFactory::withPincodes(string ...$pincodes)`
    - `OrderFactory` (defaults: COD, Pending / Confirmed)
    - `OrderItemFactory`, `AddressFactory`

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest CheckoutModelTest --no-interaction`:
```php
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
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/CheckoutModelTest.php`
Expected: FAIL — `Class "App\Models\ShippingZone" not found`.

- [ ] **Step 3: Enums**

`php artisan make:enum Enums/PaymentMethod --string --no-interaction` (repeat for `PaymentStatus`, `FulfilmentStatus`). If the generator doesn't take `--string`, write the files directly.

`app/Enums/PaymentMethod.php`:
```php
<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Razorpay = 'razorpay';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Cash on delivery',
            self::Razorpay => 'Online payment',
        };
    }
}
```

`app/Enums/PaymentStatus.php`:
```php
<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return Str::headline($this->name);
    }
}
```

`app/Enums/FulfilmentStatus.php`:
```php
<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum FulfilmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Packed = 'packed';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return Str::headline($this->name);
    }
}
```

- [ ] **Step 4: Migration**

`php artisan make:migration create_checkout_tables --no-interaction`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 10)->nullable()->after('email');
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedInteger('rate');
            $table->unsignedInteger('free_above')->nullable();
            $table->boolean('cod_allowed')->default(true);
            $table->unsignedInteger('cod_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_zone_pincodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->index()->constrained()->cascadeOnDelete();
            $table->string('pincode', 6)->unique();
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 10);
            $table->string('line1', 200);
            $table->string('locality', 100);
            $table->string('district', 30);
            $table->string('pincode', 6);
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->index()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
            $table->unique(['user_id', 'product_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 10);
            $table->string('customer_email', 150)->nullable();
            $table->string('ship_line1', 200);
            $table->string('ship_locality', 100);
            $table->string('ship_district', 30);
            $table->string('ship_pincode', 6);
            $table->foreignId('shipping_zone_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('shipping');
            $table->unsignedInteger('total');
            $table->string('payment_method', 20);
            $table->string('payment_status', 20);
            $table->string('fulfilment_status', 20)->index();
            $table->text('tracking_note')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->index()->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('size', 30)->nullable();
            $table->string('colour', 30)->nullable();
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('shipping_zone_pincodes');
        Schema::dropIfExists('shipping_zones');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
```

- [ ] **Step 5: Models**

`php artisan make:model ShippingZone --factory --no-interaction`, and the same for `Address`, `Order` and `OrderItem`. Run `php artisan make:model ShippingZonePincode --no-interaction` and `php artisan make:model CartItem --no-interaction` without factories. Then replace the bodies.

`app/Models/ShippingZone.php`:
```php
<?php

namespace App\Models;

use Database\Factories\ShippingZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'rate', 'free_above', 'cod_allowed', 'cod_max', 'is_active'])]
class ShippingZone extends Model
{
    /** @use HasFactory<ShippingZoneFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'free_above' => 'integer',
            'cod_allowed' => 'boolean',
            'cod_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ShippingZonePincode, $this>
     */
    public function pincodes(): HasMany
    {
        return $this->hasMany(ShippingZonePincode::class)->orderBy('pincode');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The active zone that delivers to this pincode, if any.
     */
    public static function forPincode(string $pincode): ?self
    {
        return static::where('is_active', true)
            ->whereHas('pincodes', fn ($query) => $query->where('pincode', $pincode))
            ->first();
    }

    /**
     * Shipping in paise for an order subtotal: free at or above free_above, otherwise the zone rate.
     */
    public function shippingFor(int $subtotal): int
    {
        return $this->free_above !== null && $subtotal >= $this->free_above ? 0 : $this->rate;
    }

    /**
     * Cash on delivery needs the store-wide switch, the zone's flag, and a total within the zone's COD limit.
     */
    public function allowsCod(int $total): bool
    {
        return (bool) Setting::values()['cod_enabled']
            && $this->cod_allowed
            && ($this->cod_max === null || $total <= $this->cod_max);
    }
}
```

`app/Models/ShippingZonePincode.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pincode'])]
class ShippingZonePincode extends Model
{
    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }
}
```

`app/Models/Address.php`:
```php
<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'phone', 'line1', 'locality', 'district', 'pincode'])]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    /**
     * Districts of Mizoram, the only state the store delivers to.
     *
     * @var list<string>
     */
    public const DISTRICTS = [
        'Aizawl', 'Champhai', 'Hnahthial', 'Khawzawl', 'Kolasib', 'Lawngtlai',
        'Lunglei', 'Mamit', 'Saitual', 'Serchhip', 'Siaha',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`app/Models/CartItem.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_variant_id', 'quantity'])]
class CartItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
```

`app/Models/Order.php`:
```php
<?php

namespace App\Models;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'user_id', 'customer_name', 'customer_phone', 'customer_email',
    'ship_line1', 'ship_locality', 'ship_district', 'ship_pincode', 'shipping_zone_id',
    'subtotal', 'shipping', 'total', 'payment_method', 'payment_status', 'fulfilment_status',
    'tracking_note', 'placed_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'shipping' => 'integer',
            'total' => 'integer',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'fulfilment_status' => FulfilmentStatus::class,
            'placed_at' => 'datetime',
        ];
    }

    /**
     * Customer-facing order number, derived from the id: ARU-000123.
     *
     * @return Attribute<string, never>
     */
    protected function number(): Attribute
    {
        return Attribute::get(fn (): string => sprintf('ARU-%06d', $this->id));
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    /**
     * Permanent signed link to the order status page, safe to email to a guest.
     */
    public function trackingUrl(): string
    {
        return URL::signedRoute('orders.show', $this);
    }
}
```

`app/Models/OrderItem.php`:
```php
<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_variant_id', 'product_name', 'size', 'colour', 'unit_price', 'quantity'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function lineTotal(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
```

In `app/Models/User.php` add (imports `HasMany`):
```php
    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
```

In `config/app.php` set `'timezone' => 'Asia/Kolkata',`.

- [ ] **Step 6: Factories**

`database/factories/ShippingZoneFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'rate' => 5000,
            'free_above' => null,
            'cod_allowed' => true,
            'cod_max' => null,
            'is_active' => true,
        ];
    }

    public function withPincodes(string ...$pincodes): static
    {
        return $this->afterCreating(function (ShippingZone $zone) use ($pincodes): void {
            $zone->pincodes()->createMany(array_map(fn (string $pincode): array => ['pincode' => $pincode], $pincodes));
        });
    }
}
```

`database/factories/AddressFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('98########'),
            'line1' => fake()->streetAddress(),
            'locality' => 'Zarkawt',
            'district' => 'Aizawl',
            'pincode' => '796001',
        ];
    }
}
```

`database/factories/OrderFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('98########'),
            'customer_email' => null,
            'ship_line1' => fake()->streetAddress(),
            'ship_locality' => 'Zarkawt',
            'ship_district' => 'Aizawl',
            'ship_pincode' => '796001',
            'shipping_zone_id' => ShippingZone::factory(),
            'subtotal' => 99900,
            'shipping' => 5000,
            'total' => 104900,
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => PaymentStatus::Pending,
            'fulfilment_status' => FulfilmentStatus::Confirmed,
            'placed_at' => now(),
        ];
    }
}
```

`database/factories/OrderItemFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name' => 'Wolf Badge Tee',
            'size' => 'M',
            'colour' => 'Black',
            'unit_price' => 99900,
            'quantity' => 1,
        ];
    }
}
```

- [ ] **Step 7: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/CheckoutModelTest.php`
Expected: 5 PASS.

- [ ] **Step 8: Demo zones seeder**

`php artisan make:seeder ShippingZoneSeeder --no-interaction`:
```php
<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingZoneSeeder extends Seeder
{
    /**
     * Demo zones for local checks. The pincode lists are placeholders; the owner enters the real ones in admin.
     */
    public function run(): void
    {
        ShippingZone::factory()
            ->withPincodes(...array_map(fn (int $n): string => (string) (796000 + $n), range(1, 15)))
            ->create(['name' => 'Aizawl', 'rate' => 4000, 'free_above' => 149900, 'cod_allowed' => true, 'cod_max' => 500000]);

        ShippingZone::factory()
            ->withPincodes('796321', '796701', '796181')
            ->create(['name' => 'District towns', 'rate' => 10000, 'free_above' => null, 'cod_allowed' => false]);
    }
}
```

In `DatabaseSeeder::run()` add `$this->call(ShippingZoneSeeder::class);` after `CatalogSeeder`. Run: `php artisan migrate:fresh --seed`. Expected: no errors.

- [ ] **Step 9: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app config database tests/Feature/CheckoutModelTest.php
git commit -m "feat: checkout schema, order enums and models"
```

---

### Task 2: Admin shipping zones

**Files:**
- Create: `app/Http/Controllers/Admin/ShippingZoneController.php`, `app/Http/Requests/Admin/ShippingZoneRequest.php`
- Create: `resources/js/Pages/Admin/ShippingZones/Index.vue`, `Create.vue`, `Edit.vue`, `Partials/ShippingZoneForm.vue`
- Modify: `routes/admin.php`, `resources/js/Layouts/AdminLayout.vue`
- Test: `tests/Feature/Admin/ShippingZoneManagementTest.php`

**Interfaces:**
- Consumes: `ShippingZone`, `ShippingZoneFactory::withPincodes()`, `OrderFactory` (Task 1); `AdminTable.vue`, `formatPaise`, `toPaise` (Phase 2).
- Produces: routes `admin.shipping-zones.{index,create,store,edit,update,destroy}` (route parameter `shipping_zone`). The pincodes field is free text (comma, space or newline separated), and the server parses it into `pincodes[]`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Admin/ShippingZoneManagementTest --no-interaction`:
```php
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
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Admin/ShippingZoneManagementTest.php`
Expected: FAIL — 404 on `/admin/shipping-zones`.

- [ ] **Step 3: Form Request**

`php artisan make:request Admin/ShippingZoneRequest --no-interaction`:
```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * The admin types pincodes as free text; validate them as a list.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'pincodes' => array_values(array_unique(
                preg_split('/[\s,]+/', (string) $this->input('pincodes'), -1, PREG_SPLIT_NO_EMPTY),
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'integer', 'min:0', 'max:10000000'],
            'free_above' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'cod_allowed' => ['required', 'boolean'],
            'cod_max' => ['nullable', 'integer', 'min:100', 'max:10000000'],
            'is_active' => ['required', 'boolean'],
            'pincodes' => ['required', 'array', 'min:1', 'max:1000'],
            'pincodes.*' => [
                'digits:6',
                Rule::unique('shipping_zone_pincodes', 'pincode')->ignore($this->route('shipping_zone')?->id, 'shipping_zone_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pincodes.*.digits' => 'Pincode :input must be 6 digits.',
            'pincodes.*.unique' => 'Pincode :input already belongs to another zone.',
        ];
    }
}
```

- [ ] **Step 4: Controller and routes**

`php artisan make:controller Admin/ShippingZoneController --no-interaction`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingZoneRequest;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShippingZoneController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        return Inertia::render('Admin/ShippingZones/Index', [
            'zones' => ShippingZone::query()
                ->withCount('pincodes')
                ->when($search, fn ($query) => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereHas('pincodes', fn ($pincodes) => $pincodes->where('pincode', $search)))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (ShippingZone $zone): array => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'rate' => $zone->rate,
                    'free_above' => $zone->free_above,
                    'cod_allowed' => $zone->cod_allowed,
                    'pincodes_count' => $zone->pincodes_count,
                    'is_active' => $zone->is_active,
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ShippingZones/Create');
    }

    public function store(ShippingZoneRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $zone = ShippingZone::create($request->safe()->except('pincodes'));
            $this->savePincodes($zone, $request->validated('pincodes'));
        });

        return to_route('admin.shipping-zones.index')->with('status', 'Zone created.');
    }

    public function edit(ShippingZone $shippingZone): Response
    {
        return Inertia::render('Admin/ShippingZones/Edit', [
            'zone' => [
                ...$shippingZone->only('id', 'name', 'rate', 'free_above', 'cod_allowed', 'cod_max', 'is_active'),
                'pincodes' => $shippingZone->pincodes->pluck('pincode')->implode("\n"),
            ],
        ]);
    }

    public function update(ShippingZoneRequest $request, ShippingZone $shippingZone): RedirectResponse
    {
        DB::transaction(function () use ($request, $shippingZone): void {
            $shippingZone->update($request->safe()->except('pincodes'));
            $shippingZone->pincodes()->delete();
            $this->savePincodes($shippingZone, $request->validated('pincodes'));
        });

        return to_route('admin.shipping-zones.index')->with('status', 'Zone saved.');
    }

    public function destroy(ShippingZone $shippingZone): RedirectResponse
    {
        if ($shippingZone->orders()->exists()) {
            return back()->withErrors(['zone' => "\"{$shippingZone->name}\" has orders. Untick Active to stop using it instead."]);
        }

        $shippingZone->delete();

        return to_route('admin.shipping-zones.index')->with('status', 'Zone deleted.');
    }

    /**
     * @param  list<string>  $pincodes
     */
    private function savePincodes(ShippingZone $zone, array $pincodes): void
    {
        $zone->pincodes()->createMany(array_map(fn (string $pincode): array => ['pincode' => $pincode], $pincodes));
    }
}
```

`routes/admin.php` — import and add inside the group:
```php
        Route::resource('shipping-zones', ShippingZoneController::class)->except('show');
```

- [ ] **Step 5: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Admin/ShippingZoneManagementTest.php`
Expected: 7 PASS.

- [ ] **Step 6: Zone pages**

`resources/js/Pages/Admin/ShippingZones/Partials/ShippingZoneForm.vue`:
```vue
<script setup>
import { computed } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    form: { type: Object, required: true },
});

defineEmits(['submit']);

const pincodeErrors = computed(() =>
    Object.entries(props.form.errors)
        .filter(([key]) => key.startsWith('pincodes'))
        .map(([, message]) => message),
);
</script>

<template>
    <form class="max-w-xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="name" value="Name" />
            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="rate" value="Shipping rate (₹)" />
                <TextInput id="rate" v-model="form.rate" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.rate" />
            </div>
            <div>
                <InputLabel for="free_above" value="Free shipping from (₹, optional)" />
                <TextInput id="free_above" v-model="form.free_above" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                <InputError class="mt-2" :message="form.errors.free_above" />
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <Checkbox v-model:checked="form.cod_allowed" />
            Cash on delivery allowed
        </label>
        <div v-if="form.cod_allowed">
            <InputLabel for="cod_max" value="COD only up to (₹, optional)" />
            <TextInput id="cod_max" v-model="form.cod_max" type="number" min="1" step="0.01" class="mt-1 block w-40" />
            <InputError class="mt-2" :message="form.errors.cod_max" />
        </div>
        <div>
            <InputLabel for="pincodes" value="Pincodes (separate with commas, spaces or new lines)" />
            <textarea
                id="pincodes"
                v-model="form.pincodes"
                rows="6"
                class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
            />
            <ul v-if="pincodeErrors.length" class="mt-2 space-y-1 text-sm text-red-600">
                <li v-for="message in pincodeErrors" :key="message">{{ message }}</li>
            </ul>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <Checkbox v-model:checked="form.is_active" />
            Active
        </label>
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
```

`resources/js/Pages/Admin/ShippingZones/Create.vue`:
```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ShippingZoneForm from './Partials/ShippingZoneForm.vue';
import { toPaise } from '@/money';

const form = useForm({ name: '', rate: '', free_above: '', cod_allowed: true, cod_max: '', is_active: true, pincodes: '' });

const submit = () => {
    form.transform((data) => ({
        ...data,
        rate: toPaise(data.rate),
        free_above: toPaise(data.free_above),
        cod_max: data.cod_allowed ? toPaise(data.cod_max) : null,
    })).post(route('admin.shipping-zones.store'));
};
</script>

<template>
    <Head title="New shipping zone" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">New shipping zone</h1>
        </template>

        <ShippingZoneForm :form="form" @submit="submit" />
    </AdminLayout>
</template>
```

`resources/js/Pages/Admin/ShippingZones/Edit.vue`:
```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ShippingZoneForm from './Partials/ShippingZoneForm.vue';
import { toPaise } from '@/money';

const props = defineProps({
    zone: { type: Object, required: true },
});

const rupees = (paise) => (paise === null ? '' : paise / 100);

const form = useForm({
    ...props.zone,
    rate: rupees(props.zone.rate),
    free_above: rupees(props.zone.free_above),
    cod_max: rupees(props.zone.cod_max),
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        rate: toPaise(data.rate),
        free_above: toPaise(data.free_above),
        cod_max: data.cod_allowed ? toPaise(data.cod_max) : null,
    })).put(route('admin.shipping-zones.update', props.zone.id));
};
</script>

<template>
    <Head :title="`Edit ${zone.name}`" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Edit shipping zone</h1>
        </template>

        <ShippingZoneForm :form="form" @submit="submit" />
    </AdminLayout>
</template>
```

`resources/js/Pages/Admin/ShippingZones/Index.vue`:
```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import { formatPaise } from '@/money';

defineProps({
    zones: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const columns = [
    { key: 'name', label: 'Zone' },
    { key: 'rate', label: 'Rate' },
    { key: 'free_above', label: 'Free from' },
    { key: 'cod_allowed', label: 'COD' },
    { key: 'pincodes_count', label: 'Pincodes' },
    { key: 'is_active', label: 'Active' },
    { key: 'actions', label: '' },
];

const deleteZone = (zone) => {
    router.delete(route('admin.shipping-zones.destroy', zone.id), {
        onBefore: () => window.confirm(`Delete "${zone.name}"?`),
    });
};
</script>

<template>
    <Head title="Shipping zones" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Shipping zones</h1>
                <Link :href="route('admin.shipping-zones.create')" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">New zone</Link>
            </div>
        </template>

        <p v-if="$page.props.errors.zone" class="mb-4 rounded bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">
            {{ $page.props.errors.zone }}
        </p>

        <AdminTable :rows="zones" :columns="columns" :filters="filters">
            <template #cell-name="{ row }">
                <Link :href="route('admin.shipping-zones.edit', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-rate="{ row }">{{ formatPaise(row.rate) }}</template>
            <template #cell-free_above="{ row }">{{ row.free_above === null ? '—' : formatPaise(row.free_above) }}</template>
            <template #cell-cod_allowed="{ row }">{{ row.cod_allowed ? 'Yes' : 'No' }}</template>
            <template #cell-is_active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
            <template #cell-actions="{ row }">
                <button type="button" class="text-red-600 hover:underline" @click="deleteZone(row)">Delete</button>
            </template>
            <template #empty>No zones yet. Without a zone nobody can check out.</template>
        </AdminTable>
    </AdminLayout>
</template>
```

In `AdminLayout.vue` `links`, before Pages:
```js
    { label: 'Shipping zones', route: 'admin.shipping-zones.index', match: 'admin.shipping-zones.*' },
```

- [ ] **Step 7: Build and commit**

```bash
source ~/.nvm/nvm.sh && nvm use && npm run build
vendor/bin/pint --dirty --format agent
git add app routes resources/js tests/Feature/Admin/ShippingZoneManagementTest.php
git commit -m "feat: admin shipping zones with pincode lists"
```

---

### Task 3: Cart service, routes and login merge

**Files:**
- Create: `app/Services/Cart.php`, `app/Listeners/MergeGuestCart.php`, `app/Http/Controllers/CartController.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`
- Test: `tests/Feature/Storefront/CartTest.php`

**Interfaces:**
- Consumes: `CartItem`, `User::cartItems()` (Task 1); `Product::visible()`, `ProductVariant::effectivePrice()`, `ProductImage::toImageData()` (Phase 2).
- Produces:
  - `App\Services\Cart`:
    - `const MAX_PER_LINE = 10`
    - `quantities(): array<int,int>` (variant id → quantity)
    - `add(ProductVariant, int)`, `set(ProductVariant, int)`, `remove(int $variantId)`, `clear()`
    - `mergeSessionInto(User)`
    - `lines(): Collection` of `{variant_id, name, slug, size, colour, image, unit_price, quantity, max_quantity, line_total, available}`
    - `summary(): array{lines: list, count: int, subtotal: int, has_unavailable: bool}`. `count` and `subtotal` include available lines only.
  - Shared Inertia prop `cart` (the `summary()`), `null` on admin pages.
  - Routes:
    - `cart.index` GET `/cart`, page `Cart/Index` (renders only shared props)
    - `cart.store` POST `/cart` with `{variant_id, quantity}`
    - `cart.update` PATCH `/cart/{variant}` with `{quantity}`
    - `cart.destroy` DELETE `/cart/{variant}`
  - Guest session key: `cart` (array `variantId => quantity`).

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Storefront/CartTest --no-interaction`:
```php
<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

function cartVariant(array $attributes = []): ProductVariant
{
    return ProductVariant::factory()->for(Product::factory()->create(['base_price' => 99900]))->create($attributes);
}

test('a guest can add an in-stock variant to the session cart', function () {
    $variant = cartVariant();

    $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 2])
        ->assertRedirect()
        ->assertSessionHas("cart.{$variant->id}", 2);
});

test('quantities are capped at stock and at ten per line', function () {
    $low = cartVariant(['stock' => 3]);
    $plenty = cartVariant(['stock' => 50]);

    $this->withSession(['cart' => [$low->id => 2]])
        ->post('/cart', ['variant_id' => $low->id, 'quantity' => 5])
        ->assertSessionHas("cart.{$low->id}", 3);

    $this->withSession(['cart' => [$plenty->id => 8]])
        ->post('/cart', ['variant_id' => $plenty->id, 'quantity' => 5])
        ->assertSessionHas("cart.{$plenty->id}", 10);
});

test('unavailable variants cannot be added', function (ProductVariant $variant) {
    $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1])
        ->assertSessionHasErrors('variant_id');
})->with([
    'sold out' => fn () => cartVariant(['stock' => 0]),
    'inactive variant' => fn () => cartVariant(['is_active' => false]),
    'hidden product' => fn () => ProductVariant::factory()->for(Product::factory()->inactive())->create(),
]);

test('the cart page shows live prices and totals from the server', function () {
    $variant = cartVariant();
    $variant->update(['price_override' => 79900]);

    $this->withSession(['cart' => [$variant->id => 2]])
        ->get('/cart')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cart/Index')
            ->where('cart.count', 2)
            ->where('cart.subtotal', 159800)
            ->where('cart.lines.0.unit_price', 79900)
            ->where('cart.lines.0.available', true));
});

test('a line that became unavailable is flagged and left out of the totals', function () {
    $available = cartVariant();
    $gone = cartVariant();
    $gone->update(['is_active' => false]);

    $this->withSession(['cart' => [$available->id => 1, $gone->id => 1]])
        ->get('/cart')
        ->assertInertia(fn ($page) => $page
            ->where('cart.count', 1)
            ->where('cart.subtotal', 99900)
            ->where('cart.has_unavailable', true));
});

test('a signed-in shopper keeps the cart in the database', function () {
    $user = User::factory()->create();
    $variant = cartVariant();

    $this->actingAs($user)->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
    $this->actingAs($user)->patch("/cart/{$variant->id}", ['quantity' => 4]);

    expect($user->cartItems()->sole()->quantity)->toBe(4);

    $this->actingAs($user)->delete("/cart/{$variant->id}");

    expect($user->cartItems()->count())->toBe(0);
});

test('updating a variant that is not in the cart is not found', function () {
    $this->patch('/cart/'.cartVariant()->id, ['quantity' => 2])->assertNotFound();
});

test('the guest cart merges into the saved cart on login', function () {
    $user = User::factory()->create();
    $both = cartVariant(['stock' => 4]);
    $guestOnly = cartVariant();
    $user->cartItems()->create(['product_variant_id' => $both->id, 'quantity' => 3]);

    $this->withSession(['cart' => [$both->id => 2, $guestOnly->id => 1]])
        ->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionMissing('cart');

    expect($user->cartItems()->pluck('quantity', 'product_variant_id')->all())
        ->toEqual([$both->id => 4, $guestOnly->id => 1]);
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Storefront/CartTest.php`
Expected: FAIL — 404/405 on `/cart`.

- [ ] **Step 3: Cart service**

`php artisan make:class Services/Cart --no-interaction`:
```php
<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Cart
{
    /**
     * Most of one variant a shopper can hold.
     */
    public const MAX_PER_LINE = 10;

    private const SESSION_KEY = 'cart';

    public function __construct(private Request $request) {}

    /**
     * Variant id => quantity: the saved cart when signed in, otherwise the session cart.
     *
     * @return array<int, int>
     */
    public function quantities(): array
    {
        $user = $this->request->user();

        if ($user) {
            return $user->cartItems()->pluck('quantity', 'product_variant_id')->map(fn ($quantity): int => (int) $quantity)->all();
        }

        return $this->request->session()->get(self::SESSION_KEY, []);
    }

    public function add(ProductVariant $variant, int $quantity): void
    {
        $this->set($variant, ($this->quantities()[$variant->id] ?? 0) + $quantity);
    }

    /**
     * Store a quantity, capped at stock and MAX_PER_LINE. Zero or less removes the line.
     */
    public function set(ProductVariant $variant, int $quantity): void
    {
        $quantity = min($quantity, $variant->stock, self::MAX_PER_LINE);

        if ($quantity < 1) {
            $this->remove($variant->id);

            return;
        }

        $user = $this->request->user();

        if ($user) {
            $user->cartItems()->updateOrCreate(['product_variant_id' => $variant->id], ['quantity' => $quantity]);
        } else {
            $this->request->session()->put(self::SESSION_KEY.'.'.$variant->id, $quantity);
        }
    }

    public function remove(int $variantId): void
    {
        $this->request->user()?->cartItems()->where('product_variant_id', $variantId)->delete();
        $this->request->session()->forget(self::SESSION_KEY.'.'.$variantId);
    }

    public function clear(): void
    {
        $this->request->user()?->cartItems()->delete();
        $this->request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Move the guest session cart into the user's saved cart, adding quantities up to the caps.
     */
    public function mergeSessionInto(User $user): void
    {
        $guest = $this->request->session()->pull(self::SESSION_KEY, []);

        if ($guest === []) {
            return;
        }

        $saved = $user->cartItems()->pluck('quantity', 'product_variant_id');

        foreach (ProductVariant::whereKey(array_keys($guest))->get() as $variant) {
            $quantity = min(($saved[$variant->id] ?? 0) + $guest[$variant->id], $variant->stock, self::MAX_PER_LINE);

            if ($quantity > 0) {
                $user->cartItems()->updateOrCreate(['product_variant_id' => $variant->id], ['quantity' => $quantity]);
            }
        }
    }

    /**
     * Cart lines priced from current variant data. Lines that can't be bought now are kept but flagged.
     *
     * @return Collection<int, array{variant_id: int, name: string, slug: string, size: ?string, colour: ?string, image: array{src: string, srcset: string, alt: string}|null, unit_price: int, quantity: int, max_quantity: int, line_total: int, available: bool}>
     */
    public function lines(): Collection
    {
        $quantities = $this->quantities();

        if ($quantities === []) {
            return collect();
        }

        $variants = ProductVariant::query()
            ->with(['product', 'product.images' => fn (HasMany $images) => $images->limit(1)])
            ->whereKey(array_keys($quantities))
            ->orderBy('id')
            ->get();

        $visibleProductIds = Product::visible()->whereKey($variants->pluck('product_id'))->pluck('id');

        return $variants->map(function (ProductVariant $variant) use ($quantities, $visibleProductIds): array {
            $available = $variant->is_active && $variant->stock > 0 && $visibleProductIds->contains($variant->product_id);
            $quantity = $available ? min($quantities[$variant->id], $variant->stock, self::MAX_PER_LINE) : $quantities[$variant->id];
            $unitPrice = $variant->effectivePrice();

            return [
                'variant_id' => $variant->id,
                'name' => $variant->product->name,
                'slug' => $variant->product->slug,
                'size' => $variant->size,
                'colour' => $variant->colour,
                'image' => $variant->product->images->first()?->toImageData(),
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'max_quantity' => max(0, min($variant->stock, self::MAX_PER_LINE)),
                'line_total' => $unitPrice * $quantity,
                'available' => $available,
            ];
        })->values();
    }

    /**
     * @return array{lines: list<array<string, mixed>>, count: int, subtotal: int, has_unavailable: bool}
     */
    public function summary(): array
    {
        $lines = $this->lines();
        $available = $lines->where('available', true);

        return [
            'lines' => $lines->all(),
            'count' => (int) $available->sum('quantity'),
            'subtotal' => (int) $available->sum('line_total'),
            'has_unavailable' => $lines->contains('available', false),
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, shared prop, listener**

`php artisan make:controller CartController --no-interaction`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\Cart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * The page reads the shared `cart` prop.
     */
    public function index(): Response
    {
        return Inertia::render('Cart/Index');
    }

    public function store(Request $request, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.Cart::MAX_PER_LINE],
        ]);

        $variant = ProductVariant::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereHas('product', fn (Builder $product) => $product->visible())
            ->find($validated['variant_id']);

        if (! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'This item is no longer available.']);
        }

        $cart->add($variant, $validated['quantity']);

        return back();
    }

    public function update(Request $request, Cart $cart, ProductVariant $variant): RedirectResponse
    {
        abort_unless(array_key_exists($variant->id, $cart->quantities()), 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_PER_LINE],
        ]);

        $cart->set($variant, $validated['quantity']);

        return back();
    }

    public function destroy(Cart $cart, ProductVariant $variant): RedirectResponse
    {
        $cart->remove($variant->id);

        return back();
    }
}
```

`routes/web.php` — import and add after the search route:
```php
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->middleware('throttle:60,1')->name('cart.store');
Route::patch('/cart/{variant}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{variant}', [CartController::class, 'destroy'])->name('cart.destroy');
```

In `HandleInertiaRequests::share()` add (import `App\Services\Cart`):
```php
            'cart' => fn (): ?array => $request->routeIs('admin.*') ? null : app(Cart::class)->summary(),
```

`php artisan make:listener MergeGuestCart --event=Login --no-interaction`:
```php
<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Cart;
use Illuminate\Auth\Events\Login;

class MergeGuestCart
{
    public function __construct(private Cart $cart) {}

    /**
     * Keep what a shopper added before signing in.
     */
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->cart->mergeSessionInto($event->user);
        }
    }
}
```

Laravel auto-discovers listeners in `app/Listeners`; confirm with `php artisan event:list --event=Illuminate\\Auth\\Events\\Login`.

- [ ] **Step 5: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Storefront/CartTest.php`
Expected: 10 PASS (the dataset counts 3). Then run `php artisan test --compact tests/Feature/Auth` — Breeze tests still pass.

- [ ] **Step 6: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes tests/Feature/Storefront/CartTest.php
git commit -m "feat: session and account cart with login merge"
```

---

### Task 4: Cart UI (add to cart, drawer, cart page)

**Design:** Before writing Vue, invoke `ui-ux-pro-max` (cart drawer, cart page, add-to-cart button on a mobile product page, apparel store) and read `.claude/skills/design-motion-principles/SKILL.md` for the drawer slide-in and the add confirmation. Keep the Phase 1/2 visual language:
- `brand-*` palette, `ink`, `font-display` headings
- spaced uppercase labels
- 44 px touch targets
- `focusRing` outlines

**Files:**
- Create: `resources/js/cartDrawer.js`, `resources/js/Components/Store/CartDrawer.vue`, `resources/js/Components/Icons/IconBag.vue`, `resources/js/Pages/Cart/Index.vue`
- Modify: `resources/js/Layouts/StoreLayout.vue`, `resources/js/Pages/Catalog/Product.vue`

**Interfaces:**
- Consumes:
  - shared prop `cart` and routes `cart.index`, `cart.store`, `cart.update`, `cart.destroy` (Task 3)
  - `formatPaise` (Phase 2)
  - route `checkout.create`, which Task 5 adds. Until then, Ziggy throws at runtime when the checkout button renders. Do this task's browser check after Task 5, or temporarily point the button at `cart.index`.
- Produces: `cartDrawer.js` exports `isCartOpen` (a Vue `ref(false)`).

- [ ] **Step 1: Drawer state and icon**

`resources/js/cartDrawer.js`:
```js
import { ref } from 'vue';

export const isCartOpen = ref(false);
```

`resources/js/Components/Icons/IconBag.vue`:
```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />
    </svg>
</template>
```

- [ ] **Step 2: Cart drawer**

`resources/js/Components/Store/CartDrawer.vue`. The behaviour is fixed; the design pass may refine the classes.
```vue
<script setup>
import { nextTick, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import IconClose from '@/Components/Icons/IconClose.vue';
import { isCartOpen } from '@/cartDrawer';
import { formatPaise } from '@/money';

const page = usePage();
const closeButton = ref(null);

watch(isCartOpen, async (open) => {
    document.body.classList.toggle('overflow-hidden', open);
    if (open) {
        await nextTick();
        closeButton.value?.focus();
    }
});

const close = () => {
    isCartOpen.value = false;
};

const variantLabel = (line) => [line.colour, line.size].filter(Boolean).join(' / ');

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <div>
        <Transition
            enter-active-class="transition-opacity duration-200 motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div v-if="isCartOpen" class="fixed inset-0 z-40 bg-ink/40" aria-hidden="true" @click="close" />
        </Transition>
        <Transition
            enter-active-class="transition-transform duration-300 ease-out motion-reduce:transition-none"
            enter-from-class="translate-x-full"
            leave-active-class="transition-transform duration-200 ease-in motion-reduce:transition-none"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="isCartOpen"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cart-drawer-title"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col bg-brand-50 text-ink shadow-xl"
                @keydown.esc="close"
            >
                <div class="flex items-center justify-between border-b border-brand-200 px-4 py-3">
                    <h2 id="cart-drawer-title" class="text-xs font-medium uppercase tracking-[0.2em]">Your bag ({{ page.props.cart.count }})</h2>
                    <button ref="closeButton" type="button" class="inline-flex h-11 w-11 items-center justify-center" :class="focusRing" aria-label="Close bag" @click="close">
                        <IconClose class="h-6 w-6" />
                    </button>
                </div>

                <ul v-if="page.props.cart.lines.length" class="flex-1 divide-y divide-brand-200 overflow-y-auto px-4">
                    <li v-for="line in page.props.cart.lines" :key="line.variant_id" class="flex gap-3 py-4">
                        <div class="h-20 w-16 shrink-0 overflow-hidden bg-brand-100">
                            <img v-if="line.image" :src="line.image.src" :srcset="line.image.srcset" sizes="64px" :alt="line.image.alt" class="h-full w-full object-cover" />
                        </div>
                        <div class="flex-1 text-sm">
                            <p class="font-medium">{{ line.name }}</p>
                            <p v-if="variantLabel(line)" class="text-brand-600">{{ variantLabel(line) }}</p>
                            <p v-if="line.available" class="mt-1 tabular-nums">{{ line.quantity }} × {{ formatPaise(line.unit_price) }}</p>
                            <p v-else class="mt-1 text-red-700">No longer available</p>
                        </div>
                    </li>
                </ul>
                <p v-else class="flex-1 px-4 py-12 text-center text-brand-700">Your bag is empty.</p>

                <div v-if="page.props.cart.count" class="border-t border-brand-200 p-4">
                    <p class="flex justify-between text-sm">
                        <span>Subtotal</span>
                        <span class="tabular-nums">{{ formatPaise(page.props.cart.subtotal) }}</span>
                    </p>
                    <p class="mt-1 text-xs text-brand-600">Shipping is worked out at checkout.</p>
                    <div class="mt-4 grid gap-2">
                        <Link :href="route('checkout.create')" class="flex min-h-11 items-center justify-center bg-ink text-xs font-medium uppercase tracking-[0.2em] text-brand-50" :class="focusRing" @click="close">Checkout</Link>
                        <Link :href="route('cart.index')" class="flex min-h-11 items-center justify-center border border-ink text-xs font-medium uppercase tracking-[0.2em]" :class="focusRing" @click="close">View bag</Link>
                    </div>
                </div>
            </aside>
        </Transition>
    </div>
</template>
```

- [ ] **Step 3: Header bag button**

In `StoreLayout.vue`:
- Import `IconBag`, `CartDrawer` and `{ isCartOpen } from '@/cartDrawer'`.
- Before the account `Link` in the right-hand icon group, add the button below.
- Render `<CartDrawer />` as the last child of the root `<div>`.
```vue
                    <button
                        type="button"
                        class="relative inline-flex h-11 w-11 items-center justify-center rounded transition-colors duration-200 hover:text-brand-200"
                        :class="focusRing"
                        :aria-label="`Bag, ${page.props.cart.count} items`"
                        @click="isCartOpen = true"
                    >
                        <IconBag class="h-6 w-6" />
                        <span
                            v-if="page.props.cart.count"
                            class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-50 px-1 text-[10px] font-semibold text-ink"
                            aria-hidden="true"
                        >
                            {{ page.props.cart.count }}
                        </span>
                    </button>
```

- [ ] **Step 4: Add to cart and the sold-out label on the product page**

In `resources/js/Pages/Catalog/Product.vue`:
- Import `router` alongside `Head, Link`, and import `{ isCartOpen } from '@/cartDrawer'`.
- Add to the script:
```js
const isAdding = ref(false);

const colourSoldOut = computed(() => !props.variants.some((v) => v.colour === selectedColour.value && v.in_stock));

const addToCart = () => {
    router.post(route('cart.store'), { variant_id: selectedVariant.value.id, quantity: 1 }, {
        preserveScroll: true,
        onStart: () => { isAdding.value = true; },
        onFinish: () => { isAdding.value = false; },
        onSuccess: () => { isCartOpen.value = true; },
    });
};
```
- Replace the status `<p role="status">` and add the button after it:
```vue
                <p class="mt-6 min-h-5 text-sm text-brand-700" role="status">
                    <template v-if="selectedVariant">{{ selectedVariant.in_stock ? 'In stock' : 'Sold out' }}</template>
                    <template v-else-if="colourSoldOut">Sold out</template>
                    <template v-else-if="sizes.length">Choose a size</template>
                </p>
                <p v-if="$page.props.errors.variant_id" class="mt-2 text-sm text-red-700" role="alert">{{ $page.props.errors.variant_id }}</p>
                <button
                    type="button"
                    class="mt-4 flex min-h-12 w-full items-center justify-center bg-ink text-xs font-medium uppercase tracking-[0.2em] text-brand-50 transition-opacity duration-150 disabled:opacity-40 motion-reduce:transition-none sm:w-auto sm:px-12"
                    :class="focusRing"
                    :disabled="!selectedVariant?.in_stock || isAdding"
                    @click="addToCart"
                >
                    {{ isAdding ? 'Adding…' : colourSoldOut ? 'Sold out' : selectedVariant ? 'Add to bag' : 'Choose a size' }}
                </button>
```

- [ ] **Step 5: Cart page**

`resources/js/Pages/Cart/Index.vue`:
```vue
<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

const page = usePage();

const setQuantity = (line, quantity) => {
    router.patch(route('cart.update', line.variant_id), { quantity }, { preserveScroll: true });
};

const remove = (line) => {
    router.delete(route('cart.destroy', line.variant_id), { preserveScroll: true });
};

const variantLabel = (line) => [line.colour, line.size].filter(Boolean).join(' / ');

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
const stepper = 'inline-flex h-11 w-11 items-center justify-center border border-brand-300 disabled:opacity-40';
</script>

<template>
    <Head title="Your bag" />

    <StoreLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
            <h1 class="font-display text-3xl font-semibold sm:text-4xl">Your bag</h1>

            <template v-if="page.props.cart.lines.length">
                <p v-if="page.props.cart.has_unavailable" class="mt-6 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    Some items are no longer available. Remove them to check out.
                </p>

                <ul class="mt-6 divide-y divide-brand-200 border-y border-brand-200">
                    <li v-for="line in page.props.cart.lines" :key="line.variant_id" class="flex gap-4 py-5">
                        <Link :href="route('products.show', line.slug)" class="h-28 w-24 shrink-0 overflow-hidden bg-brand-100" :class="focusRing">
                            <img v-if="line.image" :src="line.image.src" :srcset="line.image.srcset" sizes="96px" :alt="line.image.alt" class="h-full w-full object-cover" />
                        </Link>
                        <div class="flex flex-1 flex-col gap-2 text-sm sm:flex-row sm:justify-between">
                            <div>
                                <Link :href="route('products.show', line.slug)" class="font-medium hover:underline" :class="focusRing">{{ line.name }}</Link>
                                <p v-if="variantLabel(line)" class="text-brand-600">{{ variantLabel(line) }}</p>
                                <p class="mt-1 tabular-nums">{{ formatPaise(line.unit_price) }}</p>
                                <p v-if="!line.available" class="mt-1 text-red-700">No longer available</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div v-if="line.available" class="flex items-center">
                                    <button type="button" :class="[stepper, focusRing]" :aria-label="`One fewer ${line.name}`" @click="setQuantity(line, line.quantity - 1)">−</button>
                                    <span class="w-10 text-center tabular-nums" aria-live="polite">{{ line.quantity }}</span>
                                    <button type="button" :class="[stepper, focusRing]" :aria-label="`One more ${line.name}`" :disabled="line.quantity >= line.max_quantity" @click="setQuantity(line, line.quantity + 1)">+</button>
                                </div>
                                <button type="button" class="min-h-11 text-sm underline underline-offset-4" :class="focusRing" @click="remove(line)">Remove</button>
                            </div>
                        </div>
                    </li>
                </ul>

                <div class="mt-6 flex flex-col items-stretch gap-4 sm:items-end">
                    <p class="flex justify-between gap-12 text-base sm:justify-end">
                        <span>Subtotal</span>
                        <span class="tabular-nums">{{ formatPaise(page.props.cart.subtotal) }}</span>
                    </p>
                    <p class="text-xs text-brand-600">Shipping is worked out at checkout from your pincode.</p>
                    <Link
                        v-if="!page.props.cart.has_unavailable && page.props.cart.count"
                        :href="route('checkout.create')"
                        class="flex min-h-12 items-center justify-center bg-ink px-12 text-xs font-medium uppercase tracking-[0.2em] text-brand-50"
                        :class="focusRing"
                    >
                        Checkout
                    </Link>
                </div>
            </template>

            <div v-else class="mt-12 text-center">
                <p class="text-brand-700">Your bag is empty.</p>
                <Link :href="route('home')" class="mt-4 inline-flex min-h-11 items-center underline underline-offset-4" :class="focusRing">Continue shopping</Link>
            </div>
        </div>
    </StoreLayout>
</template>
```

- [ ] **Step 6: Build and commit**

Run `source ~/.nvm/nvm.sh && nvm use && npm run build`. Expected: it compiles. Record the size of the `Cart/Index` and `StoreLayout` chunks in the report.

Run `php artisan test --compact tests/Feature/Storefront`. Expected: PASS. These are existing tests, but they now go through the shared `cart` prop.

```bash
git add resources/js
git commit -m "feat: add to bag, cart drawer and cart page"
```

---

### Task 5: Checkout page and shipping quote

**Design:** Invoke `ui-ux-pro-max` (mobile checkout form, address fields for India, order summary) before writing Vue. Same visual language as Task 4.

**Files:**
- Create: `app/Http/Controllers/CheckoutController.php`, `resources/js/Pages/Checkout/Create.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/Storefront/CheckoutPageTest.php`

**Interfaces:**
- Consumes: `Cart::summary()` (Task 3); `ShippingZone::forPincode()`, `shippingFor()`, `allowsCod()`, `Address::DISTRICTS`, `User::addresses()` (Task 1).
- Produces:
  - Route `checkout.create` GET `/checkout`. It redirects to `cart.index` when the cart has nothing buyable or has unavailable lines.
  - Page `Checkout/Create` props:
    - `prefill {name, phone, email, line1, locality, district, pincode}`
    - `districts`
    - `quote`. It is `null` without a 6-digit `?pincode=`; otherwise `{deliverable: false}`, or `{deliverable: true, zone, shipping, total, cod_available}`.
  - The page reloads `quote` with `router.reload({ only: ['quote'], data: { pincode } })`.
  - Task 6 adds `CheckoutController::store()` and route `checkout.store`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Storefront/CheckoutPageTest --no-interaction`:
```php
<?php

use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;

beforeEach(function () {
    $this->variant = ProductVariant::factory()->for(Product::factory()->create(['base_price' => 100000]))->create();
    $this->cart = ['cart' => [$this->variant->id => 1]];
    ShippingZone::factory()->withPincodes('796001')->create([
        'name' => 'Aizawl', 'rate' => 4000, 'free_above' => 150000, 'cod_allowed' => true, 'cod_max' => 120000,
    ]);
});

test('an empty cart goes back to the cart page', function () {
    $this->get('/checkout')->assertRedirect('/cart');
});

test('a cart with an unavailable line goes back to the cart page', function () {
    $this->variant->update(['is_active' => false]);

    $this->withSession($this->cart)->get('/checkout')->assertRedirect('/cart');
});

test('the quote prices shipping and cash on delivery for a known pincode', function () {
    $this->withSession($this->cart)
        ->get('/checkout?pincode=796001')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/Create')
            ->where('quote.deliverable', true)
            ->where('quote.zone', 'Aizawl')
            ->where('quote.shipping', 4000)
            ->where('quote.total', 104000)
            ->where('quote.cod_available', true));
});

test('shipping is free and cod is withheld above the zone limits', function () {
    $this->withSession(['cart' => [$this->variant->id => 2]])
        ->get('/checkout?pincode=796001')
        ->assertInertia(fn ($page) => $page
            ->where('quote.shipping', 0)
            ->where('quote.total', 200000)
            ->where('quote.cod_available', false));
});

test('an unknown pincode is not deliverable and no pincode gives no quote', function () {
    $this->withSession($this->cart)
        ->get('/checkout?pincode=110001')
        ->assertInertia(fn ($page) => $page->where('quote.deliverable', false));

    $this->withSession($this->cart)
        ->get('/checkout')
        ->assertInertia(fn ($page) => $page->where('quote', null));
});

test('a signed-in shopper gets their last address filled in', function () {
    $user = User::factory()->create();
    $user->cartItems()->create(['product_variant_id' => $this->variant->id, 'quantity' => 1]);
    Address::factory()->for($user)->create(['line1' => 'Old house']);
    Address::factory()->for($user)->create(['line1' => 'New house', 'phone' => '9876543210']);

    $this->actingAs($user)
        ->get('/checkout')
        ->assertInertia(fn ($page) => $page
            ->where('prefill.line1', 'New house')
            ->where('prefill.phone', '9876543210')
            ->where('prefill.email', $user->email)
            ->has('districts', 11));
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Storefront/CheckoutPageTest.php`
Expected: FAIL — 404 on `/checkout`.

- [ ] **Step 3: Controller and route**

`php artisan make:controller CheckoutController --no-interaction`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\ShippingZone;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function create(Request $request, Cart $cart): Response|RedirectResponse
    {
        $summary = $cart->summary();

        if ($summary['count'] === 0 || $summary['has_unavailable']) {
            return to_route('cart.index');
        }

        $user = $request->user();
        $address = $user?->addresses()->latest('id')->first();

        return Inertia::render('Checkout/Create', [
            'prefill' => [
                'name' => $address->name ?? $user?->name ?? '',
                'phone' => $address->phone ?? $user?->phone ?? '',
                'email' => $user?->email ?? '',
                'line1' => $address->line1 ?? '',
                'locality' => $address->locality ?? '',
                'district' => $address->district ?? 'Aizawl',
                'pincode' => $address->pincode ?? '',
            ],
            'districts' => Address::DISTRICTS,
            'quote' => fn (): ?array => $this->quote((string) $request->query('pincode'), $summary['subtotal']),
        ]);
    }

    /**
     * Shipping and payment options for a pincode, using the same rules PlaceOrder enforces.
     *
     * @return array{deliverable: bool, zone?: string, shipping?: int, total?: int, cod_available?: bool}|null
     */
    private function quote(string $pincode, int $subtotal): ?array
    {
        if (! preg_match('/^\d{6}$/', $pincode)) {
            return null;
        }

        $zone = ShippingZone::forPincode($pincode);

        if (! $zone) {
            return ['deliverable' => false];
        }

        $shipping = $zone->shippingFor($subtotal);

        return [
            'deliverable' => true,
            'zone' => $zone->name,
            'shipping' => $shipping,
            'total' => $subtotal + $shipping,
            'cod_available' => $zone->allowsCod($subtotal + $shipping),
        ];
    }
}
```

`routes/web.php` — import and add after the cart routes:
```php
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
```

- [ ] **Step 4: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Storefront/CheckoutPageTest.php`
Expected: 6 PASS.

- [ ] **Step 5: Checkout page**

`resources/js/Pages/Checkout/Create.vue`. The submit route `checkout.store` arrives in Task 6. Until then, the form posts to a missing route, so the browser check waits for Task 6.
```vue
<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import InputError from '@/Components/InputError.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    prefill: { type: Object, required: true },
    districts: { type: Array, required: true },
    quote: { type: Object, default: null },
});

const page = usePage();

const form = useForm({ ...props.prefill, payment_method: 'cod' });

watch(
    () => form.pincode,
    (pincode) => {
        if (/^\d{6}$/.test(pincode)) {
            router.reload({ only: ['quote'], data: { pincode } });
        }
    },
    { immediate: true },
);

const canPlaceOrder = computed(() => props.quote?.deliverable && props.quote.cod_available && !form.processing);

const submit = () => form.post(route('checkout.store'));

const variantLabel = (line) => [line.colour, line.size].filter(Boolean).join(' / ');

const field = 'mt-1 block min-h-11 w-full border-brand-300 bg-transparent focus:border-ink focus:ring-ink';
const label = 'text-xs font-medium uppercase tracking-[0.2em]';
const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <Head title="Checkout" />

    <StoreLayout>
        <div class="mx-auto grid max-w-5xl gap-10 px-4 py-8 sm:py-12 md:grid-cols-[1fr_20rem]">
            <form class="space-y-6" novalidate @submit.prevent="submit">
                <h1 class="font-display text-3xl font-semibold sm:text-4xl">Checkout</h1>

                <p v-if="form.errors.cart" class="border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ form.errors.cart }} <Link :href="route('cart.index')" class="underline">Review your bag</Link>
                </p>

                <fieldset class="space-y-4">
                    <legend class="font-display text-xl font-semibold">Contact</legend>
                    <div>
                        <label for="name" :class="label">Full name</label>
                        <input id="name" v-model="form.name" autocomplete="name" required :class="field" />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <label for="phone" :class="label">Mobile number</label>
                        <input id="phone" v-model="form.phone" type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="10 digits" required :class="field" />
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>
                    <div>
                        <label for="email" :class="label">Email (optional, for your receipt)</label>
                        <input id="email" v-model="form.email" type="email" autocomplete="email" :class="field" />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="font-display text-xl font-semibold">Delivery address</legend>
                    <div>
                        <label for="line1" :class="label">House, building, street</label>
                        <input id="line1" v-model="form.line1" autocomplete="address-line1" required :class="field" />
                        <InputError class="mt-1" :message="form.errors.line1" />
                    </div>
                    <div>
                        <label for="locality" :class="label">Locality / veng</label>
                        <input id="locality" v-model="form.locality" autocomplete="address-level3" required :class="field" />
                        <InputError class="mt-1" :message="form.errors.locality" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="district" :class="label">District</label>
                            <select id="district" v-model="form.district" required :class="field">
                                <option v-for="district in districts" :key="district" :value="district">{{ district }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.district" />
                        </div>
                        <div>
                            <label for="pincode" :class="label">Pincode</label>
                            <input id="pincode" v-model="form.pincode" inputmode="numeric" autocomplete="postal-code" maxlength="6" required :class="field" />
                            <InputError class="mt-1" :message="form.errors.pincode" />
                            <p v-if="quote && !quote.deliverable" class="mt-1 text-sm text-red-700" role="status">We don't deliver here yet.</p>
                            <p v-else-if="quote?.deliverable" class="mt-1 text-sm text-brand-700" role="status">Delivering to {{ quote.zone }}.</p>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="font-display text-xl font-semibold">Payment</legend>
                    <label class="flex min-h-11 items-center gap-3 border border-brand-300 px-4" :class="{ 'opacity-50': quote?.deliverable && !quote.cod_available }">
                        <input v-model="form.payment_method" type="radio" value="cod" class="text-ink focus:ring-ink" :disabled="quote?.deliverable && !quote.cod_available" />
                        <span>Cash on delivery</span>
                    </label>
                    <p v-if="quote?.deliverable && !quote.cod_available" class="text-sm text-red-700" role="status">
                        Cash on delivery isn't available for this order. Online payment is coming soon.
                    </p>
                    <InputError :message="form.errors.payment_method" />
                </fieldset>

                <button
                    type="submit"
                    class="flex min-h-12 w-full items-center justify-center bg-ink text-xs font-medium uppercase tracking-[0.2em] text-brand-50 disabled:opacity-40"
                    :class="focusRing"
                    :disabled="!canPlaceOrder"
                >
                    {{ form.processing ? 'Placing order…' : quote?.deliverable ? `Place order · ${formatPaise(quote.total)}` : 'Enter your pincode' }}
                </button>
            </form>

            <aside class="h-fit border border-brand-200 p-4 md:sticky md:top-20" aria-labelledby="summary-title">
                <h2 id="summary-title" class="text-xs font-medium uppercase tracking-[0.2em]">Order summary</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li v-for="line in page.props.cart.lines" :key="line.variant_id" class="flex justify-between gap-4">
                        <span>{{ line.name }}<span v-if="variantLabel(line)" class="text-brand-600"> · {{ variantLabel(line) }}</span> × {{ line.quantity }}</span>
                        <span class="tabular-nums">{{ formatPaise(line.line_total) }}</span>
                    </li>
                </ul>
                <dl class="mt-4 space-y-1 border-t border-brand-200 pt-4 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd class="tabular-nums">{{ formatPaise(page.props.cart.subtotal) }}</dd></div>
                    <div class="flex justify-between">
                        <dt>Shipping</dt>
                        <dd class="tabular-nums">{{ quote?.deliverable ? (quote.shipping ? formatPaise(quote.shipping) : 'Free') : '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-base font-medium">
                        <dt>Total</dt>
                        <dd class="tabular-nums">{{ formatPaise(quote?.deliverable ? quote.total : page.props.cart.subtotal) }}</dd>
                    </div>
                </dl>
            </aside>
        </div>
    </StoreLayout>
</template>
```

- [ ] **Step 6: Build and commit**

```bash
source ~/.nvm/nvm.sh && nvm use && npm run build
vendor/bin/pint --dirty --format agent
git add app routes resources/js tests/Feature/Storefront/CheckoutPageTest.php
git commit -m "feat: checkout page with pincode shipping quote"
```

---

### Task 6: Place COD orders and the signed order page

**Design:** Invoke `ui-ux-pro-max` for the order confirmation / tracking page. Same visual language.

**Files:**
- Create: `app/Actions/PlaceOrder.php`, `app/Http/Requests/CheckoutRequest.php`, `app/Http/Controllers/OrderController.php`, `resources/js/Pages/Orders/Show.vue`
- Modify: `app/Http/Controllers/CheckoutController.php` (add `store()`), `routes/web.php`
- Test: `tests/Feature/Storefront/PlaceOrderTest.php`

**Interfaces:**
- Consumes: `Cart` (Task 3); `ShippingZone`, `Order`, `OrderItem`, `Address`, enums, `Order::trackingUrl()` (Task 1); `Product::visible()`, `ProductVariant::effectivePrice()` (Phase 2).
- Produces:
  - `PlaceOrder::handle(?User $user, array $customer, array $quantities): Order`:
    - `$customer` holds the validated `CheckoutRequest` data; `$quantities` maps variant id → quantity.
    - It throws `ValidationException` with key `cart`, `pincode` or `payment_method`.
  - Routes:
    - `checkout.store` POST `/checkout` (throttled to 10/min)
    - `orders.show` GET `/orders/{order}`, behind the `signed` middleware
  - Page `Orders/Show` props: `order {number, placed_at, payment_method, payment_status, fulfilment_status, tracking_note, customer_name, customer_phone, address: string[], subtotal, shipping, total, items: [{name, variant, unit_price, quantity, line_total}]}` and `just_placed: bool`.
  - Task 7 adds the email queueing to the end of `PlaceOrder::handle()`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Storefront/PlaceOrderTest --no-interaction`:
```php
<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;

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
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($order->items()->sole()->only('product_name', 'size', 'colour', 'unit_price', 'quantity'))
        ->toBe(['product_name' => 'Wolf Tee', 'size' => 'M', 'colour' => 'Black', 'unit_price' => 99900, 'quantity' => 2])
        ->and($this->tee->fresh()->stock)->toBe(3);
});

test('shipping is charged below the free-above amount', function () {
    $this->withSession(['cart' => [$this->tee->id => 1]])->post('/checkout', checkoutPayload());

    expect(Order::sole()->only('shipping', 'total'))->toBe(['shipping' => 4000, 'total' => 103900]);
});

test('a shortage rolls the whole order back', function () {
    $mug = ProductVariant::factory()->create(['stock' => 1]);

    $this->withSession(['cart' => [$this->tee->id => 1, $mug->id => 3]])
        ->post('/checkout', checkoutPayload())
        ->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0)
        ->and($this->tee->fresh()->stock)->toBe(5)
        ->and($mug->fresh()->stock)->toBe(1);
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

test('online payment is not offered yet', function () {
    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']))
        ->assertSessionHasErrors('payment_method');
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
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Storefront/PlaceOrderTest.php`
Expected: FAIL — 405 on POST `/checkout`, 404 on `/orders/{id}`.

- [ ] **Step 3: Form Request**

`php artisan make:request CheckoutRequest --no-interaction`:
```php
<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Address;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'line1' => ['required', 'string', 'max:200'],
            'locality' => ['required', 'string', 'max:100'],
            'district' => ['required', Rule::in(Address::DISTRICTS)],
            'pincode' => ['required', 'digits:6'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::Cod])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a 10-digit mobile number, without +91.',
        ];
    }
}
```

- [ ] **Step 4: PlaceOrder action**

`php artisan make:class Actions/PlaceOrder --no-interaction`:
```php
<?php

namespace App\Actions;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    /**
     * Create the order in one transaction: lock and re-check every variant, price from current data,
     * snapshot the items and address, and decrement stock. Any failure rolls everything back.
     *
     * @param  array{name: string, phone: string, email: ?string, line1: string, locality: string, district: string, pincode: string, payment_method: string}  $customer
     * @param  array<int, int>  $quantities  variant id => quantity
     */
    public function handle(?User $user, array $customer, array $quantities): Order
    {
        if ($quantities === []) {
            throw ValidationException::withMessages(['cart' => 'Your bag is empty.']);
        }

        $zone = ShippingZone::forPincode($customer['pincode'])
            ?? throw ValidationException::withMessages(['pincode' => "We don't deliver to this pincode yet."]);

        $paymentMethod = PaymentMethod::from($customer['payment_method']);

        return DB::transaction(function () use ($user, $customer, $quantities, $zone, $paymentMethod): Order {
            $variants = ProductVariant::with('product')
                ->whereKey(array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $visibleProductIds = Product::visible()->whereKey($variants->pluck('product_id'))->pluck('id');
            $subtotal = 0;

            foreach ($quantities as $variantId => $quantity) {
                $variant = $variants->get($variantId);

                if (! $variant || ! $variant->is_active || ! $visibleProductIds->contains($variant->product_id)) {
                    throw ValidationException::withMessages(['cart' => 'An item in your bag is no longer available.']);
                }

                if ($variant->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Only {$variant->stock} left of {$variant->product->name}".$this->variantSuffix($variant).'.',
                    ]);
                }

                $subtotal += $variant->effectivePrice() * $quantity;
            }

            $shipping = $zone->shippingFor($subtotal);
            $total = $subtotal + $shipping;

            if ($paymentMethod === PaymentMethod::Cod && ! $zone->allowsCod($total)) {
                throw ValidationException::withMessages(['payment_method' => "Cash on delivery isn't available for this order."]);
            }

            $order = Order::create([
                'user_id' => $user?->id,
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'customer_email' => $customer['email'] ?? null,
                'ship_line1' => $customer['line1'],
                'ship_locality' => $customer['locality'],
                'ship_district' => $customer['district'],
                'ship_pincode' => $customer['pincode'],
                'shipping_zone_id' => $zone->id,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'payment_status' => PaymentStatus::Pending,
                'fulfilment_status' => FulfilmentStatus::Confirmed,
                'placed_at' => now(),
            ]);

            foreach ($quantities as $variantId => $quantity) {
                $variant = $variants->get($variantId);

                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'size' => $variant->size,
                    'colour' => $variant->colour,
                    'unit_price' => $variant->effectivePrice(),
                    'quantity' => $quantity,
                ]);

                $variant->decrement('stock', $quantity);
            }

            if ($user) {
                $user->addresses()->firstOrCreate(Arr::only($customer, ['name', 'phone', 'line1', 'locality', 'district', 'pincode']));

                if (! $user->phone) {
                    $user->forceFill(['phone' => $customer['phone']])->save();
                }
            }

            return $order;
        });
    }

    private function variantSuffix(ProductVariant $variant): string
    {
        $label = collect([$variant->colour, $variant->size])->filter()->implode(' / ');

        return $label === '' ? '' : " ({$label})";
    }
}
```

- [ ] **Step 5: Controllers and routes**

Add to `CheckoutController` (imports `App\Actions\PlaceOrder`, `App\Http\Requests\CheckoutRequest`):
```php
    public function store(CheckoutRequest $request, Cart $cart, PlaceOrder $placeOrder): RedirectResponse
    {
        $order = $placeOrder->handle($request->user(), $request->validated(), $cart->quantities());
        $cart->clear();

        return redirect($order->trackingUrl())->with('order_placed', true);
    }
```

`php artisan make:controller OrderController --no-interaction`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Order status page. The route is signed, so the link itself is the permission.
     */
    public function show(Order $order): Response
    {
        $order->load('items');

        return Inertia::render('Orders/Show', [
            'order' => [
                'number' => $order->number,
                'placed_at' => $order->placed_at->format('j M Y, g:i a'),
                'payment_method' => $order->payment_method->label(),
                'payment_status' => $order->payment_status->label(),
                'fulfilment_status' => $order->fulfilment_status->label(),
                'tracking_note' => $order->tracking_note,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'address' => [$order->ship_line1, $order->ship_locality, "{$order->ship_district} {$order->ship_pincode}"],
                'subtotal' => $order->subtotal,
                'shipping' => $order->shipping,
                'total' => $order->total,
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'name' => $item->product_name,
                    'variant' => collect([$item->colour, $item->size])->filter()->implode(' / '),
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->lineTotal(),
                ]),
            ],
            'just_placed' => (bool) session('order_placed'),
        ]);
    }
}
```

`routes/web.php` — import and add:
```php
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('signed')->name('orders.show');
```

- [ ] **Step 6: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Storefront/PlaceOrderTest.php`
Expected: 14 PASS (the phone dataset counts 4).

- [ ] **Step 7: Order page**

`resources/js/Pages/Orders/Show.vue`:
```vue
<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

defineProps({
    order: { type: Object, required: true },
    just_placed: { type: Boolean, required: true },
});

const page = usePage();
</script>

<template>
    <Head :title="`Order ${order.number}`" />

    <StoreLayout>
        <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
            <div v-if="just_placed" class="mb-8 border border-brand-300 bg-brand-100 px-4 py-4" role="status">
                <p class="font-display text-2xl font-semibold">Thank you, {{ order.customer_name }}.</p>
                <p class="mt-1 text-sm text-brand-700">
                    Your order is confirmed. We'll call {{ order.customer_phone }} before delivery. Bookmark this page to check on your order.
                </p>
            </div>

            <p class="text-xs uppercase tracking-[0.3em] text-brand-600">Order</p>
            <h1 class="mt-1 font-display text-3xl font-semibold tabular-nums">{{ order.number }}</h1>
            <p class="mt-1 text-sm text-brand-700">Placed {{ order.placed_at }}</p>

            <dl class="mt-6 grid grid-cols-2 gap-4 border-y border-brand-200 py-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-[0.2em] text-brand-600">Status</dt>
                    <dd class="mt-1 font-medium">{{ order.fulfilment_status }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-[0.2em] text-brand-600">Payment</dt>
                    <dd class="mt-1">{{ order.payment_method }} · {{ order.payment_status }}</dd>
                </div>
                <div v-if="order.tracking_note" class="col-span-2">
                    <dt class="text-xs uppercase tracking-[0.2em] text-brand-600">Delivery note</dt>
                    <dd class="mt-1 whitespace-pre-line">{{ order.tracking_note }}</dd>
                </div>
            </dl>

            <ul class="mt-6 space-y-3 text-sm">
                <li v-for="(item, index) in order.items" :key="index" class="flex justify-between gap-4">
                    <span>{{ item.name }}<span v-if="item.variant" class="text-brand-600"> · {{ item.variant }}</span> × {{ item.quantity }}</span>
                    <span class="tabular-nums">{{ formatPaise(item.line_total) }}</span>
                </li>
            </ul>
            <dl class="mt-4 space-y-1 border-t border-brand-200 pt-4 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd class="tabular-nums">{{ formatPaise(order.subtotal) }}</dd></div>
                <div class="flex justify-between"><dt>Shipping</dt><dd class="tabular-nums">{{ order.shipping ? formatPaise(order.shipping) : 'Free' }}</dd></div>
                <div class="flex justify-between text-base font-medium"><dt>Total</dt><dd class="tabular-nums">{{ formatPaise(order.total) }}</dd></div>
            </dl>

            <div class="mt-8 text-sm">
                <h2 class="text-xs uppercase tracking-[0.2em] text-brand-600">Delivering to</h2>
                <address class="mt-1 not-italic">
                    {{ order.customer_name }}<br />
                    <template v-for="line in order.address" :key="line">{{ line }}<br /></template>
                </address>
            </div>

            <p v-if="page.props.store.whatsapp_number" class="mt-8 text-sm">
                Questions?
                <a :href="`https://wa.me/91${page.props.store.whatsapp_number}?text=${encodeURIComponent(`Hi, about order ${order.number}`)}`" target="_blank" rel="noopener" class="underline underline-offset-4">Message us on WhatsApp</a>
            </p>
        </div>
    </StoreLayout>
</template>
```

- [ ] **Step 8: Browser check (Tasks 4–6 together)**

Run `npm run build` and `php artisan migrate:fresh --seed`. Then, at 375 px:
1. Add two sizes of a tee to the bag. The drawer opens, and the bag count updates.
2. Change quantities on `/cart`.
3. Check out with pincode `796001`: the quote shows shipping, and the COD option is available.
4. Try `110001`: you see the "We don't deliver" message.
5. Place the order. You land on the signed order page with the thank-you banner.
6. Reload: the banner is gone.
7. Remove the `signature` query parameter from the URL: you get a 403.

- [ ] **Step 9: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes resources/js tests/Feature/Storefront/PlaceOrderTest.php
git commit -m "feat: place cash on delivery orders with signed order page"
```

---

### Task 7: Order emails

**Files:**
- Create: `app/Mail/OrderPlaced.php`, `app/Mail/NewOrderReceived.php`
- Create: `resources/views/mail/orders/placed.blade.php`, `resources/views/mail/orders/received.blade.php`, `resources/views/components/paise.blade.php`
- Modify: `app/Actions/PlaceOrder.php`
- Test: `tests/Feature/OrderEmailTest.php`

**Interfaces:**
- Consumes: `PlaceOrder::handle()` (Task 6); `Order::trackingUrl()`, `Order::items()`, `OrderItem::lineTotal()` (Task 1); `Setting::values()` (Phase 1).
- Produces:
  - `new OrderPlaced(Order $order)` (customer) and `new NewOrderReceived(Order $order)` (admins). Both are `ShouldQueue` Markdown mailables.
  - `<x-paise :amount="int" />` Blade component (`₹1,299`, `₹1,299.50`, Indian grouping).

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest OrderEmailTest --no-interaction`:
```php
<?php

use App\Mail\NewOrderReceived;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

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
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/OrderEmailTest.php`
Expected: FAIL — `Class "App\Mail\OrderPlaced" not found`.

- [ ] **Step 3: Money component and mailables**

`resources/views/components/paise.blade.php`:
```blade
@props(['amount'])
{{ \Illuminate\Support\Number::currency($amount / 100, 'INR', 'en_IN', $amount % 100 ? 2 : 0) }}
```

`php artisan make:mail OrderPlaced --markdown=mail.orders.placed --no-interaction`:
```php
<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlaced extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Setting::values()['store_name']." order {$this->order->number} is confirmed");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.placed', with: ['trackingUrl' => $this->order->trackingUrl()]);
    }
}
```

`resources/views/mail/orders/placed.blade.php`:
```blade
<x-mail::message>
# Thank you, {{ $order->customer_name }}

Your order **{{ $order->number }}** is confirmed. You pay cash on delivery, and we'll call {{ $order->customer_phone }} before we deliver.

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }}@if ($item->colour || $item->size) ({{ collect([$item->colour, $item->size])->filter()->implode(' / ') }})@endif | {{ $item->quantity }} | <x-paise :amount="$item->lineTotal()" /> |
@endforeach
| Shipping | | @if ($order->shipping) <x-paise :amount="$order->shipping" /> @else Free @endif |
| **Total** | | **<x-paise :amount="$order->total" />** |
</x-mail::table>

**Delivering to:** {{ $order->ship_line1 }}, {{ $order->ship_locality }}, {{ $order->ship_district }} {{ $order->ship_pincode }}

<x-mail::button :url="$trackingUrl">
Track your order
</x-mail::button>

{{ \App\Models\Setting::values()['store_name'] }}
</x-mail::message>
```

`php artisan make:mail NewOrderReceived --markdown=mail.orders.received --no-interaction`:
```php
<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrderReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New order {$this->order->number}: {$this->order->ship_district}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.received', with: ['trackingUrl' => $this->order->trackingUrl()]);
    }
}
```

`resources/views/mail/orders/received.blade.php`:
```blade
<x-mail::message>
# New order {{ $order->number }}

**{{ $order->customer_name }}**, {{ $order->customer_phone }}@if ($order->customer_email), {{ $order->customer_email }}@endif

{{ $order->ship_line1 }}, {{ $order->ship_locality }}, {{ $order->ship_district }} {{ $order->ship_pincode }}

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }}@if ($item->colour || $item->size) ({{ collect([$item->colour, $item->size])->filter()->implode(' / ') }})@endif | {{ $item->quantity }} | <x-paise :amount="$item->lineTotal()" /> |
@endforeach
| **Total ({{ $order->payment_method->label() }})** | | **<x-paise :amount="$order->total" />** |
</x-mail::table>

<x-mail::button :url="$trackingUrl">
View order
</x-mail::button>
</x-mail::message>
```

- [ ] **Step 4: Queue the emails after the order commits**

In `PlaceOrder::handle()`, replace `return DB::transaction(function () ...` with `$order = DB::transaction(function () ...` (same closure). After the transaction, add (imports `App\Mail\NewOrderReceived`, `App\Mail\OrderPlaced`, `Illuminate\Support\Facades\Mail`):
```php
        $order->load('items');

        if ($order->customer_email) {
            Mail::to($order->customer_email)->queue(new OrderPlaced($order));
        }

        $adminEmails = User::where('is_admin', true)->pluck('email');

        if ($adminEmails->isNotEmpty()) {
            Mail::to($adminEmails)->queue(new NewOrderReceived($order));
        }

        return $order;
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/OrderEmailTest.php tests/Feature/Storefront/PlaceOrderTest.php`
Expected: all PASS.

- [ ] **Step 6: Look at a rendered email**

With `MAIL_MAILER=log` in `.env`, place an order locally and run `php artisan queue:work --once`. Read the email HTML in `storage/logs/laravel.log`, and check that the prices read `₹1,299`, not `₹1,299.00`.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources/views tests/Feature/OrderEmailTest.php
git commit -m "feat: queued order confirmation and new-order emails"
```

---

### Task 8: Account page

**Design:** Invoke `ui-ux-pro-max` for the account page (order history list, saved addresses). Same visual language.

**Files:**
- Create: `app/Http/Controllers/AccountController.php`, `app/Http/Controllers/AddressController.php`, `resources/js/Pages/Account/Show.vue`
- Delete: `resources/js/Pages/Dashboard.vue`
- Modify: `routes/web.php`, `resources/js/Layouts/StoreLayout.vue`
- Test: `tests/Feature/Storefront/AccountTest.php`

**Interfaces:**
- Consumes: `User::orders()`, `User::addresses()`, `Order::trackingUrl()`, `FulfilmentStatus::label()` (Task 1).
- Produces:
  - Route `account` GET `/account` (auth), page `Account/Show` with:
    - `orders [{number, placed_at, total, status, url}]`
    - `addresses [{id, name, phone, line1, locality, district, pincode}]`
  - Route `account.addresses.destroy` DELETE `/account/addresses/{address}`.
  - `dashboard` is now a redirect to `/account`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Storefront/AccountTest --no-interaction`:
```php
<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\User;

test('guests are sent to log in', function () {
    $this->get('/account')->assertRedirect('/login');
});

test('the dashboard link now goes to the account page', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/account');
});

test('the account page lists only the shopper\'s own orders, with signed links', function () {
    $user = User::factory()->create();
    $mine = Order::factory()->for($user)->create();
    Order::factory()->for(User::factory())->create();
    Order::factory()->create();

    $this->actingAs($user)
        ->get('/account')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Account/Show')
            ->has('orders', 1)
            ->where('orders.0.number', $mine->number)
            ->where('orders.0.url', $mine->trackingUrl()));
});

test('a shopper can delete their own saved address but not someone else\'s', function () {
    $user = User::factory()->create();
    $mine = Address::factory()->for($user)->create();
    $theirs = Address::factory()->create();

    $this->actingAs($user)->delete("/account/addresses/{$theirs->id}")->assertNotFound();
    $this->actingAs($user)->delete("/account/addresses/{$mine->id}")->assertRedirect();

    expect(Address::pluck('id')->all())->toBe([$theirs->id]);
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Storefront/AccountTest.php`
Expected: FAIL — 404 on `/account`.

- [ ] **Step 3: Controllers and routes**

`php artisan make:controller AccountController --invokable --no-interaction`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Show', [
            'orders' => $user->orders()->latest('placed_at')->get()->map(fn (Order $order): array => [
                'number' => $order->number,
                'placed_at' => $order->placed_at->format('j M Y'),
                'total' => $order->total,
                'status' => $order->fulfilment_status->label(),
                'url' => $order->trackingUrl(),
            ]),
            'addresses' => $user->addresses()->latest('id')->get(['id', 'name', 'phone', 'line1', 'locality', 'district', 'pincode']),
        ]);
    }
}
```

`php artisan make:controller AddressController --no-interaction`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $address->delete();

        return back()->with('status', 'Address removed.');
    }
}
```

In `routes/web.php`:
- Replace the `/dashboard` closure route (and the now-unused `Inertia` import) with:
```php
Route::redirect('/dashboard', '/account')->name('dashboard');
```
- Inside the existing `Route::middleware('auth')->group(...)`, add:
```php
    Route::get('/account', AccountController::class)->name('account');
    Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('account.addresses.destroy');
```

Delete `resources/js/Pages/Dashboard.vue`. `AuthenticatedLayout.vue` still links to `route('dashboard')`, which now redirects to the account page.

In `StoreLayout.vue`, change the account link's `route('dashboard')` to `route('account')`.

- [ ] **Step 4: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Storefront/AccountTest.php tests/Feature/Auth`
Expected: all PASS. The Breeze tests still assert `route('dashboard')`, which still exists.

- [ ] **Step 5: Account page**

`resources/js/Pages/Account/Show.vue`:
```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

defineProps({
    orders: { type: Array, required: true },
    addresses: { type: Array, required: true },
});

const removeAddress = (address) => {
    router.delete(route('account.addresses.destroy', address.id), {
        preserveScroll: true,
        onBefore: () => window.confirm('Remove this address?'),
    });
};

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <Head title="Your account" />

    <StoreLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
            <div class="flex flex-wrap items-baseline justify-between gap-4">
                <h1 class="font-display text-3xl font-semibold sm:text-4xl">Your account</h1>
                <div class="flex gap-6 text-sm">
                    <Link :href="route('profile.edit')" class="underline underline-offset-4" :class="focusRing">Profile & password</Link>
                    <Link :href="route('logout')" method="post" as="button" class="underline underline-offset-4" :class="focusRing">Log out</Link>
                </div>
            </div>

            <section class="mt-10" aria-labelledby="orders-title">
                <h2 id="orders-title" class="text-xs font-medium uppercase tracking-[0.2em]">Orders</h2>
                <ul v-if="orders.length" class="mt-4 divide-y divide-brand-200 border-y border-brand-200">
                    <li v-for="order in orders" :key="order.number">
                        <a :href="order.url" class="flex min-h-14 items-center justify-between gap-4 py-3 text-sm hover:bg-brand-100" :class="focusRing">
                            <span>
                                <span class="font-medium tabular-nums">{{ order.number }}</span>
                                <span class="block text-brand-600">{{ order.placed_at }}</span>
                            </span>
                            <span class="text-right">
                                <span class="block tabular-nums">{{ formatPaise(order.total) }}</span>
                                <span class="block text-brand-600">{{ order.status }}</span>
                            </span>
                        </a>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-brand-700">No orders yet.</p>
            </section>

            <section class="mt-10" aria-labelledby="addresses-title">
                <h2 id="addresses-title" class="text-xs font-medium uppercase tracking-[0.2em]">Saved addresses</h2>
                <ul v-if="addresses.length" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <li v-for="address in addresses" :key="address.id" class="border border-brand-200 p-4 text-sm">
                        <address class="not-italic">
                            {{ address.name }} · {{ address.phone }}<br />
                            {{ address.line1 }}<br />
                            {{ address.locality }}, {{ address.district }} {{ address.pincode }}
                        </address>
                        <button type="button" class="mt-2 min-h-11 underline underline-offset-4" :class="focusRing" @click="removeAddress(address)">Remove</button>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-brand-700">Addresses you check out with are saved here.</p>
            </section>
        </div>
    </StoreLayout>
</template>
```

- [ ] **Step 6: Build and commit**

```bash
source ~/.nvm/nvm.sh && nvm use && npm run build
vendor/bin/pint --dirty --format agent
git add -A app routes resources/js tests/Feature/Storefront/AccountTest.php
git commit -m "feat: account page with orders and saved addresses"
```

---

### Task 9: Admin safety for products and stock (Phase 2 follow-ups)

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductController.php` (`destroy()`), `resources/js/Pages/Admin/Products/Index.vue`
- Modify: `app/Http/Requests/Admin/UpdateProductVariantsRequest.php`, `app/Http/Controllers/Admin/ProductVariantController.php`, `resources/js/Pages/Admin/Products/Partials/VariantGrid.vue`
- Test: `tests/Feature/Admin/ProductManagementTest.php`, `tests/Feature/Admin/ProductVariantTest.php`

**Interfaces:**
- Consumes: `OrderItem`, `OrderItemFactory` (Task 1).
- Produces:
  - The variant grid payload gains `variants.*.stock_was`: the stock the admin saw when the page loaded, `null` for new rows.
  - The server rejects a save when an existing row's live stock differs from its `stock_was`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/ProductManagementTest.php` (import `App\Models\OrderItem`):
```php
test('a product with orders cannot be deleted', function () {
    $item = OrderItem::factory()->create();
    $product = $item->variant->product;

    $this->actingAs($this->admin)
        ->delete("/admin/products/{$product->id}")
        ->assertSessionHasErrors('product');

    expect($product->fresh())->not->toBeNull();
});
```

Append to `tests/Feature/Admin/ProductVariantTest.php`:
```php
test('saving is refused when stock changed since the page loaded', function () {
    $variant = ProductVariant::factory()->for($this->product)->create(['size' => 'M', 'colour' => 'Black', 'sku' => 'TEE-BLACK-M', 'stock' => 4]);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(['id' => $variant->id, 'stock' => 10, 'stock_was' => 5]),
        ]])
        ->assertSessionHasErrors('variants.0.stock');

    expect($variant->fresh()->stock)->toBe(4);
});

test('saving works when stock is unchanged since the page loaded', function () {
    $variant = ProductVariant::factory()->for($this->product)->create(['size' => 'M', 'colour' => 'Black', 'sku' => 'TEE-BLACK-M', 'stock' => 4]);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(['id' => $variant->id, 'stock' => 10, 'stock_was' => 4]),
        ]])
        ->assertSessionHasNoErrors();

    expect($variant->fresh()->stock)->toBe(10);
});
```

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test --compact tests/Feature/Admin/ProductManagementTest.php tests/Feature/Admin/ProductVariantTest.php`
Expected: the delete test fails with a `QueryException` (FK violation), and the stale-stock test fails because no error is raised.

- [ ] **Step 3: Block deleting products with orders**

In `Admin\ProductController::destroy()`, at the top (import `App\Models\OrderItem`):
```php
        if (OrderItem::whereIn('product_variant_id', $product->variants()->select('id'))->exists()) {
            return back()->withErrors(['product' => "\"{$product->name}\" has orders. Untick \"Visible on the store\" to hide it instead."]);
        }
```

In `resources/js/Pages/Admin/Products/Index.vue`, above `<AdminTable>`:
```vue
        <p v-if="$page.props.errors.product" class="mb-4 rounded bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">
            {{ $page.props.errors.product }}
        </p>
```

- [ ] **Step 4: Reject stale stock in the variant grid**

In `UpdateProductVariantsRequest::rules()`, add:
```php
            'variants.*.stock_was' => ['nullable', 'integer', 'min:0'],
```

Replace `ProductVariantController::update()` (import `Illuminate\Validation\ValidationException`):
```php
    /**
     * Save the whole variant grid. Variants are never deleted; the admin deactivates them instead.
     * Rows are locked, and a row whose stock moved since the page loaded (an order came in) is refused.
     */
    public function update(UpdateProductVariantsRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $liveStock = $product->variants()->lockForUpdate()->pluck('stock', 'id');

            foreach ($request->validated('variants') as $index => $variant) {
                if (isset($variant['id'], $variant['stock_was']) && $liveStock[$variant['id']] !== (int) $variant['stock_was']) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.stock" => 'Stock changed since you opened this page (an order came in). Reload and try again.',
                    ]);
                }
            }

            foreach ($request->validated('variants') as $variant) {
                $attributes = Arr::except($variant, ['id', 'stock_was']);

                if (isset($variant['id'])) {
                    $product->variants()->whereKey($variant['id'])->update($attributes);
                } else {
                    $product->variants()->create($attributes);
                }
            }
        });

        return back()->with('status', 'Variants saved.');
    }
```

In `VariantGrid.vue`, add `stock_was: variant.stock` to the object returned by `toRows`. Newly generated rows have no `stock_was`, and the server treats them as new.

- [ ] **Step 5: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Admin`
Expected: all PASS, including the existing variant tests, which send no `stock_was`.

- [ ] **Step 6: Build, full suite, commit**

```bash
source ~/.nvm/nvm.sh && nvm use && npm run build
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add app resources/js tests/Feature/Admin
git commit -m "fix: block deleting ordered products and stale variant stock saves"
```
Expected: whole suite PASS.

---

## Phase 3 done when

- `php artisan test --compact` is green.
- A guest at 375 px can:
  1. add items, then adjust them in the drawer and on `/cart`;
  2. enter a Mizoram pincode and see shipping and COD availability;
  3. place a COD order;
  4. land on the signed order page.

  Stock drops by the ordered quantity.
- Logging in keeps the guest bag. A signed-in shopper's address and phone are reused at the next checkout, and `/account` lists their orders.
- An unknown pincode, a COD total over the zone limit, the COD switch turned off, and a stock shortage are all refused with a clear message, and nothing is written.
- `php artisan queue:work --stop-when-empty` delivers the receipt (when an email was given) and the admin alert. Prices show as `₹1,299`.
- Admin can manage shipping zones and pincodes. Deleting a product or zone that has orders is refused with an explanation.

## Known follow-ups (not Phase 3)

- Phase 4:
  - Razorpay (`payments` table, `AwaitingPayment` flow, webhook, abandoned-order cleanup, refunds).
  - The checkout payment radio gains "Online payment".
  - `PlaceOrder` gains the Razorpay branch.
- Phase 5:
  - Admin order list and detail, with fulfilment transitions.
  - Cancel (restores stock), dispatched and delivered emails, COD marked Paid on delivery.
  - Packing slip and the WhatsApp link.
- Phase 6: the production PHP needs `intl` (for `Number::currency`); configure Gmail SMTP, and run a Supervisor queue worker.
- Not built: carts persist indefinitely, and guest session carts end with the session (the default lifetime is 120 minutes).
