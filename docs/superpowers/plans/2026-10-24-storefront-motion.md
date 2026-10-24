# Storefront Motion & Imagery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the browse path (layout, Home, Category, Product, Search, cart drawer) the reference board's editorial look with CSS-only motion, and seed real stock product photos.

**Architecture:** Stock photos go through the existing `StoreProductImage` action from `CatalogSeeder`; a static hero lives in `public/images/`. Motion is a handful of keyframe/transition classes in `resources/css/app.css` plus one `Reveal.vue` IntersectionObserver wrapper. The only backend change is a second card image (`hover_image`). Spec: `docs/superpowers/specs/2026-10-24-storefront-motion-design.md`. References: `docs/design/reference-product-board.jpg`, `public/logo.jpg`.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v2 + Vue 3, Tailwind 3, Pest.

## Global Constraints

- No new npm or composer dependencies. Motion = CSS transitions/keyframes, Vue `<Transition>`, and `Reveal.vue` only.
- Durations 150–300 ms (marquee loop excepted). Animate only `opacity` and `transform` (header shadow is paint-only and allowed).
- `prefers-reduced-motion: reduce` disables every animation/transition added here and stops the marquee; content is never hidden without JS or under reduced motion.
- Icons are inline SVG components in `resources/js/Components/Icons/` (24×24 viewBox, `stroke-width="1.5"`, `aria-hidden="true"`), never a package.
- Touch targets ≥ 44 px (`min-h-11` / `h-11 w-11`); keep existing `focus-ring` / `focus-ring-inverse` classes on interactive elements.
- Images: WebP + `srcset` + `loading="lazy"` except above-the-fold. Seeded photos must be ≥ 1200 px wide (`ProductImage` srcset assumes it).
- Stock photos only from Unsplash or Pexels (free licence, commercial use, no attribution required).
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Work on branch `feat/storefront-motion`, not `master`. Run `git merge` / `git checkout` as separate commands.

---

### Task 1: Source stock photos (controller task — needs user approval)

This task needs the user to approve images, so the session controller runs it directly, not a subagent.

**Files:**
- Create: `database/seeders/images/wolf-badge-tee-black.jpg`, `wolf-badge-tee-cream.jpg`, `heritage-logo-tee-black.jpg`, `heritage-logo-tee-cream.jpg`, `puff-print-tee-black.jpg`, `puff-print-tee-cream.jpg`, `enamel-mug.jpg`
- Create: `public/images/hero-800.webp`, `public/images/hero-1600.webp`

**Interfaces:**
- Produces: the seven JPGs above (names = `<product-slug>-<lowercase colour>.jpg`, mug has no colour) and the two hero WebPs, used by Tasks 2 and 5.

- [ ] **Step 1: Create the branch**

```bash
git checkout -b feat/storefront-motion
```

- [ ] **Step 2: Find candidates**

Search Unsplash and Pexels for: plain black t-shirt flat lay / on hanger, cream or off-white t-shirt, black enamel camping mug, and one hero shot (dark t-shirt or person in dark streetwear, warm side light, moody wall — like the reference board). Prefer plain tees with no visible third-party branding. Download 2–3 candidates per slot into the scratchpad directory (one new empty folder), view each with the Read tool.

- [ ] **Step 3: Show the shortlist to the user**

Present the picks (source URL + licence per image) and wait for approval or swaps. Do not continue until approved.

- [ ] **Step 4: Resize and place the approved images**

```bash
mkdir -p database/seeders/images public/images
# for each approved product photo (≥ 1200 px wide after resize):
sips -Z 1600 -s format jpeg -s formatOptions 80 <downloaded-file> --out database/seeders/images/<name>.jpg
# hero:
cwebp -q 78 -resize 1600 0 <hero-file> -o public/images/hero-1600.webp
cwebp -q 78 -resize 800 0 <hero-file> -o public/images/hero-800.webp
ls -lh database/seeders/images public/images
```

Expected: 7 JPGs (each ≤ ~400 KB), `hero-1600.webp` < 250 KB.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/images public/images
git commit -m "chore: add stock product and hero photos" -m "Sources (Unsplash/Pexels licence): <list each file → URL>"
```

---

### Task 2: Seed product photos

**Files:**
- Modify: `database/seeders/CatalogSeeder.php`
- Test: `tests/Feature/CatalogSeederTest.php`

**Interfaces:**
- Consumes: the seven JPGs from Task 1; `App\Actions\StoreProductImage::handle(Product $product, UploadedFile $file): ProductImage`.
- Produces: seeded tees have two images (black `sort_order` 1, cream `sort_order` 2, `colour` set); mug has one image with `colour` null.

- [ ] **Step 1: Create the test file**

```bash
php artisan make:test --pest CatalogSeederTest --no-interaction
```

Replace its contents with:

```php
<?php

use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Storage;

test('the catalog seeder attaches a photo per tee colour and one for the mug', function () {
    Storage::fake('public');

    $this->seed(CatalogSeeder::class);

    $tee = Product::where('slug', 'wolf-badge-tee')->firstOrFail();
    expect($tee->images->pluck('colour')->all())->toBe(['Black', 'Cream']);

    $mug = Product::where('slug', 'enamel-mug')->firstOrFail();
    expect($mug->images)->toHaveCount(1)
        ->and($mug->images->first()->colour)->toBeNull();

    Storage::disk('public')->assertExists($tee->images->first()->path.'-1200.webp');
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/CatalogSeederTest.php`
Expected: FAIL — images are empty (`[]` is not `['Black', 'Cream']`).

- [ ] **Step 3: Attach images in the seeder**

In `database/seeders/CatalogSeeder.php`, update the class docblock and add the image calls and helper:

```php
<?php

namespace Database\Seeders;

use App\Actions\StoreProductImage;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class CatalogSeeder extends Seeder
{
    /**
     * A small demo catalog, with stock photos from database/seeders/images, for checking the storefront locally.
     */
    public function run(): void
    {
        $apparel = Category::factory()->create(['name' => 'Apparel', 'slug' => 'apparel', 'sort_order' => 1]);
        $tees = Category::factory()->create(['name' => 'T-Shirts', 'slug' => 't-shirts', 'parent_id' => $apparel->id]);
        $goods = Category::factory()->create(['name' => 'Goods', 'slug' => 'goods', 'sort_order' => 2]);

        foreach (['Wolf Badge Tee', 'Heritage Logo Tee', 'Puff Print Tee'] as $index => $name) {
            $tee = Product::factory()->for($tees)->create(['name' => $name, 'slug' => str($name)->slug(), 'base_price' => 99900]);

            foreach (['Black', 'Cream'] as $colour) {
                $this->attachImage($tee, $tee->slug.'-'.strtolower($colour), $colour);

                foreach (['S', 'M', 'L', 'XL'] as $size) {
                    $tee->variants()->create([
                        'sku' => strtoupper("{$tee->slug}-{$colour}-{$size}"),
                        'size' => $size,
                        'colour' => $colour,
                        'price_override' => $size === 'XL' ? 109900 : null,
                        'stock' => $index === 2 && $colour === 'Cream' ? 0 : 5,
                    ]);
                }
            }
        }

        $mug = Product::factory()->for($goods)->create(['name' => 'Enamel Mug', 'slug' => 'enamel-mug', 'base_price' => 49900]);
        $mug->variants()->create(['sku' => 'ENAMEL-MUG', 'stock' => 12]);
        $this->attachImage($mug, 'enamel-mug');
    }

    /**
     * Run a bundled photo through the real upload pipeline; skip quietly if the file is missing.
     */
    private function attachImage(Product $product, string $name, ?string $colour = null): void
    {
        $path = database_path("seeders/images/{$name}.jpg");

        if (! is_file($path)) {
            return;
        }

        app(StoreProductImage::class)
            ->handle($product, new UploadedFile($path, basename($path), test: true))
            ->update(['colour' => $colour]);
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact tests/Feature/CatalogSeederTest.php`
Expected: PASS.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/CatalogSeeder.php tests/Feature/CatalogSeederTest.php
git commit -m "feat: seed stock product photos through the upload pipeline"
```

---

### Task 3: Second card image (`hover_image`)

**Files:**
- Modify: `app/Models/Product.php` (`withCardData()` scope and `cardData()`)
- Test: `tests/Feature/CatalogModelTest.php`

**Interfaces:**
- Produces: every listing card (Home, Category, Search) gains `hover_image: {src, srcset, alt}|null` — the product's second image by `sort_order`, `id`. Consumed by `ProductCard.vue` in Task 4.

- [ ] **Step 1: Update and add tests**

In `tests/Feature/CatalogModelTest.php`, add `use App\Models\ProductImage;` to the imports, add `'hover_image' => null,` after `'image' => null,` in the existing `card data reports the price range, stock and first image` test, then add:

```php
test('card data includes the second image of each product for the hover swap', function () {
    [$first, $second] = Product::factory()->count(2)->has(ProductVariant::factory(), 'variants')->create();
    foreach ([$first, $second] as $product) {
        foreach ([1, 2, 3] as $order) {
            ProductImage::factory()->for($product)->create(['alt' => "{$product->slug} {$order}", 'sort_order' => $order]);
        }
    }
    $lonely = Product::factory()->has(ProductVariant::factory(), 'variants')->create();
    ProductImage::factory()->for($lonely)->create();

    $cards = Product::withCardData()->orderBy('id')->get()->map->cardData();

    expect($cards[0]['image']['alt'])->toBe("{$first->slug} 1")
        ->and($cards[0]['hover_image']['alt'])->toBe("{$first->slug} 2")
        ->and($cards[1]['hover_image']['alt'])->toBe("{$second->slug} 2")
        ->and($cards[2]['hover_image'])->toBeNull();
});
```

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test --compact tests/Feature/CatalogModelTest.php`
Expected: FAIL — `hover_image` key missing.

- [ ] **Step 3: Load two images and expose the second**

In `app/Models/Product.php`:

```php
    /**
     * Eager-load what cardData() needs: active variants and the first two images.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function withCardData(Builder $query): void
    {
        $query->with([
            'variants' => fn (HasMany $variants) => $variants->where('is_active', true),
            'images' => fn (HasMany $images) => $images->limit(2),
        ]);
    }

    /**
     * Listing card props. Requires the withCardData() scope.
     *
     * @return array{name: string, slug: string, price_from: int, has_price_range: bool, in_stock: bool, image: array{src: string, srcset: string, alt: string}|null, hover_image: array{src: string, srcset: string, alt: string}|null}
     */
    public function cardData(): array
    {
        $prices = $this->variants->map(fn (ProductVariant $variant): int => $variant->effectivePrice());

        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'price_from' => (int) $prices->min(),
            'has_price_range' => $prices->unique()->count() > 1,
            'in_stock' => $this->variants->contains(fn (ProductVariant $variant): bool => $variant->stock > 0),
            'image' => $this->images->first()?->toImageData(),
            'hover_image' => $this->images->get(1)?->toImageData(),
        ];
    }
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact tests/Feature/CatalogModelTest.php tests/Feature/HomePageTest.php tests/Feature/Storefront`
Expected: PASS.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Product.php tests/Feature/CatalogModelTest.php
git commit -m "feat: add a hover image to product card data"
```

---

### Task 4: Motion kit, product grid reveal and card hover

**Files:**
- Modify: `resources/css/app.css`
- Create: `resources/js/Components/Store/Reveal.vue`
- Modify: `resources/js/Components/Store/ProductGrid.vue`
- Modify: `resources/js/Components/Store/ProductCard.vue`

**Interfaces:**
- Consumes: `product.hover_image` from Task 3.
- Produces: CSS classes `reveal`, `is-revealed`, `animate-rise-in`, `animate-rule-draw`, `animate-marquee` (delays via Tailwind arbitrary `[animation-delay:150ms]`); component `Reveal` with props `as: string = 'div'`, `delay: number = 0` (ms), single root, default slot, attrs fall through.

No automated tests (styling only, per project rules); verified in Task 7.

- [ ] **Step 1: Add motion CSS**

Append to `resources/css/app.css` (outside any `@layer`, so Tailwind never purges classes that `Reveal.vue` adds at runtime):

```css
/* Storefront motion. Opacity/transform only; all switched off under reduced motion. */
.reveal { opacity: 0; transform: translateY(12px); transition: opacity 300ms ease-out, transform 300ms ease-out; }
.reveal.is-revealed { opacity: 1; transform: none; }
.animate-rise-in { animation: rise-in 300ms ease-out both; }
.animate-rule-draw { animation: rule-draw 300ms ease-out both; }
.animate-marquee { animation: marquee 40s linear infinite; }

@keyframes rise-in { from { opacity: 0; transform: translateY(12px); } }
@keyframes rule-draw { from { transform: scaleX(0); } }
@keyframes marquee { to { transform: translateX(-50%); } }

@media (prefers-reduced-motion: no-preference) {
    html { scroll-behavior: smooth; }
}

@media (prefers-reduced-motion: reduce) {
    .reveal { opacity: 1; transform: none; transition: none; }
    .animate-rise-in, .animate-rule-draw, .animate-marquee { animation: none; }
}
```

- [ ] **Step 2: Create `Reveal.vue`**

`resources/js/Components/Store/Reveal.vue`:

```vue
<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    as: { type: String, default: 'div' },
    delay: { type: Number, default: 0 },
});

const element = ref(null);
let observer = null;

onMounted(() => {
    // Without the observer, or with reduced motion, content simply stays visible.
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const target = element.value;
    target.classList.add('reveal');
    target.style.transitionDelay = `${props.delay}ms`;

    observer = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) {
            target.classList.add('is-revealed');
            observer.disconnect();
        }
    }, { rootMargin: '0px 0px -10% 0px' });
    observer.observe(target);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <component :is="as" ref="element">
        <slot />
    </component>
</template>
```

- [ ] **Step 3: Stagger the grid**

`resources/js/Components/Store/ProductGrid.vue`:

```vue
<script setup>
import ProductCard from '@/Components/Store/ProductCard.vue';
import Reveal from '@/Components/Store/Reveal.vue';

defineProps({
    products: { type: Array, required: true },
    eagerCount: { type: Number, default: 2 },
});
</script>

<template>
    <ul class="grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4">
        <Reveal v-for="(product, index) in products" :key="product.slug" as="li" :delay="(index % 4) * 75">
            <ProductCard :product="product" :eager="index < eagerCount" />
        </Reveal>
    </ul>
</template>
```

- [ ] **Step 4: Hover image and badge in `ProductCard.vue`**

Replace the image block (`<div class="relative aspect-[4/5] …">…</div>`) with:

```vue
        <div class="relative aspect-[4/5] overflow-hidden bg-brand-100">
            <img
                v-if="product.image"
                :src="product.image.src"
                :srcset="product.image.srcset"
                sizes="(min-width: 1024px) 25vw, 50vw"
                :alt="product.image.alt"
                :loading="eager ? 'eager' : 'lazy'"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
            />
            <img
                v-if="product.hover_image"
                :src="product.hover_image.src"
                :srcset="product.hover_image.srcset"
                sizes="(min-width: 1024px) 25vw, 50vw"
                alt=""
                loading="lazy"
                class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-300 motion-reduce:transition-none [@media(hover:hover)]:group-hover:opacity-100"
            />
            <span
                v-if="!product.in_stock"
                class="absolute left-3 top-3 border border-brand-50/40 bg-ink/85 px-3 py-1.5 text-[10px] font-medium uppercase tracking-[0.3em] text-brand-50"
            >
                Sold out
            </span>
        </div>
```

- [ ] **Step 5: Build and commit**

Run: `npm run build`
Expected: build succeeds.

```bash
git add resources/css/app.css resources/js/Components/Store/Reveal.vue resources/js/Components/Store/ProductGrid.vue resources/js/Components/Store/ProductCard.vue
git commit -m "feat: scroll reveal for product grids and hover image swap on cards"
```

---

### Task 5: Editorial Home page

**Files:**
- Create: `resources/js/Components/Icons/IconCotton.vue`, `IconPuffPrint.vue`, `IconShield.vue`, `IconFeather.vue`
- Modify: `resources/js/Pages/Home.vue`

**Interfaces:**
- Consumes: `Reveal` and motion classes (Task 4); `/images/hero-800.webp`, `/images/hero-1600.webp` (Task 1); shared props `store.store_name`, `categories: {name, slug}[]` (top-level, active); `ApplicationLogo` (`on-dark` prop; a `sizes` attribute passed in overrides its default `64px`).
- Produces: Home page; `products` prop unchanged, so `tests/Feature/HomePageTest.php` stays valid.

- [ ] **Step 1: Create the four icons**

`resources/js/Components/Icons/IconCotton.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <circle cx="12" cy="6.5" r="3" />
        <circle cx="8" cy="10.5" r="3" />
        <circle cx="16" cy="10.5" r="3" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 13.5V21m0-3c-2 0-3.5-1-4-2.5m4 2.5c2 0 3.5-1 4-2.5" />
    </svg>
</template>
```

`resources/js/Components/Icons/IconPuffPrint.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <circle cx="6" cy="6" r="1.5" />
        <circle cx="12" cy="6" r="1.5" />
        <circle cx="18" cy="6" r="1.5" />
        <circle cx="6" cy="12" r="1.5" />
        <circle cx="12" cy="12" r="1.5" />
        <circle cx="18" cy="12" r="1.5" />
        <circle cx="6" cy="18" r="1.5" />
        <circle cx="12" cy="18" r="1.5" />
        <circle cx="18" cy="18" r="1.5" />
    </svg>
</template>
```

`resources/js/Components/Icons/IconShield.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
    </svg>
</template>
```

`resources/js/Components/Icons/IconFeather.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5l6.74-6.76ZM16 8 2 22m15.5-7H9" />
    </svg>
</template>
```

- [ ] **Step 2: Rewrite `Home.vue`**

`resources/js/Pages/Home.vue`:

```vue
<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductGrid from '@/Components/Store/ProductGrid.vue';
import Reveal from '@/Components/Store/Reveal.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import IconCotton from '@/Components/Icons/IconCotton.vue';
import IconPuffPrint from '@/Components/Icons/IconPuffPrint.vue';
import IconShield from '@/Components/Icons/IconShield.vue';
import IconFeather from '@/Components/Icons/IconFeather.vue';

defineProps({ products: { type: Array, required: true } });

const page = usePage();
const store = page.props.store;

const marqueeItems = ['Streetwear', 'Premium quality', 'Aizawl, Mizoram', 'Est. 2026'];

const features = [
    { label: 'Premium cotton', icon: IconCotton },
    { label: 'Puff print detail', icon: IconPuffPrint },
    { label: 'Durable & long lasting', icon: IconShield },
    { label: 'Comfort fit', icon: IconFeather },
];
</script>

<template>
    <Head title="Home" />

    <StoreLayout>
        <section class="relative isolate overflow-hidden bg-brand-900 text-brand-50">
            <img
                src="/images/hero-1600.webp"
                srcset="/images/hero-800.webp 800w, /images/hero-1600.webp 1600w"
                sizes="100vw"
                alt=""
                fetchpriority="high"
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
            />
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-900 via-brand-900/60 to-brand-900/20" aria-hidden="true" />

            <div class="mx-auto flex min-h-[80svh] max-w-6xl flex-col items-center justify-center px-4 py-20 text-center">
                <ApplicationLogo on-dark sizes="112px" class="h-24 w-24 animate-rise-in sm:h-28 sm:w-28" />
                <div class="mt-8 flex w-full items-center justify-center gap-4 sm:gap-6">
                    <span class="h-px w-10 origin-right animate-rule-draw bg-brand-400 [animation-delay:150ms] sm:w-16" aria-hidden="true" />
                    <h1 class="animate-rise-in font-display text-5xl font-semibold uppercase tracking-[0.12em] [animation-delay:150ms] sm:text-7xl">
                        {{ store.store_name }}
                    </h1>
                    <span class="h-px w-10 origin-left animate-rule-draw bg-brand-400 [animation-delay:150ms] sm:w-16" aria-hidden="true" />
                </div>
                <p class="mt-3 animate-rise-in text-sm font-medium uppercase tracking-[0.3em] text-brand-200 [animation-delay:300ms]">
                    Apparel Co.
                </p>
                <a
                    v-if="products.length"
                    href="#new-in"
                    class="mt-10 inline-flex min-h-12 animate-rise-in items-center border border-brand-50 px-8 text-xs font-medium uppercase tracking-[0.2em] transition-colors duration-200 [animation-delay:450ms] hover:bg-brand-50 hover:text-ink motion-reduce:transition-none focus-ring-inverse"
                >
                    Shop new in
                </a>
            </div>
        </section>

        <div class="overflow-hidden border-y border-brand-800 bg-brand-900 py-4 text-brand-300">
            <p class="sr-only">{{ marqueeItems.join(' · ') }}</p>
            <div class="flex w-max animate-marquee" aria-hidden="true">
                <ul v-for="copy in 6" :key="copy" class="flex shrink-0">
                    <li v-for="item in marqueeItems" :key="item" class="flex items-center whitespace-nowrap text-xs uppercase tracking-[0.3em]">
                        <span class="px-6">{{ item }}</span>
                        <span class="text-brand-600">·</span>
                    </li>
                </ul>
            </div>
        </div>

        <section aria-label="Why Arusa" class="mx-auto max-w-6xl px-4 py-14 sm:py-20">
            <ul class="grid grid-cols-2 gap-x-4 gap-y-10 sm:grid-cols-4">
                <Reveal v-for="(feature, index) in features" :key="feature.label" as="li" :delay="index * 75" class="flex flex-col items-center text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full border border-ink">
                        <component :is="feature.icon" class="h-7 w-7" />
                    </span>
                    <span class="mt-4 text-xs font-medium uppercase tracking-[0.2em]">{{ feature.label }}</span>
                </Reveal>
            </ul>
        </section>

        <section v-if="page.props.categories.length" aria-label="Shop by category" class="mx-auto max-w-6xl px-4">
            <ul class="grid gap-4 sm:grid-cols-2">
                <Reveal v-for="(category, index) in page.props.categories" :key="category.slug" as="li" :delay="(index % 2) * 75">
                    <Link
                        :href="route('categories.show', category.slug)"
                        class="group flex aspect-[2/1] flex-col justify-end bg-brand-900 p-6 text-brand-50 focus-ring sm:p-8"
                    >
                        <span class="text-[10px] uppercase tracking-[0.3em] text-brand-300">Shop</span>
                        <span class="mt-2 font-display text-3xl font-semibold uppercase tracking-[0.12em] sm:text-4xl">{{ category.name }}</span>
                        <span
                            class="mt-4 h-px w-10 origin-left bg-brand-400 transition-transform duration-300 group-hover:scale-x-[2.5] motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </Link>
                </Reveal>
            </ul>
        </section>

        <section v-if="products.length" id="new-in" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-16 sm:py-20">
            <div class="flex items-center justify-center gap-4">
                <span class="h-px w-10 bg-brand-400" aria-hidden="true" />
                <h2 class="text-xs font-medium uppercase tracking-[0.3em] text-brand-600">New in</h2>
                <span class="h-px w-10 bg-brand-400" aria-hidden="true" />
            </div>
            <ProductGrid :products="products" class="mt-10" />
        </section>
        <section v-else class="mx-auto max-w-2xl px-4 py-16 text-center sm:py-20">
            <p class="text-xs font-medium uppercase tracking-[0.3em] text-brand-600">The first drop</p>
            <h2 class="mt-3 font-display text-3xl font-semibold sm:text-4xl">Our collection is coming soon.</h2>
            <p class="mt-4 text-base leading-relaxed text-brand-700">
                Apparel and everyday goods, delivered across Mizoram.
            </p>
        </section>
    </StoreLayout>
</template>
```

- [ ] **Step 3: Run the Home test and build**

Run: `php artisan test --compact tests/Feature/HomePageTest.php && npm run build`
Expected: PASS, build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Icons resources/js/Pages/Home.vue
git commit -m "feat: editorial home page with hero, marquee, features and category tiles"
```

---

### Task 6: Header shadow, product page rise-in, cart drawer timing

**Files:**
- Modify: `resources/js/Layouts/StoreLayout.vue`
- Modify: `resources/js/Pages/Catalog/Product.vue`
- Modify: `resources/js/Components/Store/CartDrawer.vue`

**Interfaces:**
- Consumes: `animate-rise-in` (Task 4).

- [ ] **Step 1: Header shadow on scroll (`StoreLayout.vue`)**

Change the vue import and add after `const isMenuOpen = ref(false);`:

```js
import { onBeforeUnmount, onMounted, ref } from 'vue';
```

```js
const isScrolled = ref(false);
const updateScrolled = () => {
    isScrolled.value = window.scrollY > 8;
};

onMounted(() => {
    updateScrolled();
    window.addEventListener('scroll', updateScrolled, { passive: true });
});
onBeforeUnmount(() => window.removeEventListener('scroll', updateScrolled));
```

Replace the header opening tag:

```vue
        <header
            class="sticky top-0 z-30 bg-brand-900 text-brand-50 transition-shadow duration-200 motion-reduce:transition-none"
            :class="{ 'shadow-lg shadow-ink/30': isScrolled }"
        >
```

- [ ] **Step 2: Product page rise-in (`Catalog/Product.vue`)**

Inside the top grid `<div class="mx-auto grid max-w-6xl …">`, change the two direct child `<div>` opening tags:

```vue
            <div class="animate-rise-in">
```

```vue
            <div class="animate-rise-in [animation-delay:75ms]">
```

- [ ] **Step 3: Drawer timing (`CartDrawer.vue`)**

Backdrop: enter becomes 250 ms ease-out (Tailwind 3 has no `duration-250`, so use `duration-[250ms]`), leave 200 ms ease-in:

```vue
        <Transition
            enter-active-class="transition-opacity duration-[250ms] ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
```

Panel transition:

```vue
        <Transition
            enter-active-class="transition-transform duration-[250ms] ease-out motion-reduce:transition-none"
            enter-from-class="translate-x-full"
            leave-active-class="transition-transform duration-200 ease-in motion-reduce:transition-none"
            leave-to-class="translate-x-full"
        >
```

- [ ] **Step 4: Build and commit**

Run: `npm run build`
Expected: build succeeds.

```bash
git add resources/js/Layouts/StoreLayout.vue resources/js/Pages/Catalog/Product.vue resources/js/Components/Store/CartDrawer.vue
git commit -m "feat: header scroll shadow, product page rise-in and drawer timing"
```

---

### Task 7: Verify in the browser and open the PR

**Files:** none (fixes found here go in the file they belong to, with their own commit).

- [ ] **Step 1: Reseed locally**

Run: `php artisan migrate:fresh --seed`
Expected: completes; `php artisan tinker --execute 'echo App\Models\ProductImage::count();'` prints `7`.

- [ ] **Step 2: Bundle budget**

Run: `npm run build`
Expected: storefront JS (app chunk + Home/Catalog chunks) well under ~200 KB; no new vendor chunk.

- [ ] **Step 3: Playwright checks**

Use `get-absolute-url` for the base URL. At 375×812 and 1280×800, visit `/`, `/categories/t-shirts`, `/products/wolf-badge-tee`, `/search?q=tee`; open the cart drawer after adding a size. Confirm: hero intro plays, marquee scrolls with no gap at 1280, feature row and cards reveal on scroll, hovering a card shows the cream photo (1280 only), switching colour on the product page changes the gallery, header shadow appears after scrolling, no console errors (`browser_console_messages`). Take a screenshot of each page at each width.

- [ ] **Step 4: Reduced-motion check**

`browser_emulate_media` with `reducedMotion: 'reduce'`, reload `/` and `/categories/t-shirts`: everything visible immediately, marquee static, no hidden cards.

- [ ] **Step 5: Run the affected tests**

Run: `php artisan test --compact tests/Feature/CatalogSeederTest.php tests/Feature/CatalogModelTest.php tests/Feature/HomePageTest.php tests/Feature/Storefront`
Expected: PASS. Then ask the user to run the full suite with `php artisan test --compact`.

- [ ] **Step 6: Push and open the PR**

```bash
git push -u origin feat/storefront-motion
gh pr create --title "feat: storefront motion and stock imagery" --body "Implements docs/superpowers/specs/2026-10-24-storefront-motion-design.md. Editorial home (hero, marquee, features, category tiles), scroll reveal for product grids, card hover image, header shadow, seeded stock photos. CSS-only motion, honours prefers-reduced-motion. Screenshots: <attach>."
```
