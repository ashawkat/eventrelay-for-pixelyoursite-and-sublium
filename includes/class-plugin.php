<?php
/**
 * Main plugin class.
 *
 * @package PYS_Sublium_Bridge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PYS_Sublium_Bridge_Plugin
 */
final class PYS_Sublium_Bridge_Plugin {

	/**
	 * Option key.
	 */
	const OPTION_KEY = 'pys_sublium_bridge';

	/**
	 * Order meta written when Sublium creates a renewal.
	 */
	const RENEWAL_TYPE_META = '_pys_sublium_renewal_type';

	/**
	 * Plugin instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		register_activation_hook( PYS_SUBLIUM_BRIDGE_FILE, array( $this, 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		add_filter( 'plugin_action_links_' . PYS_SUBLIUM_BRIDGE_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Seed defaults on activation.
	 *
	 * @return void
	 */
	public function activate() {
		$current = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $current ) ) {
			add_option( self::OPTION_KEY, self::default_settings() );
		}
	}

	/**
	 * Default settings. Both exclusions are on so renewals stop inflating ad data immediately.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_settings() {
		return array(
			'exclude_auto_renewals'   => true,
			'exclude_manual_renewals' => true,
			'send_first_time_buyer'   => true,
			'logging_enabled'         => true,
		);
	}

	/**
	 * Load components after WooCommerce and PixelYourSite are available.
	 *
	 * @return void
	 */
	public function init() {
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-logger.php';
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-detector.php';
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-compat.php';
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-tracking.php';
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-first-time-buyer.php';
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-admin.php';

		PYS_Sublium_Bridge_Logger::instance();
		PYS_Sublium_Bridge_Detector::instance();
		PYS_Sublium_Bridge_Compat::instance();
		PYS_Sublium_Bridge_Tracking::instance();
		PYS_Sublium_Bridge_First_Time_Buyer::instance();

		if ( is_admin() ) {
			PYS_Sublium_Bridge_Admin::instance();
		}
	}

	/**
	 * Plugin list settings link.
	 *
	 * @param array<int,string> $links Existing links.
	 * @return array<int,string>
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'admin.php?page=pys-sublium-bridge' );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'pys-sublium-bridge' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Get a setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::default_settings() );

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Persist settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	public static function update_settings( $settings ) {
		$clean = wp_parse_args( $settings, self::default_settings() );
		$clean = array(
			'exclude_auto_renewals'   => ! empty( $clean['exclude_auto_renewals'] ),
			'exclude_manual_renewals' => ! empty( $clean['exclude_manual_renewals'] ),
			'send_first_time_buyer'   => ! empty( $clean['send_first_time_buyer'] ),
			'logging_enabled'         => ! empty( $clean['logging_enabled'] ),
		);

		update_option( self::OPTION_KEY, $clean, false );
	}

	/**
	 * Whether Sublium is available.
	 *
	 * @return bool
	 */
	public static function is_sublium_active() {
		return function_exists( 'sublium_get_subscription' )
			|| function_exists( 'sublium_order_contains_renewal' )
			|| function_exists( 'sublium_init' )
			|| class_exists( '\Sublium_WCS\Includes\Controller\Subscriptions\Subscription' )
			|| class_exists( '\Sublium\Includes\database\Subscriptions' );
	}

	/**
	 * Whether PixelYourSite Pro is available.
	 *
	 * @return bool
	 */
	public static function is_pys_active() {
		return function_exists( 'PixelYourSite\\PYS' ) || class_exists( '\PixelYourSite\PYS' );
	}

	/**
	 * Whether WooCommerce Subscriptions is the active official plugin.
	 *
	 * @return bool
	 */
	public static function is_wcs_active() {
		return function_exists( 'wcs_order_contains_renewal' )
			&& class_exists( 'WC_Subscriptions' );
	}
}
