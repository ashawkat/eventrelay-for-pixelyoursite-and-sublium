<?php
/**
 * Plugin Name:       EventRelay – Bridge for PixelYourSite and Sublium
 * Description:       Third-party plugin by Betatech. Exclude Sublium subscription renewals from PixelYourSite Purchase events and log what was sent or blocked. Not affiliated with PixelYourSite or Sublium.
 * Version:           1.2.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, pixelyoursite, sublium-subscriptions-for-woocommerce
 * Author:            Betatech
 * Author URI:        https://betatech.co/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       eventrelay-for-pixelyoursite-and-sublium
 * WC requires at least: 7.0
 * WC tested up to:   10.3
 *
 * @package EventRelay
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

define( 'EVENTRELAY_VERSION', '1.2.4' );
define( 'EVENTRELAY_FILE', __FILE__ );
define( 'EVENTRELAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'EVENTRELAY_URL', plugin_dir_url( __FILE__ ) );
define( 'EVENTRELAY_BASENAME', plugin_basename( __FILE__ ) );

require_once EVENTRELAY_PATH . 'includes/class-plugin.php';

/**
 * Returns the main plugin instance.
 *
 * @return EventRelay_Plugin
 */
function eventrelay() {
	return EventRelay_Plugin::instance();
}

eventrelay();
