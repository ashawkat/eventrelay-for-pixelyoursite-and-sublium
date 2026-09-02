<?php
/**
 * Plugin Name:       PYS Sublium Bridge
 * Plugin URI:        https://betatech.co/
 * Description:       Exclude Sublium subscription renewals from PixelYourSite Purchase events and log what was sent or blocked.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Betatech
 * Author URI:        https://betatech.co/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       pys-sublium-bridge
 * WC requires at least: 7.0
 * WC tested up to:   10.3
 *
 * @package PYS_Sublium_Bridge
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

define( 'PYS_SUBLIUM_BRIDGE_VERSION', '1.2.0' );
define( 'PYS_SUBLIUM_BRIDGE_FILE', __FILE__ );
define( 'PYS_SUBLIUM_BRIDGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PYS_SUBLIUM_BRIDGE_URL', plugin_dir_url( __FILE__ ) );
define( 'PYS_SUBLIUM_BRIDGE_BASENAME', plugin_basename( __FILE__ ) );

require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-plugin.php';

/**
 * Returns the main plugin instance.
 *
 * @return PYS_Sublium_Bridge_Plugin
 */
function pys_sublium_bridge() {
	return PYS_Sublium_Bridge_Plugin::instance();
}

pys_sublium_bridge();
