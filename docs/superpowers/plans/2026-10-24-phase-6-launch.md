# Phase 6: Launch Plan (draft)

> **Status: on hold.** The owner is adding more features before launch. This is the launch checklist written on 2026-10-24, after Phase 5. When the owner is ready, start with **Step 0**. The app will have changed by then, so refresh this plan before following it, and write the code tasks out properly with superpowers:writing-plans.

**Goal:** arusa.co.in is live on AWS Lightsail (Mumbai) with HTTPS, real email, Razorpay in live mode, a queue worker, the scheduler and a backup routine. The owner can deploy updates with one script.

**Spec:** `docs/superpowers/specs/2026-10-23-arusa-ecommerce-design.md`, sections 8 (Infrastructure) and 11 (phase 6).

---

## Step 0: Before starting (refresh)

Do this first, in a fresh session:

1. Read `git log` since `2e3d3b0` (the end of Phase 5). Note every feature added since then, and:
   - new `.env` keys;
   - new PHP extensions or packages (`composer.json`, `package.json`);
   - new queued jobs, scheduled commands or storage disks.
2. Update this plan for what changed. Pay most attention to the env table, the server packages and the smoke tests.
3. Run `php artisan test --compact` and `npm run build`. Both must be green.
4. Ask the owner the **Open questions** below in one batch.
5. Then write the code tasks (section A) as a normal TDD plan with superpowers:writing-plans, on a new branch `phase-6-launch`.

---

## Owner prerequisites (gather before launch day)

- [ ] AWS account with billing set up; Lightsail available in **Mumbai (ap-south-1)**.
- [ ] Access to the domain registrar for **arusa.co.in**, to edit DNS.
- [ ] Gmail account for the store, with 2-Step Verification on and an **App Password** created (Google Account → Security → App passwords).
- [ ] **Razorpay KYC** submitted and approved. Razorpay checks the live website, so the content pages must be filled in first (see C3).
- [ ] Brand mark confirmed. The logo says wolf / Estd 2026; an older board said lion / EST. 2024; the site follows the logo.
- [ ] Real store details: store name, contact phone, WhatsApp number, contact email.
- [ ] Real catalog ready to enter: categories, products, variant stock and photos.
- [ ] Shipping zones and their pincodes, rates, free-above amounts and COD limits.

---

## A. Code tasks (in the repo, before deploying)

Small. Write these up with writing-plans when starting.

1. **Deploy script** (`deploy.sh` at the repo root, or wherever the owner approves). It runs the spec's steps in order:
   ```bash
   php artisan down --retry=60
   git pull --ff-only
   composer install --no-dev --optimize-autoloader --no-interaction
   npm ci && npm run build
   php artisan migrate --force
   php artisan optimize          # config, routes, views, events cache
   php artisan queue:restart
   php artisan up
   ```
2. **Server config files** for nginx, Supervisor and cron, kept in the repo for reference (proposed: `deploy/`). *This is a new base folder, so it needs owner approval per CLAUDE.md.* The alternative is to keep them only in this doc.
3. **Production env review.** Confirm that nothing reads `env()` outside `config/`, since `config:cache` breaks that. Confirm that `.env.example` lists every key the app needs.
4. **Trusted proxies**, only if the site sits behind a proxy or CDN such as Cloudflare. Then configure `$middleware->trustProxies(...)` in `bootstrap/app.php`; otherwise signed tracking links and HTTPS detection fail. Plain Nginx on the instance doesn't need it.
5. **Pre-launch audits** (spec section 10):
   - `security-review` on the whole app;
   - an `impeccable` audit of the storefront;
   - a design-motion-principles audit;
   - ~~the storefront JS budget~~: **accepted** by the owner on 2026-10-24. The app chunk is ~300 KB raw / ~102 KB gzip, almost all Vue, Inertia and axios. Look again only if a large library is added.
6. **Open follow-ups to triage before going live:**
   - ~~Webhook 302 on a failed automatic refund~~: fixed after Phase 5. It now answers 503, so Razorpay redelivers.
   - Fixed on 2026-10-24: refund webhooks are now handled, and most deferred Minor findings are cleared. See the "follow-ups" commit for what was left and why.
   - The Razorpay MCP (deferred since Phase 4; optional).

---

## B. Server setup (Lightsail, Mumbai)

1. **Instance:** Ubuntu LTS, Mumbai. Size: see the open questions. Attach a **static IP**.
2. **Firewall:** allow 22 (SSH, ideally from the owner's IP only), 80 and 443. Nothing else.
3. **Packages:**
   - Nginx, PostgreSQL, Supervisor, Composer, git;
   - Node LTS, matching the repo's `.nvmrc`, for building assets on the server;
   - PHP 8.4 with FPM, plus the extensions `pgsql`, `pdo_pgsql`, `intl` (needed for `Number::currency`), `gd` with WebP support (image uploads use `imagewebp`), `mbstring`, `xml`, `curl`, `zip`, `bcmath`.
4. **Database:** create the database and its user with a strong password. Do **not** expose Postgres outside localhost.
5. **App:**
   1. Clone into `/var/www/arusa`, owned by a deploy user, with `www-data` able to write `storage/` and `bootstrap/cache/`.
   2. `composer install --no-dev`, `npm ci && npm run build`.
   3. Create `.env` from the table below and run `php artisan key:generate`.
   4. `php artisan migrate --force`, then `php artisan storage:link`.
   5. **Never run `--seed` in production.** It creates the `test@example.com` admin and demo data.
6. **Nginx:** a server block for `arusa.co.in` with root `/var/www/arusa/public`, the standard Laravel `try_files` and PHP-FPM socket, and `client_max_body_size` large enough for product photos (e.g. 20M). Redirect `www` to the apex domain, or the other way round (open question).
7. **Queue worker (Supervisor):** `php artisan queue:work database --sleep=3 --tries=3 --max-time=3600`, one process, autorestart, running as `www-data`. Emails depend on it.
8. **Scheduler (cron):** `* * * * * cd /var/www/arusa && php artisan schedule:run >> /dev/null 2>&1`, as `www-data`. The unpaid-order sweep (`orders:cancel-abandoned`, every 5 minutes) depends on it.

### Production `.env` (values from the owner; never commit them)

| Key | Value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://arusa.co.in` (signed tracking links depend on it) |
| `APP_KEY` | from `key:generate` |
| `LOG_LEVEL` | `error` (or `warning`) |
| `DB_*` | the local Postgres database and user |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUEUE_CONNECTION` / `CACHE_STORE` | `database` / `database` (spec: no Redis) |
| `FILESYSTEM_DISK` | `public` per spec, or leave the default if uploads already target the `public` disk explicitly |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_SCHEME` | `smtp.gmail.com` / `587` / per Laravel 13's mail config (check with search-docs) |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | the store Gmail address / its App Password |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | the same Gmail address / `Arusa` |
| `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` | the **live** keys |
| `RAZORPAY_WEBHOOK_SECRET` | the secret typed into the **Live Mode** webhook |

---

## C. Domain, email, payments

1. **DNS:** point an `A` record for `@` (and `www`) to the static IP. Wait for it to resolve.
2. **SSL:** Let's Encrypt with certbot's Nginx plugin. Confirm that auto-renewal is scheduled (`certbot renew --dry-run`).
3. **Content pages for KYC:**
   - Fill in the seeded pages in admin → Pages: About, Contact, Size Guide, and Shipping & Returns.
   - Razorpay usually also expects **Privacy Policy**, **Terms & Conditions** and a **Cancellation & Refund** policy. Check Razorpay's current website requirements at the time, add any missing pages and link them in the footer.
4. **Email:**
   1. Send a test with `php artisan tinker`.
   2. Place a real COD order with an email and check that the receipt and the admin alert arrive.
   3. Gmail allows about 500 emails a day; fine at launch volume.
5. **Razorpay live:**
   1. After KYC approval, generate the **live** keys and put them in `.env`.
   2. In **Live Mode**, create a webhook to `https://arusa.co.in/webhooks/razorpay` with its own secret. Events: `payment.captured`, `payment.failed`, `refund.processed`, `refund.failed`. Also add the two refund events to the Test Mode webhook.
   3. Keep payment capture on Automatic.
   4. Pay a small real order with UPI, then cancel it in admin to check that the refund goes through.

---

## D. Store data (in admin, on production)

1. Register the owner's account on the live site, then run `php artisan admin:grant <owner-email>` on the server.
2. Settings: store name, contact phone, WhatsApp number, email, COD on or off.
3. Shipping zones with all their pincodes.
4. Categories, then products with variants, stock and photos.
5. Check: no demo data, and no `test@example.com` user.

---

## E. Backups (manual, owner-run; spec section 8)

- Each backup must include **both** the database and the uploads:
  ```bash
  pg_dump -Fc arusa > arusa-$(date +%F).dump
  tar czf uploads-$(date +%F).tgz -C /var/www/arusa/storage/app public
  ```
- Copy both off the server, e.g. to the owner's computer or a Lightsail snapshot or bucket.
- Optional: Lightsail automatic snapshots.
- **Do one restore drill** before launch, into a scratch database (`pg_restore`), so the backup is known to work.

---

## F. Go-live smoke test (at 375 px on a real phone)

- [ ] `https://arusa.co.in` loads over HTTPS, and `http://` and the other host redirect to it.
- [ ] Browse a category, view a product, add to the bag, check out as a guest with COD.
- [ ] The confirmation page and the signed tracking link work, and the emails arrive.
- [ ] A live Razorpay payment with UPI on a phone: the order is Paid, and the webhook shows a 2xx in the Razorpay dashboard.
- [ ] In admin: pack, dispatch with a note, deliver (the emails arrive), print the slip, then cancel and refund a paid order.
- [ ] `/up` returns 200. After the first 5 minutes, the scheduler log shows the sweep ran.
- [ ] Watch `storage/logs/laravel.log` for errors for the first day.

## Rollback

- Code: `git checkout <previous-commit>` on the server, then run the deploy script steps after `git pull`.
- Data: restore the last `pg_dump` and uploads tarball.
- Payments: switching the keys back to test mode takes the online payment option offline. COD keeps working.

---

## Open questions for the owner (ask in one batch at Step 0)

1. **Instance size.** The smallest Lightsail plans may be tight while running `npm run build` on the server. Choose a bigger plan, or build assets locally or in CI and upload them instead.
2. **Canonical host:** `arusa.co.in` or `www.arusa.co.in`?
3. **Server config files in the repo** (`deploy/` folder, a new base folder), or only in this doc?
4. **A CDN or proxy (e.g. Cloudflare) in front?** If yes, configure trusted proxies.
5. **Error alerts:** the spec has none. Is reading the logs enough, or should we add a free option, e.g. a daily email of errors?
6. ~~**The JS budget**~~: accepted (~300 KB raw / ~102 KB gzip), owner decision 2026-10-24.
7. **Which of the deferred follow-ups** must be fixed before going live?
8. **Extra policy pages** for Razorpay KYC: who writes them?
