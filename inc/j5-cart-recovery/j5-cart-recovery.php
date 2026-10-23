<?php
/**
 * J5 Cart Recovery: abandoned cart reminders and failed-payment follow-up.
 *
 *   Capture   checkout email field (guests) or the account (logged in) saves
 *             the cart, options included, to {prefix}j5_carts.
 *   Remind    up to 3 configurable reminder emails after the cart goes quiet,
 *             each with a "return to your cart" link that rebuilds the exact
 *             cart. Optional per-step discount that never touches excluded
 *             brands (MAP), LE-only items or sale items.
 *   Failed    orders left unpaid at checkout (declined card, closed PayPal,
 *             gateway error) get a "finish your order" email, and you get an
 *             alert with the gateway's last note.
 *   Admin     WooCommerce > Cart Recovery: carts list with stats, and every
 *             decision (timing, wording, discounts, exclusions, audience,
 *             retention) on the Settings tab. Off until enabled there.
 *
 * Self-contained like inc/j5-documents/ so it can become a plugin later.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'J5CR_DIR', trailingslashit( __DIR__ ) );
const J5CR_DB_VERSION = '1';
const J5CR_OPTION     = 'j5cr_settings';
const J5CR_UNSUB      = 'j5cr_unsubscribed';
const J5CR_CRON       = 'j5cr_cron';

require_once J5CR_DIR . 'includes/settings.php';
require_once J5CR_DIR . 'includes/store.php';
require_once J5CR_DIR . 'includes/emails.php';
require_once J5CR_DIR . 'includes/engine.php';
if ( is_admin() ) {
	require_once J5CR_DIR . 'includes/admin.php';
}

/** Table name. */
function j5cr_table() {
	global $wpdb;
	return $wpdb->prefix . 'j5_carts';
}

/** Create / upgrade the table once per DB version. */
add_action( 'init', function () {
	if ( get_option( 'j5cr_db_version' ) === J5CR_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t       = j5cr_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		token char(32) NOT NULL,
		session_key varchar(64) NOT NULL DEFAULT '',
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		email varchar(190) NOT NULL DEFAULT '',
		first_name varchar(100) NOT NULL DEFAULT '',
		cart longtext NOT NULL,
		total decimal(12,2) NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'USD',
		has_le tinyint(1) NOT NULL DEFAULT 0,
		source varchar(20) NOT NULL DEFAULT 'cart',
		status varchar(20) NOT NULL DEFAULT 'active',
		steps_sent tinyint(3) unsigned NOT NULL DEFAULT 0,
		last_sent datetime NULL DEFAULT NULL,
		coupon varchar(40) NOT NULL DEFAULT '',
		clicked datetime NULL DEFAULT NULL,
		order_id bigint(20) unsigned NOT NULL DEFAULT 0,
		note varchar(255) NOT NULL DEFAULT '',
		created datetime NOT NULL,
		updated datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token (token),
		KEY session_key (session_key),
		KEY email (email),
		KEY status_updated (status, updated)
	) {$charset};" );
	update_option( 'j5cr_db_version', J5CR_DB_VERSION, false );
}, 5 );

/** Run the engine every 10 minutes (WP-Cron; the server's real cron hits wp-cron.php). */
add_filter( 'cron_schedules', function ( $s ) {
	$s['j5cr_10min'] = array( 'interval' => 600, 'display' => 'Every 10 minutes (J5 Cart Recovery)' );
	return $s;
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( J5CR_CRON ) ) {
		wp_schedule_event( time() + 120, 'j5cr_10min', J5CR_CRON );
	}
} );
add_action( J5CR_CRON, 'j5cr_run' );
