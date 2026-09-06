<?php
/**
 * Settings screen and PixelYourSite admin UI unlock.
 *
 * @package EventRelay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EventRelay_Admin
 */
class EventRelay_Admin {

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
		add_filter( 'option_page_capability_eventrelay', array( $this, 'get_settings_capability' ) );
		add_action( 'admin_init', array( $this, 'sync_pys_save' ), 10 );
		add_action( 'admin_init', array( $this, 'handle_clear_log' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_dependency_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_pys_admin_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
		add_action( 'update_option_' . EventRelay_Plugin::OPTION_KEY, array( $this, 'sync_settings_to_pys' ), 10, 0 );
		add_action( 'add_option_' . EventRelay_Plugin::OPTION_KEY, array( $this, 'sync_settings_to_pys' ), 10, 0 );
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'EventRelay – Bridge for PixelYourSite and Sublium', 'eventrelay-for-pixelyoursite-and-sublium' ),
			__( 'EventRelay', 'eventrelay-for-pixelyoursite-and-sublium' ),
			$this->settings_capability(),
			'eventrelay',
			array( $this, 'render_settings_page' ),
			'dashicons-chart-area',
			56
		);
	}

	/**
	 * Capability for the settings screen.
	 *
	 * @return string
	 */
	private function settings_capability() {
		return EventRelay_Plugin::is_woocommerce_active() ? 'manage_woocommerce' : 'activate_plugins';
	}

	/**
	 * Public capability callback for the Settings API.
	 *
	 * @return string
	 */
	public function get_settings_capability() {
		return $this->settings_capability();
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'eventrelay',
			EventRelay_Plugin::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => EventRelay_Plugin::default_settings(),
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
		if ( ! current_user_can( $this->settings_capability() ) ) {
			return get_option( EventRelay_Plugin::OPTION_KEY, EventRelay_Plugin::default_settings() );
		}

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

		if ( ! current_user_can( $this->settings_capability() ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'pys_save_settings' ) ) {
			return;
		}

		$ui_unlocked = ! empty( $_POST['eventrelay_ui'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $ui_unlocked ) {
			$posted = wp_unslash( $_POST['pys']['core'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			EventRelay_Plugin::update_settings(
				array(
					'exclude_auto_renewals'   => ! empty( $posted['woo_purchase_dont_track_auto_renewal'] ),
					'exclude_manual_renewals' => ! empty( $posted['woo_purchase_dont_track_manual_renewal'] ),
				)
			);
			return;
		}

		$_POST['pys']['core']['woo_purchase_dont_track_auto_renewal']   = EventRelay_Plugin::get_setting( 'exclude_auto_renewals' ) ? '1' : '0';
		$_POST['pys']['core']['woo_purchase_dont_track_manual_renewal'] = EventRelay_Plugin::get_setting( 'exclude_manual_renewals' ) ? '1' : '0';
	}

	/**
	 * Mirror our settings into PixelYourSite so its native skip path stays aligned.
	 *
	 * @return void
	 */
	public function sync_settings_to_pys() {
		if ( ! EventRelay_Plugin::is_pys_active() ) {
			return;
		}

		$pys = \PixelYourSite\PYS();
		if ( ! $pys || ! method_exists( $pys, 'updateOptions' ) ) {
			return;
		}

		$options = array(
			'woo_purchase_dont_track_auto_renewal'   => EventRelay_Plugin::get_setting( 'exclude_auto_renewals' ) ? 1 : 0,
			'woo_purchase_dont_track_manual_renewal' => EventRelay_Plugin::get_setting( 'exclude_manual_renewals' ) ? 1 : 0,
		);

		if ( EventRelay_Plugin::get_setting( 'send_first_time_buyer' ) ) {
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
		if ( empty( $_POST['eventrelay_clear_log'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( $this->settings_capability() ) ) {
			return;
		}

		check_admin_referer( 'eventrelay_clear_log' );

		EventRelay_Logger::instance()->clear();

		wp_safe_redirect( admin_url( 'admin.php?page=eventrelay&log_cleared=1' ) );
		exit;
	}

	/**
	 * Styles for the settings / log table.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_settings_assets( $hook ) {
		if ( 'toplevel_page_eventrelay' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'eventrelay-admin',
			EVENTRELAY_URL . 'assets/admin.css',
			array(),
			EVENTRELAY_VERSION
		);

		wp_enqueue_script(
			'eventrelay-dashboard',
			EVENTRELAY_URL . 'assets/dashboard.js',
			array(),
			EVENTRELAY_VERSION,
			true
		);
	}

	/**
	 * Missing-plugin notices.
	 *
	 * @return void
	 */
	public function maybe_show_dependency_notice() {
		if ( ! current_user_can( $this->settings_capability() ) && ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$allowed = array( 'toplevel_page_eventrelay', 'plugins' );
		if ( ! $screen || ! in_array( $screen->id, $allowed, true ) ) {
			return;
		}

		$missing = EventRelay_Plugin::get_missing_required_plugins();
		if ( empty( $missing ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: comma-separated plugin names */
				__( 'EventRelay – Bridge for PixelYourSite and Sublium requires these plugins to be installed and active: %s.', 'eventrelay-for-pixelyoursite-and-sublium' ),
				implode( ', ', $missing )
			)
		);
		echo '</p></div>';
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
			'eventrelay-admin',
			EVENTRELAY_URL . 'assets/admin.js',
			array(),
			EVENTRELAY_VERSION,
			true
		);

		wp_localize_script(
			'eventrelay-admin',
			'pysSubliumBridge',
			array(
				'excludeAuto'   => (bool) EventRelay_Plugin::get_setting( 'exclude_auto_renewals' ),
				'excludeManual' => (bool) EventRelay_Plugin::get_setting( 'exclude_manual_renewals' ),
				'notice'        => __( 'These options are unlocked for Sublium Subscriptions by EventRelay. Automatic and manual renewals can be excluded the same way as WooCommerce Subscriptions. This is a third-party integration and is not affiliated with PixelYourSite or Sublium.', 'eventrelay-for-pixelyoursite-and-sublium' ),
			)
		);
	}

	/**
	 * Dashboard markup.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( $this->settings_capability() ) ) {
			return;
		}

		$settings = wp_parse_args( get_option( EventRelay_Plugin::OPTION_KEY, array() ), EventRelay_Plugin::default_settings() );
		$pys_ok   = EventRelay_Plugin::is_pys_active();
		$sub_ok   = EventRelay_Plugin::is_sublium_active();
		$inspect  = null;
		$entries  = array();
		$groups   = array();
		$stats    = array(
			'purchases_sent'   => 0,
			'renewals_blocked' => 0,
			'first_time_sent'  => 0,
			'today'            => 0,
		);

		$filter     = 'all';
		$pagination = array(
			'groups'   => array(),
			'page'     => 1,
			'pages'    => 1,
			'total'    => 0,
			'per_page' => 10,
			'from'     => 0,
			'to'       => 0,
		);

		if ( class_exists( 'EventRelay_Detector' ) && class_exists( 'EventRelay_Logger' ) ) {
			EventRelay_Detector::instance()->backfill_recent_renewals();
			$entries = array_reverse( EventRelay_Logger::instance()->get_entries() );
			$groups  = EventRelay_Logger::group_entries_by_order( $entries );
			$stats   = EventRelay_Logger::instance()->get_stats();

			$filter = isset( $_GET['eventrelay_filter'] ) ? sanitize_key( wp_unslash( $_GET['eventrelay_filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! in_array( $filter, array( 'all', 'allowed', 'sent', 'blocked' ), true ) ) {
				$filter = 'all';
			}

			$page       = isset( $_GET['pysb_paged'] ) ? absint( wp_unslash( $_GET['pysb_paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filtered   = EventRelay_Logger::filter_groups_by_action( $groups, $filter );
			$pagination = EventRelay_Logger::paginate_groups( $filtered, $page, 10 );
			$groups     = $pagination['groups'];

			$inspect_id = isset( $_GET['eventrelay_order'] ) ? absint( wp_unslash( $_GET['eventrelay_order'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $inspect_id ) {
				$inspect = EventRelay_Detector::instance()->inspect( $inspect_id );
			}
		}

		$log_cleared = isset( $_GET['log_cleared'] ) ? sanitize_text_field( wp_unslash( $_GET['log_cleared'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '1' === $log_cleared ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Activity log cleared.', 'eventrelay-for-pixelyoursite-and-sublium' ) . '</p></div>';
		}

		require EVENTRELAY_PATH . 'includes/views/dashboard.php';
	}

	/**
	 * Summary line for a grouped order.
	 *
	 * @param array<string,mixed> $group Grouped log rows.
	 * @return string
	 */
	public static function group_summary( $group ) {
		if ( ! empty( $group['renewal'] ) && ! empty( $group['actions']['blocked'] ) ) {
			return __( 'Sublium renewal. Purchase was blocked on every connected pixel.', 'eventrelay-for-pixelyoursite-and-sublium' );
		}

		if ( ! empty( $group['first_time'] ) ) {
			return __( 'First-time sale. Purchase was allowed on the destinations below.', 'eventrelay-for-pixelyoursite-and-sublium' );
		}

		if ( ! empty( $group['actions']['allowed'] ) ) {
			return __( 'Not a renewal. Purchase was allowed on the destinations below.', 'eventrelay-for-pixelyoursite-and-sublium' );
		}

		return __( 'Tracking decisions for this order.', 'eventrelay-for-pixelyoursite-and-sublium' );
	}

	/**
	 * Dashboard URL that keeps the current filter / order checker.
	 *
	 * @param array<string,mixed> $args Query args to merge. Pass null to drop a key.
	 * @return string
	 */
	public static function dashboard_url( $args = array() ) {
		$query = array( 'page' => 'eventrelay' );

		$filter = isset( $_GET['eventrelay_filter'] ) ? sanitize_key( wp_unslash( $_GET['eventrelay_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $filter, array( 'allowed', 'sent', 'blocked' ), true ) ) {
			$query['eventrelay_filter'] = $filter;
		}

		$order_id = isset( $_GET['eventrelay_order'] ) ? absint( wp_unslash( $_GET['eventrelay_order'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $order_id ) {
			$query['eventrelay_order'] = $order_id;
		}

		$query = array_merge( $query, $args );

		foreach ( $query as $key => $value ) {
			if ( null === $value || '' === $value ) {
				unset( $query[ $key ] );
			}
		}

		return add_query_arg( $query, admin_url( 'admin.php' ) );
	}

	/**
	 * HTML pagination for the activity feed.
	 *
	 * @param array<string,mixed> $pagination Page slice.
	 * @return string
	 */
	public static function pagination_html( $pagination ) {
		$pages = isset( $pagination['pages'] ) ? (int) $pagination['pages'] : 1;
		if ( $pages < 2 ) {
			return '';
		}

		$current = isset( $pagination['page'] ) ? (int) $pagination['page'] : 1;
		$base    = self::dashboard_url( array( 'pysb_paged' => 999999999 ) ) . '#pysb-activity';
		$base    = str_replace( '999999999', '%#%', esc_url_raw( $base ) );

		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => $current,
				'total'     => $pages,
				'type'      => 'list',
				'end_size'  => 1,
				'mid_size'  => 1,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			)
		);

		return is_string( $links ) ? $links : '';
	}

	/**
	 * Badge label for a log action.
	 *
	 * @param string $action Action key.
	 * @return string
	 */
	public static function action_label( $action ) {
		$labels = array(
			'blocked' => __( 'Renewal blocked', 'eventrelay-for-pixelyoursite-and-sublium' ),
			'allowed' => __( 'Purchase sent', 'eventrelay-for-pixelyoursite-and-sublium' ),
			'sent'    => __( 'FirstTimeBuyer sent', 'eventrelay-for-pixelyoursite-and-sublium' ),
			'skipped' => __( 'Skipped', 'eventrelay-for-pixelyoursite-and-sublium' ),
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
			__( '%s ago', 'eventrelay-for-pixelyoursite-and-sublium' ),
			human_time_diff( $timestamp, current_time( 'timestamp' ) )
		);
	}
}
