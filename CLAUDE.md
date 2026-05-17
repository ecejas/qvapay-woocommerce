# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

WordPress plugin: WooCommerce payment gateway for [QvaPay](https://www.qvapay.com). Single-flow integration (invoice + redirect) using QvaPay's merchants API v2. USD-only.

Plain PHP loaded by WordPress — no `composer.json`, no `package.json`, no test suite. Don't introduce one unless asked. The only tooling is `build.sh` (produces the release zip) and `.github/workflows/release.yml` (attaches the zip to a GitHub Release when a `v*` tag is pushed).

## Architecture

Entry point `qvapay-for-woocommerce.php` defines plugin constants and, on `plugins_loaded`, requires the five classes under `includes/` in this order (the order matters — later classes reference earlier ones):

1. `QvaPay_Logger` — wraps `wc_get_logger()`, source `qvapay`. Non-error messages are dropped unless the gateway's `debug` setting is `yes`; errors always log.
2. `QvaPay_Api_Client` — static HTTP client. POSTs JSON to `QVAPAY_WC_API_BASE` (`https://qvapay.com/api/v2`) with `app-id` / `app-secret` headers. Returns decoded array or `WP_Error`.
3. `QvaPay_Gateway extends WC_Payment_Gateway` — checkout-facing gateway (id `qvapay`). Owns settings UI, `is_available()` guards (enabled + currency=USD + credentials present), and `process_payment()` which calls `create_invoice` and redirects to `QVAPAY_WC_CHECKOUT_BASE` + uuid.
4. `QvaPay_Webhook` — registers `POST /wp-json/qvapay/v1/webhook` (open `permission_callback`; auth is done by HMAC verification inside `handle()`).
5. `QvaPay_Plugin` (singleton) — wires the gateway into `woocommerce_payment_gateways` and registers REST routes on `rest_api_init`.

HPOS (custom order tables) compatibility is declared via `FeaturesUtil::declare_compatibility` on `before_woocommerce_init` in the main plugin file — keep order access going through `wc_get_order()` / `$order->get_meta()`, never direct postmeta.

Settings live in the single option `woocommerce_qvapay_settings` (deleted by `uninstall.php`). Per-order state is one meta key: `_qvapay_transaction_uuid` (constant `QvaPay_Gateway::ORDER_META_TXN_UUID`).

## Webhook contract (critical)

The webhook handler in `includes/class-qvapay-webhook.php` enforces, in order:

- Presence of body + `x-qvapay-signature` + `x-qvapay-timestamp` headers. WP normalizes header names, so `WP_REST_Request::get_header()` is called with underscores (`x_qvapay_signature`, `x_qvapay_timestamp`) — don't change these to dashes.
- `|now - timestamp| ≤ TIMESTAMP_TOLERANCE` (300 s) — anti-replay window.
- Signature must be `sha256=` + `hash_hmac('sha256', raw_body, app_secret)`, compared with `hash_equals` (timing-safe). Signature is computed over the **raw** body — never re-encode the JSON before hashing.
- Order lookup via `remote_id` (= WC order id). If `_qvapay_transaction_uuid` is stored on the order, it must match the incoming uuid.
- Idempotency: if the order `is_paid()` or status is `processing` / `completed` / `refunded`, the handler returns 200 with `already_processed=true` and does **not** call `payment_complete()` again. Preserve this — QvaPay retries.
- Only `status === 'paid'` triggers `$order->payment_complete($uuid)`; other statuses are logged and acknowledged with 200.

Rejections return non-2xx via `self::reject()` so QvaPay will retry. Webhooks for unknown orders return 200 (no point retrying) but log an error.

## Constraints to preserve

- **USD only.** `is_available()` and the admin notice both check `get_woocommerce_currency() === 'USD'`. The QvaPay API only accepts USD.
- **HTTPS required** for webhook delivery — there's an admin notice but no hard block.
- **Amount range:** `0 < total ≤ 100000` enforced in `process_payment()`.
- **No refunds, no subscriptions, no sandbox** in v1 — don't add `supports` flags (`refunds`, `subscriptions`) without an underlying implementation; QvaPay has no refund endpoint.
- Product names sent to QvaPay are stripped of `< > ' " ( ) ;` and truncated to 150 chars; description to 255. Keep this sanitization when changing payload shape.

## Text domain & i18n

Text domain `qvapay-for-woocommerce`, loaded from `/languages` (directory exists, empty). All user-facing strings go through `__()` / `esc_html__()` — match the existing Spanish source strings rather than introducing English ones.

## Layout

```
qvapay-for-woocommerce.php      Entry point. Constants, bootstrap, HPOS declaration.
uninstall.php                   Removes woocommerce_qvapay_settings on uninstall.
includes/                       All PHP classes (flat — WP convention, do not nest).
assets/images/icon.svg          Checkout icon. Path is referenced from class-qvapay-gateway.php.
languages/                      .mo/.po catalogs (text-domain: qvapay-for-woocommerce).
readme.txt                      WordPress.org-style manifest.
build.sh                        Release packager (SHIP array is the source of truth).
.gitattributes                  export-ignore list — keep aligned with build.sh's SHIP.
.github/workflows/release.yml   Tag-triggered release: validates header version, runs build.sh.
.wordpress-org/                 WP.org banners/icons. Not shipped in the zip — SVN-only.
dist/                           Build output. Gitignored.
```

## Release

- **Package:** `bash build.sh` → `dist/qvapay-for-woocommerce-<version>.zip` with `qvapay-for-woocommerce/` as the top-level dir. The `SHIP` array at the top of the script is the authoritative include list — anything missing from it does **not** ship. When adding new runtime files/dirs, update `SHIP` *and* `.gitattributes` so `git archive` stays consistent with the zip.
- **GitHub Release:** push a tag `vX.Y.Z` that matches the plugin `Version:` header; the workflow fails fast on mismatch.
- **Three places to bump on a version change** (must move together): `Version:` header in `qvapay-for-woocommerce.php`, `QVAPAY_WC_VERSION` constant in the same file, and `Stable tag:` in `readme.txt`.
- **`.wordpress-org/`** holds the WP.org plugin directory assets (banner-1544x500.png, banner-772x250.png, icon-256x256.png, icon-128x128.png). These do **not** ship with the plugin — they go into the SVN repo's separate `/assets/` path. The README inside that folder documents expected dimensions.
