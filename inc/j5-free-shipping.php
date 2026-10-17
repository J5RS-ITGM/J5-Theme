<?php
/**
 * J5 Free Shipping — single source of truth.
 *
 * The threshold lives in ONE place: WooCommerce → Settings → Shipping →
 * Domestic → Free shipping → "Minimum order amount". Every customer-facing
 * message (header promo bar, homepage trust bar) reads it from there, so the
 * banner can never drift from what checkout actually does again.
 *
 * Exception: body armor (category 256 and all its children: plates, plate
 * sets, etc.) ships insured and is never eligible for free shipping. If the
 * cart contains any of it, the free shipping method is hidden and the
 * customer sees paid rates for the order.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Product categories excluded from free shipping (children included). */
const J5_NO_FREE_SHIP_CATS = array( 256 ); // body-armor

/** Free shipping minimum, read from the WooCommerce free_shipping method. */
function j5_free_ship_min() {
	static $min = null;
	if ( null !== $min ) return $min;
	$min = (float) wp_cache_get( 'j5_free_ship_min', 'j5' );
	if ( $min > 0 ) return $min;
	$min = 0.0;
	if ( class_exists( 'WC_Shipping_Zones' ) ) {
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			foreach ( $zone['shipping_methods'] as $m ) {
				if ( 'free_shipping' === $m->id && $m->is_enabled() && ! empty( $m->instance_settings['min_amount'] ) ) {
					$min = (float) $m->instance_settings['min_amount'];
					break 2;
				}
			}
		}
	}
	wp_cache_set( 'j5_free_ship_min', $min, 'j5', HOUR_IN_SECONDS );
	return $min;
}

/** "$500" style amount, no decimals when whole. */
function j5_free_ship_amount() {
	$min = j5_free_ship_min();
	return $min > 0 ? '$' . ( floor( $min ) == $min ? number_format( $min ) : number_format( $min, 2 ) ) : '';
}

/** Header promo bar text. */
add_filter( 'j5_shipping_promo', function () {
	$amt = j5_free_ship_amount();
	return $amt ? "Free shipping on orders over {$amt} · Excludes body armor" : '';
}, 20 );

/** Homepage trust bar label (after "Free Shipping"). */
if ( ! function_exists( 'j5_free_ship_label' ) ) {
	function j5_free_ship_label() {
		$amt = j5_free_ship_amount();
		return $amt ? "on orders {$amt}+" : '';
	}
}

/** Is this product in a category that never ships free? */
function j5_product_excluded_from_free_ship( $product_id ) {
	$product_id = wp_get_post_parent_id( $product_id ) ?: $product_id;
	foreach ( J5_NO_FREE_SHIP_CATS as $cat ) {
		$ids = array_merge( array( $cat ), get_term_children( $cat, 'product_cat' ) ?: array() );
		if ( has_term( $ids, 'product_cat', $product_id ) ) return true;
	}
	return false;
}

/** Hide free shipping when the cart contains excluded items. */
add_filter( 'woocommerce_shipping_free_shipping_is_available', function ( $available, $package ) {
	if ( ! $available || empty( $package['contents'] ) ) return $available;
	foreach ( $package['contents'] as $item ) {
		if ( j5_product_excluded_from_free_ship( $item['product_id'] ) ) return false;
	}
	return $available;
}, 20, 2 );

/** Clear the cached minimum whenever shipping settings are saved. */
add_action( 'woocommerce_update_options_shipping', function () { wp_cache_delete( 'j5_free_ship_min', 'j5' ); } );
