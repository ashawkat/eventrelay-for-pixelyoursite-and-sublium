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

		add_filter( 'wp_plugin_dependencies_slug', array( $this, 'map_dependency_slug' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		add_filter( 'plugin_action_links_' . PYS_SUBLIUM_BRIDGE_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Seed defaults on activation after required plugins are confirmed.
	 *
	 * @return void
	 */
	public function activate() {
		$missing = self::get_missing_installed_plugins();

		if ( ! empty( $missing ) ) {
			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( PYS_SUBLIUM_BRIDGE_BASENAME );
			}

			$message = sprintf(
				/* translators: %s: comma-separated plugin names */
				__( 'PYS Sublium Bridge requires these plugins to be installed: %s.', 'pys-sublium-bridge' ),
				implode( ', ', $missing )
			);

			wp_die( esc_html( $message ), esc_html__( 'Plugin dependency check failed', 'pys-sublium-bridge' ), array( 'back_link' => true ) );
		}

		$current = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $current ) ) {
			add_option( self::OPTION_KEY, self::default_settings() );
		}
	}

	/**
	 * Map header slugs onto installed premium folders when the free slug is absent.
	 *
	 * @param string $slug Dependency slug from Requires Plugins.
	 * @return string
	 */
	public function map_dependency_slug( $slug ) {
		if ( 'pixelyoursite-pro' === $slug && $this->plugin_directory_exists( 'pixelyoursite-pro' ) ) {
			return $slug;
		}

		if ( 'sublium-subscriptions-for-woocommerce' === $slug && ! $this->plugin_directory_exists( $slug ) && $this->plugin_directory_exists( 'sublium-subscriptions-for-woocommerce-pro' ) ) {
			return 'sublium-subscriptions-for-woocommerce-pro';
		}

		return $slug;
	}

	/**
	 * Whether a plugin directory exists under wp-content/plugins.
	 *
	 * @param string $slug Plugin folder name.
	 * @return bool
	 */
	private function plugin_directory_exists( $slug ) {
		return is_dir( trailingslashit( WP_PLUGIN_DIR ) . $slug );
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
		if ( is_admin() ) {
			require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-admin.php';
			PYS_Sublium_Bridge_Admin::instance();
		}

		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-logger.php';
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-detector.php';
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-compat.php';
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-tracking.php';
		require_once PYS_SUBLIUM_BRIDGE_PATH . 'includes/class-first-time-buyer.php';

		PYS_Sublium_Bridge_Logger::instance();
		PYS_Sublium_Bridge_Detector::instance();
		PYS_Sublium_Bridge_Compat::instance();

		if ( self::is_pys_active() ) {
			PYS_Sublium_Bridge_Tracking::instance();
			PYS_Sublium_Bridge_First_Time_Buyer::instance();
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
	 * Required plugins that are not installed (used on activation).
	 *
	 * Runtime function checks can fail during activation because some
	 * plugins boot on plugins_loaded, which has already run.
	 *
	 * @return array<int,string>
	 */
	public static function get_missing_installed_plugins() {
		$missing = array();

		if ( ! self::plugin_file_exists( 'woocommerce/woocommerce.php' ) && ! self::is_woocommerce_active() ) {
			$missing[] = 'WooCommerce';
		}

		if ( ! self::plugin_file_exists( 'pixelyoursite-pro/pixelyoursite-pro.php' ) && ! self::is_pys_active() ) {
			$missing[] = 'PixelYourSite Pro';
		}

		if ( ! self::plugin_file_exists( 'sublium-subscriptions-for-woocommerce/sublium-subscriptions-for-woocommerce.php' )
			&& ! self::plugin_file_exists( 'sublium-subscriptions-for-woocommerce-pro/sublium-subscriptions-for-woocommerce-pro.php' )
			&& ! self::is_sublium_active() ) {
			$missing[] = 'Sublium Subscriptions';
		}

		return $missing;
	}

	/**
	 * Whether a plugin main file exists in wp-content/plugins.
	 *
	 * @param string $plugin_file Plugin basename, e.g. woocommerce/woocommerce.php.
	 * @return bool
	 */
	public static function plugin_file_exists( $plugin_file ) {
		return file_exists( trailingslashit( WP_PLUGIN_DIR ) . ltrim( $plugin_file, '/' ) );
	}

	/**
	 * Required plugins that are not currently available.
	 *
	 * @return array<int,string>
	 */
	public static function get_missing_required_plugins() {
		$missing = array();

		if ( ! self::is_woocommerce_active() ) {
			$missing[] = 'WooCommerce';
		}

		if ( ! self::is_pys_active() ) {
			$missing[] = 'PixelYourSite Pro';
		}

		if ( ! self::is_sublium_active() ) {
			$missing[] = 'Sublium Subscriptions';
		}

		return $missing;
	}

	/**
	 * Whether WooCommerce is available.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
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
