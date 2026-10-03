# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ELC — an **OpenCart 4.1.0.3** storefront for a Sri Lankan bakery/cake business, serving two regions (Sri Lanka and Australia) from one codebase. Runs locally on XAMPP at `http://elc.local/` against MySQL database `elc` (table prefix `oc_`).

There is **no build system, no test suite, no linter, and no package manager** at the project root. PHP files are served directly by Apache. The only `package.json`/`gulpfile.js` in the tree belong to a vendored third-party extension and are not part of this project's workflow. Do not invent build or test commands.

## Commands

Everything runs through XAMPP (Apache + MySQL) with the docroot at `C:/xampp/htdocs/elc/`. There is nothing to compile — edit a `.php` or `.twig` file and reload.

```bash
php extension/<code>/install.php
```

Custom extensions are installed by a **standalone CLI installer**, not through the admin marketplace. Each `install.php` bootstraps `config.php`, opens a raw `mysqli` connection, and does the registration work itself: inserts into `oc_extension`, seeds `oc_setting` defaults, registers `oc_event` / `oc_startup` rows, creates its own tables, and grants `access`/`modify` on its route to every `oc_user_group`. Installers are idempotent (`DELETE` then `INSERT`, or `INSERT IGNORE`), so re-running is safe.

```bash
php extension/<code>/fix_permissions.php
```

Re-grants admin permissions only. Use this when an extension's settings page returns a permission error — most often after adding a new user group. Available for `stock_notify`, `store_selector`, and `contact_stores`.

Clearing caches after template or setting changes means deleting `system/storage/cache/` (gitignored) — Journal 3 also caches compiled CSS under its own storage.

`cron.php` is the entry point for scheduled jobs, dispatching to `cron/cron`.

## Architecture

Standard OpenCart 4 MVC-L (Model / View / Controller / Language) with three entry points: [index.php](index.php) → catalog, [admin/index.php](admin/index.php) → admin, and [cron.php](cron.php). Each has its own `config.php` defining `DIR_*` and `DB_*` constants.

### Journal 3 is a second application, not a skin

The commercial **Journal 3** theme (v3.2.8, licensed — see the license `.txt` at root) is the only theme and is deeply woven in. `catalog/controller/journal3/` holds ~65 controllers and `system/library/journal3/` carries its own vendored dependency tree. It owns page composition, module rendering, and much of the admin design UI. When a storefront change doesn't take effect, check whether Journal 3 is rendering that region before editing core templates. Journal 3 fires its **own event triggers** (e.g. `catalog/view/journal3/products/after`) that custom extensions hook into — core OpenCart triggers alone are often not enough.

### Extension layout and naming

Custom code lives under `extension/<code>/{admin,catalog}/{controller,model,view,language}/<type>/<name>.php`, mirroring the core tree. Namespaces are derived mechanically:

```
extension/stock_notify/catalog/controller/module/stock_notify.php
  → Opencart\Catalog\Controller\Extension\StockNotify\Module\StockNotify
```

Routes follow `extension/<code>/<type>/<name>` and, for events, `extension/<code>/<type>/<name>.<method>`. Each extension carries an `install.json` manifest (name, codename, version, author).

### Custom extensions

| Code | Type | Notes |
|---|---|---|
| `store_selector` | module + **startup** | SL↔AU region gateway. Runs on **every request** — see below. |
| `payhere` | payment | PayHere gateway (Sri Lanka), one-off and subscription, md5sig callback |
| `courier_service` | shipping | Province/city × weight rates, seeded for all 25 SL districts |
| `next_day_trishaw_delivery_rate` | shipping | Byte-identical clone of `courier_service` — see below |
| `stock_notify` | module | "Notify me when back in stock" subscriptions + admin console |
| `chatbot` | module | Product search / FAQ / order status, OpenAI fallback via `callAi()` |
| `contact_stores` | module | Contact page with AU + SL store details and maps |
| `instagram_feed` | module | Instagram Graph API feed |
| `ajax_quick_checkout` | module | Dreamvention one-page checkout; depends on `dv_dialogify`, `dv_opencart_patch`, `dv_simple_html_dom` — do not remove those |

### The store selector runs on every request

[extension/store_selector/catalog/controller/startup/store_selector.php](extension/store_selector/catalog/controller/startup/store_selector.php) is registered in `oc_startup` and executes before every controller. It resolves a region from cookie, host, or IP geolocation and **may issue a redirect**. It carries an explicit route allowlist (the gateway/select routes, `contact`, maintenance, `error/not_found`) plus a blanket skip for `api/*`.

If you add a route that must be reachable before a region is chosen — a webhook, a callback, an AJAX endpoint — **add it to the `$ignore` array**, or it will be redirected to the region gateway and break.

Note: these source files were saved with doubled blank lines between every statement. That is cosmetic, not a corrupted file.

### POS inventory sync lives in core, not in an extension

[admin/controller/catalog/sync.php](admin/controller/catalog/sync.php) + [admin/model/catalog/sync.php](admin/model/catalog/sync.php) pull a stock list from an external POS (`api-printacake.posbill.net`) and update product and product-option price/quantity **matched by SKU**, writing a run log plus per-item detail rows. Its schema is [install_sync.sql](install_sync.sql), applied lazily by `checkInstall()` on first page load rather than by an installer script. The menu entry is wired into `admin/controller/common/column_left.php`.

## Core modifications — these are not upgrade-safe

Several changes live in core paths instead of `extension/`, so an OpenCart point release will overwrite them. Know about these before touching the affected files or upgrading:

- `system/helper/watermark.php` — **new file**, applies configured watermarks to uploaded images
- `admin/controller/setting/setting.php` + its language/template — watermark settings UI
- `admin/controller/common/filemanager.php` — watermark hooks
- `admin/controller/common/column_left.php` — POS sync menu entry
- `admin/controller/catalog/sync.php` + `admin/model/catalog/sync.php` — the POS sync feature

When adding new functionality, prefer an `extension/` module with `oc_event` hooks over another core edit.

## Known issues worth fixing

- **PayHere callback fails open.** In [extension/payhere/catalog/controller/payment/payhere.php](extension/payhere/catalog/controller/payment/payhere.php), `$verified` is initialised to `true` and signature validation is wrapped in `if ($secret)`. With a blank merchant secret, any unauthenticated POST to the callback marks an order paid. Should default to `false` and reject when unconfigured.
- **Hardcoded token in POS sync.** [admin/controller/catalog/sync.php](admin/controller/catalog/sync.php) inlines a Bearer JWT — and it is the well-known jwt.io demo token, so the sync is not really authenticating. Every other extension correctly stores credentials in `oc_setting`; this should too.
- **`courier_service` and `next_day_trishaw_delivery_rate` are the same extension twice.** Diffing them with names normalised yields zero substantive delta across ~770 lines, identical schemas, identical seed rates. Any fix to one must be mirrored to the other until they are merged into one rate-table-driven extension.
- **Tracked files that shouldn't be.** `system/storage/logs/error.log` (live errors with full server paths), `DB/elc.zip` (a 44MB SQL dump), and ~10 extension build `.zip`s are committed. `.gitignore` covers only `system/storage/cache/` and `image/`.
- **Production hardening not applied.** `system/storage/` sits inside the web root, `.htaccess.txt` is still unrenamed (so SEO URLs and directory protections are inactive), and the admin directory is still `admin/`. Fine on localhost; all three need attention before a live deploy.

## Conventions

Match OpenCart core style, which the custom extensions already follow: tabs for indentation, PHPDoc blocks with `@param`/`@return` on public methods, typed signatures and return types, plain namespaced classes. Never interpolate request data into SQL — use `$this->db->escape()`, or `real_escape_string()` in the standalone installers, which is the pattern already used there.

All user-facing strings go through language files (`$_['key']` arrays) rather than being hardcoded in controllers or templates.

Git history uses one commit per feature with short lowercase subjects (`store-selector`, `pos-sync`, `notify-me-buttton`).
