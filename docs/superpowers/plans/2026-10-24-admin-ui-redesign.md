# Admin UI Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn every admin screen into a modern, brand-tied workspace (warm neutrals, dense, calm) with status pills, an order stepper and timeline, a revenue chart, toasts, sticky save bars, mobile card tables and live previews, and use no Breeze components in the admin.

**Architecture:** Styling primitives are `.admin-*` classes in `resources/css/app.css` (following the existing `.store-*` convention). Markup or behaviour lives in small components in `resources/js/Components/Admin/`. The backend changes are additive: a `revenue.daily` dashboard prop, `*_value` status keys next to the existing labels, and a JSON `POST admin/pages/preview` endpoint that reuses `Page::renderedBlocks()`. Spec: `docs/superpowers/specs/2026-10-24-admin-ui-redesign-design.md` (read §8 "Refinements found while planning": it overrides earlier sections).

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v2 + Vue 3 (`<script setup>`), Tailwind 3 + `@tailwindcss/forms`, Pest, PostgreSQL.

## Global Constraints

- Work on branch `feat/admin-redesign`, not `master`. Run `git checkout` / `git merge` as separate commands.
- No new npm or composer dependencies. Icons are inline SVG components in `resources/js/Components/Icons/` (stroke `currentColor`, `viewBox="0 0 24 24"`, `stroke-width="1.5"`, `aria-hidden="true"`). Never add Lucide or any icon or animation package.
- No admin file (`resources/js/Pages/Admin/**`, `resources/js/Components/Admin/**`, `resources/js/Layouts/AdminLayout.vue`) may import `TextInput`, `InputLabel`, `InputError`, `PrimaryButton`, `Checkbox` or `ApplicationLogo`. Do not delete those files: the storefront and auth pages still use them.
- Colours: `brand-50…900` and `ink` from `tailwind.config.js`. Page background `brand-50`, cards white with a `brand-200` border, text `ink`, secondary text `brand-500/600`. Status tones: amber, sky, violet, emerald, red (Tailwind defaults). No indigo, and no `gray-*` in admin files.
- Fonts: Inter (`font-sans`) everywhere; `font-display` only on the dashboard greeting.
- Motion: CSS transitions or Vue `<Transition>`/`<TransitionGroup>`, 150–200 ms, opacity/transform/colour only. Every animated element also gets `motion-reduce:transition-none` (or `motion-reduce:animate-none`).
- Keep `focus-ring` on every interactive element you restyle. Keep all existing `aria-label`s.
- Behaviour is unchanged: form fields, `v-model`s, submit handlers, routes, `window.confirm` calls and payload transforms stay exactly as they are unless a step says otherwise.
- Money is integer paise. Format only with `formatPaise` from `@/money`; convert rupee inputs with `toPaise`.
- Vue components have a single root element.
- PHP: run `vendor/bin/pint --dirty --format agent` after any PHP change. Use curly braces, typed params and returns, PHPDoc over inline comments.
- Styling-only tasks get no new Pest tests. They run the existing admin suites named in the task plus `npm run build`.
- Never run `migrate:fresh` (the local DB has real orders). Never delete `public/hot`.

---

### Task 1: Dashboard daily revenue and status values for badges

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `app/Http/Controllers/Admin/OrderController.php` (index row map ~line 61–71, show props ~line 89–130)
- Test: `tests/Feature/Admin/DashboardTest.php`, `tests/Feature/Admin/OrderListTest.php`, `tests/Feature/Admin/OrderWorkflowTest.php`

**Interfaces:**
- Produces (Inertia props used by Tasks 6 and 7):
  - Dashboard: `revenue.daily: Array<{date: 'Y-m-d', label: 'j M', value: int}>` (30 entries, oldest first); `today.orders[].status_value: string`.
  - Orders index rows: `status_value`, `payment_status_value`, `payment_method_label`, `payment_status_label` (existing `payment` and `status` keys stay).
  - Order show: `order.fulfilment_status_value`, `order.payment_status_value`, `payments[].status_value`, `events[].fulfilment_status_value`, `events[].payment_status_value`.

- [ ] **Step 1: Create the branch**

```bash
git checkout -b feat/admin-redesign
```

- [ ] **Step 2: Write the failing tests**

Append to `tests/Feature/Admin/DashboardTest.php`:

```php
test('daily revenue lists the last 30 days oldest first with empty days as zero', function () {
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 10000]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 5000]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 20000, 'placed_at' => now()->subDays(29)]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'total' => 40000, 'placed_at' => now()->subDays(30)]);
    Order::factory()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Cancelled, 'total' => 80000]);
    Order::factory()->create(['total' => 160000]);

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->has('revenue.daily', 30)
            ->where('revenue.daily.0', [
                'date' => today()->subDays(29)->format('Y-m-d'),
                'label' => today()->subDays(29)->format('j M'),
                'value' => 20000,
            ])
            ->where('revenue.daily.1.value', 0)
            ->where('revenue.daily.29.date', today()->format('Y-m-d'))
            ->where('revenue.daily.29.value', 15000));
});
```

In the same file, replace the existing `the dashboard shows today's orders` test with:

```php
test('the dashboard shows today\'s orders', function () {
    $today = Order::factory()->create();
    Order::factory()->create(['placed_at' => now()->subDays(2)]);

    $this->actingAs($this->admin)->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('today.count', 1)
            ->where('today.orders.0.id', $today->id)
            ->where('today.orders.0.status_value', $today->fulfilment_status->value));
});
```

Append to `tests/Feature/Admin/OrderListTest.php`:

```php
test('each listed order carries its status values for badges', function () {
    Order::factory()->razorpay()->create(['payment_status' => PaymentStatus::Paid, 'fulfilment_status' => FulfilmentStatus::Packed]);

    $this->actingAs($this->admin)->get(route('admin.orders.index'))
        ->assertInertia(fn ($page) => $page
            ->where('orders.data.0.status_value', 'packed')
            ->where('orders.data.0.payment_status_value', 'paid')
            ->where('orders.data.0.payment_status_label', PaymentStatus::Paid->label())
            ->where('orders.data.0.payment_method_label', PaymentMethod::Razorpay->label()));
});
```

Add `use App\Enums\PaymentMethod;` to that file's imports.

Append to `tests/Feature/Admin/OrderWorkflowTest.php`:

```php
test('the detail page carries status values for badges', function () {
    $order = paidOnlineOrder();

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn ($page) => $page
            ->where('order.fulfilment_status_value', 'confirmed')
            ->where('order.payment_status_value', 'paid')
            ->where('payments.0.status_value', 'paid')
            ->has('events.0.fulfilment_status_value')
            ->has('events.0.payment_status_value'));
});
```

- [ ] **Step 3: Run the tests and confirm they fail**

Run: `php artisan test --compact tests/Feature/Admin/DashboardTest.php tests/Feature/Admin/OrderListTest.php tests/Feature/Admin/OrderWorkflowTest.php`
Expected: the four new or extended assertions FAIL (missing `revenue.daily`, `status_value` and so on). Everything else passes.

- [ ] **Step 4: Implement the dashboard changes**

In `DashboardController::__invoke`, add `'status_value'` to the today's orders map:

```php
                'orders' => $todayOrders->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'customer' => $order->customer_name,
                    'total' => $order->total,
                    'status' => $order->fulfilment_status->label(),
                    'status_value' => $order->fulfilment_status->value,
                ]),
```

Change the `revenue` prop to:

```php
            'revenue' => [
                'last_7_days' => $this->revenueSince(6),
                'last_30_days' => $this->revenueSince(29),
                'daily' => $this->dailyRevenue(30),
            ],
```

Add this method under `revenueSince`:

```php
    /**
     * Money received per day for the last $days days, oldest first, with empty days as zero.
     *
     * @return list<array{date: string, label: string, value: int}>
     */
    private function dailyRevenue(int $days): array
    {
        $totals = Order::where('payment_status', PaymentStatus::Paid)
            ->where('fulfilment_status', '!=', FulfilmentStatus::Cancelled)
            ->where('placed_at', '>=', today()->subDays($days - 1))
            ->selectRaw('DATE(placed_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range($days - 1, 0))
            ->map(function (int $daysAgo) use ($totals): array {
                $day = today()->subDays($daysAgo);

                return [
                    'date' => $day->format('Y-m-d'),
                    'label' => $day->format('j M'),
                    'value' => (int) ($totals[$day->format('Y-m-d')] ?? 0),
                ];
            })
            ->all();
    }
```

- [ ] **Step 5: Implement the order status values**

In `OrderController::index`, replace the `->through(...)` row array with:

```php
                ->through(fn (Order $order): array => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'placed_at' => $order->placed_at->format('j M, g:i a'),
                    'customer' => $order->customer_name,
                    'phone' => $order->customer_phone,
                    'zone' => $order->shippingZone->name,
                    'total' => $order->total,
                    'payment' => "{$order->payment_method->label()} · {$order->payment_status->label()}",
                    'payment_method_label' => $order->payment_method->label(),
                    'payment_status_label' => $order->payment_status->label(),
                    'payment_status_value' => $order->payment_status->value,
                    'status' => $order->fulfilment_status->label(),
                    'status_value' => $order->fulfilment_status->value,
                ]),
```

In `OrderController::show`:
- In `'order' => [...]`, add after `'payment_status' => …` and `'fulfilment_status' => …`:

```php
                'payment_status_value' => $order->payment_status->value,
                'fulfilment_status_value' => $order->fulfilment_status->value,
```

- In the `payments` map, add after `'status' => $payment->status->label(),`:

```php
                'status_value' => $payment->status->value,
```

- In the `events` map, add after `'payment_status' => $event->payment_status->label(),`:

```php
                'fulfilment_status_value' => $event->fulfilment_status->value,
                'payment_status_value' => $event->payment_status->value,
```

- [ ] **Step 6: Run the tests and confirm they pass**

Run: `vendor/bin/pint --dirty --format agent`
Run: `php artisan test --compact tests/Feature/Admin/DashboardTest.php tests/Feature/Admin/OrderListTest.php tests/Feature/Admin/OrderWorkflowTest.php`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php app/Http/Controllers/Admin/OrderController.php tests/Feature/Admin/DashboardTest.php tests/Feature/Admin/OrderListTest.php tests/Feature/Admin/OrderWorkflowTest.php
git commit -m "feat: dashboard daily revenue and order status values for admin badges"
```

---

### Task 2: Page preview endpoint

**Files:**
- Create: `app/Http/Requests/Admin/PagePreviewRequest.php`
- Create: `app/Http/Controllers/Admin/PagePreviewController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/PagePreviewTest.php`

**Interfaces:**
- Produces: route `admin.pages.preview` (`POST /admin/pages/preview`). Request JSON `{ blocks: Array<{type, data}> }` (no `uid`). It responds `200 { blocks: Array<{type, data}> }` in the storefront's rendered shape (Markdown → `html`, image paths → `{src, srcset}`, contact `href`), or `422 { message, errors: { 'blocks.0.data.markdown': [..] } }` with the same keys as saving. Task 10 consumes it.

- [ ] **Step 1: Scaffold the files**

```bash
php artisan make:request Admin/PagePreviewRequest --no-interaction
php artisan make:controller Admin/PagePreviewController --invokable --no-interaction
php artisan make:test Admin/PagePreviewTest --pest --no-interaction
```

- [ ] **Step 2: Write the failing tests**

Replace `tests/Feature/Admin/PagePreviewTest.php` with:

```php
<?php

use App\Models\Page;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->blocks = [['type' => 'text', 'data' => ['markdown' => "## Sizes\n\n<script>alert(1)</script>"]]];
});

test('admin previews unsaved blocks rendered like the storefront', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.pages.preview'), ['blocks' => $this->blocks])
        ->assertOk()
        ->assertJsonPath('blocks.0.type', 'text')
        ->assertJsonPath('blocks.0.data.html', fn (string $html) => str_contains($html, '<h2>Sizes</h2>') && ! str_contains($html, '<script>'));

    expect(Page::count())->toBe(0);
});

test('an invalid block is rejected with the same error key as saving', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.pages.preview'), ['blocks' => [['type' => 'text', 'data' => ['markdown' => '']]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks.0.data.markdown');
});

test('only admins can preview pages', function () {
    $this->post(route('admin.pages.preview'), ['blocks' => $this->blocks])->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.pages.preview'), ['blocks' => $this->blocks])
        ->assertForbidden();
});
```

- [ ] **Step 3: Run the tests and confirm they fail**

Run: `php artisan test --compact tests/Feature/Admin/PagePreviewTest.php`
Expected: FAIL with `Route [admin.pages.preview] not defined.`

- [ ] **Step 4: Implement the request**

Replace `app/Http/Requests/Admin/PagePreviewRequest.php` with:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class PagePreviewRequest extends PageRequest
{
    /**
     * Saving's block rules without the page's own title and slug.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['title', 'slug']);
    }
}
```

(`authorize()`, `validated()` and `attributes()` are inherited from `PageRequest`.)

- [ ] **Step 5: Implement the controller**

Replace `app/Http/Controllers/Admin/PagePreviewController.php` with:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PagePreviewRequest;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class PagePreviewController extends Controller
{
    /**
     * Render unsaved blocks the way the storefront shows them, without saving anything.
     */
    public function __invoke(PagePreviewRequest $request): JsonResponse
    {
        return response()->json([
            'blocks' => (new Page(['blocks' => $request->validated('blocks')]))->renderedBlocks(),
        ]);
    }
}
```

- [ ] **Step 6: Register the route**

In `routes/admin.php`, add `use App\Http\Controllers\Admin\PagePreviewController;` to the imports (alphabetical, after `PageImageController`). Add this line directly **above** `Route::resource('pages', PageController::class)->except('show');`:

```php
        Route::post('pages/preview', PagePreviewController::class)->name('pages.preview');
```

- [ ] **Step 7: Run the tests and confirm they pass**

Run: `vendor/bin/pint --dirty --format agent`
Run: `php artisan test --compact tests/Feature/Admin/PagePreviewTest.php tests/Feature/Admin/PageManagementTest.php tests/Feature/Admin/PageBlocksTest.php`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Admin/PagePreviewRequest.php app/Http/Controllers/Admin/PagePreviewController.php routes/admin.php tests/Feature/Admin/PagePreviewTest.php
git commit -m "feat: admin page preview endpoint renders unsaved blocks"
```

---

### Task 3: Admin design foundations (classes, icons, core components, rule update)

**Files:**
- Modify: `resources/css/app.css` (inside the existing `@layer components { … }`)
- Modify: `resources/js/Pages/Admin/Pages/Blocks/fieldOptions.js` (first three exports)
- Create icons in `resources/js/Components/Icons/`: `IconHome.vue`, `IconTag.vue`, `IconBox.vue`, `IconTruck.vue`, `IconDocument.vue`, `IconSliders.vue`, `IconExternal.vue`, `IconLogout.vue`, `IconCheck.vue`, `IconAlert.vue`, `IconPlus.vue`
- Create: `resources/js/Components/Admin/FormField.vue`, `FormSection.vue`, `PageHeader.vue`, `StatusBadge.vue`
- Modify: `.claude/skills/arusa-commerce/SKILL.md` (Admin section)

**Interfaces:**
- Produces CSS classes: `.admin-card`, `.admin-label`, `.admin-field`, `.admin-checkbox`, `.admin-btn`, `.admin-btn-secondary`, `.admin-btn-danger`.
- `FormField` props `{ label: String (req), for: String (req), error: String|null, hint: String|null }`, default slot = the control.
- `FormSection` props `{ title: String (req), description: String|null }`, default slot = fields.
- `PageHeader` props `{ title: String (req), back: {label, href}|null, meta: String|null }`, slots `badges`, `actions`.
- `StatusBadge` props `{ value: String (req), label: String (req) }`. Tones by value: amber `pending|awaiting_payment`, sky `confirmed|packed`, violet `dispatched`, emerald `delivered|paid|active`, red `cancelled|failed`, neutral otherwise.
- Icons: each a single-root `<svg>`; size them with a class such as `class="h-5 w-5"`.

- [ ] **Step 1: Add the CSS classes**

In `resources/css/app.css`, inside `@layer components { … }` after the `.store-link` line, add:

```css
    .admin-card { @apply rounded-xl border border-brand-200 bg-white; }
    .admin-label { @apply block text-sm font-medium text-ink; }
    .admin-field { @apply block w-full rounded-lg border-brand-300 bg-white text-sm text-ink placeholder:text-brand-400 focus:border-ink focus:ring-ink disabled:bg-brand-50; }
    .admin-checkbox { @apply h-4 w-4 rounded border-brand-300 text-ink focus:ring-ink; }
    .admin-btn { @apply inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-ink px-4 text-sm font-medium text-brand-50 transition-colors duration-150 hover:bg-brand-800 disabled:pointer-events-none disabled:opacity-50 motion-reduce:transition-none focus-ring; }
    .admin-btn-secondary { @apply inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-brand-300 bg-white px-4 text-sm font-medium text-ink transition-colors duration-150 hover:bg-brand-50 disabled:pointer-events-none disabled:opacity-50 motion-reduce:transition-none focus-ring; }
    .admin-btn-danger { @apply inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium text-red-700 transition-colors duration-150 hover:bg-red-50 disabled:pointer-events-none disabled:opacity-50 motion-reduce:transition-none focus-ring; }
```

- [ ] **Step 2: Point the block editor class constants at them**

In `resources/js/Pages/Admin/Pages/Blocks/fieldOptions.js`, replace the first three exports with:

```js
export const fieldClass = 'admin-field';

export const smallButton = 'admin-btn-secondary min-h-8 px-3';

export const removeButton = 'admin-btn-danger min-h-8 px-2';
```

- [ ] **Step 3: Add the icons**

Each file is the template below with its own path(s). Template (`IconHome.vue` shown):

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
    </svg>
</template>
```

`d` values for the others (one `<path>` each unless two are listed):
- `IconTag.vue`: `M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z` and a second path `M6 6h.008v.008H6V6Z`
- `IconBox.vue`: `m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9`
- `IconTruck.vue`: `M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12`
- `IconDocument.vue`: `M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z`
- `IconSliders.vue`: `M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75`
- `IconExternal.vue`: `M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25`
- `IconLogout.vue`: `M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9`
- `IconCheck.vue`: `m4.5 12.75 6 6 9-13.5`
- `IconAlert.vue`: `M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z`
- `IconPlus.vue`: `M12 4.5v15m7.5-7.5h-15`

- [ ] **Step 4: Create `FormField.vue`**

`resources/js/Components/Admin/FormField.vue`:

```vue
<script setup>
defineProps({
    label: { type: String, required: true },
    for: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
});
</script>

<template>
    <div>
        <label :for="$props.for" class="admin-label">{{ label }}</label>
        <div class="mt-1.5">
            <slot />
        </div>
        <p v-if="hint" class="mt-1.5 text-xs text-brand-500">{{ hint }}</p>
        <p v-if="error" :id="`${$props.for}-error`" class="mt-1.5 text-sm text-red-700">{{ error }}</p>
    </div>
</template>
```

- [ ] **Step 5: Create `FormSection.vue`**

`resources/js/Components/Admin/FormSection.vue`:

```vue
<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <section class="grid gap-5 p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
        <header>
            <h2 class="font-medium text-ink">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-brand-600">{{ description }}</p>
        </header>
        <div class="min-w-0 space-y-5">
            <slot />
        </div>
    </section>
</template>
```

- [ ] **Step 6: Create `PageHeader.vue`**

`resources/js/Components/Admin/PageHeader.vue`:

```vue
<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    back: { type: Object, default: null },
    meta: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <Link v-if="back" :href="back.href" class="focus-ring rounded text-sm text-brand-600 hover:text-ink">← {{ back.label }}</Link>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ title }}</h1>
                <slot name="badges" />
            </div>
            <p v-if="meta" class="mt-1 text-sm text-brand-600">{{ meta }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
```

- [ ] **Step 7: Create `StatusBadge.vue`**

`resources/js/Components/Admin/StatusBadge.vue`:

```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: String, required: true },
    label: { type: String, required: true },
});

const amber = 'bg-amber-50 text-amber-800 ring-amber-200';
const sky = 'bg-sky-50 text-sky-800 ring-sky-200';
const emerald = 'bg-emerald-50 text-emerald-800 ring-emerald-200';
const red = 'bg-red-50 text-red-800 ring-red-200';

// Keys are FulfilmentStatus and PaymentStatus values, plus "active" for visibility columns.
const tones = {
    pending: amber,
    awaiting_payment: amber,
    confirmed: sky,
    packed: sky,
    dispatched: 'bg-violet-50 text-violet-800 ring-violet-200',
    delivered: emerald,
    paid: emerald,
    active: emerald,
    cancelled: red,
    failed: red,
};

const tone = computed(() => tones[props.value] ?? 'bg-brand-100 text-brand-700 ring-brand-200');
</script>

<template>
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="tone">
        <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ label }}
    </span>
</template>
```

- [ ] **Step 8: Update the project rule**

In `.claude/skills/arusa-commerce/SKILL.md`, under `## Admin`, replace the line

`- Plain and functional. Reuse shared admin components; no design-skill passes, no decorative motion.`

with

`- Brand-tied, modern workspace (spec: `docs/superpowers/specs/2026-10-24-admin-ui-redesign-design.md`). Reuse `Components/Admin/*` and the `.admin-*` classes in `app.css`; no Breeze components in admin. Same motion rules as the storefront.`

- [ ] **Step 9: Build**

Run: `npm run build`
Expected: build succeeds with no errors. (Nothing uses the new components yet; the block editors pick up the new classes.)

- [ ] **Step 10: Commit**

```bash
git add resources/css/app.css resources/js/Pages/Admin/Pages/Blocks/fieldOptions.js resources/js/Components/Icons resources/js/Components/Admin/FormField.vue resources/js/Components/Admin/FormSection.vue resources/js/Components/Admin/PageHeader.vue resources/js/Components/Admin/StatusBadge.vue .claude/skills/arusa-commerce/SKILL.md
git commit -m "feat: admin design foundations: classes, icons, form field, header and status badge"
```

---

### Task 4: Admin shell (sidebar, mobile drawer) and toasts

**Files:**
- Create: `resources/js/Components/Admin/toasts.js`
- Create: `resources/js/Components/Admin/Toasts.vue`
- Modify (rewrite): `resources/js/Layouts/AdminLayout.vue`

**Interfaces:**
- Consumes: icons from Task 3 (`IconHome`, `IconBox`, `IconTag`, `IconTruck`, `IconDocument`, `IconSliders`, `IconExternal`, `IconLogout`, `IconCheck`, `IconAlert`), plus the existing `IconBag`, `IconMenu`, `IconClose`.
- Produces: `AdminLayout` keeps its `header` slot and default slot (every page already uses them). `toasts.js` exports `toasts` (reactive array of `{id, message, tone: 'success'|'error'}`), `dismissToast(id)`, `pauseToast(id)`, `resumeToast(id)`, `readInitialPage(props)`.

- [ ] **Step 1: Create the toast store**

`resources/js/Components/Admin/toasts.js`:

```js
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

// Error keys controllers use for whole-page messages; none of them is a form field name.
const toastErrorKeys = ['status', 'order', 'payment', 'category', 'product', 'zone', 'search', 'from', 'to', 'payment_method', 'shipping_zone_id'];

const timers = new Map();
let nextId = 0;
let hasReadInitialPage = false;

export const toasts = reactive([]);

export const dismissToast = (id) => {
    clearTimeout(timers.get(id));
    timers.delete(id);
    const index = toasts.findIndex((toast) => toast.id === id);

    if (index !== -1) {
        toasts.splice(index, 1);
    }
};

export const pauseToast = (id) => clearTimeout(timers.get(id));

export const resumeToast = (id) => {
    clearTimeout(timers.get(id));
    timers.set(id, setTimeout(() => dismissToast(id), 5000));
};

const push = (message, tone) => {
    if (toasts.some((toast) => toast.message === message)) {
        return;
    }

    const id = nextId++;
    toasts.push({ id, message, tone });
    resumeToast(id);
};

const pushErrors = (errors) => {
    Object.entries(errors ?? {})
        .filter(([key]) => toastErrorKeys.includes(key))
        .forEach(([, message]) => push(message, 'error'));
};

const isAdminPath = () => window.location.pathname.startsWith('/admin');

// AdminLayout remounts on every visit, so toasts live here and listen to the router once.
router.on('success', (event) => {
    const status = event.detail.page.props.flash?.status;

    if (isAdminPath() && status) {
        push(status, 'success');
    }
});

router.on('error', (event) => {
    if (isAdminPath()) {
        pushErrors(event.detail.errors);
    }
});

/**
 * Show the flash and errors of a full page load, which fires no router event.
 */
export const readInitialPage = (props) => {
    if (hasReadInitialPage) {
        return;
    }

    hasReadInitialPage = true;

    if (props.flash?.status) {
        push(props.flash.status, 'success');
    }

    pushErrors(props.errors);
};
```

- [ ] **Step 2: Create `Toasts.vue`**

`resources/js/Components/Admin/Toasts.vue`:

```vue
<script setup>
import { onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import IconAlert from '@/Components/Icons/IconAlert.vue';
import IconCheck from '@/Components/Icons/IconCheck.vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import { dismissToast, pauseToast, readInitialPage, resumeToast, toasts } from './toasts.js';

const page = usePage();

onMounted(() => readInitialPage(page.props));
</script>

<template>
    <div class="pointer-events-none fixed inset-x-4 bottom-24 z-[60] flex flex-col items-center gap-2 md:inset-x-auto md:bottom-6 md:right-6 md:items-end" aria-live="polite">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :role="toast.tone === 'error' ? 'alert' : 'status'"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm text-ink shadow-lg shadow-ink/5"
                :class="toast.tone === 'error' ? 'border-red-200' : 'border-brand-200'"
                @mouseenter="pauseToast(toast.id)"
                @mouseleave="resumeToast(toast.id)"
                @focusin="pauseToast(toast.id)"
                @focusout="resumeToast(toast.id)"
            >
                <IconAlert v-if="toast.tone === 'error'" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <IconCheck v-else class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <p class="flex-1">{{ toast.message }}</p>
                <button type="button" class="focus-ring -m-1 rounded p-1 text-brand-500 hover:text-ink" aria-label="Dismiss" @click="dismissToast(toast.id)">
                    <IconClose class="h-4 w-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
```

- [ ] **Step 3: Rewrite `AdminLayout.vue`**

Replace `resources/js/Layouts/AdminLayout.vue` with:

```vue
<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Toasts from '@/Components/Admin/Toasts.vue';
import IconBag from '@/Components/Icons/IconBag.vue';
import IconBox from '@/Components/Icons/IconBox.vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import IconDocument from '@/Components/Icons/IconDocument.vue';
import IconExternal from '@/Components/Icons/IconExternal.vue';
import IconHome from '@/Components/Icons/IconHome.vue';
import IconLogout from '@/Components/Icons/IconLogout.vue';
import IconMenu from '@/Components/Icons/IconMenu.vue';
import IconSliders from '@/Components/Icons/IconSliders.vue';
import IconTag from '@/Components/Icons/IconTag.vue';
import IconTruck from '@/Components/Icons/IconTruck.vue';
import logo256 from '../../images/logo-256.jpg';
import logo512 from '../../images/logo-512.jpg';

const isDrawerOpen = ref(false);
const menuButton = ref(null);
const nav = ref(null);

const links = [
    { label: 'Dashboard', route: 'admin.dashboard', match: 'admin.dashboard', icon: IconHome },
    { label: 'Orders', route: 'admin.orders.index', match: 'admin.orders.*', icon: IconBag },
    { label: 'Categories', route: 'admin.categories.index', match: 'admin.categories.*', icon: IconTag },
    { label: 'Products', route: 'admin.products.index', match: 'admin.products.*', icon: IconBox },
    { label: 'Shipping zones', route: 'admin.shipping-zones.index', match: 'admin.shipping-zones.*', icon: IconTruck },
    { label: 'Pages', route: 'admin.pages.index', match: 'admin.pages.*', icon: IconDocument },
    { label: 'Settings', route: 'admin.settings.edit', match: 'admin.settings.*', icon: IconSliders },
];

const linkClass = 'focus-ring flex items-center gap-3 rounded-lg border px-3 py-2 text-sm font-medium transition-colors duration-150 motion-reduce:transition-none';

const openDrawer = async () => {
    isDrawerOpen.value = true;
    await nextTick();
    nav.value?.querySelector('a')?.focus();
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    menuButton.value?.focus();
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && isDrawerOpen.value) {
        closeDrawer();
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="min-h-screen bg-brand-50 font-sans text-ink antialiased md:flex">
        <header class="sticky top-0 z-30 flex items-center justify-between border-b border-brand-200 bg-brand-50/90 px-4 py-2 backdrop-blur md:hidden">
            <Link :href="route('admin.dashboard')" class="focus-ring flex items-center gap-2 rounded font-semibold">
                <!-- JPG has a white background: multiply blends it into the cream surface -->
                <img :src="logo256" :srcset="`${logo256} 256w, ${logo512} 512w`" sizes="32px" width="256" height="256" alt="" class="h-8 w-8 mix-blend-multiply" />
                Arusa
                <span class="text-xs font-normal text-brand-500">Admin</span>
            </Link>
            <button
                ref="menuButton"
                type="button"
                class="focus-ring rounded-lg p-2 text-brand-700 hover:bg-white"
                aria-label="Open menu"
                aria-controls="admin-sidebar"
                :aria-expanded="isDrawerOpen"
                @click="openDrawer"
            >
                <IconMenu class="h-6 w-6" />
            </button>
        </header>

        <Transition
            enter-active-class="transition-opacity duration-200 motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div v-if="isDrawerOpen" class="fixed inset-0 z-40 bg-ink/30 md:hidden" aria-hidden="true" @click="closeDrawer" />
        </Transition>

        <aside
            id="admin-sidebar"
            :role="isDrawerOpen ? 'dialog' : null"
            :aria-modal="isDrawerOpen ? 'true' : null"
            aria-label="Admin menu"
            :class="isDrawerOpen ? 'visible translate-x-0' : 'invisible -translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-brand-200 bg-brand-50 transition-[transform,visibility] duration-200 ease-out motion-reduce:transition-none md:visible md:sticky md:top-0 md:z-auto md:h-screen md:w-60 md:translate-x-0"
        >
            <div class="flex items-center justify-between px-5 py-5">
                <Link :href="route('admin.dashboard')" class="focus-ring flex items-center gap-3 rounded">
                    <img :src="logo256" :srcset="`${logo256} 256w, ${logo512} 512w`" sizes="36px" width="256" height="256" alt="" class="h-9 w-9 mix-blend-multiply" />
                    <span class="leading-tight">
                        <span class="block font-semibold">Arusa</span>
                        <span class="block text-xs text-brand-500">Admin</span>
                    </span>
                </Link>
                <button type="button" class="focus-ring rounded-lg p-2 text-brand-700 hover:bg-white md:hidden" aria-label="Close menu" @click="closeDrawer">
                    <IconClose class="h-5 w-5" />
                </button>
            </div>

            <nav ref="nav" class="flex-1 space-y-0.5 overflow-y-auto px-3" aria-label="Admin">
                <Link
                    v-for="link in links"
                    :key="link.route"
                    :href="route(link.route)"
                    :aria-current="route().current(link.match) ? 'page' : null"
                    :class="[linkClass, route().current(link.match) ? 'border-brand-200 bg-white text-ink shadow-sm' : 'border-transparent text-brand-600 hover:bg-white/60 hover:text-ink']"
                >
                    <component :is="link.icon" class="h-5 w-5 shrink-0" />
                    {{ link.label }}
                </Link>
            </nav>

            <div class="space-y-0.5 border-t border-brand-200 px-3 py-4">
                <a href="/" target="_blank" rel="noopener" :class="[linkClass, 'border-transparent text-brand-600 hover:bg-white/60 hover:text-ink']">
                    <IconExternal class="h-5 w-5 shrink-0" />
                    View store
                </a>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    :class="[linkClass, 'w-full border-transparent text-left text-brand-600 hover:bg-white/60 hover:text-ink']"
                >
                    <IconLogout class="h-5 w-5 shrink-0" />
                    Log out
                </Link>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="mx-auto max-w-6xl px-4 py-6 md:px-8 md:py-8">
                <header v-if="$slots.header" class="mb-6">
                    <slot name="header" />
                </header>
                <slot />
            </div>
        </main>

        <Toasts />
    </div>
</template>
```

(The drawer closes on navigation by itself: every page renders its own `AdminLayout`, so it remounts with `isDrawerOpen = false`.)

- [ ] **Step 4: Build and run the admin suites**

Run: `npm run build`
Expected: success.
Run: `php artisan test --compact tests/Feature/Admin`
Expected: all PASS (no backend change; this guards against a broken shared prop).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Admin/toasts.js resources/js/Components/Admin/Toasts.vue resources/js/Layouts/AdminLayout.vue
git commit -m "feat: admin shell with icon sidebar, mobile drawer and toasts"
```

---

### Task 5: AdminTable with mobile cards, and every index page

**Files:**
- Modify (rewrite): `resources/js/Components/Admin/AdminTable.vue`
- Modify (rewrite): `resources/js/Pages/Admin/Orders/Index.vue`, `Categories/Index.vue`, `Products/Index.vue`, `ShippingZones/Index.vue`, `Pages/Index.vue`

**Interfaces:**
- Consumes: `PageHeader`, `StatusBadge`, `IconPlus`, `IconSliders` (Task 3); order row keys `status_value`, `payment_status_value`, `payment_method_label`, `payment_status_label` (Task 1).
- Produces: `AdminTable` with the same props (`rows`, `columns`, `filters`) and slots (`filters` with `{ query }`, `cell-{key}` with `{ row }`, `empty`) as today.

- [ ] **Step 1: Rewrite `AdminTable.vue`**

```vue
<script setup>
import { reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import IconSearch from '@/Components/Icons/IconSearch.vue';
import IconSliders from '@/Components/Icons/IconSliders.vue';

const props = defineProps({
    rows: { type: Object, required: true },
    columns: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const query = reactive({ search: '', ...props.filters });
const areFiltersOpen = ref(false);

const apply = () => {
    router.get(window.location.pathname, query, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="space-y-4">
        <form class="flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <div class="relative w-full sm:w-72">
                <IconSearch class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-400" />
                <input v-model="query.search" type="search" placeholder="Search…" aria-label="Search" class="admin-field pl-9" />
            </div>
            <button
                v-if="$slots.filters"
                type="button"
                class="admin-btn-secondary md:hidden"
                aria-controls="table-filters"
                :aria-expanded="areFiltersOpen"
                @click="areFiltersOpen = !areFiltersOpen"
            >
                <IconSliders class="h-4 w-4" />
                Filters
            </button>
            <div v-if="$slots.filters" id="table-filters" :class="areFiltersOpen ? 'flex' : 'hidden'" class="w-full flex-wrap items-center gap-2 md:flex md:w-auto">
                <slot name="filters" :query="query" />
            </div>
            <button type="submit" class="admin-btn">Apply</button>
        </form>

        <template v-if="rows.data.length">
            <div class="admin-card hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-brand-200 bg-brand-50/60 text-xs uppercase tracking-wide text-brand-500">
                        <tr>
                            <th v-for="column in columns" :key="column.key" scope="col" class="px-4 py-3 font-medium">{{ column.label }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-100">
                        <tr v-for="row in rows.data" :key="row.id" class="transition-colors duration-150 hover:bg-brand-50/60 motion-reduce:transition-none">
                            <td v-for="column in columns" :key="column.key" class="px-4 py-3 align-middle">
                                <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] }}</slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="space-y-3 md:hidden">
                <li v-for="row in rows.data" :key="row.id" class="admin-card p-4">
                    <div class="font-medium">
                        <slot :name="`cell-${columns[0].key}`" :row="row">{{ row[columns[0].key] }}</slot>
                    </div>
                    <dl class="mt-3 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                        <template v-for="column in columns.slice(1)" :key="column.key">
                            <dt class="text-brand-500">{{ column.label }}</dt>
                            <dd>
                                <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] }}</slot>
                            </dd>
                        </template>
                    </dl>
                </li>
            </ul>
        </template>
        <div v-else class="admin-card px-4 py-12 text-center text-sm text-brand-500">
            <slot name="empty">Nothing found.</slot>
        </div>

        <nav v-if="rows.total > 0" class="flex items-center justify-between text-sm text-brand-600" aria-label="Pagination">
            <span class="tabular-nums">{{ rows.from }}–{{ rows.to }} of {{ rows.total }}</span>
            <div class="flex gap-2">
                <Link v-if="rows.prev_page_url" :href="rows.prev_page_url" preserve-state class="admin-btn-secondary min-h-9">Previous</Link>
                <Link v-if="rows.next_page_url" :href="rows.next_page_url" preserve-state class="admin-btn-secondary min-h-9">Next</Link>
            </div>
        </nav>
    </div>
</template>
```

- [ ] **Step 2: Rewrite `Orders/Index.vue`**

```vue
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
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

const fieldClass = 'admin-field md:w-auto';
</script>

<template>
    <Head title="Orders" />

    <AdminLayout>
        <template #header>
            <PageHeader title="Orders" />
        </template>

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
                <label class="flex items-center gap-2 text-sm text-brand-600">
                    From
                    <input v-model="query.from" type="date" class="admin-field w-auto" />
                </label>
                <label class="flex items-center gap-2 text-sm text-brand-600">
                    To
                    <input v-model="query.to" type="date" class="admin-field w-auto" />
                </label>
            </template>
            <template #cell-number="{ row }">
                <Link :href="route('admin.orders.show', row.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ row.number }}</Link>
            </template>
            <template #cell-placed_at="{ row }">
                <span class="whitespace-nowrap text-brand-700">{{ row.placed_at }}</span>
            </template>
            <template #cell-customer="{ row }">
                {{ row.customer }}
                <span class="block text-xs text-brand-500">{{ row.phone }}</span>
            </template>
            <template #cell-total="{ row }">
                <span class="tabular-nums">{{ formatPaise(row.total) }}</span>
            </template>
            <template #cell-payment="{ row }">
                <span class="flex flex-wrap items-center gap-2">
                    <span class="text-brand-700">{{ row.payment_method_label }}</span>
                    <StatusBadge :value="row.payment_status_value" :label="row.payment_status_label" />
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :value="row.status_value" :label="row.status" />
            </template>
            <template #empty>No orders match.</template>
        </AdminTable>
    </AdminLayout>
</template>
```

(The inline error list is gone: toasts show the `status`, `from`, `to`, `payment_method` and `shipping_zone_id` errors.)

- [ ] **Step 3: Rewrite `Categories/Index.vue`**

```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconPlus from '@/Components/Icons/IconPlus.vue';

defineProps({
    categories: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'parent', label: 'Parent' },
    { key: 'products_count', label: 'Products' },
    { key: 'sort_order', label: 'Order' },
    { key: 'is_active', label: 'Visible' },
    { key: 'actions', label: '' },
];

const deleteCategory = (category) => {
    router.delete(route('admin.categories.destroy', category.id), {
        onBefore: () => window.confirm(`Delete "${category.name}"?`),
    });
};
</script>

<template>
    <Head title="Categories" />

    <AdminLayout>
        <template #header>
            <PageHeader title="Categories">
                <template #actions>
                    <Link :href="route('admin.categories.create')" class="admin-btn">
                        <IconPlus class="h-4 w-4" />
                        New category
                    </Link>
                </template>
            </PageHeader>
        </template>

        <AdminTable :rows="categories" :columns="columns" :filters="filters">
            <template #cell-name="{ row }">
                <Link :href="route('admin.categories.edit', row.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-parent="{ row }">{{ row.parent ?? '—' }}</template>
            <template #cell-is_active="{ row }">
                <StatusBadge :value="row.is_active ? 'active' : 'inactive'" :label="row.is_active ? 'Visible' : 'Hidden'" />
            </template>
            <template #cell-actions="{ row }">
                <button type="button" class="admin-btn-danger min-h-8 px-3" @click="deleteCategory(row)">Delete</button>
            </template>
            <template #empty>No categories yet.</template>
        </AdminTable>
    </AdminLayout>
</template>
```

- [ ] **Step 4: Rewrite `Products/Index.vue`**

```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconPlus from '@/Components/Icons/IconPlus.vue';
import { formatPaise } from '@/money';

defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
    categories: { type: Array, required: true },
});

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
    { key: 'base_price', label: 'Price' },
    { key: 'variants_count', label: 'Variants' },
    { key: 'stock', label: 'Stock' },
    { key: 'is_active', label: 'Visible' },
    { key: 'actions', label: '' },
];

const deleteProduct = (product) => {
    router.delete(route('admin.products.destroy', product.id), {
        onBefore: () => window.confirm(`Delete "${product.name}" and its images?`),
    });
};
</script>

<template>
    <Head title="Products" />

    <AdminLayout>
        <template #header>
            <PageHeader title="Products">
                <template #actions>
                    <Link :href="route('admin.products.create')" class="admin-btn">
                        <IconPlus class="h-4 w-4" />
                        New product
                    </Link>
                </template>
            </PageHeader>
        </template>

        <AdminTable :rows="products" :columns="columns" :filters="filters">
            <template #filters="{ query }">
                <select v-model="query.category_id" aria-label="Category" class="admin-field md:w-auto">
                    <option :value="null">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</option>
                </select>
            </template>
            <template #cell-name="{ row }">
                <Link :href="route('admin.products.edit', row.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-base_price="{ row }">
                <span class="tabular-nums">{{ formatPaise(row.base_price) }}</span>
            </template>
            <template #cell-stock="{ row }">
                <span class="tabular-nums" :class="row.stock <= 3 ? 'font-medium text-red-700' : ''">{{ row.stock }}</span>
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge :value="row.is_active ? 'active' : 'inactive'" :label="row.is_active ? 'Visible' : 'Hidden'" />
            </template>
            <template #cell-actions="{ row }">
                <button type="button" class="admin-btn-danger min-h-8 px-3" @click="deleteProduct(row)">Delete</button>
            </template>
            <template #empty>No products yet.</template>
        </AdminTable>
    </AdminLayout>
</template>
```

- [ ] **Step 5: Rewrite `ShippingZones/Index.vue`**

```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconPlus from '@/Components/Icons/IconPlus.vue';
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
            <PageHeader title="Shipping zones">
                <template #actions>
                    <Link :href="route('admin.shipping-zones.create')" class="admin-btn">
                        <IconPlus class="h-4 w-4" />
                        New zone
                    </Link>
                </template>
            </PageHeader>
        </template>

        <AdminTable :rows="zones" :columns="columns" :filters="filters">
            <template #cell-name="{ row }">
                <Link :href="route('admin.shipping-zones.edit', row.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-rate="{ row }">
                <span class="tabular-nums">{{ formatPaise(row.rate) }}</span>
            </template>
            <template #cell-free_above="{ row }">
                <span class="tabular-nums">{{ row.free_above === null ? '—' : formatPaise(row.free_above) }}</span>
            </template>
            <template #cell-cod_allowed="{ row }">{{ row.cod_allowed ? 'Yes' : 'No' }}</template>
            <template #cell-is_active="{ row }">
                <StatusBadge :value="row.is_active ? 'active' : 'inactive'" :label="row.is_active ? 'Active' : 'Off'" />
            </template>
            <template #cell-actions="{ row }">
                <button type="button" class="admin-btn-danger min-h-8 px-3" @click="deleteZone(row)">Delete</button>
            </template>
            <template #empty>No zones yet. Without a zone nobody can check out.</template>
        </AdminTable>
    </AdminLayout>
</template>
```

- [ ] **Step 6: Rewrite `Pages/Index.vue`** (not paginated and has no search, so it uses a plain card list instead of `AdminTable`)

```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import IconPlus from '@/Components/Icons/IconPlus.vue';

defineProps({
    pages: { type: Array, required: true },
});

const deletePage = (page) => {
    router.delete(route('admin.pages.destroy', page.id), {
        onBefore: () => window.confirm(`Delete "${page.title}"?`),
    });
};
</script>

<template>
    <Head title="Pages" />

    <AdminLayout>
        <template #header>
            <PageHeader title="Pages">
                <template #actions>
                    <Link :href="route('admin.pages.create')" class="admin-btn">
                        <IconPlus class="h-4 w-4" />
                        New page
                    </Link>
                </template>
            </PageHeader>
        </template>

        <ul v-if="pages.length" class="admin-card divide-y divide-brand-100">
            <li v-for="page in pages" :key="page.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <Link :href="route('admin.pages.edit', page.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ page.title }}</Link>
                    <p class="truncate font-mono text-xs text-brand-500">/pages/{{ page.slug }}</p>
                </div>
                <div class="flex gap-1">
                    <a :href="route('pages.show', page.slug)" target="_blank" rel="noopener" class="admin-btn-secondary min-h-8 px-3">View</a>
                    <button type="button" class="admin-btn-danger min-h-8 px-3" @click="deletePage(page)">Delete</button>
                </div>
            </li>
        </ul>
        <div v-else class="admin-card px-4 py-12 text-center text-sm text-brand-500">No pages yet.</div>
    </AdminLayout>
</template>
```

- [ ] **Step 7: Build and run the suites**

Run: `npm run build`
Expected: success.
Run: `php artisan test --compact tests/Feature/Admin/OrderListTest.php tests/Feature/Admin/CategoryManagementTest.php tests/Feature/Admin/ProductManagementTest.php tests/Feature/Admin/ShippingZoneManagementTest.php tests/Feature/Admin/PageManagementTest.php`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Components/Admin/AdminTable.vue resources/js/Pages/Admin/Orders/Index.vue resources/js/Pages/Admin/Categories/Index.vue resources/js/Pages/Admin/Products/Index.vue resources/js/Pages/Admin/ShippingZones/Index.vue resources/js/Pages/Admin/Pages/Index.vue
git commit -m "feat: admin index pages with status pills and mobile card tables"
```

---

### Task 6: Dashboard with revenue chart

**Files:**
- Create: `resources/js/Components/Admin/BarChart.vue`
- Modify (rewrite): `resources/js/Pages/Admin/Dashboard.vue`

**Interfaces:**
- Consumes: `revenue.daily`, `today.orders[].status_value` (Task 1); `StatusBadge` (Task 3); the existing `IconChevronDown`.
- Produces: `BarChart` props `{ points: Array<{date, label, value}> (req), format: Function (req) }`.

- [ ] **Step 1: Create `BarChart.vue`**

```vue
<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    points: { type: Array, required: true },
    format: { type: Function, required: true },
});

// Chart drawing units; the SVG stretches to its box (preserveAspectRatio="none").
const width = 300;
const height = 100;

const activeIndex = ref(null);
const max = computed(() => Math.max(0, ...props.points.map((point) => point.value)));
const total = computed(() => props.points.reduce((sum, point) => sum + point.value, 0));
const slotWidth = computed(() => width / props.points.length);
const barHeight = (value) => (max.value ? (value / max.value) * height : 0);
const isLabelled = (index) => (props.points.length - 1 - index) % 7 === 0;
</script>

<template>
    <div>
        <p v-if="max === 0" class="py-12 text-center text-sm text-brand-500">No payments received in the last 30 days.</p>
        <div v-else class="flex gap-3">
            <div class="flex h-48 w-16 shrink-0 flex-col justify-between text-right text-[11px] tabular-nums text-brand-400" aria-hidden="true">
                <span class="-translate-y-1/2">{{ format(max) }}</span>
                <span>{{ format(Math.round(max / 2)) }}</span>
                <span class="translate-y-1/2">{{ format(0) }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="relative">
                    <svg
                        :viewBox="`0 0 ${width} ${height}`"
                        preserveAspectRatio="none"
                        class="block h-48 w-full overflow-visible"
                        role="img"
                        :aria-label="`Received per day over the last ${points.length} days, ${format(total)} in total`"
                    >
                        <line v-for="y in [0, height / 2, height]" :key="y" x1="0" :x2="width" :y1="y" :y2="y" class="stroke-brand-200" stroke-dasharray="2 3" vector-effect="non-scaling-stroke" />
                        <rect
                            v-for="(point, index) in points"
                            :key="point.date"
                            :x="index * slotWidth + slotWidth * 0.15"
                            :width="slotWidth * 0.7"
                            :y="height - barHeight(point.value)"
                            :height="barHeight(point.value)"
                            rx="1"
                            :class="activeIndex === index ? 'fill-ink' : 'fill-brand-600'"
                            class="transition-colors duration-150 motion-reduce:transition-none"
                        />
                        <rect
                            v-for="(point, index) in points"
                            :key="`hit-${point.date}`"
                            :x="index * slotWidth"
                            :width="slotWidth"
                            y="0"
                            :height="height"
                            fill="transparent"
                            @mouseenter="activeIndex = index"
                            @mouseleave="activeIndex = null"
                        />
                    </svg>
                    <div
                        v-if="activeIndex !== null"
                        class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-ink px-2.5 py-1.5 text-xs text-brand-50 shadow"
                        :style="{ left: `${((activeIndex + 0.5) / points.length) * 100}%` }"
                    >
                        <span class="block text-brand-300">{{ points[activeIndex].label }}</span>
                        <span class="font-medium tabular-nums">{{ format(points[activeIndex].value) }}</span>
                    </div>
                </div>
                <div class="mt-2 flex h-4 text-[11px] text-brand-500" aria-hidden="true">
                    <span v-for="(point, index) in points" :key="point.date" class="relative flex-1">
                        <span v-if="isLabelled(index)" class="absolute left-1/2 -translate-x-1/2 whitespace-nowrap">{{ point.label }}</span>
                    </span>
                </div>
            </div>
        </div>
        <table class="sr-only">
            <caption>Received per day</caption>
            <thead>
                <tr>
                    <th scope="col">Day</th>
                    <th scope="col">Received</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="point in points" :key="point.date">
                    <td>{{ point.label }}</td>
                    <td>{{ format(point.value) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
```

- [ ] **Step 2: Rewrite `Dashboard.vue`**

```vue
<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BarChart from '@/Components/Admin/BarChart.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    today: { type: Object, required: true },
    needs_action: { type: Array, required: true },
    low_stock: { type: Array, required: true },
    revenue: { type: Object, required: true },
});

const now = new Date();
const greeting = now.getHours() < 12 ? 'Good morning' : now.getHours() < 17 ? 'Good afternoon' : 'Good evening';
const todayLabel = now.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long' });

const isRefundOwed = (item) => item.filters.status === 'refund_owed' && item.count > 0;

const kpis = computed(() => [
    { label: 'Orders today', value: props.today.count },
    { label: 'Received, last 7 days', value: formatPaise(props.revenue.last_7_days) },
    { label: 'Received, last 30 days', value: formatPaise(props.revenue.last_30_days) },
]);
</script>

<template>
    <Head title="Admin" />

    <AdminLayout>
        <template #header>
            <p class="text-sm text-brand-500">{{ todayLabel }}</p>
            <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-ink">{{ greeting }}</h1>
        </template>

        <div class="space-y-8">
            <section aria-labelledby="needs-action">
                <h2 id="needs-action" class="mb-3 text-sm font-medium text-brand-600">Needs action</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <Link
                        v-for="item in needs_action"
                        :key="item.label"
                        :href="route('admin.orders.index', item.filters)"
                        class="admin-card focus-ring group relative flex flex-col gap-1 overflow-hidden p-4 transition-colors duration-150 hover:border-brand-400 motion-reduce:transition-none"
                    >
                        <span v-if="isRefundOwed(item)" class="absolute inset-y-0 left-0 w-1 bg-red-600" aria-hidden="true" />
                        <span class="text-2xl font-semibold tabular-nums" :class="item.count === 0 ? 'text-brand-300' : isRefundOwed(item) ? 'text-red-700' : 'text-ink'">{{ item.count }}</span>
                        <span class="flex items-center justify-between gap-2 text-sm text-brand-600">
                            {{ item.label }}
                            <IconChevronDown class="h-4 w-4 shrink-0 -rotate-90 text-brand-400 transition-transform duration-150 group-hover:translate-x-0.5 motion-reduce:transition-none" />
                        </span>
                    </Link>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-3">
                <div v-for="kpi in kpis" :key="kpi.label" class="admin-card p-5">
                    <p class="text-sm text-brand-600">{{ kpi.label }}</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums">{{ kpi.value }}</p>
                </div>
            </section>

            <section class="admin-card p-5">
                <div class="mb-5 flex items-baseline justify-between gap-3">
                    <h2 class="font-medium">Received, last 30 days</h2>
                    <span class="text-sm tabular-nums text-brand-500">{{ formatPaise(revenue.last_30_days) }}</span>
                </div>
                <BarChart :points="revenue.daily" :format="formatPaise" />
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="admin-card">
                    <h2 class="border-b border-brand-200 px-5 py-4 font-medium">Today's orders</h2>
                    <ul v-if="today.orders.length" class="divide-y divide-brand-100">
                        <li v-for="order in today.orders" :key="order.id">
                            <Link :href="route('admin.orders.show', order.id)" class="focus-ring flex items-center gap-3 px-5 py-3 text-sm transition-colors duration-150 hover:bg-brand-50/60 motion-reduce:transition-none">
                                <span class="shrink-0 font-medium">{{ order.number }}</span>
                                <span class="min-w-0 flex-1 truncate text-brand-700">{{ order.customer }}</span>
                                <span class="tabular-nums">{{ formatPaise(order.total) }}</span>
                                <StatusBadge :value="order.status_value" :label="order.status" />
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-10 text-center text-sm text-brand-500">No orders yet today.</p>
                </section>

                <section class="admin-card">
                    <h2 class="border-b border-brand-200 px-5 py-4 font-medium">Low stock (3 or fewer)</h2>
                    <ul v-if="low_stock.length" class="divide-y divide-brand-100">
                        <li v-for="variant in low_stock" :key="variant.id">
                            <Link :href="route('admin.products.edit', variant.product_id)" class="focus-ring flex items-center gap-3 px-5 py-3 text-sm transition-colors duration-150 hover:bg-brand-50/60 motion-reduce:transition-none">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ variant.name }}</span>
                                    <span v-if="variant.variant" class="block text-xs text-brand-500">{{ variant.variant }}</span>
                                </span>
                                <span class="hidden font-mono text-xs text-brand-500 sm:inline">{{ variant.sku }}</span>
                                <span
                                    class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
                                    :class="variant.stock === 0 ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'"
                                >
                                    {{ variant.stock === 0 ? 'Sold out' : `${variant.stock} left` }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-10 text-center text-sm text-brand-500">Everything is well stocked.</p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
```

- [ ] **Step 3: Build and test**

Run: `npm run build`
Expected: success.
Run: `php artisan test --compact tests/Feature/Admin/DashboardTest.php`
Expected: all PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Admin/BarChart.vue resources/js/Pages/Admin/Dashboard.vue
git commit -m "feat: admin dashboard with action tiles, KPIs and a 30-day revenue chart"
```

---

### Task 7: Order detail page (stepper, next action, timeline)

**Files:**
- Modify (rewrite): `resources/js/Pages/Admin/Orders/Show.vue`

**Interfaces:**
- Consumes: `order.fulfilment_status_value`, `order.payment_status_value`, `payments[].status_value`, `events[].fulfilment_status_value`, `events[].payment_status_value` (Task 1); `PageHeader`, `StatusBadge`, `FormField`, `IconCheck`, `IconAlert`, `IconDocument` (Task 3); the existing `IconChat`.

- [ ] **Step 1: Rewrite `Orders/Show.vue`**

The `<script setup>` keeps every existing handler (`advance`, `cancel`, `refund`) unchanged and adds the stepper data.

```vue
<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormField from '@/Components/Admin/FormField.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconAlert from '@/Components/Icons/IconAlert.vue';
import IconChat from '@/Components/Icons/IconChat.vue';
import IconCheck from '@/Components/Icons/IconCheck.vue';
import IconDocument from '@/Components/Icons/IconDocument.vue';
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

// Must match FulfilmentStatus::next() order.
const steps = [
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'packed', label: 'Packed' },
    { value: 'dispatched', label: 'Dispatched' },
    { value: 'delivered', label: 'Delivered' },
];

const currentStep = computed(() => steps.findIndex((step) => step.value === props.order.fulfilment_status_value));
const isStepDone = (index) => index < currentStep.value || (index === currentStep.value && steps[index].value === 'delivered');

const nextHints = {
    confirmed: 'Call the customer to confirm, then mark the order confirmed.',
    packed: 'Pack the items, then mark the order packed.',
    dispatched: 'Hand the parcel over, add a tracking note if there is one, then mark it dispatched.',
    delivered: 'Mark it delivered once the customer has the parcel.',
};

const nextHint = computed(() => (props.next_status ? nextHints[props.next_status.value] : 'Waiting for payment before this order can move on.'));
</script>

<template>
    <Head :title="order.number" />

    <AdminLayout>
        <template #header>
            <PageHeader :title="order.number" :back="{ label: 'Orders', href: route('admin.orders.index') }" :meta="`${order.placed_at} · ${order.payment_method}`">
                <template #badges>
                    <StatusBadge :value="order.fulfilment_status_value" :label="order.fulfilment_status" />
                    <StatusBadge :value="order.payment_status_value" :label="order.payment_status" />
                </template>
                <template #actions>
                    <a :href="route('admin.orders.slip', order.id)" target="_blank" rel="noopener" class="admin-btn-secondary">
                        <IconDocument class="h-4 w-4" />
                        Print slip
                    </a>
                    <a :href="whatsapp_url" target="_blank" rel="noopener" class="admin-btn-secondary">
                        <IconChat class="h-4 w-4" />
                        Message customer
                    </a>
                </template>
            </PageHeader>
        </template>

        <div class="space-y-6">
            <div v-if="order.fulfilment_status_value === 'cancelled'" class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <IconAlert class="h-5 w-5 shrink-0" />
                This order was cancelled.
            </div>
            <ol v-else class="admin-card grid grid-cols-5 px-2 py-4" aria-label="Order progress">
                <li
                    v-for="(step, index) in steps"
                    :key="step.value"
                    class="flex flex-col items-center gap-2 text-center text-xs"
                    :aria-current="index === currentStep ? 'step' : null"
                >
                    <span class="relative flex w-full items-center justify-center">
                        <span
                            v-if="index > 0"
                            class="absolute right-1/2 top-1/2 h-0.5 w-full -translate-y-1/2"
                            :class="index <= currentStep ? 'bg-ink' : 'bg-brand-200'"
                            aria-hidden="true"
                        />
                        <span
                            class="relative flex h-7 w-7 items-center justify-center rounded-full border-2"
                            :class="isStepDone(index) ? 'border-ink bg-ink text-brand-50' : index === currentStep ? 'border-ink bg-white text-ink ring-4 ring-brand-100' : 'border-brand-200 bg-white text-brand-300'"
                        >
                            <IconCheck v-if="isStepDone(index)" class="h-4 w-4" />
                            <span v-else class="text-[11px] font-semibold">{{ index + 1 }}</span>
                        </span>
                    </span>
                    <span :class="index <= currentStep ? 'font-medium text-ink' : 'text-brand-400'">{{ step.label }}</span>
                </li>
            </ol>

            <p v-if="next_status?.value === 'confirmed'" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                Call <a :href="`tel:+91${order.customer_phone}`" class="font-medium underline">{{ order.customer_phone }}</a> to confirm this cash-on-delivery order before packing it.
                It is cancelled automatically 24 hours after it was placed if nobody confirms it.
            </p>

            <section v-if="next_status || can_cancel" class="admin-card flex flex-wrap items-end justify-between gap-4 p-5">
                <div class="min-w-0 max-w-md">
                    <h2 class="font-medium">Next step</h2>
                    <p class="mt-1 text-sm text-brand-600">{{ nextHint }}</p>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <FormField v-if="next_status?.value === 'dispatched'" label="Tracking note (optional)" for="tracking_note">
                        <input id="tracking_note" v-model="trackingNote" type="text" maxlength="500" placeholder="Bus parcel, receipt no." class="admin-field sm:w-72" />
                    </FormField>
                    <button v-if="next_status" type="button" :disabled="isBusy" class="admin-btn" @click="advance">
                        {{ next_status.value === 'confirmed' ? 'Confirmed by phone' : `Mark ${next_status.label}` }}
                    </button>
                    <button v-if="can_cancel" type="button" :disabled="isBusy" class="admin-btn-danger" @click="cancel">Cancel order</button>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="admin-card lg:col-span-2">
                    <h2 class="border-b border-brand-200 px-5 py-4 font-medium">Items</h2>
                    <ul class="divide-y divide-brand-100">
                        <li v-for="item in order.items" :key="item.id" class="flex items-start gap-4 px-5 py-3 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ item.name }}</p>
                                <p v-if="item.variant" class="text-xs text-brand-500">{{ item.variant }}</p>
                            </div>
                            <p class="whitespace-nowrap tabular-nums text-brand-600">{{ item.quantity }} × {{ formatPaise(item.unit_price) }}</p>
                            <p class="w-20 text-right font-medium tabular-nums">{{ formatPaise(item.line_total) }}</p>
                        </li>
                    </ul>
                    <dl class="space-y-2 border-t border-brand-200 px-5 py-4 text-sm">
                        <div class="flex justify-between text-brand-600">
                            <dt>Subtotal</dt>
                            <dd class="tabular-nums">{{ formatPaise(order.subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between text-brand-600">
                            <dt>Shipping ({{ order.zone }})</dt>
                            <dd class="tabular-nums">{{ order.shipping ? formatPaise(order.shipping) : 'Free' }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-brand-100 pt-2 text-base font-semibold">
                            <dt>Total</dt>
                            <dd class="tabular-nums">{{ formatPaise(order.total) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-card space-y-5 p-5 text-sm">
                    <div>
                        <h2 class="text-xs font-medium uppercase tracking-wide text-brand-500">Customer</h2>
                        <p class="mt-2 font-medium">{{ order.customer_name }}</p>
                        <a :href="`tel:${order.customer_phone}`" class="focus-ring block rounded text-brand-700 hover:underline">{{ order.customer_phone }}</a>
                        <p v-if="order.customer_email" class="break-all text-brand-700">{{ order.customer_email }}</p>
                    </div>
                    <div>
                        <h2 class="text-xs font-medium uppercase tracking-wide text-brand-500">Ship to</h2>
                        <p v-for="line in order.address" :key="line" class="mt-1 text-brand-700 first-of-type:mt-2">{{ line }}</p>
                    </div>
                    <div v-if="order.tracking_note">
                        <h2 class="text-xs font-medium uppercase tracking-wide text-brand-500">Tracking</h2>
                        <p class="mt-2 text-brand-700">{{ order.tracking_note }}</p>
                    </div>
                </section>
            </div>

            <section v-if="payments.length" class="admin-card overflow-x-auto">
                <h2 class="border-b border-brand-200 px-5 py-4 font-medium">Payment attempts</h2>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-brand-200 bg-brand-50/60 text-xs uppercase tracking-wide text-brand-500">
                        <tr>
                            <th scope="col" class="px-5 py-2 font-medium">When</th>
                            <th scope="col" class="px-5 py-2 font-medium">Razorpay ids</th>
                            <th scope="col" class="px-5 py-2 font-medium">Amount</th>
                            <th scope="col" class="px-5 py-2 font-medium">Status</th>
                            <th scope="col" class="px-5 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-100">
                        <tr v-for="payment in payments" :key="payment.id">
                            <td class="whitespace-nowrap px-5 py-3">{{ payment.created_at }}</td>
                            <td class="px-5 py-3 font-mono text-xs">
                                {{ payment.gateway_payment_id ?? '—' }}
                                <span class="block text-brand-500">{{ payment.gateway_order_id }}</span>
                            </td>
                            <td class="px-5 py-3 tabular-nums">{{ formatPaise(payment.amount) }}</td>
                            <td class="px-5 py-3"><StatusBadge :value="payment.status_value" :label="payment.status" /></td>
                            <td class="px-5 py-3 text-right">
                                <button v-if="payment.refundable" type="button" :disabled="isBusy" class="admin-btn-danger min-h-8 px-3" @click="refund(payment)">Refund</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="admin-card p-5">
                <h2 class="font-medium">Timeline</h2>
                <ol class="mt-4">
                    <li v-for="(event, index) in events" :key="event.id" class="relative flex gap-4 pb-6 last:pb-0">
                        <span v-if="index < events.length - 1" class="absolute left-[4.5px] top-4 h-full w-px bg-brand-200" aria-hidden="true" />
                        <span class="relative mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="index === events.length - 1 ? 'bg-ink ring-4 ring-brand-100' : 'bg-brand-300'" aria-hidden="true" />
                        <div class="min-w-0 text-sm">
                            <div class="flex flex-wrap gap-2">
                                <StatusBadge :value="event.fulfilment_status_value" :label="event.fulfilment_status" />
                                <StatusBadge :value="event.payment_status_value" :label="event.payment_status" />
                            </div>
                            <p class="mt-1 text-xs text-brand-500">
                                {{ event.at }}<template v-if="event.by"> · by {{ event.by }}</template>
                            </p>
                        </div>
                    </li>
                </ol>
            </section>
        </div>
    </AdminLayout>
</template>
```

(The inline `errors.status/order/payment` alerts are gone: toasts show them.)

- [ ] **Step 2: Build and test**

Run: `npm run build`
Expected: success.
Run: `php artisan test --compact tests/Feature/Admin/OrderWorkflowTest.php tests/Feature/Admin/PackingSlipTest.php`
Expected: all PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Orders/Show.vue
git commit -m "feat: admin order page with progress stepper, next-step card and timeline"
```

---

### Task 8: SaveBar, and the category, shipping zone and settings forms

**Files:**
- Create: `resources/js/Components/Admin/SaveBar.vue`
- Modify (rewrite): `resources/js/Pages/Admin/Categories/Partials/CategoryForm.vue`, `Categories/Create.vue`, `Categories/Edit.vue`
- Modify (rewrite): `resources/js/Pages/Admin/ShippingZones/Partials/ShippingZoneForm.vue`, `ShippingZones/Create.vue`, `ShippingZones/Edit.vue`
- Modify (rewrite): `resources/js/Pages/Admin/Settings/Edit.vue`

**Interfaces:**
- Consumes: `FormField`, `FormSection`, `PageHeader` (Task 3).
- Produces: `SaveBar` props `{ form: Object (req, an Inertia useForm), label: String = 'Save' }`. It renders the submit button (`type="submit"`), so it must sit inside the `<form>`. Discard calls `form.reset()` and `form.clearErrors()` itself.

- [ ] **Step 1: Create `SaveBar.vue`**

```vue
<script setup>
defineProps({
    form: { type: Object, required: true },
    label: { type: String, default: 'Save' },
});
</script>

<template>
    <div class="sticky bottom-4 z-20 mt-6 flex items-center justify-between gap-3 rounded-xl border border-brand-200 bg-white/95 px-4 py-3 shadow-lg shadow-ink/5 backdrop-blur">
        <Transition
            enter-active-class="transition-opacity duration-150 motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <p v-if="form.isDirty" class="flex items-center gap-2 text-sm text-brand-700">
                <span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true" />
                Unsaved changes
            </p>
        </Transition>
        <div class="ml-auto flex gap-2">
            <button v-if="form.isDirty" type="button" class="admin-btn-secondary" :disabled="form.processing" @click="form.reset(); form.clearErrors()">Discard</button>
            <button type="submit" class="admin-btn" :disabled="form.processing">{{ form.processing ? 'Saving…' : label }}</button>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Rewrite `CategoryForm.vue`**

```vue
<script setup>
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';

defineProps({
    form: { type: Object, required: true },
    parents: { type: Array, required: true },
    imageUrl: { type: String, default: null },
});

defineEmits(['submit']);
</script>

<template>
    <form class="max-w-4xl" @submit.prevent="$emit('submit')">
        <div class="admin-card divide-y divide-brand-100">
            <FormSection title="Details" description="How the category is named and where it sits.">
                <FormField label="Name" for="name" :error="form.errors.name">
                    <input id="name" v-model="form.name" class="admin-field" required />
                </FormField>
                <FormField label="URL slug" for="slug" hint="e.g. t-shirts" :error="form.errors.slug">
                    <input id="slug" v-model="form.slug" class="admin-field" required />
                </FormField>
                <FormField label="Parent category" for="parent_id" :error="form.errors.parent_id">
                    <select id="parent_id" v-model="form.parent_id" class="admin-field">
                        <option :value="null">None (top level)</option>
                        <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
                    </select>
                </FormField>
                <FormField label="Sort order" for="sort_order" hint="Lower shows first." :error="form.errors.sort_order">
                    <input id="sort_order" v-model="form.sort_order" type="number" min="0" class="admin-field w-32" required />
                </FormField>
            </FormSection>
            <FormSection title="Image" description="Shown on the home page tile.">
                <FormField label="Image file" for="image" hint="JPG, PNG or WebP." :error="form.errors.image">
                    <img v-if="imageUrl && !form.image" :src="imageUrl" alt="" class="mb-3 aspect-[2/1] w-64 rounded-lg object-cover" />
                    <input
                        id="image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-brand-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-brand-200"
                        @input="form.image = $event.target.files[0] ?? null"
                    />
                </FormField>
            </FormSection>
            <FormSection title="Visibility">
                <label class="flex items-center gap-3 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="admin-checkbox" />
                    Visible on the store
                </label>
            </FormSection>
        </div>
        <SaveBar :form="form" />
    </form>
</template>
```

- [ ] **Step 3: Update the category Create and Edit headers**

In `Categories/Create.vue`, add `import PageHeader from '@/Components/Admin/PageHeader.vue';` and replace the `#header` template contents with:

```vue
            <PageHeader title="New category" :back="{ label: 'Categories', href: route('admin.categories.index') }" />
```

In `Categories/Edit.vue`, add the same import and replace the `#header` contents with:

```vue
            <PageHeader :title="category.name" :back="{ label: 'Categories', href: route('admin.categories.index') }" />
```

- [ ] **Step 4: Rewrite `ShippingZoneForm.vue`**

```vue
<script setup>
import { computed } from 'vue';
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';

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
    <form class="max-w-4xl" @submit.prevent="$emit('submit')">
        <div class="admin-card divide-y divide-brand-100">
            <FormSection title="Details">
                <FormField label="Name" for="name" :error="form.errors.name">
                    <input id="name" v-model="form.name" class="admin-field" required />
                </FormField>
            </FormSection>
            <FormSection title="Rates" description="What delivery costs in this zone, and whether cash on delivery is offered.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Shipping rate (₹)" for="rate" :error="form.errors.rate">
                        <input id="rate" v-model="form.rate" type="number" min="0" step="0.01" class="admin-field" required />
                    </FormField>
                    <FormField label="Free shipping from (₹)" for="free_above" hint="Optional." :error="form.errors.free_above">
                        <input id="free_above" v-model="form.free_above" type="number" min="0" step="0.01" class="admin-field" />
                    </FormField>
                </div>
                <label class="flex items-center gap-3 text-sm">
                    <input v-model="form.cod_allowed" type="checkbox" class="admin-checkbox" />
                    Cash on delivery allowed
                </label>
                <FormField v-if="form.cod_allowed" label="COD only up to (₹)" for="cod_max" hint="Optional." :error="form.errors.cod_max">
                    <input id="cod_max" v-model="form.cod_max" type="number" min="1" step="0.01" class="admin-field w-40" />
                </FormField>
            </FormSection>
            <FormSection title="Pincodes" description="Separate with commas, spaces or new lines.">
                <FormField label="Pincodes" for="pincodes">
                    <textarea id="pincodes" v-model="form.pincodes" rows="6" class="admin-field font-mono" required />
                </FormField>
                <ul v-if="pincodeErrors.length" class="space-y-1 text-sm text-red-700">
                    <li v-for="message in pincodeErrors" :key="message">{{ message }}</li>
                </ul>
            </FormSection>
            <FormSection title="Status">
                <label class="flex items-center gap-3 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="admin-checkbox" />
                    Active
                </label>
            </FormSection>
        </div>
        <SaveBar :form="form" />
    </form>
</template>
```

- [ ] **Step 5: Update the zone Create and Edit headers**

In `ShippingZones/Create.vue`, add `import PageHeader from '@/Components/Admin/PageHeader.vue';` and replace the `#header` contents with:

```vue
            <PageHeader title="New shipping zone" :back="{ label: 'Shipping zones', href: route('admin.shipping-zones.index') }" />
```

In `ShippingZones/Edit.vue`, add the same import and replace the `#header` contents with:

```vue
            <PageHeader :title="zone.name" :back="{ label: 'Shipping zones', href: route('admin.shipping-zones.index') }" />
```

- [ ] **Step 6: Rewrite `Settings/Edit.vue`**

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';

const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    store_name: props.settings.store_name ?? '',
    contact_phone: props.settings.contact_phone ?? '',
    whatsapp_number: props.settings.whatsapp_number ?? '',
    contact_email: props.settings.contact_email ?? '',
    cod_enabled: props.settings.cod_enabled === '1',
});

const contactFields = [
    { key: 'contact_phone', label: 'Contact phone', hint: '10 digits.', type: 'tel' },
    { key: 'whatsapp_number', label: 'WhatsApp number', hint: '10 digits.', type: 'tel' },
    { key: 'contact_email', label: 'Contact email', hint: null, type: 'email' },
];
</script>

<template>
    <Head title="Settings" />

    <AdminLayout>
        <template #header>
            <PageHeader title="Settings" />
        </template>

        <form class="max-w-4xl" @submit.prevent="form.put(route('admin.settings.update'))">
            <div class="admin-card divide-y divide-brand-100">
                <FormSection title="Store">
                    <FormField label="Store name" for="store_name" :error="form.errors.store_name">
                        <input id="store_name" v-model="form.store_name" type="text" class="admin-field" />
                    </FormField>
                </FormSection>
                <FormSection title="Contact" description="Shown to customers and used for order messages.">
                    <FormField v-for="field in contactFields" :key="field.key" :label="field.label" :for="field.key" :hint="field.hint" :error="form.errors[field.key]">
                        <input :id="field.key" v-model="form[field.key]" :type="field.type" class="admin-field" />
                    </FormField>
                </FormSection>
                <FormSection title="Payments">
                    <label class="flex items-center gap-3 text-sm">
                        <input v-model="form.cod_enabled" type="checkbox" class="admin-checkbox" />
                        Cash on delivery enabled
                    </label>
                </FormSection>
            </div>
            <SaveBar :form="form" />
        </form>
    </AdminLayout>
</template>
```

- [ ] **Step 7: Build and test**

Run: `npm run build`
Expected: success.
Run: `php artisan test --compact tests/Feature/Admin/CategoryManagementTest.php tests/Feature/Admin/ShippingZoneManagementTest.php tests/Feature/Admin/SettingsTest.php`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Components/Admin/SaveBar.vue resources/js/Pages/Admin/Categories resources/js/Pages/Admin/ShippingZones resources/js/Pages/Admin/Settings
git commit -m "feat: sectioned admin forms with a sticky save bar for categories, zones and settings"
```

---

### Task 9: Product forms with live preview

**Files:**
- Create: `resources/js/Pages/Admin/Products/Partials/ProductPreview.vue`
- Modify (rewrite): `resources/js/Pages/Admin/Products/Partials/ProductForm.vue`, `Products/Create.vue`, `Products/Edit.vue`
- Modify: `resources/js/Pages/Admin/Products/Partials/VariantGrid.vue` (template and the `cell` constant only), `resources/js/Components/Admin/ImageUploader.vue` (template and the `field` constant only)

**Interfaces:**
- Consumes: `FormField`, `FormSection`, `PageHeader`, `StatusBadge`, `SaveBar`, `IconExternal` (Tasks 3, 8); `Components/Store/ProductCard.vue` (prop `product: {name, slug, price_from, has_price_range, in_stock, image, hover_image}`); `toPaise` from `@/money`.
- Produces: `ProductPreview` props `{ form: Object (req), variants: Array = [], images: Array = [], viewUrl: String|null = null }`.

- [ ] **Step 1: Create `ProductPreview.vue`**

```vue
<script setup>
import { computed } from 'vue';
import ProductCard from '@/Components/Store/ProductCard.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconExternal from '@/Components/Icons/IconExternal.vue';
import { toPaise } from '@/money';

const props = defineProps({
    form: { type: Object, required: true },
    variants: { type: Array, default: () => [] },
    images: { type: Array, default: () => [] },
    viewUrl: { type: String, default: null },
});

// Mirrors Product::cardData(): active variants, effective price = price_override ?? base_price.
const card = computed(() => {
    const basePrice = toPaise(props.form.base_price) ?? 0;
    const activeVariants = props.variants.filter((variant) => variant.is_active);
    const prices = activeVariants.map((variant) => variant.price_override ?? basePrice);
    const name = props.form.name || 'Product name';
    const image = (index) => (props.images[index] ? { src: props.images[index].url, srcset: '', alt: props.images[index].alt || name } : null);

    return {
        name,
        slug: props.form.slug || 'preview',
        price_from: prices.length ? Math.min(...prices) : basePrice,
        has_price_range: new Set(prices).size > 1,
        in_stock: activeVariants.some((variant) => variant.stock > 0),
        image: image(0),
        hover_image: image(1),
    };
});
</script>

<template>
    <div class="admin-card p-5">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-medium">Preview</h2>
            <StatusBadge v-if="!form.is_active" value="inactive" label="Hidden from store" />
        </div>
        <div class="mt-4 rounded-lg bg-brand-50 p-4 text-ink">
            <!-- inert: the card is a real storefront link; the preview must not navigate or prefetch -->
            <div inert class="mx-auto max-w-[15rem]">
                <ProductCard :product="card" />
            </div>
        </div>
        <p class="mt-3 truncate font-mono text-xs text-brand-500">/products/{{ form.slug || '…' }}</p>
        <p class="mt-2 text-xs text-brand-500">Variant and photo changes show here once they are saved.</p>
        <a v-if="viewUrl" :href="viewUrl" target="_blank" rel="noopener" class="admin-btn-secondary mt-4 w-full">
            <IconExternal class="h-4 w-4" />
            View on store
        </a>
    </div>
</template>
```

- [ ] **Step 2: Rewrite `ProductForm.vue`**

```vue
<script setup>
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';

defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
    genders: { type: Array, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form @submit.prevent="$emit('submit')">
        <div class="admin-card divide-y divide-brand-100">
            <FormSection title="Details" description="Name, address and where the product is listed.">
                <FormField label="Name" for="name" :error="form.errors.name">
                    <input id="name" v-model="form.name" class="admin-field" required />
                </FormField>
                <FormField label="URL slug" for="slug" hint="e.g. wolf-badge-tee" :error="form.errors.slug">
                    <input id="slug" v-model="form.slug" class="admin-field" required />
                </FormField>
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Category" for="category_id" :error="form.errors.category_id">
                        <select id="category_id" v-model="form.category_id" class="admin-field" required>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Gender" for="gender" :error="form.errors.gender">
                        <select id="gender" v-model="form.gender" class="admin-field">
                            <option :value="null">None (not clothing)</option>
                            <option v-for="gender in genders" :key="gender.value" :value="gender.value">{{ gender.label }}</option>
                        </select>
                    </FormField>
                </div>
            </FormSection>
            <FormSection title="Pricing">
                <FormField label="Price (₹)" for="base_price" hint="Variants can override this price." :error="form.errors.base_price">
                    <input id="base_price" v-model="form.base_price" type="number" min="1" step="0.01" class="admin-field w-40" required />
                </FormField>
            </FormSection>
            <FormSection title="Description">
                <FormField label="Description" for="description" :error="form.errors.description">
                    <textarea id="description" v-model="form.description" rows="6" class="admin-field" />
                </FormField>
            </FormSection>
            <FormSection title="Visibility">
                <label class="flex items-center gap-3 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="admin-checkbox" />
                    Visible on the store
                </label>
            </FormSection>
        </div>
        <SaveBar :form="form" />
    </form>
</template>
```

- [ ] **Step 3: Rewrite `Products/Edit.vue`**

```vue
<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import ProductForm from './Partials/ProductForm.vue';
import ProductPreview from './Partials/ProductPreview.vue';
import ImageUploader from '@/Components/Admin/ImageUploader.vue';
import VariantGrid from './Partials/VariantGrid.vue';
import { toPaise } from '@/money';

const props = defineProps({
    product: { type: Object, required: true },
    variants: { type: Array, required: true },
    categories: { type: Array, required: true },
    genders: { type: Array, required: true },
    images: { type: Array, required: true },
    colours: { type: Array, required: true },
});

const form = useForm({ ...props.product, base_price: props.product.base_price / 100 });
const isPreviewOpen = ref(false);

const submit = () => {
    form.transform((data) => ({ ...data, base_price: toPaise(data.base_price) }))
        .put(route('admin.products.update', props.product.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <AdminLayout>
        <template #header>
            <PageHeader :title="product.name" :back="{ label: 'Products', href: route('admin.products.index') }" />
        </template>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="min-w-0 space-y-6">
                <button type="button" class="admin-btn-secondary lg:hidden" aria-controls="product-preview-inline" :aria-expanded="isPreviewOpen" @click="isPreviewOpen = !isPreviewOpen">
                    {{ isPreviewOpen ? 'Hide preview' : 'Preview' }}
                </button>
                <ProductPreview
                    v-if="isPreviewOpen"
                    id="product-preview-inline"
                    class="lg:hidden"
                    :form="form"
                    :variants="variants"
                    :images="images"
                    :view-url="route('products.show', product.slug)"
                />
                <ProductForm :form="form" :categories="categories" :genders="genders" @submit="submit" />
                <VariantGrid :product="product" :variants="variants" />
                <ImageUploader
                    :images="images"
                    :colours="colours"
                    :upload-url="route('admin.products.images.store', product.id)"
                    :save-url="route('admin.products.images.update', product.id)"
                />
            </div>
            <aside class="hidden lg:block">
                <div class="sticky top-8">
                    <ProductPreview :form="form" :variants="variants" :images="images" :view-url="route('products.show', product.slug)" />
                </div>
            </aside>
        </div>
    </AdminLayout>
</template>
```

- [ ] **Step 4: Rewrite `Products/Create.vue`**

```vue
<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import ProductForm from './Partials/ProductForm.vue';
import ProductPreview from './Partials/ProductPreview.vue';
import { toPaise } from '@/money';

const props = defineProps({
    categories: { type: Array, required: true },
    genders: { type: Array, required: true },
});

const form = useForm({
    name: '',
    slug: '',
    category_id: props.categories[0]?.id ?? null,
    gender: null,
    description: '',
    base_price: '',
    is_active: true,
});
const isPreviewOpen = ref(false);

const submit = () => {
    form.transform((data) => ({ ...data, base_price: toPaise(data.base_price) })).post(route('admin.products.store'));
};
</script>

<template>
    <Head title="New product" />

    <AdminLayout>
        <template #header>
            <PageHeader title="New product" :back="{ label: 'Products', href: route('admin.products.index') }" />
        </template>

        <div v-if="categories.length === 0" class="admin-card px-4 py-12 text-center text-sm text-brand-600">
            Create a category first.
            <Link :href="route('admin.categories.create')" class="focus-ring ml-1 rounded font-medium text-ink underline">New category</Link>
        </div>
        <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="min-w-0 space-y-6">
                <button type="button" class="admin-btn-secondary lg:hidden" aria-controls="product-preview-inline" :aria-expanded="isPreviewOpen" @click="isPreviewOpen = !isPreviewOpen">
                    {{ isPreviewOpen ? 'Hide preview' : 'Preview' }}
                </button>
                <ProductPreview v-if="isPreviewOpen" id="product-preview-inline" class="lg:hidden" :form="form" />
                <ProductForm :form="form" :categories="categories" :genders="genders" @submit="submit" />
            </div>
            <aside class="hidden lg:block">
                <div class="sticky top-8">
                    <ProductPreview :form="form" />
                </div>
            </aside>
        </div>
    </AdminLayout>
</template>
```

- [ ] **Step 5: Restyle `VariantGrid.vue`**

In `<script setup>`: delete the two imports `InputError` and `PrimaryButton`, and change the last line to:

```js
const cell = 'admin-field';
```

Replace the whole `<template>` with:

```vue
<template>
    <section class="admin-card p-6">
        <h2 class="font-medium">Variants</h2>
        <p class="mt-1 text-sm text-brand-600">
            Type sizes and colours separated by commas, then Generate. Leave both empty for a single-variant product.
            Variants can't be deleted; untick Active to hide one.
        </p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm font-medium text-ink">
                Sizes
                <input v-model="sizes" placeholder="S, M, L, XL" :class="cell" class="mt-1.5 sm:w-56" />
            </label>
            <label class="text-sm font-medium text-ink">
                Colours
                <input v-model="colours" placeholder="Black, Cream" :class="cell" class="mt-1.5 sm:w-56" />
            </label>
            <button type="button" class="admin-btn-secondary" @click="generate">Generate</button>
        </div>

        <p v-if="form.errors.variants" class="mt-4 text-sm text-red-700">{{ form.errors.variants }}</p>

        <form class="mt-4" @submit.prevent="save">
            <div class="overflow-x-auto rounded-lg border border-brand-200">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-brand-200 bg-brand-50/60 text-xs uppercase tracking-wide text-brand-500">
                        <tr>
                            <th scope="col" class="px-3 py-2 font-medium">Colour</th>
                            <th scope="col" class="px-3 py-2 font-medium">Size</th>
                            <th scope="col" class="px-3 py-2 font-medium">SKU</th>
                            <th scope="col" class="px-3 py-2 font-medium">Price override (₹)</th>
                            <th scope="col" class="px-3 py-2 font-medium">Stock</th>
                            <th scope="col" class="px-3 py-2 font-medium">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-100">
                        <tr v-for="(variant, index) in form.variants" :key="variant.id ?? `new-${index}`" class="align-top">
                            <td class="px-3 py-2 pt-4">{{ variant.colour ?? '—' }}</td>
                            <td class="px-3 py-2 pt-4">{{ variant.size ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <input v-model="variant.sku" :class="cell" class="font-mono text-xs" :aria-label="`SKU row ${index + 1}`" required />
                                <p v-if="form.errors[`variants.${index}.sku`]" class="mt-1 text-xs text-red-700">{{ form.errors[`variants.${index}.sku`] }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <input v-model="variant.price_override" type="number" min="1" step="0.01" placeholder="Base price" :class="cell" :aria-label="`Price override row ${index + 1}`" />
                                <p v-if="form.errors[`variants.${index}.price_override`]" class="mt-1 text-xs text-red-700">{{ form.errors[`variants.${index}.price_override`] }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <input v-model="variant.stock" type="number" min="0" :class="cell" :aria-label="`Stock row ${index + 1}`" required />
                                <p v-if="form.errors[`variants.${index}.stock`]" class="mt-1 text-xs text-red-700">{{ form.errors[`variants.${index}.stock`] }}</p>
                            </td>
                            <td class="px-3 py-2 pt-4">
                                <input v-model="variant.is_active" type="checkbox" class="admin-checkbox" :aria-label="`Active row ${index + 1}`" />
                            </td>
                        </tr>
                        <tr v-if="form.variants.length === 0">
                            <td colspan="6" class="px-3 py-8 text-center text-brand-500">No variants yet. Generate some above.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="admin-btn mt-4" :disabled="form.processing || form.variants.length === 0">Save variants</button>
        </form>
    </section>
</template>
```

- [ ] **Step 6: Restyle `ImageUploader.vue`**

In `<script setup>`: delete the `PrimaryButton` import and change the last line to:

```js
const field = 'admin-field';
```

Replace the whole `<template>` with:

```vue
<template>
    <section class="admin-card p-6">
        <h2 class="font-medium">Images</h2>
        <p class="mt-1 text-sm text-brand-600">JPG, PNG or WebP, up to 8 MB each. The first image is the listing photo.</p>

        <label class="admin-btn-secondary mt-4 cursor-pointer focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-700">
            {{ upload.processing ? 'Uploading…' : 'Add images' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" :disabled="upload.processing" @change="onFiles" />
        </label>
        <ul v-if="Object.keys(upload.errors).length" class="mt-2 text-sm text-red-700">
            <li v-for="(message, key) in upload.errors" :key="key">{{ message }}</li>
        </ul>

        <form v-if="form.images.length" class="mt-6" @submit.prevent="save">
            <ul class="divide-y divide-brand-100 rounded-lg border border-brand-200">
                <li v-for="(image, index) in form.images" :key="image.id" class="flex flex-wrap items-center gap-4 p-3">
                    <div class="relative">
                        <img :src="byId[image.id]?.url" :alt="image.alt" class="h-20 w-20 rounded-lg object-cover" />
                        <span v-if="index === 0" class="absolute left-1 top-1 rounded bg-ink/80 px-1.5 py-0.5 text-[10px] font-medium text-brand-50">Listing</span>
                    </div>
                    <div class="min-w-[12rem] flex-1 space-y-2">
                        <input v-model="image.alt" placeholder="Alt text" :class="field" :aria-label="`Alt text image ${index + 1}`" />
                        <select v-model="image.colour" :class="field" :aria-label="`Colour image ${index + 1}`">
                            <option :value="null">All colours</option>
                            <option v-for="colour in colours" :key="colour" :value="colour">{{ colour }}</option>
                        </select>
                    </div>
                    <div class="flex gap-1">
                        <button type="button" class="admin-btn-secondary min-h-8 px-2" :disabled="index === 0" aria-label="Move up" @click="move(index, -1)">↑</button>
                        <button type="button" class="admin-btn-secondary min-h-8 px-2" :disabled="index === form.images.length - 1" aria-label="Move down" @click="move(index, 1)">↓</button>
                        <button type="button" class="admin-btn-danger min-h-8 px-2" @click="remove(image)">Delete</button>
                    </div>
                </li>
            </ul>
            <button type="submit" class="admin-btn mt-4" :disabled="form.processing">Save images</button>
        </form>
    </section>
</template>
```

- [ ] **Step 7: Build and test**

Run: `npm run build`
Expected: success.
Run: `php artisan test --compact tests/Feature/Admin/ProductManagementTest.php tests/Feature/Admin/ProductVariantTest.php tests/Feature/Admin/ProductImageTest.php`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Admin/Products resources/js/Components/Admin/ImageUploader.vue
git commit -m "feat: product forms with sections and a live storefront card preview"
```

---

### Task 10: Page editor with Edit / Preview tabs

**Files:**
- Create: `resources/js/xsrfToken.js`
- Create: `resources/js/Components/Store/Blocks/PageBlocks.vue`
- Create: `resources/js/Pages/Admin/Pages/Partials/PagePreview.vue`
- Modify (rewrite): `resources/js/Pages/Content/Show.vue`
- Modify (rewrite): `resources/js/Pages/Admin/Pages/Partials/PageForm.vue`, `Pages/Create.vue`, `Pages/Edit.vue`
- Modify: `resources/js/Pages/Admin/Pages/Blocks/Field.vue`, `ImageField.vue`, `ContactCardsFields.vue`, `FaqFields.vue`, `FeaturesFields.vue`, `TableFields.vue`

**Interfaces:**
- Consumes: `admin.pages.preview` (Task 2); `FormField`, `FormSection`, `PageHeader`, `SaveBar`, `IconAlert`, `IconExternal` (Tasks 3, 8); `withoutUids` from `Pages/Admin/Pages/Blocks/blockTypes.js`.
- Produces: `PageBlocks` props `{ title: String (req), blocks: Array (req) }` (rendered storefront blocks). `xsrfToken(): string`. `PagePreview` props `{ form: Object (req) }`, emits `edit`.

- [ ] **Step 1: Extract the CSRF helper**

`resources/js/xsrfToken.js`:

```js
// Laravel sets this cookie on every response and accepts it back as a CSRF header.
export const xsrfToken = () => decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
```

In `Pages/Admin/Pages/Blocks/ImageField.vue`, delete the comment line and the `const xsrfToken = …` line, and add `import { xsrfToken } from '@/xsrfToken';` to the imports.

- [ ] **Step 2: Extract `PageBlocks.vue` from `Content/Show`**

`resources/js/Components/Store/Blocks/PageBlocks.vue`:

```vue
<script setup>
import Reveal from '@/Components/Store/Reveal.vue';
import CallToActionBlock from './CallToActionBlock.vue';
import ContactCardsBlock from './ContactCardsBlock.vue';
import FaqBlock from './FaqBlock.vue';
import FeaturesBlock from './FeaturesBlock.vue';
import HeroBlock from './HeroBlock.vue';
import ImageTextBlock from './ImageTextBlock.vue';
import TableBlock from './TableBlock.vue';
import TextBlock from './TextBlock.vue';

defineProps({
    title: { type: String, required: true },
    blocks: { type: Array, required: true },
});

// Keys must match PageBlockType values.
const components = {
    hero: HeroBlock,
    text: TextBlock,
    image_text: ImageTextBlock,
    features: FeaturesBlock,
    table: TableBlock,
    faq: FaqBlock,
    contact_cards: ContactCardsBlock,
    call_to_action: CallToActionBlock,
};
</script>

<template>
    <div>
        <HeroBlock v-if="blocks[0]?.type !== 'hero'" :data="{ eyebrow: 'Arusa Apparel Co.', heading: title }" />

        <div class="pb-16 sm:pb-24">
            <template v-for="(block, index) in blocks" :key="index">
                <HeroBlock v-if="index === 0 && block.type === 'hero'" :data="block.data" />
                <Reveal v-else class="mt-14 sm:mt-20">
                    <component :is="components[block.type]" :data="block.data" v-bind="block.type === 'hero' ? { headingLevel: 'h2' } : {}" />
                </Reveal>
            </template>
        </div>
    </div>
</template>
```

Before replacing `Content/Show.vue`, open it and confirm its template contains only `<Head>`, `<StoreLayout>`, and the hero plus block loop copied above. If it has anything else, keep that around `<PageBlocks>`. Then replace it with:

```vue
<script setup>
import { Head } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import PageBlocks from '@/Components/Store/Blocks/PageBlocks.vue';

defineProps({
    title: { type: String, required: true },
    blocks: { type: Array, required: true },
});
</script>

<template>
    <Head :title="title" />

    <StoreLayout>
        <PageBlocks :title="title" :blocks="blocks" />
    </StoreLayout>
</template>
```

- [ ] **Step 3: Create `PagePreview.vue`**

`resources/js/Pages/Admin/Pages/Partials/PagePreview.vue`:

```vue
<script setup>
import { onMounted, ref } from 'vue';
import PageBlocks from '@/Components/Store/Blocks/PageBlocks.vue';
import IconAlert from '@/Components/Icons/IconAlert.vue';
import { withoutUids } from '../Blocks/blockTypes.js';
import { xsrfToken } from '@/xsrfToken';

const props = defineProps({
    form: { type: Object, required: true },
});

defineEmits(['edit']);

const state = ref('loading');
const blocks = ref([]);

const load = async () => {
    state.value = 'loading';

    try {
        const response = await fetch(route('admin.pages.preview'), {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify({ blocks: withoutUids({ blocks: props.form.blocks }).blocks }),
        });

        if (response.status === 422) {
            const { errors } = await response.json();
            props.form.clearErrors(...Object.keys(props.form.errors).filter((key) => key.startsWith('blocks')));
            props.form.setError(Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]])));
            state.value = 'invalid';
        } else if (response.ok) {
            blocks.value = (await response.json()).blocks;
            state.value = 'ready';
        } else {
            state.value = 'failed';
        }
    } catch {
        state.value = 'failed';
    }
};

onMounted(load);
</script>

<template>
    <div>
        <div v-if="state === 'loading'" class="space-y-4 p-4" aria-busy="true" aria-label="Loading preview">
            <div class="h-48 animate-pulse rounded-lg bg-brand-100 motion-reduce:animate-none" />
            <div class="h-4 w-2/3 animate-pulse rounded bg-brand-100 motion-reduce:animate-none" />
            <div class="h-4 w-1/2 animate-pulse rounded bg-brand-100 motion-reduce:animate-none" />
        </div>
        <div v-else-if="state === 'invalid'" class="flex flex-col items-center gap-3 px-6 py-12 text-center text-sm text-brand-700" role="alert">
            <IconAlert class="h-6 w-6 text-amber-600" />
            Fix the highlighted fields to preview.
            <button type="button" class="admin-btn-secondary" @click="$emit('edit')">Back to edit</button>
        </div>
        <div v-else-if="state === 'failed'" class="flex flex-col items-center gap-3 px-6 py-12 text-center text-sm text-brand-700" role="alert">
            <IconAlert class="h-6 w-6 text-red-600" />
            Preview unavailable, try again.
            <button type="button" class="admin-btn-secondary" @click="load">Try again</button>
        </div>
        <div v-else class="overflow-hidden rounded-lg border border-brand-200 bg-brand-50 font-sans text-ink">
            <PageBlocks :title="form.title || 'Untitled page'" :blocks="blocks" />
        </div>
    </div>
</template>
```

- [ ] **Step 4: Rewrite `PageForm.vue`**

```vue
<script setup>
import { provide, ref } from 'vue';
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';
import PagePreview from './PagePreview.vue';
import { blockTypes, newBlock } from '../Blocks/blockTypes.js';
import { fieldClass, removeButton } from '../Blocks/fieldOptions.js';

const props = defineProps({
    form: { type: Object, required: true },
    imageBaseUrl: { type: String, required: true },
});

defineEmits(['submit']);

provide('imageBaseUrl', props.imageBaseUrl);

const newType = ref('text');
const tab = ref('edit');

const tabs = [
    { value: 'edit', label: 'Edit' },
    { value: 'preview', label: 'Preview' },
];

const move = (index, offset) => {
    const [block] = props.form.blocks.splice(index, 1);
    props.form.blocks.splice(index + offset, 0, block);
    props.form.clearErrors();
};

const remove = (index) => {
    if (window.confirm(`Remove this ${blockTypes[props.form.blocks[index].type].label} block?`)) {
        props.form.blocks.splice(index, 1);
        props.form.clearErrors();
    }
};

const hasErrors = (index) => Object.keys(props.form.errors).some((key) => key.startsWith(`blocks.${index}.`));
</script>

<template>
    <form class="max-w-4xl" @submit.prevent="$emit('submit')">
        <div class="admin-card">
            <FormSection title="Page" description="The title shows in the browser tab and in the default banner.">
                <FormField label="Title" for="title" :error="form.errors.title">
                    <input id="title" v-model="form.title" class="admin-field" required />
                </FormField>
                <FormField label="URL slug" for="slug" hint="e.g. size-guide" :error="form.errors.slug">
                    <input id="slug" v-model="form.slug" class="admin-field" required />
                </FormField>
            </FormSection>
        </div>

        <section class="admin-card mt-6">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-brand-200 px-6 pt-5">
                <div class="pb-3">
                    <h2 class="font-medium">Sections</h2>
                    <p class="mt-1 text-sm text-brand-600">Shown on the page from top to bottom. Start with a Hero for a large title banner.</p>
                </div>
                <div role="tablist" aria-label="Sections view" class="flex gap-1">
                    <button
                        v-for="option in tabs"
                        :id="`sections-tab-${option.value}`"
                        :key="option.value"
                        type="button"
                        role="tab"
                        aria-controls="sections-panel"
                        :aria-selected="tab === option.value"
                        :class="tab === option.value ? 'border-ink text-ink' : 'border-transparent text-brand-500 hover:text-ink'"
                        class="focus-ring -mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors duration-150 motion-reduce:transition-none"
                        @click="tab = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div id="sections-panel" role="tabpanel" :aria-labelledby="`sections-tab-${tab}`" :class="tab === 'edit' ? 'p-6' : 'p-3'">
                <template v-if="tab === 'edit'">
                    <ol class="space-y-4">
                        <li
                            v-for="(block, index) in form.blocks"
                            :key="block.uid"
                            class="rounded-xl border"
                            :class="hasErrors(index) ? 'border-red-300 bg-red-50/30' : 'border-brand-200'"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-brand-100 px-4 py-3">
                                <h3 class="flex items-center gap-2 text-sm font-semibold">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-xs tabular-nums text-brand-700">{{ index + 1 }}</span>
                                    {{ blockTypes[block.type].label }}
                                </h3>
                                <div class="flex gap-1">
                                    <button type="button" class="admin-btn-secondary min-h-8 px-2" :disabled="index === 0" aria-label="Move up" @click="move(index, -1)">↑</button>
                                    <button type="button" class="admin-btn-secondary min-h-8 px-2" :disabled="index === form.blocks.length - 1" aria-label="Move down" @click="move(index, 1)">↓</button>
                                    <button type="button" :class="removeButton" @click="remove(index)">Remove</button>
                                </div>
                            </div>
                            <component :is="blockTypes[block.type].fields" class="p-4" :data="block.data" :errors="form.errors" :prefix="`blocks.${index}.data`" />
                        </li>
                    </ol>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <select v-model="newType" :class="fieldClass" class="w-auto" aria-label="Section type">
                            <option v-for="(type, key) in blockTypes" :key="key" :value="key">{{ type.label }}</option>
                        </select>
                        <button type="button" class="admin-btn-secondary" :disabled="form.blocks.length >= 30" @click="form.blocks.push(newBlock(newType))">Add section</button>
                    </div>
                    <p v-if="form.errors.blocks" class="mt-2 text-sm text-red-700">{{ form.errors.blocks }}</p>
                </template>
                <PagePreview v-else :form="form" @edit="tab = 'edit'" />
            </div>
        </section>

        <SaveBar :form="form" />
    </form>
</template>
```

- [ ] **Step 5: Update the page Create and Edit headers**

In `Pages/Create.vue`, add `import PageHeader from '@/Components/Admin/PageHeader.vue';` and replace the `#header` contents with:

```vue
            <PageHeader title="New page" :back="{ label: 'Pages', href: route('admin.pages.index') }" />
```

In `Pages/Edit.vue`, replace the `Link` import line with `import { Head, useForm } from '@inertiajs/vue3';`, add `import PageHeader from '@/Components/Admin/PageHeader.vue';` and `import IconExternal from '@/Components/Icons/IconExternal.vue';`, and replace the `#header` contents with:

```vue
            <PageHeader :title="page.title" :back="{ label: 'Pages', href: route('admin.pages.index') }">
                <template #actions>
                    <a :href="route('pages.show', page.slug)" target="_blank" rel="noopener" class="admin-btn-secondary">
                        <IconExternal class="h-4 w-4" />
                        View on store
                    </a>
                </template>
            </PageHeader>
```

- [ ] **Step 6: Move the block field components off Breeze**

`Blocks/Field.vue`: replace it with:

```vue
<script setup>
import FormField from '@/Components/Admin/FormField.vue';
import { fieldClass } from './fieldOptions.js';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    error: { type: String, default: null },
    rows: { type: Number, default: 0 },
    options: { type: Array, default: null },
});

const value = defineModel({ type: String, default: '' });
</script>

<template>
    <FormField :label="label" :for="id" :error="error">
        <select v-if="options" :id="id" v-model="value" :class="fieldClass">
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <textarea v-else-if="rows" :id="id" v-model="value" :rows="rows" :class="fieldClass" />
        <input v-else :id="id" v-model="value" :class="fieldClass" />
    </FormField>
</template>
```

`Blocks/ImageField.vue` (template and imports only; the upload logic stays): delete `import InputError from '@/Components/InputError.vue';` and replace the `<template>` with:

```vue
<template>
    <div>
        <p class="admin-label">{{ label }}</p>
        <div class="mt-1.5 flex flex-wrap items-center gap-3">
            <img v-if="path" :src="`${imageBaseUrl}${path}-800.webp`" alt="" class="h-20 w-20 rounded-lg object-cover" />
            <label class="admin-btn-secondary cursor-pointer focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-700">
                {{ uploading ? 'Uploading…' : path ? 'Replace image' : 'Upload image' }}
                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="uploading" @change="onFile" />
            </label>
            <button v-if="path && clearable" type="button" class="admin-btn-danger min-h-8 px-2" @click="path = null">Remove image</button>
        </div>
        <p v-if="uploadError ?? error" class="mt-1.5 text-sm text-red-700">{{ uploadError ?? error }}</p>
    </div>
</template>
```

`Blocks/ContactCardsFields.vue`, `Blocks/FaqFields.vue`, `Blocks/FeaturesFields.vue`: in each, delete `import InputError from '@/Components/InputError.vue';` and make these three template edits:
- the `<legend class="text-sm font-medium text-gray-700">` becomes `<legend class="text-sm font-medium text-ink">`
- each repeated item box's `rounded border border-gray-200 p-3` becomes `rounded-lg border border-brand-200 bg-brand-50/40 p-3` (keep the other classes on that element, such as `grid gap-3 sm:grid-cols-3` or `space-y-3`)
- `<InputError :message="error('items')" />` becomes `<p v-if="error('items')" class="text-sm text-red-700">{{ error('items') }}</p>`

`Blocks/TableFields.vue`: change `<ul v-if="tableErrors.length" class="text-sm text-red-600">` to `<ul v-if="tableErrors.length" class="text-sm text-red-700">`.

- [ ] **Step 7: Build and test**

Run: `npm run build`
Expected: success.
Run: `php artisan test --compact tests/Feature/Admin/PageManagementTest.php tests/Feature/Admin/PageBlocksTest.php tests/Feature/Admin/PageImageTest.php tests/Feature/Admin/PagePreviewTest.php`
Expected: all PASS.
Run: `php artisan test --compact tests/Feature/ContentPageTest.php`
Expected: all PASS (the storefront `Content/Show` page still renders).

- [ ] **Step 8: Commit**

```bash
git add resources/js/xsrfToken.js resources/js/Components/Store/Blocks/PageBlocks.vue resources/js/Pages/Content/Show.vue resources/js/Pages/Admin/Pages
git commit -m "feat: page editor with section cards and an Edit / Preview tab using the storefront blocks"
```

---

### Task 11: Final verification

**Files:** none new (fixes only, if a check fails).

- [ ] **Step 1: No Breeze in admin**

Run: `grep -rnE "Components/(TextInput|InputLabel|InputError|PrimaryButton|Checkbox|ApplicationLogo)" resources/js/Pages/Admin resources/js/Components/Admin resources/js/Layouts/AdminLayout.vue`
Expected: no output.

Run: `grep -rnE "indigo-|gray-" resources/js/Pages/Admin resources/js/Components/Admin resources/js/Layouts/AdminLayout.vue`
Expected: no output. Fix any leftovers with the matching `brand-*` / `.admin-*` class.

- [ ] **Step 2: Build, format and run the tests**

Run: `npm run build`
Expected: success.
Run: `vendor/bin/pint --dirty --format agent`
Run: `php artisan test --compact tests/Feature/Admin`
Expected: all PASS.

- [ ] **Step 3: Browser check with Playwright**

Get the base URL with the Boost `get-absolute-url` tool, then log in as an admin. Check each item at **1280 px** and again at **390 px** wide:

1. Dashboard: tiles, KPIs, the chart (hover a bar to show the tooltip), today's orders with badges, low stock.
2. Below `md`: the menu button opens the drawer, Esc and the backdrop close it, and focus returns to the menu button.
3. Orders index: badges render, the mobile cards show every column, and the "Filters" toggle opens the filters.
4. Order show for a **pending COD**, a **delivered** and a **cancelled** order (`EdgeCaseSeeder` creates an order in every status; run `php artisan db:seed --class=EdgeCaseSeeder` only if the local DB has none of them, and ask the user first). Check the stepper states, the next-step card and the timeline (latest dot highlighted).
5. Product edit: typing a name or price updates the preview card; unticking Visible shows "Hidden from store"; Save shows a toast and the save bar's "Unsaved changes" appears and clears.
6. Page edit: Preview renders the blocks. Clearing a required Text block and opening Preview shows "Fix the highlighted fields", and the field is red on the Edit tab.
7. Categories, zones, settings: sections lay out and save shows a toast.
8. `browser_console_messages`: no errors on any page.

Also open one storefront content page (`/pages/{slug}`) and confirm it looks unchanged after the `PageBlocks` extraction.

- [ ] **Step 4: Commit any fixes, then hand off**

```bash
git add -A
git commit -m "fix: admin redesign polish from browser check"
```

(Skip this commit if nothing changed.) Ask the user to run the full suite with `php artisan test --compact`, then open the PR.
