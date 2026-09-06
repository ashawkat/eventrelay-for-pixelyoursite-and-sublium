<?php
/**
 * Fire PixelYourSite FirstTimeBuyer with Purchase on a customer's first sale.
 *
 * @package EventRelay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class EventRelay_First_Time_Buyer
 */
class EventRelay_First_Time_Buyer {

	/**
	 * Order meta that marks FirstTimeBuyer as already sent by this bridge.
	 */
	const FIRED_META = '_eventrelay_ftb_fired';

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
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_send' ), 30, 4 );
		add_filter( 'pys_validate_pixel_event', array( $this, 'skip_browser_if_server_sent' ), 6, 3 );
	}

	/**
	 * Send FirstTimeBuyer through PixelYourSite when this is a first paid sale.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Previous status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order      Order.
	 * @return void
	 */
	public function maybe_send( $order_id, $old_status, $new_status, $order ) {
		if ( ! EventRelay_Plugin::get_setting( 'send_first_time_buyer' ) ) {
			return;
		}

		if ( ! EventRelay_Plugin::is_pys_active() || ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->status_should_track( $new_status ) ) {
			return;
		}

		$detector = EventRelay_Detector::instance();

		if ( $detector->should_skip_purchase( $order ) ) {
			return;
		}

		if ( ! $detector->is_first_time_sale( $order ) ) {
			return;
		}

		if ( $order->get_meta( self::FIRED_META, true ) ) {
			return;
		}

		if ( ! function_exists( 'PixelYourSite\\EventsWoo' ) || ! function_exists( 'PixelYourSite\\PYS' ) ) {
			return;
		}

		add_filter(
			'pys_woo_checkout_order_id',
			static function () use ( $order_id ) {
				return $order_id;
			}
		);

		if ( method_exists( \PixelYourSite\EventsWoo(), 'setNewCustomer' ) ) {
			\PixelYourSite\EventsWoo()->setNewCustomer( $order_id );
		}

		$event = \PixelYourSite\EventsWoo()->getEvent( 'woo_FirstTimeBuyer', false, true );

		if ( ! $event && class_exists( '\PixelYourSite\SingleEvent' ) && class_exists( '\PixelYourSite\EventTypes' ) ) {
			$event       = new \PixelYourSite\SingleEvent( 'woo_FirstTimeBuyer', \PixelYourSite\EventTypes::$STATIC, 'woo' );
			$event->args = array( 'order_id' => $order_id );
		}

		if ( ! $event ) {
			EventRelay_Logger::instance()->log(
				'skipped',
				array(
					'order_id'   => $order_id,
					'event'      => 'FirstTimeBuyer',
					'channel'    => 'server',
					'first_time' => true,
					'message'    => sprintf(
						/* translators: %d: order ID */
						__( 'Order #%d is a first sale, but PixelYourSite did not build a FirstTimeBuyer event. Turn on FirstTimeBuyer under PixelYourSite → WooCommerce Events.', 'eventrelay-for-pixelyoursite-and-sublium' ),
						$order_id
					),
				)
			);
			return;
		}

		$sent = $this->send_through_pys( $event, $order );

		$order->update_meta_data( self::FIRED_META, wp_json_encode( $sent ) );
		$order->save();

		$platforms = empty( $sent ) ? __( 'no platforms accepted it', 'eventrelay-for-pixelyoursite-and-sublium' ) : implode( ', ', $sent );

		EventRelay_Logger::instance()->log(
			empty( $sent ) ? 'skipped' : 'sent',
			array(
				'order_id'   => $order_id,
				'event'      => 'FirstTimeBuyer',
				'channel'    => 'server',
				'platform'   => implode( ', ', $sent ),
				'first_time' => true,
				'message'    => sprintf(
					/* translators: 1: order ID, 2: platform list */
					__( 'Order #%1$d is a first-time sale. FirstTimeBuyer sent with Purchase via API to: %2$s.', 'eventrelay-for-pixelyoursite-and-sublium' ),
					$order_id,
					$platforms
				),
			)
		);
	}

	/**
	 * Avoid a second browser FirstTimeBuyer after the API already sent it.
	 *
	 * @param bool   $is_active Whether the event may fire.
	 * @param object $event     PixelYourSite event.
	 * @param object $pixel     Pixel object.
	 * @return bool
	 */
	public function skip_browser_if_server_sent( $is_active, $event, $pixel ) {
		if ( ! $is_active || ! is_object( $event ) || ! method_exists( $event, 'getId' ) ) {
			return $is_active;
		}

		if ( 'woo_FirstTimeBuyer' !== $event->getId() ) {
			return $is_active;
		}

		$order_id = 0;
		if ( isset( $event->args['order_id'] ) ) {
			$order_id = absint( $event->args['order_id'] );
		}

		if ( ! $order_id && function_exists( 'wooGetOrderIdFromRequest' ) ) {
			$order_id = absint( wooGetOrderIdFromRequest() );
		}

		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order instanceof WC_Order || ! $order->get_meta( self::FIRED_META, true ) ) {
			return $is_active;
		}

		return false;
	}

	/**
	 * Whether this order status is one PixelYourSite uses for Purchase.
	 *
	 * @param string $status Status without wc- prefix is fine.
	 * @return bool
	 */
	private function status_should_track( $status ) {
		$status = str_replace( 'wc-', '', $status );

		if ( function_exists( 'PixelYourSite\\PYS' ) ) {
			$selected = (array) \PixelYourSite\PYS()->getOption( 'woo_order_advanced_purchase_status' );
			if ( ! empty( $selected ) ) {
				$clean = array_map(
					static function ( $item ) {
						return str_replace( 'wc-', '', $item );
					},
					$selected
				);

				return in_array( $status, $clean, true );
			}
		}

		return in_array( $status, array( 'processing', 'completed' ), true );
	}

	/**
	 * Send the PYS event through configured server APIs.
	 *
	 * @param object   $event PYS event.
	 * @param WC_Order $order Order.
	 * @return array<int,string>
	 */
	private function send_through_pys( $event, $order ) {
		$sent = array();

		if ( function_exists( 'PixelYourSite\\Facebook' ) && \PixelYourSite\Facebook()->enabled() ) {
			$events = \PixelYourSite\Facebook()->generateEvents( $event );
			if ( ! empty( $events ) && function_exists( 'PixelYourSite\\FacebookServer' ) ) {
				\PixelYourSite\FacebookServer()->sendEventsNow( $events );
				$sent[] = 'Facebook';
			}
		}

		if ( function_exists( 'PixelYourSite\\GA' ) && \PixelYourSite\GA()->enabled() ) {
			$events = \PixelYourSite\GA()->generateEvents( $event );
			if ( ! empty( $events ) && function_exists( 'PixelYourSite\\GaMeasurementProtocolAPI' ) ) {
				\PixelYourSite\GaMeasurementProtocolAPI()->sendEventsNow( $events );
				$sent[] = 'Google Analytics';
			}
		}

		if ( function_exists( 'PixelYourSite\\Tiktok' ) && \PixelYourSite\Tiktok()->enabled() && method_exists( \PixelYourSite\Tiktok(), 'isServerApiEnabled' ) && \PixelYourSite\Tiktok()->isServerApiEnabled() ) {
			$events = \PixelYourSite\Tiktok()->generateEvents( $event );
			if ( ! empty( $events ) && function_exists( 'PixelYourSite\\TikTokServer' ) ) {
				\PixelYourSite\TikTokServer()->sendEventsNow( $events );
				$sent[] = 'TikTok';
			}
		}

		if ( function_exists( 'PixelYourSite\\OpenAI' ) && \PixelYourSite\OpenAI()->enabled() && method_exists( \PixelYourSite\OpenAI(), 'isServerApiEnabled' ) && \PixelYourSite\OpenAI()->isServerApiEnabled() ) {
			$events = \PixelYourSite\OpenAI()->generateEvents( $event );
			if ( ! empty( $events ) && function_exists( 'PixelYourSite\\OpenAIServer' ) ) {
				\PixelYourSite\OpenAIServer()->sendEventsNow( $events );
				$sent[] = 'OpenAI';
			}
		}

		if ( function_exists( 'PixelYourSite\\Pinterest' ) && function_exists( 'PixelYourSite\\isPinterestActive' ) && \PixelYourSite\isPinterestActive() && \PixelYourSite\Pinterest()->enabled() && method_exists( \PixelYourSite\Pinterest(), 'isServerApiEnabled' ) && \PixelYourSite\Pinterest()->isServerApiEnabled() ) {
			$events = \PixelYourSite\Pinterest()->generateEvents( $event );
			if ( ! empty( $events ) && function_exists( 'PixelYourSite\\PinterestServer' ) ) {
				\PixelYourSite\PinterestServer()->sendEventsNow( $events );
				$sent[] = 'Pinterest';
			}
		}

		return $sent;
	}
}
