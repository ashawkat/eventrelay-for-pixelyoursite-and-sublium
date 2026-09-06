<?php
/**
 * WooCommerce Subscriptions compatibility shims for PixelYourSite.
 *
 * When official WCS is missing, PixelYourSite never sees `wcs_order_contains_renewal()`
 * so its native "exclude renewals" check never runs. These shims map that API onto Sublium.
 * When WCS is present, we only filter its results so Sublium renewals are included.
 *
 * @package EventRelay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EventRelay_Compat
 */
class EventRelay_Compat {

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
		$this->maybe_define_wcs_functions();

		add_filter( 'woocommerce_subscriptions_is_renewal_order', array( $this, 'filter_is_renewal_order' ), 20, 2 );
		add_filter( 'woocommerce_subscriptions_is_early_renewal_order', array( $this, 'filter_is_early_renewal_order' ), 20, 2 );
	}

	/**
	 * Include Sublium renewals in WCS renewal detection.
	 *
	 * @param bool     $is_renewal Existing result.
	 * @param mixed    $order      Order.
	 * @return bool
	 */
	public function filter_is_renewal_order( $is_renewal, $order ) {
		if ( $is_renewal ) {
			return true;
		}

		return EventRelay_Detector::instance()->is_renewal( $order );
	}

	/**
	 * Include Sublium early / customer-initiated renewals.
	 *
	 * @param bool  $is_early Existing result.
	 * @param mixed $order    Order.
	 * @return bool
	 */
	public function filter_is_early_renewal_order( $is_early, $order ) {
		if ( $is_early ) {
			return true;
		}

		$detector = EventRelay_Detector::instance();

		return $detector->is_renewal( $order ) && 'manual' === $detector->get_renewal_type( $order );
	}

	/**
	 * Define the WCS helpers PixelYourSite calls when WCS is not installed.
	 *
	 * @return void
	 */
	private function maybe_define_wcs_functions() {
		if ( ! function_exists( 'wcs_order_contains_renewal' ) ) {
			/**
			 * Whether an order is a subscription renewal.
			 *
			 * PixelYourSite calls this WooCommerce Subscriptions helper. The name
			 * must match so PYS native renewal exclusion can run without WCS.
			 *
			 * @param WC_Order|int $order Order.
			 * @return bool
			 */
			function wcs_order_contains_renewal( $order ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
				$is_renewal = EventRelay_Detector::instance()->is_renewal( $order );

				/**
				 * Filter whether an order is a subscription renewal.
				 *
				 * @since 1.0.0
				 *
				 * @param bool           $is_renewal Whether the order is a renewal.
				 * @param WC_Order|int   $order      Order.
				 */
				return (bool) apply_filters( 'woocommerce_subscriptions_is_renewal_order', $is_renewal, $order ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			}
		}

		if ( ! function_exists( 'wcs_order_contains_early_renewal' ) ) {
			/**
			 * Whether an order is an early / customer-initiated renewal.
			 *
			 * @param WC_Order|int $order Order.
			 * @return bool
			 */
			function wcs_order_contains_early_renewal( $order ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
				$detector = EventRelay_Detector::instance();
				$is_early = $detector->is_renewal( $order ) && 'manual' === $detector->get_renewal_type( $order );

				/**
				 * Filter whether an order is an early renewal.
				 *
				 * @since 1.0.0
				 *
				 * @param bool           $is_early Whether the order is an early renewal.
				 * @param WC_Order|int   $order    Order.
				 */
				return (bool) apply_filters( 'woocommerce_subscriptions_is_early_renewal_order', $is_early, $order ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			}
		}

		if ( ! function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
			/**
			 * Subscriptions related to a renewal order.
			 *
			 * PixelYourSite calls `is_manual()` on each result.
			 *
			 * @param WC_Order|int $order Order.
			 * @return array<int,object>
			 */
			function wcs_get_subscriptions_for_renewal_order( $order ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
				$detector = EventRelay_Detector::instance();
				$order    = $order instanceof WC_Order ? $order : ( function_exists( 'wc_get_order' ) ? wc_get_order( $order ) : null );

				if ( ! $order instanceof WC_Order ) {
					return array();
				}

				$subscriptions = array();

				foreach ( $detector->get_subscription_ids( $order ) as $subscription_id ) {
					$subscription = $detector->get_subscription( $subscription_id );
					if ( $subscription ) {
						$subscriptions[ $subscription_id ] = new EventRelay_Subscription_Adapter( $subscription );
					}
				}

				return $subscriptions;
			}
		}
	}
}

/**
 * Minimal adapter so PixelYourSite can call is_manual() on Sublium subscriptions.
 */
class EventRelay_Subscription_Adapter {

	/**
	 * Underlying Sublium subscription.
	 *
	 * @var object
	 */
	private $subscription;

	/**
	 * Constructor.
	 *
	 * @param object $subscription Subscription.
	 */
	public function __construct( $subscription ) {
		$this->subscription = $subscription;
	}

	/**
	 * Whether the subscription renews manually.
	 *
	 * @return bool
	 */
	public function is_manual() {
		if ( method_exists( $this->subscription, 'is_manual' ) ) {
			return (bool) $this->subscription->is_manual();
		}

		if ( method_exists( $this->subscription, 'get_requires_manual_renewal' ) ) {
			return (bool) $this->subscription->get_requires_manual_renewal();
		}

		return false;
	}

	/**
	 * Proxy unknown calls to the wrapped object.
	 *
	 * @param string $name Method.
	 * @param array  $args Args.
	 * @return mixed
	 */
	public function __call( $name, $args ) {
		if ( method_exists( $this->subscription, $name ) ) {
			return $this->subscription->{$name}( ...$args );
		}

		return null;
	}
}
