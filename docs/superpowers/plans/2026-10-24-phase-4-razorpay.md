# Phase 4: Razorpay Online Payment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Shoppers can pay online with Razorpay (UPI, cards, netbanking). Payments are confirmed by signature and by webhook. Orders left unpaid for 30 minutes are cancelled and their stock comes back. Paid orders can be refunded through the API.

**Architecture:**
- `App\Services\Razorpay` is a thin wrapper over Laravel's `Http` client. It creates orders, issues refunds and checks signatures; there is no SDK.
- `PlaceOrder` gains a Razorpay branch. Inside the same transaction it reserves stock, creates the Razorpay order and stores a `payments` row. Any API failure rolls everything back.
- The pay step lives on the signed order page. It loads Razorpay Checkout on demand, and the success handler posts the signature back to a signed endpoint.
- `MarkOrderPaid` is the single place an order becomes paid. Both the browser handler and the `/webhooks/razorpay` endpoint call it. It is idempotent, and it refunds automatically if the payment lands after the order was cancelled.
- A scheduled `orders:cancel-abandoned` command uses `CancelOrder` to sweep unpaid orders. `RefundPayment` reverses captured payments; Phase 5's admin cancel button will reuse both.

**Tech Stack:** Laravel 13.38, PHP 8.4, Inertia v2 + Vue 3, Tailwind 3, Pest on PostgreSQL (`arusa_app_testing`). Razorpay REST API v1 is called with `Http`. Razorpay Checkout (`https://checkout.razorpay.com/v1/checkout.js`) is loaded at runtime, not from npm.

**Spec:** `docs/superpowers/specs/2026-10-23-arusa-ecommerce-design.md`, sections 2, 3 (`payments`), 4.4–4.7 and 9.

## Global Constraints

- No new PHP/JS dependencies without approval. **No Razorpay SDK:** call the API with Laravel's `Http` client. Load Checkout from `https://checkout.razorpay.com/v1/checkout.js` at runtime, never from npm.
- All money is stored as **integer paise**. The Razorpay `amount` is paise too: send `$order->total` unchanged.
- **Prices are always recomputed server-side.** The client never sends a price or a total.
- Stock changes run in a DB transaction with `lockForUpdate()` on variant rows, locked in ascending `id` order. Lock an order row before its variants.
- Razorpay webhooks are **signature-verified** (`X-Razorpay-Signature`, HMAC-SHA256 of the raw body with the webhook secret), **idempotent**, and the **source of truth**.
- Browser payment confirmation verifies `HMAC-SHA256("{razorpay_order_id}|{razorpay_payment_id}", key_secret)` before marking anything paid.
- Razorpay API failure while placing an order rolls back the whole transaction.
- Orders with payment status `AwaitingPayment` that are older than 30 minutes are cancelled every 5 minutes: payment → `Failed`, fulfilment → `Cancelled`, stock restored.
- Keys come from `.env` only: `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`. Read them through `config('services.razorpay.*')`.
- Tests never reach Razorpay. Use `Http::fake()`; `tests/TestCase.php` calls `Http::preventStrayRequests()` (Task 1).
- Emails are queued (`ShouldQueue`), never sent inline.
- Design skills apply to **storefront pages only** (Task 7): ui-ux-pro-max is primary; design-motion-principles for motion; impeccable only for audit/polish.
- Motion: CSS transitions or Vue `<Transition>` only, 150–300 ms, respecting `prefers-reduced-motion`. Mobile-first.
- PHP: curly braces always, explicit return types, constructor promotion, PHPDoc over inline comments, TitleCase enum keys.
- After PHP changes run `vendor/bin/pint --dirty --format agent`. Run the narrowest tests with `php artisan test --compact <path>`.
- Tests that need a guest cart seed the session with `->withSession(['cart' => [$variantId => $quantity]])`.
- Pest datasets: when a test takes a typed model param, use a single `fn () => Model::factory()->create()`. Never `fn () => fn () =>`.
- Activate project skills while working: `arusa-commerce` (always), `laravel-best-practices` (PHP), `inertia-vue-development` (Vue pages), `tailwindcss-development` (styling), `testing-best-practices` (tests). Use the laravel-boost `search-docs` tool for `Http` client faking and scheduling syntax.
- Node: run `source ~/.nvm/nvm.sh && nvm use` before any npm command.

## Decisions made in this plan

- **One Razorpay order per shop order, created when the order is placed.**
  - Retries happen inside Razorpay Checkout against the same Razorpay order; Razorpay accepts repeated attempts until one succeeds.
  - `payments` keeps one row per attempt. The row created at placement is upgraded to `Paid` on capture. Each failed attempt reported by the webhook gets its own `Failed` row, unique on `gateway_payment_id`.
  - `payments.payload` is a JSON object keyed by stage: `order` (the Razorpay order), `payment` (the payment entity or browser response), `refund`.
- **The pay step is on the signed order page.** Checkout places the order, reserving the stock and clearing the bag, then redirects to `/orders/{id}?signature=…`, where Checkout opens automatically. Coming back to the same link within 30 minutes is how a shopper retries.
- **`payment.failed` does not fail the order.** The shopper may try again. Only the 30-minute sweep moves an order to `Failed`/`Cancelled`.
- **Online orders send "order placed" emails when paid**, not when placed. Unpaid orders would only be noise for the admin.
- **Late capture:** if a payment is captured after the sweep has cancelled the order, `MarkOrderPaid` records it and refunds it in full straight away. The order stays cancelled.
- **Refunds are built now as an action with tests (`RefundPayment`), but there is no admin button yet.** Admin order screens, cancel and refund buttons are Phase 5, which calls `CancelOrder` + `RefundPayment`.
- **`CancelOrder` does not refund.** It restores stock and fails an unpaid payment. Refunding a paid order is a separate call, so a Razorpay outage never blocks a cancel.
- **Online payment is offered only when Razorpay keys are configured** (`Razorpay::isConfigured()`). It is not limited by `cod_max` and has no store setting.
- **The Razorpay API call happens inside the `PlaceOrder` transaction,** as the spec requires for rollback. It holds the variant locks for at most the 10-second HTTP timeout, which is acceptable at this shop's volume.
- **Razorpay MCP (spec section 10) is deferred.** It needs its repo vetted first and is not needed to ship payments. It is listed under follow-ups.

## File Map

| File | Responsibility |
|---|---|
| `config/services.php`, `.env.example`, `phpunit.xml`, `tests/TestCase.php` | Razorpay config, fake test keys, no stray HTTP |
| `app/Services/Razorpay.php` | Create order, refund, signature checks |
| `database/migrations/*_create_payments_table.php`, `app/Models/Payment.php`, `database/factories/PaymentFactory.php` | Payment attempts |
| `app/Models/Order.php`, `database/factories/OrderFactory.php` | `payments()` relation, `razorpay()` state |
| `app/Actions/PlaceOrder.php`, `app/Actions/SendOrderPlacedEmails.php` | Razorpay branch, shared email dispatch |
| `app/Http/Requests/CheckoutRequest.php`, `app/Http/Controllers/CheckoutController.php` | Accept `razorpay`, `online_payment` prop |
| `app/Actions/CancelOrder.php`, `app/Actions/RefundPayment.php` | Reversals |
| `app/Actions/MarkOrderPaid.php`, `app/Http/Controllers/OrderPaymentController.php`, `app/Http/Controllers/OrderController.php` | Confirm payments, order page payment props |
| `resources/views/mail/orders/placed.blade.php` | Receipt copy for online payments |
| `app/Http/Controllers/RazorpayWebhookController.php`, `bootstrap/app.php` | Webhook, CSRF exemption |
| `routes/console.php` | `orders:cancel-abandoned` + schedule |
| `routes/web.php` | New routes |
| `resources/js/Pages/Checkout/Create.vue`, `resources/js/Pages/Orders/Show.vue` | Payment choice, pay panel |

---

### Task 1: Razorpay client and the payments table

**Files:**
- Create: `app/Services/Razorpay.php`, `app/Models/Payment.php`, `database/migrations/*_create_payments_table.php`, `database/factories/PaymentFactory.php`
- Modify: `config/services.php`, `.env.example`, `phpunit.xml`, `tests/TestCase.php`, `app/Models/Order.php`, `database/factories/OrderFactory.php`
- Test: `tests/Feature/RazorpayTest.php`

**Interfaces:**
- Consumes: `Order`, `PaymentStatus` (Phase 3).
- Produces:
  - `Razorpay` methods:
    - `isConfigured(): bool`
    - `keyId(): string`
    - `createOrder(int $amount, string $receipt): array`: returns the Razorpay order (`id`, `amount`, `status`, …) and throws `RequestException|ConnectionException`.
    - `refund(string $paymentId, int $amount): array`: returns the Razorpay refund (`id`, `status`, …) and throws `RequestException|ConnectionException`.
    - `isValidPaymentSignature(string $gatewayOrderId, string $paymentId, string $signature): bool`
    - `isValidWebhookSignature(string $body, string $signature): bool`
  - `Payment` model with fillable `order_id, gateway, gateway_order_id, gateway_payment_id, amount, status, payload`.
    - Casts: `amount` int, `status` `PaymentStatus`, `payload` array.
    - Relation: `order()`.
  - `Order::payments(): HasMany` (ordered by id).
  - Factory states:
    - `OrderFactory::razorpay()`: method Razorpay, payment `AwaitingPayment`, fulfilment `Pending`.
    - `PaymentFactory` default: an awaiting payment, amount 104900, matching `OrderFactory`'s total.
    - `PaymentFactory::paid()`.
  - Test keys from `phpunit.xml`: `rzp_test_fake`, `test_secret`, `test_webhook_secret`.

- [ ] **Step 1: Config, test keys and no stray HTTP**

`config/services.php`, add before the closing `];`:
```php
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
```

`.env.example`, append:
```
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
```

`phpunit.xml`, inside `<php>` after the `MAIL_MAILER` line:
```xml
        <env name="RAZORPAY_KEY_ID" value="rzp_test_fake"/>
        <env name="RAZORPAY_KEY_SECRET" value="test_secret"/>
        <env name="RAZORPAY_WEBHOOK_SECRET" value="test_webhook_secret"/>
```

`tests/TestCase.php`, the full file:
```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
    }
}
```

- [ ] **Step 2: Write the failing test**

`php artisan make:test --pest RazorpayTest --no-interaction`, then replace the contents:
```php
<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Razorpay;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

test('creating a Razorpay order posts the amount in paise with basic auth', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_TEST1', 'amount' => 104900, 'status' => 'created'])]);

    $gatewayOrder = app(Razorpay::class)->createOrder(104900, 'ARU-000001');

    expect($gatewayOrder['id'])->toBe('order_TEST1');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.razorpay.com/v1/orders'
        && $request->method() === 'POST'
        && $request->data() === ['amount' => 104900, 'currency' => 'INR', 'receipt' => 'ARU-000001']
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_fake:test_secret')));
});

test('a Razorpay API error is thrown to the caller', function () {
    Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['description' => 'Bad request']], 400)]);

    app(Razorpay::class)->createOrder(104900, 'ARU-000001');
})->throws(RequestException::class);

test('a refund posts the amount to the payment', function () {
    Http::fake(['api.razorpay.com/v1/payments/pay_TEST1/refund' => Http::response(['id' => 'rfnd_TEST1', 'amount' => 104900, 'status' => 'processed'])]);

    expect(app(Razorpay::class)->refund('pay_TEST1', 104900)['id'])->toBe('rfnd_TEST1');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.razorpay.com/v1/payments/pay_TEST1/refund'
        && $request['amount'] === 104900);
});

test('payment signatures are checked against the key secret', function () {
    $razorpay = app(Razorpay::class);
    $signature = hash_hmac('sha256', 'order_TEST1|pay_TEST1', 'test_secret');

    expect($razorpay->isValidPaymentSignature('order_TEST1', 'pay_TEST1', $signature))->toBeTrue()
        ->and($razorpay->isValidPaymentSignature('order_TEST1', 'pay_OTHER', $signature))->toBeFalse();
});

test('webhook signatures are checked against the webhook secret', function () {
    $razorpay = app(Razorpay::class);
    $body = '{"event":"payment.captured"}';

    expect($razorpay->isValidWebhookSignature($body, hash_hmac('sha256', $body, 'test_webhook_secret')))->toBeTrue()
        ->and($razorpay->isValidWebhookSignature($body.' ', hash_hmac('sha256', $body, 'test_webhook_secret')))->toBeFalse();

    config(['services.razorpay.webhook_secret' => null]);

    expect($razorpay->isValidWebhookSignature($body, hash_hmac('sha256', $body, '')))->toBeFalse();
});

test('Razorpay counts as configured only with both keys', function () {
    expect(app(Razorpay::class)->isConfigured())->toBeTrue();

    config(['services.razorpay.key_secret' => null]);

    expect(app(Razorpay::class)->isConfigured())->toBeFalse();
});

test('an order keeps its payment attempts in order', function () {
    $order = Order::factory()->razorpay()->create();
    Payment::factory()->for($order)->create();
    Payment::factory()->for($order)->paid()->create(['payload' => ['payment' => ['id' => 'pay_TEST1']]]);

    expect($order->payments)->toHaveCount(2)
        ->and($order->payments->last()->status)->toBe(PaymentStatus::Paid)
        ->and($order->payments->last()->payload)->toBe(['payment' => ['id' => 'pay_TEST1']]);
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/RazorpayTest.php`
Expected: FAIL with `Class "App\Services\Razorpay" not found`.

- [ ] **Step 4: The payments table, model and factory**

Run `php artisan make:model Payment --migration --factory --no-interaction`.

The migration's `up()`:
```php
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->index()->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('gateway_order_id', 40)->index();
            $table->string('gateway_payment_id', 40)->nullable()->unique();
            $table->unsignedInteger('amount');
            $table->string('status', 20);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }
```

`app/Models/Payment.php`:
```php
<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'amount', 'status', 'payload'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
```

`database/factories/PaymentFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->razorpay(),
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_'.Str::random(14),
            'gateway_payment_id' => null,
            'amount' => 104900,
            'status' => PaymentStatus::AwaitingPayment,
            'payload' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway_payment_id' => 'pay_'.Str::random(14),
            'status' => PaymentStatus::Paid,
        ]);
    }
}
```

`database/factories/OrderFactory.php`, add after `definition()`:
```php
    public function razorpay(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Razorpay,
            'payment_status' => PaymentStatus::AwaitingPayment,
            'fulfilment_status' => FulfilmentStatus::Pending,
        ]);
    }
```

`app/Models/Order.php`, add after `items()`:
```php
    /**
     * Payment attempts, oldest first. The first row is the one created when the order was placed.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }
```

- [ ] **Step 5: The Razorpay client**

`php artisan make:class Services/Razorpay --no-interaction`, then replace the contents:
```php
<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class Razorpay
{
    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    public function keyId(): string
    {
        return (string) config('services.razorpay.key_id');
    }

    /**
     * Create a Razorpay order for an amount in paise.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException|ConnectionException
     */
    public function createOrder(int $amount, string $receipt): array
    {
        return $this->client()
            ->post('orders', ['amount' => $amount, 'currency' => 'INR', 'receipt' => $receipt])
            ->throw()
            ->json();
    }

    /**
     * Refund a captured payment, amount in paise.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException|ConnectionException
     */
    public function refund(string $paymentId, int $amount): array
    {
        return $this->client()
            ->post("payments/{$paymentId}/refund", ['amount' => $amount])
            ->throw()
            ->json();
    }

    /**
     * The signature Razorpay Checkout hands the browser after a successful payment.
     */
    public function isValidPaymentSignature(string $gatewayOrderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', "{$gatewayOrderId}|{$paymentId}", (string) config('services.razorpay.key_secret'));

        return hash_equals($expected, $signature);
    }

    /**
     * The X-Razorpay-Signature header: HMAC of the raw request body with the webhook secret.
     */
    public function isValidWebhookSignature(string $body, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('https://api.razorpay.com/v1')
            ->withBasicAuth((string) config('services.razorpay.key_id'), (string) config('services.razorpay.key_secret'))
            ->acceptJson()
            ->asJson()
            ->timeout(10);
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan migrate --no-interaction && php artisan test --compact tests/Feature/RazorpayTest.php`
Expected: 7 passed.

Then run the full suite once: `php artisan test --compact`. Expected: all green. `Http::preventStrayRequests()` must not break any existing test, because nothing calls out over HTTP yet.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/services.php .env.example phpunit.xml tests/TestCase.php app/Services/Razorpay.php app/Models/Payment.php app/Models/Order.php database/migrations/*_create_payments_table.php database/factories/PaymentFactory.php database/factories/OrderFactory.php tests/Feature/RazorpayTest.php
git commit -m "feat: razorpay client and payments table"
```

---

### Task 2: Place online payment orders

**Files:**
- Create: `app/Actions/SendOrderPlacedEmails.php`
- Modify: `app/Actions/PlaceOrder.php`, `app/Http/Requests/CheckoutRequest.php`, `app/Http/Controllers/CheckoutController.php`
- Test: `tests/Feature/Storefront/PlaceOrderTest.php`, `tests/Feature/Storefront/CheckoutPageTest.php`, `tests/Feature/OrderEmailTest.php`

**Interfaces:**
- Consumes: `Razorpay` and `Payment` (Task 1); `PlaceOrder` (Phase 3).
- Produces:
  - `SendOrderPlacedEmails::handle(Order $order): void`. It queues `OrderPlaced` to the customer when they gave an email, and `NewOrderReceived` to every admin. Task 4 calls it when an online order is paid.
  - `PlaceOrder::handle()` keeps its signature. With `payment_method` `razorpay`:
    - The order is created with `AwaitingPayment` and `Pending`.
    - One `Payment` is created with `gateway_order_id` = the Razorpay order id, `amount` = order total, `status` `AwaitingPayment` and `payload` `['order' => <razorpay order>]`.
    - No email is sent.
    - Razorpay failure throws `ValidationException` on `payment_method` and nothing is written.
  - Page `Checkout/Create` gains the prop `online_payment: bool`.

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/Storefront/PlaceOrderTest.php`:

1. Add these imports to the top block:
```php
use App\Models\Payment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
```

2. Replace the test `online payment is not offered yet` with:
```php
test('an online payment order reserves the stock and waits for payment', function () {
    Mail::fake();
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_TEST1', 'amount' => 103900, 'status' => 'created'])]);

    $response = $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']));

    $order = Order::sole();
    $response->assertRedirect($order->trackingUrl())->assertSessionMissing('cart');

    expect($order->payment_status)->toBe(PaymentStatus::AwaitingPayment)
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::Pending)
        ->and($order->payments()->sole()->only('gateway_order_id', 'amount'))->toBe(['gateway_order_id' => 'order_TEST1', 'amount' => 103900])
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::AwaitingPayment)
        ->and($order->payments()->sole()->payload['order']['id'])->toBe('order_TEST1')
        ->and($this->tee->fresh()->stock)->toBe(4);
    Http::assertSent(fn (Request $request) => $request['amount'] === 103900 && $request['receipt'] === $order->number);
    Mail::assertNothingQueued();
});

test('a Razorpay failure rolls the whole order back', function (string $failure) {
    Http::fake(['api.razorpay.com/*' => $failure === 'error response'
        ? Http::response(['error' => ['description' => 'Server error']], 500)
        : Http::failedConnection('Timed out')]);

    $this->withSession(['cart' => [$this->tee->id => 2]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']))
        ->assertSessionHasErrors('payment_method')
        ->assertSessionHas('cart');

    expect(Order::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and($this->tee->fresh()->stock)->toBe(5);
})->with(['error response', 'no connection']);

test('online payment is offered beyond the cash on delivery limit', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_TEST1', 'amount' => 399600, 'status' => 'created'])]);

    $this->withSession(['cart' => [$this->tee->id => 4]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']))
        ->assertSessionHasNoErrors();

    expect(Order::sole()->total)->toBe(399600);
});

test('online payment is refused when Razorpay is not set up', function () {
    config(['services.razorpay.key_id' => null]);

    $this->withSession(['cart' => [$this->tee->id => 1]])
        ->post('/checkout', checkoutPayload(['payment_method' => 'razorpay']))
        ->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
    Http::assertNothingSent();
});
```

In `tests/Feature/Storefront/CheckoutPageTest.php`, append:
```php
test('online payment is offered only when Razorpay is set up', function () {
    $this->withSession($this->cart)->get('/checkout')
        ->assertInertia(fn ($page) => $page->where('online_payment', true));

    config(['services.razorpay.key_id' => null]);

    $this->withSession($this->cart)->get('/checkout')
        ->assertInertia(fn ($page) => $page->where('online_payment', false));
});
```

In `tests/Feature/OrderEmailTest.php`:

1. Add the import:
```php
use Illuminate\Support\Facades\Http;
```

2. Append:
```php
test('an online order sends no email until it is paid', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_TEST1', 'status' => 'created'])]);

    test()->withSession(['cart' => [test()->variant->id => 1]])->post('/checkout', [
        'name' => 'Mami', 'phone' => '9876543210', 'email' => 'mami@example.com',
        'line1' => 'House 1', 'locality' => 'Chanmari', 'district' => 'Aizawl', 'pincode' => '796001',
        'payment_method' => 'razorpay',
    ])->assertSessionHasNoErrors();

    Mail::assertNothingQueued();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Storefront/PlaceOrderTest.php tests/Feature/Storefront/CheckoutPageTest.php tests/Feature/OrderEmailTest.php`
Expected: the new tests FAIL. `payment_method` is rejected by `CheckoutRequest`, and the `online_payment` prop is missing.

- [ ] **Step 3: Extract the email dispatch**

`php artisan make:class Actions/SendOrderPlacedEmails --no-interaction`, then replace the contents:
```php
<?php

namespace App\Actions;

use App\Mail\NewOrderReceived;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SendOrderPlacedEmails
{
    /**
     * Queue the receipt (when the customer gave an email) and the admin alert.
     * COD orders send these when placed; online orders when paid.
     */
    public function handle(Order $order): void
    {
        $order->loadMissing('items');

        if ($order->customer_email) {
            Mail::to($order->customer_email)->queue(new OrderPlaced($order));
        }

        $adminEmails = User::where('is_admin', true)->pluck('email');

        if ($adminEmails->isNotEmpty()) {
            Mail::to($adminEmails)->queue(new NewOrderReceived($order));
        }
    }
}
```

- [ ] **Step 4: The Razorpay branch in `PlaceOrder`**

In `app/Actions/PlaceOrder.php`:

1. Replace the `use` block with:
```php
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Razorpay;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
```

2. Add a constructor as the first member of the class:
```php
    public function __construct(
        private Razorpay $razorpay,
        private SendOrderPlacedEmails $sendOrderPlacedEmails,
    ) {}
```

3. Update the method docblock's first sentence to: `Create the order in one transaction: lock and re-check every variant, price from current data, snapshot the items and address, decrement stock and, for online payment, create the Razorpay order. Any failure rolls everything back.`

4. Directly after `$paymentMethod = PaymentMethod::from($customer['payment_method']);`, add:
```php
        if ($paymentMethod === PaymentMethod::Razorpay && ! $this->razorpay->isConfigured()) {
            throw ValidationException::withMessages(['payment_method' => "Online payment isn't available right now."]);
        }
```

5. In `Order::create([...])`, replace the two status lines with:
```php
                'payment_status' => $paymentMethod === PaymentMethod::Cod ? PaymentStatus::Pending : PaymentStatus::AwaitingPayment,
                'fulfilment_status' => $paymentMethod === PaymentMethod::Cod ? FulfilmentStatus::Confirmed : FulfilmentStatus::Pending,
```

6. Inside the transaction, directly after the `foreach` that creates items and decrements stock (before `if ($user) {`), add:
```php
            if ($paymentMethod === PaymentMethod::Razorpay) {
                try {
                    $gatewayOrder = $this->razorpay->createOrder($total, $order->number);
                } catch (RequestException|ConnectionException $exception) {
                    report($exception);

                    throw ValidationException::withMessages(['payment_method' => "We couldn't start the online payment. Please try again."]);
                }

                $order->payments()->create([
                    'gateway' => 'razorpay',
                    'gateway_order_id' => $gatewayOrder['id'],
                    'amount' => $total,
                    'status' => PaymentStatus::AwaitingPayment,
                    'payload' => ['order' => $gatewayOrder],
                ]);
            }
```

7. Replace everything after the transaction (from `$order->load('items');` to the end of `handle()`) with:
```php
        if ($paymentMethod === PaymentMethod::Cod) {
            $this->sendOrderPlacedEmails->handle($order);
        }

        return $order;
    }
```

Remove the now-unused `Mail`, `OrderPlaced` and `NewOrderReceived` imports; Pint flags any that are left.

- [ ] **Step 5: Accept `razorpay` at checkout and share `online_payment`**

`app/Http/Requests/CheckoutRequest.php`, replace the `payment_method` rule:
```php
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
```

`app/Http/Controllers/CheckoutController.php`:

1. Add `use App\Services\Razorpay;`.

2. Change the signature to `public function create(Request $request, Cart $cart, Razorpay $razorpay): Response|RedirectResponse`.

3. Add to the `Inertia::render('Checkout/Create', [...])` props, after `'districts' => Address::DISTRICTS,`:
```php
            'online_payment' => $razorpay->isConfigured(),
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Storefront/PlaceOrderTest.php tests/Feature/Storefront/CheckoutPageTest.php tests/Feature/OrderEmailTest.php`
Expected: all pass, including every existing COD test.

Then run the full suite once: `php artisan test --compact`.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/PlaceOrder.php app/Actions/SendOrderPlacedEmails.php app/Http/Requests/CheckoutRequest.php app/Http/Controllers/CheckoutController.php tests/Feature/Storefront/PlaceOrderTest.php tests/Feature/Storefront/CheckoutPageTest.php tests/Feature/OrderEmailTest.php
git commit -m "feat: place online payment orders through razorpay"
```

---

### Task 3: Cancel and refund actions

**Files:**
- Create: `app/Actions/CancelOrder.php`, `app/Actions/RefundPayment.php`
- Test: `tests/Feature/OrderReversalTest.php`

**Interfaces:**
- Consumes: `Razorpay::refund()` and `Payment` (Task 1); `Order`, `OrderItem`, `ProductVariant` (Phase 3).
- Produces:
  - `CancelOrder::handle(Order $order, bool $onlyIfAwaitingPayment = false): Order`:
    - It locks the order, then its variants in ascending id order, and restores stock.
    - It sets fulfilment to `Cancelled`. If the payment was `AwaitingPayment`, it marks both the order and its awaiting payment rows `Failed`.
    - It is idempotent.
    - It throws `ValidationException` on `order` when the order is past `Packed`.
    - With `$onlyIfAwaitingPayment` it does nothing unless the order is still `AwaitingPayment`. Task 6's sweep uses this.
    - It does not refund.
  - `RefundPayment::handle(Order $order): Payment`:
    - It refunds the latest `Paid` payment in full.
    - It sets that payment and the order to `Refunded` and stores `payload['refund']`.
    - It is idempotent: an already refunded payment is returned without an API call.
    - It throws `ValidationException` on `payment` when there is nothing to refund or Razorpay fails. In that case nothing changes.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest OrderReversalTest --no-interaction`, then replace the contents:
```php
<?php

use App\Actions\CancelOrder;
use App\Actions\RefundPayment;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->variant = ProductVariant::factory()->create(['stock' => 2]);
    $this->order = Order::factory()->razorpay()->create();
    OrderItem::factory()->for($this->order)->create(['product_variant_id' => $this->variant->id, 'quantity' => 3]);
    $this->payment = Payment::factory()->for($this->order)->create(['amount' => $this->order->total]);
});

test('cancelling an unpaid online order puts the stock back and fails the payment', function () {
    app(CancelOrder::class)->handle($this->order);

    $order = $this->order->fresh();

    expect($order->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($order->payment_status)->toBe(PaymentStatus::Failed)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->variant->fresh()->stock)->toBe(5);
});

test('cancelling twice puts the stock back once', function () {
    app(CancelOrder::class)->handle($this->order);
    app(CancelOrder::class)->handle($this->order);

    expect($this->variant->fresh()->stock)->toBe(5);
});

test('a dispatched order cannot be cancelled', function () {
    $this->order->update(['fulfilment_status' => FulfilmentStatus::Dispatched]);

    expect(fn () => app(CancelOrder::class)->handle($this->order))->toThrow(ValidationException::class);

    expect($this->variant->fresh()->stock)->toBe(2);
});

test('the unpaid-order sweep leaves an order that was just paid', function () {
    $this->order->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);

    app(CancelOrder::class)->handle($this->order, onlyIfAwaitingPayment: true);

    expect($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->variant->fresh()->stock)->toBe(2);
});

test('a captured online payment is refunded in full', function () {
    $this->order->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    $this->payment->update(['gateway_payment_id' => 'pay_TEST1', 'status' => PaymentStatus::Paid]);
    Http::fake(['api.razorpay.com/v1/payments/pay_TEST1/refund' => Http::response(['id' => 'rfnd_TEST1', 'status' => 'processed'])]);

    app(RefundPayment::class)->handle($this->order);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($this->payment->fresh()->payload['refund']['id'])->toBe('rfnd_TEST1')
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
    Http::assertSent(fn (Request $request) => $request['amount'] === $this->order->total);
});

test('a refund is only sent once', function () {
    $this->order->update(['payment_status' => PaymentStatus::Paid]);
    $this->payment->update(['gateway_payment_id' => 'pay_TEST1', 'status' => PaymentStatus::Paid]);
    Http::fake(['api.razorpay.com/v1/payments/pay_TEST1/refund' => Http::response(['id' => 'rfnd_TEST1', 'status' => 'processed'])]);

    app(RefundPayment::class)->handle($this->order);
    app(RefundPayment::class)->handle($this->order);

    Http::assertSentCount(1);
});

test('a failed refund leaves the payment as paid', function () {
    $this->order->update(['payment_status' => PaymentStatus::Paid]);
    $this->payment->update(['gateway_payment_id' => 'pay_TEST1', 'status' => PaymentStatus::Paid]);
    Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['description' => 'Refund failed']], 400)]);

    expect(fn () => app(RefundPayment::class)->handle($this->order))->toThrow(ValidationException::class);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('an order without a captured payment cannot be refunded', function () {
    expect(fn () => app(RefundPayment::class)->handle($this->order))->toThrow(ValidationException::class);

    Http::assertNothingSent();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/OrderReversalTest.php`
Expected: FAIL with `Class "App\Actions\CancelOrder" not found`.

- [ ] **Step 3: `CancelOrder`**

`php artisan make:class Actions/CancelOrder --no-interaction`, then replace the contents:
```php
<?php

namespace App\Actions;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    /**
     * Cancel the order and put its stock back. An unpaid online payment is marked Failed.
     * Refunding a paid order is a separate step (RefundPayment).
     *
     * With $onlyIfAwaitingPayment the order is left alone unless it is still waiting for payment,
     * so the unpaid-order sweep never cancels an order that was paid a moment ago.
     */
    public function handle(Order $order, bool $onlyIfAwaitingPayment = false): Order
    {
        return DB::transaction(function () use ($order, $onlyIfAwaitingPayment): Order {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->fulfilment_status === FulfilmentStatus::Cancelled
                || ($onlyIfAwaitingPayment && $order->payment_status !== PaymentStatus::AwaitingPayment)) {
                return $order;
            }

            if (! in_array($order->fulfilment_status, [FulfilmentStatus::Pending, FulfilmentStatus::Confirmed, FulfilmentStatus::Packed], true)) {
                throw ValidationException::withMessages(['order' => "This order can't be cancelled once it has been dispatched."]);
            }

            $items = $order->items()->get();
            $variants = ProductVariant::whereKey($items->pluck('product_variant_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $variants->get($item->product_variant_id)?->increment('stock', $item->quantity);
            }

            if ($order->payment_status === PaymentStatus::AwaitingPayment) {
                $order->payments()
                    ->where('status', PaymentStatus::AwaitingPayment->value)
                    ->update(['status' => PaymentStatus::Failed->value]);
                $order->payment_status = PaymentStatus::Failed;
            }

            $order->fulfilment_status = FulfilmentStatus::Cancelled;
            $order->save();

            return $order;
        });
    }
}
```

- [ ] **Step 4: `RefundPayment`**

`php artisan make:class Actions/RefundPayment --no-interaction`, then replace the contents:
```php
<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Razorpay;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundPayment
{
    public function __construct(private Razorpay $razorpay) {}

    /**
     * Refund the order's captured online payment in full and record the result.
     * Calling it again after a successful refund changes nothing.
     */
    public function handle(Order $order): Payment
    {
        return DB::transaction(function () use ($order): Payment {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $payment = $order->payments()
                ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::Refunded->value])
                ->whereNotNull('gateway_payment_id')
                ->latest('id')
                ->first()
                ?? throw ValidationException::withMessages(['payment' => 'This order has no online payment to refund.']);

            if ($payment->status === PaymentStatus::Refunded) {
                return $payment;
            }

            try {
                $refund = $this->razorpay->refund($payment->gateway_payment_id, $payment->amount);
            } catch (RequestException|ConnectionException $exception) {
                report($exception);

                throw ValidationException::withMessages(['payment' => "Razorpay couldn't refund this payment. Try again in a few minutes."]);
            }

            $payment->update([
                'status' => PaymentStatus::Refunded,
                'payload' => [...($payment->payload ?? []), 'refund' => $refund],
            ]);
            $order->update(['payment_status' => PaymentStatus::Refunded]);

            return $payment;
        });
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/OrderReversalTest.php`
Expected: 8 passed.

- [ ] **Step 6: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/CancelOrder.php app/Actions/RefundPayment.php tests/Feature/OrderReversalTest.php
git commit -m "feat: cancel order and refund payment actions"
```

---

### Task 4: Confirm payments from the browser

**Files:**
- Create: `app/Actions/MarkOrderPaid.php`, `app/Http/Controllers/OrderPaymentController.php`
- Modify: `app/Http/Controllers/OrderController.php`, `routes/web.php`, `resources/views/mail/orders/placed.blade.php`
- Test: `tests/Feature/Storefront/OrderPaymentTest.php`

**Interfaces:**
- Consumes: `Razorpay`, `Payment` (Task 1); `SendOrderPlacedEmails` (Task 2); `RefundPayment` (Task 3).
- Produces:
  - `MarkOrderPaid::handle(Order $order, string $gatewayOrderId, string $paymentId, int $amount, array $payload = []): void`. It locks the order and finds the first payment row for `$gatewayOrderId`.
    - It throws `UnexpectedValueException` when there is no such row or the amount differs.
    - If the row is already `Paid` or `Refunded`, it does nothing.
    - Otherwise it sets the row's `gateway_payment_id` and status `Paid`, and adds `payload['payment']`. Then:
      - an open order becomes `Paid` + `Confirmed`, and the placed emails are sent;
      - a cancelled order stays cancelled and the payment is refunded.
    - Task 5's webhook calls it too.
  - Route `orders.payment.store`: POST `/orders/{order}/payment`, behind `signed` and `throttle:10,1`. It takes `razorpay_order_id`, `razorpay_payment_id` and `razorpay_signature`, and redirects to `trackingUrl()` with flash `payment_confirmed` (bool) or `payment_error` (string).
  - New props on page `Orders/Show`:
    - `pays_on_delivery: bool`
    - `payment: {key, gateway_order_id, amount, verify_url, prefill: {name, contact, email}} | null`. It is non-null only while an online order is `AwaitingPayment`.
    - `payment_confirmed: bool`
    - `payment_error: string | null`

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Storefront/OrderPaymentTest --no-interaction`, then replace the contents:
```php
<?php

use App\Actions\MarkOrderPaid;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\NewOrderReceived;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Mail::fake();
    User::factory()->admin()->create();
    $this->order = Order::factory()->razorpay()->create(['customer_email' => 'mami@example.com']);
    $this->payment = Payment::factory()->for($this->order)->create(['gateway_order_id' => 'order_TEST1', 'amount' => $this->order->total]);
});

function confirmPayment(Order $order, string $paymentId = 'pay_TEST1', ?string $signature = null): TestResponse
{
    return test()->post(URL::signedRoute('orders.payment.store', $order), [
        'razorpay_order_id' => 'order_TEST1',
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature' => $signature ?? hash_hmac('sha256', "order_TEST1|{$paymentId}", 'test_secret'),
    ]);
}

test('a verified payment confirms the order and sends the emails', function () {
    confirmPayment($this->order)
        ->assertRedirect($this->order->trackingUrl())
        ->assertSessionHas('payment_confirmed', true);

    $order = $this->order->fresh();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->payment->fresh()->gateway_payment_id)->toBe('pay_TEST1')
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Paid);
    Mail::assertQueued(OrderPlaced::class, 1);
    Mail::assertQueued(NewOrderReceived::class, 1);
});

test('a tampered signature changes nothing', function () {
    confirmPayment($this->order, signature: 'not-a-real-signature')
        ->assertRedirect($this->order->trackingUrl())
        ->assertSessionHas('payment_error');

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
    Mail::assertNothingQueued();
});

test('the payment endpoint needs a signed link', function () {
    $this->post("/orders/{$this->order->id}/payment", [
        'razorpay_order_id' => 'order_TEST1',
        'razorpay_payment_id' => 'pay_TEST1',
        'razorpay_signature' => hash_hmac('sha256', 'order_TEST1|pay_TEST1', 'test_secret'),
    ])->assertForbidden();
});

test('confirming the same payment twice sends the emails once', function () {
    confirmPayment($this->order);
    confirmPayment($this->order);

    Mail::assertQueued(OrderPlaced::class, 1);
});

test('a payment that lands after the order was cancelled is refunded', function () {
    $this->order->update(['payment_status' => PaymentStatus::Failed, 'fulfilment_status' => FulfilmentStatus::Cancelled]);
    $this->payment->update(['status' => PaymentStatus::Failed]);
    Http::fake(['api.razorpay.com/v1/payments/pay_TEST1/refund' => Http::response(['id' => 'rfnd_TEST1', 'status' => 'processed'])]);

    app(MarkOrderPaid::class)->handle($this->order, 'order_TEST1', 'pay_TEST1', $this->order->total);

    $order = $this->order->fresh();

    expect($order->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded);
    Mail::assertNothingQueued();
});

test('a payment for a different amount is rejected', function () {
    expect(fn () => app(MarkOrderPaid::class)->handle($this->order, 'order_TEST1', 'pay_TEST1', 100))
        ->toThrow(UnexpectedValueException::class);

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
});

test('the order page offers payment only while the order is unpaid', function () {
    $this->get($this->order->trackingUrl())
        ->assertInertia(fn ($page) => $page
            ->where('pays_on_delivery', false)
            ->where('payment.key', 'rzp_test_fake')
            ->where('payment.gateway_order_id', 'order_TEST1')
            ->where('payment.amount', $this->order->total)
            ->where('payment.prefill.contact', $this->order->customer_phone)
            ->has('payment.verify_url')
            ->where('payment_confirmed', false)
            ->where('payment_error', null));

    $this->order->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);

    $this->get($this->order->trackingUrl())
        ->assertInertia(fn ($page) => $page->where('payment', null));
});

test('the receipt for an online order says the payment is received', function () {
    $html = (new OrderPlaced($this->order->load('items')))->render();

    expect($html)->toContain('Your online payment is received')
        ->not->toContain('cash on delivery');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Storefront/OrderPaymentTest.php`
Expected: FAIL. Route `orders.payment.store` is not defined, and `App\Actions\MarkOrderPaid` is not found.

- [ ] **Step 3: `MarkOrderPaid`**

`php artisan make:class Actions/MarkOrderPaid --no-interaction`, then replace the contents:
```php
<?php

namespace App\Actions;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class MarkOrderPaid
{
    public function __construct(
        private SendOrderPlacedEmails $sendOrderPlacedEmails,
        private RefundPayment $refundPayment,
    ) {}

    /**
     * The one place an online order becomes paid. The browser handler and the webhook both call it,
     * so it is idempotent. A payment that lands after the order was cancelled is refunded at once.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws UnexpectedValueException when the payment does not belong to this order or the amount differs
     */
    public function handle(Order $order, string $gatewayOrderId, string $paymentId, int $amount, array $payload = []): void
    {
        $outcome = DB::transaction(function () use ($order, $gatewayOrderId, $paymentId, $amount, $payload): string {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = $order->payments()->where('gateway_order_id', $gatewayOrderId)->oldest('id')->first();

            if (! $payment || $payment->amount !== $amount) {
                throw new UnexpectedValueException("Payment {$paymentId} does not match order {$order->number}.");
            }

            if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)) {
                return 'already recorded';
            }

            $payment->update([
                'gateway_payment_id' => $paymentId,
                'status' => PaymentStatus::Paid,
                'payload' => [...($payment->payload ?? []), 'payment' => $payload],
            ]);

            if ($order->fulfilment_status === FulfilmentStatus::Cancelled) {
                return 'order cancelled';
            }

            $order->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);

            return 'paid';
        });

        if ($outcome === 'paid') {
            $this->sendOrderPlacedEmails->handle($order->fresh());
        }

        if ($outcome === 'order cancelled') {
            $this->refundPayment->handle($order);
        }
    }
}
```

- [ ] **Step 4: The browser confirmation endpoint**

`php artisan make:controller OrderPaymentController --no-interaction`, then replace the contents:
```php
<?php

namespace App\Http\Controllers;

use App\Actions\MarkOrderPaid;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Razorpay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderPaymentController extends Controller
{
    /**
     * Razorpay Checkout's success handler posts here. The signature proves the payment belongs to
     * this order's Razorpay order; the webhook confirms the same payment independently.
     */
    public function store(Request $request, Order $order, Razorpay $razorpay, MarkOrderPaid $markOrderPaid): RedirectResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:40'],
            'razorpay_payment_id' => ['required', 'string', 'max:40'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
        ]);

        $payment = $order->payments()->where('gateway_order_id', $data['razorpay_order_id'])->oldest('id')->first();

        if (! $payment || ! $razorpay->isValidPaymentSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return redirect($order->trackingUrl())
                ->with('payment_error', "We couldn't confirm that payment. If money left your account, this page will update within a few minutes.");
        }

        $markOrderPaid->handle($order, $data['razorpay_order_id'], $data['razorpay_payment_id'], $payment->amount, $data);

        return redirect($order->trackingUrl())
            ->with('payment_confirmed', $order->fresh()->payment_status === PaymentStatus::Paid);
    }
}
```

`routes/web.php`:

1. Add `use App\Http\Controllers\OrderPaymentController;` to the imports.

2. Add after the `orders.show` route:
```php
Route::post('/orders/{order}/payment', [OrderPaymentController::class, 'store'])->middleware(['signed', 'throttle:10,1'])->name('orders.payment.store');
```

- [ ] **Step 5: Payment props on the order page**

In `app/Http/Controllers/OrderController.php`:

1. Add these imports:
```php
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Services\Razorpay;
use Illuminate\Support\Facades\URL;
```

2. Change the signature to `public function show(Order $order, Razorpay $razorpay): Response`.

3. After the `'just_placed' => (bool) session('order_placed'),` line, add:
```php
            'pays_on_delivery' => $order->payment_method === PaymentMethod::Cod,
            'payment' => $order->payment_method === PaymentMethod::Razorpay && $order->payment_status === PaymentStatus::AwaitingPayment
                ? [
                    'key' => $razorpay->keyId(),
                    'gateway_order_id' => $order->payments()->oldest('id')->value('gateway_order_id'),
                    'amount' => $order->total,
                    'verify_url' => URL::signedRoute('orders.payment.store', $order),
                    'prefill' => ['name' => $order->customer_name, 'contact' => $order->customer_phone, 'email' => (string) $order->customer_email],
                ]
                : null,
            'payment_confirmed' => (bool) session('payment_confirmed'),
            'payment_error' => session('payment_error'),
```

- [ ] **Step 6: The receipt copy for online payments**

In `resources/views/mail/orders/placed.blade.php`, replace the line that starts `Your order **{{ $order->number }}** is confirmed.` with:
```blade
Your order **{{ $order->number }}** is confirmed. {{ $order->payment_method === \App\Enums\PaymentMethod::Cod ? 'You pay cash on delivery' : 'Your online payment is received' }}, and we'll call {{ $order->customer_phone }} before we deliver.
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Storefront/OrderPaymentTest.php tests/Feature/OrderEmailTest.php tests/Feature/Storefront/PlaceOrderTest.php`
Expected: all pass. The COD receipt still reads "You pay cash on delivery", and the existing receipt tests stay green.

- [ ] **Step 8: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/MarkOrderPaid.php app/Http/Controllers/OrderPaymentController.php app/Http/Controllers/OrderController.php routes/web.php resources/views/mail/orders/placed.blade.php tests/Feature/Storefront/OrderPaymentTest.php
git commit -m "feat: confirm razorpay payments from the browser"
```

---

### Task 5: Razorpay webhook

**Files:**
- Create: `app/Http/Controllers/RazorpayWebhookController.php`
- Modify: `routes/web.php`, `bootstrap/app.php`
- Test: `tests/Feature/RazorpayWebhookTest.php`

**Interfaces:**
- Consumes: `Razorpay::isValidWebhookSignature()`, `Payment` (Task 1); `MarkOrderPaid` (Task 4).
- Produces: route `webhooks.razorpay`, POST `/webhooks/razorpay`, CSRF-exempt.
  - A bad signature gets 400.
  - Everything verified gets 204, so Razorpay stops retrying.
  - `payment.captured` calls `MarkOrderPaid`.
  - `payment.failed` stores a `Failed` payment row keyed on `gateway_payment_id`, without touching the order.
  - Other events, and Razorpay orders that aren't ours, are acknowledged and ignored.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest RazorpayWebhookTest --no-interaction`, then replace the contents:
```php
<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Mail::fake();
    $this->order = Order::factory()->razorpay()->create(['customer_email' => 'mami@example.com']);
    $this->payment = Payment::factory()->for($this->order)->create(['gateway_order_id' => 'order_TEST1', 'amount' => $this->order->total]);
});

/**
 * @return array<string, mixed>
 */
function razorpayEvent(string $event, string $paymentId, int $amount, string $gatewayOrderId = 'order_TEST1'): array
{
    return [
        'entity' => 'event',
        'event' => $event,
        'payload' => ['payment' => ['entity' => [
            'id' => $paymentId,
            'entity' => 'payment',
            'order_id' => $gatewayOrderId,
            'amount' => $amount,
            'currency' => 'INR',
            'status' => $event === 'payment.captured' ? 'captured' : 'failed',
        ]]],
    ];
}

function postRazorpayWebhook(array $event, ?string $signature = null): TestResponse
{
    $body = json_encode($event);

    return test()->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, 'test_webhook_secret'),
    ], $body);
}

test('a captured payment confirms the order', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', $this->order->total))->assertNoContent();

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->payment->fresh()->gateway_payment_id)->toBe('pay_TEST1');
    Mail::assertQueued(OrderPlaced::class, 1);
});

test('a replayed capture is recorded once', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', $this->order->total));
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', $this->order->total))->assertNoContent();

    expect(Payment::count())->toBe(1);
    Mail::assertQueued(OrderPlaced::class, 1);
});

test('a bad signature is rejected and changes nothing', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', $this->order->total), 'bad-signature')->assertStatus(400);

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
    Mail::assertNothingQueued();
});

test('a failed attempt is recorded once and the order keeps waiting', function () {
    postRazorpayWebhook(razorpayEvent('payment.failed', 'pay_FAIL1', $this->order->total))->assertNoContent();
    postRazorpayWebhook(razorpayEvent('payment.failed', 'pay_FAIL1', $this->order->total))->assertNoContent();

    expect(Payment::where('gateway_payment_id', 'pay_FAIL1')->sole()->status)->toBe(PaymentStatus::Failed)
        ->and(Payment::count())->toBe(2)
        ->and($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment)
        ->and($this->order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Pending);
});

test('events for other Razorpay orders and other event types are acknowledged and ignored', function () {
    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_OTHER', 100, 'order_UNKNOWN'))->assertNoContent();
    postRazorpayWebhook(['entity' => 'event', 'event' => 'order.paid', 'payload' => []])->assertNoContent();

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
});

test('a capture for the wrong amount is reported and ignored', function () {
    Exceptions::fake();

    postRazorpayWebhook(razorpayEvent('payment.captured', 'pay_TEST1', 100))->assertNoContent();

    expect($this->order->fresh()->payment_status)->toBe(PaymentStatus::AwaitingPayment);
    Exceptions::assertReported(UnexpectedValueException::class);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/RazorpayWebhookTest.php`
Expected: FAIL with 404 responses, because the route doesn't exist.

- [ ] **Step 3: The webhook controller, route and CSRF exemption**

`php artisan make:controller RazorpayWebhookController --invokable --no-interaction`, then replace the contents:
```php
<?php

namespace App\Http\Controllers;

use App\Actions\MarkOrderPaid;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Razorpay;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use UnexpectedValueException;

class RazorpayWebhookController extends Controller
{
    /**
     * Razorpay posts every subscribed event here. This is the source of truth for payments:
     * signature-checked, idempotent, and answered 2xx once verified so Razorpay stops retrying.
     */
    public function __invoke(Request $request, Razorpay $razorpay, MarkOrderPaid $markOrderPaid): Response
    {
        abort_unless(
            $razorpay->isValidWebhookSignature($request->getContent(), (string) $request->header('X-Razorpay-Signature')),
            400,
            'Invalid signature.',
        );

        $entity = $request->input('payload.payment.entity');

        if (! is_array($entity) || ! isset($entity['id'], $entity['order_id'], $entity['amount'])) {
            return response()->noContent();
        }

        $payment = Payment::with('order')->where('gateway_order_id', $entity['order_id'])->oldest('id')->first();

        if (! $payment) {
            return response()->noContent();
        }

        try {
            match ($request->input('event')) {
                'payment.captured' => $markOrderPaid->handle($payment->order, $entity['order_id'], $entity['id'], (int) $entity['amount'], $entity),
                'payment.failed' => $this->recordFailedAttempt($payment, $entity),
                default => null,
            };
        } catch (UnexpectedValueException $exception) {
            report($exception);
        }

        return response()->noContent();
    }

    /**
     * One row per failed attempt. The order keeps waiting: the shopper may try again until the sweep.
     *
     * @param  array{id: string, order_id: string, amount: int|string}  $entity
     */
    private function recordFailedAttempt(Payment $payment, array $entity): void
    {
        Payment::firstOrCreate(['gateway_payment_id' => $entity['id']], [
            'order_id' => $payment->order_id,
            'gateway' => 'razorpay',
            'gateway_order_id' => $entity['order_id'],
            'amount' => (int) $entity['amount'],
            'status' => PaymentStatus::Failed,
            'payload' => ['payment' => $entity],
        ]);
    }
}
```

`routes/web.php`:

1. Add `use App\Http\Controllers\RazorpayWebhookController;`.

2. Add after the `orders.payment.store` route:
```php
Route::post('/webhooks/razorpay', RazorpayWebhookController::class)->name('webhooks.razorpay');
```

`bootstrap/app.php`, replace the `//` inside `withMiddleware` with:
```php
        $middleware->validateCsrfTokens(except: ['webhooks/razorpay']);
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/RazorpayWebhookTest.php`
Expected: 6 passed.

The test runner skips CSRF checks, so also check the exemption by hand. Start the app, then:
```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8000/webhooks/razorpay -H 'Content-Type: application/json' -H 'X-Razorpay-Signature: x' -d '{}'
```
Expected: `400`, the signature rejection. `419` would mean CSRF still applies.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/RazorpayWebhookController.php routes/web.php bootstrap/app.php tests/Feature/RazorpayWebhookTest.php
git commit -m "feat: razorpay webhook for captured and failed payments"
```

---

### Task 6: Cancel orders left unpaid

**Files:**
- Modify: `routes/console.php`
- Test: `tests/Feature/AbandonedOrderTest.php`

**Interfaces:**
- Consumes: `CancelOrder::handle($order, onlyIfAwaitingPayment: true)` (Task 3).
- Produces: the artisan command `orders:cancel-abandoned`, scheduled every five minutes without overlapping. It cancels orders whose `payment_status` is `AwaitingPayment` and whose `placed_at` is more than 30 minutes ago.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest AbandonedOrderTest --no-interaction`, then replace the contents:
```php
<?php

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;

beforeEach(function () {
    $this->variant = ProductVariant::factory()->create(['stock' => 0]);
});

function unpaidOrder(int $minutesAgo): Order
{
    $order = Order::factory()->razorpay()->create(['placed_at' => now()->subMinutes($minutesAgo)]);
    OrderItem::factory()->for($order)->create(['product_variant_id' => test()->variant->id, 'quantity' => 2]);
    Payment::factory()->for($order)->create(['amount' => $order->total]);

    return $order;
}

test('online orders left unpaid for 30 minutes are cancelled and their stock comes back', function () {
    $order = unpaidOrder(31);

    $this->artisan('orders:cancel-abandoned')->assertSuccessful();

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Cancelled)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Failed)
        ->and($this->variant->fresh()->stock)->toBe(2);
});

test('recent, paid and cash on delivery orders are left alone', function () {
    $recent = unpaidOrder(29);
    $paid = unpaidOrder(45);
    $paid->update(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Confirmed]);
    $cashOnDelivery = Order::factory()->create(['placed_at' => now()->subHour()]);

    $this->artisan('orders:cancel-abandoned')->assertSuccessful();

    expect($recent->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Pending)
        ->and($paid->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($cashOnDelivery->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Confirmed)
        ->and($this->variant->fresh()->stock)->toBe(0);
});

test('the sweep is scheduled every five minutes', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('*/5 * * * *')
        ->expectsOutputToContain('orders:cancel-abandoned')
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/AbandonedOrderTest.php`
Expected: FAIL with `The command "orders:cancel-abandoned" does not exist.`

- [ ] **Step 3: The command and its schedule**

In `routes/console.php`, add these imports to the top:
```php
use App\Actions\CancelOrder;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Schedule;
```
Then append at the end of the file:
```php
Artisan::command('orders:cancel-abandoned', function (CancelOrder $cancelOrder): int {
    $orders = Order::where('payment_status', PaymentStatus::AwaitingPayment)
        ->where('placed_at', '<', now()->subMinutes(30))
        ->get();

    foreach ($orders as $order) {
        $cancelOrder->handle($order, onlyIfAwaitingPayment: true);
    }

    $this->info("Checked {$orders->count()} unpaid orders.");

    return 0;
})->purpose('Cancel online orders left unpaid for 30 minutes and put their stock back');

Schedule::command('orders:cancel-abandoned')->everyFiveMinutes()->withoutOverlapping();
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/AbandonedOrderTest.php`
Expected: 3 passed.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/console.php tests/Feature/AbandonedOrderTest.php
git commit -m "feat: cancel online orders left unpaid for 30 minutes"
```

---

### Task 7: Online payment on the storefront

**Design:**
- Before writing Vue, invoke `ui-ux-pro-max` for the payment choice and the pay panel (mobile apparel store).
- Read `.claude/skills/design-motion-principles/SKILL.md` for any motion.
- Keep the Phase 3 checkout and order page visual language: same fields, borders and button styles.

**Files:**
- Modify: `resources/js/Pages/Checkout/Create.vue`, `resources/js/Pages/Orders/Show.vue`

**Interfaces:**
- Consumes:
  - Prop `online_payment: bool` on `Checkout/Create` (Task 2).
  - Props `pays_on_delivery`, `payment`, `payment_confirmed` and `payment_error` on `Orders/Show` (Task 4).
  - Route `orders.payment.store`, through `payment.verify_url` (Task 4).
- Produces: the storefront flow. There are no new server interfaces.

- [ ] **Step 1: The payment choice at checkout**

In `resources/js/Pages/Checkout/Create.vue`:

1. Add `online_payment` to `defineProps`:
```js
    online_payment: { type: Boolean, required: true },
```

2. Replace the `canPlaceOrder` line with:
```js
watch(liveQuote, (quote) => {
    if (quote?.deliverable && !quote.cod_available && props.online_payment) {
        form.payment_method = 'razorpay';
    }
});

const methodAvailable = computed(() => (form.payment_method === 'cod' ? liveQuote.value?.cod_available : props.online_payment));

const canPlaceOrder = computed(() => liveQuote.value?.deliverable && methodAvailable.value && !form.processing);

const buttonLabel = computed(() => {
    if (form.processing) {
        return 'Placing order…';
    }

    if (!liveQuote.value?.deliverable) {
        return 'Enter your pincode';
    }

    return `${form.payment_method === 'razorpay' ? 'Continue to payment' : 'Place order'} · ${formatPaise(liveQuote.value.total)}`;
});
```

3. Replace the whole Payment `<fieldset>` with:
```html
                <fieldset class="space-y-3">
                    <legend class="font-display text-xl font-semibold">Payment</legend>
                    <label class="flex min-h-11 items-center gap-3 border border-brand-300 px-4" :class="{ 'opacity-50': liveQuote?.deliverable && !liveQuote.cod_available }">
                        <input v-model="form.payment_method" type="radio" value="cod" class="text-ink focus:ring-ink" :disabled="liveQuote?.deliverable && !liveQuote.cod_available" />
                        <span>Cash on delivery</span>
                    </label>
                    <label v-if="online_payment" class="flex min-h-11 items-center gap-3 border border-brand-300 px-4">
                        <input v-model="form.payment_method" type="radio" value="razorpay" class="text-ink focus:ring-ink" />
                        <span>Pay online <span class="text-brand-600">· UPI, cards, netbanking</span></span>
                    </label>
                    <p v-if="liveQuote?.deliverable && !liveQuote.cod_available" class="text-sm text-red-700" role="status">
                        Cash on delivery isn't available for this order.<template v-if="online_payment"> Pay online instead.</template>
                    </p>
                    <InputError :message="form.errors.payment_method" />
                </fieldset>
```

4. Replace the submit button's text expression `{{ form.processing ? … }}` with `{{ buttonLabel }}`.

- [ ] **Step 2: The pay panel on the order page**

Replace `resources/js/Pages/Orders/Show.vue` with the following. Everything below the pay panel matches the Phase 3 page.
```vue
<script setup>
import { onMounted, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    order: { type: Object, required: true },
    just_placed: { type: Boolean, required: true },
    pays_on_delivery: { type: Boolean, required: true },
    payment: { type: Object, default: null },
    payment_confirmed: { type: Boolean, required: true },
    payment_error: { type: String, default: null },
});

const page = usePage();
const paymentMessage = ref(props.payment_error);
const opening = ref(false);

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';

/** Razorpay Checkout is loaded only on this page, only when someone pays. */
const loadCheckout = () =>
    new Promise((resolve, reject) => {
        if (window.Razorpay) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = resolve;
        script.onerror = () => reject(new Error('Razorpay Checkout could not load.'));
        document.head.appendChild(script);
    });

const pay = async () => {
    opening.value = true;
    paymentMessage.value = null;

    try {
        await loadCheckout();
    } catch {
        paymentMessage.value = "The payment window couldn't load. Check your connection and try again.";
        opening.value = false;
        return;
    }

    const checkout = new window.Razorpay({
        key: props.payment.key,
        amount: props.payment.amount,
        currency: 'INR',
        order_id: props.payment.gateway_order_id,
        name: page.props.store.store_name,
        description: `Order ${props.order.number}`,
        prefill: props.payment.prefill,
        theme: { color: '#121110' },
        handler: (response) => router.post(props.payment.verify_url, response, { preserveScroll: true }),
        modal: {
            ondismiss: () => {
                opening.value = false;
                paymentMessage.value ??= 'Payment not completed. Your items stay reserved for 30 minutes.';
            },
        },
    });

    checkout.on('payment.failed', (response) => {
        paymentMessage.value = response.error?.description ?? 'The payment failed. Please try again.';
    });

    checkout.open();
};

onMounted(() => {
    if (props.payment && props.just_placed) {
        pay();
    }
});
</script>

<template>
    <Head :title="`Order ${order.number}`" />

    <StoreLayout>
        <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
            <div v-if="payment_confirmed" class="mb-8 border border-brand-300 bg-brand-100 px-4 py-4" role="status">
                <p class="font-display text-2xl font-semibold">Payment received. Thank you, {{ order.customer_name }}.</p>
                <p class="mt-1 text-sm text-brand-700">
                    Your order is confirmed. We'll call {{ order.customer_phone }} before delivery. Bookmark this page to check on your order.
                </p>
            </div>
            <div v-else-if="just_placed && pays_on_delivery" class="mb-8 border border-brand-300 bg-brand-100 px-4 py-4" role="status">
                <p class="font-display text-2xl font-semibold">Thank you, {{ order.customer_name }}.</p>
                <p class="mt-1 text-sm text-brand-700">
                    Your order is confirmed. We'll call {{ order.customer_phone }} before delivery. Bookmark this page to check on your order.
                </p>
            </div>

            <section v-if="payment" class="mb-8 border border-ink px-4 py-5" aria-labelledby="pay-title">
                <h2 id="pay-title" class="font-display text-2xl font-semibold">Complete your payment</h2>
                <p class="mt-1 text-sm text-brand-700">Your items are reserved for 30 minutes. Pay now to confirm the order.</p>
                <p v-if="paymentMessage" class="mt-3 text-sm text-red-700" role="alert">{{ paymentMessage }}</p>
                <button
                    type="button"
                    class="mt-4 flex min-h-12 w-full items-center justify-center bg-ink text-xs font-medium uppercase tracking-[0.2em] text-brand-50 disabled:opacity-40"
                    :class="focusRing"
                    :disabled="opening"
                    @click="pay"
                >
                    {{ opening ? 'Opening payment…' : `Pay ${formatPaise(payment.amount)}` }}
                </button>
            </section>
            <p v-else-if="order.fulfilment_status === 'Cancelled' && order.payment_status === 'Failed'" class="mb-8 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="status">
                This order was cancelled because the payment wasn't completed.
            </p>
            <p v-else-if="order.fulfilment_status === 'Cancelled' && order.payment_status === 'Refunded'" class="mb-8 border border-brand-300 bg-brand-100 px-4 py-3 text-sm" role="status">
                This order was cancelled and your payment has been refunded. Refunds reach your account in 5–7 working days.
            </p>

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

- [ ] **Step 3: Build and run the full suite**

```bash
source ~/.nvm/nvm.sh && nvm use && npm run build
php artisan test --compact
```
Expected: the build succeeds. Note the `Orders/Show` and `Checkout/Create` chunk sizes in the report. Razorpay's script is not bundled. All tests pass.

- [ ] **Step 4: Browser check in Razorpay test mode (Playwright MCP, 375 px)**

Use the `.env` test keys. Save screenshots under `.playwright-mcp/`, which is git-ignored. Run `php artisan queue:work --stop-when-empty` (or the `composer run dev` queue listener) for the emails. Check:
1. Checkout with pincode 796001 shows both payment options.
2. Choosing "Pay online" changes the button to "Continue to payment · ₹…".
3. A bag above the zone's COD limit switches to "Pay online" automatically.
4. Placing the order lands on the signed order page and opens Razorpay Checkout.
5. Pay with Razorpay's test UPI ID `success@razorpay`. The page shows "Payment received…", the status is Confirmed, the payment is Online payment · Paid, and the receipt and admin alert appear in the log.
6. Another order, closing the Checkout window: "Payment not completed…" shows and "Pay ₹…" reopens it.
7. Set that order's `placed_at` 31 minutes back with tinker. `php artisan orders:cancel-abandoned` cancels it and puts the stock back, and its page says it was cancelled because payment wasn't completed.

If Playwright cannot complete the Razorpay test window, list steps 4–7 as manual checks for the owner.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Checkout/Create.vue resources/js/Pages/Orders/Show.vue
git commit -m "feat: pay online at checkout and on the order page"
```

---

## Phase 4 done when

- `php artisan test --compact` is green.
- At 375 px, a guest can choose "Pay online" and pay in Razorpay test mode. They land on a confirmed, paid order page, and the receipt and admin alert are queued.
- Closing Checkout leaves the order payable from the same signed link for 30 minutes. After that, `orders:cancel-abandoned` cancels it and stock returns.
- A Razorpay API failure at checkout leaves no order, no payment row and no stock change.
- The webhook needs a valid signature, is CSRF-exempt and idempotent. It confirms a captured payment, and it records a failed attempt without failing the order.
- A capture after cancellation is refunded automatically. `RefundPayment` refunds a paid order once.

## Owner setup (not code)

- In the Razorpay dashboard (test mode), add a webhook to `https://<public-url>/webhooks/razorpay`:
  - events `payment.captured` and `payment.failed`;
  - secret = `RAZORPAY_WEBHOOK_SECRET`.
- Locally this needs a tunnel (for example `cloudflared tunnel --url http://localhost:8000`). The browser confirmation works without it.
- Keep payment capture on **Automatic** in the dashboard (Settings → Payment capture). Orders confirm on `payment.captured`.
- Production (Phase 6): live keys after KYC; the scheduler (`* * * * * php artisan schedule:run`) so the 5-minute sweep runs.

## Known follow-ups (not Phase 4)

- **Phase 5, admin order screens:**
  - the Cancel button calls `CancelOrder`, then `RefundPayment` for paid online orders;
  - show payment attempts from `payments`;
  - mark COD orders Paid on delivery.
- **Razorpay MCP** for read-only lookups of test payments (spec section 10): vet the repo, then add it to `.mcp.json`.
- **Refund webhooks** (`refund.processed`, `refund.failed`) are not handled. The refund API response is stored as is.
