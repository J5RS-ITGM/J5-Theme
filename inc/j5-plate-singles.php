<?php
/**
 * Plate singles and sets (J5-PLATE-SINGLES-1, Nov 2026).
 *
 * The DP sells each plate family as one product with a "Configuration" option
 * (custom attribute, WC key attribute_configuration) whose option text is
 * "Single plate" or "Set of 2 plates". Singles are factory orders; stocked
 * plates are serial-matched sets that J5 never breaks up.
 *
 * 1. Default option: plate pages open on the set (Eric, 2026-11-08). Only fills
 *    in Configuration; anything the DP sets as a default is kept.
 * 2. Cart: a Single plate line with quantity 2+ is combined into the matching
 *    Set of 2 (same parent, every other option identical): 2 -> 1 set,
 *    3 -> 1 set + 1 single, 4 -> 2 sets. Only when the set can be bought right
 *    now and costs no more than two singles. Runs on add to cart, quantity
 *    changes and cart load, so checkout always sees the combined cart. Extra
 *    cart data on the line (acknowledgments, add-ons) moves to the set line.
 *    Picked ArmorSync shelf units are never touched.
 *
 * Role detection accepts the DP's labels and older spellings:
 *   set:    "Set of 2 plates", "Set of 2", "Set", "Pair"
 *   single: "Single plate", "Single"
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5PS_ATTR = 'configuration';

/** 'set' | 'single' | '' for one Configuration value. */
function j5ps_role_of( $value ) {
	$v = sanitize_title( (string) $value );
	if ( in_array( $v, array( 'set-of-2-plates', 'set-of-2', 'set', 'pair' ), true ) ) {
		return 'set';
	}
	if ( in_array( $v, array( 'single-plate', 'single' ), true ) ) {
		return 'single';
	}
	return '';
}

/** Role of a variation (from its own attributes). */
function j5ps_variation_role( $variation ) {
	if ( ! $variation instanceof WC_Product_Variation ) {
		return '';
	}
	$attrs = $variation->get_attributes();
	return isset( $attrs[ J5PS_ATTR ] ) ? j5ps_role_of( $attrs[ J5PS_ATTR ] ) : '';
}

/* =========================================================================
 * 1. DEFAULT OPTION: Set of 2 plates
 * ====================================================================== */

add_filter( 'woocommerce_product_get_default_attributes', function ( $defaults, $product ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $defaults; // never write our default into the product edit screen
	}
	if ( ! $product instanceof WC_Product_Variable || isset( $defaults[ J5PS_ATTR ] ) ) {
		return $defaults;
	}
	$attrs = $product->get_attributes();
	if ( empty( $attrs[ J5PS_ATTR ] ) || ! $attrs[ J5PS_ATTR ]->get_variation() ) {
		return $defaults;
	}
	foreach ( $attrs[ J5PS_ATTR ]->get_options() as $opt ) {
		if ( 'set' === j5ps_role_of( $opt ) ) {
			$defaults           = is_array( $defaults ) ? $defaults : array();
			$defaults[ J5PS_ATTR ] = $opt;
			break;
		}
	}
	return $defaults;
}, 20, 2 );

/* The option picker clears later groups whenever an earlier one changes, so it
 * also gets the set as a group default it re-applies once size/cut are chosen. */
add_filter( 'j5_variation_group_default', function ( $def, $product, $attribute_name ) {
	if ( '' !== $def || ! $product instanceof WC_Product_Variable || J5PS_ATTR !== sanitize_title( $attribute_name ) ) {
		return $def;
	}
	$d = $product->get_default_attributes();
	return isset( $d[ J5PS_ATTR ] ) && 'set' === j5ps_role_of( $d[ J5PS_ATTR ] ) ? (string) $d[ J5PS_ATTR ] : $def;
}, 10, 3 );

/* =========================================================================
 * 2. CART: two singles -> one set
 * ====================================================================== */

/** The set variation matching a single (same parent, all other options equal), or null. */
function j5ps_matching_set( WC_Product_Variation $single ) {
	$parent = wc_get_product( $single->get_parent_id() );
	if ( ! $parent instanceof WC_Product_Variable ) {
		return null;
	}
	$want = array();
	foreach ( $single->get_attributes() as $k => $v ) {
		if ( J5PS_ATTR !== $k ) {
			$want[ $k ] = sanitize_title( (string) $v );
		}
	}
	foreach ( $parent->get_children() as $vid ) {
		$v = wc_get_product( $vid );
		if ( ! $v instanceof WC_Product_Variation || 'set' !== j5ps_variation_role( $v ) ) {
			continue;
		}
		$have = array();
		foreach ( $v->get_attributes() as $k => $val ) {
			if ( J5PS_ATTR !== $k ) {
				$have[ $k ] = sanitize_title( (string) $val );
			}
		}
		if ( $have == $want ) { // phpcs:ignore Universal.Operators.StrictComparisons -- order-insensitive array compare
			return $v;
		}
	}
	return null;
}

/** Cart item keys WooCommerce builds itself; everything else is carried to the set line. */
function j5ps_core_keys() {
	return array( 'key', 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'data_hash', 'line_tax_data', 'line_subtotal', 'line_subtotal_tax', 'line_total', 'line_tax' );
}

function j5ps_combine() {
	static $busy = false;
	if ( $busy || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$busy  = true;
	$cart  = WC()->cart;
	$moved = 0;

	foreach ( $cart->get_cart() as $key => $item ) {
		$qty    = (int) $item['quantity'];
		$single = $item['data'] ?? null;
		if ( $qty < 2 || ! empty( $item['j5_as'] ) || ! $single instanceof WC_Product_Variation || 'single' !== j5ps_variation_role( $single ) ) {
			continue;
		}
		$set = j5ps_matching_set( $single );
		if ( ! $set || ! $set->is_purchasable() || ! $set->is_in_stock() || 'publish' !== get_post_status( $set->get_parent_id() ) ) {
			continue;
		}
		$set_price    = (float) $set->get_price();
		$single_price = (float) $single->get_price();
		if ( $set_price <= 0 || $set_price > 2 * $single_price + 0.005 ) {
			continue; // never raise the customer's total
		}
		$pairs = intdiv( $qty, 2 );
		if ( $set->managing_stock() && ! $set->backorders_allowed() ) {
			$pairs = min( $pairs, max( 0, (int) $set->get_stock_quantity() ) );
		}
		if ( $pairs < 1 ) {
			continue;
		}

		$extra = array_diff_key( $item, array_flip( j5ps_core_keys() ) );
		$attrs = array();
		foreach ( $set->get_attributes() as $k => $v ) {
			$attrs[ 'attribute_' . $k ] = $v;
		}
		// Keep the customer's chosen values for any "Any …" options on the single.
		foreach ( (array) $item['variation'] as $k => $v ) {
			if ( isset( $attrs[ $k ] ) && '' === (string) $attrs[ $k ] ) {
				$attrs[ $k ] = $v;
			}
		}

		$new = $cart->add_to_cart( $set->get_parent_id(), $pairs, $set->get_id(), $attrs, $extra );
		if ( ! $new ) {
			continue;
		}
		$left = $qty - 2 * $pairs;
		if ( $left > 0 ) {
			$cart->set_quantity( $key, $left, false );
		} else {
			$cart->remove_cart_item( $key );
		}
		$moved += $pairs;
	}

	if ( $moved ) {
		$cart->calculate_totals();
		wc_add_notice(
			1 === $moved
				? 'We combined 2 single plates into a serial-matched Set of 2 plates.'
				: sprintf( 'We combined your single plates into %d serial-matched Sets of 2 plates.', $moved ),
			'success'
		);
	}
	$busy = false;
}

add_action( 'woocommerce_add_to_cart', function () { j5ps_combine(); }, 50 );
add_action( 'woocommerce_after_cart_item_quantity_update', function () { j5ps_combine(); }, 50 );
add_action( 'woocommerce_cart_loaded_from_session', function () { j5ps_combine(); }, 50 );
