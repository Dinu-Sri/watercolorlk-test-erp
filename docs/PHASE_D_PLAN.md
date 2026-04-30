# Phase D — Roadmap & Tracker

> Living document. Each task has a checkbox; mark with `[x]` when done. Group order roughly = recommended implementation order.

---

## Legend
- `[ ]` not started
- `[~]` in progress
- `[x]` done
- `[!]` blocked / deferred

---

## 0. Pre-flight (manual, you do these — NOT code)

- [ ] Run `db/migrations/2026_06_users.sql` on live DB (creates `users`, `user_tokens`, `user_addresses`, adds `orders.user_id`).
- [ ] Add SMTP, Mail-from, Site-URL values to `config/local.php`.
- [ ] Create Google Cloud OAuth client. Authorised redirect URI: `https://watercolor.lk/account/google-callback.php`. Paste client id/secret/redirect into `config/local.php`.
- [ ] Create GTM container (web). Note container ID `GTM-XXXXXXX`.
- [ ] Create GA4 property + Measurement ID `G-XXXXXXXX`.
- [ ] Create Meta (Facebook) Pixel; note Pixel ID. Decide whether CAPI (server-side) is in scope.
- [ ] Create Google Search Console property + verify domain (HTML tag, will be served from SEO module).

---

## Phase D-1 — Functional gaps & security hardening

### 1. Email-change verification
- [ ] DB: add `pending_email`, `pending_email_token_id` to `users` (or reuse `user_tokens` with `purpose='email_change'`).
- [ ] `account/profile.php`: when user changes email, store new value in `pending_email`, issue token, send confirmation link to NEW address. Old address remains active until link is clicked.
- [ ] New `account/confirm-email-change.php`: validates token, atomically swaps `email` ← `pending_email`, clears `email_verified_at` only if email actually changed (and re-sets it since they just clicked the link), invalidates other email-change tokens.
- [ ] Notify OLD address (security email): "Your email was changed".

### 2. Password / login rate-limiting + lockout
- [ ] DB: new `auth_attempts` table — `id, ip, email_hash, kind ENUM('login','forgot','reset','signup'), success TINYINT, created_at`. Index `(ip, kind, created_at)`, `(email_hash, kind, created_at)`.
- [ ] New `src/Services/RateLimiter.php` — `hit($key, $kind)`, `tooMany($key, $kind, $max, $windowSec): bool`.
- [ ] Apply to `account/login.php` (5/min/IP, 10/15-min/email), `forgot.php` (3/15-min/IP), `reset.php` (5/15-min/token), `signup.php` (3/min/IP).
- [ ] On rate-limit hit: 429 + generic "Too many attempts, try again in X minutes".
- [ ] Background sweeper: scripts/cron-sync.php deletes rows older than 7 days.

### 3. Admin → user detail page
- [ ] `admin/user-view.php?id=X` showing: profile, addresses, all orders w/ totals, audit log of admin actions on this user, tokens issued (verify/reset).
- [ ] Actions: force-resend verification email, force password reset (issues token + emails it), set/clear `email_verified_at`, ban/unban (existing).
- [ ] All actions audit via `audit('user_*', 'user', id)`.
- [ ] Link from `admin/users.php` rows.

### 4. Admin → coupon redemptions log
- [ ] `admin/coupon-view.php?id=X`: shows all redemptions (timestamp, order link, customer phone, discount given), plus the existing config + analytics.
- [ ] `CouponRepository::listRedemptions($couponId)`.
- [ ] Link from `admin/coupons.php` rows ("View" button).

### 5. PayHere webhook hardening
- [ ] Verify MD5 signature: `md5(merchant_id + order_id + payhere_amount + payhere_currency + status_code + strtoupper(md5(merchant_secret)))`.
- [ ] Update order on `status_code === '2'` (success), `'-1'` (cancelled), `'-2'` (failed), `'-3'` (chargeback).
- [ ] Idempotent: skip if status already terminal.
- [ ] Audit each webhook hit.
- [ ] Email customer + admin on success.

### 6. Order email branding polish
- [ ] Update `Mailer::renderLayout()` template: site logo header (img absolute URL), brand colors, footer with address + unsubscribe.
- [ ] Add helper `renderOrderEmail(array $order, array $items, ?string $accountUrl): string`.
- [ ] Send a "shipped" email when admin sets order status → shipped.
- [ ] Send a "cancelled" email on cancellation.

### 7. Stock guard at checkout (server-side re-check)
- [ ] In `api/place-order.php`, before `createOrder()`, re-fetch each line item's effective stock (simple = `stock_qty`, combined = sum of children, pack = `floor(child / pack_qty)`). If any line `qty > effectiveStock`, return 409 with `{ insufficient: [{sku, available}] }`.
- [ ] Cart UI handles 409 → toast "Some items are out of stock" + reload page so the cart shows fresh stock.

### 8. Local stock decrement on order
- [ ] After successful `createOrder`, decrement `products.stock_qty` (or for combined/pack, decrement underlying children) atomically inside the same transaction.
- [ ] Increment back on order status → cancelled (only if not already cancelled).
- [ ] Decision: this is overlay layer — ERP sync will eventually correct numbers from ERP master. Document the rule.

### 9. Account dashboard polish
- [ ] Resend-verification cooldown: store `verify_email_sent_at` in users, disable button + show countdown for 60s on `account/index.php`.
- [ ] Default address card on dashboard.
- [ ] Address count + "Manage addresses →" CTA.

### 10. Sitemap.xml + robots.txt
- [ ] `sitemap.xml.php` (rewrite `sitemap.xml` → this file via `.htaccess`): index + shop + categories + each visible product (simple/combined/pack) + account/login + cart. Use `lastmod = product.updated_at`.
- [ ] `robots.txt` (already exists): add `Sitemap: https://watercolor.lk/sitemap.xml`. Block `/admin/`, `/account/google-callback.php`, `/api/`, `/checkout-success.php`.

### 11. Account deletion (GDPR-style)
- [ ] `account/profile.php` "Delete my account" button → confirm with password (or Google re-auth) → POST `account/delete.php`.
- [ ] Anonymise: orders.customer_name → "Deleted user", customer_email → null, customer_phone → masked, user_id → null.
- [ ] Hard-delete: user_addresses, user_tokens.
- [ ] Soft-mark user: set status='deleted', email='deleted_<id>@example.invalid', password_hash=null, google_sub=null, full_name='Deleted user'.
- [ ] Email confirmation to old address.

---

## Phase D-2 — SEO module

### Goals
- Per-page meta (title/description/canonical/og/twitter) editable in admin.
- Auto-generated JSON-LD for: Organization, WebSite + SearchAction, BreadcrumbList, Product (with offers + aggregateRating), ItemList (shop/category), FAQPage (where applicable), Review (per Google review surfaced on product pages).
- Sitemap (D-1 #10 above).
- Search Console / Bing verification meta tags configurable.

### 2.1 — DB

```sql
CREATE TABLE seo_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  default_title_suffix VARCHAR(120) NOT NULL DEFAULT '| Watercolor.LK',
  default_description VARCHAR(255) NOT NULL,
  default_og_image VARCHAR(255) DEFAULT NULL,
  twitter_handle VARCHAR(60) DEFAULT NULL,
  google_site_verification VARCHAR(120) DEFAULT NULL,
  bing_site_verification VARCHAR(120) DEFAULT NULL,
  facebook_app_id VARCHAR(40) DEFAULT NULL,
  organization_name VARCHAR(120) NOT NULL DEFAULT 'Watercolor.LK',
  organization_logo_url VARCHAR(255) DEFAULT NULL,
  organization_phone VARCHAR(30) DEFAULT NULL,
  organization_address_json TEXT,         -- {streetAddress, addressLocality, postalCode, addressCountry}
  organization_same_as_json TEXT,         -- ["https://facebook.com/...", ...]
  search_action_url VARCHAR(255) DEFAULT NULL,  -- e.g. "/shop.php?q={search_term_string}"
  updated_at DATETIME ON UPDATE NOW()
);

CREATE TABLE seo_overrides (
  id INT AUTO_INCREMENT PRIMARY KEY,
  route_key VARCHAR(120) NOT NULL,        -- 'home', 'shop', 'cart', 'product:slug:my-pencil-set', 'category:slug:watercolor-paints', etc.
  meta_title VARCHAR(160) DEFAULT NULL,
  meta_description VARCHAR(255) DEFAULT NULL,
  canonical_url VARCHAR(255) DEFAULT NULL,
  og_image VARCHAR(255) DEFAULT NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  json_ld_extra MEDIUMTEXT DEFAULT NULL,  -- raw JSON pasted by admin (validated)
  updated_at DATETIME ON UPDATE NOW(),
  UNIQUE KEY uniq_route (route_key)
);

CREATE TABLE seo_redirects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  source_path VARCHAR(255) NOT NULL,
  target_url VARCHAR(255) NOT NULL,
  http_status SMALLINT NOT NULL DEFAULT 301,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  hit_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_hit_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_src (source_path)
);
```

Migration file: `db/migrations/2026_07_seo.sql`.

### 2.2 — Service layer
- [ ] `src/Services/SeoService.php`:
  - `forRoute(string $routeKey, array $defaults): array` — returns merged meta (override > computed > default).
  - `renderMetaTags(array $meta): string` — title, description, canonical, og, twitter, viewport already in head.
  - `productJsonLd(array $product, array $variants=[], ?array $reviews=[]): array` — returns Product+Offer+AggregateRating+Review[] (limit 5).
  - `categoryJsonLd(string $categoryName, array $products): array` — ItemList.
  - `breadcrumbJsonLd(array $crumbs): array`.
  - `organizationJsonLd(): array` (uses seo_settings).
  - `websiteJsonLd(): array` (with SearchAction).
  - `renderJsonLd(array $blocks): string` — wraps in `<script type="application/ld+json">`, `htmlspecialchars`-safe.
- [ ] `src/Repositories/SeoRepository.php`: get/save settings, getOverride/saveOverride/deleteOverride, listRedirects/upsertRedirect/recordHit.

### 2.3 — Integration
- [ ] `partials/seo-head.php` — drop-in head fragment. Each page sets `$seoRoute = 'product:slug:'.$slug;` and `$seoDefaults = ['title'=>..., 'description'=>..., 'image'=>...]` then `include 'partials/seo-head.php'`.
- [ ] Wire into: `index.php` (home), `shop.php` (shop + per-category route key), `product.php` (per-product), `cart.php`, `checkout.php` (noindex by default), `account/*.php` (noindex), `admin/*` (noindex via existing layout).
- [ ] `partials/seo-jsonld-footer.php` — emits site-wide JSON-LD (Organization + WebSite) at the end of body. Page-specific JSON-LD emitted by individual pages.

### 2.4 — Admin UI
- [ ] `admin/seo.php` — tabs:
  - **Settings**: edit `seo_settings` row (description, og image, organization, social links, verification codes).
  - **Page overrides**: list `seo_overrides`, search by route_key, edit/delete; "Add override" form with route picker (home, shop, cart, checkout-success, login, signup, account-dashboard, product:slug:*, category:slug:*).
  - **Redirects**: CRUD.
  - **Sitemap**: button "Regenerate sitemap" (writes static `sitemap.xml`); link to live sitemap.
- [ ] Per-product override shortcut: in `admin/product-edit.php` (if exists; else products edit modal), add "SEO" collapsible with title/description/og/canonical/noindex fields auto-saving to `seo_overrides` with route_key `product:slug:<slug>`.
- [ ] Audit on every save.

### 2.5 — Dynamic sitemap + redirects routing
- [ ] `sitemap.xml.php` (or generate static).
- [ ] `index.php` + `.htaccess` first-line rule: check `seo_redirects` for current path; if active, 301/302 to target.

---

## Phase D-3 — Tag Manager / GA4 / Meta module

### Goals
- Single GTM container drives GA4, Meta Pixel, Google Ads, etc. via Tag Manager UI.
- All standard ecommerce events pushed to `dataLayer` with full GA4 ecommerce schema.
- User identifiers (hashed email + phone) pushed for advanced matching / Customer Match / CAPI.
- Optional server-side endpoints for Meta CAPI + GA4 Measurement Protocol fallback (deduplicated by event_id).

### 3.1 — Configuration
- [ ] Add to `config/app.php` (overridable via `local.php`):
  - `GTM_CONTAINER_ID` (e.g. 'GTM-XXXXXXX' — empty disables)
  - `GA4_MEASUREMENT_ID` (informational; tags managed in GTM)
  - `META_PIXEL_ID` (informational)
  - `META_CAPI_ACCESS_TOKEN` (server-side; secret)
  - `META_CAPI_TEST_EVENT_CODE` (optional; for testing)
  - `GOOGLE_ADS_CONVERSION_ID` (optional)
  - `ANALYTICS_DEBUG` (bool; logs dataLayer pushes to console)
- [ ] Admin UI: `admin/integrations.php` — saves these to a new `app_settings` table (key/value) so non-developers can edit without SSH.

### 3.2 — Front-end injection
- [ ] `partials/gtm-head.php` (top of `<head>`): GTM head snippet, conditional on `GTM_CONTAINER_ID`. Initialises `window.dataLayer` BEFORE GTM loads.
- [ ] `partials/gtm-body.php` (immediately after `<body>`): GTM noscript iframe.
- [ ] `assets/js/analytics.js` — central helper:
  ```js
  window.WLK = window.WLK || {};
  WLK.track = function(event, params) { window.dataLayer.push(Object.assign({event:event, event_id: crypto.randomUUID()}, params)); };
  WLK.trackEcom = function(event, ecom, opts) { ... };  // GA4 ecommerce schema
  ```
- [ ] Bootstrap "user properties" on each page if logged in:
  ```js
  WLK.identify({ user_id: <id>, user_email_sha256: '<sha256>', user_phone_sha256: '<sha256>' });
  ```
  (server emits hashed values into a `<script>` tag; raw email NEVER in JS).

### 3.3 — Event inventory (GA4 + Meta naming)

| User action                    | GA4 event              | Meta event       | dataLayer payload                          | Where fires |
|--------------------------------|------------------------|------------------|--------------------------------------------|-------------|
| Page view (any)                | `page_view`            | `PageView`       | page_path, page_title                      | gtm-head.php |
| View item list (shop/home)     | `view_item_list`       | `ViewContent`    | item_list_id, items[]                      | shop.php / index.php |
| View item                      | `view_item`            | `ViewContent`    | currency, value, items[item w/ variant]    | product.php |
| Select item (card click)       | `select_item`          | —                | item_list_id, items[]                      | shop card click |
| Search (submit)                | `search`               | `Search`         | search_term, results_count                 | api/log-search.php response or shop.php |
| Add to cart                    | `add_to_cart`          | `AddToCart`      | currency, value, items[]                   | cart.js |
| Remove from cart               | `remove_from_cart`     | —                | currency, value, items[]                   | cart.js |
| View cart                      | `view_cart`            | —                | currency, value, items[]                   | cart.php |
| Begin checkout                 | `begin_checkout`       | `InitiateCheckout` | currency, value, items[], coupon         | checkout.php |
| Add shipping info              | `add_shipping_info`    | —                | shipping_tier, items[]                     | checkout.php (after address) |
| Add payment info               | `add_payment_info`     | `AddPaymentInfo` | payment_type, items[]                      | checkout.php (after method) |
| Apply coupon                   | `select_promotion`     | —                | promotion_id (code)                        | checkout.php |
| Purchase                       | `purchase`             | `Purchase`       | transaction_id, currency, value, tax, shipping, items[], coupon | checkout-success.php |
| Sign up (start)                | `sign_up_start`        | —                | method=email/google                        | account/signup.php |
| Sign up (complete)             | `sign_up`              | `CompleteRegistration` | method                              | account/signup.php success |
| Login                          | `login`                | —                | method                                     | account/login.php success |
| Lead (forgot pw)               | —                      | `Lead`           | —                                          | (optional) |
| Add to wishlist                | `add_to_wishlist`      | `AddToWishlist`  | items[]                                    | future |
| Share                          | `share`                | —                | method, content_type, item_id              | share buttons |
| Newsletter signup              | `generate_lead`        | `Lead`           | —                                          | footer form |

- [ ] All `items[]` use unified shape: `{ item_id (sku), item_name, item_brand, item_category, item_variant, price, quantity, item_list_id, item_list_name, index }`.
- [ ] Server emits initial product/list payload as a JSON `<script type="application/json" id="wlk-ecom-data">` block; JS reads + pushes. Avoids parsing PHP into JS strings.

### 3.4 — Server-side helpers
- [ ] `src/Services/Analytics.php`:
  - `productItem(array $product, int $qty=1, ?string $variant=null, ?string $listId=null, ?int $idx=null): array` — produces a normalised GA4 item.
  - `cartItems(array $cartLines): array`
  - `orderItems(array $order, array $items): array`
  - `userIdentity(?array $user): array` — returns hashed email/phone + id for dataLayer.

### 3.5 — Server-side Meta CAPI (optional, recommended for purchase + lead)
- [ ] `src/Services/MetaConversionsApi.php`: `sendEvent(string $eventName, array $userData, array $customData, string $eventId): void`. Posts to Graph API with retries + logs.
- [ ] Wire in `api/place-order.php` after successful order: send `Purchase` server-side with same `event_id` that the browser pushes — Meta dedupes.
- [ ] Hash PII (sha256 lowercase trimmed) before sending.
- [ ] Store `event_id` on `orders` row to allow retries.

### 3.6 — Consent / privacy
- [ ] Cookie consent banner (existing? — verify) wired to GTM Consent Mode v2:
  - default `denied` for ad_storage, ad_user_data, ad_personalization, analytics_storage.
  - `granted` after user accepts.
- [ ] Document choices in `partials/site-footer.php` privacy link.

### 3.7 — Testing
- [ ] GTM Preview Mode walkthrough checklist for each event.
- [ ] GA4 DebugView, Meta Test Events tool.
- [ ] Lighthouse score check (no regression > 5pts).

---

## Phase D-4 — Search analytics module

### Status check
- ✅ `search_queries` table exists (`db/migrations/2026_05_admin.sql:238`).
- ✅ Insertion path: `api/log-search.php` → `ProductRepository::ensureSearchQueryTable` + insert (verified).
- ✅ Auto-creation if missing (`ensureSearchQueryTable`).
- ❌ No admin UI to view top searches.
- ❌ No "zero-result" tracking.
- ❌ No correlation with product clicks (which result was tapped after a search).

### Tasks
- [ ] DB extend `search_queries`: add `result_count INT DEFAULT NULL` (nullable) so we can flag zero-result queries; add `clicks INT DEFAULT 0` for click-through tracking.
- [ ] `api/log-search.php` accepts `result_count` from `api/search.php` response.
- [ ] New `api/log-search-click.php` (POST: query, product_id) increments `clicks`.
- [ ] `assets/js/analytics.js` (or shop search JS) hits this on result-card click after a search.
- [ ] `admin/search-analytics.php`:
  - Top queries (last 7/30/90/all days) with hits, distinct_users (if logged via session id), zero-result % , click-through %.
  - Drill into a query → list of result clicks.
  - "Zero-result queries" tab — actionable: admin can map to a product (creates a redirect or a synonym).
  - Export CSV.
- [ ] Optional synonyms table `search_synonyms (term, synonym, weight)` consulted by search service.
- [ ] Add `search` event to GTM dataLayer (already in 3.3).

---

## Phase D-5 — QA + launch

- [ ] Lint all changed PHP via XAMPP.
- [ ] Manual smoke test: signup → verify → login → add to cart → checkout → email → admin order → status update.
- [ ] Lighthouse audit (Mobile + Desktop) on home/shop/product.
- [ ] Rich Results Test on product, home, category.
- [ ] Schema.org validator check.
- [ ] GTM preview run for 100% of inventory events.
- [ ] Verify Search Console picks up sitemap.
- [ ] Commit + push each phase as a discrete commit for cleaner rollback.

---

## File / module map (delivered at end of Phase D)

```
config/app.php                    + GTM_CONTAINER_ID, GA4_MEASUREMENT_ID, META_PIXEL_ID, META_CAPI_*, GOOGLE_ADS_CONVERSION_ID
db/migrations/
  2026_07_seo.sql                 + seo_settings, seo_overrides, seo_redirects
  2026_08_analytics.sql           + auth_attempts, search_queries.result_count/clicks, app_settings, orders.meta_event_id, users.pending_email
src/Services/
  RateLimiter.php                 NEW
  SeoService.php                  NEW
  Analytics.php                   NEW
  MetaConversionsApi.php          NEW
src/Repositories/
  SeoRepository.php               NEW
  AppSettingsRepository.php       NEW
  SearchRepository.php            NEW (refactor of ensureSearchQueryTable)
partials/
  seo-head.php                    NEW
  seo-jsonld-footer.php           NEW
  gtm-head.php                    NEW
  gtm-body.php                    NEW
assets/js/
  analytics.js                    NEW
admin/
  seo.php                         NEW
  integrations.php                NEW
  search-analytics.php            NEW
  user-view.php                   NEW
  coupon-view.php                 NEW
account/
  confirm-email-change.php        NEW
  delete.php                      NEW
api/
  log-search-click.php            NEW
  payhere-webhook.php             NEW (or hardened existing)
sitemap.xml.php                   NEW
robots.txt                        UPDATED
```

---

## Recommended execution order

| Sprint | Phase | Tasks | Why first |
|--------|-------|-------|-----------|
| 1      | D-1   | #2 rate-limit, #5 webhook, #7 stock guard, #8 stock decrement | Security + correctness blockers |
| 2      | D-1   | #1 email change, #3 user detail, #4 coupon redemption log, #6 emails | Customer-facing polish |
| 3      | D-2   | SEO module end-to-end | Drives organic traffic — needed before ads |
| 4      | D-3   | GTM core + dataLayer events + GA4/Meta tags via GTM | Required for paid campaigns |
| 5      | D-3   | Meta CAPI + Consent Mode v2 | Ad performance & compliance |
| 6      | D-4   | Search analytics admin UI + zero-result handling | Insights → catalog improvements |
| 7      | D-1   | #9 dashboard polish, #10 sitemap (if not done in D-2), #11 account deletion | Cleanup |
| 8      | D-5   | Full QA + commit fence | Launch |

---

## Open decisions (to confirm before sprint 1)

- [ ] Currency: confirm GA4/Meta currency = `LKR`.
- [ ] Should anonymous (guest) checkouts also fire GA4 user_id / Meta CAPI? (Recommendation: yes for CAPI with hashed email/phone, no for GA4 user_id.)
- [ ] Cookie consent: opt-in (EU style) or opt-out (default-on for LK)? (Affects Consent Mode defaults.)
- [ ] Should admin `seo_overrides` allow raw HTML in description? (Recommend: no, plain text only — strip on save.)
- [ ] PayHere is the only payment gateway? (Affects webhook scope.)
- [ ] Stock decrement: do we mirror in our DB or rely solely on ERP push-back? (Recommendation: mirror in our DB; ERP corrects on next sync.)
- [ ] Account deletion: anonymise vs hard-delete (regulatory)?

---

_Last updated: 2026-04-30. Update the checkboxes as we progress._
