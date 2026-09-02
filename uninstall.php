<?php
/**
 * Uninstall cleanup.
 *
 * @package PYS_Sublium_Bridge
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'pys_sublium_bridge' );
delete_option( 'pys_sublium_bridge_log' );
