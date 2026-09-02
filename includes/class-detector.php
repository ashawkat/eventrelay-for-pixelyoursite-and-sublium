<?php
/**
 * Detect Sublium renewal orders and classify automatic vs manual.
 *
 * @package PYS_Sublium_Bridge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PYS_Sublium_Bridge_Detector
 */
class PYS_Sublium_Bridge_Detector {

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
		add_action( 'sublium_wcs_before_process_renewal_payment', array( $this, 'mark_automatic_renewal' ), 5, 2 );
		add_action( 'sublium_wcs_subscription_renewal_payment_complete', array( $this, 'mark_automatic_renewal' ), 5, 2 );
	}

	/**
	 * Stamp a renewal order as automatic when Sublium processes scheduled billing.
	 *
	 * @param mixed         $subscription Subscription object or ID.
	 * @param WC_Order|null $order        Renewal order.
	 * @return void
	 */
	public function mark_automatic_renewal( $subscription, $order = null ) {
		$order = $this->normalize_order( $order );

		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( '_sublium_subscription_renewal', 'yes' );
		$order->update_meta_data( PYS_Sublium_Bridge_Plugin::RENEWAL_TYPE_META, 'automatic' );
		$order->save();
	}

	/**
	 * Whether the order is a Sublium (or marked) subscription renewal.
	 *
	 * @param WC_Order|int $order Order.
	 * @return bool
	 */
	public function is_renewal( $order ) {
		$order = $this->normalize_order( $order );

		if ( ! $order ) {
			return false;
		}

		return (bool) apply_filters( 'pys_sublium_bridge_is_renewal', $this->detect_renewal( $order ), $order );
	}

	/**
	 * Renewal type: automatic or manual.
	 *
	 * @param WC_Order|int $order Order.
	 * @return string
	 */
	public function get_renewal_type( $order ) {
		$order = $this->normalize_order( $order );

		if ( ! $order ) {
			return 'automatic';
		}

		$type = $this->detect_renewal_type( $order );

		return apply_filters( 'pys_sublium_bridge_renewal_type', $type, $order );
	}

	/**
	 * Whether Purchase tracking should be skipped for this order.
	 *
	 * @param WC_Order|int $order Order.
	 * @return bool
	 */
	public function should_skip_purchase( $order ) {
		$order = $this->normalize_order( $order );

		if ( ! $order || ! $this->is_renewal( $order ) ) {
			return false;
		}

		$type = $this->get_renewal_type( $order );

		if ( 'manual' === $type ) {
			return (bool) PYS_Sublium_Bridge_Plugin::get_setting( 'exclude_manual_renewals' );
		}

		return (bool) PYS_Sublium_Bridge_Plugin::get_setting( 'exclude_auto_renewals' );
	}

	/**
	 * Subscription IDs linked to an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array<int>
	 */
	public function get_subscription_ids( $order ) {
		$ids = array();

		if ( function_exists( 'sublium_get_subscriptions_for_order' ) ) {
			$from_helper = sublium_get_subscriptions_for_order( $order );
			if ( is_array( $from_helper ) ) {
				$ids = array_merge( $ids, $from_helper );
			}
		}

		foreach ( array( '_sublium_wcs_subscription_id', '_sublium_subscription_id' ) as $key ) {
			$single = $order->get_meta( $key, true );
			if ( $single ) {
				$ids[] = $single;
			}

			if ( method_exists( $order, 'get_meta' ) ) {
				foreach ( (array) $order->get_meta( $key, false ) as $meta ) {
					if ( is_object( $meta ) && isset( $meta->value ) ) {
						$ids[] = $meta->value;
					} elseif ( is_numeric( $meta ) ) {
						$ids[] = $meta;
					}
				}
			}
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Load a Sublium subscription object.
	 *
	 * @param int|object $subscription Subscription.
	 * @return object|null
	 */
	public function get_subscription( $subscription ) {
		if ( is_object( $subscription ) ) {
			return $subscription;
		}

		$id = absint( $subscription );
		if ( ! $id ) {
			return null;
		}

		if ( function_exists( 'sublium_get_subscription' ) ) {
			$object = sublium_get_subscription( $id );
			if ( $object ) {
				return $object;
			}
		}

		if ( class_exists( '\Sublium_WCS\Includes\Controller\Subscriptions\Subscription' ) ) {
			try {
				return new \Sublium_WCS\Includes\Controller\Subscriptions\Subscription( $id );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Fall through.
			}
		}

		return null;
	}

	/**
	 * Detect renewal from order meta and Sublium links.
	 *
	 * Parent/checkout orders keep `_sublium_wcs_parent_order` and must never be treated as renewals.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	private function detect_renewal( $order ) {
		if ( $this->is_parent_subscription_order( $order ) ) {
			return false;
		}

		$flag = strtolower( (string) $order->get_meta( '_sublium_subscription_renewal', true ) );
		if ( in_array( $flag, array( 'yes', '1', 'true' ), true ) ) {
			return true;
		}

		if ( function_exists( 'sublium_get_subscription_renewal_orders' ) ) {
			foreach ( $this->get_subscription_ids( $order ) as $subscription_id ) {
				$renewal_ids = sublium_get_subscription_renewal_orders( $subscription_id );
				if ( is_array( $renewal_ids ) && in_array( (int) $order->get_id(), array_map( 'intval', $renewal_ids ), true ) ) {
					return true;
				}
			}
		}

		if ( $order->get_meta( PYS_Sublium_Bridge_Plugin::RENEWAL_TYPE_META, true ) ) {
			return true;
		}

		$order_type = strtolower( (string) $this->get_order_type_meta( $order ) );
		if ( in_array( $order_type, array( 'renewal', 'subscription_renewal' ), true ) ) {
			return true;
		}

		$created_via = method_exists( $order, 'get_created_via' ) ? (string) $order->get_created_via() : '';
		if ( in_array( $created_via, array( 'subscription', 'subscription_renewal', 'sublium_renewal' ), true ) ) {
			return true;
		}

		foreach ( $this->get_subscription_ids( $order ) as $subscription_id ) {
			$subscription = $this->get_subscription( $subscription_id );
			$parent_id    = $this->get_subscription_parent_order_id( $subscription );

			if ( $parent_id && (int) $parent_id !== (int) $order->get_id() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Classify renewal type.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function detect_renewal_type( $order ) {
		$stored = $order->get_meta( PYS_Sublium_Bridge_Plugin::RENEWAL_TYPE_META, true );
		if ( in_array( $stored, array( 'manual', 'automatic' ), true ) ) {
			return $stored;
		}

		$early = $order->get_meta( '_sublium_early_renewal', true );
		if ( in_array( strtolower( (string) $early ), array( 'yes', '1', 'true' ), true ) ) {
			return 'manual';
		}

		$created_via = method_exists( $order, 'get_created_via' ) ? (string) $order->get_created_via() : '';
		if ( in_array( $created_via, array( 'checkout', 'store-api', 'admin' ), true ) ) {
			return 'manual';
		}

		foreach ( $this->get_subscription_ids( $order ) as $subscription_id ) {
			$subscription = $this->get_subscription( $subscription_id );
			if ( $subscription && $this->subscription_is_manual( $subscription ) ) {
				return 'manual';
			}
		}

		return 'automatic';
	}

	/**
	 * Whether this is the original checkout order that created the subscription.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	private function is_parent_subscription_order( $order ) {
		$parent_meta = $order->get_meta( '_sublium_wcs_parent_order', true );

		return ! empty( $parent_meta );
	}

	/**
	 * Read Sublium / WP Fusion style order-type meta.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function get_order_type_meta( $order ) {
		foreach ( array( '_sublium_order_type', '_sublium_wcs_order_type', 'order_type' ) as $key ) {
			$value = $order->get_meta( $key, true );
			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Parent WooCommerce order ID for a Sublium subscription.
	 *
	 * @param object|null $subscription Subscription.
	 * @return int
	 */
	private function get_subscription_parent_order_id( $subscription ) {
		if ( ! $subscription ) {
			return 0;
		}

		if ( method_exists( $subscription, 'get_parent_order_id' ) ) {
			return (int) $subscription->get_parent_order_id();
		}

		if ( method_exists( $subscription, 'get_parent_id' ) ) {
			return (int) $subscription->get_parent_id();
		}

		if ( method_exists( $subscription, 'get_meta' ) ) {
			return (int) $subscription->get_meta( 'parent_order_id' );
		}

		if ( is_array( $subscription ) && isset( $subscription['parent_order_id'] ) ) {
			return (int) $subscription['parent_order_id'];
		}

		return 0;
	}

	/**
	 * Whether a Sublium subscription bills manually.
	 *
	 * @param object $subscription Subscription.
	 * @return bool
	 */
	private function subscription_is_manual( $subscription ) {
		if ( method_exists( $subscription, 'is_manual' ) ) {
			return (bool) $subscription->is_manual();
		}

		if ( method_exists( $subscription, 'get_requires_manual_renewal' ) ) {
			return (bool) $subscription->get_requires_manual_renewal();
		}

		if ( method_exists( $subscription, 'get_meta' ) ) {
			$flag = $subscription->get_meta( '_requires_manual_renewal' );
			return in_array( strtolower( (string) $flag ), array( 'yes', '1', 'true' ), true );
		}

		return false;
	}

	/**
	 * Whether this order is the customer's first paid sale (not a renewal).
	 *
	 * @param WC_Order|int $order Order.
	 * @return bool
	 */
	public function is_first_time_sale( $order ) {
		$order = $this->normalize_order( $order );

		if ( ! $order ) {
			return false;
		}

		if ( $this->is_renewal( $order ) ) {
			return false;
		}

		return ! $this->customer_has_prior_paid_order( $order );
	}

	/**
	 * Whether the customer already has another paid order.
	 *
	 * @param WC_Order $order Current order.
	 * @return bool
	 */
	private function customer_has_prior_paid_order( $order ) {
		$user_id = (int) $order->get_user_id();
		$email   = $order->get_billing_email();

		$args = array(
			'limit'        => 1,
			'exclude'      => array( $order->get_id() ),
			'status'       => array( 'processing', 'completed', 'on-hold' ),
			'return'       => 'ids',
			'date_created' => '>' . ( time() - ( DAY_IN_SECONDS * 540 ) ),
		);

		if ( $user_id ) {
			$args['customer_id'] = $user_id;
		} elseif ( $email ) {
			$args['customer'] = $email;
		} else {
			return false;
		}

		$found = wc_get_orders( $args );

		return ! empty( $found );
	}

	/**
	 * Normalize an order argument.
	 *
	 * @param mixed $order Order or ID.
	 * @return WC_Order|null
	 */
	private function normalize_order( $order ) {
		if ( $order instanceof WC_Order ) {
			return $order;
		}

		if ( ! $order || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$loaded = wc_get_order( $order );

		return $loaded instanceof WC_Order ? $loaded : null;
	}
}
