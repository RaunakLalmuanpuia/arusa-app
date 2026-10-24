# Order Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let guests find their order with order number + phone, add a "Track order" link to the storefront, and show a status timeline on the order page.

**Architecture:** A new `TrackOrderController` validates number + phone and redirects to the existing signed status page (`Order::trackingUrl()`), so no new permission model. `OrderController@show` gains a `timeline` prop built from the existing `order_events` rows. Spec: `docs/superpowers/specs/2026-10-24-order-tracking-design.md`.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v2 + Vue 3, Tailwind 3, Pest.

## Global Constraints

- Phone rule: `/^[6-9]\d{9}$/`, message "Enter a 10-digit mobile number, without +91." (same as `CheckoutRequest`).
- Order number format: `ARU-%06d` of the order id (`Order::number`).
- Lookup failure message, never revealing which field was wrong: "We couldn't find an order with those details."
- `POST /track` is throttled `throttle:5,1`.
- Timeline uses fulfilment status only; consecutive repeats collapse; oldest first; time format `'j M Y, g:i a'`.
- Storefront motion: CSS only, 150–300 ms, honour `prefers-reduced-motion`. No icon or animation packages.
- Reuse store form pieces: `GuestLayout` (`title` prop), `StoreInput`, `InputError`, CSS classes `store-label`, `store-button`, `store-link`.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Work on a feature branch (`feat/order-tracking`), not `master`.

---

### Task 1: Order lookup (number + phone)

**Files:**
- Create: `app/Http/Requests/TrackOrderRequest.php`
- Create: `app/Http/Controllers/TrackOrderController.php`
- Create: `resources/js/Pages/Orders/Track.vue`
- Modify: `routes/web.php` (add `use` + two routes after the `orders.payment.store` line)
- Test: `tests/Feature/Storefront/TrackOrderTest.php`

**Interfaces:**
- Consumes: `Order::trackingUrl(): string`, `Order::number` accessor.
- Produces: route names `orders.track` (GET `/track`) and `orders.track.store` (POST `/track`); `TrackOrderRequest::orderId(): int`; Inertia page `Orders/Track`.

- [ ] **Step 1: Create the branch and scaffold files**

```bash
git checkout -b feat/order-tracking
php artisan make:request TrackOrderRequest --no-interaction
php artisan make:controller TrackOrderController --no-interaction
php artisan make:test --pest Storefront/TrackOrderTest --no-interaction
```

- [ ] **Step 2: Write the failing tests**

Replace `tests/Feature/Storefront/TrackOrderTest.php` with:

```php
<?php

use App\Models\Order;

beforeEach(function () {
    $this->order = Order::factory()->create(['customer_phone' => '9876543210']);
});

test('the track page renders', function () {
    $this->get('/track')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Orders/Track'));
});

test('a matching number and phone open the order page', function (string $number) {
    $this->post('/track', ['number' => $number, 'phone' => '9876543210'])
        ->assertRedirect($this->order->trackingUrl());
})->with([
    'full number' => fn () => $this->order->number,
    'lowercase short' => fn () => 'aru-'.$this->order->id,
    'bare id' => fn () => (string) $this->order->id,
]);

test('a wrong phone or unknown number gets one generic error', function (array $input) {
    $this->from('/track')->post('/track', $input)
        ->assertRedirect('/track')
        ->assertSessionHasErrors(['number' => "We couldn't find an order with those details."]);
})->with([
    'wrong phone' => fn () => ['number' => $this->order->number, 'phone' => '9123456780'],
    'unknown number' => fn () => ['number' => 'ARU-999999', 'phone' => '9876543210'],
]);

test('malformed input is rejected by validation', function () {
    $this->from('/track')->post('/track', ['number' => 'hello', 'phone' => '12345'])
        ->assertRedirect('/track')
        ->assertSessionHasErrors(['number', 'phone']);
});

test('lookups are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/track', ['number' => 'ARU-999999', 'phone' => '9876543210']);
    }

    $this->post('/track', ['number' => $this->order->number, 'phone' => '9876543210'])
        ->assertTooManyRequests();
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Storefront/TrackOrderTest.php`
Expected: FAIL (404 on `/track`).

- [ ] **Step 4: Write the request**

Replace `app/Http/Requests/TrackOrderRequest.php` with:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrackOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accepts "ARU-000123", "aru-123" or "123". Nine digits at most keeps the id inside an int.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'regex:/^(aru-?)?0*[1-9]\d{0,8}$/i'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.regex' => 'Enter your order number, like ARU-000123.',
            'phone.regex' => 'Enter a 10-digit mobile number, without +91.',
        ];
    }

    /**
     * The order id inside the validated order number.
     */
    public function orderId(): int
    {
        return (int) preg_replace('/\D/', '', $this->validated('number'));
    }
}
```

- [ ] **Step 5: Write the controller**

Replace `app/Http/Controllers/TrackOrderController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackOrderRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TrackOrderController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Orders/Track');
    }

    /**
     * Order number + phone is the credential; a match goes to the signed status page.
     */
    public function store(TrackOrderRequest $request): RedirectResponse
    {
        $order = Order::whereKey($request->orderId())
            ->where('customer_phone', $request->validated('phone'))
            ->first();

        if ($order === null) {
            return back()->withErrors(['number' => "We couldn't find an order with those details."])->onlyInput('number', 'phone');
        }

        return redirect()->to($order->trackingUrl());
    }
}
```

- [ ] **Step 6: Add the routes**

In `routes/web.php`, add `use App\Http\Controllers\TrackOrderController;` to the imports (alphabetical, after `SearchController`), and after the `orders.payment.store` line add:

```php
Route::get('/track', [TrackOrderController::class, 'create'])->name('orders.track');
Route::post('/track', [TrackOrderController::class, 'store'])->middleware('throttle:5,1')->name('orders.track.store');
```

- [ ] **Step 7: Write the page**

Create `resources/js/Pages/Orders/Track.vue`:

```vue
<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import StoreInput from '@/Components/Store/StoreInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    number: '',
    phone: '',
});

const submit = () => {
    form.post(route('orders.track.store'));
};
</script>

<template>
    <GuestLayout title="Track your order">
        <Head title="Track your order" />

        <form @submit.prevent="submit">
            <div>
                <label for="number" class="store-label">Order number</label>
                <StoreInput id="number" v-model="form.number" required autofocus autocomplete="off" aria-describedby="number-hint" />
                <p id="number-hint" class="mt-1 text-xs text-brand-600">On your confirmation page and emails, e.g. ARU-000123.</p>
                <InputError class="mt-2" :message="form.errors.number" />
            </div>

            <div class="mt-6">
                <label for="phone" class="store-label">Phone used at checkout</label>
                <StoreInput id="phone" type="tel" inputmode="numeric" v-model="form.phone" required autocomplete="tel-national" maxlength="10" />
                <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <div class="mt-8 flex justify-end">
                <button class="store-button" :disabled="form.processing">Track order</button>
            </div>
        </form>

        <p v-if="$page.props.auth.user" class="mt-10 border-t border-brand-200 pt-6 text-sm text-brand-700">
            Signed in? <Link :href="route('account')" class="store-link">See all your orders in your account</Link>
        </p>
    </GuestLayout>
</template>
```

- [ ] **Step 8: Build and run the tests**

```bash
npm run build
php artisan test --compact tests/Feature/Storefront/TrackOrderTest.php
```

Expected: all PASS.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/TrackOrderRequest.php app/Http/Controllers/TrackOrderController.php routes/web.php resources/js/Pages/Orders/Track.vue tests/Feature/Storefront/TrackOrderTest.php
git commit -m "feat: let guests find their order by number and phone"
```

---

### Task 2: "Track order" links in the store layout

**Files:**
- Modify: `resources/js/Layouts/StoreLayout.vue` (mobile menu `<nav id="mobile-menu">`, footer `<nav aria-label="Footer">`)

**Interfaces:**
- Consumes: route `orders.track` from Task 1.
- Produces: nothing.

Layout-only change; no test (per project test rules).

- [ ] **Step 1: Add the mobile menu link**

In the `<nav id="mobile-menu">`, directly after the `infoLinks` `<Link v-for=...>` block and before the Admin link, add:

```vue
<Link
    :href="route('orders.track')"
    class="flex min-h-11 items-center border-b border-brand-800 text-sm font-medium uppercase tracking-[0.2em] focus-ring-inverse"
    @click="isMenuOpen = false"
>
    Track order
</Link>
```

- [ ] **Step 2: Add the footer link**

In the footer `<nav aria-label="Footer">`, after the `infoLinks` `<Link v-for=...>` block, add:

```vue
<Link
    :href="route('orders.track')"
    class="flex min-h-11 items-center rounded text-xs uppercase tracking-[0.2em] hover:text-brand-50 focus-ring-inverse"
>
    Track order
</Link>
```

- [ ] **Step 3: Build and check in the browser**

Run: `npm run build`. Then with Playwright at 375px and desktop width: open `/`, confirm "Track order" shows in the footer and in the mobile menu, and that it opens `/track`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Layouts/StoreLayout.vue
git commit -m "feat: link to order tracking from the store footer and menu"
```

---

### Task 3: Status timeline on the order page

**Files:**
- Modify: `app/Http/Controllers/OrderController.php` (`show`)
- Modify: `resources/js/Pages/Orders/Show.vue` (props + markup after the status `<dl>`)
- Test: `tests/Feature/Storefront/PlaceOrderTest.php` (add one test after "the order page needs a valid signature")

**Interfaces:**
- Consumes: `Order::events()` (oldest first), `OrderEvent::fulfilment_status` (FulfilmentStatus enum), `OrderEvent::created_at`.
- Produces: Inertia prop `timeline: array<int, array{status: string, at: string}>`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Storefront/PlaceOrderTest.php` (check the existing imports cover `FulfilmentStatus` and `PaymentStatus`; add any missing `use` lines at the top):

```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --compact tests/Feature/Storefront/PlaceOrderTest.php --filter=timeline`
Expected: FAIL (`timeline` prop missing).

- [ ] **Step 3: Pass the timeline from the controller**

In `app/Http/Controllers/OrderController.php`:
- Add `use App\Models\OrderEvent;` to the imports.
- Change `$order->load('items');` to `$order->load(['items', 'events']);`.
- Add this prop to the `Inertia::render` array, right after `'order' => [...],`:

```php
'timeline' => $order->events
    ->reject(fn (OrderEvent $event, int $index): bool => $index > 0 && $order->events[$index - 1]->fulfilment_status === $event->fulfilment_status)
    ->values()
    ->map(fn (OrderEvent $event): array => [
        'status' => $event->fulfilment_status->label(),
        'at' => $event->created_at->format('j M Y, g:i a'),
    ]),
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Storefront/PlaceOrderTest.php`
Expected: all PASS.

- [ ] **Step 5: Render the timeline**

In `resources/js/Pages/Orders/Show.vue`, add to `defineProps`:

```js
timeline: { type: Array, required: true },
```

Then directly after the closing `</dl>` of the Status/Payment/Delivery note block (before the items `<ul>`), add:

```vue
<div class="mt-6 text-sm">
    <h2 class="text-xs uppercase tracking-[0.2em] text-brand-600">Progress</h2>
    <ol class="mt-3 space-y-3 border-l border-brand-300 pl-4">
        <li v-for="(step, index) in timeline" :key="index" class="relative">
            <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full" :class="index === timeline.length - 1 ? 'bg-ink' : 'bg-brand-300'" aria-hidden="true" />
            <p :class="index === timeline.length - 1 ? 'font-medium' : 'text-brand-700'">{{ step.status }}</p>
            <p class="text-xs text-brand-600">{{ step.at }}</p>
        </li>
    </ol>
</div>
```

- [ ] **Step 6: Build and check in the browser**

Run: `npm run build`. With Playwright at 375px, open a seeded order's `trackingUrl()` (get one via `php artisan tinker --execute 'echo App\Models\Order::latest("id")->first()?->trackingUrl();'`) and confirm the Progress list renders with the dots aligned on the line.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/OrderController.php resources/js/Pages/Orders/Show.vue tests/Feature/Storefront/PlaceOrderTest.php
git commit -m "feat: show the order's status timeline to customers"
```

---

### Finish

- [ ] Run `php artisan test --compact tests/Feature/Storefront` and confirm it passes; ask the user to run the full suite.
- [ ] Open a PR from `feat/order-tracking` to `master`.
