# PYS Sublium Bridge

![PYS Sublium Bridge](.wordpress-org/banner-1544x500.png)

WordPress plugin that connects [PixelYourSite Pro](https://www.pixelyoursite.com/) to [Sublium Subscriptions](https://sublium.com/). It stops subscription **renewals** from being sent as **Purchase** events, sends PixelYourSite’s **FirstTimeBuyer** event on a customer’s first sale, and shows every decision on an admin dashboard.

The WordPress.org plugin directory uses `readme.txt`. This file is the GitHub overview.

## Why it exists

PixelYourSite Pro only enables “Exclude renewals from the Purchase event” when official **WooCommerce Subscriptions** is installed. Stores that use **Sublium** cannot turn those checkboxes on, so every automatic renewal is sent to Meta, Google, TikTok, OpenAI, and other connected pixels as a new Purchase.

That inflates conversion data. This plugin is a small bridge: it does not fork PixelYourSite. It detects Sublium renewals and blocks those Purchase events, using PixelYourSite’s own filters and APIs.

## What it does

- Blocks **Purchase** (browser + Conversion API / Measurement Protocol) for Sublium automatic and/or manual renewals
- Unlocks the greyed-out renewal checkboxes on PixelYourSite → WooCommerce Events
- Sends PixelYourSite’s **FirstTimeBuyer** event **with Purchase** on a customer’s first paid order
- Never sends FirstTimeBuyer on a Sublium renewal
- Keeps a readable **dashboard** of what was sent or blocked

### What you should see

| Order type | Purchase | FirstTimeBuyer |
|---|---|---|
| First paid checkout | Sent | Sent |
| Repeat one-time customer | Sent | Not sent |
| Sublium renewal | Blocked | Not sent |

## Requirements

Declared in the plugin header and `readme.txt` as `Requires Plugins`:

- WordPress 6.0+
- PHP 7.4+
- WooCommerce 7.0+ (`woocommerce`)
- PixelYourSite Pro (`pixelyoursite-pro`)
- Sublium Subscriptions (`sublium-subscriptions-for-woocommerce`)

WordPress will refuse to activate this plugin if those plugins are not installed. Sublium Pro is fine if the free Sublium plugin is also present.

## Install

1. Copy this folder into `wp-content/plugins/pys-sublium-bridge`.
2. Activate **PYS Sublium Bridge**.
3. Open **PYS Bridge** in the WordPress admin menu.

## Settings

All of these live on the dashboard:

- **Block automatic renewals** — scheduled Sublium charges do not fire Purchase
- **Block manual / early renewals** — customer-initiated or manual renewals do not fire Purchase
- **Send FirstTimeBuyer on first sale** — also turns on FirstTimeBuyer inside PixelYourSite
- **Keep this activity log** — last 150 decisions, also written to WooCommerce → Status → Logs (`pys-sublium-bridge`)

Both renewal exclusions default to **on**.

## Dashboard

The **PYS Bridge** menu shows:

- Connection status (PixelYourSite, Sublium, logging)
- Totals: purchases sent, renewals blocked, FirstTimeBuyer sent, events today
- Filterable activity feed (All / Purchases / First-time / Blocked)
- Settings and a short “what you should see” checklist

## How renewal detection works

An order is treated as a Sublium renewal when any of these are true:

- `_sublium_wcs_subscription_renewal` is `yes` (Sublium’s canonical flag; same as `sublium_order_contains_renewal()`)
- `_sublium_wcs_subscription_payoff` is `yes` (installment payoff, treated like a renewal)
- `created_via` is `sublium_subscription_renewal` or `sublium_subscription_payoff`
- Official WooCommerce Subscriptions `_subscription_renewal` is set
- The bridge stamped `_pys_sublium_renewal_type` during `sublium_wcs_subscription_renewal_payment_complete`

The original checkout (`_sublium_wcs_parent_order`) is **never** treated as a renewal. Upgrades and resubscribes are new sales, not renewals.

If official WooCommerce Subscriptions is not installed, the plugin also provides the `wcs_order_contains_renewal()` helpers PixelYourSite already calls.

## How events are blocked or sent

- `pys_validate_pixel_event` — browser Purchase
- `pys_disable_server_event_filter` — Facebook CAPI, GA4 MP, TikTok, OpenAI, Pinterest
- FirstTimeBuyer is generated through PixelYourSite (`EventsWoo` / pixel `generateEvents`) and sent on the same order statuses used for Advanced Purchase Tracking

## Plugin structure

```
pys-sublium-bridge.php
includes/
  class-plugin.php
  class-detector.php
  class-compat.php
  class-tracking.php
  class-first-time-buyer.php
  class-logger.php
  class-admin.php
  views/dashboard.php
assets/
  admin.css
  admin.js
  dashboard.js
  fonts/                  Google Sans (SIL Open Font License 1.1, self-hosted)
```

## WordPress.org assets

Icon and banner files live in `.wordpress-org/`. They are **not** part of the plugin zip. After the plugin is approved, upload them to the SVN `assets/` folder:

- `icon-256x256.png` / `icon-128x128.png`
- `banner-1544x500.png` / `banner-772x250.png`

## License

GPL-3.0-or-later
