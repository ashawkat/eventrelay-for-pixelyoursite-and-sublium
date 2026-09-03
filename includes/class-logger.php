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
	 * Whether this order already has a log row for an action.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $action   Action key.
	 * @return bool
	 */
	public function has_action_for_order( $order_id, $action ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return false;
		}

		foreach ( $this->get_entries() as $entry ) {
			if ( absint( $entry['order_id'] ?? 0 ) === $order_id && ( $entry['action'] ?? '' ) === $action ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Group log rows by order, newest orders first.
	 *
	 * @param array<int,array<string,mixed>> $entries Rows newest first.
	 * @return array<int,array<string,mixed>>
	 */
	public static function group_entries_by_order( $entries ) {
		$groups = array();

		foreach ( $entries as $entry ) {
			$order_id = ! empty( $entry['order_id'] ) ? (int) $entry['order_id'] : 0;
			$key      = $order_id ? (string) $order_id : 'none-' . md5( wp_json_encode( $entry ) );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'order_id'   => $order_id,
					'time'       => isset( $entry['time'] ) ? $entry['time'] : '',
					'entries'    => array(),
					'actions'    => array(),
					'first_time' => false,
					'renewal'    => false,
				);
			}

			$groups[ $key ]['entries'][] = $entry;

			if ( ! empty( $entry['action'] ) ) {
				$groups[ $key ]['actions'][ $entry['action'] ] = true;
			}

			if ( ! empty( $entry['first_time'] ) ) {
				$groups[ $key ]['first_time'] = true;
			}

			if ( ! empty( $entry['renewal'] ) ) {
				$groups[ $key ]['renewal'] = true;
			}
		}

		return array_values( $groups );
	}

	/**
	 * Keep order groups that include a given action.
	 *
	 * @param array<int,array<string,mixed>> $groups Grouped rows.
	 * @param string                         $filter all|allowed|sent|blocked.
	 * @return array<int,array<string,mixed>>
	 */
	public static function filter_groups_by_action( $groups, $filter ) {
		$filter = sanitize_key( $filter );
		if ( ! in_array( $filter, array( 'allowed', 'sent', 'blocked' ), true ) ) {
			return $groups;
		}

		return array_values(
			array_filter(
				$groups,
				static function ( $group ) use ( $filter ) {
					return ! empty( $group['actions'][ $filter ] );
				}
			)
		);
	}

	/**
	 * Slice grouped orders into a page.
	 *
	 * @param array<int,array<string,mixed>> $groups   Grouped rows.
	 * @param int                            $page     Requested page, 1-based.
	 * @param int                            $per_page Orders per page.
	 * @return array<string,mixed>
	 */
	public static function paginate_groups( $groups, $page, $per_page = 10 ) {
		$per_page = max( 1, absint( $per_page ) );
		$total    = count( $groups );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = min( max( 1, absint( $page ) ), $pages );
		$offset   = ( $page - 1 ) * $per_page;

		return array(
			'groups'     => array_slice( $groups, $offset, $per_page ),
			'page'       => $page,
			'pages'      => $pages,
			'total'      => $total,
			'per_page'   => $per_page,
			'from'       => $total ? $offset + 1 : 0,
			'to'         => $total ? min( $offset + $per_page, $total ) : 0,
		);
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
