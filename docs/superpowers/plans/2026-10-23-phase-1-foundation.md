# Phase 1: Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Set up the repo, project-scoped Claude Code tooling, admin access, store settings, content pages, and the branded storefront shell for Arusa.

**Architecture:** Plain Laravel controllers + Form Requests rendering Inertia/Vue pages. Admin lives under `/admin` (routes in `routes/admin.php`, pages in `resources/js/Pages/Admin/`, layout `AdminLayout.vue`) guarded by an `access-admin` Gate. Store settings are a cached key-value table shared to every page. Content pages are Markdown rendered server-side with HTML stripped.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v2 (inertia-laravel 2.0.28) + Vue 3, Tailwind 3, Pest 5, PostgreSQL (tests run on in-memory SQLite per `phpunit.xml`).

**Spec:** `docs/superpowers/specs/2026-10-23-arusa-ecommerce-design.md`

## Global Constraints

- No new PHP/JS dependencies without approval. **No Lucide**; no animation library; no icon package.
- Icons: inline SVG Vue components in `resources/js/Components/Icons/`.
- Motion: CSS transitions / Vue `<Transition>` only, 150–300 ms, respects `prefers-reduced-motion`.
- Design skills apply to **user-facing pages only**: ui-ux-pro-max is primary; design-motion-principles for motion; impeccable for audit/polish only. Admin stays plain and functional.
- Mobile-first; storefront JS budget < ~200 KB.
- Indian mobile numbers validate with `/^[6-9]\d{9}$/` (10 digits, no +91).
- PHP: curly braces always, explicit return types, constructor promotion, PHPDoc over inline comments, TitleCase enum keys.
- `is_admin` is never mass-assignable.
- After PHP changes: `vendor/bin/pint --dirty --format agent`. Run narrowest tests with `php artisan test --compact <path>`.
- Activate the relevant project skills while working: `laravel-best-practices` (PHP), `inertia-vue-development` (Vue pages), `tailwindcss-development` (styling), `testing-best-practices` (tests).

## Prerequisite (owner)

- [ ] Logo file placed at `resources/images/logo.svg` (preferred) or `resources/images/logo.png`. Task 6 is blocked until it exists.

## File Map

| File | Responsibility |
|---|---|
| `.gitignore` | + `.claude/settings.local.json` |
| `.claude/settings.json` | Project-pinned plugins + marketplaces |
| `.mcp.json` | + Playwright MCP |
| `.claude/skills/ui-ux-pro-max/`, `.claude/skills/design-motion-principles/` | Third-party design skills (installed) |
| `.claude/skills/arusa-commerce/SKILL.md` | Project domain + design-routing rules |
| `database/migrations/*_add_is_admin_to_users_table.php` | `is_admin` column |
| `app/Models/User.php` | `is_admin` cast |
| `database/factories/UserFactory.php` | `admin()` state |
| `app/Providers/AppServiceProvider.php` | `access-admin` Gate |
| `routes/console.php` | `admin:grant {email}` command |
| `routes/admin.php` | All `/admin` routes |
| `routes/web.php` | Home route, content page route, require admin routes |
| `resources/js/Layouts/AdminLayout.vue` | Admin shell |
| `resources/js/Pages/Admin/Dashboard.vue` | Admin landing page |
| `database/migrations/*_create_settings_table.php`, `app/Models/Setting.php` | Cached key-value settings |
| `app/Http/Controllers/Admin/SettingController.php`, `app/Http/Requests/Admin/UpdateSettingsRequest.php` | Edit settings |
| `resources/js/Pages/Admin/Settings/Edit.vue` | Settings form |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share `store` + `flash.status` |
| `database/migrations/*_create_pages_table.php`, `app/Models/Page.php`, `database/factories/PageFactory.php`, `database/seeders/PageSeeder.php` | Content pages |
| `app/Http/Controllers/PageController.php` | Storefront page view |
| `app/Http/Controllers/Admin/PageController.php`, `app/Http/Requests/Admin/PageRequest.php` | Admin page CRUD |
| `resources/js/Pages/Content/Show.vue` | Storefront content page |
| `resources/js/Pages/Admin/Pages/{Index,Create,Edit}.vue`, `resources/js/Pages/Admin/Pages/Partials/PageForm.vue` | Admin page screens |
| `tailwind.config.js` | Brand tokens |
| `resources/js/Components/ApplicationLogo.vue` | Real logo |
| `resources/js/Components/Icons/*.vue` | Inline SVG icons |
| `resources/js/Layouts/StoreLayout.vue` | Storefront header/footer |
| `resources/js/Pages/Home.vue` | Storefront home (replaces `Welcome.vue`) |

---

### Task 1: Repo baseline and project-pinned plugins

**Files:**
- Modify: `.gitignore`
- Create: `.claude/settings.json`
- Modify: `.env.example`, `.env` (APP_NAME only)

**Interfaces:**
- Produces: a git history with the full app committed; plugins pinned for this repo.

- [ ] **Step 1: Ignore local Claude settings**

Append to `.gitignore`:

```
/.claude/settings.local.json
```

- [ ] **Step 2: Pin plugins for this project**

Create `.claude/settings.json`:

```json
{
    "enabledPlugins": {
        "superpowers@superpowers-marketplace": true,
        "ponytail@ponytail": true,
        "frontend-design@claude-plugins-official": true,
        "impeccable@impeccable": true,
        "codex@openai-codex": true
    },
    "extraKnownMarketplaces": {
        "superpowers-marketplace": { "source": { "source": "github", "repo": "obra/superpowers-marketplace" } },
        "ponytail": { "source": { "source": "github", "repo": "DietrichGebert/ponytail" } },
        "impeccable": { "source": { "source": "github", "repo": "pbakaus/impeccable" } },
        "openai-codex": { "source": { "source": "github", "repo": "openai/codex-plugin-cc" } }
    }
}
```

- [ ] **Step 3: Set app name**

In both `.env.example` and `.env`, change `APP_NAME=Laravel` to `APP_NAME=Arusa`.

- [ ] **Step 4: Verify the suite is green before committing**

Run: `php artisan test --compact`
Expected: all existing Breeze tests PASS.

- [ ] **Step 5: Check nothing secret is staged, then commit**

```bash
git add -A
git status --short | grep -E '\.env$|settings\.local|graphify-out|\.idea' && echo "STOP: secret/local file staged" || echo "clean"
```
Expected: `clean`.

```bash
git commit -m "chore: initial Laravel + Breeze app with project Claude settings"
```

---

### Task 2: Project Claude tooling (design skills, Playwright MCP, arusa-commerce skill)

**Files:**
- Create: `.claude/skills/ui-ux-pro-max/` (installer)
- Create: `.claude/skills/design-motion-principles/` (installer)
- Modify: `.mcp.json`
- Create: `.claude/skills/arusa-commerce/SKILL.md`

**Interfaces:**
- Produces: skills `ui-ux-pro-max`, `design-motion-principles`, `arusa-commerce`; MCP server `playwright`. Task 6 uses the design skills.

- [ ] **Step 1: Read each README before installing**

Read `https://github.com/nextlevelbuilder/ui-ux-pro-max-skill` and `https://github.com/kylezantos/design-motion-principles` READMEs. Confirm the project-scoped install commands below still match; if they differ, use the README's project-scoped command. Do not install globally.

- [ ] **Step 2: Install ui-ux-pro-max (project-scoped)**

Run from the project root: `npx uipro-cli init --ai claude`
Expected: `.claude/skills/ui-ux-pro-max/SKILL.md` exists. If it creates a `.shared/` data folder, keep it and commit it (the skill needs it). Python 3 is available at `/usr/bin/python3`.

- [ ] **Step 3: Install design-motion-principles (project-scoped)**

Run: `npx skills add kylezantos/design-motion-principles` and choose the project (not global) location / Claude Code agent when prompted.
Fallback if the CLI cannot target the project: `git clone --depth 1 https://github.com/kylezantos/design-motion-principles /private/tmp/dmp && mkdir -p .claude/skills/design-motion-principles && cp -R /private/tmp/dmp/<skill folder containing SKILL.md>/. .claude/skills/design-motion-principles/`
Expected: `.claude/skills/design-motion-principles/SKILL.md` exists.

- [ ] **Step 4: Read both installed SKILL.md files**

Skim each `SKILL.md` and any scripts it references. Confirm they contain no install hooks or network calls beyond what the README documents. Report anything unexpected to the owner before committing.

- [ ] **Step 5: Add Playwright MCP**

Replace `.mcp.json` with:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": ["artisan", "boost:mcp"]
        },
        "playwright": {
            "command": "npx",
            "args": ["@playwright/mcp@latest"]
        }
    }
}
```

- [ ] **Step 6: Write the arusa-commerce skill**

Create `.claude/skills/arusa-commerce/SKILL.md`:

```markdown
---
name: arusa-commerce
description: Arusa store domain and design rules. Use for any work on products, variants, cart, checkout, orders, payments, shipping zones, admin screens, or storefront UI in this project.
---

# Arusa commerce rules

Spec: `docs/superpowers/specs/2026-10-23-arusa-ecommerce-design.md`. Plans: `docs/superpowers/plans/`.

## Business
- Single vendor, online only, Aizawl (Mizoram). Delivers within Mizoram only, via shipping zones resolved by pincode.
- Payments: COD + Razorpay. Not GST-registered: plain receipts, prices are final, no tax lines.
- Guest checkout (name + 10-digit phone `/^[6-9]\d{9}$/`, optional email) plus optional Breeze account.

## Money
- Store every amount as integer **paise**. Never floats. Format only in the UI (`₹1,299`).
- Effective variant price = `price_override ?? base_price`.
- Always recompute prices and totals server-side. Never trust a client-sent price.

## Orders and stock
- `order_items` and the order's shipping address are **snapshots**: copy values, never read live product data for past orders.
- Stock changes run in a DB transaction with `lockForUpdate()` on variant rows.
- Fulfilment transitions allowed: Pending→Confirmed→Packed→Dispatched→Delivered; Cancelled from Pending, Confirmed, Packed. Reject anything else.
- Cancelling restores stock. Razorpay webhooks are signature-verified, idempotent, and the source of truth.

## Admin
- Routes in `routes/admin.php`, behind `auth` + `can:access-admin`. Pages in `resources/js/Pages/Admin/`, layout `AdminLayout.vue`.
- Plain and functional. Reuse shared admin components; no design-skill passes, no decorative motion.
- `is_admin` is never mass-assignable. Grant with `php artisan admin:grant {email}`.

## Storefront design
- User-facing pages use **ui-ux-pro-max** as the primary design source, **design-motion-principles** for motion, **impeccable** only for audit/polish passes.
- Motion: CSS transitions or Vue `<Transition>` only, 150–300 ms, honour `prefers-reduced-motion`.
- Mobile-first, storefront JS < ~200 KB, WebP + `srcset` + lazy-loaded images.
- Icons: inline SVG components in `resources/js/Components/Icons/`. **Never** add Lucide or any icon/animation package.
```

- [ ] **Step 7: Verify skills load**

Restart Claude Code in the project, then run `/skills` (or check the skills listing) and confirm `ui-ux-pro-max`, `design-motion-principles`, and `arusa-commerce` appear. Run `/mcp` and confirm `playwright` connects.

- [ ] **Step 8: Commit**

```bash
git add .claude/skills .mcp.json
[ -d .shared ] && git add .shared
git commit -m "chore: add project design skills, Playwright MCP, arusa-commerce skill"
```

---

### Task 3: Admin access (is_admin, Gate, grant command, admin shell)

**Files:**
- Create: `database/migrations/<timestamp>_add_is_admin_to_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/AppServiceProvider.php`, `routes/console.php`, `routes/web.php`
- Create: `routes/admin.php`, `resources/js/Layouts/AdminLayout.vue`, `resources/js/Pages/Admin/Dashboard.vue`
- Test: `tests/Feature/Admin/AdminAccessTest.php`

**Interfaces:**
- Produces: Gate `access-admin`; route group prefix `admin`, name prefix `admin.`; route `admin.dashboard` (`GET /admin`); `UserFactory::admin()` state; `AdminLayout.vue` with default slot and optional `header` slot; `auth.user.is_admin` (bool) in shared props.

- [ ] **Step 1: Write the failing tests**

Run: `php artisan make:test --pest Admin/AdminAccessTest --no-interaction` then replace contents of `tests/Feature/Admin/AdminAccessTest.php`:

```php
<?php

use App\Models\User;

test('guests are redirected to login from admin', function () {
    $this->get('/admin')->assertRedirect('/login');
});

test('non-admin users are forbidden from admin', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('admins can view the admin dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
});

test('is_admin cannot be mass assigned', function () {
    $user = User::factory()->create();

    $user->fill(['is_admin' => true])->save();

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('admin:grant makes an existing user an admin', function () {
    $user = User::factory()->create(['email' => 'owner@arusa.co.in']);

    $this->artisan('admin:grant', ['email' => 'owner@arusa.co.in'])
        ->expectsOutput('owner@arusa.co.in is now an admin.')
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('admin:grant fails for an unknown email', function () {
    $this->artisan('admin:grant', ['email' => 'nobody@example.com'])
        ->expectsOutput('No user with email nobody@example.com.')
        ->assertFailed();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/AdminAccessTest.php`
Expected: FAIL (404 for `/admin`, unknown `admin()` factory state, unknown command).

- [ ] **Step 3: Migration**

Run: `php artisan make:migration add_is_admin_to_users_table --table=users --no-interaction` and set its body:

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('is_admin')->default(false)->after('password');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('is_admin');
    });
}
```

- [ ] **Step 4: Model cast + factory state**

In `app/Models/User.php` `casts()`, add `'is_admin' => 'boolean',`. Do **not** add `is_admin` to `#[Fillable]`.

In `database/factories/UserFactory.php`, add after `unverified()`:

```php
    /**
     * Indicate that the user is a store admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
```

- [ ] **Step 5: Gate**

In `app/Providers/AppServiceProvider.php` add imports `use App\Models\User;` and `use Illuminate\Support\Facades\Gate;`, and in `boot()` after the Vite line:

```php
        Gate::define('access-admin', fn (User $user): bool => $user->is_admin);
```

- [ ] **Step 6: Grant command**

Append to `routes/console.php` (add `use App\Models\User;` at the top):

```php
Artisan::command('admin:grant {email}', function (string $email): int {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user with email {$email}.");

        return 1;
    }

    $user->forceFill(['is_admin' => true])->save();
    $this->info("{$email} is now an admin.");

    return 0;
})->purpose('Grant admin access to an existing user');
```

- [ ] **Step 7: Admin routes**

Create `routes/admin.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('/', 'Admin/Dashboard')->name('dashboard');
    });
```

In `routes/web.php`, add after `require __DIR__.'/auth.php';`:

```php
require __DIR__.'/admin.php';
```

- [ ] **Step 8: Admin layout**

Create `resources/js/Layouts/AdminLayout.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import IconMenu from '@/Components/Icons/IconMenu.vue';
import IconClose from '@/Components/Icons/IconClose.vue';

const isSidebarOpen = ref(false);

const links = [
    { label: 'Dashboard', route: 'admin.dashboard', match: 'admin.dashboard' },
];
</script>

<template>
    <div class="min-h-screen bg-gray-100 md:flex">
        <div class="flex items-center justify-between border-b bg-white px-4 py-3 md:hidden">
            <Link :href="route('admin.dashboard')" class="flex items-center gap-2 font-semibold">
                <ApplicationLogo class="h-8 w-auto" />
                Admin
            </Link>
            <button
                type="button"
                class="rounded p-2 text-gray-600 hover:bg-gray-100"
                :aria-label="isSidebarOpen ? 'Close menu' : 'Open menu'"
                :aria-expanded="isSidebarOpen"
                @click="isSidebarOpen = !isSidebarOpen"
            >
                <IconClose v-if="isSidebarOpen" class="h-6 w-6" />
                <IconMenu v-else class="h-6 w-6" />
            </button>
        </div>

        <aside
            :class="isSidebarOpen ? 'block' : 'hidden'"
            class="w-full border-r bg-white md:block md:min-h-screen md:w-60"
        >
            <div class="hidden px-6 py-5 md:block">
                <Link :href="route('admin.dashboard')" class="flex items-center gap-2 font-semibold">
                    <ApplicationLogo class="h-8 w-auto" />
                    Admin
                </Link>
            </div>
            <nav class="space-y-1 px-3 pb-4">
                <Link
                    v-for="link in links"
                    :key="link.route"
                    :href="route(link.route)"
                    :class="route().current(link.match) ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50'"
                    class="block rounded px-3 py-2 text-sm font-medium"
                >
                    {{ link.label }}
                </Link>
                <hr class="my-3" />
                <Link href="/" class="block rounded px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">View store</Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="block w-full rounded px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50"
                >
                    Log out
                </Link>
            </nav>
        </aside>

        <main class="flex-1 p-4 md:p-8">
            <header v-if="$slots.header" class="mb-6">
                <slot name="header" />
            </header>
            <p
                v-if="$page.props.flash?.status"
                class="mb-4 rounded bg-green-50 px-4 py-2 text-sm text-green-800"
                role="status"
            >
                {{ $page.props.flash.status }}
            </p>
            <slot />
        </main>
    </div>
</template>
```

- [ ] **Step 9: Icons used by the layout**

Create `resources/js/Components/Icons/IconMenu.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
    </svg>
</template>
```

Create `resources/js/Components/Icons/IconClose.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
    </svg>
</template>
```

- [ ] **Step 10: Admin dashboard page**

Create `resources/js/Pages/Admin/Dashboard.vue`:

```vue
<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
</script>

<template>
    <Head title="Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
        </template>

        <p class="text-sm text-gray-600">Orders and stock overview arrive in Phase 5.</p>
    </AdminLayout>
</template>
```

- [ ] **Step 11: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/AdminAccessTest.php`
Expected: 6 PASS.

- [ ] **Step 12: Pint, full suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: admin access gate, admin:grant command, admin shell"
```

---

### Task 4: Store settings

**Files:**
- Create: `database/migrations/<timestamp>_create_settings_table.php`, `app/Models/Setting.php`
- Create: `app/Http/Controllers/Admin/SettingController.php`, `app/Http/Requests/Admin/UpdateSettingsRequest.php`
- Create: `resources/js/Pages/Admin/Settings/Edit.vue`
- Modify: `routes/admin.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/Layouts/AdminLayout.vue` (nav link)
- Test: `tests/Feature/Admin/SettingsTest.php`

**Interfaces:**
- Consumes: `access-admin` Gate, `admin.` route group, `AdminLayout.vue`, `UserFactory::admin()`.
- Produces: `Setting::values(): array<string, ?string>` (defaults merged with stored values, cached under key `settings`); `Setting::DEFAULTS` keys `store_name`, `contact_phone`, `whatsapp_number`, `contact_email`, `cod_enabled` (`'1'`/`'0'`); shared Inertia props `store` (`store_name`, `contact_phone`, `whatsapp_number`, `contact_email`) and `flash.status`; routes `admin.settings.edit` (`GET /admin/settings`), `admin.settings.update` (`PUT /admin/settings`).

- [ ] **Step 1: Write the failing tests**

Run: `php artisan make:test --pest Admin/SettingsTest --no-interaction` then replace its contents:

```php
<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('settings page shows defaults when nothing is stored', function () {
    $this->actingAs($this->admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Edit')
            ->where('settings.store_name', 'Arusa')
            ->where('settings.cod_enabled', '1'));
});

test('admin can update settings and they are shared to pages', function () {
    $this->actingAs($this->admin)
        ->put('/admin/settings', [
            'store_name' => 'Arusa Aizawl',
            'contact_phone' => '9862000000',
            'whatsapp_number' => '9862000001',
            'contact_email' => 'hello@arusa.co.in',
            'cod_enabled' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Settings saved.');

    expect(Setting::values())
        ->store_name->toBe('Arusa Aizawl')
        ->cod_enabled->toBe('0');

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('store.store_name', 'Arusa Aizawl')
            ->where('store.whatsapp_number', '9862000001'));
});

test('settings reject invalid phone numbers and email', function () {
    $this->actingAs($this->admin)
        ->put('/admin/settings', [
            'store_name' => 'Arusa',
            'contact_phone' => '12345',
            'whatsapp_number' => '+919862000000',
            'contact_email' => 'not-an-email',
            'cod_enabled' => true,
        ])
        ->assertSessionHasErrors(['contact_phone', 'whatsapp_number', 'contact_email']);
});

test('non-admins cannot update settings', function () {
    $this->actingAs(User::factory()->create())
        ->put('/admin/settings', ['store_name' => 'Hacked', 'cod_enabled' => true])
        ->assertForbidden();

    expect(Setting::values()['store_name'])->toBe('Arusa');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/SettingsTest.php`
Expected: FAIL (route/model missing).

- [ ] **Step 3: Migration + model**

Run: `php artisan make:model Setting -m --no-interaction`. Migration `up()` body:

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->timestamps();
});
```

Replace `app/Models/Setting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Values used until the admin saves their own.
     *
     * @var array{store_name: string, contact_phone: ?string, whatsapp_number: ?string, contact_email: ?string, cod_enabled: string}
     */
    public const DEFAULTS = [
        'store_name' => 'Arusa',
        'contact_phone' => null,
        'whatsapp_number' => null,
        'contact_email' => null,
        'cod_enabled' => '1',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings'));
    }

    /**
     * All settings, defaults merged with stored values.
     *
     * @return array<string, ?string>
     */
    public static function values(): array
    {
        return array_merge(
            self::DEFAULTS,
            Cache::rememberForever('settings', fn (): array => static::pluck('value', 'key')->all()),
        );
    }
}
```

- [ ] **Step 4: Form request**

Run: `php artisan make:request Admin/UpdateSettingsRequest --no-interaction` and replace:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'store_name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'whatsapp_number' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'cod_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Enter a 10-digit mobile number without +91.',
            'whatsapp_number.regex' => 'Enter a 10-digit mobile number without +91.',
        ];
    }
}
```

- [ ] **Step 5: Controller + routes**

Run: `php artisan make:controller Admin/SettingController --no-interaction` and replace:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/Edit', [
            'settings' => Setting::values(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $values = [
            ...$request->safe()->except('cod_enabled'),
            'cod_enabled' => $request->boolean('cod_enabled') ? '1' : '0',
        ];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('status', 'Settings saved.');
    }
}
```

In `routes/admin.php` add `use App\Http\Controllers\Admin\SettingController;` and inside the group:

```php
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
```

- [ ] **Step 6: Share store + flash props**

In `app/Http/Middleware/HandleInertiaRequests.php` add `use App\Models\Setting;` and `use Illuminate\Support\Arr;`, and extend `share()`:

```php
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'store' => fn (): array => Arr::only(Setting::values(), [
                'store_name', 'contact_phone', 'whatsapp_number', 'contact_email',
            ]),
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
        ];
```

- [ ] **Step 7: Settings page**

Create `resources/js/Pages/Admin/Settings/Edit.vue`:

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

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

const fields = [
    { key: 'store_name', label: 'Store name', type: 'text' },
    { key: 'contact_phone', label: 'Contact phone (10 digits)', type: 'tel' },
    { key: 'whatsapp_number', label: 'WhatsApp number (10 digits)', type: 'tel' },
    { key: 'contact_email', label: 'Contact email', type: 'email' },
];
</script>

<template>
    <Head title="Settings" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Settings</h1>
        </template>

        <form class="max-w-xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="form.put(route('admin.settings.update'))">
            <div v-for="field in fields" :key="field.key">
                <InputLabel :for="field.key" :value="field.label" />
                <TextInput :id="field.key" v-model="form[field.key]" :type="field.type" class="mt-1 block w-full" />
                <InputError class="mt-2" :message="form.errors[field.key]" />
            </div>

            <label class="flex items-center gap-2">
                <Checkbox v-model:checked="form.cod_enabled" />
                <span class="text-sm text-gray-700">Cash on delivery enabled</span>
            </label>

            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        </form>
    </AdminLayout>
</template>
```

Check `resources/js/Components/Checkbox.vue` for its v-model prop name (`checked` in Breeze); adjust the binding if it differs.

In `AdminLayout.vue` `links`, add: `{ label: 'Settings', route: 'admin.settings.edit', match: 'admin.settings.*' },`

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/SettingsTest.php`
Expected: 4 PASS.

- [ ] **Step 9: Pint, full suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: cached store settings with admin editor and shared props"
```

---

### Task 5: Content pages (storefront + admin CRUD)

**Files:**
- Create: `database/migrations/<timestamp>_create_pages_table.php`, `app/Models/Page.php`, `database/factories/PageFactory.php`, `database/seeders/PageSeeder.php`
- Create: `app/Http/Controllers/PageController.php`, `app/Http/Controllers/Admin/PageController.php`, `app/Http/Requests/Admin/PageRequest.php`
- Create: `resources/js/Pages/Content/Show.vue`, `resources/js/Pages/Admin/Pages/Index.vue`, `Create.vue`, `Edit.vue`, `Partials/PageForm.vue`
- Modify: `routes/web.php`, `routes/admin.php`, `database/seeders/DatabaseSeeder.php`, `AdminLayout.vue` (nav link)
- Test: `tests/Feature/ContentPageTest.php`, `tests/Feature/Admin/PageManagementTest.php`

**Interfaces:**
- Consumes: admin route group, `AdminLayout.vue`, `UserFactory::admin()`.
- Produces: `Page` model (`slug`, `title`, `body`); route `pages.show` (`GET /pages/{page:slug}`) rendering `Content/Show` with props `title`, `html`; admin resource routes `admin.pages.*` (except `show`); seeded slugs `about`, `contact`, `size-guide`, `shipping-returns`. Task 6 footer links to these slugs. `Content/Show.vue` initially uses `GuestLayout`; Task 6 switches it to `StoreLayout`.

- [ ] **Step 1: Write the failing storefront tests**

Run: `php artisan make:test --pest ContentPageTest --no-interaction` and replace:

```php
<?php

use App\Models\Page;

test('a content page renders its markdown as html', function () {
    Page::factory()->create(['slug' => 'about', 'title' => 'About Arusa', 'body' => "# Hello\n\nMade in **Aizawl**."]);

    $this->get('/pages/about')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Content/Show')
            ->where('title', 'About Arusa')
            ->where('html', fn (string $html) => str_contains($html, '<strong>Aizawl</strong>')));
});

test('raw html and unsafe links in page bodies are stripped', function () {
    Page::factory()->create(['slug' => 'x', 'body' => "<script>alert(1)</script>\n\n[click](javascript:alert(1))"]);

    $this->get('/pages/x')
        ->assertInertia(fn ($page) => $page
            ->where('html', fn (string $html) => ! str_contains($html, '<script') && ! str_contains($html, 'javascript:')));
});

test('unknown content pages return 404', function () {
    $this->get('/pages/missing')->assertNotFound();
});
```

- [ ] **Step 2: Write the failing admin tests**

Run: `php artisan make:test --pest Admin/PageManagementTest --no-interaction` and replace:

```php
<?php

use App\Models\Page;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
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
        ->post('/admin/pages', ['title' => 'FAQ', 'slug' => 'faq', 'body' => 'Questions'])
        ->assertRedirect('/admin/pages');

    expect(Page::where('slug', 'faq')->exists())->toBeTrue();
});

test('admin can update a page keeping its own slug', function () {
    $page = Page::factory()->create(['slug' => 'about']);

    $this->actingAs($this->admin)
        ->put("/admin/pages/{$page->id}", ['title' => 'About us', 'slug' => 'about', 'body' => 'New body'])
        ->assertRedirect('/admin/pages');

    expect($page->fresh()->title)->toBe('About us');
});

test('slugs must be unique and url safe', function () {
    Page::factory()->create(['slug' => 'about']);

    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Dup', 'slug' => 'about', 'body' => 'x'])
        ->assertSessionHasErrors('slug');

    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Bad', 'slug' => 'has spaces/slash', 'body' => 'x'])
        ->assertSessionHasErrors('slug');
});

test('admin can delete a page', function () {
    $page = Page::factory()->create();

    $this->actingAs($this->admin)->delete("/admin/pages/{$page->id}")->assertRedirect('/admin/pages');

    expect(Page::count())->toBe(0);
});

test('non-admins cannot manage pages', function () {
    $this->actingAs(User::factory()->create())
        ->post('/admin/pages', ['title' => 'X', 'slug' => 'x', 'body' => 'x'])
        ->assertForbidden();
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/ContentPageTest.php tests/Feature/Admin/PageManagementTest.php`
Expected: FAIL (model/routes missing).

- [ ] **Step 4: Model, migration, factory, seeder**

Run: `php artisan make:model Page -mf --no-interaction`. Migration `up()` body:

```php
Schema::create('pages', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('title');
    $table->text('body');
    $table->timestamps();
});
```

Replace `app/Models/Page.php`:

```php
<?php

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['slug', 'title', 'body'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /**
     * Markdown body rendered to HTML with raw HTML and unsafe links removed.
     */
    public function renderedBody(): string
    {
        return (string) Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
```

`database/factories/PageFactory.php` `definition()`:

```php
        return [
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraphs(2, true),
        ];
```

Run: `php artisan make:seeder PageSeeder --no-interaction` and set `run()`:

```php
    public function run(): void
    {
        $pages = [
            'about' => ['About Arusa', "Arusa is an apparel and lifestyle store from Aizawl, Mizoram.\n\nEdit this page in the admin."],
            'contact' => ['Contact', 'Reach us by phone or WhatsApp. Edit this page in the admin.'],
            'size-guide' => ['Size Guide', "| Size | Chest (in) |\n|---|---|\n| S | 36 |\n| M | 38 |\n| L | 40 |\n| XL | 42 |\n\nEdit this page in the admin."],
            'shipping-returns' => ['Shipping & Returns', "We deliver across Mizoram.\n\nEdit this page in the admin."],
        ];

        foreach ($pages as $slug => [$title, $body]) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body]);
        }
    }
```

(Add `use App\Models\Page;`.) In `DatabaseSeeder::run()` append `$this->call(PageSeeder::class);`.

Note: the size-guide table needs CommonMark's table extension, which `Str::markdown` does not enable by default. If the table renders as plain text, pass `extensions: [new \League\CommonMark\Extension\Table\TableExtension]` as the third argument of `Str::markdown` in `renderedBody()` (league/commonmark ships with Laravel; no new dependency).

- [ ] **Step 5: Storefront route + controller**

Run: `php artisan make:controller PageController --no-interaction` and replace:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(Page $page): Response
    {
        return Inertia::render('Content/Show', [
            'title' => $page->title,
            'html' => $page->renderedBody(),
        ]);
    }
}
```

In `routes/web.php` add `use App\Http\Controllers\PageController;` and:

```php
Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('pages.show');
```

Create `resources/js/Pages/Content/Show.vue`:

```vue
<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    title: { type: String, required: true },
    html: { type: String, required: true },
});
</script>

<template>
    <Head :title="title" />

    <GuestLayout>
        <article class="content-prose">
            <h1>{{ title }}</h1>
            <!-- html is server-rendered Markdown with raw HTML stripped -->
            <div v-html="html" />
        </article>
    </GuestLayout>
</template>
```

Add to `resources/css/app.css` (after the Tailwind directives) a minimal prose style, since `@tailwindcss/typography` is not installed:

```css
.content-prose h1 { @apply mb-4 text-2xl font-semibold; }
.content-prose h2 { @apply mb-2 mt-6 text-xl font-semibold; }
.content-prose p { @apply mb-4 leading-relaxed; }
.content-prose ul { @apply mb-4 list-disc pl-6; }
.content-prose a { @apply underline; }
.content-prose table { @apply mb-4 w-full text-left text-sm; }
.content-prose th, .content-prose td { @apply border-b py-2 pr-4; }
```

- [ ] **Step 6: Admin request + controller + routes**

Run: `php artisan make:request Admin/PageRequest --no-interaction` and replace:

```php
<?php

namespace App\Http\Requests\Admin;

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
            'body' => ['required', 'string', 'max:50000'],
        ];
    }
}
```

Run: `php artisan make:controller Admin/PageController --model=Page --no-interaction` and replace:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages' => Page::orderBy('title')->get(['id', 'slug', 'title', 'updated_at']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Pages/Create');
    }

    public function store(PageRequest $request): RedirectResponse
    {
        Page::create($request->validated());

        return to_route('admin.pages.index')->with('status', 'Page created.');
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Edit', [
            'page' => $page->only('id', 'slug', 'title', 'body'),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $page->update($request->validated());

        return to_route('admin.pages.index')->with('status', 'Page saved.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return to_route('admin.pages.index')->with('status', 'Page deleted.');
    }
}
```

In `routes/admin.php` add `use App\Http\Controllers\Admin\PageController;` and inside the group:

```php
        Route::resource('pages', PageController::class)->except('show');
```

In `AdminLayout.vue` `links`, insert before Settings: `{ label: 'Pages', route: 'admin.pages.index', match: 'admin.pages.*' },`

- [ ] **Step 7: Admin page screens**

Create `resources/js/Pages/Admin/Pages/Partials/PageForm.vue`:

```vue
<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    form: { type: Object, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form class="max-w-3xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="$emit('submit')">
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
        <div>
            <InputLabel for="body" value="Content (Markdown)" />
            <textarea
                id="body"
                v-model="form.body"
                rows="16"
                class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
            />
            <InputError class="mt-2" :message="form.errors.body" />
        </div>
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
```

Create `resources/js/Pages/Admin/Pages/Create.vue`:

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageForm from './Partials/PageForm.vue';

const form = useForm({ title: '', slug: '', body: '' });
</script>

<template>
    <Head title="New page" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">New page</h1>
        </template>

        <PageForm :form="form" @submit="form.post(route('admin.pages.store'))" />
    </AdminLayout>
</template>
```

Create `resources/js/Pages/Admin/Pages/Edit.vue`:

```vue
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageForm from './Partials/PageForm.vue';

const props = defineProps({
    page: { type: Object, required: true },
});

const form = useForm({ title: props.page.title, slug: props.page.slug, body: props.page.body });
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

        <PageForm :form="form" @submit="form.put(route('admin.pages.update', page.id))" />
    </AdminLayout>
</template>
```

Create `resources/js/Pages/Admin/Pages/Index.vue`:

```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Pages</h1>
                <Link :href="route('admin.pages.create')" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">New page</Link>
            </div>
        </template>

        <div class="overflow-x-auto rounded bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">URL</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="page in pages" :key="page.id" class="border-b last:border-0">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.pages.edit', page.id)" class="font-medium text-gray-900 hover:underline">{{ page.title }}</Link>
                        </td>
                        <td class="px-4 py-3 text-gray-500">/pages/{{ page.slug }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="text-red-600 hover:underline" @click="deletePage(page)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="pages.length === 0">
                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">No pages yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/ContentPageTest.php tests/Feature/Admin/PageManagementTest.php`
Expected: 9 PASS.

- [ ] **Step 9: Seed locally and eyeball**

Run: `php artisan migrate && php artisan db:seed --class=PageSeeder`. Visit `/pages/size-guide` and confirm the table renders (apply the TableExtension note from Step 4 if not).

- [ ] **Step 10: Pint, full suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: markdown content pages with admin CRUD"
```

---

### Task 6: Brand and storefront shell (design-skill driven)

**Files:**
- Modify: `tailwind.config.js`, `resources/views/app.blade.php` (fonts), `resources/js/Components/ApplicationLogo.vue`, `resources/js/Layouts/GuestLayout.vue`, `resources/js/Pages/Content/Show.vue`, `routes/web.php`, `resources/js/app.js` (progress colour)
- Create: `resources/js/Components/Icons/IconUser.vue`, `IconChat.vue`; `resources/js/Layouts/StoreLayout.vue`; `resources/js/Pages/Home.vue`
- Delete: `resources/js/Pages/Welcome.vue`
- Test: `tests/Feature/HomePageTest.php`

**Interfaces:**
- Consumes: shared props `store.*`, `auth.user.is_admin`; route `pages.show` with slugs `about`, `contact`, `size-guide`, `shipping-returns`; icons `IconMenu`, `IconClose`.
- Produces: Tailwind colour tokens `brand-{50..900}` and `ink`, font family `display` + `sans`; `StoreLayout.vue` (default slot) used by every storefront page from Phase 2 on; route `home` (`GET /`) rendering `Home`.

**Blocked until** the logo exists at `resources/images/logo.svg` or `.png`.

- [ ] **Step 1: Write the failing test**

Run: `php artisan make:test --pest HomePageTest --no-interaction` and replace:

```php
<?php

test('home page renders the storefront home', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('store.store_name', 'Arusa'));
});
```

Run: `php artisan test --compact tests/Feature/HomePageTest.php`
Expected: FAIL (component is `Welcome`).

- [ ] **Step 2: Derive the design system (ui-ux-pro-max)**

Invoke the `ui-ux-pro-max` skill with: "Apparel + lifestyle e-commerce, single brand from Aizawl, Mizoram; mobile-first, low-bandwidth audience; brand must derive from the logo at `resources/images/logo.*`." Look at the logo first. Record the chosen palette (one brand scale 50–900 + an ink/text colour), heading and body fonts (must be on Google Fonts or Bunny Fonts, max 2 families, max 3 weights total), radius and spacing scale. If the skill writes a design-system file, keep it under `docs/design/` and commit it.

- [ ] **Step 3: Brand tokens**

Update `tailwind.config.js` `theme.extend` with the values from Step 2 (shape shown; replace hex values and font names with the Step 2 output):

```js
    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#f5f7f6', 100: '#e6ebe8', 200: '#cbd6d0', 300: '#a3b8ad',
                    400: '#76937f', 500: '#577665', 600: '#445e50', 700: '#384c42',
                    800: '#2f3e37', 900: '#28342f',
                },
                ink: '#1a1a1a',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
```

Update the font `<link>` in `resources/views/app.blade.php` to load exactly the Step 2 families/weights with `display=swap`. Set `progress.color` in `resources/js/app.js` to the brand-600 hex.

- [ ] **Step 4: Real logo**

Replace `resources/js/Components/ApplicationLogo.vue` (use `logo.png` in the import if no SVG exists):

```vue
<script setup>
import logoUrl from '../../images/logo.svg';
</script>

<template>
    <img :src="logoUrl" alt="Arusa" />
</template>
```

In `GuestLayout.vue`, change the logo classes from `h-20 w-20 fill-current text-gray-500` to `h-16 w-auto`.

- [ ] **Step 5: Remaining icons**

Create `resources/js/Components/Icons/IconUser.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
    </svg>
</template>
```

Create `resources/js/Components/Icons/IconChat.vue`:

```vue
<template>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z" />
    </svg>
</template>
```

- [ ] **Step 6: Storefront layout (functional baseline)**

Create `resources/js/Layouts/StoreLayout.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import IconMenu from '@/Components/Icons/IconMenu.vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import IconUser from '@/Components/Icons/IconUser.vue';
import IconChat from '@/Components/Icons/IconChat.vue';

const page = usePage();
const isMenuOpen = ref(false);

const infoLinks = [
    { label: 'About', slug: 'about' },
    { label: 'Size Guide', slug: 'size-guide' },
    { label: 'Shipping & Returns', slug: 'shipping-returns' },
    { label: 'Contact', slug: 'contact' },
];
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white font-sans text-ink">
        <header class="sticky top-0 z-30 border-b border-brand-100 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
                <button
                    type="button"
                    class="-ml-2 rounded p-2 md:hidden"
                    :aria-label="isMenuOpen ? 'Close menu' : 'Open menu'"
                    :aria-expanded="isMenuOpen"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <IconClose v-if="isMenuOpen" class="h-6 w-6" />
                    <IconMenu v-else class="h-6 w-6" />
                </button>

                <Link :href="route('home')" class="flex items-center">
                    <ApplicationLogo class="h-9 w-auto" />
                </Link>

                <nav class="hidden gap-6 text-sm md:flex">
                    <Link v-for="link in infoLinks" :key="link.slug" :href="route('pages.show', link.slug)" class="hover:text-brand-600">
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="page.props.auth.user?.is_admin"
                        :href="route('admin.dashboard')"
                        class="hidden text-sm text-brand-700 underline md:inline"
                    >
                        Admin
                    </Link>
                    <Link
                        :href="page.props.auth.user ? route('dashboard') : route('login')"
                        class="rounded p-2"
                        :aria-label="page.props.auth.user ? 'My account' : 'Log in'"
                    >
                        <IconUser class="h-6 w-6" />
                    </Link>
                </div>
            </div>

            <Transition
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="-translate-y-2 opacity-0"
                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <nav v-if="isMenuOpen" class="border-t border-brand-100 px-4 py-3 md:hidden">
                    <Link
                        v-for="link in infoLinks"
                        :key="link.slug"
                        :href="route('pages.show', link.slug)"
                        class="block py-2"
                        @click="isMenuOpen = false"
                    >
                        {{ link.label }}
                    </Link>
                </nav>
            </Transition>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-brand-100 bg-brand-50">
            <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 text-sm sm:grid-cols-2">
                <div>
                    <p class="font-display text-base font-semibold">{{ page.props.store.store_name }}</p>
                    <p class="mt-1 text-brand-700">Aizawl, Mizoram</p>
                    <p v-if="page.props.store.contact_phone" class="mt-2">
                        <a :href="`tel:+91${page.props.store.contact_phone}`">+91 {{ page.props.store.contact_phone }}</a>
                    </p>
                    <p v-if="page.props.store.contact_email">
                        <a :href="`mailto:${page.props.store.contact_email}`">{{ page.props.store.contact_email }}</a>
                    </p>
                    <a
                        v-if="page.props.store.whatsapp_number"
                        :href="`https://wa.me/91${page.props.store.whatsapp_number}`"
                        target="_blank"
                        rel="noopener"
                        class="mt-3 inline-flex items-center gap-2 rounded-full bg-brand-600 px-4 py-2 text-white"
                    >
                        <IconChat class="h-5 w-5" />
                        Chat on WhatsApp
                    </a>
                </div>
                <nav class="flex flex-col gap-2">
                    <Link v-for="link in infoLinks" :key="link.slug" :href="route('pages.show', link.slug)" class="hover:underline">
                        {{ link.label }}
                    </Link>
                </nav>
            </div>
        </footer>
    </div>
</template>
```

- [ ] **Step 7: Home page + route; retire Welcome**

Create `resources/js/Pages/Home.vue`:

```vue
<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

const store = usePage().props.store;
</script>

<template>
    <Head title="Home" />

    <StoreLayout>
        <section class="mx-auto max-w-6xl px-4 py-16 sm:py-24">
            <p class="text-sm uppercase tracking-widest text-brand-600">Aizawl, Mizoram</p>
            <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">{{ store.store_name }}</h1>
            <p class="mt-4 max-w-xl text-lg text-brand-800">Apparel and everyday goods, delivered across Mizoram.</p>
            <p class="mt-8 text-sm text-brand-700">Our collection is coming soon.</p>
        </section>
    </StoreLayout>
</template>
```

In `routes/web.php`, replace the whole `Route::get('/', ...)` Welcome closure with:

```php
Route::inertia('/', 'Home')->name('home');
```

Remove the now-unused `use Illuminate\Foundation\Application;` import. Delete `resources/js/Pages/Welcome.vue`.

In `resources/js/Pages/Content/Show.vue`, replace `GuestLayout` with `StoreLayout` and wrap the article: `<article class="content-prose mx-auto max-w-3xl px-4 py-10">`.

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/HomePageTest.php tests/Feature/ContentPageTest.php tests/Feature/ExampleTest.php`
Expected: all PASS.

- [ ] **Step 9: Design pass (ui-ux-pro-max + design-motion-principles)**

With `npm run dev` running, invoke `ui-ux-pro-max` to refine `StoreLayout.vue`, `Home.vue`, and `Content/Show.vue` against the Step 2 system (spacing, type scale, hierarchy, touch targets ≥ 44 px, contrast ≥ 4.5:1). Then invoke `design-motion-principles` to review the mobile-menu transition and any hover states. Constraints: Global Constraints above; keep the component structure, props, routes and the `store`/`auth` prop usage unchanged so tests stay green. Check at 375 px and 1280 px widths (Playwright MCP screenshots).

- [ ] **Step 10: Build size check**

Run: `npm run build`
Expected: build succeeds; the Home page's JS chunks plus `app` chunk total < 200 KB uncompressed in the build output. If over, report it.

- [ ] **Step 11: Pint, full suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: brand tokens, storefront layout and home page"
```

---

## Phase 1 done when

- `php artisan test --compact` is green.
- `php artisan admin:grant <email>` makes the owner an admin; `/admin`, `/admin/pages`, `/admin/settings` work on mobile.
- `/`, `/pages/about`, `/pages/size-guide`, `/pages/shipping-returns`, `/pages/contact` render in the branded `StoreLayout` with footer contact details from settings.
- Project skills `ui-ux-pro-max`, `design-motion-principles`, `arusa-commerce` and the `playwright` MCP load in this repo.

## Known follow-ups (not Phase 1)

- Tests run on SQLite; Phase 3 (`lockForUpdate`) and Phase 2 (Postgres full-text search) need a Postgres test database. Decide in the Phase 2 plan.
- `/dashboard` (Breeze) is still the customer landing page after login; Phase 3 replaces it with an account page.
