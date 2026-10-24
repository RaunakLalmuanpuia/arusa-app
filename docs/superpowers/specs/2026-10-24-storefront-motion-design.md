# Storefront Motion & Imagery — Design Spec

- **Date:** 2026-10-24
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Builds on:** `2026-10-23-arusa-ecommerce-design.md`
- **References:** `docs/design/reference-product-board.jpg`, `public/logo.jpg`

## 1. Goal

Make the browse path feel like the reference board — dark, editorial, premium streetwear — with tasteful motion and real product photos.

### Decisions
- **Direction:** editorial lookbook (option A).
- **Motion:** CSS transitions/keyframes, Vue `<Transition>` and one small IntersectionObserver component. **No animation packages** (arusa-commerce rule stands).
- **Images:** free stock photos (Unsplash / Pexels licence: commercial use, no attribution required). Shortlist shown to the user before committing.
- **Pages in scope:** `StoreLayout`, `Home`, `Catalog/Category`, `Catalog/Product`, `Catalog/Search`, `CartDrawer`, `ProductCard`.

### Out of scope
Bag-icon pulse (the cart drawer opens over the header on add, so it would never be seen), Inertia page-to-page transitions (StoreLayout is per-page, would need a persistent-layout refactor), category images in the backend, checkout / cart page / account / auth / content pages, product spec tables (would be false for non-tee products).

## 2. Images

### Product photos (seeded)
- ~7 stock photos in `database/seeders/images/`: for each of the 3 tees one black and one cream shot, plus one mug shot. Named `<product-slug>-<colour>.jpg` (mug: `enamel-mug.jpg`).
- `CatalogSeeder` attaches each via the existing `App\Actions\StoreProductImage` (WebP at 400/800/1200 + srcset), wrapping the file in `Illuminate\Http\UploadedFile` (test mode), then sets `colour` on the created `ProductImage` so the product page gallery filters by colour.
- Seeder skips silently if an image file is missing (keeps the seeder usable without the folder).

### Hero image (static)
- One lifestyle shot (dark tee on a wall, warm side light, like the board) at `public/images/hero.webp`, ~1600px wide, < 250 KB. Not a product image, so it is not in the DB.

## 3. Motion kit

### `resources/js/Components/Store/Reveal.vue`
- Props: `as` (tag, default `div`), `delay` (ms, default 0).
- On mount: adds `reveal` class (opacity 0, translateY 12px) and observes the element with one IntersectionObserver (`rootMargin: 0px 0px -10% 0px`). On intersect adds `is-revealed`, sets `transition-delay` from `delay`, and unobserves.
- If `IntersectionObserver` is missing or `prefers-reduced-motion: reduce` matches, it never hides content.
- Single root element (Inertia + Vue rule).

### Keyframes / utilities (`resources/css/app.css`)
- `reveal` / `is-revealed`: 300 ms ease-out opacity + transform.
- `animate-rise-in`: fade + rise, used with staggered `animation-delay` on hero badge, wordmark, subtitle and the product page columns.
- `animate-rule-draw`: `scaleX(0 → 1)` from the inner edge for the hero rule lines.
- `animate-marquee`: infinite `translateX(0 → -50%)`, ~30 s linear.
- All wrapped so `@media (prefers-reduced-motion: reduce)` disables animations and transitions and stops the marquee.

## 4. Home (`resources/js/Pages/Home.vue`)

Top to bottom:
1. **Hero** — full-bleed `hero.webp` (`fetchpriority="high"`) under a dark bottom-to-top gradient for contrast. Staggered intro: `ApplicationLogo` badge → wordmark with rule lines drawing outward → "Apparel Co." → tagline. CTA "Shop new in" anchors to `#new-in`.
2. **Marquee strip** — "Streetwear · Premium quality · Aizawl, Mizoram · Est. 2026", content duplicated for seamless loop; duplicate is `aria-hidden="true"`.
3. **Feature row** — 4 items from the board: Premium cotton, Puff print detail, Durable & long lasting, Comfort fit. Four new inline SVG icons in `resources/js/Components/Icons/` (`IconCotton`, `IconPuffPrint`, `IconShield`, `IconFeather`), circled outline style. Each wrapped in `Reveal` with 0/75/150/225 ms delays.
4. **Category tiles** — top-level categories from the shared `categories` prop, dark `brand-900` tiles with display-font name and an underline that grows on hover. Linked with `route('categories.show', slug)`. No backend change.
5. **New in** (`id="new-in"`) — existing `ProductGrid`; cards reveal with stagger. Empty state unchanged.

## 5. Other browse pages

- **`ProductGrid`** — each `<li>` becomes `<Reveal as="li">` with delay `(index % 4) * 75` ms.
- **`ProductCard`** — If the product has a second image, it crossfades in on hover (desktop only, `@media (hover: hover)`). Sold-out badge restyled to match the board's small-caps labels.
- **Backend for hover image:** `Product::withCardData()` loads the first two images instead of one; `cardData()` adds `hover_image` (second image's `toImageData()` or `null`). Existing card-data tests updated.
- **`Catalog/Product`** — gallery then info column rise in (0 / 75 ms) on load; existing image crossfade kept.
- **`StoreLayout`** — header gains a soft shadow once `scrollY > 8` (passive scroll listener).
- **`CartDrawer`** — tune slide/backdrop timing to 250 ms enter / 200 ms leave, ease-out/ease-in.
- **`Catalog/Category`, `Catalog/Search`** — inherit card changes; no layout change.

## 6. Constraints
- Storefront JS stays < ~200 KB; no new npm dependencies.
- Every animation ≤ 300 ms except the marquee; nothing animates layout properties (opacity/transform only).
- Content is never hidden without JS or with reduced motion.
- Mobile-first; touch targets ≥ 44 px kept.

## 7. Testing
- **Pest:** `CatalogSeeder` attaches images with colours to tees (uses `Storage::fake('public')`); `cardData()` returns `hover_image` (second image or null).
- **Browser (Playwright):** Home, Category, Product, Search, cart drawer at 375 px and 1280 px; once with `prefers-reduced-motion: reduce` to confirm content is visible and marquee static; check console for errors.
