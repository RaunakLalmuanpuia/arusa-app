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
- Brand-tied, modern workspace (spec: `docs/superpowers/specs/2026-10-24-admin-ui-redesign-design.md`). Reuse `Components/Admin/*` and the `.admin-*` classes in `app.css`; no Breeze components in admin. Same motion rules as the storefront.
- `is_admin` is never mass-assignable. Grant with `php artisan admin:grant {email}`.

## Storefront design
- User-facing pages use **ui-ux-pro-max** as the primary design source, **design-motion-principles** for motion, **impeccable** only for audit/polish passes.
- Motion: CSS transitions or Vue `<Transition>` only, 150–300 ms, honour `prefers-reduced-motion`.
- Mobile-first, storefront JS < ~200 KB, WebP + `srcset` + lazy-loaded images.
- Icons: inline SVG components in `resources/js/Components/Icons/`. **Never** add Lucide or any icon/animation package.
