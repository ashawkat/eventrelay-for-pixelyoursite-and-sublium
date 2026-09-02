<?php
/**
 * Human-readable activity log for Purchase and FirstTimeBuyer decisions.
 *
 * @package PYS_Sublium_Bridge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PYS_Sublium_Bridge_Logger
 */
class PYS_Sublium_Bridge_Logger {

	/**
	 * Option that stores log rows.
	 */
	const OPTION_KEY = 'pys_sublium_bridge_log';

	/**
	 * Max rows kept.
	 */
	const MAX_ENTRIES = 150;

	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Request-level keys already logged.
	 *
	 * @var array<string,bool>
	 */
	private $seen = array();

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
	 * Add a log row.
	 *
	 * @param string               $action  blocked|allowed|sent|skipped.
	 * @param array<string,mixed>  $context Context.
	 * @return void
	 */
	public function log( $action, $context = array() ) {
		if ( ! PYS_Sublium_Bridge_Plugin::get_setting( 'logging_enabled' ) ) {
			return;
		}

		$context = wp_parse_args(
			$context,
			array(
				'order_id'     => 0,
				'event'        => '',
				'channel'      => '',
				'platform'     => '',
				'renewal'      => false,
				'renewal_type' => '',
				'first_time'   => null,
				'message'      => '',
			)
		);

		$dedupe = implode(
			'|',
			array(
				$action,
				(string) $context['order_id'],
				(string) $context['event'],
				(string) $context['channel'],
				(string) $context['platform'],
			)
		);

		if ( isset( $this->seen[ $dedupe ] ) ) {
			return;
		}
		$this->seen[ $dedupe ] = true;

		$entry = array(
			'time'         => current_time( 'mysql' ),
			'action'       => sanitize_key( $action ),
			'order_id'     => absint( $context['order_id'] ),
			'event'        => sanitize_text_field( $context['event'] ),
			'channel'      => sanitize_text_field( $context['channel'] ),
			'platform'     => sanitize_text_field( $context['platform'] ),
			'renewal'      => ! empty( $context['renewal'] ),
			'renewal_type' => sanitize_text_field( $context['renewal_type'] ),
			'first_time'   => is_null( $context['first_time'] ) ? null : (bool) $context['first_time'],
			'message'      => sanitize_text_field( $context['message'] ),
		);

		$entries   = $this->get_entries();
		$entries[] = $entry;

		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -1 * self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $entries, false );

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				$entry['message'] ? $entry['message'] : wp_json_encode( $entry ),
				array( 'source' => 'pys-sublium-bridge' )
			);
		}
	}

	/**
	 * Get log rows, oldest first as stored.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_entries() {
		$entries = get_option( self::OPTION_KEY, array() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Counts used by the dashboard cards.
	 *
	 * @return array<string,int>
	 */
	public function get_stats() {
		$stats = array(
			'purchases_sent'    => 0,
			'renewals_blocked'  => 0,
			'first_time_sent'   => 0,
			'today'             => 0,
			'total'             => 0,
		);

		$today = current_time( 'Y-m-d' );

		foreach ( $this->get_entries() as $entry ) {
			++$stats['total'];

			$action = isset( $entry['action'] ) ? $entry['action'] : '';
			if ( 'allowed' === $action ) {
				++$stats['purchases_sent'];
			} elseif ( 'blocked' === $action ) {
				++$stats['renewals_blocked'];
			} elseif ( 'sent' === $action ) {
				++$stats['first_time_sent'];
			}

			$time = isset( $entry['time'] ) ? substr( (string) $entry['time'], 0, 10 ) : '';
			if ( $time === $today ) {
				++$stats['today'];
			}
		}

		return $stats;
	}

	/**
	 * Clear the log.
	 *
	 * @return void
	 */
	public function clear() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Human summary for a row.
	 *
	 * @param array<string,mixed> $entry Row.
	 * @return string
	 */
	public static function format_message( $entry ) {
		if ( ! empty( $entry['message'] ) ) {
			return $entry['message'];
		}

		$order = ! empty( $entry['order_id'] ) ? '#' . $entry['order_id'] : __( 'unknown order', 'pys-sublium-bridge' );
		$event = ! empty( $entry['event'] ) ? $entry['event'] : __( 'event', 'pys-sublium-bridge' );
		$via   = ! empty( $entry['channel'] ) ? $entry['channel'] : '';
		$dest  = ! empty( $entry['platform'] ) ? $entry['platform'] : '';

		return trim( sprintf( '%s %s %s %s', $event, $entry['action'], $order, $via . ( $dest ? ' → ' . $dest : '' ) ) );
	}
}
