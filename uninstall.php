<?php
/**
 * Uninstall cleanup.
 *
 * @package EventRelay
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'eventrelay' );
delete_option( 'eventrelay_log' );
delete_option( 'eventrelay_installed_at' );

// Review nudge state (option key is {slug}_review_nudge).
delete_option( 'eventrelay-for-pixelyoursite-and-sublium_review_nudge' );
