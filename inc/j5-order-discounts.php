<?php
/**
 * Named discounts on orders (J5-ORDER-DISCOUNTS-1).
 *
 * WooCommerce prints every coupon as one "Discount:" total. This replaces that
 * row with one row per coupon, named for what it is:
 *   factory-order-savings  -> "Factory order savings (8%)"
 *   plate-age-discount     -> "Plate Age Discount (14%)" (older orders: in-stock-plate-savings, same name)
 *   anything else          -> "Coupon: SAVE10"
 * The percent shows only when every discounted line used the same one.
 *
 * WooCommerce's get_order_item_totals() feeds the customer and admin emails,
 * the thank-you page, My Account and the J5 Documents receipt/invoice, so all
 * of them pick this up, old orders included.
 *
 * New orders also get a visible "Savings" line on each discounted item, e.g.
 * "Factory order savings 8% (-$38.56)" or "Plate Age Discount 13% (-$62.66)", so it's clear which item the discount
 * was for. Percent meta: _j5_fs_discount_pct (factory, written here) and
 * _j5_as_discount_pct (plate age, written by inc/j5-armorsync-units.php).
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Our auto-applied coupons: code => array( label, line pct meta key ). */
function j5od_known() {
	return array(
		'factory-order-savings'  => array( 'Factory order savings', '_j5_fs_discount_pct' ),
		'plate-age-discount'     => array( 'Plate Age Discount', '_j5_as_discount_pct' ),
		'in-stock-plate-savings' => array( 'Plate Age Discount', '_j5_as_discount_pct' ), // orders placed before the rename
	);
}

/** Customer-facing name for a coupon code. */
function j5od_label( $code ) {
	$code  = wc_format_coupon_code( (string) $code );
	$known = j5od_known();
	return isset( $known[ $code ] ) ? $known[ $code ][0] : 'Coupon: ' . strtoupper( $code );
}

/** "8%" when every line carrying this coupon's percent meta used the same percent, else ''. */
function j5od_order_pct( $order, $code ) {
	$known = j5od_known();
	$code  = wc_format_coupon_code( (string) $code );
	if ( ! isset( $known[ $code ] ) ) {
		return '';
	}
	$pcts = array();
	foreach ( $order->get_items() as $item ) {
		$p = $item->get_meta( $known[ $code ][1] );
		if ( '' !== (string) $p ) {
			$pcts[ (string) (float) $p ] = true;
		}
	}
	return 1 === count( $pcts ) ? key( $pcts ) . '%' : '';
}

function j5od_money_text( $amount, $order ) {
	return trim( html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ) );
}

/** Replace the single "Discount" total with one named row per coupon. */
add_filter( 'woocommerce_get_order_item_totals', function ( $rows, $order, $tax_display = '' ) {
	if ( ! isset( $rows['discount'] ) || ! $order instanceof WC_Order ) {
		return $rows;
	}
	$coupons = $order->get_items( 'coupon' );
	if ( ! $coupons ) {
		return $rows;
	}
	$incl  = 'incl' === ( $tax_display ? $tax_display : get_option( 'woocommerce_tax_display_cart' ) );
	$named = array();
	foreach ( $coupons as $c ) {
		$amt = (float) $c->get_discount() + ( $incl ? (float) $c->get_discount_tax() : 0 );
		if ( $amt <= 0 ) {
			continue;
		}
		$pct   = j5od_order_pct( $order, $c->get_code() );
		$label = j5od_label( $c->get_code() ) . ( $pct ? ' (' . $pct . ')' : '' );
		$named[ 'discount_' . sanitize_key( $c->get_code() ) ] = array(
			'label' => esc_html( $label ) . ':',
			'value' => '-' . wc_price( $amt, array( 'currency' => $order->get_currency() ) ),
		);
	}
	if ( ! $named ) {
		return $rows;
	}
	$out = array();
	foreach ( $rows as $key => $row ) {
		if ( 'discount' === $key ) {
			$out += $named;
			continue;
		}
		$out[ $key ] = $row;
	}
	return $out;
}, 20, 3 );

/**
 * Per-line "Savings" (visible meta) + factory percent meta, written as the order is created.
 * Runs after the ArmorSync and options hooks (priority 30).
 */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $line_item, $cart_item_key, $values ) {
	$applied = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_applied_coupons() : array();
	$base    = isset( $values['line_subtotal'] ) ? (float) $values['line_subtotal'] : 0;
	if ( $base <= 0 || ! $applied ) {
		return;
	}
	$order_stub = new WC_Order(); // currency formatting only
	$parts      = array();

	// In-stock ArmorSync plate: age discount.
	if ( ! empty( $values['j5_as']['discount_pct'] ) && in_array( defined( 'J5AS_CODE' ) ? J5AS_CODE : 'plate-age-discount', $applied, true ) ) {
		$pct     = (int) $values['j5_as']['discount_pct'];
		$parts[] = 'Plate Age Discount ' . $pct . '% (-' . j5od_money_text( round( $base * $pct / 100, 2 ), $order_stub ) . ')';
	} elseif ( function_exists( 'j5fs_pct' ) && empty( $values['j5_as'] ) && in_array( 'factory-order-savings', $applied, true ) ) {
		// Factory order (not for in-stock plates).
		$pct = isset( $values['data'] ) ? (float) j5fs_pct( $values['data'] ) : 0;
		if ( $pct > 0 ) {
			$line_item->add_meta_data( '_j5_fs_discount_pct', function_exists( 'j5fs_fmt' ) ? j5fs_fmt( $pct ) : (string) $pct, true );
			$parts[] = 'Factory order savings ' . ( function_exists( 'j5fs_fmt' ) ? j5fs_fmt( $pct ) : $pct ) . '% (-' . j5od_money_text( round( $base * $pct / 100, 2 ), $order_stub ) . ')';
		}
	}
	if ( $parts ) {
		$line_item->add_meta_data( 'Savings', implode( '; ', $parts ), true );
	}
}, 30, 3 );
