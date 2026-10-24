# Phase 5: Admin Orders & Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The admin can run every order from the browser:
- find orders by status, payment method, zone and date;
- move an order through its fulfilment steps;
- cancel it, with an automatic refund for paid online orders;
- retry failed refunds;
- print a packing slip and message the customer on WhatsApp.

Customers get queued "dispatched" and "delivered" emails. The dashboard shows today's orders, what needs action, low stock and revenue.

**Architecture:**
- **Timeline.** Every change of an order's fulfilment or payment status writes an `order_events` row. An Eloquent `created`/`updated` hook on `Order` does this, so the existing actions get a timeline without being edited: `PlaceOrder`, `MarkOrderPaid`, `CancelOrder`, `RefundPayment` and the unpaid-order sweep.
- **`AdvanceOrder`.** This new action is the one place an admin moves an order forward. It allows only `FulfilmentStatus::next()`. An online order must be paid first. A COD order becomes Paid when it is delivered. After the commit it queues the dispatched or delivered email.
- **Cancel and refund.** These reuse `CancelOrder` and `RefundPayment` unchanged. Two `Order` helpers say which payment rows an admin may refund: `mainPayment()` and `isRefundable()`. The scope `Order::refundOwed()` finds orders that still owe money back.
- **Admin screens.** `Admin\OrderController` serves the list (`AdminTable.vue`), the detail page and a plain Blade packing slip for printing. `Admin\DashboardController` replaces the static dashboard route.

**Tech Stack:** Laravel 13.38, PHP 8.4, Inertia v2 + Vue 3, Tailwind 3, Pest on PostgreSQL (`arusa_app_testing`), database queue, Markdown mailables.

**Spec:** `docs/superpowers/specs/2026-10-23-arusa-ecommerce-design.md`, sections 3, 4.7, 4.9, 6 (screens 1 and 2), 7 and 9.

## Global Constraints

- No new PHP/JS dependencies. **No Lucide**; no animation library; no icon package.
- All money is stored as **integer paise**. Format with `formatPaise()` (JS, `@/money`) or `<x-paise :amount="…" />` (Blade).
- `order_items` and the order's shipping address are **snapshots**: admin screens read them from the order, never from live product data.
- Fulfilment transitions allowed: Pending→Confirmed→Packed→Dispatched→Delivered; Cancelled from Pending, Confirmed, Packed. Reject anything else.
- Cancelling restores stock (`CancelOrder`). It runs in a DB transaction with `lockForUpdate()`, locking the order row before its variants.
- Refunds are **full only** and go through `RefundPayment`. Tests never reach Razorpay. Mock the wrapper with `$this->partialMock(Razorpay::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')->…)`.
- Emails are queued (`ShouldQueue`), never sent inline. Customer emails go out only when the order has `customer_email`.
- Admin routes: `routes/admin.php` behind `auth` + `can:access-admin`. Every admin Form Request's `authorize()` returns `$this->user()->can('access-admin')`.
- **Admin stays plain and functional. No design-skill passes and no decorative motion.** Reuse `AdminLayout.vue`, `AdminTable.vue` and the Tailwind classes already used on the admin pages: `rounded bg-white shadow-sm`, `rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white`, `rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500`.
- PHP: curly braces always, explicit return types, constructor promotion, PHPDoc over inline comments, TitleCase enum keys, `#[Scope]` attribute for scopes (as in `Product`).
- After PHP changes run `vendor/bin/pint --dirty --format agent`. Run the narrowest tests with `php artisan test --compact <path>`.
- Activate project skills while working: `arusa-commerce` (always), `laravel-best-practices` (PHP), `inertia-vue-development` (Vue pages), `tailwindcss-development` (styling), `testing-best-practices` (tests).
- Node: run `source ~/.nvm/nvm.sh && nvm use` before any npm command.
- Never print `.env` values.

## Decisions made in this plan

Owner decisions (2026-10-24):
- **The timeline is an `order_events` table.** Each row records the order's fulfilment and payment status after a change, the user who made it (null for the webhook, the sweep and guests) and the time. Existing orders get no backfill: the store is not live, and `migrate:fresh --seed` is fine on dev.
- **Admin refunds are full and only for cancelled orders.** Cancelling a paid online order refunds it at once. A Refund button appears only on a payment row that is still Paid on a cancelled order, so the admin can retry a failed refund. The same button appears on a duplicate capture (a second captured payment on one order) whose automatic refund failed. No partial refunds, and no refunds on an order that is still being fulfilled.
- **Needs action means four counts**, each linking to the filtered order list:
  - To pack (Confirmed);
  - To dispatch (Packed);
  - To deliver (Dispatched);
  - Refund owed (`Order::refundOwed()`).
- **Revenue is money received:** the sum of `total` for orders whose `payment_status` is Paid, grouped by `placed_at`. That counts online orders once captured and COD orders once delivered. Refunded and cancelled orders do not count. "7 days" is today plus the 6 days before it; "30 days" is today plus the 29 before.

Plan decisions:
- **The admin cannot move an unpaid online order to Confirmed.** The payment does that (`MarkOrderPaid`). COD orders start as Confirmed, so the admin never uses Pending→Confirmed in practice. `AdvanceOrder` still allows it for a paid order.
- **One step at a time.** The detail page shows one "Mark <next>" button. Skipping (Confirmed→Dispatched) is rejected.
- **COD becomes Paid inside the Delivered transition** (spec 4.9). It is not a separate button, and COD gets no `payments` row.
- **Dispatch takes an optional tracking note** ("Bus parcel, Lunglei, receipt 4411"). It is saved to `orders.tracking_note`, shown on the customer's order page (already wired) and included in the dispatched email.
- **`CancelOrder` and `RefundPayment` are reused unchanged.** The refund route passes `null` for the main payment, so `RefundPayment` marks the order Refunded. For a duplicate it passes the row itself, which leaves the order Paid. `CancelOrder` only swaps its inline `in_array` for the new `FulfilmentStatus::isCancellable()`.
- **If the refund fails during a cancel, the cancel still stands.** The page shows Razorpay's error, the order appears under "Refund owed", and the Refund button retries.
- **Order search:** `ARU-000123` (or `aru123`) matches the order id. Any other text matches the customer's name or phone.
- **The packing slip is a standalone Blade page** (`/admin/orders/{order}/slip`) with a Print button. It is not an Inertia page, so it prints without the admin chrome. One page serves as both packing slip and receipt: there is no GST, so no tax lines.
- **"Message customer"** is `https://wa.me/91{phone}?text=…` with a short prefilled message naming the store and the order number. It opens in a new tab.
- **`OrderEvent` has no factory.** Rows are only written by the `Order` hook, and tests create them through orders.

Phase 4 deferred Minor findings:
- Folded in:
  - **T3, no test for cancelling a paid order that hasn't been dispatched:** covered by the Task 4 admin cancel tests.
  - **T3, `CancelOrder` `?->increment` skips a missing variant:** no change needed. `order_items.product_variant_id` is `restrictOnDelete`, so an ordered variant cannot be deleted.
- The rest are under Known follow-ups.

## File Map

| File | Responsibility |
|---|---|
| `database/migrations/*_create_order_events_table.php`, `app/Models/OrderEvent.php` | Timeline rows |
| `app/Models/Order.php` | Event hook, `events()`, `refundOwed()` scope, `mainPayment()`, `isRefundable()` |
| `app/Enums/FulfilmentStatus.php` | `next()`, `isCancellable()` |
| `app/Actions/CancelOrder.php` | Use `isCancellable()` |
| `app/Actions/AdvanceOrder.php` | Validated forward transition, COD paid on delivery, emails |
| `app/Mail/{OrderDispatched,OrderDelivered}.php`, `resources/views/mail/orders/{dispatched,delivered}.blade.php` | Customer emails |
| `app/Http/Controllers/Admin/OrderController.php`, `app/Http/Requests/Admin/UpdateOrderStatusRequest.php` | List, detail, status, cancel, refund, slip |
| `resources/js/Pages/Admin/Orders/{Index,Show}.vue` | Order screens |
| `resources/views/admin/orders/slip.blade.php` | Printable packing slip / receipt |
| `app/Http/Controllers/Admin/DashboardController.php`, `resources/js/Pages/Admin/Dashboard.vue` | Dashboard |
| `routes/admin.php`, `resources/js/Layouts/AdminLayout.vue` | Routes, Orders nav link |
| `tests/Feature/OrderEventTest.php`, `tests/Feature/AdvanceOrderTest.php`, `tests/Feature/Admin/{OrderListTest,OrderWorkflowTest,PackingSlipTest,DashboardTest}.php` | Tests |

---

### Task 1: Order timeline

**Files:**
- Create: `database/migrations/*_create_order_events_table.php`, `app/Models/OrderEvent.php`
- Modify: `app/Models/Order.php`
- Test: `tests/Feature/OrderEventTest.php`

**Interfaces:**
- Consumes: the existing `Order` model, the `FulfilmentStatus` and `PaymentStatus` enums.
- Produces:
  - `OrderEvent` model: `order_id`, `fulfilment_status` (`FulfilmentStatus`), `payment_status` (`PaymentStatus`), `user_id` (`?int`), timestamps; `user(): BelongsTo`.
  - `Order::events(): HasMany`, oldest first.
  - Every `Order` create, and every update that changes `fulfilment_status` or `payment_status`, writes one event with `user_id = auth()->id()`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest OrderEventTest --no-interaction`, then replace its contents:

```php
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
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/OrderEventTest.php`
Expected: FAIL, `Call to undefined method App\Models\Order::events()`.

- [ ] **Step 3: Migration and model**

`php artisan make:model OrderEvent -m --no-interaction`. Migration `up()`:

```php
Schema::create('order_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->index()->constrained()->cascadeOnDelete();
    $table->string('fulfilment_status', 20);
    $table->string('payment_status', 20);
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamps();
});
```

`down()`: `Schema::dropIfExists('order_events');`

`app/Models/OrderEvent.php`:

```php
<?php

namespace App\Models;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One timeline entry: the order's statuses right after a change, and who made it (null for the webhook, the sweep or a guest).
 */
#[Fillable(['fulfilment_status', 'payment_status', 'user_id'])]
class OrderEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fulfilment_status' => FulfilmentStatus::class,
            'payment_status' => PaymentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: The hook and relation on `Order`**

In `app/Models/Order.php`, add after `casts()`:

```php
    /**
     * Every status change lands on the timeline, whichever action made it.
     */
    protected static function booted(): void
    {
        static::created(fn (Order $order) => $order->recordEvent());
        static::updated(function (Order $order): void {
            if ($order->wasChanged(['fulfilment_status', 'payment_status'])) {
                $order->recordEvent();
            }
        });
    }

    private function recordEvent(): void
    {
        $this->events()->create([
            'fulfilment_status' => $this->fulfilment_status,
            'payment_status' => $this->payment_status,
            'user_id' => auth()->id(),
        ]);
    }
```

Add the relation after `payments()`:

```php
    /**
     * Status timeline, oldest first.
     *
     * @return HasMany<OrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }
```

- [ ] **Step 5: Migrate and run the tests**

Run: `php artisan migrate --no-interaction && php artisan test --compact tests/Feature/OrderEventTest.php`
Expected: PASS (3 tests).

Then run the order-related suites to check that the hook breaks nothing:

Run: `php artisan test --compact tests/Feature/OrderReversalTest.php tests/Feature/RazorpayWebhookTest.php tests/Feature/AbandonedOrderTest.php tests/Feature/Storefront`
Expected: PASS.

- [ ] **Step 6: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/OrderEvent.php app/Models/Order.php tests/Feature/OrderEventTest.php
git commit -m "feat: record an order timeline on every status change"
```

---

### Task 2: Fulfilment transitions and the dispatched/delivered emails

**Files:**
- Modify: `app/Enums/FulfilmentStatus.php`, `app/Actions/CancelOrder.php`
- Create: `app/Actions/AdvanceOrder.php`, `app/Mail/OrderDispatched.php`, `app/Mail/OrderDelivered.php`, `resources/views/mail/orders/dispatched.blade.php`, `resources/views/mail/orders/delivered.blade.php`
- Test: `tests/Feature/AdvanceOrderTest.php`

**Interfaces:**
- Consumes: `Order` (Task 1 writes the timeline automatically), `Order::trackingUrl()`, `Setting::values()`.
- Produces:
  - `FulfilmentStatus::next(): ?FulfilmentStatus`. Pending→Confirmed→Packed→Dispatched→Delivered; null for Delivered and Cancelled.
  - `FulfilmentStatus::isCancellable(): bool`, true for Pending, Confirmed and Packed.
  - `App\Actions\AdvanceOrder::handle(Order $order, FulfilmentStatus $status, ?string $trackingNote = null): Order`. Throws `ValidationException` with key `status` when `$status` is not `next()`, or when an online order isn't Paid.
  - `App\Mail\OrderDispatched(Order $order)` and `App\Mail\OrderDelivered(Order $order)`, both `ShouldQueue`.

- [ ] **Step 1: Write the failing tests**

`php artisan make:test --pest AdvanceOrderTest --no-interaction`, then replace its contents:

```php
<?php

use App\Actions\AdvanceOrder;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderDelivered;
use App\Mail\OrderDispatched;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Mail::fake();
});

test('a COD order moves one step at a time and is paid on delivery', function () {
    $order = Order::factory()->create();

    foreach ([FulfilmentStatus::Packed, FulfilmentStatus::Dispatched] as $status) {
        app(AdvanceOrder::class)->handle($order, $status);
        expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
    }

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Delivered);

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Delivered)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a step cannot be skipped', function () {
    $order = Order::factory()->create();

    expect(fn () => app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Dispatched))
        ->toThrow(ValidationException::class);

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed);
});

test('a cancelled order cannot move on', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Cancelled]);

    expect(fn () => app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Confirmed))
        ->toThrow(ValidationException::class);
});

test('an unpaid online order cannot be confirmed by hand', function () {
    $order = Order::factory()->razorpay()->create();

    expect(fn () => app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Confirmed))
        ->toThrow(ValidationException::class);

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Pending);
});

test('a paid online order keeps its payment status on delivery', function () {
    $order = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Dispatched]);

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Delivered);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('dispatching saves the tracking note and emails the customer', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Packed, 'customer_email' => 'mami@example.com']);

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Dispatched, 'Bus parcel, receipt 4411');

    expect($order->fresh()->tracking_note)->toBe('Bus parcel, receipt 4411');
    Mail::assertQueued(OrderDispatched::class, fn (OrderDispatched $mail) => $mail->hasTo('mami@example.com'));
});

test('delivering emails the customer', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Dispatched, 'customer_email' => 'mami@example.com']);

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Delivered);

    Mail::assertQueued(OrderDelivered::class, fn (OrderDelivered $mail) => $mail->hasTo('mami@example.com'));
});

test('no email is sent without a customer email', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Packed]);

    app(AdvanceOrder::class)->handle($order, FulfilmentStatus::Dispatched);

    Mail::assertNothingQueued();
});

test('the emails render with the tracking link', function () {
    $order = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Dispatched, 'tracking_note' => 'Bus parcel']);

    expect((new OrderDispatched($order))->render())->toContain($order->number)->toContain('Bus parcel')
        ->and((new OrderDelivered($order))->render())->toContain($order->number);
});
```

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test --compact tests/Feature/AdvanceOrderTest.php`
Expected: FAIL, `Class "App\Actions\AdvanceOrder" not found`.

- [ ] **Step 3: Transition rules on the enum**

In `app/Enums/FulfilmentStatus.php`, add after `label()`:

```php
    /**
     * The one status an admin may move an order to next; null at the end of the line.
     * Cancelling is separate (CancelOrder).
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Pending => self::Confirmed,
            self::Confirmed => self::Packed,
            self::Packed => self::Dispatched,
            self::Dispatched => self::Delivered,
            self::Delivered, self::Cancelled => null,
        };
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Packed], true);
    }
```

In `app/Actions/CancelOrder.php`, replace

```php
            if (! in_array($order->fulfilment_status, [FulfilmentStatus::Pending, FulfilmentStatus::Confirmed, FulfilmentStatus::Packed], true)) {
```

with

```php
            if (! $order->fulfilment_status->isCancellable()) {
```

- [ ] **Step 4: The mailables and their views**

Run:

```bash
php artisan make:mail OrderDispatched --markdown=mail.orders.dispatched --no-interaction
php artisan make:mail OrderDelivered --markdown=mail.orders.delivered --no-interaction
```

`app/Mail/OrderDispatched.php`, written to match `OrderPlaced`:

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

class OrderDispatched extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Setting::values()['store_name']." order {$this->order->number} is on its way");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.dispatched', with: ['trackingUrl' => $this->order->trackingUrl()]);
    }
}
```

`app/Mail/OrderDelivered.php` is the same, except for the class name, subject `" order {$this->order->number} was delivered"` and view `mail.orders.delivered`.

`resources/views/mail/orders/dispatched.blade.php`:

```blade
<x-mail::message>
# Your order is on its way

Hi {{ $order->customer_name }}, order **{{ $order->number }}** has left us and is on its way to {{ $order->ship_locality }}, {{ $order->ship_district }}.
@if ($order->tracking_note)

**Delivery details:** {{ $order->tracking_note }}
@endif
@if ($order->payment_method === \App\Enums\PaymentMethod::Cod)

Please keep <x-paise :amount="$order->total" /> ready to pay on delivery.
@endif

<x-mail::button :url="$trackingUrl">
Track your order
</x-mail::button>

{{ \App\Models\Setting::values()['store_name'] }}
</x-mail::message>
```

`resources/views/mail/orders/delivered.blade.php`:

```blade
<x-mail::message>
# Delivered

Hi {{ $order->customer_name }}, order **{{ $order->number }}** has been delivered. Thank you for shopping with us!

<x-mail::button :url="$trackingUrl">
View your order
</x-mail::button>

{{ \App\Models\Setting::values()['store_name'] }}
</x-mail::message>
```

- [ ] **Step 5: The action**

`app/Actions/AdvanceOrder.php`:

```php
<?php

namespace App\Actions;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\OrderDelivered;
use App\Mail\OrderDispatched;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdvanceOrder
{
    /**
     * Move the order one step along Pending → Confirmed → Packed → Dispatched → Delivered.
     * An online order must be paid first; a COD order becomes paid when it is delivered.
     * The customer is emailed when the order is dispatched and when it is delivered.
     */
    public function handle(Order $order, FulfilmentStatus $status, ?string $trackingNote = null): Order
    {
        $order = DB::transaction(function () use ($order, $status, $trackingNote): Order {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->fulfilment_status->next() !== $status) {
                throw ValidationException::withMessages(['status' => "A {$order->fulfilment_status->label()} order can't be marked {$status->label()}."]);
            }

            if ($order->payment_method === PaymentMethod::Razorpay && $order->payment_status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages(['status' => "This order isn't paid yet."]);
            }

            $order->fulfilment_status = $status;

            if ($status === FulfilmentStatus::Dispatched && $trackingNote !== null) {
                $order->tracking_note = $trackingNote;
            }

            if ($status === FulfilmentStatus::Delivered && $order->payment_method === PaymentMethod::Cod) {
                $order->payment_status = PaymentStatus::Paid;
            }

            $order->save();

            return $order;
        });

        $mail = match ($status) {
            FulfilmentStatus::Dispatched => new OrderDispatched($order),
            FulfilmentStatus::Delivered => new OrderDelivered($order),
            default => null,
        };

        if ($mail && $order->customer_email) {
            Mail::to($order->customer_email)->queue($mail);
        }

        return $order;
    }
}
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact tests/Feature/AdvanceOrderTest.php tests/Feature/OrderReversalTest.php`
Expected: PASS.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/FulfilmentStatus.php app/Actions/CancelOrder.php app/Actions/AdvanceOrder.php app/Mail resources/views/mail/orders tests/Feature/AdvanceOrderTest.php
git commit -m "feat: validated fulfilment steps with dispatched and delivered emails"
```

---

### Task 3: Admin order list

**Files:**
- Create: `app/Http/Controllers/Admin/OrderController.php`, `resources/js/Pages/Admin/Orders/Index.vue`
- Modify: `app/Models/Order.php`, `routes/admin.php`, `resources/js/Layouts/AdminLayout.vue`
- Test: `tests/Feature/Admin/OrderListTest.php`

**Interfaces:**
- Consumes: `FulfilmentStatus`, `PaymentMethod`, `ShippingZone`, `AdminTable.vue` (props `rows`, `columns`, `filters`; slots `filters` with `{ query }`, `cell-<key>` with `{ row }`, `empty`).
- Produces:
  - `Order::refundOwed()` scope (Task 6 uses it too).
  - Route `admin.orders.index` (`GET /admin/orders`) with query keys `search`, `status` (a fulfilment value or `refund_owed`), `payment_method`, `shipping_zone_id`, `from`, `to` (`Y-m-d`).
  - Route `admin.orders.show` (`GET /admin/orders/{order}`), registered here and implemented in Task 4.
  - Inertia page `Admin/Orders/Index` with props `orders` (paginator rows: `id, number, placed_at, customer, phone, zone, total, payment, status`), `filters`, `statuses` (`[{value, label}]`), `payment_methods` (`[{value, label}]`), `zones` (`[{id, name}]`).

- [ ] **Step 1: Write the failing tests**

`php artisan make:test --pest Admin/OrderListTest --no-interaction` creates `tests/Feature/Admin/OrderListTest.php`. Replace its contents:

```php
<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingZone;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function listedOrderIds(array $query = []): array
{
    $ids = [];

    test()->actingAs(test()->admin)
        ->get(route('admin.orders.index', $query))
        ->assertOk()
        ->assertInertia(function ($page) use (&$ids) {
            $page->component('Admin/Orders/Index');
            $ids = collect($page->toArray()['props']['orders']['data'])->pluck('id')->all();
        });

    return $ids;
}

test('non-admins cannot see orders', function () {
    $this->actingAs(User::factory()->create())->get('/admin/orders')->assertForbidden();
});

test('orders are listed newest first', function () {
    $older = Order::factory()->create(['placed_at' => now()->subDay()]);
    $newer = Order::factory()->create();

    expect(listedOrderIds())->toBe([$newer->id, $older->id]);
});

test('orders filter by fulfilment status', function () {
    $packed = Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Packed]);
    Order::factory()->create();

    expect(listedOrderIds(['status' => 'packed']))->toBe([$packed->id]);
});

test('orders filter by payment method and zone', function () {
    $zone = ShippingZone::factory()->create();
    $match = Order::factory()->razorpay()->create(['shipping_zone_id' => $zone->id]);
    Order::factory()->create(['shipping_zone_id' => $zone->id]);
    Order::factory()->razorpay()->create();

    expect(listedOrderIds(['payment_method' => 'razorpay', 'shipping_zone_id' => $zone->id]))->toBe([$match->id]);
});

test('orders filter by date range', function () {
    $inRange = Order::factory()->create(['placed_at' => '2026-10-10 12:00']);
    Order::factory()->create(['placed_at' => '2026-10-01 12:00']);

    expect(listedOrderIds(['from' => '2026-10-05', 'to' => '2026-10-10']))->toBe([$inRange->id]);
});

test('search finds an order by number or by phone', function () {
    $order = Order::factory()->create(['customer_phone' => '9876500001']);
    Order::factory()->create(['customer_phone' => '9876500002']);

    expect(listedOrderIds(['search' => $order->number]))->toBe([$order->id])
        ->and(listedOrderIds(['search' => '500001']))->toBe([$order->id]);
});

test('the refund owed filter finds cancelled orders still holding a payment', function () {
    $owed = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    Payment::factory()->paid()->for($owed)->create();
    $fine = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    Payment::factory()->paid()->for($fine)->create();

    expect(listedOrderIds(['status' => 'refund_owed']))->toBe([$owed->id]);
});

test('the refund owed filter finds a second capture on one order', function () {
    $order = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    Payment::factory()->paid()->for($order)->count(2)->create();

    expect(listedOrderIds(['status' => 'refund_owed']))->toBe([$order->id]);
});

test('an unknown status filter is rejected', function () {
    $this->actingAs($this->admin)->get('/admin/orders?status=lost')->assertSessionHasErrors('status');
});
```

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test --compact tests/Feature/Admin/OrderListTest.php`
Expected: FAIL, `Route [admin.orders.index] not defined`.

- [ ] **Step 3: The `refundOwed` scope**

In `app/Models/Order.php` add these imports: `use Illuminate\Database\Eloquent\Attributes\Scope;`, `use Illuminate\Database\Eloquent\Builder;`. Then add:

```php
    /**
     * Orders holding a captured payment that should go back: a cancelled order still paid, or a second capture on one order.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function refundOwed(Builder $query): void
    {
        $paid = fn (Builder $payment) => $payment->where('status', PaymentStatus::Paid);

        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('fulfilment_status', FulfilmentStatus::Cancelled)->whereHas('payments', $paid))
            ->orWhereHas('payments', $paid, '>', 1));
    }
```

- [ ] **Step 4: Controller and routes**

`app/Http/Controllers/Admin/OrderController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => null, 'status' => null, 'payment_method' => null, 'shipping_zone_id' => null, 'from' => null, 'to' => null,
            ...$request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', Rule::in([...array_column(FulfilmentStatus::cases(), 'value'), 'refund_owed'])],
                'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
                'shipping_zone_id' => ['nullable', 'integer'],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d'],
            ]),
        ];
        $filters['shipping_zone_id'] = $filters['shipping_zone_id'] ? (int) $filters['shipping_zone_id'] : null;

        return Inertia::render('Admin/Orders/Index', [
            'orders' => Order::query()
                ->with('shippingZone:id,name')
                ->when($filters['search'], fn (Builder $query, string $search) => preg_match('/^ARU-?(\d+)$/i', $search, $match)
                    ? $query->whereKey((int) $match[1])
                    : $query->where(fn (Builder $query) => $query->whereLike('customer_name', "%{$search}%")->orWhereLike('customer_phone', "%{$search}%")))
                ->when($filters['status'] === 'refund_owed', fn (Builder $query) => $query->refundOwed())
                ->when($filters['status'] && $filters['status'] !== 'refund_owed', fn (Builder $query) => $query->where('fulfilment_status', $filters['status']))
                ->when($filters['payment_method'], fn (Builder $query, string $method) => $query->where('payment_method', $method))
                ->when($filters['shipping_zone_id'], fn (Builder $query, $zoneId) => $query->where('shipping_zone_id', $zoneId))
                ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('placed_at', '>=', $from))
                ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('placed_at', '<=', $to))
                ->latest('placed_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Order $order): array => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'placed_at' => $order->placed_at->format('j M, g:i a'),
                    'customer' => $order->customer_name,
                    'phone' => $order->customer_phone,
                    'zone' => $order->shippingZone->name,
                    'total' => $order->total,
                    'payment' => "{$order->payment_method->label()} · {$order->payment_status->label()}",
                    'status' => $order->fulfilment_status->label(),
                ]),
            'filters' => $filters,
            'statuses' => [
                ...array_map(fn (FulfilmentStatus $status): array => ['value' => $status->value, 'label' => $status->label()], FulfilmentStatus::cases()),
                ['value' => 'refund_owed', 'label' => 'Refund owed'],
            ],
            'payment_methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
            'zones' => ShippingZone::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
```

In `routes/admin.php`, import `App\Http\Controllers\Admin\OrderController` and add, after the dashboard route:

```php
        Route::resource('orders', OrderController::class)->only(['index', 'show']);
```

Until Task 4 adds `show()`, the `show` route exists without a method. Nothing calls it yet, and the Index page links to it.

- [ ] **Step 5: Nav link**

In `resources/js/Layouts/AdminLayout.vue`, add as the second entry of `links`:

```js
    { label: 'Orders', route: 'admin.orders.index', match: 'admin.orders.*' },
```

- [ ] **Step 6: The list page**

`resources/js/Pages/Admin/Orders/Index.vue`:

```vue
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import { formatPaise } from '@/money';

defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    payment_methods: { type: Array, required: true },
    zones: { type: Array, required: true },
});

const columns = [
    { key: 'number', label: 'Order' },
    { key: 'placed_at', label: 'Placed' },
    { key: 'customer', label: 'Customer' },
    { key: 'zone', label: 'Zone' },
    { key: 'total', label: 'Total' },
    { key: 'payment', label: 'Payment' },
    { key: 'status', label: 'Status' },
];

const fieldClass = 'rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <Head title="Orders" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Orders</h1>
        </template>

        <p v-if="$page.props.errors.status" class="mb-4 rounded bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">
            {{ $page.props.errors.status }}
        </p>
        <AdminTable :rows="orders" :columns="columns" :filters="filters">
            <template #filters="{ query }">
                <select v-model="query.status" aria-label="Status" :class="fieldClass">
                    <option :value="null">All statuses</option>
                    <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
                <select v-model="query.payment_method" aria-label="Payment method" :class="fieldClass">
                    <option :value="null">All payments</option>
                    <option v-for="method in payment_methods" :key="method.value" :value="method.value">{{ method.label }}</option>
                </select>
                <select v-model="query.shipping_zone_id" aria-label="Zone" :class="fieldClass">
                    <option :value="null">All zones</option>
                    <option v-for="zone in zones" :key="zone.id" :value="zone.id">{{ zone.name }}</option>
                </select>
                <label class="text-sm text-gray-600">
                    From
                    <input v-model="query.from" type="date" :class="fieldClass" />
                </label>
                <label class="text-sm text-gray-600">
                    To
                    <input v-model="query.to" type="date" :class="fieldClass" />
                </label>
            </template>
            <template #cell-number="{ row }">
                <Link :href="route('admin.orders.show', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.number }}</Link>
            </template>
            <template #cell-customer="{ row }">
                {{ row.customer }}
                <span class="block text-xs text-gray-500">{{ row.phone }}</span>
            </template>
            <template #cell-total="{ row }">{{ formatPaise(row.total) }}</template>
            <template #empty>No orders match.</template>
        </AdminTable>
    </AdminLayout>
</template>
```

The search box's placeholder comes from `AdminTable` ("Search…"). The admin can search by order number, name or phone.

- [ ] **Step 7: Run the tests and build**

Run: `php artisan test --compact tests/Feature/Admin/OrderListTest.php`
Expected: PASS (9 tests).

Run: `source ~/.nvm/nvm.sh && nvm use && npm run build`
Expected: build succeeds.

- [ ] **Step 8: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Order.php app/Http/Controllers/Admin/OrderController.php routes/admin.php resources/js/Layouts/AdminLayout.vue resources/js/Pages/Admin/Orders/Index.vue tests/Feature/Admin/OrderListTest.php
git commit -m "feat: admin order list with status, payment, zone and date filters"
```

---

### Task 4: Order detail, status buttons, cancel and refund

**Files:**
- Create: `app/Http/Requests/Admin/UpdateOrderStatusRequest.php`, `resources/js/Pages/Admin/Orders/Show.vue`
- Modify: `app/Models/Order.php`, `app/Http/Controllers/Admin/OrderController.php`, `routes/admin.php`
- Test: `tests/Feature/Admin/OrderWorkflowTest.php`

**Interfaces:**
- Consumes:
  - `AdvanceOrder::handle(Order, FulfilmentStatus, ?string)` and `FulfilmentStatus::next()` / `isCancellable()` (Task 2).
  - `Order::events()` and `OrderEvent::user()` (Task 1).
  - `CancelOrder::handle(Order): Order`, which throws `ValidationException` key `order` once dispatched.
  - `RefundPayment::handle(Order, ?Payment): Payment`, which throws `ValidationException` key `payment`.
- Produces:
  - `Order::mainPayment(): ?Payment`, the oldest Paid or Refunded payment row with a `gateway_payment_id`.
  - `Order::isRefundable(Payment $payment): bool`.
  - Routes:
    - `admin.orders.show` (implemented);
    - `admin.orders.status.update` (`PUT /admin/orders/{order}/status`, fields `status`, `tracking_note`);
    - `admin.orders.cancel` (`POST /admin/orders/{order}/cancel`);
    - `admin.orders.payments.refund` (`POST /admin/orders/{order}/payments/{payment}/refund`, scoped binding).
  - Inertia page `Admin/Orders/Show` with props `order`, `payments`, `events`, `next_status` (`{value, label}|null`), `can_cancel`, `whatsapp_url`. Task 5 adds a "Print slip" link to it.

- [ ] **Step 1: Write the failing tests**

`php artisan make:test --pest Admin/OrderWorkflowTest --no-interaction`, then replace its contents:

```php
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
```

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test --compact tests/Feature/Admin/OrderWorkflowTest.php`
Expected: FAIL, `Route [admin.orders.status.update] not defined` (or `show` missing).

- [ ] **Step 3: Payment helpers on `Order`**

In `app/Models/Order.php` add:

```php
    /**
     * The captured payment the order was paid with: the oldest paid or refunded one. A later capture is a duplicate.
     */
    public function mainPayment(): ?Payment
    {
        return $this->payments()
            ->whereIn('status', [PaymentStatus::Paid, PaymentStatus::Refunded])
            ->whereNotNull('gateway_payment_id')
            ->first();
    }

    /**
     * An admin may refund a still-paid payment of a cancelled order, or a duplicate capture.
     * Never the payment of an order that is still being fulfilled.
     */
    public function isRefundable(Payment $payment): bool
    {
        return $payment->status === PaymentStatus::Paid
            && ($this->fulfilment_status === FulfilmentStatus::Cancelled || ! $payment->is($this->mainPayment()));
    }
```

- [ ] **Step 4: Form Request**

`php artisan make:request Admin/UpdateOrderStatusRequest --no-interaction`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\FulfilmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(FulfilmentStatus::class)],
            'tracking_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

- [ ] **Step 5: Controller methods**

Add these imports to `app/Http/Controllers/Admin/OrderController.php`: `App\Actions\AdvanceOrder`, `App\Actions\CancelOrder`, `App\Actions\RefundPayment`, `App\Enums\PaymentStatus`, `App\Http\Requests\Admin\UpdateOrderStatusRequest`, `App\Models\OrderEvent`, `App\Models\OrderItem`, `App\Models\Payment`, `App\Models\Setting`, `Illuminate\Http\RedirectResponse`, `Illuminate\Validation\ValidationException`. Then add the methods:

```php
    public function show(Order $order): Response
    {
        $order->load(['items', 'payments', 'events.user:id,name', 'shippingZone:id,name']);
        $nextStatus = $order->fulfilment_status->next();
        $canAdvance = $nextStatus && ($order->payment_method === PaymentMethod::Cod || $order->payment_status === PaymentStatus::Paid);
        $message = 'Hi '.$order->customer_name.', this is '.Setting::values()['store_name']." about your order {$order->number}.";

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'placed_at' => $order->placed_at->format('j M Y, g:i a'),
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'address' => [$order->ship_line1, $order->ship_locality, "{$order->ship_district} {$order->ship_pincode}"],
                'zone' => $order->shippingZone->name,
                'payment_method' => $order->payment_method->label(),
                'payment_status' => $order->payment_status->label(),
                'fulfilment_status' => $order->fulfilment_status->label(),
                'tracking_note' => $order->tracking_note,
                'subtotal' => $order->subtotal,
                'shipping' => $order->shipping,
                'total' => $order->total,
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'variant' => collect([$item->colour, $item->size])->filter()->implode(' / '),
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->lineTotal(),
                ]),
            ],
            'payments' => $order->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'gateway_payment_id' => $payment->gateway_payment_id,
                'gateway_order_id' => $payment->gateway_order_id,
                'amount' => $payment->amount,
                'status' => $payment->status->label(),
                'created_at' => $payment->created_at->format('j M Y, g:i a'),
                'refundable' => $order->isRefundable($payment),
            ]),
            'events' => $order->events->map(fn (OrderEvent $event): array => [
                'id' => $event->id,
                'at' => $event->created_at->format('j M Y, g:i a'),
                'fulfilment_status' => $event->fulfilment_status->label(),
                'payment_status' => $event->payment_status->label(),
                'by' => $event->user?->name,
            ]),
            'next_status' => $canAdvance ? ['value' => $nextStatus->value, 'label' => $nextStatus->label()] : null,
            'can_cancel' => $order->fulfilment_status->isCancellable(),
            'whatsapp_url' => "https://wa.me/91{$order->customer_phone}?text=".rawurlencode($message),
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, AdvanceOrder $advanceOrder): RedirectResponse
    {
        $status = $request->enum('status', FulfilmentStatus::class);
        $advanceOrder->handle($order, $status, $request->validated('tracking_note'));

        return back()->with('status', "Order marked {$status->label()}.");
    }

    /**
     * Cancel and put the stock back, then refund a paid online order.
     * If the refund fails the cancel still stands, and the admin retries from the payment row.
     */
    public function cancel(Order $order, CancelOrder $cancelOrder, RefundPayment $refundPayment): RedirectResponse
    {
        $order = $cancelOrder->handle($order);

        if ($order->payment_method === PaymentMethod::Razorpay && $order->payment_status === PaymentStatus::Paid) {
            $refundPayment->handle($order);

            return back()->with('status', 'Order cancelled and payment refunded.');
        }

        return back()->with('status', 'Order cancelled.');
    }

    public function refund(Order $order, Payment $payment, RefundPayment $refundPayment): RedirectResponse
    {
        if (! $order->isRefundable($payment)) {
            throw ValidationException::withMessages(['payment' => 'Only a cancelled order or a duplicate payment can be refunded.']);
        }

        $refundPayment->handle($order, $payment->is($order->mainPayment()) ? null : $payment);

        return back()->with('status', 'Payment refunded.');
    }
```

The main payment goes to `RefundPayment` as `null`, so the order itself becomes Refunded. A duplicate goes as the row, which leaves the order Paid.

In `routes/admin.php`, below the orders resource:

```php
        Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/payments/{payment}/refund', [OrderController::class, 'refund'])
            ->scopeBindings()
            ->name('orders.payments.refund');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact tests/Feature/Admin/OrderWorkflowTest.php`
Expected: PASS (11 tests).

- [ ] **Step 7: The detail page**

`resources/js/Pages/Admin/Orders/Show.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    order: { type: Object, required: true },
    payments: { type: Array, required: true },
    events: { type: Array, required: true },
    next_status: { type: Object, default: null },
    can_cancel: { type: Boolean, required: true },
    whatsapp_url: { type: String, required: true },
});

const trackingNote = ref(props.order.tracking_note ?? '');
const isBusy = ref(false);
const options = { preserveScroll: true, onStart: () => (isBusy.value = true), onFinish: () => (isBusy.value = false) };

const advance = () => {
    router.put(
        route('admin.orders.status.update', props.order.id),
        { status: props.next_status.value, tracking_note: props.next_status.value === 'dispatched' ? trackingNote.value || null : null },
        options,
    );
};

const cancel = () => {
    if (window.confirm(`Cancel ${props.order.number}? Stock goes back, and a paid online order is refunded.`)) {
        router.post(route('admin.orders.cancel', props.order.id), {}, options);
    }
};

const refund = (payment) => {
    if (window.confirm(`Refund ${formatPaise(payment.amount)} for ${payment.gateway_payment_id}?`)) {
        router.post(route('admin.orders.payments.refund', [props.order.id, payment.id]), {}, options);
    }
};
</script>

<template>
    <Head :title="order.number" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link :href="route('admin.orders.index')" class="text-sm text-gray-500 hover:underline">← Orders</Link>
                    <h1 class="text-xl font-semibold text-gray-900">{{ order.number }}</h1>
                    <p class="text-sm text-gray-600">
                        {{ order.placed_at }} · {{ order.fulfilment_status }} · {{ order.payment_method }} ({{ order.payment_status }})
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a :href="whatsapp_url" target="_blank" rel="noopener" class="rounded border bg-white px-3 py-2 text-sm font-medium text-gray-700">Message customer</a>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <p
                v-for="key in ['status', 'order', 'payment']"
                v-show="$page.props.errors[key]"
                :key="key"
                class="rounded bg-red-50 px-4 py-2 text-sm text-red-800"
                role="alert"
            >
                {{ $page.props.errors[key] }}
            </p>

            <section v-if="next_status || can_cancel" class="flex flex-wrap items-end gap-3 rounded bg-white p-4 shadow-sm">
                <label v-if="next_status?.value === 'dispatched'" class="block text-sm text-gray-700">
                    Tracking note (optional)
                    <input
                        v-model="trackingNote"
                        type="text"
                        maxlength="500"
                        placeholder="Bus parcel, receipt no."
                        class="mt-1 block w-72 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </label>
                <button
                    v-if="next_status"
                    type="button"
                    :disabled="isBusy"
                    class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                    @click="advance"
                >
                    Mark {{ next_status.label }}
                </button>
                <button v-if="can_cancel" type="button" :disabled="isBusy" class="rounded px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50" @click="cancel">
                    Cancel order
                </button>
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="rounded bg-white shadow-sm lg:col-span-2">
                    <h2 class="border-b px-4 py-3 font-medium text-gray-900">Items</h2>
                    <table class="w-full text-left text-sm">
                        <tbody>
                            <tr v-for="item in order.items" :key="item.id" class="border-b">
                                <td class="px-4 py-3">
                                    {{ item.name }}
                                    <span v-if="item.variant" class="block text-xs text-gray-500">{{ item.variant }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ item.quantity }} × {{ formatPaise(item.unit_price) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatPaise(item.line_total) }}</td>
                            </tr>
                            <tr class="border-b text-gray-600">
                                <td class="px-4 py-2" colspan="2">Subtotal</td>
                                <td class="px-4 py-2 text-right">{{ formatPaise(order.subtotal) }}</td>
                            </tr>
                            <tr class="border-b text-gray-600">
                                <td class="px-4 py-2" colspan="2">Shipping ({{ order.zone }})</td>
                                <td class="px-4 py-2 text-right">{{ order.shipping ? formatPaise(order.shipping) : 'Free' }}</td>
                            </tr>
                            <tr class="font-medium">
                                <td class="px-4 py-3" colspan="2">Total</td>
                                <td class="px-4 py-3 text-right">{{ formatPaise(order.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="space-y-1 rounded bg-white p-4 text-sm shadow-sm">
                    <h2 class="mb-2 font-medium text-gray-900">Customer</h2>
                    <p>{{ order.customer_name }}</p>
                    <p><a :href="`tel:${order.customer_phone}`" class="hover:underline">{{ order.customer_phone }}</a></p>
                    <p v-if="order.customer_email">{{ order.customer_email }}</p>
                    <p v-for="line in order.address" :key="line" class="text-gray-600">{{ line }}</p>
                    <p v-if="order.tracking_note" class="pt-2 text-gray-600"><span class="font-medium text-gray-900">Tracking:</span> {{ order.tracking_note }}</p>
                </section>
            </div>

            <section v-if="payments.length" class="overflow-x-auto rounded bg-white shadow-sm">
                <h2 class="border-b px-4 py-3 font-medium text-gray-900">Payment attempts</h2>
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">When</th>
                            <th class="px-4 py-2 font-medium">Razorpay ids</th>
                            <th class="px-4 py-2 font-medium">Amount</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payment in payments" :key="payment.id" class="border-b last:border-0">
                            <td class="px-4 py-2">{{ payment.created_at }}</td>
                            <td class="px-4 py-2 font-mono text-xs">
                                {{ payment.gateway_payment_id ?? '—' }}
                                <span class="block text-gray-500">{{ payment.gateway_order_id }}</span>
                            </td>
                            <td class="px-4 py-2">{{ formatPaise(payment.amount) }}</td>
                            <td class="px-4 py-2">{{ payment.status }}</td>
                            <td class="px-4 py-2 text-right">
                                <button v-if="payment.refundable" type="button" :disabled="isBusy" class="text-red-600 hover:underline disabled:opacity-50" @click="refund(payment)">
                                    Refund
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded bg-white p-4 shadow-sm">
                <h2 class="mb-3 font-medium text-gray-900">Timeline</h2>
                <ol class="space-y-2 text-sm">
                    <li v-for="event in events" :key="event.id" class="flex flex-wrap gap-x-3">
                        <span class="text-gray-500">{{ event.at }}</span>
                        <span>{{ event.fulfilment_status }} · {{ event.payment_status }}</span>
                        <span v-if="event.by" class="text-gray-500">by {{ event.by }}</span>
                    </li>
                </ol>
            </section>
        </div>
    </AdminLayout>
</template>
```

- [ ] **Step 8: Build and run the order tests**

Run: `source ~/.nvm/nvm.sh && nvm use && npm run build`
Expected: build succeeds.

Run: `php artisan test --compact tests/Feature/Admin/OrderWorkflowTest.php tests/Feature/Admin/OrderListTest.php`
Expected: PASS.

- [ ] **Step 9: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Order.php app/Http/Controllers/Admin/OrderController.php app/Http/Requests/Admin/UpdateOrderStatusRequest.php routes/admin.php resources/js/Pages/Admin/Orders/Show.vue tests/Feature/Admin/OrderWorkflowTest.php
git commit -m "feat: admin order detail with status steps, cancel, refund and WhatsApp link"
```

---

### Task 5: Printable packing slip / receipt

**Files:**
- Create: `resources/views/admin/orders/slip.blade.php`
- Modify: `app/Http/Controllers/Admin/OrderController.php`, `routes/admin.php`, `resources/js/Pages/Admin/Orders/Show.vue`
- Test: `tests/Feature/Admin/PackingSlipTest.php`

**Interfaces:**
- Consumes: `Order` with `items`, `OrderItem::lineTotal()`, `Setting::values()` (`store_name`, `contact_phone`), the `<x-paise>` component.
- Produces: route `admin.orders.slip` (`GET /admin/orders/{order}/slip`), which returns plain HTML.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Admin/PackingSlipTest --no-interaction`:

```php
<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

test('the packing slip lists the order snapshot for printing', function () {
    $order = Order::factory()->create(['customer_name' => 'Mami', 'total' => 104900]);
    OrderItem::factory()->for($order)->create(['product_name' => 'Wolf Tee', 'size' => 'M', 'colour' => 'Black']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.orders.slip', $order))
        ->assertOk()
        ->assertSee($order->number)
        ->assertSee('Mami')
        ->assertSee('Wolf Tee')
        ->assertSee('Black / M')
        ->assertSee('₹1,049')
        ->assertSee('Cash on delivery');
});

test('non-admins cannot open a packing slip', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('admin.orders.slip', $order))->assertForbidden();
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Admin/PackingSlipTest.php`
Expected: FAIL, `Route [admin.orders.slip] not defined`.

- [ ] **Step 3: Route and controller method**

In `routes/admin.php`, below the cancel route:

```php
        Route::get('orders/{order}/slip', [OrderController::class, 'slip'])->name('orders.slip');
```

In `OrderController` import `Illuminate\Contracts\View\View` and add:

```php
    /**
     * Packing slip and receipt in one plain page, printed from the browser.
     */
    public function slip(Order $order): View
    {
        return view('admin.orders.slip', ['order' => $order->load('items'), 'settings' => Setting::values()]);
    }
```

- [ ] **Step 4: The view**

`resources/views/admin/orders/slip.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->number }} · {{ $settings['store_name'] }}</title>
    <style>
        body { font-family: system-ui, sans-serif; color: #111; max-width: 720px; margin: 24px auto; padding: 0 16px; font-size: 14px; }
        h1 { font-size: 20px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #ddd; }
        .right { text-align: right; }
        .muted { color: #555; }
        .columns { display: flex; justify-content: space-between; gap: 24px; margin-top: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button type="button" class="no-print" onclick="window.print()">Print</button>

    <div class="columns">
        <div>
            <h1>{{ $settings['store_name'] }}</h1>
            @if ($settings['contact_phone'])
                <div class="muted">{{ $settings['contact_phone'] }}</div>
            @endif
        </div>
        <div class="right">
            <h1>{{ $order->number }}</h1>
            <div class="muted">{{ $order->placed_at->format('j M Y, g:i a') }}</div>
        </div>
    </div>

    <div class="columns">
        <div>
            <strong>Deliver to</strong><br>
            {{ $order->customer_name }} · {{ $order->customer_phone }}<br>
            {{ $order->ship_line1 }}<br>
            {{ $order->ship_locality }}<br>
            {{ $order->ship_district }} {{ $order->ship_pincode }}
        </div>
        <div class="right">
            <strong>Payment</strong><br>
            {{ $order->payment_method->label() }}<br>
            {{ $order->payment_status->label() }}
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Total</th></tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}
                        @if ($item->colour || $item->size)
                            <span class="muted">({{ collect([$item->colour, $item->size])->filter()->implode(' / ') }})</span>
                        @endif
                    </td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right"><x-paise :amount="$item->unit_price" /></td>
                    <td class="right"><x-paise :amount="$item->lineTotal()" /></td>
                </tr>
            @endforeach
            <tr><td colspan="3">Subtotal</td><td class="right"><x-paise :amount="$order->subtotal" /></td></tr>
            <tr><td colspan="3">Shipping</td><td class="right">@if ($order->shipping) <x-paise :amount="$order->shipping" /> @else Free @endif</td></tr>
            <tr><td colspan="3"><strong>Total</strong></td><td class="right"><strong><x-paise :amount="$order->total" /></strong></td></tr>
        </tbody>
    </table>

    <p class="muted">Thank you for shopping with {{ $settings['store_name'] }}.</p>
</body>
</html>
```

- [ ] **Step 5: Link it from the detail page**

In `resources/js/Pages/Admin/Orders/Show.vue`, inside the header's `<div class="flex flex-wrap gap-2">`, before "Message customer":

```vue
                    <a :href="route('admin.orders.slip', order.id)" target="_blank" class="rounded border bg-white px-3 py-2 text-sm font-medium text-gray-700">Print slip</a>
```

- [ ] **Step 6: Run the tests and build**

Run: `php artisan test --compact tests/Feature/Admin/PackingSlipTest.php`
Expected: PASS (2 tests).

Run: `source ~/.nvm/nvm.sh && nvm use && npm run build`
Expected: build succeeds.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/admin.php app/Http/Controllers/Admin/OrderController.php resources/views/admin/orders/slip.blade.php resources/js/Pages/Admin/Orders/Show.vue tests/Feature/Admin/PackingSlipTest.php
git commit -m "feat: printable packing slip and receipt"
```

---

### Task 6: Dashboard

**Files:**
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `routes/admin.php`, `resources/js/Pages/Admin/Dashboard.vue`
- Test: `tests/Feature/Admin/DashboardTest.php`

**Interfaces:**
- Consumes: the `Order::refundOwed()` scope (Task 3), the route `admin.orders.index` with `status` (Task 3), the route `admin.orders.show` (Task 4) and the route `admin.products.edit`.
- Produces: the Inertia page `Admin/Dashboard`. Its props:
  - `today`: `{count, orders: [{id, number, customer, total, status}]}`;
  - `needs_action`: `[{label, status, count}]`;
  - `low_stock`: `[{id, product_id, name, variant, sku, stock}]`;
  - `revenue`: `{last_7_days, last_30_days}` in paise.
  - The existing `AdminAccessTest` still passes, because the component name is unchanged.

- [ ] **Step 1: Write the failing tests**

`php artisan make:test --pest Admin/DashboardTest --no-interaction`:

```php
<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the dashboard shows today\'s orders', function () {
    $today = Order::factory()->create();
    Order::factory()->create(['placed_at' => now()->subDays(2)]);

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('today.count', 1)
            ->where('today.orders.0.id', $today->id));
});

test('the dashboard counts orders that need action', function () {
    Order::factory()->count(2)->create();
    Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Packed]);
    Order::factory()->create(['fulfilment_status' => FulfilmentStatus::Delivered]);
    $owed = Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    Payment::factory()->paid()->for($owed)->create();

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page->where('needs_action', [
            ['label' => 'To pack', 'status' => 'confirmed', 'count' => 2],
            ['label' => 'To dispatch', 'status' => 'packed', 'count' => 1],
            ['label' => 'To deliver', 'status' => 'dispatched', 'count' => 0],
            ['label' => 'Refund owed', 'status' => 'refund_owed', 'count' => 1],
        ]));
});

test('the dashboard lists active variants with three or fewer in stock, lowest first', function () {
    $low = ProductVariant::factory()->create(['stock' => 1]);
    $edge = ProductVariant::factory()->create(['stock' => 3]);
    ProductVariant::factory()->create(['stock' => 4]);
    ProductVariant::factory()->create(['stock' => 0, 'is_active' => false]);

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->has('low_stock', 2)
            ->where('low_stock.0.id', $low->id)
            ->where('low_stock.1.id', $edge->id));
});

test('revenue counts paid orders placed in the last 7 and 30 days', function () {
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 10000]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 20000, 'placed_at' => now()->subDays(6)]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 40000, 'placed_at' => now()->subDays(20)]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 80000, 'placed_at' => now()->subDays(40)]);
    Order::factory()->create(['payment_status' => PaymentStatus::Refunded, 'total' => 160000]);
    Order::factory()->create(['total' => 320000]);

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->where('revenue.last_7_days', 30000)
            ->where('revenue.last_30_days', 70000));
});
```

The last `Order::factory()->create(['total' => 320000])` is an undelivered COD order (payment Pending), so it does not count.

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test --compact tests/Feature/Admin/DashboardTest.php`
Expected: FAIL, missing prop `today`.

- [ ] **Step 3: Controller and route**

`app/Http/Controllers/Admin/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // shortcut: loads all of today's orders to count and list them; fine at tens per day, paginate if that grows to hundreds.
        $todayOrders = Order::whereDate('placed_at', today())->latest('placed_at')->latest('id')->get();

        return Inertia::render('Admin/Dashboard', [
            'today' => [
                'count' => $todayOrders->count(),
                'orders' => $todayOrders->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'customer' => $order->customer_name,
                    'total' => $order->total,
                    'status' => $order->fulfilment_status->label(),
                ]),
            ],
            'needs_action' => [
                ['label' => 'To pack', 'status' => FulfilmentStatus::Confirmed->value, 'count' => Order::where('fulfilment_status', FulfilmentStatus::Confirmed)->count()],
                ['label' => 'To dispatch', 'status' => FulfilmentStatus::Packed->value, 'count' => Order::where('fulfilment_status', FulfilmentStatus::Packed)->count()],
                ['label' => 'To deliver', 'status' => FulfilmentStatus::Dispatched->value, 'count' => Order::where('fulfilment_status', FulfilmentStatus::Dispatched)->count()],
                ['label' => 'Refund owed', 'status' => 'refund_owed', 'count' => Order::refundOwed()->count()],
            ],
            'low_stock' => ProductVariant::with('product:id,name')
                ->where('is_active', true)
                ->where('stock', '<=', 3)
                ->orderBy('stock')
                ->orderBy('id')
                ->limit(20)
                ->get()
                ->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'name' => $variant->product->name,
                    'variant' => collect([$variant->colour, $variant->size])->filter()->implode(' / '),
                    'sku' => $variant->sku,
                    'stock' => $variant->stock,
                ]),
            'revenue' => [
                'last_7_days' => $this->revenueSince(6),
                'last_30_days' => $this->revenueSince(29),
            ],
        ]);
    }

    /**
     * Money received: paid orders placed from $days days before today up to now.
     */
    private function revenueSince(int $days): int
    {
        return (int) Order::where('payment_status', PaymentStatus::Paid)
            ->where('placed_at', '>=', today()->subDays($days))
            ->sum('total');
    }
}
```

In `routes/admin.php`, import `App\Http\Controllers\Admin\DashboardController` and replace

```php
        Route::inertia('/', 'Admin/Dashboard')->name('dashboard');
```

with

```php
        Route::get('/', DashboardController::class)->name('dashboard');
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact tests/Feature/Admin/DashboardTest.php tests/Feature/Admin/AdminAccessTest.php`
Expected: PASS.

- [ ] **Step 5: The page**

Replace `resources/js/Pages/Admin/Dashboard.vue`:

```vue
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatPaise } from '@/money';

defineProps({
    today: { type: Object, required: true },
    needs_action: { type: Array, required: true },
    low_stock: { type: Array, required: true },
    revenue: { type: Object, required: true },
});
</script>

<template>
    <Head title="Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
        </template>

        <div class="space-y-6">
            <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Link
                    v-for="item in needs_action"
                    :key="item.status"
                    :href="route('admin.orders.index', { status: item.status })"
                    class="rounded bg-white p-4 shadow-sm hover:bg-gray-50"
                >
                    <span class="block text-sm text-gray-500">{{ item.label }}</span>
                    <span class="text-2xl font-semibold" :class="item.status === 'refund_owed' && item.count ? 'text-red-700' : 'text-gray-900'">{{ item.count }}</span>
                </Link>
            </section>

            <section class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <div class="rounded bg-white p-4 shadow-sm">
                    <span class="block text-sm text-gray-500">Orders today</span>
                    <span class="text-2xl font-semibold text-gray-900">{{ today.count }}</span>
                </div>
                <div class="rounded bg-white p-4 shadow-sm">
                    <span class="block text-sm text-gray-500">Received, last 7 days</span>
                    <span class="text-2xl font-semibold text-gray-900">{{ formatPaise(revenue.last_7_days) }}</span>
                </div>
                <div class="rounded bg-white p-4 shadow-sm">
                    <span class="block text-sm text-gray-500">Received, last 30 days</span>
                    <span class="text-2xl font-semibold text-gray-900">{{ formatPaise(revenue.last_30_days) }}</span>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="overflow-x-auto rounded bg-white shadow-sm">
                    <h2 class="border-b px-4 py-3 font-medium text-gray-900">Today's orders</h2>
                    <table class="w-full text-left text-sm">
                        <tbody>
                            <tr v-for="order in today.orders" :key="order.id" class="border-b last:border-0">
                                <td class="px-4 py-2">
                                    <Link :href="route('admin.orders.show', order.id)" class="font-medium hover:underline">{{ order.number }}</Link>
                                </td>
                                <td class="px-4 py-2">{{ order.customer }}</td>
                                <td class="px-4 py-2">{{ formatPaise(order.total) }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ order.status }}</td>
                            </tr>
                            <tr v-if="today.orders.length === 0">
                                <td class="px-4 py-6 text-center text-gray-500">No orders yet today.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="overflow-x-auto rounded bg-white shadow-sm">
                    <h2 class="border-b px-4 py-3 font-medium text-gray-900">Low stock (3 or fewer)</h2>
                    <table class="w-full text-left text-sm">
                        <tbody>
                            <tr v-for="variant in low_stock" :key="variant.id" class="border-b last:border-0">
                                <td class="px-4 py-2">
                                    <Link :href="route('admin.products.edit', variant.product_id)" class="font-medium hover:underline">{{ variant.name }}</Link>
                                    <span v-if="variant.variant" class="block text-xs text-gray-500">{{ variant.variant }}</span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">{{ variant.sku }}</td>
                                <td class="px-4 py-2 font-medium" :class="variant.stock === 0 ? 'text-red-700' : 'text-gray-900'">{{ variant.stock }}</td>
                            </tr>
                            <tr v-if="low_stock.length === 0">
                                <td class="px-4 py-6 text-center text-gray-500">Everything is well stocked.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
```

- [ ] **Step 6: Build**

Run: `source ~/.nvm/nvm.sh && nvm use && npm run build`
Expected: build succeeds.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/DashboardController.php routes/admin.php resources/js/Pages/Admin/Dashboard.vue tests/Feature/Admin/DashboardTest.php
git commit -m "feat: admin dashboard with needs-action counts, low stock and revenue"
```

---

### Task 7: Browser check

No new code unless the check finds a bug. Any fix gets a test and its own commit.

**Files:** none planned.

**Interfaces:**
- Consumes: every route and page from Tasks 3–6.
- Produces: a short report of what was checked. Screenshots are optional.

- [ ] **Step 1: Fresh data**

Run: `php artisan migrate:fresh --seed --no-interaction`, then `composer run dev` in the background, or `php artisan serve` plus `npm run dev`. Use the laravel-boost `get-absolute-url` tool for the base URL.

- [ ] **Step 2: Make orders through the storefront**

Use the Playwright MCP at 375 px width:
- Place two COD orders as a guest, one of them with an email.
- If Razorpay test keys are set, also place and pay one online order (netbanking test). If they aren't set, say so in the report.

- [ ] **Step 3: Walk the admin**

Log in as `test@example.com` / `password` and check:
1. The dashboard shows today's orders, "To pack" = 2 (3 with the online order), revenue ₹0 so far, and the seeded low-stock variants if there are any.
2. The orders list filters by status, payment method, zone and date. Search by `ARU-00000N` and by phone works.
3. On the first COD order:
   - Mark Packed, then Mark Dispatched with a tracking note.
   - The customer's signed order page shows the note.
   - The `jobs` table has an `OrderDispatched` job: use laravel-boost `database-query` on `jobs`.
4. Mark Delivered. The payment shows Paid, the timeline shows four rows with "by Test User", and dashboard revenue now includes the order.
5. "Print slip" opens a clean page with the items and totals, and the browser print preview has no admin chrome.
6. "Message customer" opens `https://wa.me/91…` with the prefilled text. Check the href; don't send anything.
7. Cancel the second COD order. Stock goes back (see the product's variant grid), and "Cancel order" is gone.
8. Cancel the paid online order if one exists. The flash says "refunded", and the payment row shows Refunded.
9. At 375 px the admin pages are usable. Wide tables scroll inside their own box.

Check `browser-logs` for errors after each page.

- [ ] **Step 4: Full suite**

Run: `php artisan test --compact`
Expected: all green. Report the count.

---

## Phase 5 done when

- `php artisan test --compact` is green.
- Every status change shows on the order's timeline with the time and, for admin actions, the admin's name.
- The admin can move an order only one step forward. An unpaid online order can't be moved, and a COD order becomes Paid on delivery.
- Dispatching and delivering queue the customer email when the order has one.
- Cancelling restores stock and refunds a paid online order. A failed refund leaves the order under "Refund owed" with a working Refund button.
- The order list filters by status, payment method, zone and date, and searches by number, name and phone.
- The packing slip prints cleanly. "Message customer" opens WhatsApp with the customer's number.
- The dashboard shows today's orders, the four needs-action counts, low stock (≤ 3) and revenue for 7 and 30 days.

## Known follow-ups (not Phase 5)

Carried over from Phase 4:
- **Razorpay MCP** for read-only lookups of test payments (spec section 10): vet the repo, then add it to `.mcp.json`.
- **Refund webhooks** (`refund.processed`, `refund.failed`) are not handled. The refund API response is stored as is.

Phase 4 Minor findings not folded in:
- **T1:**
  - `.env.example` has no blank line before the `RAZORPAY_` keys.
  - The `isConfigured` test covers only a missing secret, not a missing key id.
- **T2:**
  - `PlaceOrderTest` calls `$order->payments()->sole()` three times.
  - `PlaceOrder` reads `$gatewayOrder['id']` unchecked. A missing id gives an undefined-key error, but it still rolls back.
- **T4:**
  - When a payment arrives late on a cancelled order, the shopper gets `payment_confirmed=false` and no `payment_error`, so they see no explanation.
  - A `RazorpayException` from `MarkOrderPaid`'s auto-refund gives a 500 on the browser request. The Paid row is committed, and the retry design copes.
  - The "first payment row" lookup is duplicated in `OrderController`, `MarkOrderPaid` and `OrderPaymentController`. Its meaning, the row created when the order was placed, differs from the new `Order::mainPayment()`, so they were not merged.
- **T5:**
  - There is no webhook test for a late capture on a cancelled order, nor for the 500-on-refund-failure retry.
  - `payment.failed` on an already paid or cancelled order still writes a Failed row (harmless).
  - The webhook ignore-cases test doesn't assert `Payment::count()`.
- **T6:**
  - One `CancelOrder` exception aborts the rest of the sweep run (no per-order try/catch).
  - The sweep uses `->get()`, which is fine at this volume.
  - The global `unpaidOrder()` helper in `AbandonedOrderTest` could collide with Pest helpers of the same name.
- **T7:**
  - A `payment.failed` message isn't cleared on a later attempt inside the same modal.
  - There are no frontend tests for the handler/flash flow.
  - Razorpay asks for a mobile number on the first order despite the prefill.

New in Phase 5:
- The timeline records status changes only. It does not record tracking-note edits or refund attempts that failed; those are in the logs via `report()`.
- No backfill of `order_events` for orders placed before this phase. The store is not live, so none are expected.
- Test helpers `listedOrderIds()`, `orderWithItem()` and `paidOnlineOrder()` are global Pest functions, the same pattern as `zonePayload()`. Rename them if a collision appears.
