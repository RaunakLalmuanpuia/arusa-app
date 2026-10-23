    # Phase 2: Catalog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Categories, products, variants and images with admin CRUD, plus the storefront home, category, product and search pages.

**Architecture:** Same shape as Phase 1: plain controllers + one Form Request per resource, rendering Inertia/Vue pages. Admin routes live in `routes/admin.php`, admin pages in `resources/js/Pages/Admin/`. A shared `AdminTable.vue` drives server-side search/filter/pagination through the query string. Uploaded images are resized with PHP's built-in GD into three WebP widths on the `public` disk. Search uses Postgres full-text (`whereFullText`), so tests move from SQLite to a Postgres test database in Task 1.

**Tech Stack:** Laravel 13.38, PHP 8.4 (GD with WebP, exif), Inertia v2 (inertia-laravel 2.0.28, @inertiajs/vue3 2.3) + Vue 3, Tailwind 3, Pest 5, PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-10-23-arusa-ecommerce-design.md` (sections 3, 5, 6, 11 phase 2)

## Global Constraints

- No new PHP/JS dependencies without approval. **No Lucide**; no animation library; no icon package. Image resizing uses PHP GD (already installed), not Intervention.
- All money is stored as **integer paise**. Admin forms show rupees and convert with `toPaise()` before submit; the server only ever receives and validates paise.
- Effective variant price = `price_override ?? base_price`. Always computed server-side.
- Icons: inline SVG Vue components in `resources/js/Components/Icons/`.
- Motion: CSS transitions / Vue `<Transition>` only, 150–300 ms, respects `prefers-reduced-motion`.
- Design skills apply to **storefront pages only** (Tasks 6–8): ui-ux-pro-max is primary; design-motion-principles for motion; impeccable for audit/polish only. Admin stays plain and functional (gray Tailwind, Breeze inputs).
- Mobile-first; storefront JS budget < ~200 KB; Inertia `prefetch` on product links; images WebP + `srcset` + `loading="lazy"` below the fold.
- PHP: curly braces always, explicit return types, constructor promotion, PHPDoc over inline comments, TitleCase enum keys.
- Admin routes: `routes/admin.php` behind `auth` + `can:access-admin`; every Form Request `authorize()` returns `$this->user()->can('access-admin')`.
- After PHP changes: `vendor/bin/pint --dirty --format agent`. Run narrowest tests with `php artisan test --compact <path>`.
- Activate project skills while working: `arusa-commerce` (always), `laravel-best-practices` (PHP), `inertia-vue-development` (Vue pages), `tailwindcss-development` (styling), `testing-best-practices` (tests).

## Decisions made in this plan

- **Tests run on Postgres** (`arusa_app_testing` on the local server already used by `.env`). Needed now for full-text search and in Phase 3 for `lockForUpdate`. SQLite is dropped from `phpunit.xml`.
- **Images link to a colour, not a variant.** The spec lists `product_images.variant_id`; an image belongs to every size of one colour, so the column is `colour` (nullable string, null = all colours). Colour switching on the product page filters by it.
- **Variants are never deleted**, only deactivated (`is_active = false`). Phase 3 order items will reference variant rows.
- **`products.description` is NOT NULL (default `''`)**: Postgres `to_tsvector(NULL) || …` is NULL and would hide the product from search.
- **Storefront category pages are not paginated** (catalog < ~100 products). Marked with a `shortcut:` comment.
- **Desktop header nav shows categories**; info pages stay in the footer and the mobile menu.

## File Map

| File | Responsibility |
|---|---|
| `phpunit.xml` | Postgres test DB |
| `database/migrations/*_create_catalog_tables.php` | categories, products, product_variants, product_images |
| `app/Models/{Category,Product,ProductVariant,ProductImage}.php` | Catalog models, scopes, price + image URL helpers |
| `database/factories/{Category,Product,ProductVariant,ProductImage}Factory.php` | Test data |
| `database/seeders/CatalogSeeder.php` | Demo catalog for manual checks |
| `resources/js/money.js` | `formatPaise()`, `toPaise()` |
| `resources/js/Components/Admin/AdminTable.vue` | Shared admin list: search, filter slot, column slots, prev/next |
| `app/Http/Controllers/Admin/CategoryController.php`, `app/Http/Requests/Admin/CategoryRequest.php` | Category CRUD |
| `resources/js/Pages/Admin/Categories/{Index,Create,Edit}.vue`, `.../Partials/CategoryForm.vue` | Category screens |
| `app/Http/Controllers/Admin/ProductController.php`, `app/Http/Requests/Admin/ProductRequest.php` | Product CRUD |
| `resources/js/Pages/Admin/Products/{Index,Create,Edit}.vue`, `.../Partials/ProductForm.vue` | Product screens |
| `app/Http/Controllers/Admin/ProductVariantController.php`, `app/Http/Requests/Admin/UpdateProductVariantsRequest.php` | Save the variant grid |
| `resources/js/Pages/Admin/Products/Partials/VariantGrid.vue` | Size × colour generator + inline edit |
| `app/Actions/StoreProductImage.php` | GD resize → 3 WebP widths |
| `app/Http/Controllers/Admin/ProductImageController.php`, `app/Http/Requests/Admin/{StoreProductImagesRequest,UpdateProductImagesRequest}.php` | Upload, reorder/edit, delete images |
| `resources/js/Components/Admin/ImageUploader.vue` | Multi-upload, reorder, alt/colour, delete |
| `app/Http/Controllers/ProductController.php`, `resources/js/Pages/Catalog/Product.vue` | Storefront product page |
| `app/Http/Controllers/CategoryController.php`, `resources/js/Pages/Catalog/Category.vue` | Storefront category listing + filters |
| `app/Http/Controllers/HomeController.php`, `resources/js/Pages/Home.vue` | Home with "New in" grid |
| `app/Http/Controllers/SearchController.php`, `resources/js/Pages/Catalog/Search.vue` | Full-text search |
| `resources/js/Components/Store/{ProductCard,ProductGrid}.vue` | Shared listing UI |
| `resources/js/Components/Icons/IconSearch.vue` | Search icon |
| `app/Http/Middleware/HandleInertiaRequests.php` | + shared `categories` for nav |
| `resources/js/Layouts/{StoreLayout,AdminLayout}.vue` | Nav links |
| `routes/web.php`, `routes/admin.php` | Routes |

---

### Task 1: Postgres test database and catalog schema

**Files:**
- Modify: `phpunit.xml`
- Create: `database/migrations/2026_10_24_000000_create_catalog_tables.php`
- Create: `app/Models/Category.php`, `app/Models/Product.php`, `app/Models/ProductVariant.php`, `app/Models/ProductImage.php`
- Create: `database/factories/CategoryFactory.php`, `ProductFactory.php`, `ProductVariantFactory.php`, `ProductImageFactory.php`
- Create: `database/seeders/CatalogSeeder.php`; Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/CatalogModelTest.php`

**Interfaces:**
- Produces:
  - `Category`: fillable `parent_id, name, slug, sort_order, is_active`; `parent(): BelongsTo`, `children(): HasMany`, `products(): HasMany`; scope `active()`.
  - `Product`: fillable `category_id, name, slug, description, base_price, is_active`; `category()`, `variants()` (ordered by id, chaperoned), `images()` (ordered by `sort_order`); scopes `visible()` (active product, active category, ≥ 1 active variant) and `withCardData()` (eager-loads active variants + first image); `cardData(): array{name, slug, price_from, has_price_range, in_stock, image}`.
  - `ProductVariant`: fillable `sku, size, colour, price_override, stock, is_active`; `product()`; `effectivePrice(): int`.
  - `ProductImage`: `const WIDTHS = [400, 800, 1200]`; fillable `colour, path, alt, sort_order`; `url(int $width = 800): string`, `srcset(): string`, `toImageData(): array{src, srcset, alt}`, `deleteFiles(): void`. `path` is a file stem; files are `{path}-{width}.webp` on the `public` disk.
  - Factories with states: `ProductFactory::inactive()`, `ProductVariantFactory::outOfStock()`, `ProductVariantFactory::inactive()`, `CategoryFactory::inactive()`.

- [ ] **Step 1: Create the test database and point Pest at it**

Run (password is `DB_PASSWORD` from `.env`):
```bash
createdb -h 127.0.0.1 -U postgres arusa_app_testing
```

In `phpunit.xml` replace the two SQLite lines:
```xml
        <env name="DB_CONNECTION" value="pgsql"/>
        <env name="DB_DATABASE" value="arusa_app_testing"/>
```
Host, port, user and password still come from `.env`.

- [ ] **Step 2: Run the whole Phase 1 suite on Postgres**

Run: `php artisan test --compact`
Expected: all 46 tests PASS. If one fails, it is a SQLite/Postgres difference in Phase 1 code — fix it before continuing.

- [ ] **Step 3: Commit**

```bash
git add phpunit.xml
git commit -m "test: run the suite on a Postgres test database"
```

- [ ] **Step 4: Write the failing model test**

`php artisan make:test --pest CatalogModelTest --no-interaction`, then:

```php
<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;

test('variant price falls back to the product base price', function () {
    $product = Product::factory()->create(['base_price' => 99900]);
    $plain = ProductVariant::factory()->for($product)->create();
    $override = ProductVariant::factory()->for($product)->create(['price_override' => 129900]);

    expect($plain->effectivePrice())->toBe(99900)
        ->and($override->effectivePrice())->toBe(129900);
});

test('visible products need an active product, active category and an active variant', function () {
    $visible = Product::factory()->has(ProductVariant::factory(), 'variants')->create();
    Product::factory()->inactive()->has(ProductVariant::factory(), 'variants')->create();
    Product::factory()->for(Category::factory()->inactive())->has(ProductVariant::factory(), 'variants')->create();
    Product::factory()->has(ProductVariant::factory()->inactive(), 'variants')->create();
    Product::factory()->create();

    expect(Product::visible()->pluck('id')->all())->toBe([$visible->id]);
});

test('card data reports the price range, stock and first image', function () {
    $product = Product::factory()->create(['base_price' => 50000]);
    ProductVariant::factory()->for($product)->outOfStock()->create();
    ProductVariant::factory()->for($product)->create(['price_override' => 60000]);
    ProductVariant::factory()->for($product)->inactive()->create(['price_override' => 100]);

    $card = Product::withCardData()->find($product->id)->cardData();

    expect($card)->toMatchArray([
        'slug' => $product->slug,
        'price_from' => 50000,
        'has_price_range' => true,
        'in_stock' => true,
        'image' => null,
    ]);
});

test('stock can never go negative', function () {
    $variant = ProductVariant::factory()->create(['stock' => 0]);

    $variant->decrement('stock');
})->throws(QueryException::class);
```

- [ ] **Step 5: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/CatalogModelTest.php`
Expected: FAIL — `Class "App\Models\Product" not found`.

- [ ] **Step 6: Write the migration**

`php artisan make:migration create_catalog_tables --no-interaction`, then:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->default('');
            $table->unsignedInteger('base_price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->fullText(['name', 'description']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 50)->unique();
            $table->string('size', 30)->nullable();
            $table->string('colour', 30)->nullable();
            $table->unsignedInteger('price_override')->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('alter table product_variants add constraint product_variants_stock_non_negative check (stock >= 0)');

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('colour', 30)->nullable();
            $table->string('path');
            $table->string('alt')->default('');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
```

Rename the generated file to `2026_10_24_000000_create_catalog_tables.php` only if its timestamp sorts before the Phase 1 migrations (it should not).

- [ ] **Step 7: Write the models**

`php artisan make:model Category --factory --no-interaction` (repeat for `Product`, `ProductVariant`, `ProductImage`), then replace each model body.

`app/Models/Category.php`:
```php
<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'slug', 'sort_order', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
```

`app/Models/Product.php`:
```php
<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'slug', 'description', 'base_price', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Variants in creation order, which is the order the admin typed sizes and colours.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id')->chaperone();
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Products a shopper may see: active, in an active category, with at least one active variant.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true))
            ->whereHas('variants', fn (Builder $variant) => $variant->where('is_active', true));
    }

    /**
     * Eager-load what cardData() needs: active variants and the first image.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function withCardData(Builder $query): void
    {
        $query->with([
            'variants' => fn (HasMany $variants) => $variants->where('is_active', true),
            'images' => fn (HasMany $images) => $images->limit(1),
        ]);
    }

    /**
     * Listing card props. Requires the withCardData() scope.
     *
     * @return array{name: string, slug: string, price_from: int, has_price_range: bool, in_stock: bool, image: array{src: string, srcset: string, alt: string}|null}
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
        ];
    }
}
```

`app/Models/ProductVariant.php`:
```php
<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sku', 'size', 'colour', 'price_override', 'stock', 'is_active'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_override' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Price in paise: the override when set, otherwise the product's base price.
     */
    public function effectivePrice(): int
    {
        return $this->price_override ?? $this->product->base_price;
    }
}
```

`app/Models/ProductImage.php`:
```php
<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['colour', 'path', 'alt', 'sort_order'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    /**
     * Widths each upload is resized to. Files are stored as "{path}-{width}.webp".
     *
     * @var list<int>
     */
    public const WIDTHS = [400, 800, 1200];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(int $width = 800): string
    {
        return Storage::disk('public')->url("{$this->path}-{$width}.webp");
    }

    public function srcset(): string
    {
        return collect(self::WIDTHS)
            ->map(fn (int $width): string => "{$this->url($width)} {$width}w")
            ->implode(', ');
    }

    /**
     * @return array{src: string, srcset: string, alt: string}
     */
    public function toImageData(): array
    {
        return ['src' => $this->url(), 'srcset' => $this->srcset(), 'alt' => $this->alt];
    }

    public function deleteFiles(): void
    {
        Storage::disk('public')->delete(
            array_map(fn (int $width): string => "{$this->path}-{$width}.webp", self::WIDTHS),
        );
    }
}
```

- [ ] **Step 8: Write the factories**

`database/factories/CategoryFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

`database/factories/ProductFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(12),
            'base_price' => fake()->numberBetween(5, 30) * 10000,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

`database/factories/ProductVariantFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('ARU-####-??')),
            'size' => null,
            'colour' => null,
            'price_override' => null,
            'stock' => 10,
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

`database/factories/ProductImageFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'colour' => null,
            'path' => 'products/test/'.Str::uuid(),
            'alt' => fake()->words(3, true),
            'sort_order' => 0,
        ];
    }
}
```

- [ ] **Step 9: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/CatalogModelTest.php`
Expected: 4 PASS.

- [ ] **Step 10: Demo catalog seeder**

`php artisan make:seeder CatalogSeeder --no-interaction`:
```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * A small demo catalog (no images) for checking the storefront locally.
     */
    public function run(): void
    {
        $apparel = Category::factory()->create(['name' => 'Apparel', 'slug' => 'apparel', 'sort_order' => 1]);
        $tees = Category::factory()->create(['name' => 'T-Shirts', 'slug' => 't-shirts', 'parent_id' => $apparel->id]);
        $goods = Category::factory()->create(['name' => 'Goods', 'slug' => 'goods', 'sort_order' => 2]);

        foreach (['Wolf Badge Tee', 'Heritage Logo Tee', 'Puff Print Tee'] as $index => $name) {
            $tee = Product::factory()->for($tees)->create(['name' => $name, 'slug' => str($name)->slug(), 'base_price' => 99900]);

            foreach (['Black', 'Cream'] as $colour) {
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
    }
}
```

In `DatabaseSeeder::run()` add `$this->call(CatalogSeeder::class);` after `PageSeeder`.

Run: `php artisan migrate:fresh --seed` (local dev DB). Expected: no errors.

- [ ] **Step 11: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database app/Models tests/Feature/CatalogModelTest.php
git commit -m "feat: catalog schema, models and factories"
```

---

### Task 2: Shared admin table and category CRUD

**Files:**
- Create: `resources/js/money.js`
- Create: `resources/js/Components/Admin/AdminTable.vue`
- Create: `app/Http/Controllers/Admin/CategoryController.php`, `app/Http/Requests/Admin/CategoryRequest.php`
- Create: `resources/js/Pages/Admin/Categories/Index.vue`, `Create.vue`, `Edit.vue`, `Partials/CategoryForm.vue`
- Modify: `routes/admin.php`, `resources/js/Layouts/AdminLayout.vue`
- Test: `tests/Feature/Admin/CategoryManagementTest.php`

**Interfaces:**
- Consumes: `Category` (Task 1).
- Produces:
  - `AdminTable.vue` props: `rows` (a serialized Laravel `LengthAwarePaginator`: `data`, `from`, `to`, `total`, `prev_page_url`, `next_page_url`), `columns: Array<{ key: string, label: string }>`, `filters: Object` (current query values). Slots: `cell-{key}` (`{ row }`), `filters` (`{ query }` — bind extra inputs to `query.x`), `empty`. Submitting the toolbar runs `router.get(currentPath, query, { preserveState: true, replace: true })`.
  - `money.js`: `formatPaise(paise: number): string` (`₹1,299` / `₹1,299.50`), `toPaise(rupees): number|null`.
  - Routes `admin.categories.{index,create,store,edit,update,destroy}`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Admin/CategoryManagementTest --no-interaction`:
```php
<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admin can search the category list', function () {
    Category::factory()->create(['name' => 'Hoodies']);
    Category::factory()->create(['name' => 'Mugs']);

    $this->actingAs($this->admin)
        ->get('/admin/categories?search=hood')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Categories/Index')
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Hoodies')
            ->where('filters.search', 'hood'));
});

test('admin can create a subcategory', function () {
    $parent = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post('/admin/categories', [
            'name' => 'Tees', 'slug' => 'tees', 'parent_id' => $parent->id, 'sort_order' => 2, 'is_active' => true,
        ])
        ->assertRedirect('/admin/categories');

    expect(Category::where('slug', 'tees')->first()->parent_id)->toBe($parent->id);
});

test('categories nest only one level deep', function () {
    $child = Category::factory()->for(Category::factory(), 'parent')->create();

    $this->actingAs($this->admin)
        ->post('/admin/categories', [
            'name' => 'Deep', 'slug' => 'deep', 'parent_id' => $child->id, 'sort_order' => 0, 'is_active' => true,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('a category with subcategories cannot be given a parent', function () {
    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);
    $other = Category::factory()->create();

    $this->actingAs($this->admin)
        ->put("/admin/categories/{$parent->id}", [
            'name' => $parent->name, 'slug' => $parent->slug, 'parent_id' => $other->id, 'sort_order' => 0, 'is_active' => true,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('a category with products cannot be deleted', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    $this->actingAs($this->admin)
        ->delete("/admin/categories/{$category->id}")
        ->assertSessionHasErrors('category');

    expect($category->fresh())->not->toBeNull();
});

test('an empty category can be deleted', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)->delete("/admin/categories/{$category->id}")->assertRedirect('/admin/categories');

    expect(Category::count())->toBe(0);
});

test('non-admins cannot manage categories', function () {
    $this->actingAs(User::factory()->create())->get('/admin/categories')->assertForbidden();
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Admin/CategoryManagementTest.php`
Expected: FAIL — 404 on `/admin/categories`.

- [ ] **Step 3: Form Request**

`php artisan make:request Admin/CategoryRequest --no-interaction`:
```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('categories', 'slug')->ignore($category)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id])),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('parent_id') && $this->route('category')?->children()->exists()) {
                    $validator->errors()->add('parent_id', 'A category with subcategories cannot have a parent.');
                }
            },
        ];
    }
}
```

- [ ] **Step 4: Controller and routes**

`php artisan make:controller Admin/CategoryController --no-interaction`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::query()
                ->with('parent:id,name')
                ->withCount('products')
                ->when($search, fn ($query) => $query->whereLike('name', "%{$search}%"))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent' => $category->parent?->name,
                    'sort_order' => $category->sort_order,
                    'is_active' => $category->is_active,
                    'products_count' => $category->products_count,
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Create', [
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return to_route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category->only('id', 'name', 'slug', 'parent_id', 'sort_order', 'is_active'),
            'parents' => $this->parentOptions($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return to_route('admin.categories.index')->with('status', 'Category saved.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->withErrors(['category' => "Move the products and subcategories out of \"{$category->name}\" first."]);
        }

        $category->delete();

        return to_route('admin.categories.index')->with('status', 'Category deleted.');
    }

    /**
     * Top-level categories that may be chosen as a parent.
     *
     * @return Collection<int, Category>
     */
    private function parentOptions(?Category $except = null): Collection
    {
        return Category::whereNull('parent_id')
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
```

`routes/admin.php` — add the import and, inside the group:
```php
        Route::resource('categories', CategoryController::class)->except('show');
```

- [ ] **Step 5: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Admin/CategoryManagementTest.php`
Expected: 7 PASS (Inertia test pages need not exist, per commit 2850335).

- [ ] **Step 6: Money helper**

`resources/js/money.js`:
```js
export const formatPaise = (paise) =>
    `₹${(paise / 100).toLocaleString('en-IN', {
        minimumFractionDigits: paise % 100 ? 2 : 0,
        maximumFractionDigits: 2,
    })}`;

export const toPaise = (rupees) => (rupees === '' || rupees === null ? null : Math.round(Number(rupees) * 100));
```

- [ ] **Step 7: AdminTable component**

`resources/js/Components/Admin/AdminTable.vue`:
```vue
<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    rows: { type: Object, required: true },
    columns: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const query = reactive({ search: '', ...props.filters });

const apply = () => {
    router.get(window.location.pathname, query, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="space-y-4">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
            <input
                v-model="query.search"
                type="search"
                placeholder="Search…"
                aria-label="Search"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-64"
            />
            <slot name="filters" :query="query" />
            <button type="submit" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">Filter</button>
        </form>

        <div class="overflow-x-auto rounded bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-gray-500">
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="px-4 py-3 font-medium">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-b last:border-0">
                        <td v-for="column in columns" :key="column.key" class="px-4 py-3">
                            <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] }}</slot>
                        </td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td :colspan="columns.length" class="px-4 py-6 text-center text-gray-500">
                            <slot name="empty">Nothing found.</slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="rows.total > 0" class="flex items-center justify-between text-sm text-gray-600" aria-label="Pagination">
            <span>{{ rows.from }}–{{ rows.to }} of {{ rows.total }}</span>
            <div class="flex gap-2">
                <Link v-if="rows.prev_page_url" :href="rows.prev_page_url" preserve-state class="rounded border bg-white px-3 py-1">Previous</Link>
                <Link v-if="rows.next_page_url" :href="rows.next_page_url" preserve-state class="rounded border bg-white px-3 py-1">Next</Link>
            </div>
        </nav>
    </div>
</template>
```

- [ ] **Step 8: Category pages**

`resources/js/Pages/Admin/Categories/Partials/CategoryForm.vue`:
```vue
<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    form: { type: Object, required: true },
    parents: { type: Array, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form class="max-w-xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="name" value="Name" />
            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>
        <div>
            <InputLabel for="slug" value="URL slug (e.g. t-shirts)" />
            <TextInput id="slug" v-model="form.slug" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.slug" />
        </div>
        <div>
            <InputLabel for="parent_id" value="Parent category" />
            <select
                id="parent_id"
                v-model="form.parent_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option :value="null">None (top level)</option>
                <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
            </select>
            <InputError class="mt-2" :message="form.errors.parent_id" />
        </div>
        <div>
            <InputLabel for="sort_order" value="Sort order (lower shows first)" />
            <TextInput id="sort_order" v-model="form.sort_order" type="number" min="0" class="mt-1 block w-32" required />
            <InputError class="mt-2" :message="form.errors.sort_order" />
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <Checkbox v-model:checked="form.is_active" />
            Visible on the store
        </label>
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
```

`resources/js/Pages/Admin/Categories/Create.vue`:
```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CategoryForm from './Partials/CategoryForm.vue';

defineProps({
    parents: { type: Array, required: true },
});

const form = useForm({ name: '', slug: '', parent_id: null, sort_order: 0, is_active: true });
</script>

<template>
    <Head title="New category" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">New category</h1>
        </template>

        <CategoryForm :form="form" :parents="parents" @submit="form.post(route('admin.categories.store'))" />
    </AdminLayout>
</template>
```

`resources/js/Pages/Admin/Categories/Edit.vue`:
```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CategoryForm from './Partials/CategoryForm.vue';

const props = defineProps({
    category: { type: Object, required: true },
    parents: { type: Array, required: true },
});

const form = useForm({ ...props.category });
</script>

<template>
    <Head :title="`Edit ${category.name}`" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Edit category</h1>
        </template>

        <CategoryForm :form="form" :parents="parents" @submit="form.put(route('admin.categories.update', category.id))" />
    </AdminLayout>
</template>
```

`resources/js/Pages/Admin/Categories/Index.vue`:
```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';

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
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Categories</h1>
                <Link :href="route('admin.categories.create')" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">New category</Link>
            </div>
        </template>

        <p v-if="$page.props.errors.category" class="mb-4 rounded bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">
            {{ $page.props.errors.category }}
        </p>

        <AdminTable :rows="categories" :columns="columns" :filters="filters">
            <template #cell-name="{ row }">
                <Link :href="route('admin.categories.edit', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-parent="{ row }">{{ row.parent ?? '—' }}</template>
            <template #cell-is_active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
            <template #cell-actions="{ row }">
                <button type="button" class="text-red-600 hover:underline" @click="deleteCategory(row)">Delete</button>
            </template>
            <template #empty>No categories yet.</template>
        </AdminTable>
    </AdminLayout>
</template>
```

In `resources/js/Layouts/AdminLayout.vue` add to `links`, after Dashboard:
```js
    { label: 'Categories', route: 'admin.categories.index', match: 'admin.categories.*' },
```

- [ ] **Step 9: Check in the browser**

Run `npm run build`, open `/admin/categories` as an admin: create a parent and a child, search, delete an empty one, try deleting one with products (seeded `t-shirts`) and see the red message. Check at 375 px width.

- [ ] **Step 10: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes resources/js tests/Feature/Admin/CategoryManagementTest.php
git commit -m "feat: admin categories with shared AdminTable"
```

---

### Task 3: Admin product CRUD (details)

**Files:**
- Create: `app/Http/Controllers/Admin/ProductController.php`, `app/Http/Requests/Admin/ProductRequest.php`
- Create: `resources/js/Pages/Admin/Products/Index.vue`, `Create.vue`, `Edit.vue`, `Partials/ProductForm.vue`
- Modify: `routes/admin.php`, `resources/js/Layouts/AdminLayout.vue`
- Test: `tests/Feature/Admin/ProductManagementTest.php`

**Interfaces:**
- Consumes: `Product`, `Category`, `ProductImage::deleteFiles()` (Task 1); `AdminTable.vue`, `formatPaise`, `toPaise` (Task 2).
- Produces:
  - Routes `admin.products.{index,create,store,edit,update,destroy}`. `store` redirects to `admin.products.edit`.
  - `Admin/Products/Edit` receives `product` (`id, name, slug, category_id, description, base_price, is_active`) and `categories` (`[{ id, label }]`). Tasks 4 and 5 add `variants`, `images`, `colours` props to the same `edit()` method and add sections to `Edit.vue`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Admin/ProductManagementTest --no-interaction`:
```php
<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function productPayload(array $overrides = []): array
{
    return [
        'name' => 'Wolf Badge Tee',
        'slug' => 'wolf-badge-tee',
        'category_id' => Category::factory()->create()->id,
        'description' => 'Heavyweight cotton.',
        'base_price' => 99900,
        'is_active' => true,
        ...$overrides,
    ];
}

test('admin can filter the product list by category and search', function () {
    $tees = Category::factory()->create();
    Product::factory()->for($tees)->create(['name' => 'Wolf Tee']);
    Product::factory()->for($tees)->create(['name' => 'Logo Tee']);
    Product::factory()->create(['name' => 'Wolf Mug']);

    $this->actingAs($this->admin)
        ->get("/admin/products?category_id={$tees->id}&search=wolf")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Wolf Tee'));
});

test('creating a product redirects to its editor', function () {
    $response = $this->actingAs($this->admin)->post('/admin/products', productPayload());

    $product = Product::firstWhere('slug', 'wolf-badge-tee');
    $response->assertRedirect("/admin/products/{$product->id}/edit");
    expect($product->base_price)->toBe(99900);
});

test('an empty description is stored as an empty string', function () {
    $this->actingAs($this->admin)->post('/admin/products', productPayload(['description' => '']));

    expect(Product::firstWhere('slug', 'wolf-badge-tee')->description)->toBe('');
});

test('base price must be whole paise of at least one rupee', function (mixed $price) {
    $this->actingAs($this->admin)
        ->post('/admin/products', productPayload(['base_price' => $price]))
        ->assertSessionHasErrors('base_price');
})->with([99.5, 50, -100, 'abc']);

test('admin can update a product keeping its own slug', function () {
    $product = Product::factory()->create(['slug' => 'wolf-badge-tee']);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$product->id}", productPayload(['name' => 'Renamed']))
        ->assertRedirect("/admin/products/{$product->id}/edit");

    expect($product->fresh()->name)->toBe('Renamed');
});

test('deleting a product removes its image files', function () {
    Storage::fake('public');
    $image = ProductImage::factory()->create();
    Storage::disk('public')->put("{$image->path}-400.webp", 'x');

    $this->actingAs($this->admin)
        ->delete("/admin/products/{$image->product_id}")
        ->assertRedirect('/admin/products');

    Storage::disk('public')->assertMissing("{$image->path}-400.webp");
    expect(Product::count())->toBe(0);
});

test('non-admins cannot manage products', function () {
    $this->actingAs(User::factory()->create())->post('/admin/products', productPayload())->assertForbidden();
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Admin/ProductManagementTest.php`
Expected: FAIL — 404 / 405 on `/admin/products`.

- [ ] **Step 3: Form Request**

`php artisan make:request Admin/ProductRequest --no-interaction`:
```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * The column is NOT NULL so full-text search never sees a null description.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['description' => (string) $this->input('description')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:150', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['string', 'max:5000'],
            'base_price' => ['required', 'integer', 'min:100', 'max:10000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Controller and routes**

`php artisan make:controller Admin/ProductController --no-interaction`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'category_id' => $request->integer('category_id') ?: null,
        ];

        return Inertia::render('Admin/Products/Index', [
            'products' => Product::query()
                ->with('category:id,name')
                ->withCount('variants')
                ->withSum('variants', 'stock')
                ->when($filters['search'], fn ($query, string $search) => $query->whereLike('name', "%{$search}%"))
                ->when($filters['category_id'], fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
                ->latest()
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category->name,
                    'base_price' => $product->base_price,
                    'variants_count' => $product->variants_count,
                    'stock' => (int) $product->variants_sum_stock,
                    'is_active' => $product->is_active,
                ]),
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        return to_route('admin.products.edit', $product)->with('status', 'Product created. Add variants and images below.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Edit', [
            'product' => $product->only('id', 'name', 'slug', 'category_id', 'description', 'base_price', 'is_active'),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return to_route('admin.products.edit', $product)->with('status', 'Product saved.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->images->each(fn (ProductImage $image) => $image->deleteFiles());
        $product->delete();

        return to_route('admin.products.index')->with('status', 'Product deleted.');
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function categoryOptions(): Collection
    {
        return Category::with('parent:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'label' => $category->parent ? "{$category->parent->name} › {$category->name}" : $category->name,
            ])
            ->sortBy('label')
            ->values();
    }
}
```

`routes/admin.php` — add the import and inside the group:
```php
        Route::resource('products', ProductController::class)->except('show');
```

- [ ] **Step 5: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Admin/ProductManagementTest.php`
Expected: 10 PASS (4 dataset rows count separately).

- [ ] **Step 6: Product pages**

`resources/js/Pages/Admin/Products/Partials/ProductForm.vue`:
```vue
<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form class="max-w-3xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="name" value="Name" />
            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>
        <div>
            <InputLabel for="slug" value="URL slug (e.g. wolf-badge-tee)" />
            <TextInput id="slug" v-model="form.slug" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.slug" />
        </div>
        <div>
            <InputLabel for="category_id" value="Category" />
            <select
                id="category_id"
                v-model="form.category_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
            >
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</option>
            </select>
            <InputError class="mt-2" :message="form.errors.category_id" />
        </div>
        <div>
            <InputLabel for="base_price" value="Price (₹)" />
            <TextInput id="base_price" v-model="form.base_price" type="number" min="1" step="0.01" class="mt-1 block w-40" required />
            <p class="mt-1 text-xs text-gray-500">Variants can override this price.</p>
            <InputError class="mt-2" :message="form.errors.base_price" />
        </div>
        <div>
            <InputLabel for="description" value="Description" />
            <textarea
                id="description"
                v-model="form.description"
                rows="6"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-2" :message="form.errors.description" />
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <Checkbox v-model:checked="form.is_active" />
            Visible on the store
        </label>
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
```

`resources/js/Pages/Admin/Products/Create.vue`:
```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from './Partials/ProductForm.vue';
import { toPaise } from '@/money';

const props = defineProps({
    categories: { type: Array, required: true },
});

const form = useForm({
    name: '',
    slug: '',
    category_id: props.categories[0]?.id ?? null,
    description: '',
    base_price: '',
    is_active: true,
});

const submit = () => {
    form.transform((data) => ({ ...data, base_price: toPaise(data.base_price) })).post(route('admin.products.store'));
};
</script>

<template>
    <Head title="New product" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">New product</h1>
        </template>

        <p v-if="categories.length === 0" class="text-sm text-gray-600">Create a category first.</p>
        <ProductForm v-else :form="form" :categories="categories" @submit="submit" />
    </AdminLayout>
</template>
```

`resources/js/Pages/Admin/Products/Edit.vue`:
```vue
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from './Partials/ProductForm.vue';
import { toPaise } from '@/money';

const props = defineProps({
    product: { type: Object, required: true },
    categories: { type: Array, required: true },
});

const form = useForm({ ...props.product, base_price: props.product.base_price / 100 });

const submit = () => {
    form.transform((data) => ({ ...data, base_price: toPaise(data.base_price) }))
        .put(route('admin.products.update', props.product.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">{{ product.name }}</h1>
                <Link :href="route('admin.products.index')" class="text-sm text-gray-600 underline">All products</Link>
            </div>
        </template>

        <div class="space-y-8">
            <ProductForm :form="form" :categories="categories" @submit="submit" />
        </div>
    </AdminLayout>
</template>
```

`resources/js/Pages/Admin/Products/Index.vue`:
```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
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
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Products</h1>
                <Link :href="route('admin.products.create')" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">New product</Link>
            </div>
        </template>

        <AdminTable :rows="products" :columns="columns" :filters="filters">
            <template #filters="{ query }">
                <select
                    v-model="query.category_id"
                    aria-label="Category"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="null">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</option>
                </select>
            </template>
            <template #cell-name="{ row }">
                <Link :href="route('admin.products.edit', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-base_price="{ row }">{{ formatPaise(row.base_price) }}</template>
            <template #cell-stock="{ row }">
                <span :class="row.stock <= 3 ? 'font-medium text-red-700' : ''">{{ row.stock }}</span>
            </template>
            <template #cell-is_active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
            <template #cell-actions="{ row }">
                <button type="button" class="text-red-600 hover:underline" @click="deleteProduct(row)">Delete</button>
            </template>
            <template #empty>No products yet.</template>
        </AdminTable>
    </AdminLayout>
</template>
```

In `AdminLayout.vue` `links`, after Categories:
```js
    { label: 'Products', route: 'admin.products.index', match: 'admin.products.*' },
```

- [ ] **Step 7: Check in the browser**

`npm run build`; as admin create a product with price `999.50`, confirm it lands on the editor and the list shows `₹999.50`. Filter by category. Check at 375 px.

- [ ] **Step 8: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes resources/js tests/Feature/Admin/ProductManagementTest.php
git commit -m "feat: admin product CRUD"
```

---

### Task 4: Variant grid

**Files:**
- Create: `app/Http/Controllers/Admin/ProductVariantController.php`, `app/Http/Requests/Admin/UpdateProductVariantsRequest.php`
- Create: `resources/js/Pages/Admin/Products/Partials/VariantGrid.vue`
- Modify: `app/Http/Controllers/Admin/ProductController.php` (`edit()`), `resources/js/Pages/Admin/Products/Edit.vue`, `routes/admin.php`
- Test: `tests/Feature/Admin/ProductVariantTest.php`

**Interfaces:**
- Consumes: `Product::variants()`, `ProductVariant` (Task 1); `Admin/Products/Edit` page (Task 3); `toPaise` (Task 2).
- Produces:
  - `PUT /admin/products/{product}/variants` named `admin.products.variants.update`. Payload `variants: [{ id?: int, size: ?string, colour: ?string, sku: string, price_override: ?int (paise), stock: int, is_active: bool }]`. Rows with `id` are updated, rows without are created. Nothing is deleted.
  - `Admin/Products/Edit` gains prop `variants` (`[{ id, size, colour, sku, price_override, stock, is_active }]`).

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Admin/ProductVariantTest --no-interaction`:
```php
<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create();
});

function variantRow(array $overrides = []): array
{
    return ['size' => 'M', 'colour' => 'Black', 'sku' => 'TEE-BLACK-M', 'price_override' => null, 'stock' => 5, 'is_active' => true, ...$overrides];
}

test('saving the grid updates existing variants and creates new ones', function () {
    $existing = ProductVariant::factory()->for($this->product)->create(['size' => 'S', 'colour' => 'Black', 'sku' => 'TEE-BLACK-S']);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(['id' => $existing->id, 'size' => 'S', 'sku' => 'TEE-BLACK-S', 'stock' => 9]),
            variantRow(['price_override' => 109900]),
        ]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($existing->fresh()->stock)->toBe(9)
        ->and($this->product->variants()->count())->toBe(2)
        ->and($this->product->variants()->firstWhere('sku', 'TEE-BLACK-M')->price_override)->toBe(109900);
});

test('size and colour combinations must be unique', function () {
    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(),
            variantRow(['sku' => 'OTHER']),
        ]])
        ->assertSessionHasErrors('variants');
});

test('a sku used by another product is rejected', function () {
    ProductVariant::factory()->create(['sku' => 'TEE-BLACK-M']);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [variantRow()]])
        ->assertSessionHasErrors('variants.0.sku');
});

test('a variant of another product cannot be edited through this product', function () {
    $foreign = ProductVariant::factory()->create();

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [variantRow(['id' => $foreign->id])]])
        ->assertSessionHasErrors('variants.0.id');
});

test('stock cannot be negative', function () {
    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [variantRow(['stock' => -1])]])
        ->assertSessionHasErrors('variants.0.stock');
});

test('the editor receives the variants', function () {
    ProductVariant::factory()->for($this->product)->create();

    $this->actingAs($this->admin)
        ->get("/admin/products/{$this->product->id}/edit")
        ->assertInertia(fn ($page) => $page->has('variants', 1));
});

test('non-admins cannot save variants', function () {
    $this->actingAs(User::factory()->create())
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [variantRow()]])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Admin/ProductVariantTest.php`
Expected: FAIL — 404/405 on the variants route; `variants` prop missing.

- [ ] **Step 3: Form Request**

`php artisan make:request Admin/UpdateProductVariantsRequest --no-interaction`:
```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductVariantsRequest extends FormRequest
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
        $rules = [
            'variants' => ['required', 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $this->route('product')->id)],
            'variants.*.size' => ['nullable', 'string', 'max:30'],
            'variants.*.colour' => ['nullable', 'string', 'max:30'],
            'variants.*.sku' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'variants.*.price_override' => ['nullable', 'integer', 'min:100', 'max:10000000'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'variants.*.is_active' => ['required', 'boolean'],
        ];

        foreach ((array) $this->input('variants') as $index => $variant) {
            $rules["variants.{$index}.sku"][] = Rule::unique('product_variants', 'sku')
                ->ignore(is_array($variant) ? ($variant['id'] ?? null) : null);
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $combinations = collect((array) $this->input('variants'))
                    ->map(fn (mixed $variant): string => is_array($variant)
                        ? mb_strtolower(($variant['size'] ?? '').'|'.($variant['colour'] ?? ''))
                        : '');

                if ($combinations->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('variants', 'Each size and colour combination can appear only once.');
                }
            },
        ];
    }
}
```

- [ ] **Step 4: Controller, route, editor prop**

`php artisan make:controller Admin/ProductVariantController --no-interaction`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProductVariantsRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    /**
     * Save the whole variant grid. Variants are never deleted; the admin deactivates them instead.
     */
    public function update(UpdateProductVariantsRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            foreach ($request->validated('variants') as $variant) {
                $attributes = Arr::except($variant, 'id');

                if (isset($variant['id'])) {
                    $product->variants()->whereKey($variant['id'])->update($attributes);
                } else {
                    $product->variants()->create($attributes);
                }
            }
        });

        return back()->with('status', 'Variants saved.');
    }
}
```

`routes/admin.php`, inside the group:
```php
        Route::put('products/{product}/variants', [ProductVariantController::class, 'update'])->name('products.variants.update');
```

In `Admin\ProductController::edit()` add to the props array:
```php
            'variants' => $product->variants->map->only('id', 'size', 'colour', 'sku', 'price_override', 'stock', 'is_active'),
```

- [ ] **Step 5: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Admin/ProductVariantTest.php`
Expected: 7 PASS.

- [ ] **Step 6: VariantGrid component**

`resources/js/Pages/Admin/Products/Partials/VariantGrid.vue`:
```vue
<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { toPaise } from '@/money';

const props = defineProps({
    product: { type: Object, required: true },
    variants: { type: Array, required: true },
});

const sizes = ref('');
const colours = ref('');

const form = useForm({
    variants: props.variants.map((variant) => ({
        ...variant,
        price_override: variant.price_override === null ? '' : variant.price_override / 100,
    })),
});

const split = (text) => {
    const values = text.split(',').map((value) => value.trim()).filter(Boolean);
    return values.length ? values : [null];
};

const comboKey = (size, colour) => `${size ?? ''}|${colour ?? ''}`.toLowerCase();

const skuFor = (size, colour) =>
    [props.product.slug, colour, size].filter(Boolean).join('-').toUpperCase().replace(/[^A-Z0-9-]/g, '');

const generate = () => {
    const existing = new Set(form.variants.map((variant) => comboKey(variant.size, variant.colour)));

    for (const colour of split(colours.value)) {
        for (const size of split(sizes.value)) {
            if (!existing.has(comboKey(size, colour))) {
                form.variants.push({ size, colour, sku: skuFor(size, colour), price_override: '', stock: 0, is_active: true });
            }
        }
    }
};

const save = () => {
    form.transform((data) => ({
        variants: data.variants.map((variant) => ({ ...variant, price_override: toPaise(variant.price_override) })),
    })).put(route('admin.products.variants.update', props.product.id), { preserveScroll: true });
};

const cell = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <section class="rounded bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">Variants</h2>
        <p class="mt-1 text-sm text-gray-600">
            Type sizes and colours separated by commas, then Generate. Leave both empty for a single-variant product.
            Variants can't be deleted; untick Active to hide one.
        </p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm text-gray-700">
                Sizes
                <input v-model="sizes" placeholder="S, M, L, XL" :class="cell" class="mt-1 block sm:w-56" />
            </label>
            <label class="text-sm text-gray-700">
                Colours
                <input v-model="colours" placeholder="Black, Cream" :class="cell" class="mt-1 block sm:w-56" />
            </label>
            <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm" @click="generate">Generate</button>
        </div>

        <InputError class="mt-4" :message="form.errors.variants" />

        <form class="mt-4" @submit.prevent="save">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b text-gray-500">
                        <tr>
                            <th class="px-2 py-2 font-medium">Colour</th>
                            <th class="px-2 py-2 font-medium">Size</th>
                            <th class="px-2 py-2 font-medium">SKU</th>
                            <th class="px-2 py-2 font-medium">Price override (₹)</th>
                            <th class="px-2 py-2 font-medium">Stock</th>
                            <th class="px-2 py-2 font-medium">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(variant, index) in form.variants" :key="variant.id ?? `new-${index}`" class="border-b align-top last:border-0">
                            <td class="px-2 py-2">{{ variant.colour ?? '—' }}</td>
                            <td class="px-2 py-2">{{ variant.size ?? '—' }}</td>
                            <td class="px-2 py-2">
                                <input v-model="variant.sku" :class="cell" :aria-label="`SKU row ${index + 1}`" required />
                                <InputError class="mt-1" :message="form.errors[`variants.${index}.sku`]" />
                            </td>
                            <td class="px-2 py-2">
                                <input v-model="variant.price_override" type="number" min="1" step="0.01" placeholder="Base price" :class="cell" :aria-label="`Price override row ${index + 1}`" />
                                <InputError class="mt-1" :message="form.errors[`variants.${index}.price_override`]" />
                            </td>
                            <td class="px-2 py-2">
                                <input v-model="variant.stock" type="number" min="0" :class="cell" :aria-label="`Stock row ${index + 1}`" required />
                                <InputError class="mt-1" :message="form.errors[`variants.${index}.stock`]" />
                            </td>
                            <td class="px-2 py-2">
                                <input v-model="variant.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" :aria-label="`Active row ${index + 1}`" />
                            </td>
                        </tr>
                        <tr v-if="form.variants.length === 0">
                            <td colspan="6" class="px-2 py-6 text-center text-gray-500">No variants yet. Generate some above.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PrimaryButton class="mt-4" :disabled="form.processing || form.variants.length === 0">Save variants</PrimaryButton>
        </form>
    </section>
</template>
```

In `Admin/Products/Edit.vue`: import `VariantGrid from './Partials/VariantGrid.vue'`, add prop `variants: { type: Array, required: true }`, and inside the `space-y-8` div after `ProductForm`:
```vue
            <VariantGrid :product="product" :variants="variants" />
```

- [ ] **Step 7: Check in the browser**

`npm run build`; on a product, enter `S, M, L` and `Black, Cream`, Generate (6 rows), set stock and one override, Save. Generate again: no duplicate rows. Reload: values persist. Clear a SKU to see the per-row error.

- [ ] **Step 8: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes resources/js tests/Feature/Admin/ProductVariantTest.php
git commit -m "feat: admin variant grid"
```

---

### Task 5: Product images (resize, upload, reorder)

**Files:**
- Create: `app/Actions/StoreProductImage.php`
- Create: `app/Http/Controllers/Admin/ProductImageController.php`, `app/Http/Requests/Admin/StoreProductImagesRequest.php`, `app/Http/Requests/Admin/UpdateProductImagesRequest.php`
- Create: `resources/js/Components/Admin/ImageUploader.vue`
- Modify: `app/Http/Controllers/Admin/ProductController.php` (`edit()`), `resources/js/Pages/Admin/Products/Edit.vue`, `routes/admin.php`
- Test: `tests/Feature/Admin/ProductImageTest.php`

**Interfaces:**
- Consumes: `ProductImage::WIDTHS`, `url()`, `deleteFiles()` (Task 1); `Admin/Products/Edit` (Task 3).
- Produces:
  - `StoreProductImage::handle(Product $product, UploadedFile $file): ProductImage` — writes `{path}-{400,800,1200}.webp` to the `public` disk, never upscaling.
  - Routes: `POST admin/products/{product}/images` (`admin.products.images.store`, field `images[]`), `PUT admin/products/{product}/images` (`admin.products.images.update`, `images: [{ id, alt, colour }]` in display order), `DELETE admin/products/{product}/images/{image}` (`admin.products.images.destroy`, scoped).
  - `Admin/Products/Edit` gains props `images` (`[{ id, url, alt, colour, delete_url }]`) and `colours` (`string[]` from the variants).
  - `ImageUploader.vue` props: `images`, `colours`, `uploadUrl`, `saveUrl`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Admin/ProductImageTest --no-interaction`:
```php
<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create();
});

function storedWidth(string $file): int
{
    return getimagesizefromstring(Storage::disk('public')->get($file))[0];
}

test('uploads are resized to each width as webp', function () {
    ProductImage::factory()->for($this->product)->create(['sort_order' => 4]);

    $this->actingAs($this->admin)
        ->post("/admin/products/{$this->product->id}/images", ['images' => [UploadedFile::fake()->image('tee.jpg', 1600, 1200)]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $image = $this->product->images()->latest('id')->first();

    expect($image->sort_order)->toBe(5)
        ->and(storedWidth("{$image->path}-400.webp"))->toBe(400)
        ->and(storedWidth("{$image->path}-1200.webp"))->toBe(1200)
        ->and(getimagesizefromstring(Storage::disk('public')->get("{$image->path}-800.webp"))['mime'])->toBe('image/webp');
});

test('small uploads are not upscaled', function () {
    $this->actingAs($this->admin)
        ->post("/admin/products/{$this->product->id}/images", ['images' => [UploadedFile::fake()->image('small.png', 300, 300)]]);

    expect(storedWidth("{$this->product->images()->first()->path}-1200.webp"))->toBe(300);
});

test('non-image uploads are rejected', function () {
    $this->actingAs($this->admin)
        ->post("/admin/products/{$this->product->id}/images", ['images' => [UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')]])
        ->assertSessionHasErrors('images.0');

    expect($this->product->images()->count())->toBe(0);
});

test('admin can reorder images and set alt text and colour', function () {
    [$first, $second] = ProductImage::factory()->for($this->product)->count(2)->sequence(['sort_order' => 0], ['sort_order' => 1])->create();

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/images", ['images' => [
            ['id' => $second->id, 'alt' => 'Back print', 'colour' => 'Black'],
            ['id' => $first->id, 'alt' => null, 'colour' => null],
        ]])
        ->assertSessionHasNoErrors();

    expect($this->product->images()->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($second->fresh()->only('alt', 'colour'))->toBe(['alt' => 'Back print', 'colour' => 'Black'])
        ->and($first->fresh()->alt)->toBe('');
});

test('deleting an image removes its files', function () {
    $image = ProductImage::factory()->for($this->product)->create();
    Storage::disk('public')->put("{$image->path}-800.webp", 'x');

    $this->actingAs($this->admin)->delete("/admin/products/{$this->product->id}/images/{$image->id}")->assertRedirect();

    Storage::disk('public')->assertMissing("{$image->path}-800.webp");
    expect(ProductImage::count())->toBe(0);
});

test('an image of another product cannot be deleted through this product', function () {
    $foreign = ProductImage::factory()->create();

    $this->actingAs($this->admin)->delete("/admin/products/{$this->product->id}/images/{$foreign->id}")->assertNotFound();
});

test('non-admins cannot upload images', function () {
    $this->actingAs(User::factory()->create())
        ->post("/admin/products/{$this->product->id}/images", ['images' => [UploadedFile::fake()->image('tee.jpg')]])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Admin/ProductImageTest.php`
Expected: FAIL — 404/405 on the images routes.

- [ ] **Step 3: Resize action**

`php artisan make:class Actions/StoreProductImage --no-interaction`:
```php
<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProductImage
{
    /**
     * Resize the upload to every ProductImage width (never upscaling), save each as WebP, and attach it to the product.
     */
    public function handle(Product $product, UploadedFile $file): ProductImage
    {
        $source = @imagecreatefromstring($file->getContent());

        if ($source === false) {
            throw ValidationException::withMessages(['images' => "Could not read {$file->getClientOriginalName()}."]);
        }

        $source = $this->uprightJpeg($source, $file);
        $path = "products/{$product->id}/".Str::uuid();

        foreach (ProductImage::WIDTHS as $width) {
            $resized = imagescale($source, min($width, imagesx($source)));
            ob_start();
            imagewebp($resized, null, 80);
            Storage::disk('public')->put("{$path}-{$width}.webp", ob_get_clean());
        }

        return $product->images()->create([
            'path' => $path,
            'alt' => $product->name,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
        ]);
    }

    /**
     * Phone photos store rotation in EXIF instead of the pixels; GD ignores it.
     */
    private function uprightJpeg(GdImage $image, UploadedFile $file): GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        return match (@exif_read_data($file->getRealPath())['Orientation'] ?? 1) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
```

Note: with a 300 px source, the `-1200.webp` file is 300 px wide, so the `srcset` `1200w` descriptor overstates it. Harmless (the browser just shows a smaller image); add above `WIDTHS` in `ProductImage`: `// shortcut: srcset assumes uploads are ≥ 1200 px wide; store real widths if small uploads become common.`

- [ ] **Step 4: Form Requests**

`php artisan make:request Admin/StoreProductImagesRequest --no-interaction`:
```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductImagesRequest extends FormRequest
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
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }
}
```

`php artisan make:request Admin/UpdateProductImagesRequest --no-interaction`:
```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductImagesRequest extends FormRequest
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
            'images' => ['required', 'array'],
            'images.*.id' => ['required', 'integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $this->route('product')->id)],
            'images.*.alt' => ['nullable', 'string', 'max:150'],
            'images.*.colour' => ['nullable', 'string', 'max:30'],
        ];
    }
}
```

- [ ] **Step 5: Controller, routes, editor props**

`php artisan make:controller Admin/ProductImageController --no-interaction`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StoreProductImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductImagesRequest;
use App\Http\Requests\Admin\UpdateProductImagesRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;

class ProductImageController extends Controller
{
    public function store(StoreProductImagesRequest $request, Product $product, StoreProductImage $storeProductImage): RedirectResponse
    {
        foreach ($request->file('images') as $file) {
            $storeProductImage->handle($product, $file);
        }

        return back()->with('status', 'Images uploaded.');
    }

    /**
     * Save alt text and colour, and set the order to the order the images were sent in.
     */
    public function update(UpdateProductImagesRequest $request, Product $product): RedirectResponse
    {
        foreach ($request->validated('images') as $position => $image) {
            $product->images()->whereKey($image['id'])->update([
                'alt' => $image['alt'] ?? '',
                'colour' => $image['colour'] ?? null,
                'sort_order' => $position,
            ]);
        }

        return back()->with('status', 'Images saved.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        $image->deleteFiles();
        $image->delete();

        return back()->with('status', 'Image deleted.');
    }
}
```

`routes/admin.php`, inside the group:
```php
        Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
        Route::put('products/{product}/images', [ProductImageController::class, 'update'])->name('products.images.update');
        Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
            ->scopeBindings()
            ->name('products.images.destroy');
```

In `Admin\ProductController::edit()` add to the props (import `ProductImage` is already there):
```php
            'images' => $product->images->map(fn (ProductImage $image): array => [
                'id' => $image->id,
                'url' => $image->url(400),
                'alt' => $image->alt,
                'colour' => $image->colour,
                'delete_url' => route('admin.products.images.destroy', [$product, $image]),
            ]),
            'colours' => $product->variants->pluck('colour')->filter()->unique()->values(),
```

- [ ] **Step 6: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Admin/ProductImageTest.php`
Expected: 7 PASS.

- [ ] **Step 7: ImageUploader component**

`resources/js/Components/Admin/ImageUploader.vue`:
```vue
<script setup>
import { computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    images: { type: Array, required: true },
    colours: { type: Array, default: () => [] },
    uploadUrl: { type: String, required: true },
    saveUrl: { type: String, required: true },
});

const editable = (images) => images.map(({ id, alt, colour }) => ({ id, alt, colour }));

const upload = useForm({ images: [] });
const form = useForm({ images: editable(props.images) });

// Uploads and deletes change the server list; edits not yet saved are dropped.
watch(() => props.images, (images) => {
    form.images = editable(images);
});

const byId = computed(() => Object.fromEntries(props.images.map((image) => [image.id, image])));

const onFiles = (event) => {
    upload.images = Array.from(event.target.files);
    upload.post(props.uploadUrl, {
        preserveScroll: true,
        onFinish: () => {
            event.target.value = '';
        },
    });
};

const move = (index, offset) => {
    const [image] = form.images.splice(index, 1);
    form.images.splice(index + offset, 0, image);
};

const remove = (image) => {
    router.delete(byId.value[image.id].delete_url, {
        preserveScroll: true,
        onBefore: () => window.confirm('Delete this image?'),
    });
};

const save = () => form.put(props.saveUrl, { preserveScroll: true });

const field = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <section class="rounded bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">Images</h2>
        <p class="mt-1 text-sm text-gray-600">JPG, PNG or WebP, up to 8 MB each. The first image is the listing photo.</p>

        <label class="mt-4 inline-flex cursor-pointer items-center rounded border border-gray-300 px-3 py-2 text-sm">
            {{ upload.processing ? 'Uploading…' : 'Add images' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" :disabled="upload.processing" @change="onFiles" />
        </label>
        <ul v-if="Object.keys(upload.errors).length" class="mt-2 text-sm text-red-600">
            <li v-for="(message, key) in upload.errors" :key="key">{{ message }}</li>
        </ul>

        <form v-if="form.images.length" class="mt-6" @submit.prevent="save">
            <ul class="space-y-3">
                <li v-for="(image, index) in form.images" :key="image.id" class="flex flex-wrap items-center gap-3 border-b pb-3 last:border-0">
                    <img :src="byId[image.id]?.url" :alt="image.alt" class="h-20 w-20 rounded object-cover" />
                    <div class="flex-1 space-y-2">
                        <input v-model="image.alt" placeholder="Alt text" :class="field" :aria-label="`Alt text image ${index + 1}`" />
                        <select v-model="image.colour" :class="field" :aria-label="`Colour image ${index + 1}`">
                            <option :value="null">All colours</option>
                            <option v-for="colour in colours" :key="colour" :value="colour">{{ colour }}</option>
                        </select>
                    </div>
                    <div class="flex gap-1">
                        <button type="button" class="rounded border px-2 py-1 text-sm disabled:opacity-40" :disabled="index === 0" aria-label="Move up" @click="move(index, -1)">↑</button>
                        <button type="button" class="rounded border px-2 py-1 text-sm disabled:opacity-40" :disabled="index === form.images.length - 1" aria-label="Move down" @click="move(index, 1)">↓</button>
                        <button type="button" class="rounded px-2 py-1 text-sm text-red-600 hover:underline" @click="remove(image)">Delete</button>
                    </div>
                </li>
            </ul>
            <PrimaryButton class="mt-4" :disabled="form.processing">Save images</PrimaryButton>
        </form>
    </section>
</template>
```

In `Admin/Products/Edit.vue`: import `ImageUploader from '@/Components/Admin/ImageUploader.vue'`, add props `images: { type: Array, required: true }` and `colours: { type: Array, required: true }`, and after `VariantGrid`:
```vue
            <ImageUploader
                :images="images"
                :colours="colours"
                :upload-url="route('admin.products.images.store', product.id)"
                :save-url="route('admin.products.images.update', product.id)"
            />
```

- [ ] **Step 8: Storage link and browser check**

Run: `php artisan storage:link`. Then `npm run build`; upload two phone photos (one portrait JPEG to check EXIF rotation), reorder, set a colour, delete one. Confirm files under `storage/app/public/products/{id}/`.

- [ ] **Step 9: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes resources/js tests/Feature/Admin/ProductImageTest.php
git commit -m "feat: product image upload with WebP resizing"
```

---

### Task 6: Storefront product page

**Design:** Before writing Vue, invoke `ui-ux-pro-max` (product detail page, apparel, mobile-first, existing brand tokens in `tailwind.config.js`) and read `.claude/skills/design-motion-principles/SKILL.md` for the image switch and option-select states. Keep the Phase 1 visual language: `brand-*` palette, `font-display` headings, spaced uppercase labels, 44 px touch targets, `focusRing` outlines.

**Files:**
- Create: `app/Http/Controllers/ProductController.php`
- Create: `resources/js/Pages/Catalog/Product.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/Storefront/ProductPageTest.php`

**Interfaces:**
- Consumes: `Product::visible()`, `variants()`, `images()`, `ProductVariant::effectivePrice()`, `ProductImage::toImageData()` (Task 1); `formatPaise` (Task 2).
- Produces: route `products.show` → `/products/{product:slug}`. Page `Catalog/Product` props: `product` (`name, slug, description, category: { name, slug }`), `variants` (`[{ id, size, colour, price, in_stock }]`, active only), `images` (`[{ src, srcset, alt, colour }]`). Phase 3 adds the add-to-cart button using `variants[].id`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Storefront/ProductPageTest --no-interaction`:
```php
<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;

test('a visible product shows its active variants with effective prices', function () {
    $product = Product::factory()->create(['base_price' => 99900]);
    ProductVariant::factory()->for($product)->create(['size' => 'M', 'colour' => 'Black']);
    ProductVariant::factory()->for($product)->outOfStock()->create(['size' => 'XL', 'colour' => 'Black', 'price_override' => 109900]);
    ProductVariant::factory()->for($product)->inactive()->create(['size' => 'S', 'colour' => 'Black']);
    ProductImage::factory()->for($product)->create(['colour' => 'Black', 'sort_order' => 1]);
    ProductImage::factory()->for($product)->create(['sort_order' => 0]);

    $this->get("/products/{$product->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Product')
            ->where('product.name', $product->name)
            ->has('variants', 2)
            ->where('variants.0.price', 99900)
            ->where('variants.0.in_stock', true)
            ->where('variants.1.price', 109900)
            ->where('variants.1.in_stock', false)
            ->missing('variants.0.stock')
            ->where('images.0.colour', null)
            ->where('images.1.colour', 'Black'));
});

test('hidden products are not found', function (Closure $makeProduct) {
    $product = $makeProduct();

    $this->get("/products/{$product->slug}")->assertNotFound();
})->with([
    'inactive product' => fn () => fn () => Product::factory()->inactive()->has(ProductVariant::factory(), 'variants')->create(),
    'inactive category' => fn () => fn () => Product::factory()->for(Category::factory()->inactive())->has(ProductVariant::factory(), 'variants')->create(),
    'no active variants' => fn () => fn () => Product::factory()->create(),
]);
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Storefront/ProductPageTest.php`
Expected: FAIL — 404 on `/products/...` for the first test.

- [ ] **Step 3: Controller and route**

`php artisan make:controller ProductController --no-interaction`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Product $product): Response
    {
        abort_unless(Product::visible()->whereKey($product->id)->exists(), 404);

        $product->load([
            'category:id,name,slug',
            'variants' => fn (HasMany $variants) => $variants->where('is_active', true),
            'images',
        ]);

        return Inertia::render('Catalog/Product', [
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'category' => $product->category->only('name', 'slug'),
            ],
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'size' => $variant->size,
                'colour' => $variant->colour,
                'price' => $variant->effectivePrice(),
                'in_stock' => $variant->stock > 0,
            ]),
            'images' => $product->images->map(fn (ProductImage $image): array => [
                ...$image->toImageData(),
                'colour' => $image->colour,
            ]),
        ]);
    }
}
```

`routes/web.php` — import and add after the `pages.show` route:
```php
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
```

- [ ] **Step 4: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Storefront/ProductPageTest.php`
Expected: 4 PASS.

- [ ] **Step 5: Product page**

`resources/js/Pages/Catalog/Product.vue` — behaviour is fixed; refine the classes with the design pass:
```vue
<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    product: { type: Object, required: true },
    variants: { type: Array, required: true },
    images: { type: Array, required: true },
});

const unique = (values) => [...new Set(values.filter((value) => value !== null))];
const colours = unique(props.variants.map((variant) => variant.colour));
const sizes = unique(props.variants.map((variant) => variant.size));

const selectedColour = ref(colours.find((colour) => props.variants.some((v) => v.colour === colour && v.in_stock)) ?? colours[0] ?? null);
const selectedSize = ref(sizes.length === 0 ? null : undefined);
const activeImage = ref(0);

const selectedVariant = computed(() =>
    props.variants.find((variant) => variant.colour === selectedColour.value && variant.size === selectedSize.value),
);

const isSizeAvailable = (size) =>
    props.variants.some((variant) => variant.size === size && variant.colour === selectedColour.value && variant.in_stock);

const prices = computed(() => props.variants.filter((v) => v.colour === selectedColour.value).map((v) => v.price));
const priceLabel = computed(() => {
    if (selectedVariant.value) {
        return formatPaise(selectedVariant.value.price);
    }
    const low = Math.min(...prices.value);
    return low === Math.max(...prices.value) ? formatPaise(low) : `From ${formatPaise(low)}`;
});

const gallery = computed(() => {
    const matching = props.images.filter((image) => image.colour === null || image.colour === selectedColour.value);
    return matching.length ? matching : props.images;
});

const selectColour = (colour) => {
    selectedColour.value = colour;
    activeImage.value = 0;
    if (selectedSize.value && !isSizeAvailable(selectedSize.value)) {
        selectedSize.value = undefined;
    }
};

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <Head :title="product.name">
        <meta name="description" :content="product.description.slice(0, 155)" />
    </Head>

    <StoreLayout>
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-6 md:grid-cols-2 md:gap-12 md:py-12">
            <div>
                <div class="aspect-[4/5] overflow-hidden bg-brand-100">
                    <img
                        v-if="gallery[activeImage]"
                        :key="gallery[activeImage].src"
                        :src="gallery[activeImage].src"
                        :srcset="gallery[activeImage].srcset"
                        sizes="(min-width: 768px) 50vw, 100vw"
                        :alt="gallery[activeImage].alt"
                        fetchpriority="high"
                        class="h-full w-full object-cover transition-opacity duration-200 motion-reduce:transition-none"
                    />
                </div>
                <div v-if="gallery.length > 1" class="mt-3 flex gap-2 overflow-x-auto">
                    <button
                        v-for="(image, index) in gallery"
                        :key="image.src"
                        type="button"
                        class="h-16 w-16 shrink-0 overflow-hidden border"
                        :class="[index === activeImage ? 'border-ink' : 'border-transparent', focusRing]"
                        :aria-label="`Show image ${index + 1}`"
                        :aria-current="index === activeImage"
                        @click="activeImage = index"
                    >
                        <img :src="image.src" :srcset="image.srcset" sizes="64px" :alt="''" loading="lazy" class="h-full w-full object-cover" />
                    </button>
                </div>
            </div>

            <div>
                <Link :href="route('categories.show', product.category.slug)" class="text-xs uppercase tracking-[0.3em] text-brand-600 hover:underline">
                    {{ product.category.name }}
                </Link>
                <h1 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">{{ product.name }}</h1>
                <p class="mt-3 text-xl">{{ priceLabel }}</p>

                <fieldset v-if="colours.length" class="mt-8">
                    <legend class="text-xs font-medium uppercase tracking-[0.2em]">Colour: {{ selectedColour }}</legend>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="colour in colours"
                            :key="colour"
                            type="button"
                            class="min-h-11 border px-4 text-sm transition-colors duration-150"
                            :class="[colour === selectedColour ? 'border-ink bg-ink text-brand-50' : 'border-brand-300 hover:border-ink', focusRing]"
                            :aria-pressed="colour === selectedColour"
                            @click="selectColour(colour)"
                        >
                            {{ colour }}
                        </button>
                    </div>
                </fieldset>

                <fieldset v-if="sizes.length" class="mt-6">
                    <legend class="text-xs font-medium uppercase tracking-[0.2em]">Size</legend>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="size in sizes"
                            :key="size"
                            type="button"
                            class="min-h-11 min-w-11 border px-3 text-sm transition-colors duration-150"
                            :class="[
                                size === selectedSize ? 'border-ink bg-ink text-brand-50' : 'border-brand-300 hover:border-ink',
                                isSizeAvailable(size) ? '' : 'text-brand-400 line-through',
                                focusRing,
                            ]"
                            :aria-pressed="size === selectedSize"
                            :aria-disabled="!isSizeAvailable(size)"
                            @click="isSizeAvailable(size) && (selectedSize = size)"
                        >
                            {{ size }}
                        </button>
                    </div>
                    <Link :href="route('pages.show', 'size-guide')" class="mt-3 inline-block text-sm underline underline-offset-4">Size guide</Link>
                </fieldset>

                <p class="mt-6 text-sm" role="status">
                    <template v-if="selectedVariant">{{ selectedVariant.in_stock ? 'In stock' : 'Sold out' }}</template>
                    <template v-else-if="sizes.length">Choose a size</template>
                </p>

                <p v-if="product.description" class="mt-8 whitespace-pre-line leading-relaxed text-brand-700">{{ product.description }}</p>
            </div>
        </div>
    </StoreLayout>
</template>
```

`categories.show` is added in Task 7; until then, the Ziggy call throws in the browser, so do the browser check after Task 7 or temporarily link to `home`.

- [ ] **Step 6: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes resources/js tests/Feature/Storefront/ProductPageTest.php
git commit -m "feat: storefront product page with variant picker"
```

---

### Task 7: Storefront home and category pages with filters

**Design:** Invoke `ui-ux-pro-max` (product listing grid, filter UI on mobile, product card) before writing Vue. Same visual language as Task 6.

**Files:**
- Create: `app/Http/Controllers/HomeController.php`, `app/Http/Controllers/CategoryController.php`
- Create: `resources/js/Components/Store/ProductCard.vue`, `resources/js/Components/Store/ProductGrid.vue`, `resources/js/Components/Icons/IconSearch.vue`
- Create: `resources/js/Pages/Catalog/Category.vue`
- Modify: `resources/js/Pages/Home.vue`, `resources/js/Layouts/StoreLayout.vue`, `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`
- Test: `tests/Feature/Storefront/CategoryPageTest.php`, modify `tests/Feature/HomePageTest.php`

**Interfaces:**
- Consumes: `Product::visible()`, `withCardData()`, `cardData()`, `Category::active()` (Task 1); `formatPaise` (Task 2); route `products.show` (Task 6).
- Produces:
  - Route `categories.show` → `/categories/{category:slug}`, query `size`, `colour`, `min`, `max` (rupees). Page `Catalog/Category` props: `category { name, slug }`, `subcategories [{ name, slug }]`, `products [cardData]`, `sizes string[]`, `colours string[]`, `filters`.
  - Route `home` now uses `HomeController`; `Home` gets `products` (latest 8 cards).
  - Shared prop `categories: [{ name, slug }]` (active top-level, sorted).
  - `ProductGrid.vue` props `products` (cardData list), `eagerCount` (default 2); `ProductCard.vue` props `product`, `eager`.
  - `IconSearch.vue` (used by Task 8 via the header link to `search`; the header link is added in Task 8).

- [ ] **Step 1: Write the failing tests**

`php artisan make:test --pest Storefront/CategoryPageTest --no-interaction`:
```php
<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;

beforeEach(function () {
    $this->apparel = Category::factory()->create(['slug' => 'apparel']);
    $this->tees = Category::factory()->create(['parent_id' => $this->apparel->id]);
});

function productWith(Category $category, array ...$variants): Product
{
    $product = Product::factory()->for($category)->create(['base_price' => 100000]);

    foreach ($variants as $variant) {
        ProductVariant::factory()->for($product)->create($variant);
    }

    return $product;
}

test('a parent category lists its own and its subcategories visible products', function () {
    productWith($this->apparel, ['size' => 'M']);
    productWith($this->tees, ['size' => 'L']);
    Product::factory()->inactive()->for($this->tees)->has(ProductVariant::factory(), 'variants')->create();

    $this->get('/categories/apparel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Category')
            ->has('products', 2)
            ->has('subcategories', 1)
            ->where('sizes', ['M', 'L']));
});

test('size and colour filters match one in-stock variant', function () {
    $match = productWith($this->apparel, ['size' => 'M', 'colour' => 'Black']);
    productWith($this->apparel, ['size' => 'M', 'colour' => 'Cream'], ['size' => 'L', 'colour' => 'Black']);
    productWith($this->apparel, ['size' => 'M', 'colour' => 'Black', 'stock' => 0]);

    $this->get('/categories/apparel?size=M&colour=Black')
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.slug', $match->slug));
});

test('the price filter uses the effective variant price in rupees', function () {
    $cheap = productWith($this->apparel, ['price_override' => 50000]);
    productWith($this->apparel, []);

    $this->get('/categories/apparel?max=600')
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.slug', $cheap->slug));
});

test('inactive categories are not found', function () {
    $hidden = Category::factory()->inactive()->create();

    $this->get("/categories/{$hidden->slug}")->assertNotFound();
});

test('active top-level categories are shared for the navigation', function () {
    Category::factory()->inactive()->create();

    $this->get('/')->assertInertia(fn ($page) => $page
        ->has('categories', 1)
        ->where('categories.0.slug', 'apparel'));
});
```

Replace `tests/Feature/HomePageTest.php`:
```php
<?php

use App\Models\Product;
use App\Models\ProductVariant;

test('home page renders the storefront home with the newest products', function () {
    $product = Product::factory()->has(ProductVariant::factory(), 'variants')->create();
    Product::factory()->inactive()->has(ProductVariant::factory(), 'variants')->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('store.store_name', 'Arusa')
            ->has('products', 1)
            ->where('products.0.slug', $product->slug));
});
```

- [ ] **Step 2: Run them to see them fail**

Run: `php artisan test --compact tests/Feature/Storefront/CategoryPageTest.php tests/Feature/HomePageTest.php`
Expected: FAIL — 404 on `/categories/apparel`, missing `products` / `categories` props.

- [ ] **Step 3: Shared categories prop**

In `HandleInertiaRequests::share()` add (import `App\Models\Category`):
```php
            'categories' => fn (): array => Category::active()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->toArray(),
```

- [ ] **Step 4: Controllers and routes**

`php artisan make:controller HomeController --invokable --no-interaction`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'products' => Product::visible()->withCardData()->latest()->limit(8)->get()
                ->map(fn (Product $product): array => $product->cardData()),
        ]);
    }
}
```

`php artisan make:controller CategoryController --no-interaction`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category): Response
    {
        abort_unless($category->is_active, 404);

        $filters = $request->validate([
            'size' => ['nullable', 'string', 'max:30'],
            'colour' => ['nullable', 'string', 'max:30'],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
        ]);

        $subcategories = $category->children()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
        $categoryIds = $subcategories->pluck('id')->push($category->id);
        $isFiltered = collect($filters)->filter(fn ($value) => $value !== null)->isNotEmpty();

        // shortcut: no pagination (catalog < ~100 products); add ->paginate() when a category passes ~50.
        $products = Product::visible()
            ->whereIn('category_id', $categoryIds)
            ->when($isFiltered, fn (Builder $query) => $query->whereHas('variants', fn (Builder $variant) => $variant
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->when($filters['size'] ?? null, fn (Builder $q, string $size) => $q->where('size', $size))
                ->when($filters['colour'] ?? null, fn (Builder $q, string $colour) => $q->where('colour', $colour))
                ->when($filters['min'] ?? null, fn (Builder $q, $min) => $q->whereRaw('coalesce(price_override, products.base_price) >= ?', [(int) $min * 100]))
                ->when($filters['max'] ?? null, fn (Builder $q, $max) => $q->whereRaw('coalesce(price_override, products.base_price) <= ?', [(int) $max * 100]))))
            ->withCardData()
            ->latest()
            ->get();

        return Inertia::render('Catalog/Category', [
            'category' => $category->only('name', 'slug'),
            'subcategories' => $subcategories->map->only('name', 'slug'),
            'products' => $products->map(fn (Product $product): array => $product->cardData()),
            'sizes' => $this->variantValues($categoryIds, 'size'),
            'colours' => $this->variantValues($categoryIds, 'colour'),
            'filters' => $filters,
        ]);
    }

    /**
     * Distinct sizes or colours on sale in these categories, in the order the admin created them.
     *
     * @param  Collection<int, int>  $categoryIds
     * @return Collection<int, string>
     */
    private function variantValues(Collection $categoryIds, string $column): Collection
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->whereNotNull($column)
            ->whereHas('product', fn (Builder $product) => $product->visible()->whereIn('category_id', $categoryIds))
            ->groupBy($column)
            ->orderByRaw('min(id)')
            ->pluck($column);
    }
}
```

`routes/web.php` — import both and replace the home route; add the category route:
```php
Route::get('/', HomeController::class)->name('home');
Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
```

- [ ] **Step 5: Run the tests to see them pass**

Run: `php artisan test --compact tests/Feature/Storefront tests/Feature/HomePageTest.php`
Expected: all PASS.

- [ ] **Step 6: Listing components**

`resources/js/Components/Icons/IconSearch.vue`:
```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
    </svg>
</template>
```

`resources/js/Components/Store/ProductCard.vue`:
```vue
<script setup>
import { Link } from '@inertiajs/vue3';
import { formatPaise } from '@/money';

defineProps({
    product: { type: Object, required: true },
    eager: { type: Boolean, default: false },
});
</script>

<template>
    <Link
        :href="route('products.show', product.slug)"
        prefetch
        class="group block rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-700"
    >
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
            <span v-if="!product.in_stock" class="absolute left-2 top-2 bg-ink px-2 py-1 text-[10px] uppercase tracking-[0.2em] text-brand-50">Sold out</span>
        </div>
        <h3 class="mt-3 text-sm font-medium">{{ product.name }}</h3>
        <p class="mt-1 text-sm text-brand-700">
            <template v-if="product.has_price_range">From </template>{{ formatPaise(product.price_from) }}
        </p>
    </Link>
</template>
```

`resources/js/Components/Store/ProductGrid.vue`:
```vue
<script setup>
import ProductCard from '@/Components/Store/ProductCard.vue';

defineProps({
    products: { type: Array, required: true },
    eagerCount: { type: Number, default: 2 },
});
</script>

<template>
    <ul class="grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4">
        <li v-for="(product, index) in products" :key="product.slug">
            <ProductCard :product="product" :eager="index < eagerCount" />
        </li>
    </ul>
</template>
```

- [ ] **Step 7: Category page**

`resources/js/Pages/Catalog/Category.vue`:
```vue
<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductGrid from '@/Components/Store/ProductGrid.vue';

const props = defineProps({
    category: { type: Object, required: true },
    subcategories: { type: Array, required: true },
    products: { type: Array, required: true },
    sizes: { type: Array, required: true },
    colours: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const query = reactive({ size: null, colour: null, min: null, max: null, ...props.filters });

const apply = () => {
    const params = Object.fromEntries(Object.entries(query).filter(([, value]) => value !== null && value !== ''));
    router.get(route('categories.show', props.category.slug), params, { preserveState: true, preserveScroll: true, replace: true });
};

const toggle = (key, value) => {
    query[key] = query[key] === value ? null : value;
    apply();
};

const clear = () => {
    Object.assign(query, { size: null, colour: null, min: null, max: null });
    apply();
};

const chip = 'min-h-11 border px-3 text-sm transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <Head :title="category.name" />

    <StoreLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:py-12">
            <h1 class="font-display text-3xl font-semibold sm:text-4xl">{{ category.name }}</h1>

            <nav v-if="subcategories.length" aria-label="Subcategories" class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                <Link
                    v-for="sub in subcategories"
                    :key="sub.slug"
                    :href="route('categories.show', sub.slug)"
                    class="text-xs uppercase tracking-[0.2em] text-brand-600 underline-offset-4 hover:underline"
                >
                    {{ sub.name }}
                </Link>
            </nav>

            <details class="mt-6 border-y border-brand-200 py-3 md:open:pb-6" :open="Object.values(filters).some((v) => v !== null)">
                <summary class="flex min-h-11 cursor-pointer items-center text-xs font-medium uppercase tracking-[0.2em]">Filter</summary>
                <div class="mt-3 grid gap-6 md:grid-cols-3">
                    <fieldset v-if="sizes.length">
                        <legend class="text-xs uppercase tracking-[0.2em] text-brand-600">Size</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button v-for="size in sizes" :key="size" type="button" :class="[chip, query.size === size ? 'border-ink bg-ink text-brand-50' : 'border-brand-300']" :aria-pressed="query.size === size" @click="toggle('size', size)">{{ size }}</button>
                        </div>
                    </fieldset>
                    <fieldset v-if="colours.length">
                        <legend class="text-xs uppercase tracking-[0.2em] text-brand-600">Colour</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button v-for="colour in colours" :key="colour" type="button" :class="[chip, query.colour === colour ? 'border-ink bg-ink text-brand-50' : 'border-brand-300']" :aria-pressed="query.colour === colour" @click="toggle('colour', colour)">{{ colour }}</button>
                        </div>
                    </fieldset>
                    <form @submit.prevent="apply">
                        <fieldset>
                            <legend class="text-xs uppercase tracking-[0.2em] text-brand-600">Price (₹)</legend>
                            <div class="mt-2 flex items-center gap-2">
                                <input v-model="query.min" type="number" min="0" inputmode="numeric" placeholder="Min" aria-label="Minimum price" class="min-h-11 w-24 border-brand-300 bg-transparent text-sm" />
                                <span aria-hidden="true">–</span>
                                <input v-model="query.max" type="number" min="0" inputmode="numeric" placeholder="Max" aria-label="Maximum price" class="min-h-11 w-24 border-brand-300 bg-transparent text-sm" />
                                <button type="submit" :class="[chip, 'border-ink']">Go</button>
                            </div>
                        </fieldset>
                    </form>
                </div>
                <button type="button" class="mt-4 min-h-11 text-sm underline underline-offset-4" @click="clear">Clear filters</button>
            </details>

            <ProductGrid v-if="products.length" :products="products" class="mt-8" />
            <p v-else class="mt-12 text-center text-brand-700">Nothing here matches yet. Try clearing the filters.</p>
        </div>
    </StoreLayout>
</template>
```

- [ ] **Step 8: Home grid and header nav**

In `resources/js/Pages/Home.vue`: add `import ProductGrid from '@/Components/Store/ProductGrid.vue';`, `defineProps({ products: { type: Array, required: true } });`, and replace the "coming soon" `<section>` with:
```vue
        <section v-if="products.length" class="mx-auto max-w-6xl px-4 py-16 sm:py-20">
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
```

In `resources/js/Layouts/StoreLayout.vue`:
- The desktop `<nav aria-label="Information">` in the header becomes `<nav aria-label="Shop">` and loops over `page.props.categories`, linking to `route('categories.show', category.slug)` with the same classes.
- In the mobile menu, add the same category links above the info links (same classes as the existing mobile links), then a thin `border-brand-700` divider.
- Footer keeps the info links unchanged.

- [ ] **Step 9: Check in the browser**

`npm run build`, `php artisan migrate:fresh --seed`. At 375 px and 1280 px: home shows "New in" with placeholders (seed has no images; upload some via admin to check `srcset` and lazy loading). `/categories/apparel` lists the tees, filter by `M` + `Cream` hides the sold-out cream tee, price `max=1000` hides XL-only matches. Product page from a card: colour switch filters images, sold-out sizes are struck through. Check DevTools Network: product links prefetch on hover. Run `npm run build` and note the storefront chunk sizes against the 200 KB budget.

- [ ] **Step 10: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes resources/js tests/Feature
git commit -m "feat: storefront home and category pages with filters"
```

---

### Task 8: Search

**Design:** `ui-ux-pro-max` for the search results / empty state. Reuse `ProductGrid`.

**Files:**
- Create: `app/Http/Controllers/SearchController.php`, `resources/js/Pages/Catalog/Search.vue`
- Modify: `routes/web.php`, `resources/js/Layouts/StoreLayout.vue`
- Test: `tests/Feature/Storefront/SearchTest.php`

**Interfaces:**
- Consumes: `Product::visible()`, `withCardData()`, `cardData()` (Task 1); the GIN index on `(to_tsvector('english', name) || to_tsvector('english', description))` (Task 1); `ProductGrid.vue`, `IconSearch.vue` (Task 7).
- Produces: route `search` → `/search?q=`; page `Catalog/Search` props `query: string`, `products: [cardData]`.

- [ ] **Step 1: Write the failing test**

`php artisan make:test --pest Storefront/SearchTest --no-interaction`:
```php
<?php

use App\Models\Product;
use App\Models\ProductVariant;

function searchable(array $attributes): Product
{
    return Product::factory()->has(ProductVariant::factory(), 'variants')->create($attributes);
}

test('search matches stemmed words in the name and description', function () {
    $hoodie = searchable(['name' => 'Wolf Hoodie', 'description' => 'Fleece lined.']);
    $tee = searchable(['name' => 'Logo Tee', 'description' => 'Printed with puff ink on heavy cotton.']);
    searchable(['name' => 'Enamel Mug', 'description' => 'Holds coffee.']);

    $this->get('/search?q=hoodies')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Search')
            ->has('products', 1)
            ->where('products.0.slug', $hoodie->slug));

    $this->get('/search?q=printed cotton')
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.slug', $tee->slug));
});

test('search skips hidden products', function () {
    Product::factory()->inactive()->has(ProductVariant::factory(), 'variants')->create(['name' => 'Secret Hoodie']);

    $this->get('/search?q=hoodie')->assertInertia(fn ($page) => $page->has('products', 0));
});

test('an empty query shows no results', function () {
    searchable(['name' => 'Wolf Hoodie']);

    $this->get('/search')->assertOk()->assertInertia(fn ($page) => $page->where('query', '')->has('products', 0));
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `php artisan test --compact tests/Feature/Storefront/SearchTest.php`
Expected: FAIL — 404 on `/search`.

- [ ] **Step 3: Controller and route**

`php artisan make:controller SearchController --invokable --no-interaction`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = $request->string('q')->trim()->limit(100, '')->value();

        $products = $query === ''
            ? collect()
            : Product::visible()
                ->whereFullText(['name', 'description'], $query, ['mode' => 'websearch'])
                ->withCardData()
                ->latest()
                ->limit(48)
                ->get();

        return Inertia::render('Catalog/Search', [
            'query' => $query,
            'products' => $products->map(fn (Product $product): array => $product->cardData()),
        ]);
    }
}
```

`routes/web.php` — import and add:
```php
Route::get('/search', SearchController::class)->name('search');
```

- [ ] **Step 4: Run the test to see it pass**

Run: `php artisan test --compact tests/Feature/Storefront/SearchTest.php`
Expected: 3 PASS.

- [ ] **Step 5: Search page and header link**

`resources/js/Pages/Catalog/Search.vue`:
```vue
<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductGrid from '@/Components/Store/ProductGrid.vue';

const props = defineProps({
    query: { type: String, required: true },
    products: { type: Array, required: true },
});

const term = ref(props.query);

const submit = () => router.get(route('search'), { q: term.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head :title="query ? `Search: ${query}` : 'Search'" />

    <StoreLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:py-12">
            <form role="search" class="flex gap-2" @submit.prevent="submit">
                <input
                    v-model="term"
                    type="search"
                    name="q"
                    placeholder="Search tees, hoodies, goods…"
                    aria-label="Search products"
                    autofocus
                    class="min-h-11 flex-1 border-brand-300 bg-transparent"
                />
                <button type="submit" class="min-h-11 bg-ink px-5 text-xs font-medium uppercase tracking-[0.2em] text-brand-50">Search</button>
            </form>

            <template v-if="query">
                <p class="mt-6 text-sm text-brand-700" role="status">
                    {{ products.length }} {{ products.length === 1 ? 'result' : 'results' }} for “{{ query }}”
                </p>
                <ProductGrid v-if="products.length" :products="products" class="mt-6" />
                <p v-else class="mt-12 text-center text-brand-700">No products match. Try a shorter word, like “tee”.</p>
            </template>
        </div>
    </StoreLayout>
</template>
```

In `StoreLayout.vue`, in the right-hand header icon group before the account link, add (import `IconSearch`):
```vue
                    <Link
                        :href="route('search')"
                        class="inline-flex h-11 w-11 items-center justify-center rounded transition-colors duration-200 hover:text-brand-200"
                        :class="focusRing"
                        aria-label="Search"
                    >
                        <IconSearch class="h-6 w-6" />
                    </Link>
```

- [ ] **Step 6: Check in the browser**

`npm run build`; search "tee", "tees", "wolf -badge", an empty query and a nonsense word, at 375 px.

- [ ] **Step 7: Pint, full suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add app routes resources/js tests/Feature/Storefront/SearchTest.php
git commit -m "feat: storefront full-text search"
```
Expected: whole suite PASS.

---

## Phase 2 done when

- `php artisan test --compact` is green on the Postgres test database.
- As admin: create categories (one level of nesting), a product, generate a size × colour grid, set stock/prices, upload and reorder images with colours.
- Storefront: home shows "New in"; `/categories/{slug}` lists products of the category and its subcategories with size / colour / price filters; `/products/{slug}` switches images by colour and blocks sold-out sizes; `/search?q=` finds products by name and description. All usable at 375 px.
- `npm run build` storefront chunk sizes recorded against the 200 KB budget.

## Known follow-ups (not Phase 2)

- Phase 3: add-to-cart on the product page (uses `variants[].id`), `users.phone`, account page replacing Breeze `/dashboard`.
- Phase 3: deleting a product that has orders will fail on the order-items foreign key; the admin should deactivate instead. Show a friendly error then.
- Phase 3: saving the variant grid overwrites `stock` with the value loaded in the form, which can undo a concurrent order's decrement. Switch the grid to send stock adjustments, or lock and compare, once orders exist.
- Still open from Phase 1: base app JS chunk (Vue/Inertia/axios/Ziggy) is ~295 KB raw vs the 200 KB budget; brand mark (lion/2024 vs wolf/2026) awaiting owner.
- EXIF rotation is only checked by hand (Task 5 Step 8); no automated test.
