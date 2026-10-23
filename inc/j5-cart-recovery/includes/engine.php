<?php
/**
 * J5 Cart Recovery: the engine (cron), reminders, MAP-safe coupons,
 * failed-payment follow-up, the restore link and unsubscribe.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================================
 * 1. CRON
 * ====================================================================== */

function j5cr_run() {
	$s = j5cr_settings();
	if ( empty( $s['enabled'] ) ) {
		return;
	}
	global $wpdb;
	$t   = j5cr_table();
	$now = time();

	// a) Quiet active carts become abandoned.
	$cut = gmdate( 'Y-m-d H:i:s', $now - 60 * (int) $s['abandon_after'] );
	$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'abandoned' WHERE status = 'active' AND source = 'cart' AND email <> '' AND updated < %s", $cut ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// b) One live cart per customer: older carts for the same email are retired.
	$wpdb->query( "UPDATE {$t} a JOIN {$t} b ON a.email = b.email AND b.id > a.id AND b.source = 'cart' AND b.status IN ('active','abandoned') SET a.status = 'skipped', a.note = 'Replaced by a newer cart' WHERE a.source = 'cart' AND a.email <> '' AND a.status IN ('active','abandoned')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// c) Reminders that are due.
	$gap     = 3600 * (int) $s['email_gap'];
	$emailed = array();
	$rows = $wpdb->get_results( "SELECT * FROM {$t} WHERE status = 'abandoned' AND source = 'cart' ORDER BY updated ASC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( $rows as $row ) {
		$row['cart'] = json_decode( $row['cart'], true ) ?: array();
		$next        = j5cr_next_step( $row, $s );
		if ( null === $next ) {
			continue;
		}
		$due = strtotime( $row['updated'] . ' UTC' ) + (int) round( 3600 * (float) $s['steps'][ $next ]['delay'] );
		if ( $now < $due ) {
			continue;
		}
		$why = j5cr_skip_reason( $row, $s );
		if ( $why ) {
			j5cr_update( $row['id'], array( 'status' => 'skipped', 'note' => $why ) );
			continue;
		}
		// Spacing: wait until this customer's last recovery email is old enough.
		$key = strtolower( $row['email'] );
		if ( isset( $emailed[ $key ] ) || ( $now - j5cr_last_emailed( $row['email'] ) ) < $gap ) {
			continue;
		}
		if ( j5cr_send_step( $row, $next ) ) {
			$emailed[ $key ] = 1;
		}
	}

	// d) Failed / unpaid checkout orders.
	if ( ! empty( $s['failed_enabled'] ) ) {
		j5cr_run_failed( $s );
	}

	// e) Retention.
	$old = gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS * (int) $s['retention'] );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE updated < %s", $old ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/** Index of the next enabled step not yet sent, or null. */
function j5cr_next_step( $row, $s ) {
	$sent = (int) $row['steps_sent'];
	foreach ( $s['steps'] as $i => $st ) {
		if ( $i < $sent ) {
			continue;
		}
		if ( ! empty( $st['enabled'] ) ) {
			return $i;
		}
	}
	return null;
}

/** Why this cart should not get reminders ('' = send). */
function j5cr_skip_reason( $row, $s ) {
	if ( '' === $row['email'] || ! is_email( $row['email'] ) ) {
		return 'No email';
	}
	if ( j5cr_is_unsubscribed( $row['email'] ) ) {
		return 'On the do-not-remind list';
	}
	if ( ! empty( $s['stop_open_order'] ) ) {
		$open = j5cr_open_order( $row['email'] );
		if ( $open ) {
			j5cr_update( $row['id'], array( 'order_id' => $open ) );
			return 'Has an open order (#' . $open . ')';
		}
	}
	if ( (float) $row['total'] < (float) $s['min_total'] ) {
		return 'Below minimum cart value';
	}
	if ( ! empty( $row['has_le'] ) ) {
		if ( 'none' === $s['le_audience'] ) {
			return 'LE-only items (reminders off for LE carts)';
		}
		if ( 'verified' === $s['le_audience'] ) {
			$verified = $row['user_id'] && function_exists( 'j5le_user_state' ) && 'verified' === j5le_user_state( (int) $row['user_id'] )['state'];
			if ( ! $verified ) {
				return 'LE-only items, buyer not verified';
			}
		}
	}
	// Bought since? (another device, phone order, etc.)
	if ( function_exists( 'wc_get_orders' ) ) {
		$recent = wc_get_orders( array(
			'billing_email' => $row['email'],
			'date_created'  => '>' . strtotime( $row['created'] . ' UTC' ),
			'status'        => array( 'processing', 'completed', 'on-hold' ),
			'limit'         => 1,
			'return'        => 'ids',
		) );
		if ( $recent ) {
			j5cr_update( $row['id'], array( 'status' => 'ordered', 'order_id' => (int) $recent[0] ) );
			return 'Ordered';
		}
	}
	return '';
}

/* =========================================================================
 * 2. SENDING
 * ====================================================================== */

function j5cr_restore_url( $row ) {
	return add_query_arg( 'j5-cart', $row['token'], home_url( '/' ) );
}
function j5cr_unsub_url( $row ) {
	return add_query_arg( 'j5-cart-stop', $row['token'], home_url( '/' ) );
}

/** Display items for the email from a cart snapshot. */
function j5cr_email_items( $cart ) {
	$out = array();
	foreach ( (array) $cart as $it ) {
		$out[] = array(
			'name'  => $it['name'],
			'meta'  => isset( $it['meta'] ) ? (array) $it['meta'] : array(),
			'qty'   => (int) $it['qty'],
			'line'  => html_entity_decode( wp_strip_all_tags( wc_price( (float) $it['line'] ) ), ENT_QUOTES, 'UTF-8' ),
			'image' => isset( $it['image'] ) ? $it['image'] : '',
		);
	}
	return $out;
}

/**
 * Send reminder step $i for a cart.
 *
 * @param array       $row  Record (cart decoded).
 * @param int         $i    Step index.
 * @param string|null $to   Override recipient (tests); null = the shopper.
 * @return bool
 */
function j5cr_send_step( $row, $i, $to = null ) {
	$s  = j5cr_settings();
	$st = $s['steps'][ $i ];
	$is_test = null !== $to;

	$coupon = array();
	if ( ! empty( $st['discount'] ) ) {
		$coupon = $is_test ? j5cr_coupon_preview( $row, $st ) : j5cr_make_coupon( $row, $st );
	}

	$args = array(
		'kind'      => 'reminder',
		'heading'   => $st['heading'],
		'intro'     => $st['intro'],
		'first'     => $row['first_name'],
		'button'    => $st['button'],
		'url'       => j5cr_restore_url( $row ),
		'unsub_url' => j5cr_unsub_url( $row ),
		'items'     => j5cr_email_items( $row['cart'] ),
		'total'     => html_entity_decode( wp_strip_all_tags( wc_price( (float) $row['total'] ) ), ENT_QUOTES, 'UTF-8' ),
		'coupon'    => $coupon,
		'order'     => '',
	);
	$ok = j5cr_mail( $to ? $to : $row['email'], ( $is_test ? '[TEST] ' : '' ) . $st['subject'], $args );
	$GLOBALS['j5cr_last_no_code'] = $coupon ? '' : j5cr_no_code_reason( $row, $st );

	if ( ! $is_test && $ok ) {
		$set = array(
			'steps_sent' => $i + 1,
			'last_sent'  => current_time( 'mysql', true ),
			'note'       => 'Reminder ' . ( $i + 1 ) . ' sent' . ( $coupon ? ' with code ' . $coupon['code'] : ( ! empty( $st['discount'] ) ? ', no code (nothing eligible)' : '' ) ),
		);
		if ( $coupon ) {
			$set['coupon'] = $coupon['code'];
		}
		j5cr_update( $row['id'], $set );
	}
	return $ok;
}

/** Send through WooCommerce's mailer (store From name/address), HTML + text. */
function j5cr_mail( $to, $subject, $args ) {
	if ( ! function_exists( 'WC' ) ) {
		return false;
	}
	$mailer  = WC()->mailer();
	$html    = j5cr_email_html( $args );
	$text    = j5cr_email_text( $args );
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( ! empty( $args['unsub_url'] ) ) {
		$headers[] = 'List-Unsubscribe: <' . $args['unsub_url'] . '>';
	}
	$set_alt = function ( $phpmailer ) use ( $text ) {
		$phpmailer->AltBody = $text; // multipart: better inbox placement
	};
	add_action( 'phpmailer_init', $set_alt );
	$ok = $mailer->send( $to, $subject, $html, implode( "\r\n", $headers ), array() );
	remove_action( 'phpmailer_init', $set_alt );
	return (bool) $ok;
}

/* =========================================================================
 * 3. COUPONS (never on excluded brands / LE-only / sale items)
 * ====================================================================== */

/** Product + variation IDs in the cart that may be discounted. */
function j5cr_eligible_ids( $cart, $s ) {
	$ids = array();
	foreach ( (array) $cart as $it ) {
		if ( '' !== j5cr_item_exclusion( $it, $s ) ) {
			continue;
		}
		$ids[] = (int) $it['product_id'];
		if ( ! empty( $it['variation_id'] ) ) {
			$ids[] = (int) $it['variation_id'];
		}
	}
	return array_values( array_unique( $ids ) );
}

/** Why a cart line can't take a code ('' = eligible). */
function j5cr_item_exclusion( $it, $s ) {
	$pid = (int) $it['product_id'];
	$vid = (int) $it['variation_id'];
	$p   = wc_get_product( $vid ? $vid : $pid );
	if ( ! $p ) {
		return 'product no longer exists';
	}
	if ( ! empty( $s['exclude_brands'] ) && taxonomy_exists( 'product_brand' ) ) {
		$brands = wp_get_post_terms( $pid, 'product_brand', array( 'fields' => 'all' ) );
		if ( ! is_wp_error( $brands ) ) {
			foreach ( $brands as $b ) {
				if ( in_array( (int) $b->term_id, array_map( 'intval', $s['exclude_brands'] ), true ) ) {
					return 'brand ' . $b->name . ' is checked under "Never discount these brands"';
				}
			}
		}
	}
	if ( ! empty( $s['exclude_le'] ) && function_exists( 'j5_is_le_only' ) && j5_is_le_only( $p ) ) {
		return 'LE-only item ("Law enforcement only items" is excluded)';
	}
	if ( ! empty( $s['exclude_sale'] ) && $p->is_on_sale() ) {
		return 'on sale ("Items already on sale" is excluded)';
	}
	$extra = array_filter( array_map( 'trim', explode( ',', (string) $s['exclude_products'] ) ) );
	if ( $extra && ( in_array( (string) $pid, $extra, true ) || in_array( (string) $vid, $extra, true ) || ( $p->get_sku() && in_array( $p->get_sku(), $extra, true ) ) ) ) {
		return 'listed under Excluded products';
	}
	return '';
}

/** Plain-English reason a step went out without a code ('' = it has one or shouldn't). */
function j5cr_no_code_reason( $row, $st ) {
	if ( empty( $st['discount'] ) ) {
		return '"Include a discount code" is not checked for this reminder in the saved settings.';
	}
	$s     = j5cr_settings();
	$lines = array();
	foreach ( (array) $row['cart'] as $it ) {
		$why = j5cr_item_exclusion( $it, $s );
		if ( '' === $why ) {
			return '';
		}
		$lines[] = ( $it['name'] ?? ( '#' . $it['product_id'] ) ) . ': ' . $why;
	}
	return 'Nothing in the cart can take a code. ' . implode( '; ', $lines ) . '.';
}

/** Wording for what the code covers. */
function j5cr_scope_text( $eligible_count, $cart_count ) {
	return $eligible_count >= $cart_count ? 'Good on everything in your cart.' : 'Applies to eligible items in your cart. Some brands are excluded under manufacturer pricing rules.';
}

/** Create a single-use, email-locked coupon. Empty array when nothing qualifies. */
function j5cr_make_coupon( $row, $st ) {
	$s   = j5cr_settings();
	$ids = j5cr_eligible_ids( $row['cart'], $s );
	if ( ! $ids ) {
		return array();
	}
	$code   = 'J5-' . strtoupper( wp_generate_password( 6, false, false ) );
	$expiry = time() + DAY_IN_SECONDS * (int) $st['expiry'];
	$c      = new WC_Coupon();
	$c->set_code( $code );
	$c->set_discount_type( 'percent' );
	$c->set_amount( (float) $st['pct'] );
	$c->set_product_ids( $ids );
	$c->set_usage_limit( 1 );
	$c->set_usage_limit_per_user( 1 );
	$c->set_email_restrictions( array( $row['email'] ) );
	$c->set_date_expires( $expiry );
	$c->set_exclude_sale_items( ! empty( $s['exclude_sale'] ) );
	$c->set_individual_use( false );
	$c->set_description( 'J5 Cart Recovery, cart #' . $row['id'] );
	$c->save();
	return array(
		'code'        => $code,
		'pct'         => (float) $st['pct'],
		'expiry_text' => 'Expires ' . wp_date( 'F j', $expiry ) . '.',
		'scope_text'  => j5cr_scope_text( count( array_filter( (array) $row['cart'], function ( $it ) use ( $ids ) { return in_array( (int) ( $it['variation_id'] ?: $it['product_id'] ), $ids, true ); } ) ), count( (array) $row['cart'] ) ),
	);
}

/** Same as above without saving a coupon (test sends / previews). */
function j5cr_coupon_preview( $row, $st ) {
	$s   = j5cr_settings();
	$ids = j5cr_eligible_ids( $row['cart'], $s );
	if ( ! $ids ) {
		return array();
	}
	return array(
		'code'        => 'J5-PREVIEW',
		'pct'         => (float) $st['pct'],
		'expiry_text' => 'Expires ' . wp_date( 'F j', time() + DAY_IN_SECONDS * (int) $st['expiry'] ) . '.',
		'scope_text'  => j5cr_scope_text( count( array_filter( (array) $row['cart'], function ( $it ) use ( $ids ) { return in_array( (int) ( $it['variation_id'] ?: $it['product_id'] ), $ids, true ); } ) ), count( (array) $row['cart'] ) ),
	);
}

/* =========================================================================
 * 4. FAILED / UNPAID CHECKOUT ORDERS
 * ====================================================================== */

function j5cr_run_failed( $s ) {
	$before = time() - 60 * (int) $s['failed_delay'];
	$orders = wc_get_orders( array(
		'status'       => array( 'pending', 'failed', 'cancelled' ),
		'date_created' => ( time() - 2 * DAY_IN_SECONDS ) . '...' . $before,
		'limit'        => 50,
		'orderby'      => 'date',
		'order'        => 'DESC',
		'meta_query'   => array( array( 'key' => '_j5cr_failed_done', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	$seen = array();
	foreach ( $orders as $order ) {
		// Several failed attempts by one customer: only the newest gets a follow-up.
		$key = strtolower( $order->get_billing_email() );
		if ( isset( $seen[ $key ] ) ) {
			$order->update_meta_data( '_j5cr_failed_done', 'superseded' );
			$order->save();
			continue;
		}
		if ( 'checkout' !== $order->get_created_via() || $order->get_date_paid() || '' !== (string) $order->get_transaction_id() || ! $order->get_payment_method() ) {
			$order->update_meta_data( '_j5cr_failed_done', 'skip' );
			$order->save();
			continue;
		}
		// Only cancellations WooCommerce made for non-payment (not staff, not ID rejection).
		if ( $order->has_status( 'cancelled' ) && ! j5cr_order_note_like( $order, 'Unpaid order cancelled' ) ) {
			$order->update_meta_data( '_j5cr_failed_done', 'skip' );
			$order->save();
			continue;
		}
		// Skip if they've since placed a paid order.
		$later = wc_get_orders( array(
			'billing_email' => $order->get_billing_email(),
			'date_created'  => '>' . $order->get_date_created()->getTimestamp(),
			'status'        => array( 'processing', 'completed', 'on-hold' ),
			'limit'         => 1,
			'return'        => 'ids',
		) );
		if ( $later ) {
			$order->update_meta_data( '_j5cr_failed_done', 'paid-later' );
			$order->save();
			continue;
		}
		// Any newer order for this email (a new attempt, or a pay link staff created) takes over.
		$newer = wc_get_orders( array(
			'billing_email' => $order->get_billing_email(),
			'date_created'  => '>' . $order->get_date_created()->getTimestamp(),
			'limit'         => 1,
			'return'        => 'ids',
		) );
		if ( $newer ) {
			$order->update_meta_data( '_j5cr_failed_done', 'newer-order' );
			$order->save();
			continue;
		}
		$seen[ $key ] = 1;
		j5cr_handle_failed( $order, $s );
	}
}

/** True when a note on the order contains $needle. */
function j5cr_order_note_like( $order, $needle ) {
	foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $n ) {
		if ( false !== stripos( wp_strip_all_tags( $n->content ), $needle ) ) {
			return true;
		}
	}
	return false;
}

/** Last gateway / system note (the error), for the alert. */
function j5cr_last_error_note( $order ) {
	foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id(), 'order' => 'DESC' ) ) as $n ) {
		$txt = trim( wp_strip_all_tags( $n->content ) );
		if ( '' !== $txt && false === stripos( $txt, 'email' ) && false === stripos( $txt, 'Unpaid order cancelled' ) ) {
			return $txt;
		}
	}
	return '';
}

/** Turn the order back into a restorable cart record. */
function j5cr_record_from_order( $order ) {
	global $wpdb;
	$items = array();
	foreach ( $order->get_items() as $item ) {
		$p = $item->get_product();
		if ( ! $p ) {
			continue;
		}
		$variation = array();
		if ( $p->is_type( 'variation' ) ) {
			foreach ( $p->get_variation_attributes() as $k => $v ) {
				$meta_key        = str_replace( 'attribute_', '', $k );
				$variation[ $k ] = '' !== $v ? $v : (string) $item->get_meta( $meta_key );
			}
		}
		$data = array();
		$opts = $item->get_meta( '_j5_options' );
		if ( $opts && function_exists( 'j5_opts_groups' ) ) {
			$decoded = json_decode( $opts, true );
			if ( is_array( $decoded ) ) {
				$groups = j5_opts_groups( $item->get_product_id() );
				foreach ( $decoded as $r ) {
					$mode = isset( $groups[ $r['group_code'] ]['sku_mode'] ) ? $groups[ $r['group_code'] ]['sku_mode'] : 'none';
					$data['j5_options'][] = $r + array( 'sku_mode' => $mode );
				}
			}
		}
		$meta = array();
		foreach ( $item->get_formatted_meta_data( '_' ) as $m ) {
			$meta[] = wp_strip_all_tags( $m->display_key ) . ': ' . trim( wp_strip_all_tags( html_entity_decode( $m->display_value, ENT_QUOTES, 'UTF-8' ) ) );
		}
		$img     = $p->get_image_id() ? wp_get_attachment_image_src( $p->get_image_id(), 'thumbnail' ) : false;
		$items[] = array(
			'product_id'   => $item->get_product_id(),
			'variation_id' => $item->get_variation_id(),
			'variation'    => $variation,
			'qty'          => (int) $item->get_quantity(),
			'data'         => $data,
			'name'         => wp_strip_all_tags( $item->get_name() ),
			'meta'         => $meta,
			'line'         => round( (float) $item->get_subtotal(), 2 ),
			'image'        => $img ? $img[0] : '',
			'url'          => $p->get_permalink(),
			'le'           => function_exists( 'j5_is_le_only' ) && j5_is_le_only( $p ) ? 1 : 0,
		);
	}
	$now = current_time( 'mysql', true );
	$wpdb->insert( j5cr_table(), array(
		'token'       => j5cr_new_token(),
		'session_key' => '',
		'user_id'     => (int) $order->get_customer_id(),
		'email'       => strtolower( $order->get_billing_email() ),
		'first_name'  => $order->get_billing_first_name(),
		'cart'        => wp_json_encode( $items ),
		'total'       => (float) $order->get_total(),
		'currency'    => $order->get_currency(),
		'has_le'      => function_exists( 'j5le_order_has_le_only' ) && j5le_order_has_le_only( $order ) ? 1 : 0,
		'source'      => 'failed',
		'status'      => 'abandoned',
		'order_id'    => $order->get_id(),
		'created'     => $now,
		'updated'     => $now,
	) );
	return j5cr_get( 'id', (string) $wpdb->insert_id );
}

function j5cr_handle_failed( $order, $s ) {
	$row   = j5cr_record_from_order( $order );
	$error = j5cr_last_error_note( $order );
	$sent  = false;

	// The order's own pay link works while it is still Pending; after WooCommerce
	// cancels it, the restore link rebuilds the cart instead.
	$url = ( $order->has_status( 'pending' ) && $order->needs_payment() ) ? $order->get_checkout_payment_url() : j5cr_restore_url( $row );

	$cust_ok = is_email( $order->get_billing_email() ) && ! j5cr_is_unsubscribed( $order->get_billing_email() )
		&& ( time() - j5cr_last_emailed( $order->get_billing_email() ) ) >= 3600 * (int) $s['email_gap'];
	if ( ! $cust_ok ) {
		j5cr_update( $row['id'], array( 'status' => 'skipped', 'note' => j5cr_is_unsubscribed( $order->get_billing_email() ) ? 'On the do-not-remind list' : 'Customer emailed recently, follow-up not sent' ) );
	}
	if ( ! empty( $s['failed_customer'] ) && $cust_ok ) {
		$sent = j5cr_mail( $order->get_billing_email(), $s['failed_subject'], array(
			'kind'      => 'failed',
			'heading'   => $s['failed_heading'],
			'intro'     => $s['failed_intro'],
			'first'     => $order->get_billing_first_name(),
			'button'    => $s['failed_button'],
			'url'       => $url,
			'unsub_url' => '',
			'items'     => j5cr_email_items( $row['cart'] ),
			'total'     => html_entity_decode( wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ),
			'coupon'    => array(),
			'order'     => $order->get_order_number(),
		) );
		j5cr_update( $row['id'], array( 'steps_sent' => $sent ? 1 : 0, 'last_sent' => $sent ? current_time( 'mysql', true ) : null, 'note' => $sent ? 'Payment follow-up sent' : 'Payment follow-up failed to send' ) );
	}

	if ( ! empty( $s['failed_admin'] ) ) {
		$admin = $s['failed_admin_email'] ? $s['failed_admin_email'] : get_option( 'admin_email' );
		$rows  = array(
			'Customer'  => esc_html( $order->get_formatted_billing_full_name() ) . ' &lt;' . esc_html( $order->get_billing_email() ) . '&gt;',
			'Phone'     => esc_html( $order->get_billing_phone() ),
			'Total'     => wp_kses_post( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'Payment'   => esc_html( $order->get_payment_method_title() ),
			'Status'    => esc_html( wc_get_order_status_name( $order->get_status() ) ),
			'Last note' => $error ? esc_html( $error ) : '<em>No gateway note. The customer may have closed the payment window.</em>',
			'Customer email' => $sent ? 'Follow-up sent with a link to finish the order.' : 'Not sent (turned off, unsubscribed, or no email).',
		);
		WC()->mailer()->send( $admin, 'Unpaid checkout: order #' . $order->get_order_number() . ' (' . $order->get_formatted_billing_full_name() . ')', j5cr_admin_alert_html( array(
			'order'    => $order->get_order_number(),
			'summary'  => 'This checkout did not complete payment within ' . (int) $s['failed_delay'] . ' minutes.',
			'rows'     => $rows,
			'edit_url' => $order->get_edit_order_url(),
		) ), "Content-Type: text/html; charset=UTF-8\r\n" );
	}

	$order->update_meta_data( '_j5cr_failed_done', gmdate( 'c' ) );
	$order->add_order_note( 'J5 Cart Recovery: unpaid checkout follow-up' . ( $sent ? ' sent to customer' : '' ) . ( ! empty( $s['failed_admin'] ) ? ', staff alerted' : '' ) . '.' );
	$order->save();
}

/* =========================================================================
 * 5. RESTORE LINK + UNSUBSCRIBE
 * ====================================================================== */

add_action( 'template_redirect', function () {
	if ( empty( $_GET['j5-cart'] ) && empty( $_GET['j5-cart-stop'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! empty( $_GET['j5-cart-stop'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		j5cr_handle_unsubscribe( sanitize_key( wp_unslash( $_GET['j5-cart-stop'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	j5cr_handle_restore( sanitize_key( wp_unslash( $_GET['j5-cart'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}, 5 );

function j5cr_handle_restore( $token ) {
	$row = 32 === strlen( $token ) ? j5cr_get( 'token', $token ) : null;
	if ( ! $row || ! function_exists( 'WC' ) ) {
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}
	if ( in_array( $row['status'], array( 'ordered', 'recovered' ), true ) && 'failed' !== $row['source'] ) {
		wc_add_notice( 'Good news: this order has already been placed. Thank you!', 'notice' );
		wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
		exit;
	}
	if ( ! WC()->session->has_session() ) {
		WC()->session->set_customer_session_cookie( true );
	}
	// Link the session first so cart-change hooks refresh this record instead of creating another.
	WC()->session->set( 'j5cr_id', (int) $row['id'] );
	WC()->cart->empty_cart();
	$missing = 0;
	foreach ( (array) $row['cart'] as $it ) {
		$added = WC()->cart->add_to_cart( (int) $it['product_id'], max( 1, (int) $it['qty'] ), (int) $it['variation_id'], (array) $it['variation'], (array) $it['data'] );
		if ( ! $added ) {
			$missing++;
		}
	}
	if ( $missing ) {
		wc_add_notice( 1 === $missing ? 'One item from your saved cart is no longer available.' : $missing . ' items from your saved cart are no longer available.', 'notice' );
	}
	if ( WC()->customer && is_email( $row['email'] ) ) {
		if ( ! WC()->customer->get_billing_email() ) {
			WC()->customer->set_billing_email( $row['email'] );
		}
		if ( $row['first_name'] && ! WC()->customer->get_billing_first_name() ) {
			WC()->customer->set_billing_first_name( $row['first_name'] );
		}
		WC()->customer->save();
	}
	if ( $row['coupon'] && ! WC()->cart->has_discount( $row['coupon'] ) ) {
		WC()->cart->apply_coupon( $row['coupon'] );
	}
	j5cr_update( $row['id'], array( 'clicked' => current_time( 'mysql', true ) ) );
	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}

function j5cr_handle_unsubscribe( $token ) {
	$row = 32 === strlen( $token ) ? j5cr_get( 'token', $token ) : null;
	if ( $row && is_email( $row['email'] ) ) {
		$list   = get_option( J5CR_UNSUB, array() );
		$list   = is_array( $list ) ? $list : array();
		$list[] = strtolower( $row['email'] );
		update_option( J5CR_UNSUB, array_values( array_unique( $list ) ), false );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . j5cr_table() . " SET status = 'unsubscribed' WHERE email = %s AND status IN ('active','abandoned')", strtolower( $row['email'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	wp_die(
		'<h1 style="font-family:Arial,sans-serif">You\'re unsubscribed</h1><p style="font-family:Arial,sans-serif;font-size:16px">You won\'t get any more cart reminders from J5 Rescue Supply. Order confirmations and shipping updates will still arrive as usual.</p><p style="font-family:Arial,sans-serif"><a href="' . esc_url( home_url( '/' ) ) . '">Back to j5rescue.com</a></p>',
		'Unsubscribed',
		array( 'response' => 200 )
	);
}
