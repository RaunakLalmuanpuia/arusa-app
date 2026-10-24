# Page Blocks (Admin-Editable Content Pages) — Design Spec

- **Date:** 2026-10-24
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Builds on:** `2026-10-24-storefront-pass-2-design.md` (`Content/Show` banner, `Reveal.vue`, `animate-rise-in`)

## 1. Goal

Replace the single Markdown body of content pages with an ordered list of typed section blocks the admin can add, edit, reorder and remove, and give each block type a designed storefront rendering. Ship real starter content for the four seeded pages (About, Contact, Size Guide, Shipping & Returns).

### Decisions
- **Storage:** `pages.blocks` JSON column (ordered list). No `page_blocks` table; blocks are never queried on their own.
- **Eight block types**, no more: Hero, Text, Image + text, Features, Table, FAQ, Contact cards, Call to action.
- **Markdown stays** for long-form fields, rendered server-side with the existing safe options. Raw Markdown never reaches the client.
- **Reordering** with Move up / Move down buttons. No drag-and-drop package.
- **Admin is plain and functional** (house rule). Storefront block designs use ui-ux-pro-max.

### Out of scope
Drag-and-drop, live preview, page-level SEO fields, block duplication, revisions/drafts, galleries/testimonials/quotes, deleting orphaned images.

## 2. Data

### Migration
- Add nullable `blocks` JSON column, backfill it, then make it not null. No DB default.
- Backfill: each existing page gets `[{"type":"text","data":{"markdown": <body>}}]`.
- Drop `body`. `down()` re-adds `body` as text and fills it by joining the Markdown of `text` blocks (other block types are lost on rollback).

### `Page` model
- Fillable: `slug`, `title`, `blocks`. Cast `blocks` to `array`.
- `renderedBody()` is replaced by `renderedBlocks(): array` (see §4).
- `PageFactory` produces one `text` block.

### Block shape
Every block is `{ "type": string, "data": object }`. Fields per type (all strings unless noted; `?` = optional):

| Type (`type` value) | `data` fields | Limits |
|---|---|---|
| Hero (`hero`) | `eyebrow?`, `heading`, `intro?`, `image?` (stored path) | heading ≤ 120, intro ≤ 300 |
| Text (`text`) | `markdown` | ≤ 20 000 |
| Image + text (`image_text`) | `image` (path), `image_alt?`, `image_side` (`left`\|`right`), `heading?`, `markdown` | markdown ≤ 5 000 |
| Features (`features`) | `heading?`, `items[]`: `icon`, `title`, `text` | 2–4 items; icon in allow-list; title ≤ 60, text ≤ 200 |
| Table (`table`) | `heading?`, `columns[]` (strings), `rows[][]` (strings), `note?` | 2–6 columns, 1–30 rows, each row length = columns length, cell ≤ 60 |
| FAQ (`faq`) | `heading?`, `items[]`: `question`, `answer` (Markdown) | 1–20 items; question ≤ 200, answer ≤ 2 000 |
| Contact cards (`contact_cards`) | `heading?`, `items[]`: `kind` (`phone`\|`whatsapp`\|`email`\|`address`\|`hours`), `label`, `value` | 1–6 items; phone/whatsapp value matches `/^[6-9]\d{9}$/`; email value is an email |
| Call to action (`call_to_action`) | `heading`, `text?`, `button_label`, `button_url` | url is a site path starting `/` or an `https://` URL |

Optional `heading` fields are ≤ 120 chars. Max 30 blocks per page.

Icon allow-list: `cotton`, `feather`, `puff-print`, `shield`, `chat`, `bag` (the existing `Components/Icons/` set minus UI chrome icons).

## 3. Validation

### `app/Enums/PageBlockType.php`
String-backed enum, TitleCase cases (`Hero`, `Text`, `ImageText`, `Features`, `Table`, `Faq`, `ContactCards`, `CallToAction`), values as in §2. Method `rules(string $prefix): array` returns that type's rules keyed under `"{$prefix}.data.…"`.

### `PageRequest`
- `blocks`: `required|array|max:30`; `blocks.*.type`: `required` + `Rule::enum(PageBlockType::class)`.
- For each submitted block with a valid type, merge `PageBlockType::from($type)->rules("blocks.{$i}")`.
- `body` rules removed.
- Image fields: `string`, must start with `pages/` and the `-800.webp` file must exist on the public disk (prevents pointing at arbitrary paths).
- Table rows: a closure rule checks each row's length equals `columns` length.
- Error messages use readable attribute names (e.g. "block 3 heading") via `attributes()`.

## 4. Rendering

`Page::renderedBlocks()` maps each stored block to a client-ready one:
- Markdown fields (`text.markdown`, `image_text.markdown`, each `faq.items[].answer`) become `html` keys rendered with the current options (`html_input: strip`, `allow_unsafe_links: false`, `TableExtension`). The Markdown key is dropped.
- Image paths become `image: { src, srcset }` (800 / 1600 widths).
- Contact items gain an `href`: `tel:+91…`, `https://wa.me/91…`, `mailto:…`, none for address/hours.

`PageController@show` renders `Content/Show` with `title` and `blocks` (rendered). Admin edit receives the raw stored `blocks` plus each image's preview URL.

## 5. Images

### `app/Actions/StoreWebpImage.php` (extracted)
`handle(UploadedFile $file, string $path, array $widths, string $errorKey): void` — the decode, EXIF upright, per-width WebP resize, rollback-on-failure and memory bump now in `StoreProductImage`, writing `"{$path}-{$width}.webp"`. `StoreProductImage` calls it with `ProductImage::WIDTHS` and keeps creating the `ProductImage` row. Product behaviour is unchanged.

### Upload endpoint
- `POST admin/page-images` → `Admin\PageImageController@store`, name `admin.page-images.store`, inside the existing admin group.
- `StorePageImageRequest`: `image` required, `image`, `mimes:jpg,jpeg,png,webp`, `max:8192`.
- Stores at `pages/{uuid}` with widths `[800, 1600]`; returns JSON `{ path, url }` (url = 800 width) for the editor.
- `// shortcut: images of removed blocks stay on disk; add a cleanup command if storage grows.`

## 6. Storefront

### `Content/Show.vue`
- Props: `title`, `blocks`.
- If the first block is not `hero`, render the existing title banner (current markup, unchanged).
- Loop blocks → component map from `resources/js/Components/Store/Blocks/`: `HeroBlock`, `TextBlock`, `ImageTextBlock`, `FeaturesBlock`, `TableBlock`, `FaqBlock`, `ContactCardsBlock`, `CallToActionBlock`. Each block below the first is wrapped in `Reveal`.

### Block designs (ui-ux-pro-max during implementation; constraints here)
- **Hero:** same dark image band as the current banner, taller (`h-64 sm:h-80`), eyebrow + `h1` + intro, `animate-rise-in`. Uses its image or falls back to `/images/hero-*.webp`. The page `<Head>` title stays `title`.
- **Text:** `content-prose`, `max-w-3xl`.
- **Image + text:** two columns from `md`, image side configurable, stacked on mobile (image first), lazy-loaded with `srcset`.
- **Features:** 2–4 column grid of icon + title + text cards.
- **Table:** horizontally scrollable wrapper on mobile, sticky first column, zebra rows, note in small text below.
- **FAQ:** native `<details>/<summary>`, chevron rotates with a CSS transition; answers in `content-prose`.
- **Contact cards:** grid of tappable cards (whole card is the link when `href` exists, `target=_blank rel=noopener` for WhatsApp), icon per kind.
- **Call to action:** dark `brand-900` panel, heading, text, one button; external URLs open normally (no new tab).
- New icons needed for contact kinds (phone, mail, map pin, clock) and the FAQ chevron are added as inline SVG components in `Components/Icons/`. No icon packages.
- Motion: existing pieces only, 150–300 ms, off under `prefers-reduced-motion`.

## 7. Admin editor

### Pages
`Admin/Pages/Create.vue` and `Edit.vue` keep using `Partials/PageForm.vue`, which now has: title, slug, then the block list. `useForm` holds `{ title, slug, blocks }`; Create starts with one empty `hero` block.

### Block list (in `PageForm.vue`)
- Each block: card with type name, Move up / Move down (disabled at ends), Remove, and the type's field component from `Admin/Pages/Blocks/` (`HeroFields`, `TextFields`, `ImageTextFields`, `FeaturesFields`, `TableFields`, `FaqFields`, `ContactCardsFields`, `CallToActionFields`), each taking `v-model` of `data` and an `errors` prefix.
- "Add block" `<select>` + button appends a block with empty default `data` for that type.
- Repeatable rows (features, FAQ items, contact items, table columns/rows) get Add / Remove buttons; limits from §2 disable Add at the max.
- Image fields: file input that uploads immediately to `admin.page-images.store` (via `fetch` with the `X-XSRF-TOKEN` header from the `XSRF-TOKEN` cookie; no axios), shows the preview, stores `path`. Upload error shown inline.
- Errors shown under each field using `form.errors['blocks.N.data.field']`; a block with errors gets a red border.
- Keys for `v-for` use a client-only `uid` stripped before submit (`form.transform`).
- Reuses `InputLabel`, `TextInput`, `InputError`, `PrimaryButton`. No new packages.

## 8. Seed content

`PageSeeder` writes real block content (creates missing pages; overwrites an existing page only when its blocks are still a single `text` block containing "Edit this page in the admin.", so admin edits are never overwritten):
- **About:** Hero, Image + text (store story; image created by running `public/images/hero-1600.webp` through `StoreWebpImage` into `pages/`), Features (cotton, feather, puff print), Call to action → `/catalog`.
- **Contact:** Hero, Contact cards (phone, WhatsApp, email, address in Aizawl, hours), FAQ (order help), placeholder contact values marked for the owner to replace.
- **Size Guide:** Hero, Text (how to measure), Table (S–XXL chest/length/shoulder in inches), FAQ (fit questions).
- **Shipping & Returns:** Hero, Features (Mizoram-wide delivery, COD, easy returns), Text (policy), FAQ.

Contact numbers/emails are clearly fake placeholders; copy says nothing untrue about policies the owner hasn't confirmed (delivery times stated as "typically", returns window marked for the owner to confirm in the admin).

## 9. Tests (Pest)

- **Admin save:** a page with all 8 block types saves and round-trips; unknown `type` rejected; missing required field per type rejected (dataset); `javascript:` and `http://` CTA URLs rejected; table row length mismatch rejected; >4 features rejected; image path outside `pages/` rejected; non-admin forbidden.
- **Image upload:** stores 800/1600 WebP files and returns `path`; non-image rejected; non-admin forbidden.
- **Storefront:** `pages.show` returns rendered blocks; raw HTML in Markdown is stripped; contact `href`s built correctly.
- **Migration backfill:** existing `body` becomes one `text` block.
- **Existing product image tests** pass unchanged after the `StoreWebpImage` extraction.
