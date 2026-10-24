# Page Blocks (Admin-Editable Content Pages) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single Markdown body of content pages with ordered, typed section blocks the admin can add, edit, reorder and remove, render each block type with its own storefront design, and seed real content for About, Contact, Size Guide and Shipping & Returns.

**Architecture:** `pages.blocks` is a JSON list of `{type, data}`. `App\Enums\PageBlockType` owns the eight types and their validation rules; `PageRequest` merges each submitted block's rules. `Page::renderedBlocks()` turns stored blocks into client-ready data (Markdown → safe HTML, image paths → `src`/`srcset`, contact links). The image resize code moves from `StoreProductImage` into a shared `StoreWebpImage` action, also used by a new JSON upload endpoint for page images. The admin form gets a block list with one small field component per type; the storefront `Content/Show` maps each block to a component in `Components/Store/Blocks/`. Spec: `docs/superpowers/specs/2026-10-24-page-blocks-design.md`.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Inertia v2 + Vue 3, Tailwind 3.4, Pest.

## Global Constraints

- No new npm or composer dependencies. No axios (not installed): uploads use `fetch`.
- Never run `migrate:fresh` (the local DB has real orders). Plain `php artisan migrate` is fine. Never delete `public/hot`.
- Work on branch `feat/page-blocks`, not `master`. Run `git merge` / `git checkout` as separate commands.
- Block `type` values: `hero`, `text`, `image_text`, `features`, `table`, `faq`, `contact_cards`, `call_to_action`. Max 30 blocks per page.
- Feature icons allow-list: `cotton`, `feather`, `puff-print`, `shield`, `chat`, `bag`. Contact kinds: `phone`, `whatsapp`, `email`, `address`, `hours`.
- Phone/WhatsApp values match `/^[6-9]\d{9}$/`. CTA links are a site path starting with a single `/` or an `https://` URL.
- Page images are stored as `pages/{uuid}-{width}.webp` for widths `[800, 1600]` on the `public` disk.
- Markdown is only ever rendered server-side with `html_input: strip`, `allow_unsafe_links: false` and `TableExtension`. Raw Markdown never reaches `v-html`.
- Admin UI is plain and functional (house rule): reuse `InputLabel`, `InputError`, `PrimaryButton`, the field classes from `ImageUploader.vue`; no design-skill passes, no decorative motion. Reordering uses Move up / Move down buttons.
- Storefront: mobile-first; only existing motion (`animate-rise-in` with `[animation-delay:…]`, `Components/Store/Reveal.vue`), 150–300 ms, `motion-reduce:transition-none` on any transition; touch targets ≥ 44 px (`min-h-11`); keep `focus-ring` / `focus-ring-inverse`; images `srcset` + `loading="lazy"` below the fold. Icons are inline SVG components in `resources/js/Components/Icons/`. Square corners (the store uses none of `rounded-*` on storefront cards).
- Vue components have a single root element.
- After editing PHP, run `vendor/bin/pint --dirty --format agent`.
- Run the narrowest tests for each task with `php artisan test --compact <file>`.

---

### Task 1: Extract `StoreWebpImage` from `StoreProductImage`

**Files:**
- Create: `app/Actions/StoreWebpImage.php`
- Modify: `app/Actions/StoreProductImage.php` (whole file)
- Test: `tests/Feature/Admin/ProductImageTest.php` (existing, unchanged)

**Interfaces:**
- Produces: `App\Actions\StoreWebpImage::handle(UploadedFile $file, string $path, array $widths, string $errorKey): void` — writes `"{$path}-{$width}.webp"` for each width to the `public` disk, never upscaling; throws `ValidationException` keyed by `$errorKey` if the file cannot be read or encoded (and deletes any files already written).
- `StoreProductImage::handle(Product $product, UploadedFile $file): ProductImage` keeps its signature and behaviour (error key `images`). It is resolved from the container (`app(StoreProductImage::class)` in `CatalogSeeder`, method injection in `ProductImageController`), so constructor injection works.

- [ ] **Step 1: Create the branch**

```bash
git checkout -b feat/page-blocks
```

- [ ] **Step 2: Run the product image tests to confirm they pass before the refactor**

Run: `php artisan test --compact tests/Feature/Admin/ProductImageTest.php`
Expected: all PASS.

- [ ] **Step 3: Create `app/Actions/StoreWebpImage.php`**

```php
<?php

namespace App\Actions;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoreWebpImage
{
    /**
     * Resize the upload to each width (never upscaling) and save each as "{$path}-{$width}.webp" on the public disk.
     *
     * @param  list<int>  $widths
     *
     * @throws ValidationException keyed by $errorKey when the file cannot be read or processed.
     */
    public function handle(UploadedFile $file, string $path, array $widths, string $errorKey): void
    {
        // A 6000x6000 decode is ~144 MB, plus the rotated and scaled copies.
        ini_set('memory_limit', '512M');

        $source = @imagecreatefromstring($file->getContent());

        if ($source === false) {
            throw ValidationException::withMessages([$errorKey => "Could not read {$file->getClientOriginalName()}."]);
        }

        $source = $this->uprightJpeg($source, $file);

        if ($source === false) {
            throw $this->processingFailed($file, $errorKey);
        }

        $written = [];

        foreach ($widths as $width) {
            $resized = imagescale($source, min($width, imagesx($source)));
            ob_start();
            $encoded = $resized !== false && imagewebp($resized, null, 80);
            $webp = ob_get_clean();

            if (! $encoded) {
                Storage::disk('public')->delete($written);

                throw $this->processingFailed($file, $errorKey);
            }

            Storage::disk('public')->put($written[] = "{$path}-{$width}.webp", $webp);
        }
    }

    private function processingFailed(UploadedFile $file, string $errorKey): ValidationException
    {
        return ValidationException::withMessages([$errorKey => "Could not process {$file->getClientOriginalName()}."]);
    }

    /**
     * Phone photos store rotation in EXIF instead of the pixels; GD ignores it.
     */
    private function uprightJpeg(GdImage $image, UploadedFile $file): GdImage|false
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

- [ ] **Step 4: Replace `app/Actions/StoreProductImage.php`**

```php
<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StoreProductImage
{
    public function __construct(private StoreWebpImage $storeWebpImage) {}

    /**
     * Resize the upload to every ProductImage width, save each as WebP, and attach it to the product.
     */
    public function handle(Product $product, UploadedFile $file): ProductImage
    {
        $path = "products/{$product->id}/".Str::uuid();

        $this->storeWebpImage->handle($file, $path, ProductImage::WIDTHS, 'images');

        return $product->images()->create([
            'path' => $path,
            'alt' => $product->name,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
        ]);
    }
}
```

- [ ] **Step 5: Run the product image tests again**

Run: `php artisan test --compact tests/Feature/Admin/ProductImageTest.php tests/Feature/CatalogSeederTest.php`
Expected: all PASS (behaviour unchanged).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/StoreWebpImage.php app/Actions/StoreProductImage.php
git commit -m "refactor: extract webp resizing into StoreWebpImage"
```

---

### Task 2: `blocks` column, block enum, rendering and controllers

**Files:**
- Create: `app/Enums/PageBlockType.php`
- Create: migration via `php artisan make:migration add_blocks_to_pages_table --table=pages --no-interaction`
- Modify: `app/Models/Page.php` (whole file)
- Modify: `database/factories/PageFactory.php` (definition)
- Modify: `app/Http/Requests/Admin/PageRequest.php` (rules)
- Modify: `app/Http/Controllers/PageController.php` (show)
- Modify: `app/Http/Controllers/Admin/PageController.php` (create, edit)
- Test: `tests/Feature/ContentPageTest.php` (rewrite), `tests/Feature/Admin/PageManagementTest.php` (update), `tests/Feature/PageBlocksMigrationTest.php` (new)

**Interfaces:**
- Produces: `App\Enums\PageBlockType` (string-backed: `Hero='hero'`, `Text='text'`, `ImageText='image_text'`, `Features='features'`, `Table='table'`, `Faq='faq'`, `ContactCards='contact_cards'`, `CallToAction='call_to_action'`), constants `PageBlockType::ICONS` and `PageBlockType::CONTACT_KINDS` (`list<string>`). Task 3 adds `rules()`.
- Produces: `Page::IMAGE_WIDTHS = [800, 1600]`, `Page::imageUrl(string $path, int $width = 800): string`, `Page::renderedBlocks(): array` (shape below), `blocks` cast to `array`.
- Rendered block shapes (`data` per type) sent to `Content/Show` as prop `blocks`:
  - `hero`: `eyebrow?`, `heading`, `intro?`, `image: {src, srcset} | null`
  - `text`: `html`
  - `image_text`: `image: {src, srcset}`, `image_alt?`, `image_side`, `heading?`, `html`
  - `features`: `heading?`, `items: [{icon, title, text}]`
  - `table`: `heading?`, `columns: string[]`, `rows: (string|null)[][]`, `note?`
  - `faq`: `heading?`, `items: [{question, html}]`
  - `contact_cards`: `heading?`, `items: [{kind, label, value, href: string|null}]`
  - `call_to_action`: `heading`, `text?`, `button_label`, `button_url`
- Admin `Admin/Pages/Create` receives `imageBaseUrl` (string, ends with `/`); `Admin/Pages/Edit` receives `page: {id, slug, title, blocks}` (raw stored blocks) and `imageBaseUrl`. A preview URL is `` `${imageBaseUrl}${path}-800.webp` ``.

Note: after this task the admin Vue form still posts `body` and `Content/Show.vue` still expects `html`; Tasks 5 and 6 replace them. Only the PHP tests are expected to pass in between.

- [ ] **Step 1: Write the migration test `tests/Feature/PageBlocksMigrationTest.php`**

```php
<?php

use App\Models\Page;
use Illuminate\Support\Facades\DB;

test('existing page bodies become a single text block', function () {
    $migration = require collect(glob(database_path('migrations/*_add_blocks_to_pages_table.php')))->sole();

    $migration->down();
    DB::table('pages')->insert(['slug' => 'about', 'title' => 'About', 'body' => 'Made in **Aizawl**.', 'created_at' => now(), 'updated_at' => now()]);
    $migration->up();

    expect(Page::firstWhere('slug', 'about')->blocks)->toBe([['type' => 'text', 'data' => ['markdown' => 'Made in **Aizawl**.']]]);
});
```

- [ ] **Step 2: Rewrite `tests/Feature/ContentPageTest.php`**

```php
<?php

use App\Models\Page;
use Illuminate\Support\Facades\Storage;

test('text blocks render their markdown as html', function () {
    Page::factory()->create(['slug' => 'about', 'title' => 'About Arusa', 'blocks' => [
        ['type' => 'text', 'data' => ['markdown' => "# Hello\n\nMade in **Aizawl**."]],
    ]]);

    $this->get('/pages/about')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Content/Show')
            ->where('title', 'About Arusa')
            ->where('blocks.0.type', 'text')
            ->where('blocks.0.data.html', fn (string $html) => str_contains($html, '<strong>Aizawl</strong>'))
            ->missing('blocks.0.data.markdown'));
});

test('raw html and unsafe links are stripped from text and faq answers', function () {
    $unsafe = "<script>alert(1)</script>\n\n[click](javascript:alert(1))";

    Page::factory()->create(['slug' => 'x', 'blocks' => [
        ['type' => 'text', 'data' => ['markdown' => $unsafe]],
        ['type' => 'faq', 'data' => ['items' => [['question' => 'Safe?', 'answer' => $unsafe]]]],
    ]]);

    $isSafe = fn (string $html) => ! str_contains($html, '<script') && ! str_contains($html, 'javascript:');

    $this->get('/pages/x')
        ->assertInertia(fn ($page) => $page
            ->where('blocks.0.data.html', $isSafe)
            ->where('blocks.1.data.items.0.html', $isSafe)
            ->missing('blocks.1.data.items.0.answer'));
});

test('markdown tables render as html tables', function () {
    Page::factory()->create(['slug' => 'size-guide', 'blocks' => [
        ['type' => 'text', 'data' => ['markdown' => "| Size | Chest |\n|---|---|\n| S | 36 |"]],
    ]]);

    $this->get('/pages/size-guide')
        ->assertInertia(fn ($page) => $page->where('blocks.0.data.html', fn (string $html) => str_contains($html, '<table>')));
});

test('block images become src and srcset urls', function () {
    Storage::fake('public');

    Page::factory()->create(['slug' => 'about', 'blocks' => [
        ['type' => 'hero', 'data' => ['heading' => 'About']],
        ['type' => 'image_text', 'data' => ['image' => 'pages/shop', 'image_side' => 'left', 'markdown' => 'Hi']],
    ]]);

    $disk = Storage::disk('public');

    $this->get('/pages/about')
        ->assertInertia(fn ($page) => $page
            ->where('blocks.0.data.image', null)
            ->where('blocks.1.data.image.src', $disk->url('pages/shop-800.webp'))
            ->where('blocks.1.data.image.srcset', $disk->url('pages/shop-800.webp').' 800w, '.$disk->url('pages/shop-1600.webp').' 1600w')
            ->where('blocks.1.data.html', "<p>Hi</p>\n"));
});

test('contact cards get links for their kind', function () {
    Page::factory()->create(['slug' => 'contact', 'blocks' => [
        ['type' => 'contact_cards', 'data' => ['items' => [
            ['kind' => 'phone', 'label' => 'Call', 'value' => '9876543210'],
            ['kind' => 'whatsapp', 'label' => 'WhatsApp', 'value' => '9876543210'],
            ['kind' => 'email', 'label' => 'Email', 'value' => 'hello@arusa.test'],
            ['kind' => 'address', 'label' => 'Studio', 'value' => 'Aizawl'],
        ]]],
    ]]);

    $this->get('/pages/contact')
        ->assertInertia(fn ($page) => $page
            ->where('blocks.0.data.items.0.href', 'tel:+919876543210')
            ->where('blocks.0.data.items.1.href', 'https://wa.me/919876543210')
            ->where('blocks.0.data.items.2.href', 'mailto:hello@arusa.test')
            ->where('blocks.0.data.items.3.href', null));
});

test('unknown content pages return 404', function () {
    $this->get('/pages/missing')->assertNotFound();
});
```

- [ ] **Step 3: Update `tests/Feature/Admin/PageManagementTest.php`**

Replace every `'body' => '…'` in request payloads with a `blocks` key holding one text block, and add an edit-screen test. The full file:

```php
<?php

use App\Models\Page;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->blocks = [['type' => 'text', 'data' => ['markdown' => 'Questions']]];
});

test('admin sees the page list', function () {
    Page::factory()->create(['title' => 'Size Guide']);

    $this->actingAs($this->admin)
        ->get('/admin/pages')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Pages/Index')->has('pages', 1));
});

test('admin can create a page', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'FAQ', 'slug' => 'faq', 'blocks' => $this->blocks])
        ->assertRedirect('/admin/pages');

    expect(Page::firstWhere('slug', 'faq')->blocks)->toBe($this->blocks);
});

test('admin edits a page with its stored blocks', function () {
    $page = Page::factory()->create(['blocks' => $this->blocks]);

    $this->actingAs($this->admin)
        ->get("/admin/pages/{$page->id}/edit")
        ->assertInertia(fn ($inertia) => $inertia
            ->component('Admin/Pages/Edit')
            ->where('page.blocks', $this->blocks)
            ->has('imageBaseUrl'));
});

test('admin can update a page keeping its own slug', function () {
    $page = Page::factory()->create(['slug' => 'about']);

    $this->actingAs($this->admin)
        ->put("/admin/pages/{$page->id}", ['title' => 'About us', 'slug' => 'about', 'blocks' => $this->blocks])
        ->assertRedirect('/admin/pages');

    expect($page->fresh()->title)->toBe('About us');
});

test('slugs must be unique and url safe', function () {
    Page::factory()->create(['slug' => 'about']);

    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Dup', 'slug' => 'about', 'blocks' => $this->blocks])
        ->assertSessionHasErrors('slug');

    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Bad', 'slug' => 'has spaces/slash', 'blocks' => $this->blocks])
        ->assertSessionHasErrors('slug');
});

test('a page needs at least one block', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Empty', 'slug' => 'empty', 'blocks' => []])
        ->assertSessionHasErrors('blocks');
});

test('admin can delete a page', function () {
    $page = Page::factory()->create();

    $this->actingAs($this->admin)->delete("/admin/pages/{$page->id}")->assertRedirect('/admin/pages');

    expect(Page::count())->toBe(0);
});

test('non-admins cannot manage pages', function () {
    $this->actingAs(User::factory()->create())
        ->post('/admin/pages', ['title' => 'X', 'slug' => 'x', 'blocks' => $this->blocks])
        ->assertForbidden();
});
```

- [ ] **Step 4: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/ContentPageTest.php tests/Feature/Admin/PageManagementTest.php tests/Feature/PageBlocksMigrationTest.php`
Expected: FAIL (no `blocks` column; migration file not found).

- [ ] **Step 5: Create the enum `app/Enums/PageBlockType.php`**

```php
<?php

namespace App\Enums;

enum PageBlockType: string
{
    case Hero = 'hero';
    case Text = 'text';
    case ImageText = 'image_text';
    case Features = 'features';
    case Table = 'table';
    case Faq = 'faq';
    case ContactCards = 'contact_cards';
    case CallToAction = 'call_to_action';

    /**
     * Icon names a feature item may use; each maps to a component in resources/js/Components/Icons/.
     *
     * @var list<string>
     */
    public const ICONS = ['cotton', 'feather', 'puff-print', 'shield', 'chat', 'bag'];

    /**
     * @var list<string>
     */
    public const CONTACT_KINDS = ['phone', 'whatsapp', 'email', 'address', 'hours'];
}
```

- [ ] **Step 6: Create the migration**

Run: `php artisan make:migration add_blocks_to_pages_table --table=pages --no-interaction`

Replace the generated file's body with:

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
        Schema::table('pages', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('title');
        });

        DB::table('pages')->orderBy('id')->each(function (object $page): void {
            DB::table('pages')->where('id', $page->id)->update([
                'blocks' => json_encode([['type' => 'text', 'data' => ['markdown' => $page->body]]]),
            ]);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->json('blocks')->nullable(false)->change();
            $table->dropColumn('body');
        });
    }

    /**
     * Reverse the migrations. Only text blocks survive; other block types are lost.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->text('body')->nullable()->after('title');
        });

        DB::table('pages')->orderBy('id')->each(function (object $page): void {
            DB::table('pages')->where('id', $page->id)->update([
                'body' => collect(json_decode($page->blocks, true))
                    ->where('type', 'text')
                    ->pluck('data.markdown')
                    ->implode("\n\n"),
            ]);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->text('body')->nullable(false)->change();
            $table->dropColumn('blocks');
        });
    }
};
```

- [ ] **Step 7: Replace `app/Models/Page.php`**

```php
<?php

namespace App\Models;

use App\Enums\PageBlockType;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Table\TableExtension;

#[Fillable(['slug', 'title', 'blocks'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /**
     * Widths each page image is resized to. Files are stored as "{path}-{width}.webp".
     *
     * @var list<int>
     */
    public const IMAGE_WIDTHS = [800, 1600];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }

    public static function imageUrl(string $path, int $width = 800): string
    {
        return Storage::disk('public')->url("{$path}-{$width}.webp");
    }

    /**
     * Blocks ready for the storefront: Markdown rendered to safe HTML, image paths turned into URLs and contact links built.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function renderedBlocks(): array
    {
        return array_map(fn (array $block): array => [
            'type' => $block['type'],
            'data' => $this->renderedData(PageBlockType::from($block['type']), $block['data']),
        ], $this->blocks);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function renderedData(PageBlockType $type, array $data): array
    {
        return match ($type) {
            PageBlockType::Hero => [...$data, 'image' => isset($data['image']) ? self::image($data['image']) : null],
            PageBlockType::Text => ['html' => self::markdown($data['markdown'])],
            PageBlockType::ImageText => [
                ...Arr::except($data, 'markdown'),
                'image' => self::image($data['image']),
                'html' => self::markdown($data['markdown']),
            ],
            PageBlockType::Faq => [...$data, 'items' => array_map(fn (array $item): array => [
                'question' => $item['question'],
                'html' => self::markdown($item['answer']),
            ], $data['items'])],
            PageBlockType::ContactCards => [...$data, 'items' => array_map(fn (array $item): array => [
                ...$item,
                'href' => self::contactHref($item),
            ], $data['items'])],
            default => $data,
        };
    }

    /**
     * Markdown rendered to HTML with raw HTML and unsafe links removed.
     */
    private static function markdown(string $markdown): string
    {
        return (string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ], [new TableExtension]);
    }

    /**
     * @return array{src: string, srcset: string}
     */
    private static function image(string $path): array
    {
        return [
            'src' => self::imageUrl($path),
            'srcset' => collect(self::IMAGE_WIDTHS)
                ->map(fn (int $width): string => self::imageUrl($path, $width)." {$width}w")
                ->implode(', '),
        ];
    }

    /**
     * @param  array{kind: string, value: string}  $item
     */
    private static function contactHref(array $item): ?string
    {
        return match ($item['kind']) {
            'phone' => "tel:+91{$item['value']}",
            'whatsapp' => "https://wa.me/91{$item['value']}",
            'email' => "mailto:{$item['value']}",
            default => null,
        };
    }
}
```

- [ ] **Step 8: Update `database/factories/PageFactory.php` definition**

```php
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'blocks' => [['type' => 'text', 'data' => ['markdown' => fake()->paragraphs(2, true)]]],
        ];
    }
```

- [ ] **Step 9: Update `app/Http/Requests/Admin/PageRequest.php` rules (base rules; Task 3 adds per-type rules)**

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\PageBlockType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('pages', 'slug')->ignore($this->route('page'))],
            'blocks' => ['required', 'list', 'max:30'],
            'blocks.*.type' => ['required', Rule::enum(PageBlockType::class)],
            'blocks.*.data' => ['required', 'array'],
        ];
    }
}
```

- [ ] **Step 10: Update `app/Http/Controllers/PageController.php` show**

```php
    public function show(Page $page): Response
    {
        return Inertia::render('Content/Show', [
            'title' => $page->title,
            'blocks' => $page->renderedBlocks(),
        ]);
    }
```

- [ ] **Step 11: Update `app/Http/Controllers/Admin/PageController.php` create and edit**

Add `use Illuminate\Support\Facades\Storage;` and replace the two methods:

```php
    public function create(): Response
    {
        return Inertia::render('Admin/Pages/Create', [
            'imageBaseUrl' => Storage::disk('public')->url(''),
        ]);
    }
```

```php
    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Edit', [
            'page' => $page->only('id', 'slug', 'title', 'blocks'),
            'imageBaseUrl' => Storage::disk('public')->url(''),
        ]);
    }
```

- [ ] **Step 12: Run the migration locally and the tests**

Run: `php artisan migrate --no-interaction`
Expected: `add_blocks_to_pages_table` DONE.

Run: `php artisan test --compact tests/Feature/ContentPageTest.php tests/Feature/Admin/PageManagementTest.php tests/Feature/PageBlocksMigrationTest.php`
Expected: all PASS.

- [ ] **Step 13: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/PageBlockType.php database/migrations/*_add_blocks_to_pages_table.php app/Models/Page.php database/factories/PageFactory.php app/Http/Requests/Admin/PageRequest.php app/Http/Controllers/PageController.php app/Http/Controllers/Admin/PageController.php tests/Feature/ContentPageTest.php tests/Feature/Admin/PageManagementTest.php tests/Feature/PageBlocksMigrationTest.php
git commit -m "feat: store content pages as typed blocks"
```

---

### Task 3: Per-type block validation

**Files:**
- Modify: `app/Enums/PageBlockType.php` (add `rules()` and helpers)
- Modify: `app/Http/Requests/Admin/PageRequest.php` (merge rules, `attributes()`)
- Test: `tests/Feature/Admin/PageBlocksTest.php` (new)

**Interfaces:**
- Consumes: `PageBlockType`, `PageBlockType::ICONS`, `PageBlockType::CONTACT_KINDS` (Task 2).
- Produces: `PageBlockType::rules(string $prefix, array $input): array<string, array<mixed>>` — rules keyed `"{$prefix}.data.<field>"`. Error keys the admin UI reads: `blocks.{i}.data.{field}`, `blocks.{i}.data.items.{j}.{field}`, `blocks.{i}.data.columns`, `blocks.{i}.data.rows.{r}`, `blocks.{i}.data.rows.{r}.{c}`. `validated('blocks')` keeps only known fields (unknown keys such as a client `uid` are dropped).

- [ ] **Step 1: Write the failing tests `tests/Feature/Admin/PageBlocksTest.php`**

```php
<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->image = 'pages/9b2f3c4e-1a2b-4c3d-8e9f-0a1b2c3d4e5f';
    Storage::disk('public')->put("{$this->image}-800.webp", 'webp');
    $this->admin = User::factory()->admin()->create();
});

/**
 * One valid block of each type, keyed by type.
 *
 * @return array<string, array{type: string, data: array<string, mixed>}>
 */
function validBlocks(string $image): array
{
    return [
        'hero' => ['type' => 'hero', 'data' => ['eyebrow' => 'Our story', 'heading' => 'About Arusa', 'intro' => 'Made in Aizawl.', 'image' => $image]],
        'text' => ['type' => 'text', 'data' => ['markdown' => 'Hello **there**.']],
        'image_text' => ['type' => 'image_text', 'data' => ['image' => $image, 'image_alt' => 'Shop front', 'image_side' => 'right', 'heading' => 'Where we started', 'markdown' => 'A small shop.']],
        'features' => ['type' => 'features', 'data' => ['heading' => 'Why us', 'items' => [
            ['icon' => 'cotton', 'title' => 'Cotton', 'text' => 'Soft.'],
            ['icon' => 'shield', 'title' => 'Durable', 'text' => 'Lasts.'],
        ]]],
        'table' => ['type' => 'table', 'data' => ['heading' => 'Sizes', 'columns' => ['Size', 'Chest'], 'rows' => [['S', '36'], ['M', '38']], 'note' => 'In inches.']],
        'faq' => ['type' => 'faq', 'data' => ['heading' => 'Questions', 'items' => [['question' => 'Do you ship?', 'answer' => 'Across **Mizoram**.']]]],
        'contact_cards' => ['type' => 'contact_cards', 'data' => ['heading' => 'Reach us', 'items' => [
            ['kind' => 'phone', 'label' => 'Call', 'value' => '9876543210'],
            ['kind' => 'email', 'label' => 'Email', 'value' => 'hello@arusa.test'],
            ['kind' => 'hours', 'label' => 'Hours', 'value' => 'Mon–Sat, 10am–6pm'],
        ]]],
        'call_to_action' => ['type' => 'call_to_action', 'data' => ['heading' => 'Shop the drop', 'text' => 'New tees are in.', 'button_label' => 'Shop now', 'button_url' => '/']],
    ];
}

/**
 * @param  list<array<string, mixed>>  $blocks
 * @return array<string, mixed>
 */
function pagePayload(array $blocks): array
{
    return ['title' => 'About', 'slug' => 'about', 'blocks' => $blocks];
}

test('a page with every block type saves and keeps only known fields', function () {
    $blocks = array_values(validBlocks($this->image));
    $sent = $blocks;
    $sent[0]['uid'] = 7;
    $sent[1]['data']['colour'] = 'red';

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload($sent))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/pages');

    expect(Page::firstWhere('slug', 'about')->blocks)->toEqual($blocks);
});

test('unknown block types are rejected', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([['type' => 'carousel', 'data' => ['slides' => 3]]]))
        ->assertSessionHasErrors('blocks.0.type');
});

test('each block type requires its main field', function (string $type, string $field) {
    $block = validBlocks($this->image)[$type];
    unset($block['data'][$field]);

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors("blocks.0.data.{$field}");
})->with([
    'hero heading' => ['hero', 'heading'],
    'text markdown' => ['text', 'markdown'],
    'image + text image' => ['image_text', 'image'],
    'features items' => ['features', 'items'],
    'table rows' => ['table', 'rows'],
    'faq items' => ['faq', 'items'],
    'contact items' => ['contact_cards', 'items'],
    'call to action link' => ['call_to_action', 'button_url'],
]);

test('call to action links must be a site path or https', function (string $url) {
    $block = validBlocks($this->image)['call_to_action'];
    $block['data']['button_url'] = $url;

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.button_url');
})->with(['javascript:alert(1)', 'http://example.com', '//evil.example', 'catalog']);

test('site paths and https links are accepted', function (string $url) {
    $block = validBlocks($this->image)['call_to_action'];
    $block['data']['button_url'] = $url;

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasNoErrors();
})->with(['/pages/size-guide', 'https://wa.me/919876543210']);

test('table rows must have one cell per column', function () {
    $block = validBlocks($this->image)['table'];
    $block['data']['rows'] = [['S', '36', 'extra']];

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.rows.0');
});

test('features allow at most four items', function () {
    $block = validBlocks($this->image)['features'];
    $block['data']['items'] = array_fill(0, 5, ['icon' => 'bag', 'title' => 'Bag', 'text' => 'Roomy.']);

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.items');
});

test('images must be uploaded page images', function (string $path) {
    $block = validBlocks($this->image)['image_text'];
    $block['data']['image'] = $path;

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.image');
})->with([
    'outside pages/' => ['products/1/9b2f3c4e-1a2b-4c3d-8e9f-0a1b2c3d4e5f'],
    'path traversal' => ['pages/../products/1/9b2f3c4e-1a2b-4c3d-8e9f-0a1b2c3d4e5f'],
    'never uploaded' => ['pages/00000000-0000-4000-8000-000000000000'],
]);

test('contact values must match their kind', function (string $kind, string $value) {
    $block = validBlocks($this->image)['contact_cards'];
    $block['data']['items'] = [['kind' => $kind, 'label' => 'Reach us', 'value' => $value]];

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.items.0.value');
})->with([
    'short whatsapp number' => ['whatsapp', '12345'],
    'landline-style phone' => ['phone', '0389123456'],
    'bad email' => ['email', 'not-an-email'],
]);

test('errors name the block they belong to', function () {
    $block = validBlocks($this->image)['hero'];
    unset($block['data']['heading']);

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([validBlocks($this->image)['text'], $block]))
        ->assertSessionHasErrors(['blocks.1.data.heading' => 'The block 2 heading field is required.']);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/PageBlocksTest.php`
Expected: FAIL — unknown fields are kept, required fields and links are not checked.

- [ ] **Step 3: Add `rules()` and helpers to `app/Enums/PageBlockType.php`**

Add these imports at the top:

```php
use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
```

Add these methods after the constants:

```php
    /**
     * Validation rules for this block's data, keyed under "{$prefix}.data".
     *
     * @param  array<string, mixed>  $input  The submitted data, for rules that depend on other fields.
     * @return array<string, array<mixed>>
     */
    public function rules(string $prefix, array $input): array
    {
        $data = "{$prefix}.data";
        $heading = ['nullable', 'string', 'max:120'];

        return match ($this) {
            self::Hero => [
                "{$data}.eyebrow" => ['nullable', 'string', 'max:60'],
                "{$data}.heading" => ['required', 'string', 'max:120'],
                "{$data}.intro" => ['nullable', 'string', 'max:300'],
                "{$data}.image" => ['nullable', ...self::imageRules()],
            ],
            self::Text => [
                "{$data}.markdown" => ['required', 'string', 'max:20000'],
            ],
            self::ImageText => [
                "{$data}.image" => ['required', ...self::imageRules()],
                "{$data}.image_alt" => ['nullable', 'string', 'max:150'],
                "{$data}.image_side" => ['required', Rule::in(['left', 'right'])],
                "{$data}.heading" => $heading,
                "{$data}.markdown" => ['required', 'string', 'max:5000'],
            ],
            self::Features => [
                "{$data}.heading" => $heading,
                "{$data}.items" => ['required', 'array', 'min:2', 'max:4'],
                "{$data}.items.*.icon" => ['required', Rule::in(self::ICONS)],
                "{$data}.items.*.title" => ['required', 'string', 'max:60'],
                "{$data}.items.*.text" => ['required', 'string', 'max:200'],
            ],
            self::Table => [
                "{$data}.heading" => $heading,
                "{$data}.columns" => ['required', 'array', 'min:2', 'max:6'],
                "{$data}.columns.*" => ['required', 'string', 'max:60'],
                "{$data}.rows" => ['required', 'array', 'min:1', 'max:30'],
                "{$data}.rows.*" => ['required', 'array', 'size:'.count((array) ($input['columns'] ?? []))],
                "{$data}.rows.*.*" => ['nullable', 'string', 'max:60'],
                "{$data}.note" => ['nullable', 'string', 'max:300'],
            ],
            self::Faq => [
                "{$data}.heading" => $heading,
                "{$data}.items" => ['required', 'array', 'min:1', 'max:20'],
                "{$data}.items.*.question" => ['required', 'string', 'max:200'],
                "{$data}.items.*.answer" => ['required', 'string', 'max:2000'],
            ],
            self::ContactCards => [
                "{$data}.heading" => $heading,
                "{$data}.items" => ['required', 'array', 'min:1', 'max:6'],
                "{$data}.items.*.kind" => ['required', Rule::in(self::CONTACT_KINDS)],
                "{$data}.items.*.label" => ['required', 'string', 'max:60'],
                "{$data}.items.*.value" => ['required', 'string', 'max:200'],
                ...self::contactValueRules($data, $input['items'] ?? []),
            ],
            self::CallToAction => [
                "{$data}.heading" => ['required', 'string', 'max:120'],
                "{$data}.text" => ['nullable', 'string', 'max:300'],
                "{$data}.button_label" => ['required', 'string', 'max:40'],
                // A site path ("/x", not "//host") or an https URL.
                "{$data}.button_url" => ['required', 'string', 'max:300', 'regex:#^(/(?!/)|https://)#'],
            ],
        };
    }

    /**
     * A path returned by the page image upload whose 800 px file exists.
     *
     * @return list<mixed>
     */
    private static function imageRules(): array
    {
        return [
            'bail',
            'string',
            'regex:#^pages/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$#',
            function (string $attribute, string $value, Closure $fail): void {
                if (! Storage::disk('public')->exists("{$value}-800.webp")) {
                    $fail('Upload the :attribute again.');
                }
            },
        ];
    }

    /**
     * Phone and WhatsApp values are 10-digit Indian mobiles; email values are emails.
     *
     * @return array<string, list<string>>
     */
    private static function contactValueRules(string $data, mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->mapWithKeys(fn (mixed $item, int|string $index): array => [
                "{$data}.items.{$index}.value" => match (is_array($item) ? ($item['kind'] ?? null) : null) {
                    'phone', 'whatsapp' => ['regex:/^[6-9]\d{9}$/'],
                    'email' => ['email'],
                    default => [],
                },
            ])
            ->all();
    }
```

- [ ] **Step 4: Merge the rules and add readable attribute names in `app/Http/Requests/Admin/PageRequest.php`**

Replace `rules()` and add `attributes()`:

```php
    /**
     * Base rules plus each submitted block's own rules, picked by its type.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('pages', 'slug')->ignore($this->route('page'))],
            'blocks' => ['required', 'list', 'max:30'],
            'blocks.*.type' => ['required', Rule::enum(PageBlockType::class)],
            'blocks.*.data' => ['required', 'array'],
        ];

        foreach ((array) $this->input('blocks') as $index => $block) {
            $type = is_array($block) && is_string($block['type'] ?? null) ? PageBlockType::tryFrom($block['type']) : null;

            if ($type !== null) {
                $rules += $type->rules("blocks.{$index}", (array) ($block['data'] ?? []));
            }
        }

        return $rules;
    }

    /**
     * Name block fields by position, e.g. "blocks.1.data.items.*.title" becomes "block 2 items title".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->rules())
            ->keys()
            ->filter(fn (string $key): bool => str_starts_with($key, 'blocks.') && str_contains($key, '.data.'))
            ->mapWithKeys(fn (string $key): array => [$key => preg_replace_callback(
                '/^blocks\.(\d+)\.data\.(.+)$/',
                fn (array $match): string => 'block '.($match[1] + 1).' '.str_replace(['.*', '.', '_'], ['', ' ', ' '], $match[2]),
                $key,
            )])
            ->all();
    }
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact tests/Feature/Admin/PageBlocksTest.php tests/Feature/Admin/PageManagementTest.php`
Expected: all PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/PageBlockType.php app/Http/Requests/Admin/PageRequest.php tests/Feature/Admin/PageBlocksTest.php
git commit -m "feat: validate each page block by its type"
```

---

### Task 4: Page image upload endpoint

**Files:**
- Create: `app/Http/Controllers/Admin/PageImageController.php` (via `php artisan make:controller Admin/PageImageController --no-interaction`)
- Create: `app/Http/Requests/Admin/StorePageImageRequest.php` (via `php artisan make:request Admin/StorePageImageRequest --no-interaction`)
- Modify: `routes/admin.php` (one route + import)
- Test: `tests/Feature/Admin/PageImageTest.php` (new)

**Interfaces:**
- Consumes: `StoreWebpImage::handle()` (Task 1), `Page::IMAGE_WIDTHS`, `Page::imageUrl()` (Task 2).
- Produces: `POST /admin/page-images`, route name `admin.page-images.store`, multipart field `image`. Success: `200 {"path": "pages/<uuid>", "url": "<800px url>"}`. Validation failure (with `Accept: application/json`): `422 {"message", "errors": {"image": [..]}}`.

- [ ] **Step 1: Write the failing tests `tests/Feature/Admin/PageImageTest.php`**

```php
<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('admin uploads a page image as webp at each width', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->postJson('/admin/page-images', ['image' => UploadedFile::fake()->image('shop.jpg', 2000, 1000)])
        ->assertOk();

    $path = $response->json('path');

    expect($path)->toMatch('#^pages/[0-9a-f-]{36}$#')
        ->and($response->json('url'))->toBe(Storage::disk('public')->url("{$path}-800.webp"))
        ->and(getimagesizefromstring(Storage::disk('public')->get("{$path}-1600.webp"))[0])->toBe(1600)
        ->and(getimagesizefromstring(Storage::disk('public')->get("{$path}-800.webp"))['mime'])->toBe('image/webp');
});

test('non-image uploads are rejected', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/admin/page-images', ['image' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('non-admins cannot upload page images', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/admin/page-images', ['image' => UploadedFile::fake()->image('shop.jpg')])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/PageImageTest.php`
Expected: FAIL with 404 (no route).

- [ ] **Step 3: Generate and fill the request**

Run: `php artisan make:request Admin/StorePageImageRequest --no-interaction`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePageImageRequest extends FormRequest
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
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }
}
```

- [ ] **Step 4: Generate and fill the controller**

Run: `php artisan make:controller Admin/PageImageController --no-interaction`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StoreWebpImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageImageRequest;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PageImageController extends Controller
{
    /**
     * Store an image for a page block and return the path the block saves.
     */
    public function store(StorePageImageRequest $request, StoreWebpImage $storeWebpImage): JsonResponse
    {
        // shortcut: images of removed blocks stay on disk; add a cleanup command if storage grows.
        $path = 'pages/'.Str::uuid();

        $storeWebpImage->handle($request->file('image'), $path, Page::IMAGE_WIDTHS, 'image');

        return response()->json(['path' => $path, 'url' => Page::imageUrl($path)]);
    }
}
```

- [ ] **Step 5: Add the route in `routes/admin.php`**

Add `use App\Http\Controllers\Admin\PageImageController;` to the imports (alphabetical, after `PageController`), and directly after the `Route::resource('pages', …)` line:

```php
        Route::post('page-images', [PageImageController::class, 'store'])->name('page-images.store');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact tests/Feature/Admin/PageImageTest.php`
Expected: all PASS.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/PageImageController.php app/Http/Requests/Admin/StorePageImageRequest.php routes/admin.php tests/Feature/Admin/PageImageTest.php
git commit -m "feat: page image upload endpoint"
```

---

### Task 5: Admin block editor

**Files:**
- Create: `resources/js/Pages/Admin/Pages/Blocks/fieldOptions.js`
- Create: `resources/js/Pages/Admin/Pages/Blocks/blockTypes.js`
- Create: `resources/js/Pages/Admin/Pages/Blocks/Field.vue`
- Create: `resources/js/Pages/Admin/Pages/Blocks/ImageField.vue`
- Create: `resources/js/Pages/Admin/Pages/Blocks/HeroFields.vue`, `TextFields.vue`, `ImageTextFields.vue`, `FeaturesFields.vue`, `TableFields.vue`, `FaqFields.vue`, `ContactCardsFields.vue`, `CallToActionFields.vue`
- Modify: `resources/js/Pages/Admin/Pages/Partials/PageForm.vue` (whole file)
- Modify: `resources/js/Pages/Admin/Pages/Create.vue`, `resources/js/Pages/Admin/Pages/Edit.vue` (whole files)

**Interfaces:**
- Consumes: props `imageBaseUrl` (Create, Edit) and `page.blocks` (Edit) from Task 2; route `admin.page-images.store` returning `{path, url}` (Task 4); error keys from Task 3 (`blocks.{i}.data.…`).
- Produces: each `*Fields.vue` takes props `data: Object` (the block's `data`, edited in place), `errors: Object` (`form.errors`), `prefix: String` (`"blocks.{i}.data"`). `blockTypes.js` exports `blockTypes` (type → `{label, fields, data()}`), `newBlock(type)`, `withUid(block)`, `withoutUids(formData)`. `PageForm.vue` takes `form` and `imageBaseUrl`, emits `submit`, and `provide`s `imageBaseUrl` for `ImageField`.

No Pest tests (UI only; the server contract is covered by Tasks 2–4). Verified by `npm run build` here and in the browser in Task 8.

- [ ] **Step 1: Create `Blocks/fieldOptions.js`**

```js
export const fieldClass = 'block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export const smallButton = 'rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-40';

export const removeButton = 'text-sm text-red-600 hover:underline disabled:opacity-40 disabled:no-underline';

// Must match PageBlockType::ICONS.
export const featureIcons = [
    { value: 'cotton', label: 'Cotton' },
    { value: 'feather', label: 'Feather' },
    { value: 'puff-print', label: 'Puff print' },
    { value: 'shield', label: 'Shield' },
    { value: 'chat', label: 'Chat' },
    { value: 'bag', label: 'Bag' },
];

// Must match PageBlockType::CONTACT_KINDS.
export const contactKinds = [
    { value: 'phone', label: 'Phone', hint: '10-digit mobile number' },
    { value: 'whatsapp', label: 'WhatsApp', hint: '10-digit mobile number' },
    { value: 'email', label: 'Email', hint: 'Email address' },
    { value: 'address', label: 'Address', hint: 'Address' },
    { value: 'hours', label: 'Opening hours', hint: 'e.g. Monday to Saturday, 10am – 6pm' },
];
```

- [ ] **Step 2: Create `Blocks/Field.vue`**

```vue
<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
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
    <div>
        <InputLabel :for="id" :value="label" />
        <select v-if="options" :id="id" v-model="value" :class="fieldClass" class="mt-1">
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <textarea v-else-if="rows" :id="id" v-model="value" :rows="rows" :class="fieldClass" class="mt-1" />
        <input v-else :id="id" v-model="value" :class="fieldClass" class="mt-1" />
        <InputError class="mt-2" :message="error" />
    </div>
</template>
```

- [ ] **Step 3: Create `Blocks/ImageField.vue`**

```vue
<script setup>
import { inject, ref } from 'vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    clearable: { type: Boolean, default: false },
});

const path = defineModel({ type: String, default: null });
const imageBaseUrl = inject('imageBaseUrl');
const uploading = ref(false);
const uploadError = ref(null);

// Laravel sets this cookie on every response and accepts it back as a CSRF header.
const xsrfToken = () => decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');

const onFile = async (event) => {
    const body = new FormData();
    body.append('image', event.target.files[0]);
    uploading.value = true;
    uploadError.value = null;

    try {
        const response = await fetch(route('admin.page-images.store'), {
            method: 'POST',
            body,
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        });
        const json = await response.json();

        if (response.ok) {
            path.value = json.path;
        } else {
            uploadError.value = json.errors?.image?.[0] ?? json.message ?? 'Upload failed.';
        }
    } catch {
        uploadError.value = 'Upload failed. Check the file is under 8 MB and try again.';
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
};
</script>

<template>
    <div>
        <p class="text-sm font-medium text-gray-700">{{ label }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <img v-if="path" :src="`${imageBaseUrl}${path}-800.webp`" alt="" class="h-20 w-20 rounded object-cover" />
            <label class="inline-flex cursor-pointer items-center rounded border border-gray-300 px-3 py-2 text-sm">
                {{ uploading ? 'Uploading…' : path ? 'Replace image' : 'Upload image' }}
                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="uploading" @change="onFile" />
            </label>
            <button v-if="path && clearable" type="button" class="text-sm text-red-600 hover:underline" @click="path = null">Remove image</button>
        </div>
        <InputError class="mt-2" :message="uploadError ?? error" />
    </div>
</template>
```

- [ ] **Step 4: Create `Blocks/HeroFields.vue`**

```vue
<script setup>
import Field from './Field.vue';
import ImageField from './ImageField.vue';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.eyebrow`" v-model="data.eyebrow" label="Small line above the heading (optional)" :error="error('eyebrow')" />
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading" :error="error('heading')" />
        <Field :id="`${prefix}.intro`" v-model="data.intro" label="Intro (optional)" :rows="2" :error="error('intro')" />
        <ImageField v-model="data.image" label="Background photo (optional; the store photo is used when empty)" clearable :error="error('image')" />
    </div>
</template>
```

- [ ] **Step 5: Create `Blocks/TextFields.vue`**

```vue
<script setup>
import Field from './Field.vue';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];
</script>

<template>
    <div>
        <Field
            :id="`${prefix}.markdown`"
            v-model="data.markdown"
            label="Text (Markdown: ## heading, **bold**, - list, [link](https://…), tables)"
            :rows="12"
            class="font-mono"
            :error="error('markdown')"
        />
    </div>
</template>
```

- [ ] **Step 6: Create `Blocks/ImageTextFields.vue`**

```vue
<script setup>
import Field from './Field.vue';
import ImageField from './ImageField.vue';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];

const sides = [
    { value: 'left', label: 'Photo on the left' },
    { value: 'right', label: 'Photo on the right' },
];
</script>

<template>
    <div class="space-y-4">
        <ImageField v-model="data.image" label="Photo" :error="error('image')" />
        <Field :id="`${prefix}.image_alt`" v-model="data.image_alt" label="Photo description for screen readers (optional)" :error="error('image_alt')" />
        <Field :id="`${prefix}.image_side`" v-model="data.image_side" label="Layout" :options="sides" :error="error('image_side')" />
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading (optional)" :error="error('heading')" />
        <Field :id="`${prefix}.markdown`" v-model="data.markdown" label="Text (Markdown)" :rows="8" class="font-mono" :error="error('markdown')" />
    </div>
</template>
```

- [ ] **Step 7: Create `Blocks/FeaturesFields.vue`**

```vue
<script setup>
import InputError from '@/Components/InputError.vue';
import Field from './Field.vue';
import { featureIcons, removeButton, smallButton } from './fieldOptions.js';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading (optional)" :error="error('heading')" />
        <fieldset class="space-y-3">
            <legend class="text-sm font-medium text-gray-700">Items (2 to 4)</legend>
            <div v-for="(item, index) in data.items" :key="index" class="grid gap-3 rounded border border-gray-200 p-3 sm:grid-cols-[10rem_1fr]">
                <Field :id="`${prefix}.items.${index}.icon`" v-model="item.icon" label="Icon" :options="featureIcons" :error="error(`items.${index}.icon`)" />
                <Field :id="`${prefix}.items.${index}.title`" v-model="item.title" label="Title" :error="error(`items.${index}.title`)" />
                <Field :id="`${prefix}.items.${index}.text`" v-model="item.text" label="Text" :rows="2" class="sm:col-span-2" :error="error(`items.${index}.text`)" />
                <button type="button" :class="removeButton" class="justify-self-start" :disabled="data.items.length <= 2" @click="data.items.splice(index, 1)">Remove item</button>
            </div>
            <button type="button" :class="smallButton" :disabled="data.items.length >= 4" @click="data.items.push({ icon: 'cotton', title: '', text: '' })">Add item</button>
            <InputError :message="error('items')" />
        </fieldset>
    </div>
</template>
```

- [ ] **Step 8: Create `Blocks/TableFields.vue`**

```vue
<script setup>
import { computed } from 'vue';
import Field from './Field.vue';
import { fieldClass, removeButton, smallButton } from './fieldOptions.js';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];

// Cell errors have many keys; list them together under the table.
const tableErrors = computed(() => Object.entries(props.errors)
    .filter(([key]) => key.startsWith(`${props.prefix}.columns`) || key.startsWith(`${props.prefix}.rows`))
    .map(([, message]) => message));

const addColumn = () => {
    props.data.columns.push('');
    props.data.rows.forEach((row) => row.push(''));
};

const removeColumn = (index) => {
    props.data.columns.splice(index, 1);
    props.data.rows.forEach((row) => row.splice(index, 1));
};

const addRow = () => props.data.rows.push(props.data.columns.map(() => ''));
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading (optional)" :error="error('heading')" />
        <div class="overflow-x-auto">
            <table class="text-sm">
                <thead>
                    <tr>
                        <th v-for="(column, index) in data.columns" :key="index" class="min-w-32 p-1 text-left align-top font-normal">
                            <input v-model="data.columns[index]" :class="fieldClass" class="font-semibold" placeholder="Column heading" :aria-label="`Column ${index + 1} heading`" />
                            <button type="button" :class="removeButton" class="mt-1 text-xs" :disabled="data.columns.length <= 2" @click="removeColumn(index)">Remove column</button>
                        </th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, rowIndex) in data.rows" :key="rowIndex">
                        <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="p-1">
                            <input v-model="row[cellIndex]" :class="fieldClass" :aria-label="`Row ${rowIndex + 1}, ${data.columns[cellIndex] || `column ${cellIndex + 1}`}`" />
                        </td>
                        <td class="p-1">
                            <button type="button" :class="removeButton" :disabled="data.rows.length <= 1" @click="data.rows.splice(rowIndex, 1)">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex gap-2">
            <button type="button" :class="smallButton" :disabled="data.columns.length >= 6" @click="addColumn">Add column</button>
            <button type="button" :class="smallButton" :disabled="data.rows.length >= 30" @click="addRow">Add row</button>
        </div>
        <ul v-if="tableErrors.length" class="text-sm text-red-600">
            <li v-for="message in tableErrors" :key="message">{{ message }}</li>
        </ul>
        <Field :id="`${prefix}.note`" v-model="data.note" label="Note under the table (optional)" :error="error('note')" />
    </div>
</template>
```

- [ ] **Step 9: Create `Blocks/FaqFields.vue`**

```vue
<script setup>
import InputError from '@/Components/InputError.vue';
import Field from './Field.vue';
import { removeButton, smallButton } from './fieldOptions.js';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading (optional)" :error="error('heading')" />
        <fieldset class="space-y-3">
            <legend class="text-sm font-medium text-gray-700">Questions (up to 20)</legend>
            <div v-for="(item, index) in data.items" :key="index" class="space-y-3 rounded border border-gray-200 p-3">
                <Field :id="`${prefix}.items.${index}.question`" v-model="item.question" label="Question" :error="error(`items.${index}.question`)" />
                <Field :id="`${prefix}.items.${index}.answer`" v-model="item.answer" label="Answer (Markdown)" :rows="3" :error="error(`items.${index}.answer`)" />
                <button type="button" :class="removeButton" :disabled="data.items.length <= 1" @click="data.items.splice(index, 1)">Remove question</button>
            </div>
            <button type="button" :class="smallButton" :disabled="data.items.length >= 20" @click="data.items.push({ question: '', answer: '' })">Add question</button>
            <InputError :message="error('items')" />
        </fieldset>
    </div>
</template>
```

- [ ] **Step 10: Create `Blocks/ContactCardsFields.vue`**

```vue
<script setup>
import InputError from '@/Components/InputError.vue';
import Field from './Field.vue';
import { contactKinds, removeButton, smallButton } from './fieldOptions.js';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];

const hint = (kind) => contactKinds.find((option) => option.value === kind)?.hint ?? 'Value';
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading (optional)" :error="error('heading')" />
        <fieldset class="space-y-3">
            <legend class="text-sm font-medium text-gray-700">Cards (up to 6). Phone, WhatsApp and email cards link automatically.</legend>
            <div v-for="(item, index) in data.items" :key="index" class="grid gap-3 rounded border border-gray-200 p-3 sm:grid-cols-3">
                <Field :id="`${prefix}.items.${index}.kind`" v-model="item.kind" label="Type" :options="contactKinds" :error="error(`items.${index}.kind`)" />
                <Field :id="`${prefix}.items.${index}.label`" v-model="item.label" label="Label" :error="error(`items.${index}.label`)" />
                <Field :id="`${prefix}.items.${index}.value`" v-model="item.value" :label="hint(item.kind)" :error="error(`items.${index}.value`)" />
                <button type="button" :class="removeButton" class="justify-self-start" :disabled="data.items.length <= 1" @click="data.items.splice(index, 1)">Remove card</button>
            </div>
            <button type="button" :class="smallButton" :disabled="data.items.length >= 6" @click="data.items.push({ kind: 'phone', label: '', value: '' })">Add card</button>
            <InputError :message="error('items')" />
        </fieldset>
    </div>
</template>
```

- [ ] **Step 11: Create `Blocks/CallToActionFields.vue`**

```vue
<script setup>
import Field from './Field.vue';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading" :error="error('heading')" />
        <Field :id="`${prefix}.text`" v-model="data.text" label="Text (optional)" :rows="2" :error="error('text')" />
        <Field :id="`${prefix}.button_label`" v-model="data.button_label" label="Button label" :error="error('button_label')" />
        <Field
            :id="`${prefix}.button_url`"
            v-model="data.button_url"
            label="Button link: a store path like /pages/size-guide, or an https:// address"
            :error="error('button_url')"
        />
    </div>
</template>
```

- [ ] **Step 12: Create `Blocks/blockTypes.js`**

```js
import CallToActionFields from './CallToActionFields.vue';
import ContactCardsFields from './ContactCardsFields.vue';
import FaqFields from './FaqFields.vue';
import FeaturesFields from './FeaturesFields.vue';
import HeroFields from './HeroFields.vue';
import ImageTextFields from './ImageTextFields.vue';
import TableFields from './TableFields.vue';
import TextFields from './TextFields.vue';

// Keys must match PageBlockType values.
export const blockTypes = {
    hero: { label: 'Hero', fields: HeroFields, data: () => ({ eyebrow: '', heading: '', intro: '', image: null }) },
    text: { label: 'Text', fields: TextFields, data: () => ({ markdown: '' }) },
    image_text: { label: 'Image + text', fields: ImageTextFields, data: () => ({ image: null, image_alt: '', image_side: 'left', heading: '', markdown: '' }) },
    features: {
        label: 'Features',
        fields: FeaturesFields,
        data: () => ({ heading: '', items: [{ icon: 'cotton', title: '', text: '' }, { icon: 'feather', title: '', text: '' }] }),
    },
    table: { label: 'Table', fields: TableFields, data: () => ({ heading: '', columns: ['', ''], rows: [['', '']], note: '' }) },
    faq: { label: 'FAQ', fields: FaqFields, data: () => ({ heading: '', items: [{ question: '', answer: '' }] }) },
    contact_cards: { label: 'Contact cards', fields: ContactCardsFields, data: () => ({ heading: '', items: [{ kind: 'phone', label: '', value: '' }] }) },
    call_to_action: { label: 'Call to action', fields: CallToActionFields, data: () => ({ heading: '', text: '', button_label: '', button_url: '/' }) },
};

let nextUid = 0;

// uid only keys the block list in the editor; withoutUids strips it before submit.
export const withUid = (block) => ({ ...block, uid: nextUid++ });

export const newBlock = (type) => withUid({ type, data: blockTypes[type].data() });

export const withoutUids = (data) => ({ ...data, blocks: data.blocks.map(({ uid, ...block }) => block) });
```

- [ ] **Step 13: Replace `Partials/PageForm.vue`**

```vue
<script setup>
import { provide, ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { blockTypes, newBlock } from '../Blocks/blockTypes.js';
import { fieldClass, removeButton, smallButton } from '../Blocks/fieldOptions.js';

const props = defineProps({
    form: { type: Object, required: true },
    imageBaseUrl: { type: String, required: true },
});

defineEmits(['submit']);

provide('imageBaseUrl', props.imageBaseUrl);

const newType = ref('text');

const move = (index, offset) => {
    const [block] = props.form.blocks.splice(index, 1);
    props.form.blocks.splice(index + offset, 0, block);
};

const remove = (index) => {
    if (window.confirm(`Remove this ${blockTypes[props.form.blocks[index].type].label} block?`)) {
        props.form.blocks.splice(index, 1);
    }
};

const hasErrors = (index) => Object.keys(props.form.errors).some((key) => key.startsWith(`blocks.${index}.`));
</script>

<template>
    <form class="max-w-3xl space-y-6" @submit.prevent="$emit('submit')">
        <div class="space-y-6 rounded bg-white p-6 shadow-sm">
            <div>
                <InputLabel for="title" value="Title" />
                <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.title" />
            </div>
            <div>
                <InputLabel for="slug" value="URL slug (e.g. size-guide)" />
                <TextInput id="slug" v-model="form.slug" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.slug" />
            </div>
        </div>

        <section class="rounded bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Sections</h2>
            <p class="mt-1 text-sm text-gray-600">Shown on the page from top to bottom. Start with a Hero for a large title banner.</p>

            <ol class="mt-4 space-y-4">
                <li
                    v-for="(block, index) in form.blocks"
                    :key="block.uid"
                    class="rounded border p-4"
                    :class="hasErrors(index) ? 'border-red-400' : 'border-gray-200'"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">{{ index + 1 }}. {{ blockTypes[block.type].label }}</h3>
                        <div class="flex gap-1">
                            <button type="button" class="rounded border px-2 py-1 text-sm disabled:opacity-40" :disabled="index === 0" aria-label="Move up" @click="move(index, -1)">↑</button>
                            <button type="button" class="rounded border px-2 py-1 text-sm disabled:opacity-40" :disabled="index === form.blocks.length - 1" aria-label="Move down" @click="move(index, 1)">↓</button>
                            <button type="button" :class="removeButton" class="px-2" @click="remove(index)">Remove</button>
                        </div>
                    </div>
                    <component :is="blockTypes[block.type].fields" class="mt-4" :data="block.data" :errors="form.errors" :prefix="`blocks.${index}.data`" />
                </li>
            </ol>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <select v-model="newType" :class="fieldClass" class="w-auto" aria-label="Section type">
                    <option v-for="(type, key) in blockTypes" :key="key" :value="key">{{ type.label }}</option>
                </select>
                <button type="button" :class="smallButton" :disabled="form.blocks.length >= 30" @click="form.blocks.push(newBlock(newType))">Add section</button>
            </div>
            <InputError class="mt-2" :message="form.errors.blocks" />
        </section>

        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
```

- [ ] **Step 14: Replace `Create.vue`**

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageForm from './Partials/PageForm.vue';
import { newBlock, withoutUids } from './Blocks/blockTypes.js';

defineProps({
    imageBaseUrl: { type: String, required: true },
});

const form = useForm({ title: '', slug: '', blocks: [newBlock('hero')] });
</script>

<template>
    <Head title="New page" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">New page</h1>
        </template>

        <PageForm :form="form" :image-base-url="imageBaseUrl" @submit="form.transform(withoutUids).post(route('admin.pages.store'))" />
    </AdminLayout>
</template>
```

- [ ] **Step 15: Replace `Edit.vue`**

```vue
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageForm from './Partials/PageForm.vue';
import { withoutUids, withUid } from './Blocks/blockTypes.js';

const props = defineProps({
    page: { type: Object, required: true },
    imageBaseUrl: { type: String, required: true },
});

const form = useForm({ title: props.page.title, slug: props.page.slug, blocks: props.page.blocks.map(withUid) });
</script>

<template>
    <Head :title="`Edit ${page.title}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Edit page</h1>
                <Link :href="route('pages.show', page.slug)" class="text-sm text-gray-600 underline">View on store</Link>
            </div>
        </template>

        <PageForm :form="form" :image-base-url="imageBaseUrl" @submit="form.transform(withoutUids).put(route('admin.pages.update', page.id))" />
    </AdminLayout>
</template>
```

- [ ] **Step 16: Build**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 17: Commit**

```bash
git add resources/js/Pages/Admin/Pages
git commit -m "feat: admin section editor for content pages"
```

---

### Task 6: Storefront block components

**Files:**
- Create: `resources/js/Components/Icons/IconPhone.vue`, `IconMail.vue`, `IconMapPin.vue`, `IconClock.vue`, `IconChevronDown.vue`
- Create: `resources/js/Components/Store/Blocks/HeroBlock.vue`, `TextBlock.vue`, `ImageTextBlock.vue`, `FeaturesBlock.vue`, `TableBlock.vue`, `FaqBlock.vue`, `ContactCardsBlock.vue`, `CallToActionBlock.vue`
- Modify: `resources/js/Pages/Content/Show.vue` (whole file)

**Interfaces:**
- Consumes: `Content/Show` props `title: String`, `blocks: Array` with the rendered shapes listed in Task 2; `Reveal.vue` (`as`, `delay`); icons `IconCotton`, `IconFeather`, `IconPuffPrint`, `IconShield`, `IconChat`, `IconBag`.
- Produces: each block component takes one prop `data: Object` (the rendered `data`).

Design source: run ui-ux-pro-max before styling (e.g. `python3 .claude/skills/ui-ux-pro-max/scripts/search.py "faq accordion contact cards size table" --domain ux`) and keep the markup below unless a guideline flags a problem. The look follows the home page: `bg-brand-50` page, `text-ink`, `brand-900` dark panels, display font headings, small uppercase tracked labels, square corners, `border-brand-200` hairlines.

Show.vue no longer has its own banner: when the first block is not a Hero it renders `HeroBlock` with the page title and the eyebrow "Arusa Apparel Co.", which is the same dark photo band as before, slightly taller.

No Pest tests (UI only). Verified by `npm run build` here and in the browser in Task 8.

- [ ] **Step 1: Create the five icons (Heroicons outline paths, same wrapper as `IconShield.vue`)**

`IconPhone.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
    </svg>
</template>
```

`IconMail.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
    </svg>
</template>
```

`IconMapPin.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
    </svg>
</template>
```

`IconClock.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
</template>
```

`IconChevronDown.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
    </svg>
</template>
```

- [ ] **Step 2: Create `Store/Blocks/HeroBlock.vue`**

```vue
<script setup>
defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="relative isolate overflow-hidden bg-brand-900 text-brand-50">
        <img
            :src="data.image?.src ?? '/images/hero-1600.webp'"
            :srcset="data.image?.srcset ?? '/images/hero-800.webp 800w, /images/hero-1600.webp 1600w'"
            sizes="100vw"
            alt=""
            class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
        />
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-900 via-brand-900/60 to-brand-900/20" aria-hidden="true" />

        <div class="mx-auto flex min-h-[16rem] max-w-3xl flex-col justify-end px-4 pb-10 pt-24 sm:min-h-[22rem] sm:pb-14">
            <p v-if="data.eyebrow" class="animate-rise-in text-xs uppercase tracking-[0.3em] text-brand-200">{{ data.eyebrow }}</p>
            <h1 class="mt-2 animate-rise-in font-display text-3xl font-semibold text-brand-50 [animation-delay:75ms] sm:text-5xl">{{ data.heading }}</h1>
            <p v-if="data.intro" class="mt-4 max-w-xl animate-rise-in text-base leading-relaxed text-brand-100 [animation-delay:150ms] sm:text-lg">{{ data.intro }}</p>
        </div>
    </section>
</template>
```

- [ ] **Step 3: Create `Store/Blocks/TextBlock.vue`**

```vue
<script setup>
defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <div class="mx-auto max-w-3xl px-4">
        <!-- html is server-rendered Markdown with raw HTML stripped -->
        <div class="content-prose" v-html="data.html" />
    </div>
</template>
```

- [ ] **Step 4: Create `Store/Blocks/ImageTextBlock.vue`**

```vue
<script setup>
defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto grid max-w-6xl items-center gap-8 px-4 md:grid-cols-2 md:gap-14">
        <img
            :src="data.image.src"
            :srcset="data.image.srcset"
            sizes="(min-width: 768px) 50vw, 100vw"
            :alt="data.image_alt ?? ''"
            loading="lazy"
            class="aspect-[4/5] w-full object-cover"
            :class="{ 'md:order-last': data.image_side === 'right' }"
        />
        <div>
            <h2 v-if="data.heading" class="mb-4 font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
            <!-- html is server-rendered Markdown with raw HTML stripped -->
            <div class="content-prose" v-html="data.html" />
        </div>
    </section>
</template>
```

- [ ] **Step 5: Create `Store/Blocks/FeaturesBlock.vue`**

```vue
<script setup>
import IconBag from '@/Components/Icons/IconBag.vue';
import IconChat from '@/Components/Icons/IconChat.vue';
import IconCotton from '@/Components/Icons/IconCotton.vue';
import IconFeather from '@/Components/Icons/IconFeather.vue';
import IconPuffPrint from '@/Components/Icons/IconPuffPrint.vue';
import IconShield from '@/Components/Icons/IconShield.vue';

defineProps({
    data: { type: Object, required: true },
});

// Keys must match PageBlockType::ICONS.
const icons = { cotton: IconCotton, feather: IconFeather, 'puff-print': IconPuffPrint, shield: IconShield, chat: IconChat, bag: IconBag };
</script>

<template>
    <section class="mx-auto max-w-6xl px-4">
        <h2 v-if="data.heading" class="mb-10 text-center font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
        <ul class="grid gap-x-6 gap-y-10 sm:grid-cols-2" :class="{ 'lg:grid-cols-3': data.items.length === 3, 'lg:grid-cols-4': data.items.length === 4 }">
            <li v-for="(item, index) in data.items" :key="index" class="flex flex-col items-center text-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full border border-ink">
                    <component :is="icons[item.icon]" class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-xs font-medium uppercase tracking-[0.2em]">{{ item.title }}</h3>
                <p class="mt-2 max-w-xs text-sm leading-relaxed text-brand-700">{{ item.text }}</p>
            </li>
        </ul>
    </section>
</template>
```

- [ ] **Step 6: Create `Store/Blocks/TableBlock.vue`**

```vue
<script setup>
defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto max-w-3xl px-4">
        <h2 v-if="data.heading" class="mb-4 font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
        <div class="overflow-x-auto border border-brand-200">
            <table class="w-full min-w-max text-left text-sm">
                <thead class="bg-brand-900 text-brand-50">
                    <tr>
                        <th
                            v-for="(column, index) in data.columns"
                            :key="index"
                            scope="col"
                            class="px-4 py-3 text-xs font-medium uppercase tracking-[0.2em]"
                            :class="{ 'sticky left-0 bg-brand-900': index === 0 }"
                        >
                            {{ column }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, rowIndex) in data.rows" :key="rowIndex" class="odd:bg-white even:bg-brand-100">
                        <template v-for="(cell, cellIndex) in row" :key="cellIndex">
                            <th v-if="cellIndex === 0" scope="row" class="sticky left-0 bg-inherit px-4 py-3 font-semibold">{{ cell }}</th>
                            <td v-else class="px-4 py-3 tabular-nums text-brand-800">{{ cell }}</td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="data.note" class="mt-3 text-xs text-brand-700">{{ data.note }}</p>
    </section>
</template>
```

- [ ] **Step 7: Create `Store/Blocks/FaqBlock.vue`**

```vue
<script setup>
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto max-w-3xl px-4">
        <h2 v-if="data.heading" class="mb-4 font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
        <div class="divide-y divide-brand-200 border-y border-brand-200">
            <details v-for="(item, index) in data.items" :key="index" class="group">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 py-4 font-medium focus-ring [&::-webkit-details-marker]:hidden">
                    {{ item.question }}
                    <IconChevronDown class="h-5 w-5 shrink-0 text-brand-700 transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none" />
                </summary>
                <!-- html is server-rendered Markdown with raw HTML stripped -->
                <div class="content-prose pb-4 text-brand-800" v-html="item.html" />
            </details>
        </div>
    </section>
</template>
```

- [ ] **Step 8: Create `Store/Blocks/ContactCardsBlock.vue`**

```vue
<script setup>
import IconChat from '@/Components/Icons/IconChat.vue';
import IconClock from '@/Components/Icons/IconClock.vue';
import IconMail from '@/Components/Icons/IconMail.vue';
import IconMapPin from '@/Components/Icons/IconMapPin.vue';
import IconPhone from '@/Components/Icons/IconPhone.vue';

defineProps({
    data: { type: Object, required: true },
});

const icons = { phone: IconPhone, whatsapp: IconChat, email: IconMail, address: IconMapPin, hours: IconClock };
</script>

<template>
    <section class="mx-auto max-w-6xl px-4">
        <h2 v-if="data.heading" class="mb-6 font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="(item, index) in data.items" :key="index">
                <component
                    :is="item.href ? 'a' : 'div'"
                    :href="item.href"
                    :target="item.kind === 'whatsapp' ? '_blank' : undefined"
                    :rel="item.kind === 'whatsapp' ? 'noopener' : undefined"
                    class="flex h-full items-start gap-4 border border-brand-200 bg-white p-6"
                    :class="{ 'transition-colors duration-200 hover:border-ink motion-reduce:transition-none focus-ring': item.href }"
                >
                    <component :is="icons[item.kind]" class="h-6 w-6 shrink-0 text-brand-700" />
                    <span class="min-w-0">
                        <span class="block text-xs uppercase tracking-[0.2em] text-brand-700">{{ item.label }}</span>
                        <span class="mt-1 block whitespace-pre-line break-words font-medium">{{ item.value }}</span>
                    </span>
                </component>
            </li>
        </ul>
    </section>
</template>
```

- [ ] **Step 9: Create `Store/Blocks/CallToActionBlock.vue`**

```vue
<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto max-w-6xl px-4">
        <div class="bg-brand-900 px-6 py-14 text-center text-brand-50 sm:px-12 sm:py-20">
            <h2 class="font-display text-2xl font-semibold sm:text-4xl">{{ data.heading }}</h2>
            <p v-if="data.text" class="mx-auto mt-3 max-w-xl text-brand-200">{{ data.text }}</p>
            <component
                :is="data.button_url.startsWith('/') ? Link : 'a'"
                :href="data.button_url"
                class="mt-8 inline-flex min-h-12 items-center border border-brand-50 px-8 text-xs font-medium uppercase tracking-[0.2em] transition-colors duration-200 hover:bg-brand-50 hover:text-ink motion-reduce:transition-none focus-ring-inverse"
            >
                {{ data.button_label }}
            </component>
        </div>
    </section>
</template>
```

- [ ] **Step 10: Replace `resources/js/Pages/Content/Show.vue`**

```vue
<script setup>
import { Head } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import Reveal from '@/Components/Store/Reveal.vue';
import CallToActionBlock from '@/Components/Store/Blocks/CallToActionBlock.vue';
import ContactCardsBlock from '@/Components/Store/Blocks/ContactCardsBlock.vue';
import FaqBlock from '@/Components/Store/Blocks/FaqBlock.vue';
import FeaturesBlock from '@/Components/Store/Blocks/FeaturesBlock.vue';
import HeroBlock from '@/Components/Store/Blocks/HeroBlock.vue';
import ImageTextBlock from '@/Components/Store/Blocks/ImageTextBlock.vue';
import TableBlock from '@/Components/Store/Blocks/TableBlock.vue';
import TextBlock from '@/Components/Store/Blocks/TextBlock.vue';

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
    <Head :title="title" />

    <StoreLayout>
        <HeroBlock v-if="blocks[0]?.type !== 'hero'" :data="{ eyebrow: 'Arusa Apparel Co.', heading: title }" />

        <div class="pb-16 sm:pb-24">
            <template v-for="(block, index) in blocks" :key="index">
                <HeroBlock v-if="index === 0 && block.type === 'hero'" :data="block.data" />
                <Reveal v-else class="mt-14 sm:mt-20">
                    <component :is="components[block.type]" :data="block.data" />
                </Reveal>
            </template>
        </div>
    </StoreLayout>
</template>
```

- [ ] **Step 11: Build**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 12: Commit**

```bash
git add resources/js/Components/Icons resources/js/Components/Store/Blocks resources/js/Pages/Content/Show.vue
git commit -m "feat: storefront section blocks for content pages"
```

---

### Task 7: Seed real content for the four pages

**Files:**
- Modify: `database/seeders/PageSeeder.php` (whole file)
- Test: `tests/Feature/PageSeederTest.php` (new)

**Interfaces:**
- Consumes: `StoreWebpImage::handle()` (Task 1), `Page::IMAGE_WIDTHS` (Task 2), the block shapes and validation from Tasks 2–3, `public/images/hero-1600.webp` (exists).
- Produces: pages `about`, `contact`, `size-guide`, `shipping-returns` with real blocks. A page is overwritten only if it is missing or still the placeholder (exactly one `text` block whose Markdown contains "Edit this page in the admin.").

- [ ] **Step 1: Write the failing tests `tests/Feature/PageSeederTest.php`**

```php
<?php

use App\Models\Page;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('the seeder fills placeholder pages and leaves edited pages alone', function () {
    Page::factory()->create(['slug' => 'about', 'blocks' => [['type' => 'text', 'data' => ['markdown' => "Old.\n\nEdit this page in the admin."]]]]);
    Page::factory()->create(['slug' => 'contact', 'title' => 'Talk to us', 'blocks' => [['type' => 'text', 'data' => ['markdown' => 'Our own words.']]]]);

    $this->seed(PageSeeder::class);

    $about = Page::firstWhere('slug', 'about');

    expect(collect($about->blocks)->pluck('type')->all())->toBe(['hero', 'image_text', 'features', 'call_to_action'])
        ->and(Storage::disk('public')->exists("{$about->blocks[1]['data']['image']}-800.webp"))->toBeTrue()
        ->and(Page::firstWhere('slug', 'contact')->title)->toBe('Talk to us')
        ->and(Page::whereIn('slug', ['size-guide', 'shipping-returns'])->count())->toBe(2);
});

test('seeded pages pass the admin validation', function () {
    $this->seed(PageSeeder::class);
    $admin = User::factory()->admin()->create();

    Page::all()->each(fn (Page $page) => $this->actingAs($admin)
        ->put("/admin/pages/{$page->id}", $page->only('title', 'slug', 'blocks'))
        ->assertSessionHasNoErrors());
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/PageSeederTest.php`
Expected: FAIL (seeder still writes a `body`).

- [ ] **Step 3: Replace `database/seeders/PageSeeder.php`**

Contact values, the size chart numbers and the 7-day returns window are placeholders the owner must confirm or replace in the admin before launch.

```php
<?php

namespace Database\Seeders;

use App\Actions\StoreWebpImage;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class PageSeeder extends Seeder
{
    public function __construct(private StoreWebpImage $storeWebpImage) {}

    /**
     * Create the content pages, or fill ones still holding placeholder text. Pages edited in the admin are left alone.
     *
     * Placeholders to confirm in the admin before launch: contact numbers and email, the size chart, the returns window.
     */
    public function run(): void
    {
        $pages = [
            'about' => ['About Arusa', $this->aboutBlocks(...)],
            'contact' => ['Contact', $this->contactBlocks(...)],
            'size-guide' => ['Size Guide', $this->sizeGuideBlocks(...)],
            'shipping-returns' => ['Shipping & Returns', $this->shippingBlocks(...)],
        ];

        foreach ($pages as $slug => [$title, $blocks]) {
            $page = Page::firstWhere('slug', $slug);

            if ($page !== null && ! $this->isPlaceholder($page)) {
                continue;
            }

            Page::updateOrCreate(['slug' => $slug], ['title' => $title, 'blocks' => $blocks()]);
        }
    }

    private function isPlaceholder(Page $page): bool
    {
        return count($page->blocks) === 1
            && $page->blocks[0]['type'] === 'text'
            && str_contains($page->blocks[0]['data']['markdown'], 'Edit this page in the admin.');
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function aboutBlocks(): array
    {
        $photo = $this->storePhoto();

        return array_values(array_filter([
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Our story',
                'heading' => 'Made in Aizawl, worn everywhere',
                'intro' => 'Arusa is a small streetwear label from Aizawl, Mizoram, making heavyweight tees and everyday pieces we actually want to wear.',
            ]],
            $photo === null ? null : ['type' => 'image_text', 'data' => [
                'image' => $photo,
                'image_alt' => 'Arusa tees',
                'image_side' => 'left',
                'heading' => 'Started small, on purpose',
                'markdown' => "Arusa began in 2026 with one idea: clothes from Mizoram that hold up next to anything from the big cities.\n\nWe keep our runs small, finish our designs in raised puff print, and check every piece before it ships. When something sells out, we would rather make it again properly than rush a cheaper version.",
            ]],
            ['type' => 'features', 'data' => [
                'heading' => 'What goes into every piece',
                'items' => [
                    ['icon' => 'cotton', 'title' => 'Premium cotton', 'text' => 'Soft, heavyweight fabric that keeps its shape wash after wash.'],
                    ['icon' => 'puff-print', 'title' => 'Puff print detail', 'text' => 'Raised prints you can feel, made to last.'],
                    ['icon' => 'feather', 'title' => 'Comfort fit', 'text' => 'Relaxed cuts made for long days in the hills.'],
                    ['icon' => 'shield', 'title' => 'Checked by hand', 'text' => 'Every order is inspected before it leaves us.'],
                ],
            ]],
            ['type' => 'call_to_action', 'data' => [
                'heading' => 'Find your next favourite tee',
                'text' => 'New drops land in small batches. When they are gone, they are gone.',
                'button_label' => 'Shop now',
                'button_url' => '/',
            ]],
        ]));
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function contactBlocks(): array
    {
        return [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Contact',
                'heading' => 'We’re here to help',
                'intro' => 'Questions about sizing, an order or a delivery? Reach us the way that suits you and we’ll get back to you as soon as we can.',
            ]],
            ['type' => 'contact_cards', 'data' => [
                'heading' => 'Get in touch',
                'items' => [
                    ['kind' => 'whatsapp', 'label' => 'WhatsApp', 'value' => '9000000000'],
                    ['kind' => 'phone', 'label' => 'Call us', 'value' => '9000000000'],
                    ['kind' => 'email', 'label' => 'Email', 'value' => 'hello@example.com'],
                    ['kind' => 'address', 'label' => 'Based in', 'value' => 'Aizawl, Mizoram'],
                    ['kind' => 'hours', 'label' => 'Hours', 'value' => 'Monday to Saturday, 10am – 6pm'],
                ],
            ]],
            ['type' => 'faq', 'data' => [
                'heading' => 'Order help',
                'items' => [
                    ['question' => 'How do I track my order?', 'answer' => 'Use [Track order](/track) with your order number and phone number. If you have an account, every order is also listed there.'],
                    ['question' => 'Can I change or cancel my order?', 'answer' => 'Message us as soon as you can. We can change or cancel an order until it has been dispatched.'],
                    ['question' => 'Which payment methods do you accept?', 'answer' => 'Cash on delivery where available, and online payment through Razorpay (UPI, cards and netbanking).'],
                ],
            ]],
        ];
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function sizeGuideBlocks(): array
    {
        return [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Fit',
                'heading' => 'Size guide',
                'intro' => 'Our tees have a relaxed fit. Between sizes? Take the smaller one for a closer fit or the larger one for a roomier look.',
            ]],
            ['type' => 'text', 'data' => [
                'markdown' => "## How to measure\n\n- **Chest:** measure around the fullest part of your chest, keeping the tape level under your arms.\n- **Length:** measure from the highest point of the shoulder down to the hem.\n- **Shoulder:** measure straight across the back from one shoulder seam to the other.\n\nEasiest of all: lay a tee you already love flat, measure it, and compare it with the table below.",
            ]],
            ['type' => 'table', 'data' => [
                'heading' => 'Tees, in inches',
                'columns' => ['Size', 'Chest', 'Length', 'Shoulder'],
                'rows' => [
                    ['S', '38', '27', '17'],
                    ['M', '40', '28', '18'],
                    ['L', '42', '29', '19'],
                    ['XL', '44', '30', '20'],
                    ['XXL', '46', '31', '21'],
                ],
                'note' => 'Garment measurements, laid flat. Allow up to half an inch either way.',
            ]],
            ['type' => 'faq', 'data' => [
                'heading' => 'Fit questions',
                'items' => [
                    ['question' => 'How should I wash my tee?', 'answer' => 'Wash cold and inside out, and dry in the shade to keep the fabric and print at their best.'],
                    ['question' => 'What if it doesn’t fit?', 'answer' => 'See [Shipping & Returns](/pages/shipping-returns) for how exchanges work.'],
                ],
            ]],
        ];
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function shippingBlocks(): array
    {
        return [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Delivery',
                'heading' => 'Shipping & returns',
                'intro' => 'We deliver across Mizoram. Here is how delivery, payment and returns work.',
            ]],
            ['type' => 'features', 'data' => [
                'items' => [
                    ['icon' => 'bag', 'title' => 'Mizoram-wide delivery', 'text' => 'Enter your pincode at checkout to see the delivery charge for your area.'],
                    ['icon' => 'shield', 'title' => 'Pay your way', 'text' => 'Cash on delivery where available, or pay online with UPI, cards or netbanking.'],
                    ['icon' => 'chat', 'title' => 'Real help', 'text' => 'Message us about any order and a real person will sort it out.'],
                ],
            ]],
            ['type' => 'text', 'data' => [
                'markdown' => "## Delivery\n\nOrders are packed in Aizawl and typically delivered within a few working days, depending on your location. You can follow your order on the [tracking page](/track).\n\n## Returns and exchanges\n\nIf something is wrong with your order or the size doesn’t fit, contact us within **7 days** of delivery. Items must be unworn and unwashed, with their tags on.",
            ]],
            ['type' => 'faq', 'data' => [
                'heading' => 'Common questions',
                'items' => [
                    ['question' => 'How much is delivery?', 'answer' => 'It depends on your area. The exact charge is shown at checkout once you enter your pincode.'],
                    ['question' => 'Do you deliver outside Mizoram?', 'answer' => 'Not yet. We currently deliver within Mizoram only.'],
                    ['question' => 'When will I get my refund?', 'answer' => 'Online payments are refunded to the original payment method once the return is approved. Your bank may take a few more days to show it.'],
                ],
            ]],
        ];
    }

    /**
     * Run the bundled hero photo through the page image pipeline; null if the file is missing.
     */
    private function storePhoto(): ?string
    {
        $file = public_path('images/hero-1600.webp');

        if (! is_file($file)) {
            return null;
        }

        $path = 'pages/'.Str::uuid();
        $this->storeWebpImage->handle(new UploadedFile($file, basename($file), test: true), $path, Page::IMAGE_WIDTHS, 'image');

        return $path;
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact tests/Feature/PageSeederTest.php`
Expected: all PASS.

- [ ] **Step 5: Seed locally**

Run: `php artisan db:seed --class=PageSeeder --no-interaction`
Expected: completes; the four pages now have blocks (placeholders were replaced, anything edited in the admin was kept).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/PageSeeder.php tests/Feature/PageSeederTest.php
git commit -m "feat: seed real content for the content pages"
```

---

### Task 8: Verify in the browser and open the PR (controller task)

**Files:** none unless a fix is needed.

- [ ] **Step 1: Run every test touched by this plan**

Run: `php artisan test --compact tests/Feature/ContentPageTest.php tests/Feature/PageBlocksMigrationTest.php tests/Feature/PageSeederTest.php tests/Feature/Admin/PageManagementTest.php tests/Feature/Admin/PageBlocksTest.php tests/Feature/Admin/PageImageTest.php tests/Feature/Admin/ProductImageTest.php tests/Feature/CatalogSeederTest.php`
Expected: all PASS.

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 3: Storefront check with Playwright (use `get-absolute-url` for the base URL)**

At 390 px and 1280 px wide, open `/pages/about`, `/pages/contact`, `/pages/size-guide`, `/pages/shipping-returns`:
- Hero shows eyebrow, heading and intro over the photo; sections reveal on scroll.
- About: photo + text side by side on desktop, stacked on mobile; four features; CTA button goes to `/`.
- Contact: cards link (`tel:`, `wa.me` in a new tab, `mailto:`); address and hours are not links.
- Size Guide: table scrolls sideways on mobile with the Size column pinned; FAQ opens and closes with keyboard (Tab, Enter).
- No console errors (`browser-logs`).

- [ ] **Step 4: Admin check with Playwright**

Log in as an admin, open `/admin/pages`, edit About:
- Move a section up and down, add a FAQ section, add and remove a question, save; the change shows on the store.
- Upload a photo in an Image + text section; the preview appears and saves.
- Clear a required heading and save: the section gets a red border and the error reads "The block N heading field is required."
- Create a new page; it starts with a Hero section.

- [ ] **Step 5: Push and open the PR**

```bash
git push -u origin feat/page-blocks
gh pr create --title "Content pages built from admin-editable sections" --body "Spec: docs/superpowers/specs/2026-10-24-page-blocks-design.md
Plan: docs/superpowers/plans/2026-10-24-page-blocks.md

- Pages store an ordered list of typed blocks (hero, text, image + text, features, table, FAQ, contact cards, call to action), validated per type.
- Admin section editor with move up/down, add/remove, inline image upload.
- Storefront renders each block with its own design.
- StoreWebpImage extracted from StoreProductImage and shared with page image uploads.
- Real starter content seeded for About, Contact, Size Guide and Shipping & Returns.

Before launch: replace the placeholder contact details, confirm the size chart and the 7-day returns window in the admin."
```

- [ ] **Step 6: Ask the user to run the full suite**

Ask the user to run `php artisan test --compact` before merging.
