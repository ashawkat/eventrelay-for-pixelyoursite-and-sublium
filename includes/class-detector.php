<?php
/**
 * Detect Sublium renewal orders and classify automatic vs manual.
 *
 * @package EventRelay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EventRelay_Detector
 */
class EventRelay_Detector {

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
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'mark_automatic_renewal' ), 5, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_log_blocked_renewal' ), 20, 4 );
		add_action( 'add_meta_boxes', array( $this, 'register_order_metabox' ) );
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

		if ( ! $order && is_object( $subscription ) && method_exists( $subscription, 'get_last_order' ) ) {
			$order = $this->normalize_order( $subscription->get_last_order( 'ids', 'renewal' ) );
		}

		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( '_sublium_wcs_subscription_renewal', 'yes' );
		$order->update_meta_data( '_sublium_subscription_renewal', 'yes' );
		$order->update_meta_data( EventRelay_Plugin::RENEWAL_TYPE_META, 'automatic' );
		$order->save();

		$this->log_blocked_renewal(
			$order,
			sprintf(
				/* translators: %d: order ID */
				__( 'Sublium processed an automatic renewal for order #%d. Purchase will be blocked.', 'eventrelay-for-pixelyoursite-and-sublium' ),
				$order->get_id()
			)
		);
	}

	/**
	 * Log a blocked renewal when a paid status is reached, even if PixelYourSite skipped silently.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Previous status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order      Order.
	 * @return void
	 */
	public function maybe_log_blocked_renewal( $order_id, $old_status, $new_status, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$new_status = str_replace( 'wc-', '', $new_status );
		if ( ! in_array( $new_status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
			return;
		}

		if ( ! $this->should_skip_purchase( $order ) ) {
			return;
		}

		$this->log_blocked_renewal(
			$order,
			sprintf(
				/* translators: %d: order ID */
				__( 'Order #%d is a subscription renewal. Purchase was blocked so it was not sent as a new sale.', 'eventrelay-for-pixelyoursite-and-sublium' ),
				$order->get_id()
			)
		);
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

		return (bool) apply_filters( 'eventrelay_is_renewal', $this->detect_renewal( $order ), $order );
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

		return apply_filters( 'eventrelay_renewal_type', $type, $order );
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
			return (bool) EventRelay_Plugin::get_setting( 'exclude_manual_renewals' );
		}

		return (bool) EventRelay_Plugin::get_setting( 'exclude_auto_renewals' );
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

		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$wcs_subs = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );
			if ( is_array( $wcs_subs ) ) {
				$ids = array_merge( $ids, array_keys( $wcs_subs ) );
			}
		}

		foreach ( array( '_sublium_wcs_subscription_id', '_sublium_subscription_id', '_subscription_renewal' ) as $key ) {
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
	 * Sublium's canonical renewal flag is `_sublium_wcs_subscription_renewal` = yes
	 * (`sublium_order_contains_renewal()`). Parent checkout orders keep
	 * `_sublium_wcs_parent_order` and must never be treated as renewals.
	 * Upgrades and resubscribes are new sales, not renewals.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	private function detect_renewal( $order ) {
		if ( $this->is_parent_subscription_order( $order ) ) {
			return false;
		}

		if ( $this->is_upgrade_or_resubscribe( $order ) ) {
			return false;
		}

		if ( function_exists( 'sublium_order_contains_renewal' ) && sublium_order_contains_renewal( $order ) ) {
			return true;
		}

		foreach ( array(
			'_sublium_wcs_subscription_renewal',
			'_sublium_wcs_subscription_payoff',
			'_sublium_subscription_payoff',
			'_sublium_subscription_renewal',
		) as $key ) {
			if ( $this->meta_is_yes( $order, $key ) ) {
				return true;
			}
		}

		// Official WooCommerce Subscriptions stores the subscription ID on renewal orders.
		if ( $order->get_meta( '_subscription_renewal', true ) ) {
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

		if ( $order->get_meta( EventRelay_Plugin::RENEWAL_TYPE_META, true ) ) {
			return true;
		}

		$order_type = strtolower( (string) $this->get_order_type_meta( $order ) );
		if ( in_array( $order_type, array( 'renewal', 'subscription_renewal', 'payoff' ), true ) ) {
			return true;
		}

		$created_via = method_exists( $order, 'get_created_via' ) ? (string) $order->get_created_via() : '';
		if ( in_array(
			$created_via,
			array(
				'sublium_subscription_renewal',
				'sublium_subscription_payoff',
				'subscription',
				'subscription_renewal',
				'sublium_renewal',
			),
			true
		) ) {
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
		$stored = $order->get_meta( EventRelay_Plugin::RENEWAL_TYPE_META, true );
		if ( in_array( $stored, array( 'manual', 'automatic' ), true ) ) {
			return $stored;
		}

		if ( $order->get_meta( '_sublium_wcs_order_now', true ) ) {
			return 'manual';
		}

		if ( $this->meta_is_yes( $order, '_sublium_early_renewal' ) || $this->meta_is_yes( $order, '_sublium_wcs_early_renewal' ) ) {
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
	 * Sublium writes `_sublium_wcs_parent_order` on the first paid checkout.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	private function is_parent_subscription_order( $order ) {
		$parent_meta = $order->get_meta( '_sublium_wcs_parent_order', true );

		return ! empty( $parent_meta );
	}

	/**
	 * Whether this order is a Sublium upgrade or resubscribe (a new sale, not a renewal).
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	private function is_upgrade_or_resubscribe( $order ) {
		if ( function_exists( 'sublium_order_is_upgrade' ) && sublium_order_is_upgrade( $order ) ) {
			return true;
		}

		if ( function_exists( 'sublium_order_contains_resubscribe' ) && sublium_order_contains_resubscribe( $order ) ) {
			return true;
		}

		if ( $this->meta_is_yes( $order, '_sublium_wcs_upgrade_order' ) || $this->meta_is_yes( $order, '_sublium_wcs_resubscribe' ) ) {
			return true;
		}

		$created_via = method_exists( $order, 'get_created_via' ) ? (string) $order->get_created_via() : '';

		return in_array(
			$created_via,
			array(
				'sublium_subscription_upgrade',
				'sublium_subscription_resubscribe',
				'sublium_subscription_changes',
			),
			true
		);
	}

	/**
	 * Whether an order meta flag is a truthy yes value.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $key   Meta key.
	 * @return bool
	 */
	private function meta_is_yes( $order, $key ) {
		$flag = strtolower( (string) $order->get_meta( $key, true ) );

		return in_array( $flag, array( 'yes', '1', 'true' ), true );
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
			'limit'        => 2,
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

		$found = array_map( 'absint', (array) wc_get_orders( $args ) );
		$found = array_diff( $found, array( (int) $order->get_id() ) );

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

	/**
	 * Write one blocked log row per order.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $message Message.
	 * @return void
	 */
	private function log_blocked_renewal( $order, $message ) {
		if ( ! $this->should_skip_purchase( $order ) ) {
			return;
		}

		$logger = EventRelay_Logger::instance();
		if ( $logger->has_action_for_order( $order->get_id(), 'blocked' ) ) {
			return;
		}

		$logger->log(
			'blocked',
			array(
				'order_id'     => $order->get_id(),
				'event'        => 'Purchase',
				'channel'      => 'sublium',
				'platform'     => '',
				'renewal'      => true,
				'renewal_type' => $this->get_renewal_type( $order ),
				'first_time'   => false,
				'message'      => $message,
			)
		);
	}

	/**
	 * Record recent paid renewals that PixelYourSite skipped without hitting our filters.
	 *
	 * @return void
	 */
	public function backfill_recent_renewals() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'   => 40,
				'status'  => array( 'processing', 'completed', 'on-hold' ),
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order || ! $this->should_skip_purchase( $order ) ) {
				continue;
			}

			$this->log_blocked_renewal(
				$order,
				sprintf(
					/* translators: %d: order ID */
					__( 'Order #%d is a subscription renewal. Purchase was blocked so it was not sent as a new sale.', 'eventrelay-for-pixelyoursite-and-sublium' ),
					$order->get_id()
				)
			);
		}
	}

	/**
	 * Human-readable inspection of how this order would be treated.
	 *
	 * @param WC_Order|int $order Order.
	 * @return array<string,mixed>
	 */
	public function inspect( $order ) {
		$order = $this->normalize_order( $order );

		if ( ! $order ) {
			return array(
				'found'       => false,
				'order_id'    => 0,
				'headline'    => __( 'That order was not found.', 'eventrelay-for-pixelyoursite-and-sublium' ),
				'detail'      => '',
				'would_block' => false,
				'status'      => 'missing',
				'facts'       => array(),
			);
		}

		$is_parent   = $this->is_parent_subscription_order( $order );
		$is_renewal  = $this->is_renewal( $order );
		$would_block = $this->should_skip_purchase( $order );
		$type        = $is_renewal ? $this->get_renewal_type( $order ) : '';
		$sub_ids     = $this->get_subscription_ids( $order );
		$parent_id   = 0;

		foreach ( $sub_ids as $subscription_id ) {
			$parent_id = $this->get_subscription_parent_order_id( $this->get_subscription( $subscription_id ) );
			if ( $parent_id ) {
				break;
			}
		}

		if ( $is_parent ) {
			$headline = __( 'This is the original checkout that created the subscription.', 'eventrelay-for-pixelyoursite-and-sublium' );
			$detail   = __( 'Purchase should be sent. A later Sublium charge creates a different order, and that one is the renewal to block.', 'eventrelay-for-pixelyoursite-and-sublium' );
			$status   = 'parent';
		} elseif ( $would_block ) {
			$headline = __( 'This is a Sublium renewal. Purchase will be blocked.', 'eventrelay-for-pixelyoursite-and-sublium' );
			$detail   = __( 'If PixelYourSite tries to send Purchase for this order, the activity log will show Renewal blocked.', 'eventrelay-for-pixelyoursite-and-sublium' );
			$status   = 'blocked';
		} elseif ( $is_renewal ) {
			$headline = __( 'This is a Sublium renewal, but blocking is turned off for this type.', 'eventrelay-for-pixelyoursite-and-sublium' );
			$detail   = __( 'Turn on Block automatic renewals or Block manual / early renewals, then Purchase will be kept out of ad data.', 'eventrelay-for-pixelyoursite-and-sublium' );
			$status   = 'renewal-open';
		} else {
			$headline = __( 'This is not detected as a Sublium renewal.', 'eventrelay-for-pixelyoursite-and-sublium' );
			$detail   = __( 'Purchase will be sent. If you expected a block, this is likely the first subscription checkout, or Sublium has not marked the order as a renewal.', 'eventrelay-for-pixelyoursite-and-sublium' );
			$status   = 'allowed';
		}

		$sublium_renewal = $order->get_meta( '_sublium_wcs_subscription_renewal', true );
		if ( ! $sublium_renewal ) {
			$sublium_renewal = $order->get_meta( '_sublium_subscription_renewal', true );
		}

		$facts = array(
			__( 'Order', 'eventrelay-for-pixelyoursite-and-sublium' )                    => '#' . $order->get_id(),
			__( 'Created via', 'eventrelay-for-pixelyoursite-and-sublium' )              => $order->get_created_via() ? $order->get_created_via() : '—',
			__( 'Sublium renewal flag', 'eventrelay-for-pixelyoursite-and-sublium' )     => $sublium_renewal ? $sublium_renewal : '—',
			__( 'Sublium payoff', 'eventrelay-for-pixelyoursite-and-sublium' )           => $this->meta_is_yes( $order, '_sublium_wcs_subscription_payoff' ) || $this->meta_is_yes( $order, '_sublium_subscription_payoff' ) ? __( 'yes', 'eventrelay-for-pixelyoursite-and-sublium' ) : '—',
			__( 'WooCommerce renewal', 'eventrelay-for-pixelyoursite-and-sublium' )      => $order->get_meta( '_subscription_renewal', true ) ? '#' . $order->get_meta( '_subscription_renewal', true ) : '—',
			__( 'Bridge stamp', 'eventrelay-for-pixelyoursite-and-sublium' )             => $order->get_meta( EventRelay_Plugin::RENEWAL_TYPE_META, true ) ? $order->get_meta( EventRelay_Plugin::RENEWAL_TYPE_META, true ) : '—',
			__( 'Parent checkout meta', 'eventrelay-for-pixelyoursite-and-sublium' )     => $is_parent ? __( 'yes', 'eventrelay-for-pixelyoursite-and-sublium' ) : '—',
			__( 'Linked subscription', 'eventrelay-for-pixelyoursite-and-sublium' )      => $sub_ids ? implode( ', ', array_map( 'strval', $sub_ids ) ) : '—',
			__( 'Subscription parent order', 'eventrelay-for-pixelyoursite-and-sublium' ) => $parent_id ? '#' . $parent_id : '—',
			__( 'Renewal type', 'eventrelay-for-pixelyoursite-and-sublium' )             => $type ? $type : '—',
			__( 'PixelYourSite Purchase fired', 'eventrelay-for-pixelyoursite-and-sublium' ) => $order->get_meta( '_pys_purchase_event_fired', true ) ? __( 'yes — this would mean a leak', 'eventrelay-for-pixelyoursite-and-sublium' ) : __( 'no', 'eventrelay-for-pixelyoursite-and-sublium' ),
		);

		return array(
			'found'       => true,
			'order_id'    => $order->get_id(),
			'headline'    => $headline,
			'detail'      => $detail,
			'would_block' => $would_block,
			'status'      => $status,
			'facts'       => $facts,
		);
	}

	/**
	 * Order-screen metabox.
	 *
	 * @return void
	 */
	public function register_order_metabox() {
		$screens = array( 'shop_order' );

		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}

		foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
			add_meta_box(
				'eventrelay-order',
				__( 'EventRelay', 'eventrelay-for-pixelyoursite-and-sublium' ),
				array( $this, 'render_order_metabox' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the order metabox.
	 *
	 * @param WP_Post|WC_Order $post_or_order Order.
	 * @return void
	 */
	public function render_order_metabox( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: ( isset( $post_or_order->ID ) ? wc_get_order( $post_or_order->ID ) : null );

		$inspect = $this->inspect( $order );

		echo '<p><strong>' . esc_html( $inspect['headline'] ) . '</strong></p>';
		if ( ! empty( $inspect['detail'] ) ) {
			echo '<p>' . esc_html( $inspect['detail'] ) . '</p>';
		}
	}
}
