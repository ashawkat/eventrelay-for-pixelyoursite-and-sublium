<?php
/**
 * Uninstall cleanup.
 *
 * @package EventRelay
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'eventrelay' );
delete_option( 'eventrelay_log' );
