<?php
/**
 * Block PixelYourSite Purchase events for excluded Sublium renewals.
 *
 * @package EventRelay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EventRelay_Tracking
 */
class EventRelay_Tracking {

	/**
	 * Purchase-like event slugs PixelYourSite may fire for an order.
	 *
	 * @var array<int,string>
	 */
	private $purchase_events = array(
		'woo_purchase',
		'woo_purchase_category',
	);

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
		add_filter( 'pys_validate_pixel_event', array( $this, 'filter_browser_event' ), 5, 3 );
		add_filter( 'pys_disable_server_event_filter', array( $this, 'filter_server_event' ), 5, 4 );
	}

	/**
	 * Stop browser / pixel Purchase events on thank-you pages for excluded renewals.
	 *
	 * @param bool   $is_active Whether the event may fire.
	 * @param object $event     PixelYourSite event.
	 * @param object $pixel     Pixel object.
	 * @return bool
	 */
	public function filter_browser_event( $is_active, $event, $pixel ) {
		if ( ! $is_active || ! is_object( $event ) || ! method_exists( $event, 'getId' ) ) {
			return $is_active;
		}

		if ( ! in_array( $event->getId(), $this->purchase_events, true ) ) {
			return $is_active;
		}

		$order_id = $this->get_event_order_id( $event );

		if ( ! $order_id ) {
			return $is_active;
		}

		if ( EventRelay_Detector::instance()->should_skip_purchase( $order_id ) ) {
			$this->log_decision( 'blocked', $order_id, 'Purchase', 'browser', is_object( $pixel ) && method_exists( $pixel, 'getSlug' ) ? $pixel->getSlug() : '' );
			return false;
		}

		$this->log_decision( 'allowed', $order_id, 'Purchase', 'browser', is_object( $pixel ) && method_exists( $pixel, 'getSlug' ) ? $pixel->getSlug() : '' );

		return $is_active;
	}

	/**
	 * Stop Conversion API / Measurement Protocol Purchase events.
	 *
	 * @param bool       $disable    Whether to disable.
	 * @param string     $event_name Event slug.
	 * @param string     $tag_slug   Platform slug.
	 * @param int|string $order_id   Order ID.
	 * @return bool
	 */
	public function filter_server_event( $disable, $event_name, $tag_slug, $order_id ) {
		if ( $disable || ! in_array( $event_name, $this->purchase_events, true ) || ! $order_id ) {
			return $disable;
		}

		if ( EventRelay_Detector::instance()->should_skip_purchase( $order_id ) ) {
			$this->log_decision( 'blocked', $order_id, 'Purchase', 'server', $tag_slug );
			return true;
		}

		$this->log_decision( 'allowed', $order_id, 'Purchase', 'server', $tag_slug );

		return $disable;
	}

	/**
	 * Read the WooCommerce order ID from a PixelYourSite event.
	 *
	 * @param object $event Event.
	 * @return int
	 */
	private function get_event_order_id( $event ) {
		if ( isset( $event->args['order_id'] ) ) {
			return absint( $event->args['order_id'] );
		}

		if ( isset( $event->payload['woo_order'] ) ) {
			return absint( $event->payload['woo_order'] );
		}

		if ( method_exists( $event, 'getParamValue' ) ) {
			$from_param = $event->getParamValue( 'order_id' );
			if ( $from_param ) {
				return absint( $from_param );
			}
		}

		return 0;
	}

	/**
	 * Write a readable activity-log row.
	 *
	 * @param string     $action   blocked|allowed.
	 * @param int|string $order_id Order ID.
	 * @param string     $event    Event name.
	 * @param string     $channel  browser|server.
	 * @param string     $platform Platform slug.
	 * @return void
	 */
	private function log_decision( $action, $order_id, $event, $channel, $platform ) {
		$detector    = EventRelay_Detector::instance();
		$is_renewal  = $detector->is_renewal( $order_id );
		$type        = $is_renewal ? $detector->get_renewal_type( $order_id ) : '';
		$first_time  = $detector->is_first_time_sale( $order_id );
		$platform    = $platform ? $platform : __( 'connected pixels', 'eventrelay-for-pixelyoursite-and-sublium' );

		if ( 'blocked' === $action ) {
			$message = sprintf(
				/* translators: 1: order ID, 2: renewal type, 3: channel, 4: platform */
				__( 'Order #%1$d is a %2$s Sublium renewal. Purchase was blocked on %3$s so it was not sent to %4$s.', 'eventrelay-for-pixelyoursite-and-sublium' ),
				$order_id,
				$type ? $type : __( 'subscription', 'eventrelay-for-pixelyoursite-and-sublium' ),
				$channel,
				$platform
			);
		} elseif ( $first_time ) {
			$message = sprintf(
				/* translators: 1: order ID, 2: channel, 3: platform */
				__( 'Order #%1$d is a first-time sale. Purchase was allowed on %2$s and sent to %3$s.', 'eventrelay-for-pixelyoursite-and-sublium' ),
				$order_id,
				$channel,
				$platform
			);
		} else {
			$message = sprintf(
				/* translators: 1: order ID, 2: channel, 3: platform */
				__( 'Order #%1$d is not a renewal. Purchase was allowed on %2$s and sent to %3$s.', 'eventrelay-for-pixelyoursite-and-sublium' ),
				$order_id,
				$channel,
				$platform
			);
		}

		EventRelay_Logger::instance()->log(
			$action,
			array(
				'order_id'     => $order_id,
				'event'        => $event,
				'channel'      => $channel,
				'platform'     => $platform,
				'renewal'      => $is_renewal,
				'renewal_type' => $type,
				'first_time'   => $first_time,
				'message'      => $message,
			)
		);
	}
}
