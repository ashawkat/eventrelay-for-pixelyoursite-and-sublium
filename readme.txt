=== EventRelay – Bridge for PixelYourSite and Sublium ===
Contributors: betatech, adnanshawkat
Tags: woocommerce, subscriptions, tracking, analytics, pixelyoursite
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: sublium-subscriptions-for-woocommerce
Stable tag: 1.2.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Third-party bridge by Betatech. Exclude Sublium subscription renewals from PixelYourSite Purchase events and log what was sent or blocked.

== Description ==

This is an independent plugin by Betatech. It is not affiliated with, endorsed by, or an official product of PixelYourSite or Sublium.

PixelYourSite Pro only enables “Exclude renewals from the Purchase event” when official WooCommerce Subscriptions is installed. Stores that use Sublium Subscriptions cannot turn those checkboxes on, so every automatic renewal is sent to connected pixels as a new Purchase.

This plugin is a small third-party bridge. It does not fork PixelYourSite. It detects Sublium renewals and blocks those Purchase events using PixelYourSite’s own filters and APIs.

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

WordPress will not activate this plugin unless Sublium Subscriptions (free) is installed (`Requires Plugins`):

* WordPress 6.0 or later
* PHP 7.4 or later
* Sublium Subscriptions (`sublium-subscriptions-for-woocommerce`)
* PixelYourSite free or Pro (runtime; Pro is not in the WordPress.org directory)
* WooCommerce 7.0 or later (needed at runtime because Sublium and PixelYourSite Woo events use it)

Sublium Pro is an add-on and is not required. PixelYourSite Pro works without the free plugin.

== Installation ==

1. Upload the `eventrelay-for-pixelyoursite-and-sublium` folder to `/wp-content/plugins/`
2. Activate **EventRelay – Bridge for PixelYourSite and Sublium** through the Plugins screen
3. Open **EventRelay** in the WordPress admin menu
4. Confirm PixelYourSite (free or Pro) and Sublium Subscriptions (free) are active

== Frequently Asked Questions ==

= Is this an official PixelYourSite or Sublium plugin? =

No. It is a third-party integration developed and owned by Betatech. It is not affiliated with PixelYourSite or Sublium.

= Does this replace PixelYourSite? =

No. It only bridges PixelYourSite to Sublium Subscriptions so renewal exclusion and FirstTimeBuyer work.

= Which plugins must be installed? =

Sublium Subscriptions (free) is declared in `Requires Plugins` (`sublium-subscriptions-for-woocommerce`). PixelYourSite free or Pro must also be active; Pro is detected at runtime because it is not in the WordPress.org directory. WooCommerce is not a header dependency, but tracking stays off until WooCommerce is available.

= Do I need official WooCommerce Subscriptions? =

No. If WooCommerce Subscriptions is not installed, the plugin provides the helpers PixelYourSite already calls. If it is installed, Sublium renewals are included in those results.

= Will the original checkout order be blocked? =

No. The first order that creates the subscription is never treated as a renewal.

= Where is the activity log? =

Open **EventRelay** in the admin menu. The last 150 decisions are also written to WooCommerce → Status → Logs (`eventrelay`).

== Screenshots ==

1. The EventRelay tracking dashboard: purchases sent, renewals blocked, FirstTimeBuyer events, activity log grouped by order, and settings that control what is sent or blocked.

== Changelog ==

= 1.2.4 =
* Rename to EventRelay – Bridge for PixelYourSite and Sublium to make third-party ownership clear
* Require only the Sublium free directory slug so WooCommerce and PixelYourSite Pro are not activation blockers
* Add capability checks on settings save and PixelYourSite setting sync

= 1.2.3 =
* Detect Sublium renewals using `_sublium_wcs_subscription_renewal` and Sublium's own `sublium_order_contains_renewal()` helper
* Treat Sublium payoff orders as renewals, and leave upgrades / resubscribes as new sales

= 1.2.2 =
* Detect WooCommerce Subscriptions renewal orders and show them in Blocked even when PixelYourSite skips silently
* Order checker and order-screen box so you can confirm whether a renewal will be blocked

= 1.2.1 =
* Group the activity log by order so destinations sit under one order card

= 1.2.0 =
* Dashboard for sent and blocked events
* FirstTimeBuyer on a customer’s first paid sale
* Activity log and settings

= 1.0.0 =
* Initial release
