<?php
/**
 * J5 Shipping Restrictions
 * ------------------------
 * Blocks shipments of certain products to certain destinations (e.g. no body
 * armor to Connecticut). A compliance feature: fail-closed.
 *
 * Two rule sets, MERGED at enforcement (union — a destination is blocked if
 * EITHER set matches):
 *   - j5_shipping_rules_hub    : published by the hub (business-management layer)
 *   - j5_shipping_rules_local  : authored in WP admin (J5 Apps → Shipping Restrictions)
 *
 * Neither set can overwrite the other, so a rule can never be silently erased
 * by the other system. The WP admin screen shows hub rules read-only and lets
 * you edit local rules.
 *
 * Rule shape:
 *   [
 *     'label'      => 'No body armor to CT',
 *     'match_type' => 'category' | 'product' | 'tag',
 *     'match'      => term_id (category/tag) | product_id,
 *     'block_type' => 'state' | 'city',
 *     'states'     => [ 'CT', 'NY' ],        // when block_type = state
 *     'cities'     => [ 'chicago', 'aurora' ] // when block_type = city (lowercased)
 *   ]
 *
 * Enforcement:
 *   - Cart: a warning notice when a restricted item is in the cart (uses the
 *     customer's known shipping state/city if available).
 *   - Checkout: a HARD block — the order cannot be placed to a restricted
 *     destination for any matching cart item.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const J5_SHIP_HUB_OPTION   = 'j5_shipping_rules_hub';
const J5_SHIP_LOCAL_OPTION = 'j5_shipping_rules_local';

/**
 * Validate + normalize a raw rule array.
 *
 * Supports TWO shapes:
 *   New (multi-target):  'categories' => [ids], 'tags' => [ids], 'products' => [ids]
 *   Legacy (single):     'match_type' => category|tag|product, 'match' => id
 * Legacy rules are upconverted so old hub-published rules keep working.
 * A product matches if it is in ANY listed category OR has ANY listed tag OR
 * is (or is a variation of) ANY listed product.
 *
 * @param array $r
 * @return array|null normalized rule, or null if invalid
 */
function j5_ship_normalize_rule( $r ) {
	if ( ! is_array( $r ) ) {
		return null;
	}

	$to_ids = function ( $v ) {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $id ) {
				$id = absint( $id );
				if ( $id ) {
					$out[] = $id;
				}
			}
		} elseif ( is_string( $v ) && '' !== trim( $v ) ) {
			// Comma/space separated list of IDs.
			foreach ( preg_split( '/[\s,]+/', $v ) as $id ) {
				$id = absint( $id );
				if ( $id ) {
					$out[] = $id;
				}
			}
		}
		return array_values( array_unique( $out ) );
	};

	$categories = isset( $r['categories'] ) ? $to_ids( $r['categories'] ) : array();
	$tags       = isset( $r['tags'] ) ? $to_ids( $r['tags'] ) : array();
	$products   = isset( $r['products'] ) ? $to_ids( $r['products'] ) : array();

	// Legacy single-target upconversion.
	if ( empty( $categories ) && empty( $tags ) && empty( $products )
		&& ! empty( $r['match_type'] ) && ! empty( $r['match'] ) ) {
		$m = absint( $r['match'] );
		if ( $m ) {
			if ( 'category' === $r['match_type'] ) {
				$categories = array( $m );
			} elseif ( 'tag' === $r['match_type'] ) {
				$tags = array( $m );
			} elseif ( 'product' === $r['match_type'] ) {
				$products = array( $m );
			}
		}
	}

	$block_type = isset( $r['block_type'] ) && in_array( $r['block_type'], array( 'state', 'city' ), true ) ? $r['block_type'] : null;
	if ( ! $block_type ) {
		return null;
	}
	// Must target something.
	if ( empty( $categories ) && empty( $tags ) && empty( $products ) ) {
		return null;
	}

	$states = array();
	if ( ! empty( $r['states'] ) && is_array( $r['states'] ) ) {
		foreach ( $r['states'] as $s ) {
			$s = strtoupper( sanitize_text_field( $s ) );
			if ( '' !== $s ) {
				$states[] = $s;
			}
		}
	}
	$cities = array();
	if ( ! empty( $r['cities'] ) && is_array( $r['cities'] ) ) {
		foreach ( $r['cities'] as $c ) {
			$c = strtolower( trim( sanitize_text_field( $c ) ) );
			if ( '' !== $c ) {
				$cities[] = $c;
			}
		}
	}

	if ( 'state' === $block_type && empty( $states ) ) {
		return null;
	}
	if ( 'city' === $block_type && empty( $cities ) ) {
		return null;
	}

	return array(
		'label'      => isset( $r['label'] ) ? sanitize_text_field( $r['label'] ) : '',
		'categories' => $categories,
		'tags'       => $tags,
		'products'   => $products,
		'block_type' => $block_type,
		'states'     => array_values( array_unique( $states ) ),
		'cities'     => array_values( array_unique( $cities ) ),
	);
}

/**
 * Read + normalize a rule-set option.
 *
 * @param string $option
 * @return array list of normalized rules (with 'source' tag)
 */
function j5_ship_read_rules( $option ) {
	$raw = get_option( $option, array() );
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$source = ( J5_SHIP_HUB_OPTION === $option ) ? 'hub' : 'local';
	$out    = array();
	foreach ( $raw as $r ) {
		$n = j5_ship_normalize_rule( $r );
		if ( $n ) {
			$n['source'] = $source;
			$out[]       = $n;
		}
	}
	return $out;
}

/**
 * The merged rule set (hub + local), enforced as a union.
 *
 * @return array
 */
function j5_ship_all_rules() {
	return array_merge(
		j5_ship_read_rules( J5_SHIP_HUB_OPTION ),
		j5_ship_read_rules( J5_SHIP_LOCAL_OPTION )
	);
}

/**
 * Does a product match a rule's targets?
 * Matches if the product is in ANY listed category, OR has ANY listed tag,
 * OR is (or is a variation of) ANY listed product.
 *
 * @param int   $product_id
 * @param array $rule
 * @return bool
 */
function j5_ship_product_matches( $product_id, $rule ) {
	// Products (including variation → parent).
	if ( ! empty( $rule['products'] ) ) {
		if ( in_array( (int) $product_id, array_map( 'intval', $rule['products'] ), true ) ) {
			return true;
		}
		$p = wc_get_product( $product_id );
		if ( $p && $p->is_type( 'variation' ) && in_array( (int) $p->get_parent_id(), array_map( 'intval', $rule['products'] ), true ) ) {
			return true;
		}
	}

	// Categories — has_term accepts an array (matches ANY).
	if ( ! empty( $rule['categories'] ) && has_term( $rule['categories'], 'product_cat', $product_id ) ) {
		return true;
	}

	// Tags.
	if ( ! empty( $rule['tags'] ) && has_term( $rule['tags'], 'product_tag', $product_id ) ) {
		return true;
	}

	return false;
}

/**
 * Does a destination (state, city) violate a rule?
 *
 * @param array  $rule
 * @param string $state 2-letter state code (uppercased)
 * @param string $city
 * @return bool
 */
function j5_ship_destination_blocked( $rule, $state, $city ) {
	if ( 'state' === $rule['block_type'] ) {
		return in_array( strtoupper( $state ), $rule['states'], true );
	}
	if ( 'city' === $rule['block_type'] ) {
		return in_array( strtolower( trim( $city ) ), $rule['cities'], true );
	}
	return false;
}

/**
 * Evaluate the cart against all rules for a given destination.
 * Returns a list of violations: [ product_id, product_name, rule ].
 *
 * @param string $state
 * @param string $city
 * @return array
 */
function j5_ship_violations( $state, $city ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}
	$rules = j5_ship_all_rules();
	if ( empty( $rules ) ) {
		return array();
	}

	$violations = array();
	foreach ( WC()->cart->get_cart() as $item ) {
		$pid = ! empty( $item['product_id'] ) ? $item['product_id'] : 0;
		if ( ! $pid ) {
			continue;
		}
		foreach ( $rules as $rule ) {
			if ( j5_ship_product_matches( $pid, $rule ) && j5_ship_destination_blocked( $rule, $state, $city ) ) {
				$p                = wc_get_product( $pid );
				$violations[]     = array(
					'product_id'   => $pid,
					'product_name' => $p ? $p->get_name() : ( '#' . $pid ),
					'rule'         => $rule,
				);
			}
		}
	}
	return $violations;
}

/**
 * Whether the cart contains any product that COULD be restricted somewhere
 * (used to decide if a proactive cart warning is worth showing).
 *
 * @return array list of [ product_id, product_name, rule ] for restricted items
 */
function j5_ship_restricted_items_in_cart() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}
	$rules = j5_ship_all_rules();
	$hits  = array();
	foreach ( WC()->cart->get_cart() as $item ) {
		$pid = ! empty( $item['product_id'] ) ? $item['product_id'] : 0;
		if ( ! $pid ) {
			continue;
		}
		foreach ( $rules as $rule ) {
			if ( j5_ship_product_matches( $pid, $rule ) ) {
				$p      = wc_get_product( $pid );
				$hits[] = array(
					'product_id'   => $pid,
					'product_name' => $p ? $p->get_name() : ( '#' . $pid ),
					'rule'         => $rule,
				);
			}
		}
	}
	return $hits;
}

/**
 * Human-readable destination summary for a rule.
 *
 * @param array $rule
 * @return string
 */
function j5_ship_rule_dest_text( $rule ) {
	if ( 'state' === $rule['block_type'] ) {
		return implode( ', ', $rule['states'] );
	}
	return implode( ', ', array_map( 'ucwords', $rule['cities'] ) );
}

/* =========================================================================
 * Cart warning — proactive, uses known customer destination if available
 * ====================================================================== */
add_action( 'woocommerce_check_cart_items', function () {
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}
	// Only warn if we already know a destination (returning customer / entered).
	$state = WC()->customer->get_shipping_state();
	$city  = WC()->customer->get_shipping_city();
	if ( ! $state && ! $city ) {
		// No known destination yet — show a soft heads-up if restricted items exist.
		$hits = j5_ship_restricted_items_in_cart();
		if ( ! empty( $hits ) ) {
			$names = array();
			foreach ( $hits as $h ) {
				$names[ $h['product_id'] ] = $h['product_name'];
			}
			$first = reset( $names );
			wc_add_notice(
				sprintf(
					/* translators: %s product name */
					esc_html__( 'Note: %s has shipping restrictions to certain locations. Enter your address at checkout to confirm availability.', 'astra-child' ),
					esc_html( $first )
				),
				'notice'
			);
		}
		return;
	}

	$violations = j5_ship_violations( $state, $city );
	foreach ( $violations as $v ) {
		wc_add_notice(
			sprintf(
				/* translators: 1: product 2: destination */
				esc_html__( '%1$s cannot be shipped to %2$s and must be removed before checkout.', 'astra-child' ),
				esc_html( $v['product_name'] ),
				esc_html( j5_ship_rule_dest_text( $v['rule'] ) )
			),
			'error'
		);
	}
} );

/* =========================================================================
 * Primary gate: remove ALL payment gateways when the entered destination is
 * restricted. With no gateways, WooCommerce renders no PayPal button and no
 * card form, and the order cannot be submitted — this works regardless of
 * payment method (unlike validation hooks, which some redirect gateways skip).
 * Updates live as the address changes (WC re-runs this on order review AJAX).
 * ====================================================================== */
add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $gateways;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return $gateways;
	}

	$state = WC()->customer->get_shipping_state();
	$city  = WC()->customer->get_shipping_city();
	// Fall back to billing if shipping empty.
	if ( ! $state ) {
		$state = WC()->customer->get_billing_state();
	}
	if ( ! $city ) {
		$city = WC()->customer->get_billing_city();
	}

	if ( ! $state && ! $city ) {
		return $gateways; // No destination yet — leave gateways alone.
	}

	$violations = j5_ship_violations( $state, $city );
	if ( ! empty( $violations ) ) {
		return array(); // Restricted — no payment methods available.
	}
	return $gateways;
} );

/**
 * Show a prominent restriction notice on the checkout page when the entered
 * destination is blocked (pairs with the gateway removal above).
 */
add_action( 'woocommerce_before_checkout_form', function () {
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}
	$state = WC()->customer->get_shipping_state() ?: WC()->customer->get_billing_state();
	$city  = WC()->customer->get_shipping_city() ?: WC()->customer->get_billing_city();
	if ( ! $state && ! $city ) {
		return;
	}
	$violations = j5_ship_violations( $state, $city );
	if ( empty( $violations ) ) {
		return;
	}
	$seen = array();
	foreach ( $violations as $v ) {
		if ( isset( $seen[ $v['product_id'] ] ) ) {
			continue;
		}
		$seen[ $v['product_id'] ] = true;
		wc_print_notice(
			sprintf(
				/* translators: 1: product 2: destination */
				esc_html__( '%1$s cannot be shipped to %2$s due to legal restrictions. Remove it from your cart or ship to a different address to continue.', 'astra-child' ),
				esc_html( $v['product_name'] ),
				esc_html( j5_ship_rule_dest_text( $v['rule'] ) )
			),
			'error'
		);
	}
}, 5 );

/* =========================================================================
 * Checkout — HARD block on the posted shipping (or billing) destination
 * ====================================================================== */
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	// Prefer shipping address; fall back to billing when shipping not used.
	$state = '';
	$city  = '';
	if ( ! empty( $data['ship_to_different_address'] ) ) {
		$state = isset( $data['shipping_state'] ) ? $data['shipping_state'] : '';
		$city  = isset( $data['shipping_city'] ) ? $data['shipping_city'] : '';
	}
	if ( '' === $state && '' === $city ) {
		$state = isset( $data['shipping_state'] ) && '' !== $data['shipping_state'] ? $data['shipping_state'] : ( isset( $data['billing_state'] ) ? $data['billing_state'] : '' );
		$city  = isset( $data['shipping_city'] ) && '' !== $data['shipping_city'] ? $data['shipping_city'] : ( isset( $data['billing_city'] ) ? $data['billing_city'] : '' );
	}

	$violations = j5_ship_violations( $state, $city );
	if ( empty( $violations ) ) {
		return;
	}

	$seen = array();
	foreach ( $violations as $v ) {
		$key = $v['product_id'] . '|' . $v['rule']['block_type'];
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$errors->add(
			'j5_shipping_restricted',
			sprintf(
				/* translators: 1: product 2: destination */
				esc_html__( '%1$s cannot be shipped to %2$s due to legal restrictions. Please remove it from your cart or ship to a different address.', 'astra-child' ),
				esc_html( $v['product_name'] ),
				esc_html( j5_ship_rule_dest_text( $v['rule'] ) )
			)
		);
	}
}, 10, 2 );
