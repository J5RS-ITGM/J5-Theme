<?php
/**
 * DP cache flush endpoint (J5-DP-CACHE-1, 2026-11-08).
 *
 *   POST /wp-json/j5/v1/cache-flush
 *   Body (optional JSON): { "product_id": 9179 }
 *
 * Why: the DP updates a parent product's attributes, then its variations. With
 * the Redis object cache serving a stale parent, WooCommerce matched the
 * variation's attribute names against the old parent and saved the variation
 * with no attributes (L211 sets, Nov 2026). The DP calls this right after each
 * parent attribute update and once after the variation batch.
 *
 *   - With product_id: clears that product, all its variations and their
 *     WooCommerce transients/cache groups. Fast and leaves the rest of the
 *     store's cache warm. Use this between pushes.
 *   - Without product_id (or "all": true): wp_cache_flush(), the whole object cache.
 *
 * Auth (same as the DP's other endpoints):
 *   1. WooCommerce REST API key (Basic auth) with read_write permission, or
 *   2. X-J5-Hub-Secret: <J5_HUB_DEFS_SECRET>, or
 *   3. a logged-in user with manage_woocommerce (for testing).
 *
 * Response: { "ok": true, "scope": "product"|"all", "product_id": 9179, "cleared": 7 }
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	register_rest_route( 'j5/v1', '/cache-flush', array(
		'methods'             => 'POST',
		'callback'            => 'j5_dp_cache_flush',
		'permission_callback' => 'j5_dp_cache_permission',
		'args'                => array(
			'product_id' => array( 'type' => 'integer', 'required' => false ),
			'all'        => array( 'type' => 'boolean', 'required' => false ),
		),
	) );
} );

/**
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function j5_dp_cache_permission( $request ) {
	// 1. WooCommerce API key with write access (this endpoint changes state).
	if ( function_exists( 'j5_hub_defs_basic_credentials' ) && function_exists( 'wc_api_hash' ) ) {
		list( $ck, $cs ) = j5_hub_defs_basic_credentials( $request );
		if ( '' !== $ck && '' !== $cs ) {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT consumer_secret, permissions FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s",
				wc_api_hash( sanitize_text_field( $ck ) )
			) );
			if ( $row && hash_equals( (string) $row->consumer_secret, $cs ) ) {
				if ( in_array( (string) $row->permissions, array( 'write', 'read_write' ), true ) ) {
					return true;
				}
				return new WP_Error( 'j5_dp_cache_forbidden', 'API key lacks write permission.', array( 'status' => 403 ) );
			}
		}
	}

	// 2. Shared DP secret header.
	if ( defined( 'J5_HUB_DEFS_SECRET' ) && '' !== J5_HUB_DEFS_SECRET ) {
		$sent = isset( $_SERVER['HTTP_X_J5_HUB_SECRET'] ) ? (string) $_SERVER['HTTP_X_J5_HUB_SECRET'] : '';
		if ( '' !== $sent && hash_equals( (string) J5_HUB_DEFS_SECRET, $sent ) ) {
			return true;
		}
	}

	// 3. Staff, for testing from a browser session.
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return true;
	}

	return new WP_Error( 'j5_dp_cache_forbidden', 'Authentication required.', array( 'status' => 401 ) );
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function j5_dp_cache_flush( $request ) {
	nocache_headers();
	$pid = absint( $request->get_param( 'product_id' ) );

	if ( $pid && ! $request->get_param( 'all' ) ) {
		$product = wc_get_product( $pid );
		if ( ! $product ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'product not found', 'product_id' => $pid ), 404 );
		}
		if ( $product->get_parent_id() ) {
			$pid = $product->get_parent_id(); // a variation id was sent: clear its whole family
		}
		$ids = array_merge(
			array( $pid ),
			array_map( 'intval', get_children( array( 'post_parent' => $pid, 'post_type' => 'product_variation', 'fields' => 'ids', 'post_status' => 'any', 'numberposts' => -1 ) ) )
		);
		foreach ( $ids as $id ) {
			clean_post_cache( $id ); // post, post_meta and term caches
			if ( class_exists( 'WC_Cache_Helper' ) ) {
				WC_Cache_Helper::invalidate_cache_group( 'product_' . $id );
			}
		}
		wc_delete_product_transients( $pid ); // children list, variation prices, etc.
		return new WP_REST_Response( array( 'ok' => true, 'scope' => 'product', 'product_id' => $pid, 'cleared' => count( $ids ) ), 200 );
	}

	$ok = wp_cache_flush();
	return new WP_REST_Response( array( 'ok' => (bool) $ok, 'scope' => 'all' ), 200 );
}
