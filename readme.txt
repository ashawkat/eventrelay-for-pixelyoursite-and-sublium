=== PYS Sublium Bridge ===
Contributors: ashawkat
Tags: woocommerce, subscriptions, tracking, facebook, analytics
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Exclude Sublium subscription renewals from PixelYourSite Purchase events and log what was sent or blocked.

== Description ==

PixelYourSite Pro only enables “Exclude renewals from the Purchase event” when official WooCommerce Subscriptions is installed. Stores that use Sublium Subscriptions cannot turn those checkboxes on, so every automatic renewal is sent to Meta, Google, TikTok, and other connected pixels as a new Purchase.

This plugin is a small bridge. It does not fork PixelYourSite. It detects Sublium renewals and blocks those Purchase events using PixelYourSite’s own filters and APIs.

= What it does =

* Blocks Purchase (browser and Conversion API / Measurement Protocol) for Sublium automatic and/or manual renewals
* Unlocks the greyed-out renewal checkboxes on PixelYourSite → WooCommerce Events
* Sends PixelYourSite’s FirstTimeBuyer event with Purchase on a customer’s first paid order
* Never sends FirstTimeBuyer on a Sublium renewal
* Keeps a readable dashboard of what was sent or blocked

= What you should see =

* First paid checkout: Purchase sent, FirstTimeBuyer sent
* Repeat one-time customer: Purchase sent, FirstTimeBuyer not sent
* Sublium renewal: Purchase blocked, FirstTimeBuyer not sent

= Requirements =

* WordPress 6.0 or later
* PHP 7.4 or later
* WooCommerce 7.0 or later
* PixelYourSite Pro
* Sublium Subscriptions

== Installation ==

1. Upload the `pys-sublium-bridge` folder to `/wp-content/plugins/`
2. Activate **PYS Sublium Bridge** through the Plugins screen
3. Open **PYS Bridge** in the WordPress admin menu
4. Confirm PixelYourSite Pro and Sublium Subscriptions are active

== Frequently Asked Questions ==

= Does this replace PixelYourSite? =

No. It only bridges PixelYourSite Pro to Sublium Subscriptions so renewal exclusion and FirstTimeBuyer work.

= Do I need official WooCommerce Subscriptions? =

No. If WooCommerce Subscriptions is not installed, the plugin provides the helpers PixelYourSite already calls. If it is installed, Sublium renewals are included in those results.

= Will the original checkout order be blocked? =

No. The first order that creates the subscription is never treated as a renewal.

= Where is the activity log? =

Open **PYS Bridge** in the admin menu. The last 150 decisions are also written to WooCommerce → Status → Logs (`pys-sublium-bridge`).

== Changelog ==

= 1.2.0 =
* Dashboard for sent and blocked events
* FirstTimeBuyer on a customer’s first paid sale
* Activity log and settings

= 1.0.0 =
* Initial release
