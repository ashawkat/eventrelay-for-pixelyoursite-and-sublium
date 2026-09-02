<?php
/**
 * Settings screen and PixelYourSite admin UI unlock.
 *
 * @package PYS_Sublium_Bridge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PYS_Sublium_Bridge_Admin
 */
class PYS_Sublium_Bridge_Admin {

	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'sync_pys_save' ), 10 );
		add_action( 'admin_init', array( $this, 'handle_clear_log' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_dependency_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_pys_admin_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
		add_action( 'update_option_' . PYS_Sublium_Bridge_Plugin::OPTION_KEY, array( $this, 'sync_settings_to_pys' ), 10, 0 );
		add_action( 'add_option_' . PYS_Sublium_Bridge_Plugin::OPTION_KEY, array( $this, 'sync_settings_to_pys' ), 10, 0 );
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'PYS Sublium Bridge', 'pys-sublium-bridge' ),
			__( 'PYS Bridge', 'pys-sublium-bridge' ),
			'manage_woocommerce',
			'pys-sublium-bridge',
			array( $this, 'render_settings_page' ),
			'dashicons-chart-area',
			56
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'pys_sublium_bridge',
			PYS_Sublium_Bridge_Plugin::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => PYS_Sublium_Bridge_Plugin::default_settings(),
			)
		);
	}

	/**
	 * Sanitize settings form.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,bool>
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'exclude_auto_renewals'   => ! empty( $input['exclude_auto_renewals'] ),
			'exclude_manual_renewals' => ! empty( $input['exclude_manual_renewals'] ),
			'send_first_time_buyer'   => ! empty( $input['send_first_time_buyer'] ),
			'logging_enabled'         => ! empty( $input['logging_enabled'] ),
		);
	}

	/**
	 * When PixelYourSite saves, keep both UIs in sync.
	 *
	 * If the admin script unlocked the native checkboxes, trust those posted values.
	 * Otherwise restore our settings so a PYS save cannot wipe the exclusions.
	 *
	 * @return void
	 */
	public function sync_pys_save() {
		if ( empty( $_POST['pys']['core'] ) || ! is_array( $_POST['pys']['core'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'pys_save_settings' ) ) {
			return;
		}

		$ui_unlocked = ! empty( $_POST['pys_sublium_bridge_ui'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $ui_unlocked ) {
			$posted = wp_unslash( $_POST['pys']['core'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			PYS_Sublium_Bridge_Plugin::update_settings(
				array(
					'exclude_auto_renewals'   => ! empty( $posted['woo_purchase_dont_track_auto_renewal'] ),
					'exclude_manual_renewals' => ! empty( $posted['woo_purchase_dont_track_manual_renewal'] ),
				)
			);
			return;
		}

		$_POST['pys']['core']['woo_purchase_dont_track_auto_renewal']   = PYS_Sublium_Bridge_Plugin::get_setting( 'exclude_auto_renewals' ) ? '1' : '0';
		$_POST['pys']['core']['woo_purchase_dont_track_manual_renewal'] = PYS_Sublium_Bridge_Plugin::get_setting( 'exclude_manual_renewals' ) ? '1' : '0';
	}

	/**
	 * Mirror our settings into PixelYourSite so its native skip path stays aligned.
	 *
	 * @return void
	 */
	public function sync_settings_to_pys() {
		if ( ! PYS_Sublium_Bridge_Plugin::is_pys_active() ) {
			return;
		}

		$pys = \PixelYourSite\PYS();
		if ( ! $pys || ! method_exists( $pys, 'updateOptions' ) ) {
			return;
		}

		$options = array(
			'woo_purchase_dont_track_auto_renewal'   => PYS_Sublium_Bridge_Plugin::get_setting( 'exclude_auto_renewals' ) ? 1 : 0,
			'woo_purchase_dont_track_manual_renewal' => PYS_Sublium_Bridge_Plugin::get_setting( 'exclude_manual_renewals' ) ? 1 : 0,
		);

		if ( PYS_Sublium_Bridge_Plugin::get_setting( 'send_first_time_buyer' ) ) {
			$options['woo_FirstTimeBuyer_enabled'] = 1;
			$options['woo_new_customer_enabled']   = 1;
		}

		$pys->updateOptions( $options );
	}

	/**
	 * Clear the activity log.
	 *
	 * @return void
	 */
	public function handle_clear_log() {
		if ( empty( $_POST['pys_sublium_bridge_clear_log'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		check_admin_referer( 'pys_sublium_bridge_clear_log' );

		PYS_Sublium_Bridge_Logger::instance()->clear();

		wp_safe_redirect( admin_url( 'admin.php?page=pys-sublium-bridge&log_cleared=1' ) );
		exit;
	}

	/**
	 * Styles for the settings / log table.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_settings_assets( $hook ) {
		if ( 'toplevel_page_pys-sublium-bridge' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'pys-sublium-bridge-admin',
			PYS_SUBLIUM_BRIDGE_URL . 'assets/admin.css',
			array(),
			PYS_SUBLIUM_BRIDGE_VERSION
		);

		wp_enqueue_script(
			'pys-sublium-bridge-dashboard',
			PYS_SUBLIUM_BRIDGE_URL . 'assets/dashboard.js',
			array(),
			PYS_SUBLIUM_BRIDGE_VERSION,
			true
		);
	}

	/**
	 * Missing-plugin notices.
	 *
	 * @return void
	 */
	public function maybe_show_dependency_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'toplevel_page_pys-sublium-bridge' !== $screen->id ) {
			return;
		}

		if ( ! PYS_Sublium_Bridge_Plugin::is_pys_active() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'PixelYourSite Pro is not active. Activate it so this bridge can block renewal Purchase events.', 'pys-sublium-bridge' ) . '</p></div>';
		}

		if ( ! PYS_Sublium_Bridge_Plugin::is_sublium_active() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Sublium Subscriptions is not detected on this site. The bridge will still load, but renewal detection needs Sublium to be active.', 'pys-sublium-bridge' ) . '</p></div>';
		}
	}

	/**
	 * Unlock PixelYourSite renewal checkboxes when Sublium is in use.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_pys_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'pixelyoursite' ) ) {
			return;
		}

		wp_enqueue_script(
			'pys-sublium-bridge-admin',
			PYS_SUBLIUM_BRIDGE_URL . 'assets/admin.js',
			array(),
			PYS_SUBLIUM_BRIDGE_VERSION,
			true
		);

		wp_localize_script(
			'pys-sublium-bridge-admin',
			'pysSubliumBridge',
			array(
				'excludeAuto'   => (bool) PYS_Sublium_Bridge_Plugin::get_setting( 'exclude_auto_renewals' ),
				'excludeManual' => (bool) PYS_Sublium_Bridge_Plugin::get_setting( 'exclude_manual_renewals' ),
				'notice'        => __( 'These options are unlocked for Sublium Subscriptions by PYS Sublium Bridge. Automatic and manual renewals can be excluded the same way as WooCommerce Subscriptions.', 'pys-sublium-bridge' ),
			)
		);
	}

	/**
	 * Dashboard markup.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = wp_parse_args( get_option( PYS_Sublium_Bridge_Plugin::OPTION_KEY, array() ), PYS_Sublium_Bridge_Plugin::default_settings() );
		$entries  = array_reverse( PYS_Sublium_Bridge_Logger::instance()->get_entries() );
		$stats    = PYS_Sublium_Bridge_Logger::instance()->get_stats();
		$pys_ok   = PYS_Sublium_Bridge_Plugin::is_pys_active();
		$sub_ok   = PYS_Sublium_Bridge_Plugin::is_sublium_active();

		$log_cleared = isset( $_GET['log_cleared'] ) ? sanitize_text_field( wp_unslash( $_GET['log_cleared'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '1' === $log_cleared ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Activity log cleared.', 'pys-sublium-bridge' ) . '</p></div>';
		}

		require PYS_SUBLIUM_BRIDGE_PATH . 'includes/views/dashboard.php';
	}

	/**
	 * Badge label for a log action.
	 *
	 * @param string $action Action key.
	 * @return string
	 */
	public static function action_label( $action ) {
		$labels = array(
			'blocked' => __( 'Renewal blocked', 'pys-sublium-bridge' ),
			'allowed' => __( 'Purchase sent', 'pys-sublium-bridge' ),
			'sent'    => __( 'FirstTimeBuyer sent', 'pys-sublium-bridge' ),
			'skipped' => __( 'Skipped', 'pys-sublium-bridge' ),
		);

		return isset( $labels[ $action ] ) ? $labels[ $action ] : $action;
	}

	/**
	 * Relative time for the activity feed.
	 *
	 * @param string $mysql_time MySQL datetime.
	 * @return string
	 */
	public static function time_ago( $mysql_time ) {
		if ( empty( $mysql_time ) ) {
			return '';
		}

		$timestamp = strtotime( $mysql_time );
		if ( ! $timestamp ) {
			return $mysql_time;
		}

		return sprintf(
			/* translators: %s: human time difference */
			__( '%s ago', 'pys-sublium-bridge' ),
			human_time_diff( $timestamp, current_time( 'timestamp' ) )
		);
	}
}
