# Storefront Pass 2 (Remaining User Pages) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the auth pages, order tracking, bag, checkout, order page, account, profile and content pages up to the pass-1 editorial look, reusing its motion kit.

**Architecture:** Frontend only. One new component, `Components/Store/PageHeading.vue`, replaces the hand-written page `h1`s. `GuestLayout.vue` becomes a split layout (hero photo + form) while keeping its `title` prop, so the seven pages on it are untouched. Every other page gets class/wrapper changes plus pass-1 `animate-rise-in` and `Reveal.vue`. Spec: `docs/superpowers/specs/2026-10-24-storefront-pass-2-design.md`. Builds on pass 1: `docs/superpowers/plans/2026-10-24-storefront-motion.md`.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v2 + Vue 3, Tailwind 3, Pest.

## Global Constraints

- Frontend only: no new props, routes, validation, controller or PHP changes.
- `Checkout/Create.vue` and `Orders/Show.vue` are money-critical: change only classes and wrappers. Never touch form fields, `v-model`s, handlers, `computed`/`watch`, button `:disabled` logic, labels or the Razorpay code.
- No new npm or composer dependencies. Motion = pass-1 pieces only: `animate-rise-in` (delays via `[animation-delay:…]`), `animate-rule-draw`, and `Components/Store/Reveal.vue` (props `as: string = 'div'`, `delay: number = 0` ms). No new keyframes.
- Durations 150–300 ms; animate only `opacity` and `transform`. Stagger delays are capped: `Math.min(index, 4) * 75`.
- `prefers-reduced-motion: reduce`: everything visible, no animation (pass-1 CSS and `Reveal.vue` already handle this).
- No global smooth scroll (removed on purpose in pass 1; Inertia visits must stay instant). Do not add `scroll-behavior` anywhere.
- Touch targets ≥ 44 px (`min-h-11`); keep `focus-ring` / `focus-ring-inverse` on interactive elements.
- Images: WebP + `srcset`; `loading="lazy"` except above the fold. Hero files: `/images/hero-800.webp`, `/images/hero-1600.webp`. `ApplicationLogo` takes `on-dark`; a `sizes` attr overrides its 64 px default.
- Vue components: single root element.
- Styling-only tasks get no new Pest tests. Each task runs the existing suites named in it plus `npm run build`.
- Never run `migrate:fresh` (the local DB has real orders). Never delete `public/hot` (it points at the running Vite dev server).
- Work on branch `feat/storefront-pass-2`, not `master`. Run `git merge` / `git checkout` as separate commands.

---

### Task 1: PageHeading component and split GuestLayout

**Files:**
- Create: `resources/js/Components/Store/PageHeading.vue`
- Modify: `resources/js/Layouts/GuestLayout.vue` (whole file)

Pages affected without edits: `Auth/Login`, `Auth/Register`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/VerifyEmail`, `Auth/ConfirmPassword`, `Orders/Track` (all use `<GuestLayout title="…">` and stay as they are).

**Interfaces:**
- Consumes: `ApplicationLogo` (`on-dark` prop), `animate-rise-in`, `animate-rule-draw`, `$page.props.store.store_name` (shared by `HandleInertiaRequests`).
- Produces: `PageHeading` with props `title: String` (required) and `eyebrow: String | null` (default `null`); single root `div`, so `class` falls through (e.g. `class="tabular-nums"` or `class="mt-4"`). `GuestLayout` keeps `title: String` (required) and its default slot.

- [ ] **Step 1: Create the branch**

```bash
git checkout -b feat/storefront-pass-2
```

- [ ] **Step 2: Create `resources/js/Components/Store/PageHeading.vue`**

```vue
<script setup>
defineProps({
    title: { type: String, required: true },
    eyebrow: { type: String, default: null },
});
</script>

<template>
    <div class="animate-rise-in">
        <p v-if="eyebrow" class="text-xs uppercase tracking-[0.3em] text-brand-600">{{ eyebrow }}</p>
        <h1 class="font-display text-3xl font-semibold sm:text-4xl" :class="{ 'mt-1': eyebrow }">{{ title }}</h1>
        <span class="mt-4 block h-px w-10 bg-brand-400" aria-hidden="true" />
    </div>
</template>
```

- [ ] **Step 3: Replace `resources/js/Layouts/GuestLayout.vue`**

Below `lg` the photo is a 160 px dark band with the badge only; from `lg` it is the left half, filling the viewport under the 64 px header. The wordmark is a `<p>`, not a heading: the page's one `h1` comes from `PageHeading`. `sizes` is `(min-width: 1024px) 50vw, 100vw` rather than the spec's bare `50vw`, because below `lg` the band is full width.

```vue
<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import PageHeading from '@/Components/Store/PageHeading.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';

defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <StoreLayout>
        <div class="lg:grid lg:min-h-[calc(100dvh-4rem)] lg:grid-cols-2">
            <div class="relative isolate flex h-40 items-center justify-center overflow-hidden bg-brand-900 text-brand-50 lg:h-auto">
                <img
                    src="/images/hero-1600.webp"
                    srcset="/images/hero-800.webp 800w, /images/hero-1600.webp 1600w"
                    sizes="(min-width: 1024px) 50vw, 100vw"
                    alt=""
                    class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
                />
                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-900 via-brand-900/60 to-brand-900/20" aria-hidden="true" />

                <div class="flex flex-col items-center text-center">
                    <ApplicationLogo on-dark sizes="112px" class="h-20 w-20 animate-rise-in lg:h-28 lg:w-28" />
                    <p class="mt-6 hidden animate-rise-in font-display text-5xl font-semibold uppercase tracking-[0.12em] [animation-delay:150ms] lg:block">
                        {{ $page.props.store.store_name }}
                    </p>
                    <p class="mt-3 hidden animate-rise-in text-sm font-medium uppercase tracking-[0.3em] text-brand-200 [animation-delay:300ms] lg:block">
                        Apparel Co.
                    </p>
                    <span class="mt-6 hidden h-px w-16 animate-rule-draw bg-brand-400 [animation-delay:300ms] lg:block" aria-hidden="true" />
                </div>
            </div>

            <div class="flex items-center">
                <div class="mx-auto w-full max-w-md px-4 py-10 sm:py-16">
                    <PageHeading :title="title" class="mb-8" />
                    <slot />
                </div>
            </div>
        </div>
    </StoreLayout>
</template>
```

- [ ] **Step 4: Build and run the affected tests**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --compact tests/Feature/Auth tests/Feature/Storefront/TrackOrderTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Store/PageHeading.vue resources/js/Layouts/GuestLayout.vue
git commit -m "feat: page heading component and split auth layout"
```

---

### Task 2: Bag page

**Files:**
- Modify: `resources/js/Pages/Cart/Index.vue`

**Interfaces:**
- Consumes: `PageHeading` (Task 1: `title`, `eyebrow`), `Reveal` (`as`, `delay`), `ApplicationLogo`. The home page section has `id="new-in"` (pass 1).

- [ ] **Step 1: Imports**

Replace the import block at the top of `<script setup>`:

```js
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import PageHeading from '@/Components/Store/PageHeading.vue';
import Reveal from '@/Components/Store/Reveal.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';
```

- [ ] **Step 2: Heading**

Replace `<h1 class="font-display text-3xl font-semibold sm:text-4xl">Your bag</h1>` with:

```vue
            <PageHeading title="Your bag" />
```

- [ ] **Step 3: Staggered lines**

Replace the `<li v-for="line in page.props.cart.lines" …>` opening tag and its closing `</li>` (inner markup unchanged):

```vue
                    <Reveal
                        v-for="(line, index) in page.props.cart.lines"
                        :key="line.variant_id"
                        as="li"
                        :delay="Math.min(index, 4) * 75"
                        class="flex gap-4 py-5"
                    >
```

```vue
                    </Reveal>
```

- [ ] **Step 4: Continue shopping next to Checkout**

Replace the checkout `<Link v-if="!page.props.cart.has_unavailable && page.props.cart.count" …>Checkout</Link>` with:

```vue
                    <div class="flex flex-col-reverse items-stretch gap-4 sm:flex-row sm:items-center">
                        <Link :href="route('home')" class="store-link inline-flex min-h-11 items-center justify-center">Continue shopping</Link>
                        <Link
                            v-if="!page.props.cart.has_unavailable && page.props.cart.count"
                            :href="route('checkout.create')"
                            class="flex min-h-12 items-center justify-center bg-ink px-12 text-xs font-medium uppercase tracking-[0.2em] text-brand-50 focus-ring"
                        >
                            Checkout
                        </Link>
                    </div>
```

- [ ] **Step 5: Empty state**

Replace the `<div v-else class="mt-12 text-center">…</div>` block:

```vue
            <div v-else class="mt-12 flex flex-col items-center text-center">
                <ApplicationLogo class="h-16 w-16" />
                <p class="mt-6 text-brand-700">Your bag is empty.</p>
                <Link :href="route('home') + '#new-in'" class="store-link mt-4 inline-flex min-h-11 items-center">Shop new in</Link>
            </div>
```

- [ ] **Step 6: Build and run the affected tests**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --compact tests/Feature/Storefront/CartTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Cart/Index.vue
git commit -m "feat: bag page heading, line reveal and empty state"
```

---

### Task 3: Checkout page (cosmetic only)

**Files:**
- Modify: `resources/js/Pages/Checkout/Create.vue` (template + one import; nothing else in `<script setup>`)

**Interfaces:**
- Consumes: `PageHeading` (Task 1). Cart lines already carry `line.image` (`{ src, srcset, alt }` or `null`) — the same shape `Cart/Index.vue` renders.

Money-critical: only the edits below. Do not change `useForm`, either `watch`, `liveQuote`, `methodAvailable`, `canPlaceOrder`, `buttonLabel`, `submit`, any input/`v-model`/`name`/`id`, the radio `:disabled` bindings, or the submit button's `:disabled` and label.

- [ ] **Step 1: Import**

Add after `import InputError from '@/Components/InputError.vue';`:

```js
import PageHeading from '@/Components/Store/PageHeading.vue';
```

- [ ] **Step 2: Heading**

Replace `<h1 class="font-display text-3xl font-semibold sm:text-4xl">Checkout</h1>` with:

```vue
                <PageHeading title="Checkout" />
```

- [ ] **Step 3: Numbered section legends**

The sections are `<fieldset>`s with `<legend>`s (the spec's "section h2s"). The number is `aria-hidden` so the accessible names stay "Contact", "Delivery address", "Payment". Replace the three legends:

```vue
                    <legend class="font-display text-xl font-semibold"><span class="mr-3 text-brand-500 tabular-nums" aria-hidden="true">01</span>Contact</legend>
```

```vue
                    <legend class="font-display text-xl font-semibold"><span class="mr-3 text-brand-500 tabular-nums" aria-hidden="true">02</span>Delivery address</legend>
```

```vue
                    <legend class="font-display text-xl font-semibold"><span class="mr-3 text-brand-500 tabular-nums" aria-hidden="true">03</span>Payment</legend>
```

- [ ] **Step 4: Reassurance line under the submit button**

Directly after the submit `</button>` (still inside the `<form>`), add. "Secure online payment" shows only when online payment is on, so the line never promises something the store has switched off:

```vue
                <p class="text-center text-xs text-brand-600">
                    Cash on delivery<template v-if="online_payment"> · Secure online payment</template> · Delivered across Mizoram
                </p>
```

- [ ] **Step 5: Summary thumbnails and sticky aside**

The grid splits into two columns at `md`, so the aside stays sticky from `md` (the spec says `lg`, but at `md`–`lg` the summary is already a side column); only the offset moves to `top-24`. Replace the `<aside …>` opening tag:

```vue
            <aside class="h-fit border border-brand-200 p-4 md:sticky md:top-24" aria-labelledby="summary-title">
```

Replace the summary `<li v-for="line in page.props.cart.lines" …>…</li>`:

```vue
                    <li v-for="line in page.props.cart.lines" :key="line.variant_id" class="flex gap-3">
                        <div class="h-[60px] w-12 shrink-0 overflow-hidden bg-brand-100">
                            <img v-if="line.image" :src="line.image.src" :srcset="line.image.srcset" sizes="48px" alt="" loading="lazy" class="h-full w-full object-cover" />
                        </div>
                        <div class="flex flex-1 justify-between gap-4">
                            <span>{{ line.name }}<span v-if="variantLabel(line)" class="text-brand-600"> · {{ variantLabel(line) }}</span> × {{ line.quantity }}</span>
                            <span class="tabular-nums">{{ formatPaise(line.line_total) }}</span>
                        </div>
                    </li>
```

- [ ] **Step 6: Check the script is untouched**

Run: `git diff resources/js/Pages/Checkout/Create.vue | grep '^[-+]' | grep -v '^[-+][-+]'`
Expected: the only changed script line is the added `PageHeading` import; every other changed line is template markup listed above.

- [ ] **Step 7: Build and run the affected tests**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --compact tests/Feature/Storefront/CheckoutPageTest.php tests/Feature/Storefront/PlaceOrderTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Checkout/Create.vue
git commit -m "feat: checkout heading, numbered steps and summary thumbnails"
```

---

### Task 4: Order page (cosmetic only)

**Files:**
- Modify: `resources/js/Pages/Orders/Show.vue` (template + two imports; nothing else in `<script setup>`)

**Interfaces:**
- Consumes: `PageHeading` (Task 1), `Reveal` (`as`, `delay`; extra attrs such as `class` and `aria-current` fall through to its root).

Money-critical: do not change `loadCheckout`, `pay`, `onMounted`, `paymentMessage`/`opening`, the pay button's `:disabled`, `@click` or label, or any `v-if`/`v-else-if` condition.

- [ ] **Step 1: Imports**

Add after `import StoreLayout from '@/Layouts/StoreLayout.vue';`:

```js
import PageHeading from '@/Components/Store/PageHeading.vue';
import Reveal from '@/Components/Store/Reveal.vue';
```

- [ ] **Step 2: Dark payment panel**

Replace the `<section v-if="payment" …>…</section>` block. Same condition, ids, message binding, handler and label; only classes change (`text-red-300` keeps the error readable on the dark panel; the button switches to `focus-ring-inverse`):

```vue
            <section v-if="payment" class="mb-8 bg-brand-900 p-6 text-brand-50" aria-labelledby="pay-title">
                <h2 id="pay-title" class="font-display text-2xl font-semibold">Complete your payment</h2>
                <p class="mt-1 text-sm text-brand-200">Your items are reserved for 30 minutes. Pay now to confirm the order.</p>
                <p v-if="paymentMessage" class="mt-3 text-sm text-red-300" role="alert">{{ paymentMessage }}</p>
                <button
                    type="button"
                    class="mt-4 flex min-h-12 w-full items-center justify-center bg-brand-50 text-xs font-medium uppercase tracking-[0.2em] text-ink disabled:opacity-40 focus-ring-inverse"
                    :disabled="opening"
                    @click="pay"
                >
                    {{ opening ? 'Opening payment…' : `Pay ${formatPaise(payment.amount)}` }}
                </button>
            </section>
```

- [ ] **Step 3: Heading**

Replace these two lines:

```vue
            <p class="text-xs uppercase tracking-[0.3em] text-brand-600">Order</p>
            <h1 class="mt-1 font-display text-3xl font-semibold tabular-nums">{{ order.number }}</h1>
            <p class="mt-1 text-sm text-brand-700">Placed {{ order.placed_at }}</p>
```

with:

```vue
            <PageHeading eyebrow="Order" :title="order.number" class="tabular-nums" />
            <p class="mt-3 text-sm text-brand-700">Placed {{ order.placed_at }}</p>
```

- [ ] **Step 4: Timeline**

Replace the `<ol …>…</ol>` inside the Progress block. Dots are 10 px, centred on the 1 px rule (`pl-4` + 1 px border = 17 px; 17 + 5 − 0.5 = 21.5 px); the current step is filled ink with a ring and gets `aria-current="step"`:

```vue
                <ol class="mt-3 space-y-4 border-l border-brand-300 pl-4">
                    <Reveal
                        v-for="(step, index) in timeline"
                        :key="index"
                        as="li"
                        :delay="Math.min(index, 4) * 75"
                        class="relative"
                        :aria-current="index === timeline.length - 1 ? 'step' : undefined"
                    >
                        <span
                            class="absolute -left-[21.5px] top-[5px] h-2.5 w-2.5 rounded-full"
                            :class="index === timeline.length - 1 ? 'bg-ink ring-4 ring-brand-200' : 'bg-brand-400'"
                            aria-hidden="true"
                        />
                        <p :class="index === timeline.length - 1 ? 'font-medium' : 'text-brand-700'">{{ step.status }}</p>
                        <p class="text-xs text-brand-600">{{ step.at }}</p>
                    </Reveal>
                </ol>
```

- [ ] **Step 5: Check the script is untouched**

Run: `git diff resources/js/Pages/Orders/Show.vue | grep '^[-+]' | grep -v '^[-+][-+]'`
Expected: the only changed script lines are the two added imports; everything else is the template markup above.

- [ ] **Step 6: Build and run the affected tests**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --compact tests/Feature/Storefront/OrderPaymentTest.php tests/Feature/Storefront/PlaceOrderTest.php tests/Feature/Storefront/TrackOrderTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Orders/Show.vue
git commit -m "feat: order page heading, timeline and dark payment panel"
```

---

### Task 5: Account, profile and content pages

**Files:**
- Modify: `resources/js/Pages/Account/Show.vue`
- Modify: `resources/js/Pages/Profile/Edit.vue`
- Modify: `resources/js/Pages/Content/Show.vue` (whole file)

The three `Profile/Partials/*.vue` files are not edited: each has a single root `<section>`, so the border classes go on from `Edit.vue` and fall through. Their form logic stays untouched.

**Interfaces:**
- Consumes: `PageHeading` (Task 1), `Reveal`; `$page.props.auth.user.name` (shared by `HandleInertiaRequests`; `/account` is behind `auth`); `order.status` is the fulfilment status label (`AccountController`).

- [ ] **Step 1: Account script**

In `resources/js/Pages/Account/Show.vue`, replace the imports and add `firstName` after `defineProps(…)`:

```js
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeading from '@/Components/Store/PageHeading.vue';
import Reveal from '@/Components/Store/Reveal.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';
```

```js
const page = usePage();
const firstName = computed(() => page.props.auth.user.name.trim().split(/\s+/)[0]);
```

- [ ] **Step 2: Account heading**

Replace the top `<div class="flex flex-wrap items-baseline justify-between gap-4">` opening tag and the `<h1>` inside it (the two links stay as they are):

```vue
            <div class="flex flex-wrap items-end justify-between gap-4">
                <PageHeading eyebrow="Your account" :title="`Hello, ${firstName}`" />
```

- [ ] **Step 3: Account order rows**

Replace the orders `<li v-for="order in orders" :key="order.number">…</li>`:

```vue
                    <Reveal v-for="(order, index) in orders" :key="order.number" as="li" :delay="Math.min(index, 4) * 75">
                        <a :href="order.url" class="flex min-h-14 items-center justify-between gap-4 py-3 text-sm hover:bg-brand-100 focus-ring">
                            <span>
                                <span class="font-medium tabular-nums">{{ order.number }}</span>
                                <span class="block text-brand-600">{{ order.placed_at }}</span>
                            </span>
                            <span class="flex flex-col items-end gap-1">
                                <span class="tabular-nums">{{ formatPaise(order.total) }}</span>
                                <span class="border border-brand-300 px-2 py-1 text-[10px] uppercase tracking-[0.2em] text-brand-700">{{ order.status }}</span>
                            </span>
                        </a>
                    </Reveal>
```

- [ ] **Step 4: Profile page**

In `resources/js/Pages/Profile/Edit.vue`, add after `import StoreLayout from '@/Layouts/StoreLayout.vue';`:

```js
import PageHeading from '@/Components/Store/PageHeading.vue';
```

Replace the `<h1 …>Profile & password</h1>` and the partials wrapper `<div class="mt-10 max-w-xl divide-y divide-brand-200">…</div>`:

```vue
            <PageHeading eyebrow="Your account" title="Profile & password" class="mt-4" />

            <div class="mt-10 max-w-xl space-y-8">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" class="border border-brand-200 p-6 sm:p-8" />
                <UpdatePasswordForm class="border border-brand-200 p-6 sm:p-8" />
                <DeleteUserForm class="border border-red-200 p-6 sm:p-8" />
            </div>
```

- [ ] **Step 5: Content page banner**

Replace `resources/js/Pages/Content/Show.vue`. The banner holds the page's only `h1`; the in-article `h1` goes (seeded page bodies have no Markdown `#` headings). Above the fold, so the image is not lazy:

```vue
<script setup>
import { Head } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

defineProps({
    title: { type: String, required: true },
    html: { type: String, required: true },
});
</script>

<template>
    <Head :title="title" />

    <StoreLayout>
        <section class="relative isolate overflow-hidden bg-brand-900 text-brand-50">
            <img
                src="/images/hero-1600.webp"
                srcset="/images/hero-800.webp 800w, /images/hero-1600.webp 1600w"
                sizes="100vw"
                alt=""
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
            />
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-900 via-brand-900/60 to-brand-900/20" aria-hidden="true" />

            <div class="mx-auto flex h-48 max-w-3xl flex-col justify-end px-4 pb-8 sm:h-64">
                <p class="animate-rise-in text-xs uppercase tracking-[0.3em] text-brand-200">Arusa Apparel Co.</p>
                <h1 class="mt-2 animate-rise-in font-display text-3xl font-semibold text-brand-50 [animation-delay:75ms] sm:text-4xl">{{ title }}</h1>
            </div>
        </section>

        <article class="content-prose mx-auto max-w-3xl px-4 py-10 sm:py-16">
            <!-- html is server-rendered Markdown with raw HTML stripped -->
            <div v-html="html" />
        </article>
    </StoreLayout>
</template>
```

- [ ] **Step 6: Build and run the affected tests**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --compact tests/Feature/Storefront/AccountTest.php tests/Feature/ProfileTest.php tests/Feature/ContentPageTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Account/Show.vue resources/js/Pages/Profile/Edit.vue resources/js/Pages/Content/Show.vue
git commit -m "feat: account greeting, profile panels and content banner"
```

---

### Task 6: Verify in the browser and open the PR (controller task)

The session controller runs this directly, not a subagent.

**Files:** none (fixes found here go in the file they belong to, with their own commit).

Do not run `migrate:fresh` or any reseed: the local DB has real orders. Do not delete `public/hot`. Save every screenshot under `.playwright-mcp/` (the only allowed root), e.g. `.playwright-mcp/pass2-login-375.png`.

- [ ] **Step 1: Full test run of the affected suites**

Run: `php artisan test --compact tests/Feature/Auth tests/Feature/Storefront tests/Feature/ProfileTest.php tests/Feature/ContentPageTest.php`
Expected: PASS.

Run: `npm run build`
Expected: build succeeds; no new vendor chunk.

- [ ] **Step 2: Pages at 375 and 1280 px**

Use `get-absolute-url` for the base URL. With `browser_resize` at 375×812 and then 1280×800, visit and screenshot:
- Logged out: `/login`, `/register`, `/forgot-password`, `/track`, `/cart` (empty), `/pages/about`.
- Logged in as `test@example.com` / `password`: `/account`, `/profile`, `/confirm-password`.

Confirm: auth pages show the dark 160 px band with the badge at 375 and the split photo + wordmark at 1280 with the form vertically centred; one `h1` per page (`browser_evaluate`: `document.querySelectorAll('h1').length === 1`); headings rise in; account greeting reads "Hello, Test"; profile panels are bordered, delete panel red-bordered; no horizontal scroll at 375 (`document.documentElement.scrollWidth <= 375`).

`/reset-password/{token}` and `/verify-email` need a token / an unverified user; they share `GuestLayout` with the pages above, so the login check covers their layout. Skip them unless the layout looks wrong elsewhere.

- [ ] **Step 3: One full cash-on-delivery checkout**

Still logged in, at 1280: add a size of `/products/wolf-badge-tee` to the bag, open `/cart` (lines reveal, "Continue shopping" beside Checkout), go to checkout. Check the "01/02/03" step numbers, summary thumbnails, sticky summary on scroll, the reassurance line. Fill an Aizawl pincode (`796001`, COD allowed), choose Cash on delivery, place the order. Expected: lands on the order page with the "Thank you" panel, `PageHeading` showing "Order" + the number, timeline with the current step ringed. Screenshot cart, checkout and order page at 375 and 1280.

This creates one real order in the local DB; that is expected. Note its number in the PR body.

- [ ] **Step 4: Reduced-motion pass**

`browser_emulate_media` with `reducedMotion: 'reduce'`, reload `/login`, `/cart` and the order page from Step 3: everything visible immediately, no rise-in, no hidden lines or timeline steps.

- [ ] **Step 5: Console clean and log out**

`browser_console_messages` after the pages above: no errors or Vue warnings. Log out from `/account` ("Log out"), then log back in with `test@example.com` / `password` on `/login` and log out again. Expected: both work and redirect as before.

- [ ] **Step 6: Ask for the full suite**

Ask the user to run `php artisan test --compact`.

- [ ] **Step 7: Push and open the PR**

```bash
git push -u origin feat/storefront-pass-2
```

```bash
gh pr create --title "feat: storefront pass 2 (auth, bag, checkout, order, account, content)" --body "Implements docs/superpowers/specs/2026-10-24-storefront-pass-2-design.md. PageHeading component, split auth layout with hero photo, bag reveal and empty state, numbered checkout steps with summary thumbnails, order timeline and dark payment panel, account greeting with status chips, bordered profile panels, content banner. Frontend only; checkout and payment logic unchanged. Verified at 375/1280 px, reduced motion, clean console, one COD checkout (order <number>). Screenshots: <attach>."
```
