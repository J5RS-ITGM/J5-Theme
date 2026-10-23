<?php
/**
 * J5 Cart Recovery: capturing carts and closing them out when an order lands.
 *
 * A record exists once we know who the cart belongs to: the email typed at
 * checkout (guests) or the logged-in account. After that, every cart change
 * refreshes the snapshot. The snapshot keeps what's needed to rebuild the
 * cart (product, variation, attributes, qty, DP option picks) plus display
 * copies (name, options text, line total, image) for the reminder emails.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Cart item data keys carried into the restored cart. */
function j5cr_keep_keys() {
	return (array) apply_filters( 'j5cr_cart_item_keys', array( 'j5_options' ) );
}

/** Random token for links. */
function j5cr_new_token() {
	return md5( wp_generate_password( 32, true, true ) . microtime() );
}

/** Record by column. */
function j5cr_get( $field, $value ) {
	global $wpdb;
	$allowed = array( 'id', 'token', 'session_key' );
	if ( ! in_array( $field, $allowed, true ) ) {
		return null;
	}
	$t   = j5cr_table();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$field} = %s ORDER BY id DESC LIMIT 1", $value ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $row ) {
		$row['cart'] = json_decode( $row['cart'], true ) ?: array();
	}
	return $row;
}

/** Update a record. */
function j5cr_update( $id, $data ) {
	global $wpdb;
	if ( isset( $data['cart'] ) && is_array( $data['cart'] ) ) {
		$data['cart'] = wp_json_encode( $data['cart'] );
	}
	return $wpdb->update( j5cr_table(), $data, array( 'id' => (int) $id ) );
}

/** Email on the suppression list? */
function j5cr_is_unsubscribed( $email ) {
	$list = get_option( J5CR_UNSUB, array() );
	return is_array( $list ) && in_array( strtolower( trim( $email ) ), $list, true );
}

/** Snapshot the current WooCommerce cart. */
function j5cr_snapshot() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array( 'items' => array(), 'total' => 0, 'has_le' => 0 );
	}
	$items  = array();
	$total  = 0.0;
	$has_le = 0;
	$keep   = j5cr_keep_keys();
	foreach ( WC()->cart->get_cart() as $ci ) {
		$p = isset( $ci['data'] ) ? $ci['data'] : null;
		if ( ! $p instanceof WC_Product ) {
			continue;
		}
		$data = array();
		foreach ( $keep as $k ) {
			if ( isset( $ci[ $k ] ) ) {
				$data[ $k ] = $ci[ $k ];
			}
		}
		// Display copy of attributes + options, as the cart shows them.
		$meta = array();
		$flat = trim( (string) wc_get_formatted_cart_item_data( $ci, true ) );
		foreach ( '' !== $flat ? explode( "\n", $flat ) : array() as $line ) {
			$line = trim( wp_strip_all_tags( html_entity_decode( $line, ENT_QUOTES, 'UTF-8' ) ) );
			if ( '' !== $line ) {
				$meta[] = $line;
			}
		}
		$line_total = isset( $ci['line_subtotal'] ) ? (float) $ci['line_subtotal'] : (float) $p->get_price() * (int) $ci['quantity'];
		$img        = $p->get_image_id() ? wp_get_attachment_image_src( $p->get_image_id(), 'thumbnail' ) : false;
		if ( ! $img && $p->get_parent_id() ) {
			$parent = wc_get_product( $p->get_parent_id() );
			$img    = $parent && $parent->get_image_id() ? wp_get_attachment_image_src( $parent->get_image_id(), 'thumbnail' ) : false;
		}
		$le = function_exists( 'j5_is_le_only' ) && j5_is_le_only( $p );
		if ( $le ) {
			$has_le = 1;
		}
		$items[] = array(
			'product_id'   => (int) $ci['product_id'],
			'variation_id' => (int) $ci['variation_id'],
			'variation'    => isset( $ci['variation'] ) ? (array) $ci['variation'] : array(),
			'qty'          => (int) $ci['quantity'],
			'data'         => $data,
			'name'         => wp_strip_all_tags( $p->get_name() ),
			'meta'         => $meta,
			'line'         => round( $line_total, 2 ),
			'image'        => $img ? $img[0] : '',
			'url'          => $p->get_permalink(),
			'le'           => $le ? 1 : 0,
		);
		$total += $line_total;
	}
	return array( 'items' => $items, 'total' => round( $total, 2 ), 'has_le' => $has_le );
}

/**
 * Create or refresh this visitor's record.
 *
 * @param string $email      Known email ('' = keep the one on file).
 * @param string $first_name
 * @return int Record id, 0 when nothing was saved.
 */
function j5cr_capture( $email = '', $first_name = '' ) {
	$s = j5cr_settings();
	if ( empty( $s['enabled'] ) || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
		return 0;
	}
	$user_id = get_current_user_id();
	if ( $user_id && empty( $s['capture_users'] ) ) {
		return 0;
	}
	if ( ! $user_id && empty( $s['capture_guests'] ) ) {
		return 0;
	}
	if ( $user_id && '' === $email ) {
		$u     = wp_get_current_user();
		$email = WC()->customer && WC()->customer->get_billing_email() ? WC()->customer->get_billing_email() : $u->user_email;
		if ( '' === $first_name ) {
			$first_name = WC()->customer && WC()->customer->get_billing_first_name() ? WC()->customer->get_billing_first_name() : $u->first_name;
		}
	}

	if ( ! WC()->session->has_session() ) {
		WC()->session->set_customer_session_cookie( true );
	}
	$session_key = (string) WC()->session->get_customer_id();
	$existing_id = (int) WC()->session->get( 'j5cr_id' );
	$row         = $existing_id ? j5cr_get( 'id', $existing_id ) : null;
	if ( ! $row && '' !== $session_key ) {
		$row = j5cr_get( 'session_key', $session_key );
	}
	// A record that already turned into an order starts fresh next time.
	if ( $row && in_array( $row['status'], array( 'ordered', 'recovered' ), true ) ) {
		$row = null;
	}

	$snap = j5cr_snapshot();
	if ( ! $snap['items'] ) {
		if ( $row && in_array( $row['status'], array( 'active', 'abandoned' ), true ) ) {
			j5cr_update( $row['id'], array( 'status' => 'emptied', 'updated' => current_time( 'mysql', true ) ) );
		}
		return 0;
	}

	$email = sanitize_email( $email );
	if ( ! $row && ! is_email( $email ) ) {
		return 0; // nobody to remind yet
	}
	$now = current_time( 'mysql', true );
	$set = array(
		'cart'     => $snap['items'],
		'total'    => $snap['total'],
		'currency' => get_woocommerce_currency(),
		'has_le'   => $snap['has_le'],
		'user_id'  => $user_id,
		'updated'  => $now,
	);
	if ( is_email( $email ) ) {
		$set['email'] = strtolower( $email );
	}
	if ( '' !== $first_name ) {
		$set['first_name'] = sanitize_text_field( $first_name );
	}

	if ( $row ) {
		// New activity resets the reminder clock; a quiet or emptied cart is live again.
		if ( in_array( $row['status'], array( 'abandoned', 'emptied', 'skipped' ), true ) ) {
			$set['status'] = 'active';
		}
		j5cr_update( $row['id'], $set );
		$id = (int) $row['id'];
	} else {
		global $wpdb;
		$set['cart']        = wp_json_encode( $set['cart'] );
		$set['token']       = j5cr_new_token();
		$set['session_key'] = $session_key;
		$set['status']      = 'active';
		$set['source']      = 'cart';
		$set['created']     = $now;
		$wpdb->insert( j5cr_table(), $set );
		$id = (int) $wpdb->insert_id;
	}
	WC()->session->set( 'j5cr_id', $id );
	return $id;
}

/* ---------- Capture triggers ---------- */

/** Checkout email field (guests and members) via AJAX. */
add_action( 'wp_ajax_j5cr_capture', 'j5cr_ajax_capture' );
add_action( 'wp_ajax_nopriv_j5cr_capture', 'j5cr_ajax_capture' );
function j5cr_ajax_capture() {
	check_ajax_referer( 'j5cr', 'nonce' );
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$first = isset( $_POST['first'] ) ? sanitize_text_field( wp_unslash( $_POST['first'] ) ) : '';
	$id    = is_email( $email ) ? j5cr_capture( $email, $first ) : 0;
	wp_send_json_success( array( 'saved' => (bool) $id ) );
}

/**
 * Cart changes refresh a known record; logged-in members are captured outright.
 * Deferred to the end of the request (before WooCommerce saves the session at
 * shutdown priority 20) so a burst of changes, like a restore, writes once
 * with the final cart.
 */
function j5cr_on_cart_change() {
	static $queued = false;
	if ( $queued || ( is_admin() && ! wp_doing_ajax() ) ) {
		return;
	}
	$queued = true;
	add_action( 'shutdown', function () {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		if ( WC()->session->get( 'j5cr_id' ) || is_user_logged_in() ) {
			j5cr_capture();
			WC()->session->save_data();
		}
	}, 5 );
}
add_action( 'woocommerce_add_to_cart', 'j5cr_on_cart_change', 99 );
add_action( 'woocommerce_cart_item_removed', 'j5cr_on_cart_change', 99 );
add_action( 'woocommerce_after_cart_item_quantity_update', 'j5cr_on_cart_change', 99 );
add_action( 'woocommerce_cart_emptied', 'j5cr_on_cart_change', 99 );

/** Checkout script: save the cart as soon as a valid email is typed. */
add_action( 'wp_enqueue_scripts', function () {
	$s = j5cr_settings();
	if ( empty( $s['enabled'] ) || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	if ( ! is_user_logged_in() && empty( $s['capture_guests'] ) ) {
		return;
	}
	wp_register_script( 'j5cr-capture', '', array(), '1', true );
	wp_enqueue_script( 'j5cr-capture' );
	wp_add_inline_script( 'j5cr-capture', 'window.j5cr=' . wp_json_encode( array(
		'url'   => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'j5cr' ),
	) ) . ';' . <<<'JS'
(function(){
  var last='', timer=null;
  function send(){
    var e=document.getElementById('billing_email'); if(!e) return;
    var email=(e.value||'').trim(); if(!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) return;
    var f=document.getElementById('billing_first_name'); var first=f?(f.value||'').trim():'';
    var key=email+'|'+first; if(key===last) return; last=key;
    var b=new URLSearchParams(); b.append('action','j5cr_capture'); b.append('nonce',window.j5cr.nonce); b.append('email',email); b.append('first',first);
    fetch(window.j5cr.url,{method:'POST',credentials:'same-origin',body:b}).catch(function(){});
  }
  function later(){ clearTimeout(timer); timer=setTimeout(send,800); }
  document.addEventListener('change',function(ev){ if(ev.target&&(ev.target.id==='billing_email'||ev.target.id==='billing_first_name')) send(); },true);
  document.addEventListener('input',function(ev){ if(ev.target&&ev.target.id==='billing_email') later(); },true);
  document.addEventListener('DOMContentLoaded',function(){ setTimeout(send,1500); });
})();
JS
	);
} );

/* ---------- Closing records out ---------- */

/** An order from this visitor stops their reminders; a clicked reminder counts as recovered. */
function j5cr_on_order( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! function_exists( 'WC' ) ) {
		return;
	}
	global $wpdb;
	$t   = j5cr_table();
	$ids = array();
	if ( WC()->session && WC()->session->get( 'j5cr_id' ) ) {
		$ids[] = (int) WC()->session->get( 'j5cr_id' );
	}
	$email = strtolower( (string) $order->get_billing_email() );
	if ( $email ) {
		$ids = array_merge( $ids, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE email = %s AND status IN ('active','abandoned') AND source = 'cart'", $email ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	foreach ( array_unique( array_filter( $ids ) ) as $id ) {
		$row = j5cr_get( 'id', $id );
		if ( ! $row || ! in_array( $row['status'], array( 'active', 'abandoned' ), true ) ) {
			continue;
		}
		j5cr_update( $id, array(
			'status'   => $row['clicked'] || (int) $row['steps_sent'] > 0 ? 'recovered' : 'ordered',
			'order_id' => (int) $order_id,
			'updated'  => current_time( 'mysql', true ),
		) );
		if ( $row['clicked'] || (int) $row['steps_sent'] > 0 ) {
			$order->add_order_note( 'Recovered by J5 Cart Recovery (' . ( (int) $row['steps_sent'] ) . ' reminder' . ( 1 === (int) $row['steps_sent'] ? '' : 's' ) . ' sent' . ( $row['clicked'] ? ', link clicked' : '' ) . ').' );
		}
	}
	// Failed-payment records for this email are settled by a new order too.
	if ( $email ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'recovered', order_id = %d WHERE email = %s AND source = 'failed' AND status IN ('active','abandoned')", $order_id, $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	if ( WC()->session ) {
		WC()->session->__unset( 'j5cr_id' );
	}
}
add_action( 'woocommerce_checkout_order_processed', 'j5cr_on_order', 20 );
