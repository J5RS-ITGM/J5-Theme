<?php
/**
 * Shipping restrictions pushed by the dealer portal (J5-SHIP-DP-1, 2026-11-08).
 *
 * Contract: project doc claude/dp-shipping-restrictions-contract.md + DP addendum 1.
 *
 *   PUT /wp-json/j5/v1/shipping-rules   full replace of the DP rule set
 *   GET /wp-json/j5/v1/shipping-rules   live rules + hash + version (DP "Verify store")
 *   Auth: X-J5-Hub-Secret = J5_HUB_DEFS_SECRET (j5_hub_defs_permission(), same as /redirects).
 *
 * Stored in option j5_shipping_rules_dp as sent (category SLUGS, SKUs, states, city+state).
 * Resolved to IDs on every request, never at push time, so category ID changes can't break
 * a rule. A category covers all of its subcategories. Enforcement is the existing module
 * (inc/j5-shipping-restrictions.php): these rules are merged with the hub and local sets,
 * and any match removes every payment method for that destination (fail-closed).
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5_SHIP_DP_OPTION = 'j5_shipping_rules_dp';

/** The stored set: [ 'source', 'version', 'stored_at', 'rules' => [...] ]. */
function j5_ship_dp_stored() {
	$s = get_option( J5_SHIP_DP_OPTION, array() );
	return is_array( $s ) ? $s : array();
}

/** Deterministic hash of a stored rule list (the DP compares it on Verify). */
function j5_ship_dp_hash( $rules ) {
	return hash( 'sha256', (string) wp_json_encode( array_values( (array) $rules ) ) );
}

/** Category slug -> its term id plus every descendant id. Empty when the slug is gone. */
function j5_ship_dp_cat_ids( $slug ) {
	$t = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $t || is_wp_error( $t ) ) {
		return array();
	}
	$kids = get_term_children( $t->term_id, 'product_cat' );
	return array_map( 'intval', array_merge( array( $t->term_id ), is_wp_error( $kids ) ? array() : $kids ) );
}

/**
 * The DP rules in the enforcement module's normalized shape, resolved now.
 * Slugs that no longer exist are collected for the admin screen.
 *
 * @return array
 */
function j5_ship_dp_rules_resolved() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache   = array();
	$missing = array();
	$stored  = j5_ship_dp_stored();
	foreach ( (array) ( $stored['rules'] ?? array() ) as $r ) {
		$cats = array();
		foreach ( (array) ( $r['categories'] ?? array() ) as $slug ) {
			$ids = j5_ship_dp_cat_ids( $slug );
			if ( ! $ids ) {
				$missing[] = 'category ' . $slug;
			}
			$cats = array_merge( $cats, $ids );
		}
		$tags = array();
		foreach ( (array) ( $r['tags'] ?? array() ) as $slug ) {
			$t = get_term_by( 'slug', $slug, 'product_tag' );
			if ( $t && ! is_wp_error( $t ) ) {
				$tags[] = (int) $t->term_id;
			} else {
				$missing[] = 'tag ' . $slug;
			}
		}
		$products = array();
		foreach ( (array) ( $r['skus'] ?? array() ) as $sku ) {
			$pid = (int) wc_get_product_id_by_sku( $sku );
			if ( $pid ) {
				$products[] = $pid;
			}
		}
		$states      = array_values( array_map( 'strtoupper', (array) ( $r['states'] ?? array() ) ) );
		$cities      = array();
		$city_states = array();
		foreach ( (array) ( $r['cities'] ?? array() ) as $c ) {
			$name          = strtolower( trim( (string) ( $c['city'] ?? '' ) ) );
			$cities[]      = $name;
			$city_states[] = $name . '|' . strtoupper( (string) ( $c['state'] ?? '' ) );
		}
		$cache[] = array(
			'label'       => (string) ( $r['label'] ?? '' ),
			'categories'  => array_values( array_unique( $cats ) ),
			'tags'        => array_values( array_unique( $tags ) ),
			'products'    => array_values( array_unique( $products ) ),
			'block_type'  => $states ? 'state' : 'city',
			'states'      => $states,
			'cities'      => array_values( array_unique( $cities ) ),
			'city_states' => array_values( array_unique( $city_states ) ),
			'source'      => 'dp',
			'dp_id'       => (string) ( $r['id'] ?? '' ),
		);
	}
	$GLOBALS['j5_ship_dp_missing'] = array_values( array_unique( $missing ) );
	return $cache;
}

/**
 * Validate a pushed body. Returns [ clean rules, warnings ] or WP_Error (400) listing every
 * problem: an unknown category slug or tag must never be stored, since a typo would quietly
 * leave products unblocked.
 */
function j5_ship_dp_validate( $body ) {
	if ( ! is_array( $body ) || ! isset( $body['rules'] ) || ! is_array( $body['rules'] ) ) {
		return new WP_Error( 'j5_ship_invalid', 'Shipping rules not saved: expected {"source","version","rules":[...]}.', array( 'status' => 400 ) );
	}
	$bad      = array();
	$warnings = array();
	$clean    = array();
	foreach ( $body['rules'] as $i => $r ) {
		if ( ! is_array( $r ) ) {
			$bad[] = 'rule ' . ( $i + 1 ) . ' is not an object';
			continue;
		}
		if ( isset( $r['active'] ) && ! $r['active'] ) {
			continue;
		}
		$label = sanitize_text_field( (string) ( $r['label'] ?? '' ) );
		$name  = '' !== $label ? '"' . $label . '"' : 'rule ' . ( $i + 1 );
		$id    = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $r['id'] ?? '' ) );

		$cats = array();
		foreach ( (array) ( $r['categories'] ?? array() ) as $s ) {
			$s = sanitize_title( (string) $s );
			if ( '' === $s ) { continue; }
			if ( ! get_term_by( 'slug', $s, 'product_cat' ) ) {
				$bad[] = $name . ': unknown category slug "' . $s . '"';
			}
			$cats[] = $s;
		}
		$tags = array();
		foreach ( (array) ( $r['tags'] ?? array() ) as $s ) {
			$s = sanitize_title( (string) $s );
			if ( '' === $s ) { continue; }
			if ( ! get_term_by( 'slug', $s, 'product_tag' ) ) {
				$bad[] = $name . ': unknown tag slug "' . $s . '"';
			}
			$tags[] = $s;
		}
		$skus    = array();
		$unknown = array();
		foreach ( (array) ( $r['skus'] ?? array() ) as $s ) {
			$s = trim( wp_strip_all_tags( (string) $s ) );
			if ( '' === $s ) { continue; }
			if ( ! wc_get_product_id_by_sku( $s ) ) {
				$unknown[] = $s;
			}
			$skus[] = $s;
		}
		if ( $unknown ) {
			$warnings[] = $name . ': ' . count( $unknown ) . ' SKU(s) not on the store yet: ' . implode( ', ', array_slice( $unknown, 0, 10 ) ) . ( count( $unknown ) > 10 ? ', ...' : '' );
		}
		$states = array();
		foreach ( (array) ( $r['states'] ?? array() ) as $s ) {
			$s = strtoupper( trim( (string) $s ) );
			if ( ! preg_match( '/^[A-Z]{2}$/', $s ) ) {
				$bad[] = $name . ': bad state code "' . $s . '"';
				continue;
			}
			$states[] = $s;
		}
		$cities = array();
		foreach ( (array) ( $r['cities'] ?? array() ) as $c ) {
			$city  = is_array( $c ) ? trim( sanitize_text_field( (string) ( $c['city'] ?? '' ) ) ) : '';
			$state = is_array( $c ) ? strtoupper( trim( (string) ( $c['state'] ?? '' ) ) ) : '';
			if ( '' === $city || ! preg_match( '/^[A-Z]{2}$/', $state ) ) {
				$bad[] = $name . ': each city needs a name and a 2-letter state';
				continue;
			}
			$cities[] = array( 'city' => $city, 'state' => $state );
		}
		if ( ! $cats && ! $tags && ! $skus ) {
			$bad[] = $name . ': no categories, tags or SKUs';
		}
		if ( ! $states && ! $cities ) {
			$bad[] = $name . ': no states or cities';
		}
		$clean[] = array(
			'id'         => $id,
			'label'      => $label,
			'categories' => array_values( array_unique( $cats ) ),
			'tags'       => array_values( array_unique( $tags ) ),
			'skus'       => array_values( array_unique( $skus ) ),
			'states'     => array_values( array_unique( $states ) ),
			'cities'     => $cities,
		);
	}
	if ( $bad ) {
		return new WP_Error( 'j5_ship_invalid', 'Shipping rules not saved. Fix these and push again: ' . implode( '; ', array_values( array_unique( $bad ) ) ), array( 'status' => 400 ) );
	}
	return array( $clean, $warnings );
}

add_action( 'rest_api_init', function () {
	if ( ! function_exists( 'j5_hub_defs_permission' ) ) {
		return;
	}
	register_rest_route( 'j5/v1', '/shipping-rules', array(
		array(
			'methods'             => 'PUT',
			'permission_callback' => 'j5_hub_defs_permission',
			'callback'            => function ( WP_REST_Request $request ) {
				$body = $request->get_json_params();
				$v    = j5_ship_dp_validate( $body );
				if ( is_wp_error( $v ) ) {
					return $v;
				}
				list( $clean, $warnings ) = $v;
				update_option( J5_SHIP_DP_OPTION, array(
					'source'    => sanitize_key( (string) ( $body['source'] ?? 'dp' ) ),
					'version'   => sanitize_text_field( (string) ( $body['version'] ?? '' ) ),
					'stored_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'rules'     => $clean,
				), true );
				$res = rest_ensure_response( array( 'ok' => true, 'rules' => count( $clean ), 'warnings' => $warnings, 'hash' => j5_ship_dp_hash( $clean ) ) );
				$res->header( 'Cache-Control', 'no-store, private' );
				return $res;
			},
		),
		array(
			'methods'             => 'GET',
			'permission_callback' => 'j5_hub_defs_permission',
			'callback'            => function () {
				$s     = j5_ship_dp_stored();
				$rules = (array) ( $s['rules'] ?? array() );
				$res   = rest_ensure_response( array( 'rules' => $rules, 'hash' => j5_ship_dp_hash( $rules ), 'version' => (string) ( $s['version'] ?? '' ), 'source' => (string) ( $s['source'] ?? '' ) ) );
				$res->header( 'Cache-Control', 'no-store, private' );
				return $res;
			},
		),
	) );
} );
