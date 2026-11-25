<?php
/**
 * J5 DP order substitution detail: WC_ORDER_SUBSTITUTION_CONTRACT_1 (Nov 25, 2026).
 *
 *   POST /wp-json/j5/v1/order-substitution
 *       {"order_id": 123, "sku": "SAM-XT-BK", "name": "SAM XT Tourniquet",
 *        "key": "Substituted with", "value": "2 x SAM XT Tourniquet, Orange (SKU SAM-XT-OR)"}
 *       ->  {"ok": true, "item_id": 456}
 *
 *   Sets (or, with an empty value, removes) one visible meta row on one line
 *   item of an existing order. Match is by product SKU (trimmed, case-insensitive),
 *   falling back to the line item name when the SKU is blank or matches nothing.
 *
 *   A paid order must never be recalculated, so this route only saves the line
 *   item. It never calls calculate_totals(), calculate_taxes() or $order->save().
 *   WC_Order_Item::save() writes the item meta and clears the order-items cache
 *   without touching order totals.
 *
 *   The key has no leading underscore on purpose: WooCommerce shows it under the
 *   line item in the admin order, My Account and order emails.
 *
 * Auth: X-J5-Hub-Secret = J5_HUB_DEFS_SECRET (j5_armor_ref_permission, same as
 * brands / shipping-rules / redirects). Wrong secret -> 401.
 *
 * Errors: 400 bad input, 404 order or line item not found.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5_SUBST_KEY     = 'Substituted with';
const J5_SUBST_MAX_LEN = 1000;

add_action( 'rest_api_init', function () {
	register_rest_route( 'j5/v1', '/order-substitution', array(
		'methods'             => 'POST',
		'permission_callback' => function ( $request ) {
			return function_exists( 'j5_armor_ref_permission' )
				? j5_armor_ref_permission( $request )
				: new WP_Error( 'j5_subst_forbidden', 'Authentication unavailable.', array( 'status' => 401 ) );
		},
		'callback'            => 'j5_subst_handle',
	) );
} );

function j5_subst_handle( WP_REST_Request $request ) {
	$p = $request->get_json_params();
	if ( ! is_array( $p ) ) {
		$p = $request->get_params();
	}

	$order_id = isset( $p['order_id'] ) ? absint( $p['order_id'] ) : 0;
	$sku      = isset( $p['sku'] ) ? trim( (string) $p['sku'] ) : '';
	$name     = isset( $p['name'] ) ? trim( wp_specialchars_decode( (string) $p['name'], ENT_QUOTES ) ) : '';
	$key      = isset( $p['key'] ) ? trim( (string) $p['key'] ) : J5_SUBST_KEY;
	$has_val  = array_key_exists( 'value', $p );
	$value    = $has_val ? trim( sanitize_text_field( (string) $p['value'] ) ) : '';

	if ( ! $order_id ) {
		return new WP_Error( 'j5_subst_bad_order', 'order_id is required.', array( 'status' => 400 ) );
	}
	if ( '' === $sku && '' === $name ) {
		return new WP_Error( 'j5_subst_bad_item', 'sku or name is required.', array( 'status' => 400 ) );
	}
	// Only the contract's key may be written through this route, so it can't be
	// used to set or delete arbitrary (including hidden) line item meta.
	if ( J5_SUBST_KEY !== $key ) {
		return new WP_Error( 'j5_subst_bad_key', 'key must be "' . J5_SUBST_KEY . '".', array( 'status' => 400 ) );
	}
	if ( ! $has_val ) {
		return new WP_Error( 'j5_subst_bad_value', 'value is required (empty string removes the detail).', array( 'status' => 400 ) );
	}
	if ( strlen( $value ) > J5_SUBST_MAX_LEN ) {
		$value = substr( $value, 0, J5_SUBST_MAX_LEN );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order || ! is_a( $order, 'WC_Order' ) || is_a( $order, 'WC_Order_Refund' ) ) {
		return new WP_Error( 'j5_subst_no_order', 'Order not found.', array( 'status' => 404 ) );
	}

	$item = j5_subst_find_item( $order, $sku, $name );
	if ( ! $item ) {
		return new WP_Error( 'j5_subst_no_item', 'No line item on this order matches that SKU or name.', array( 'status' => 404 ) );
	}

	if ( '' !== $value ) {
		$item->update_meta_data( J5_SUBST_KEY, $value );
	} else {
		$item->delete_meta_data( J5_SUBST_KEY );
	}
	$item->save(); // item only: no calculate_totals(), no $order->save().

	return rest_ensure_response( array(
		'ok'      => true,
		'item_id' => $item->get_id(),
	) );
}

/**
 * SKU match first (the line's own product SKU, so the variation's SKU for a
 * variation line), name match second. First match wins when a SKU appears twice.
 */
function j5_subst_find_item( WC_Order $order, $sku, $name ) {
	$items  = $order->get_items( 'line_item' );
	$sku_lc = strtolower( $sku );

	if ( '' !== $sku_lc ) {
		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$s = trim( (string) $product->get_sku() );
			if ( '' !== $s && strtolower( $s ) === $sku_lc ) {
				return $item;
			}
		}
	}

	if ( '' !== $name ) {
		$name_lc = strtolower( $name );
		foreach ( $items as $item ) {
			$item_name = strtolower( trim( wp_specialchars_decode( $item->get_name(), ENT_QUOTES ) ) );
			if ( $item_name === $name_lc ) {
				return $item;
			}
		}
	}

	return null;
}
