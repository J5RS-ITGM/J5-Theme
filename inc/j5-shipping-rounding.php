<?php
/**
 * Shipping amounts rounded to cents (J5-SHIP-ROUND-1, 2026-10-22).
 *
 * WooCommerce Advanced Shipping can produce rate costs with more than two
 * decimals (e.g. 10.1125). WooCommerce stores that as-is on the order, and
 * the MxMerchant plugin sends it unrounded as shipAmount
 * ($wc_order->get_total_shipping(), card and eCheck). MxMerchant rejects the
 * whole request ("Freight (ship) amount format must be a decimal and a maximum
 * of 10 total characters"), no transaction is created, the order sits in
 * Pending payment and WooCommerce cancels it after the hold-stock window.
 * Orders 96970 and 97938 were lost this way.
 *
 * 1. Round every shipping rate (cost and taxes) to the store's decimals when
 *    rates are calculated, so new orders never store a sub-cent amount.
 * 2. Round an order's shipping total on read, so orders that already carry
 *    one (and anything else that reads it) send a valid amount.
 * No filter exists in the MxMerchant plugin itself, so this is done here and
 * survives plugin updates.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'woocommerce_package_rates', function ( $rates ) {
	$dp = wc_get_price_decimals();
	foreach ( $rates as $rate ) {
		if ( ! $rate instanceof WC_Shipping_Rate ) {
			continue;
		}
		$rate->set_cost( wc_format_decimal( (float) $rate->get_cost(), $dp ) );
		$taxes = $rate->get_taxes();
		if ( is_array( $taxes ) && $taxes ) {
			foreach ( $taxes as $k => $t ) {
				$taxes[ $k ] = wc_format_decimal( (float) $t, $dp );
			}
			$rate->set_taxes( $taxes );
		}
	}
	return $rates;
}, 999 );

add_filter( 'woocommerce_order_get_shipping_total', function ( $value ) {
	return ( '' === $value || null === $value ) ? $value : wc_format_decimal( (float) $value, wc_get_price_decimals() );
}, 999 );
