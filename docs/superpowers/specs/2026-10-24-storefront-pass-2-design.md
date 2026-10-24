# Storefront Pass 2 (Remaining User Pages) — Design Spec

- **Date:** 2026-10-24
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Builds on:** `2026-10-24-storefront-motion-design.md` (pass 1, merged as PR #7)
- **References:** `docs/design/reference-product-board.jpg`, `public/logo.jpg`

## 1. Goal

Bring every remaining user-facing page up to the pass-1 editorial look, reusing its motion kit.

### Pages in scope
- `GuestLayout` and every page on it: `Auth/Login`, `Auth/Register`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/VerifyEmail`, `Auth/ConfirmPassword`, `Orders/Track`.
- `Cart/Index`, `Checkout/Create`, `Orders/Show`, `Account/Show`, `Profile/Edit` (+ its three `Partials`), `Content/Show`.

### Decisions
- **Frontend only.** No new props, routes, validation or controller changes. Everything uses data the pages already receive (e.g. `cart.lines[].image` for checkout thumbnails).
- **Money pages are cosmetic only.** Checkout and the order/payment page keep their logic, form fields, names, order and behaviour exactly; only markup classes and wrappers change.
- **Motion:** only pass-1 pieces — `animate-rise-in` (with `[animation-delay:…]`) and `Components/Store/Reveal.vue`. No new keyframes, no packages. Off under `prefers-reduced-motion`.
- **Auth look:** split layout with the hero photo (`/images/hero-800.webp`, `/images/hero-1600.webp`).

### Out of scope
Checkout/payment behaviour, new data or backend work, emails, admin pages, new photos.

## 2. Shared pieces

### `resources/js/Components/Store/PageHeading.vue`
- Props: `title: string` (required), `eyebrow: string | null` (default `null`).
- Renders: optional eyebrow (`text-xs uppercase tracking-[0.3em] text-brand-600`), `h1` in `font-display text-3xl sm:text-4xl font-semibold`, then a short `h-px w-10 bg-brand-400` rule. Root has `animate-rise-in`.
- Single root; extra classes fall through.
- Replaces the hand-written `h1` on Cart, Checkout, Orders/Show (eyebrow "Order", title = order number), Account, Profile and inside `GuestLayout`.

### `resources/js/Layouts/GuestLayout.vue` (split)
- Still wraps `StoreLayout`; keeps the `title` prop, so the seven pages need no changes.
- `lg` and up: two columns filling `min-h-[calc(100dvh-4rem)]` (viewport minus the 64 px header). Left: hero photo (`object-cover`, `alt=""`, `srcset` 800/1600, `sizes="50vw"`) under a `brand-900` gradient, with `ApplicationLogo on-dark` (`sizes="112px"`), "ARUSA" display wordmark, "Apparel Co." small caps and a rule line, rising in. Right: form column `max-w-md`, vertically centred, `PageHeading :title`, then the slot.
- Below `lg`: photo becomes a `h-40` dark band (same image + gradient, badge only), form below in the existing single column.

## 3. Shopping pages

### Cart (`Cart/Index.vue`)
- `PageHeading title="Your bag"`.
- Each line `<li>` becomes `<Reveal as="li" :delay="index * 75">` (cap delay at 300 ms: `Math.min(index, 4) * 75`).
- "Continue shopping" `store-link` to `route('home')` next to the checkout button.
- Empty state: `ApplicationLogo` (`h-16 w-16`), "Your bag is empty.", link "Shop new in" to `route('home') + '#new-in'`.

### Checkout (`Checkout/Create.vue`) — cosmetic only
- `PageHeading title="Checkout"`.
- Section `h2`s prefixed with step numbers: "01 Contact", "02 Delivery address", "03 Payment" (number in `text-brand-500 tabular-nums`, `aria-hidden="true"`; heading text unchanged for screen readers).
- Order summary: each line shows a 48×60 thumbnail from `line.image` (`sizes="48px"`, lazy, `alt=""` since the name is beside it); summary `aside` is `lg:sticky lg:top-24`.
- Under the submit button: one muted line "Cash on delivery · Secure online payment · Delivered across Mizoram" (`text-xs text-brand-600`).
- No change to `useForm` fields, computed values, watchers, submit handler, button disabled logic or labels.

### Order page (`Orders/Show.vue`) — cosmetic only
- `PageHeading eyebrow="Order" :title="order.number"` (keep `tabular-nums` via class).
- Progress timeline: vertical `border-l border-brand-300` with 10 px dots; current (last) step dot filled `bg-ink` with a `ring-4 ring-brand-200`, earlier dots `bg-brand-400`; each step a `Reveal as="li"` with `Math.min(index, 4) * 75` delay. Current step gets `aria-current="step"`.
- "Complete your payment" panel restyled as a dark panel (`bg-brand-900 text-brand-50 p-6`), button inverted (`bg-brand-50 text-ink`), keeping `focus-ring-inverse`. Payment script, handlers, messages and button states unchanged.

### Account (`Account/Show.vue`)
- `PageHeading eyebrow="Your account" :title="'Hello, ' + firstName"` where `firstName` is the first word of `$page.props.auth.user.name`.
- Orders list rows: number, date, total and a status chip (`border px-2 py-1 text-[10px] uppercase tracking-[0.2em]`), each a `Reveal as="li"` with capped stagger. Links unchanged.

### Profile (`Profile/Edit.vue` + Partials)
- `PageHeading eyebrow="Your account" title="Profile & password"`.
- Each partial wrapped in a `border border-brand-200 p-6 sm:p-8` section; the delete-account section uses `border-red-200`. Form logic untouched.

### Content (`Content/Show.vue`)
- Slim dark banner above the text: hero photo `h-48 sm:h-64` under the `brand-900` gradient, title (`h1`, display font, `text-brand-50`) and eyebrow "Arusa Apparel Co." rising in. The body keeps `content-prose`; the in-body `h1` is removed so there is one `h1` per page.

## 4. Constraints (from pass 1, still binding)
- No new npm or composer dependencies.
- Durations 150–300 ms; animate only `opacity` and `transform`.
- Reduced motion: everything visible, no animation.
- Touch targets ≥ 44 px; keep `focus-ring` / `focus-ring-inverse`.
- Images: WebP + `srcset`; `loading="lazy"` except above-the-fold.
- Vue components: single root element.

## 5. Testing
- **Pest:** no new tests (styling only, no prop/behaviour change). The existing suites for these pages must still pass: `tests/Feature/Auth`, `tests/Feature/Storefront`, `tests/Feature/ProfileTest.php`, `tests/Feature/ContentPageTest.php`.
- **Browser (Playwright):** every in-scope page at 375 px and 1280 px; one reduced-motion pass; console clean. One full cash-on-delivery checkout from bag to order page to prove checkout still works, and Log in / Log out with the test admin.
