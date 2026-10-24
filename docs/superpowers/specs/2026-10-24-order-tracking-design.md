# Order Tracking — Design Spec

- **Date:** 2026-10-24
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Builds on:** `2026-10-23-arusa-ecommerce-design.md`

## 1. Goal

Let every customer, guest or account holder, find and follow their order.

### Already in place (not changed)
- `/orders/{order}` (`orders.show`): permanent signed status page; `Order::trackingUrl()` builds the link.
- `/account` lists the signed-in user's orders, each linking to `trackingUrl()`.
- Placed / dispatched / delivered emails carry the tracking link (only when the guest gave an email).
- `order_events` records every status change with a timestamp.

### Gaps this spec closes
1. A guest with no email, or who lost the link, cannot reach their order.
2. No "Track order" entry point in the storefront.
3. The status page shows the current status only, not its history.

### Out of scope
Phone OTP / SMS, attaching guest orders to accounts, listing all orders for a phone number, opaque order numbers.

## 2. Order lookup (number + phone)

### Routes (`routes/web.php`)
- `GET /track` → `TrackOrderController@create`, name `orders.track`. Renders `Pages/Orders/Track.vue`.
- `POST /track` → `TrackOrderController@store`, name `orders.track.store`, middleware `throttle:5,1`.

### Validation (`TrackOrderRequest`)
- `number`: required string. Normalised by stripping an optional case-insensitive `ARU-` prefix and leading zeros; the remainder must be a positive integer, else a validation error on `number`.
- `phone`: required, `/^[6-9]\d{9}$/` (same rule as checkout).

### Behaviour
- Look up `Order::whereKey($id)->where('customer_phone', $phone)->first()`.
- Found → redirect to `$order->trackingUrl()` (the existing signed page; no new permission model).
- Not found → back with one error on `number`: "We couldn't find an order with those details." The message never reveals which field was wrong.
- Works for guest and account orders alike: order number + phone is the credential.

### Page (`Orders/Track.vue`)
- Store layout, mobile-first, styled like the existing auth/checkout forms (reuse their input and button components).
- Fields: order number (hint: "on your confirmation page and emails, e.g. ARU-000123"), phone.
- When signed in, a line: "Signed in? See all your orders in your account" linking to `route('account')`.

## 3. "Track order" entry points
- `StoreLayout.vue` footer: "Track order" link beside the contact links.
- `StoreLayout.vue` mobile menu: same link.
- Header icons are unchanged.

## 4. Status timeline on `Orders/Show`
- `OrderController@show` loads `events` and passes `timeline`: a list of `{status: label, at: 'j M Y, g:i a'}`.
- Built from fulfilment status only: consecutive events with the same `fulfilment_status` collapse to the first one (payment-only changes drop out). Oldest first.
- `Orders/Show.vue` renders it as a plain vertical list under the status block. Motion, if any, follows store rules (CSS only, 150–300 ms, honours `prefers-reduced-motion`).

## 5. Testing (Pest feature tests)
- Lookup: correct number + phone redirects to the signed URL; `ARU-000123`, `aru-123` and `123` all resolve; wrong phone, unknown number and malformed number return the generic error / validation error; the 6th attempt in a minute gets 429.
- Timeline: payment-only events are excluded and repeated fulfilment statuses collapse.

## 6. Known risk
Order numbers are sequential ids, so the phone match plus the rate limit are the only guard. Anyone holding both a customer's phone and order number can view that order, including the address. Accepted for launch, since both appear on the customer's own receipt.
