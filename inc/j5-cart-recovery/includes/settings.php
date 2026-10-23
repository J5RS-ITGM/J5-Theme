<?php
/**
 * J5 Cart Recovery: settings (one option, every decision editable in
 * WooCommerce > Cart Recovery > Settings).
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Defaults. Reminder steps are an ordered list; disabled steps are skipped. */
function j5cr_defaults() {
	return array(
		'enabled'            => 0,
		'capture_guests'     => 1,
		'capture_users'      => 1,
		'abandon_after'      => 60,      // minutes of no cart activity before a cart counts as abandoned
		'min_total'          => 0,       // ignore carts under this value
		'le_audience'        => 'verified', // all | verified | none  (carts holding LE-only items)
		'email_gap'          => 24,      // hours: at most one recovery email per customer in this window
		'stop_open_order'    => 1,       // stop reminders while the customer has a pending / on-hold order (e.g. a pay link staff sent)
		'steps'              => array(
			array(
				'enabled'  => 1,
				'delay'    => 1,      // hours after the last cart activity
				'subject'  => 'You left something in your cart',
				'heading'  => 'Still need these?',
				'intro'    => "We saved your cart so you can pick up where you left off. If anything gave you trouble at checkout, just reply to this email or give us a call and we'll help.",
				'button'   => 'Return to my cart',
				'discount' => 0,
				'pct'      => 5,
				'expiry'   => 3,      // days the code is valid
			),
			array(
				'enabled'  => 1,
				'delay'    => 24,
				'subject'  => 'Your cart is still waiting',
				'heading'  => 'Your gear is still in your cart',
				'intro'    => "Just a reminder that your cart is saved. Questions about sizing, ratings or agency purchasing? Reply here and a real person will get back to you.",
				'button'   => 'Finish my order',
				'discount' => 0,
				'pct'      => 5,
				'expiry'   => 3,
			),
			array(
				'enabled'  => 0,
				'delay'    => 72,
				'subject'  => 'Last reminder about your cart',
				'heading'  => 'Last call on your cart',
				'intro'    => "We'll clear your saved cart soon. If you still want these items, you can finish your order in a couple of clicks.",
				'button'   => 'Finish my order',
				'discount' => 0,
				'pct'      => 5,
				'expiry'   => 3,
			),
		),
		'exclude_brands'     => array(), // product_brand term IDs never discounted (MAP)
		'exclude_le'         => 1,       // never discount LE-only items
		'exclude_sale'       => 1,       // never discount items already on sale
		'exclude_products'   => '',      // extra product IDs / SKUs, comma separated
		'failed_enabled'     => 1,
		'failed_delay'       => 15,      // minutes after an unpaid checkout order
		'failed_customer'    => 1,
		'failed_admin'       => 1,
		'failed_admin_email' => '',      // blank = site admin email
		'failed_subject'     => "Your order didn't go through",
		'failed_heading'     => 'Your payment didn\'t go through',
		'failed_intro'       => "It looks like your payment didn't complete, so your order hasn't been placed yet. Your items are saved. You can finish in a couple of clicks, or call us and we'll take care of it over the phone.",
		'failed_button'      => 'Complete my order',
		'retention'          => 30,      // days to keep cart records
	);
}

/** Saved settings over defaults (steps merged one by one). */
function j5cr_settings() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$d = j5cr_defaults();
	$s = get_option( J5CR_OPTION, array() );
	$s = is_array( $s ) ? $s : array();
	$out = array_merge( $d, $s );
	$steps = array();
	foreach ( $d['steps'] as $i => $def ) {
		$steps[ $i ] = array_merge( $def, isset( $s['steps'][ $i ] ) && is_array( $s['steps'][ $i ] ) ? $s['steps'][ $i ] : array() );
	}
	$out['steps'] = $steps;
	if ( ! $s && empty( $out['exclude_brands'] ) ) {
		$out['exclude_brands'] = j5cr_default_map_brands();
	}
	$cache = $out;
	return $out;
}

/** Brands pre-checked as MAP the first time (only those that exist). */
function j5cr_default_map_brands() {
	$ids = array();
	if ( ! taxonomy_exists( 'product_brand' ) ) {
		return $ids;
	}
	foreach ( array( 'hesco-armor', 'surefire', 'eotech', 'highcom-armor', 'united-shield-international' ) as $slug ) {
		$t = get_term_by( 'slug', $slug, 'product_brand' );
		if ( $t ) {
			$ids[] = (int) $t->term_id;
		}
	}
	return $ids;
}

/** Sanitize a posted settings array. */
function j5cr_sanitize( $in ) {
	$d   = j5cr_defaults();
	$out = array();
	$b   = function ( $k ) use ( $in ) { return empty( $in[ $k ] ) ? 0 : 1; };
	$n   = function ( $k, $min, $max, $float = false ) use ( $in, $d ) {
		$v = isset( $in[ $k ] ) ? ( $float ? (float) $in[ $k ] : (int) $in[ $k ] ) : $d[ $k ];
		return max( $min, min( $max, $v ) );
	};
	$t   = function ( $arr, $k, $def ) { return isset( $arr[ $k ] ) ? sanitize_text_field( wp_unslash( $arr[ $k ] ) ) : $def; };
	$ta  = function ( $arr, $k, $def ) { return isset( $arr[ $k ] ) ? sanitize_textarea_field( wp_unslash( $arr[ $k ] ) ) : $def; };

	$out['enabled']        = $b( 'enabled' );
	$out['capture_guests'] = $b( 'capture_guests' );
	$out['capture_users']  = $b( 'capture_users' );
	$out['abandon_after']  = $n( 'abandon_after', 15, 1440 );
	$out['min_total']      = $n( 'min_total', 0, 1000000, true );
	$out['email_gap']       = $n( 'email_gap', 1, 168 );
	$out['stop_open_order'] = $b( 'stop_open_order' );
	$out['le_audience']    = isset( $in['le_audience'] ) && in_array( $in['le_audience'], array( 'all', 'verified', 'none' ), true ) ? $in['le_audience'] : 'verified';

	$out['steps'] = array();
	foreach ( $d['steps'] as $i => $def ) {
		$st = isset( $in['steps'][ $i ] ) && is_array( $in['steps'][ $i ] ) ? $in['steps'][ $i ] : array();
		$out['steps'][ $i ] = array(
			'enabled'  => empty( $st['enabled'] ) ? 0 : 1,
			'delay'    => max( 0.25, min( 720, isset( $st['delay'] ) ? (float) $st['delay'] : $def['delay'] ) ),
			'subject'  => $t( $st, 'subject', $def['subject'] ),
			'heading'  => $t( $st, 'heading', $def['heading'] ),
			'intro'    => $ta( $st, 'intro', $def['intro'] ),
			'button'   => $t( $st, 'button', $def['button'] ),
			'discount' => empty( $st['discount'] ) ? 0 : 1,
			'pct'      => max( 1, min( 50, isset( $st['pct'] ) ? (float) $st['pct'] : $def['pct'] ) ),
			'expiry'   => max( 1, min( 60, isset( $st['expiry'] ) ? (int) $st['expiry'] : $def['expiry'] ) ),
		);
	}
	// Later steps can't fire before earlier ones.
	$prev = 0;
	foreach ( $out['steps'] as $i => $st ) {
		if ( $st['enabled'] ) {
			$out['steps'][ $i ]['delay'] = max( $st['delay'], $prev + 0.25 );
			$prev                        = $out['steps'][ $i ]['delay'];
		}
	}

	$out['exclude_brands']     = isset( $in['exclude_brands'] ) ? array_values( array_filter( array_map( 'absint', (array) $in['exclude_brands'] ) ) ) : array();
	$out['exclude_le']         = $b( 'exclude_le' );
	$out['exclude_sale']       = $b( 'exclude_sale' );
	$out['exclude_products']   = $t( $in, 'exclude_products', '' );
	$out['failed_enabled']     = $b( 'failed_enabled' );
	$out['failed_delay']       = $n( 'failed_delay', 5, 55 );
	$out['failed_customer']    = $b( 'failed_customer' );
	$out['failed_admin']       = $b( 'failed_admin' );
	$out['failed_admin_email'] = isset( $in['failed_admin_email'] ) ? sanitize_email( wp_unslash( $in['failed_admin_email'] ) ) : '';
	$out['failed_subject']     = $t( $in, 'failed_subject', $d['failed_subject'] );
	$out['failed_heading']     = $t( $in, 'failed_heading', $d['failed_heading'] );
	$out['failed_intro']       = $ta( $in, 'failed_intro', $d['failed_intro'] );
	$out['failed_button']      = $t( $in, 'failed_button', $d['failed_button'] );
	$out['retention']          = $n( 'retention', 7, 365 );
	return $out;
}
