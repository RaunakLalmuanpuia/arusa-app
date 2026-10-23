# Arusa E-commerce — Design Spec

- **Date:** 2026-10-23
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Business:** Arusa, single-vendor online store selling apparel and misc products, based in Aizawl, Mizoram
- **Domain:** arusa.co.in

## 1. Goals & scope

### In scope (launch)
- Online-only store; the website is the single source of truth for stock.
- Small catalog (< ~100 products), apparel with **size × colour** variants, misc products as single-variant items.
- Delivery across **Mizoram only**: Aizawl (own rider) and district towns (courier / bus parcel), priced per shipping zone.
- Payments: **COD + Razorpay** (UPI, cards, netbanking).
- **Not GST-registered**: plain receipts, prices shown as final, no tax lines.
- **Guest checkout** (name + phone, optional email) plus optional Breeze email/password account.
- Content pages: About, Contact, Size Guide, Shipping & Returns policy.
- Hand-built admin in Inertia/Vue.

### Out of scope (launch)
Multi-vendor, physical shop / POS, Instagram/WhatsApp manual orders, shipping outside Mizoram, courier API integration, GST invoices, phone OTP login, social login, coupons, wishlist, reviews, returns/exchanges flow, newsletter, Mizo language, CSV import, multiple admin roles, Redis, search engine, automated browser test suite.

### Future-friendly decisions
- Products are store-owned, not hard-coded, so a marketplace remains possible.
- Shipping uses a zone → rate table so a courier aggregator (e.g. Shiprocket) can be added for all-India later.
- GST: add `hsn_code` + `tax_rate` columns and an invoice template when registered.

## 2. Stack

- Laravel 13, PHP 8.4, PostgreSQL, Inertia v2 + Vue 3, Tailwind 3, Pest (existing).
- Breeze auth (existing).
- Database queue and cache (existing config).
- No new PHP/JS dependencies without approval. **No Lucide**; no animation library; no icon package.
- Razorpay is called with Laravel's `Http` client (no SDK) unless the plan justifies otherwise.

## 3. Data model

All money is stored as **integer paise**.

| Table | Columns |
|---|---|
| `categories` | name, slug (unique), parent_id (nullable, one level deep), sort_order, is_active |
| `products` | category_id, name, slug (unique), description, base_price, is_active, timestamps |
| `product_variants` | product_id, sku (unique), size (nullable), colour (nullable), price_override (nullable), stock (int ≥ 0), is_active |
| `product_images` | product_id, variant_id (nullable, for colour switching), path, alt, sort_order |
| `users` (existing) | + phone (nullable), + is_admin (bool, default false) |
| `addresses` | user_id (nullable), name, phone, line1, locality, district, pincode |
| `shipping_zones` | name, rate, free_above (nullable), cod_allowed, cod_max (nullable), is_active |
| `shipping_zone_pincodes` | shipping_zone_id, pincode (unique) |
| `carts` / `cart_items` | for logged-in users; guests use the session; session cart merges on login |
| `orders` | number (`ARU-000123`), user_id (nullable), customer_name, customer_phone, customer_email (nullable), shipping address snapshot columns, shipping_zone_id, subtotal, shipping, total, payment_method (`cod`/`razorpay`), payment_status, fulfilment_status, tracking_note (nullable), placed_at |
| `order_items` | order_id, product_variant_id, product_name, size, colour, unit_price, quantity (all snapshots) |
| `payments` | order_id, gateway, gateway_order_id, gateway_payment_id (unique, nullable), amount, status, payload (json) — one row per attempt |
| `pages` | slug (unique), title, body (Markdown) |
| `settings` | key (unique), value — store name, contact phone, WhatsApp number, email, COD default |

A product's effective variant price is `price_override ?? base_price`. Misc products get one default variant with null size/colour.

### Enums (PHP backed enums, TitleCase keys)
- `PaymentMethod`: Cod, Razorpay
- `PaymentStatus`: Pending, AwaitingPayment, Paid, Failed, Refunded
- `FulfilmentStatus`: Pending, Confirmed, Packed, Dispatched, Delivered, Cancelled

Allowed fulfilment transitions: Pending→Confirmed, Confirmed→Packed, Packed→Dispatched, Dispatched→Delivered; Cancelled reachable from Pending, Confirmed, Packed. No other transitions.

## 4. Checkout & payment flow

1. **Cart:** prices always recomputed server-side from current variant prices. Client never sends prices.
2. **Checkout form:** name, 10-digit Indian phone, optional email, address, pincode. Zone resolved from pincode; unknown pincode → "We don't deliver here yet".
3. **Totals:** subtotal + zone rate (0 if subtotal ≥ `free_above`). COD offered only if zone `cod_allowed` and total ≤ `cod_max` (when set).
4. **`PlaceOrder` action** (single DB transaction):
   - `lockForUpdate` variant rows, verify stock; on shortage roll back and report which item.
   - Decrement stock, create order + item snapshots.
   - COD → payment `Pending`, fulfilment `Confirmed`; show confirmation, queue emails.
   - Razorpay → payment `AwaitingPayment`, fulfilment `Pending` (becomes `Confirmed` when payment is `Paid`); create Razorpay order via API, store `payments` row, open Razorpay Checkout. Razorpay API failure rolls back the whole transaction.
5. **Payment confirmation:**
   - Browser success handler posts payment id + signature; server verifies HMAC before showing "Paid".
   - `POST /webhooks/razorpay` (CSRF-exempt, signature-verified, idempotent on payment id) handles `payment.captured` and `payment.failed`; it is the source of truth.
6. **Abandoned payments:** scheduled job every 5 minutes cancels `AwaitingPayment` orders older than 30 minutes (payment → `Failed`, fulfilment → `Cancelled`) and restores stock. Customer may retry payment on the same order before that.
7. **Cancellation:** restores stock. For paid Razorpay orders, admin triggers a refund via the Razorpay refund API; result stored in `payments`, payment status → `Refunded`.
8. **Guest order tracking:** signed URL (`URL::signedRoute`) to the order status page, included in confirmation page and email.
9. **COD payment:** marked `Paid` when admin marks the order Delivered.

## 5. Storefront (user-facing)

Pages: Home, Category listing (filters: size, colour, price), Product (variant picker, image switch per colour), Search (Postgres full-text on product name/description), Cart (drawer + page), Checkout, Order confirmation, Order tracking, Account (orders, addresses), Content pages.

- Mobile-first; JS budget < ~200 KB for storefront pages; Inertia prefetch on product links.
- Images: resized on upload to a few widths, served as WebP with `srcset`, lazy-loaded below the fold.
- Brand direction derived from the Arusa logo (to be added at `resources/images/logo.png`).
- **Design skills apply here**: ui-ux-pro-max is the primary source for layout, colour, typography and UX; design-motion-principles for micro-interactions; impeccable for audit/polish passes only.
- Motion: CSS transitions / Vue `<Transition>` only, 150–300 ms, respects `prefers-reduced-motion`.
- Icons: inline SVG Vue components in `resources/js/Components/Icons/`.

## 6. Admin (hand-built Inertia/Vue)

- `/admin/*` behind `auth` + `is_admin` Gate; separate admin layout, usable on mobile.
- Shared components: `AdminTable.vue` (server-side search/filter/pagination via query string, column slots), admin form layout reusing Breeze inputs, `ImageUploader.vue` (multi-upload, reorder).
- One Form Request per resource; authorization on every action.
- Screens:
  1. **Dashboard:** today's orders, needs-action counts, low-stock variants (stock ≤ 3), revenue 7/30 days.
  2. **Orders:** list (filter by status, payment method, zone, date); detail with items, address, payment attempts, status timeline; transition buttons (validated), Cancel, Refund; printable packing slip / receipt; `wa.me` "Message customer" link.
  3. **Products:** list + editor with details, images, variant grid (pick sizes × colours → generate combos, edit SKU/price/stock inline).
  4. **Categories:** list with parent and sort order.
  5. **Shipping zones:** rate, free_above, COD allowed, COD max, pincode list per zone.
  6. **Content pages:** title + Markdown body (rendered safely on storefront).
  7. **Settings:** store name, contact phone, WhatsApp number, email, COD default.
- Admin stays plain and functional; design skills not required there.

## 7. Notifications

Queued emails via Gmail SMTP (App Password, ~500/day limit): order placed (customer if email given, and admin), dispatched, delivered. No SMS/WhatsApp API at launch.

## 8. Infrastructure

- **Host:** AWS Lightsail, Mumbai region. Nginx, PHP-FPM 8.4, PostgreSQL, Supervisor (queue worker), cron for `schedule:run`.
- **Deploy:** manual or a simple deploy script: `git pull`, `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, cache config/routes/views, restart queue.
- **Files:** local `public` disk (`storage/app/public`, `php artisan storage:link`).
- **Domain:** arusa.co.in, Let's Encrypt SSL.
- **Email:** Gmail SMTP.
- **Backups:** manual by owner; must include **both** a Postgres dump and `storage/app/public`. (Optional: Lightsail automatic snapshots.)
- **Razorpay:** test mode during development; live mode after KYC (needs content pages). Keys and webhook secret in `.env` only.

## 9. Testing

Pest feature tests, Razorpay faked with `Http::fake()`:
- Price recalculation, shipping/free-above, COD eligibility.
- `PlaceOrder`: stock decrement, out-of-stock rollback, Razorpay API failure rollback.
- Abandoned-order cleanup restores stock.
- Razorpay signature verification (valid/tampered), webhook idempotency, payment failure, refund.
- Fulfilment transitions: allowed and rejected.
- Security: non-admin 403 on `/admin/*`; signed tracking link required for guest order view; out-of-zone pincode rejected.

UI verified manually (Playwright MCP available for spot checks).

## 10. Claude Code tooling (project-scoped)

| Tool | Location | Purpose |
|---|---|---|
| Boost skills (existing) | `.claude/skills/` | Laravel, Inertia-Vue, Tailwind, testing |
| ui-ux-pro-max (`nextlevelbuilder/ui-ux-pro-max-skill`) | `.claude/skills/ui-ux-pro-max/` | Storefront design system and UX |
| design-motion-principles (`kylezantos/design-motion-principles`) | `.claude/skills/` | Storefront motion; audit before launch |
| `arusa-commerce` (custom, via skill-creator) | `.claude/skills/arusa-commerce/` | Domain rules: paise, snapshots, stock locking, order states, which design skill applies where |
| Playwright MCP | `.mcp.json` | Browser checks of checkout |
| Razorpay MCP (phase 4) | `.mcp.json` | Read-only lookup of test payments |

Installed at user level and still used: Superpowers, frontend-design, impeccable, security-review (before launch). Verify each repo's README before installing.

## 11. Build phases

Each phase gets its own implementation plan → implementation → review.

1. **Foundation:** initial commit, project tooling (section 10), storefront + admin layouts, logo and brand tokens, `is_admin` + admin shell, content pages, settings, icon components.
2. **Catalog:** categories, products, variants, images; admin CRUD with shared table/form/uploader; storefront home, category, product and search pages.
3. **Cart & checkout:** cart, zones + pincode lookup, guest/account checkout, COD orders, confirmation, signed tracking link, emails.
4. **Razorpay:** online payment, webhook, abandoned-order cleanup, refunds.
5. **Admin orders & dashboard:** order workflow, packing slip, WhatsApp link, low-stock and revenue overview.
6. **Launch:** Lightsail setup, domain, SSL, Gmail SMTP, Razorpay KYC and live keys, go live.
