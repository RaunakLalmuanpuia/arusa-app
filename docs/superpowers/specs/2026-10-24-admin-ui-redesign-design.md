# Admin UI Redesign — Design Spec

- **Date:** 2026-10-24
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Builds on:** `2026-10-23-arusa-ecommerce-design.md` (admin screens), `2026-10-24-page-blocks-design.md`
- **Supersedes:** the `arusa-commerce` skill rule "Admin: plain and functional, no design-skill passes"

## 1. Goal

Make every admin screen a modern, brand-tied workspace (calm, dense, warm) and drop Breeze frontend components from the admin.

### Pages in scope
`AdminLayout` and every page under `resources/js/Pages/Admin/**`: Dashboard, Orders (Index, Show), Categories, Products (incl. `VariantGrid`, image uploader), Shipping zones, Pages (incl. all `Blocks/*Fields.vue`), Settings. Plus `Components/Admin/AdminTable.vue` and `Components/Admin/ImageUploader.vue`.

### Decisions
- **Direction:** brand-tied. `brand-50` page background, white cards with a `brand-200` hairline border (no shadows), `ink` text, `brand-500/600` secondary text. Inter for all UI; Playfair (`font-display`) only for the dashboard greeting.
- **No Breeze in admin.** No admin file imports `TextInput`, `InputLabel`, `InputError`, `PrimaryButton`, `Checkbox` or `ApplicationLogo`. The Breeze files stay: storefront and auth pages still use them.
- **No new packages.** Icons are inline SVG components in `Components/Icons/`; the chart is hand-written SVG.
- **Motion:** CSS transitions or Vue `<Transition>`, 150–200 ms, opacity/transform only, off under `prefers-reduced-motion`. Drawer slide, toast fade, save bar slide-up only.
- **Behaviour is unchanged** except the four UX upgrades in §4. Form fields, names, validation, routes and order logic stay as they are.

### Out of scope
Storefront and auth pages, deleting Breeze files, dark mode, command palette, bulk actions, new admin features.

## 2. Style primitives (`resources/css/app.css`, `@layer components`)

Follows the existing `.store-*` convention.

| Class | Use |
|---|---|
| `.admin-card` | `rounded-xl border border-brand-200 bg-white` |
| `.admin-label` | `block text-sm font-medium text-ink` |
| `.admin-field` | inputs, selects, textareas: `block w-full rounded-lg border-brand-300 bg-white text-sm text-ink placeholder:text-brand-400 focus:border-ink focus:ring-ink disabled:bg-brand-50` |
| `.admin-checkbox` | `rounded border-brand-300 text-ink focus:ring-ink` |
| `.admin-btn` | primary: `inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-ink px-4 text-sm font-medium text-brand-50 hover:bg-brand-800 disabled:opacity-50 transition-colors duration-150 focus-ring` |
| `.admin-btn-secondary` | same shape, `border border-brand-300 bg-white text-ink hover:bg-brand-50` |
| `.admin-btn-danger` | same shape, `text-red-700 hover:bg-red-50` |

`Pages/Admin/Pages/Blocks/fieldOptions.js`: `fieldClass`, `smallButton` and `removeButton` become the new classes (`'admin-field'`, `'admin-btn-secondary min-h-8 px-3'`, `'admin-btn-danger min-h-8 px-2'`) so the block editors pick them up unchanged.

## 3. Components (`resources/js/Components/Admin/`)

### `FormField.vue`
- Props: `label: string` (required), `for: string` (required), `error: string|null`, `hint: string|null`.
- Renders the `.admin-label`, a default slot for the control, the hint (`text-xs text-brand-500`), and the error (`text-sm text-red-700`, `id="{for}-error"`). Replaces every `InputLabel` + `InputError` pair. `Pages/Blocks/Field.vue` uses it internally.

### `PageHeader.vue`
- Props: `title: string` (required), `back: {label, href}|null`, `meta: string|null`.
- Slots: `actions` (right side), `badges` (after the title).
- The back link is rendered as `← Orders` above the title, `h1` is `text-2xl font-semibold tracking-tight`. Replaces each page's hand-written `#header` content.

### `StatusBadge.vue`
- Props: `value: string` (required, the enum value), `label: string` (required).
- Tone is keyed by **value**, never label:
  - amber: `pending`, `awaiting_payment`
  - sky: `confirmed`, `packed`
  - violet: `dispatched`
  - emerald: `delivered`, `paid`
  - red: `cancelled`, `failed`
  - neutral (`brand-100`/`brand-700`): `refunded`, and any unknown value
- Pill: `inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium` with a 6 px dot.

### `Toasts.vue`
- Mounted once in `AdminLayout`. Watches `$page.props.flash.status` (success tone, `role="status"`) and the top-level page errors that are not field errors (`status`, `order`, `payment` keys on Order Show and the Orders index; error tone, `role="alert"`).
- Stacks bottom-right (bottom-centre on mobile, above the save bar). Auto-dismisses after 5 s, has a close button, and pauses on hover/focus. Fade + 8 px rise, 200 ms.
- Replaces the inline green flash `<p>` in `AdminLayout` and the red error `<p>`s on Orders Index/Show. The COD "call to confirm" notice on Order Show stays inline (it is guidance, not a toast).

### `SaveBar.vue`
- Props: `form: Object` (Inertia `useForm`), `label: string` (default `'Save'`).
- Emits `discard`.
- Sticky at the bottom of the main column (`sticky bottom-0`, white, top border). Left: "Unsaved changes" when `form.isDirty`, otherwise nothing. Right: Discard (`.admin-btn-secondary`, shown only when dirty; parent calls `form.reset()`), Save (`.admin-btn`, `type="submit"`, disabled while `form.processing`).
- Used inside the `<form>` of `ProductForm`, `CategoryForm`, `ShippingZoneForm`, `PageForm`, Settings, and the `VariantGrid` save. Replaces each form's `PrimaryButton`.

### `BarChart.vue`
- Props: `points: Array<{date: string, label: string, value: number}>` (value in paise), `format: Function` (e.g. `formatPaise`).
- Inline SVG, `viewBox` sized and scaled responsively, bars in `brand-700`, with a hairline baseline and 3 y-axis gridlines (0, half, max). The x-axis labels every 7th day.
- Each bar is focusable (`tabindex="0"`), and hovering or focusing it shows a tooltip (date + formatted amount). A visually hidden `<table>` lists the same data for screen readers; the `<svg>` has `role="img"` and an `aria-label` summary.
- When every value is zero: show a muted "No payments received in the last 30 days." in place of the bars.

### `AdminTable.vue` (existing, reworked)
- Same props and slots, so no index page changes its usage.
- `md` and up: table inside `.admin-card`, sticky header row in `brand-50`, row hover `bg-brand-50/60`.
- Below `md`: each row becomes a stacked card listing `column.label: cell` pairs, using the same `cell-*` slots. The first column is the card title.
- Filters: the search box is always visible; the `filters` slot and the Filter button sit behind a "Filters" toggle button below `md` (with `aria-expanded`), and are inline above it.
- Pagination: secondary buttons; the count text stays.
- Empty state: centred icon + slot text.

### Icons (`resources/js/Components/Icons/`)
New, matching the existing icon components' style (stroke, `currentColor`, 24 viewBox): `IconHome`, `IconTag` (categories), `IconBox` (products), `IconTruck` (shipping zones), `IconDocument` (pages), `IconCog` (settings), `IconExternal` (view store), `IconLogout`, `IconCheck`, `IconAlert`. Reuse the existing `IconBag` for orders and `IconMenu`/`IconClose` for the drawer.

## 4. Layout and pages

### `AdminLayout.vue`
- `md` and up: fixed 248 px sidebar, `brand-50` background with a right `brand-200` border. Top: the logo `<img>` (same `logo-256/512.jpg` imports and `mix-blend-multiply` trick as `ApplicationLogo`, inlined) plus "Arusa" and a small "Admin" caption. Nav: icon + label, `rounded-lg px-3 py-2`, active = `bg-white text-ink border border-brand-200`, inactive = `text-brand-600 hover:bg-white/60`. Bottom: View store (new tab) and Log out.
- Below `md`: a top bar with the logo and a menu button; the sidebar becomes a slide-over drawer (translate-x, 200 ms) with a `bg-ink/30` backdrop. It closes on backdrop click, Esc and navigation, moves focus to the first nav link on open, returns it to the menu button on close, and has `role="dialog"` + `aria-modal="true"`. No focus-trap library.
- Main: `max-w-6xl` content, `px-4 py-6 md:px-8 md:py-8`. The `#header` slot remains (pages put `PageHeader` in it).

### Dashboard
- Greeting header: `font-display` "Good morning/afternoon/evening" (client time) + today's date.
- **Needs action**: five compact `.admin-card` link tiles (count + label + chevron); "Refund owed" with a count > 0 gets a red left accent. Zero-count tiles are muted.
- **KPIs**: Orders today, Received in the last 7 days, Received in the last 30 days.
- **Revenue chart**: `.admin-card` titled "Received, last 30 days" with `BarChart` over `revenue.daily`.
- Today's orders (with `StatusBadge`) and Low stock (stock 0 in red, 1–3 in amber), side by side on `lg`.

### Orders
- **Index:** `number` links in bold, `customer` with the phone muted, `total` tabular-nums right-aligned, `payment` shows method text + payment `StatusBadge`, `status` is a fulfilment `StatusBadge`.
- **Show:**
  - `PageHeader`: back to Orders, title = number, badges = fulfilment + payment `StatusBadge`, meta = placed at · payment method, actions = Print slip, Message customer (secondary buttons with icons).
  - **Progress stepper** under the header: Pending → Confirmed → Packed → Dispatched → Delivered, with done steps filled, the current step ringed, and later steps muted. Cancelled orders show a single red "Cancelled" banner instead of the stepper.
  - **Next action card** (only when `next_status || can_cancel`): one-line explanation, tracking-note field when dispatching, primary button, cancel as a danger button. The `window.confirm` calls stay as they are.
  - Two-column `lg` grid: Items + totals (left, `col-span-2`), Customer + address + tracking (right).
  - Payment attempts table with a payment `StatusBadge` per row.
  - **Timeline**: vertical list with a dot + connecting line per event, showing the fulfilment and payment badges, the time, and "by {name}". Newest first, as the current data order provides.

### Categories, Products, Shipping zones, Pages (index + forms)
- Indexes: `PageHeader` with a "New …" primary action, then `AdminTable`. Boolean columns (`is_active` etc.) render as a small "Visible" (emerald) / "Hidden" (neutral) pill written in the cell slot, not via `StatusBadge`.
- Forms: wrap in `.admin-card` sections with a heading and description per group (e.g. Product: "Details", "Pricing", "Visibility"). Every field uses `FormField` + `.admin-field`. Checkboxes use `.admin-checkbox`. `SaveBar` at the bottom.
- `VariantGrid` and `ImageUploader`: restyle only (cards, `.admin-field`, `.admin-btn-*`). Drag/upload behaviour is untouched.
- Page block editors: each block becomes a `.admin-card` with a header row (block type + move/remove buttons); fields via `Field.vue` → `FormField`.

### Settings
Single `.admin-card` section per setting group, `FormField`s, `SaveBar`.

## 5. Backend changes (additive only)

1. **`DashboardController`**: add `revenue.daily`: 30 entries, oldest first, `{date: 'Y-m-d', label: 'j M', value: int paise}` for today minus 29 days through today. Same filter as `revenueSince` (paid, not cancelled), grouped by `DATE(placed_at)` in one query, zero-filled in PHP.
2. **Status values for badges** (sibling keys; existing label keys stay so the current tests and pages keep working):
   - `Admin\OrderController@index` rows: `status_value`, `payment_status_value`, plus `payment_method_label` and `payment_status_label` split out of the combined `payment` string (the combined string stays).
   - `@show` order: `fulfilment_status_value`, `payment_status_value`; each payment: `status_value`; each event: `fulfilment_status_value`, `payment_status_value`.
   - `DashboardController` today's orders: `status_value`.

## 6. Rule update

`.claude/skills/arusa-commerce/SKILL.md`, Admin section: replace "Plain and functional. Reuse shared admin components; no design-skill passes, no decorative motion." with "Brand-tied, modern workspace (see `2026-10-24-admin-ui-redesign-design.md`). Reuse `Components/Admin/*` and the `.admin-*` classes; no Breeze components in admin. Same motion rules as the storefront."

## 7. Testing and verification

- **Feature tests:**
  - `DashboardTest`: `revenue.daily` has 30 entries, sums paid non-cancelled orders per day, and zero-fills empty days. An order outside the window or cancelled is excluded.
  - `OrderListTest` / `OrderWorkflowTest`: assert the new `*_value` keys on one row and on the show page.
- All existing `tests/Feature/Admin/*` tests stay green.
- `grep -rE "Components/(TextInput|InputLabel|InputError|PrimaryButton|Checkbox|ApplicationLogo)" resources/js/Pages/Admin resources/js/Components/Admin resources/js/Layouts/AdminLayout.vue` returns nothing.
- `npm run build` succeeds.
- **Playwright:** every admin page at 1280 px and 390 px. Check the drawer open/close/Esc, a toast after saving a product, the save bar dirty state, the mobile card tables, the order stepper on a pending, a delivered and a cancelled order, and the chart tooltip. Console free of errors.
