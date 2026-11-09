<?php
/**
 * Shipping rates pushed by the dealer portal + the J5 shipping method that charges them
 * (J5-SHIP-RATES-1, 2026-11-09).
 *
 * Contract: claude/dp-shipping-restrictions-contract.md + the DP addendum (settings keys,
 * class_charges / default_class / oversize_class added 2026-11-09).
 *
 *   PUT /wp-json/j5/v1/shipping-rates   full replace of { settings, rules }
 *   GET /wp-json/j5/v1/shipping-rates   live settings + rules + hash + version
 *   Auth: X-J5-Hub-Secret = J5_HUB_DEFS_SECRET.
 *
 * Shipping method "J5 Shipping (dealer portal)" (id j5_dp_shipping) produces ONE shipping line.
 * It changes nothing until it is added to a shipping zone in WooCommerce > Settings > Shipping.
 * Until then the existing flat_rate / Advanced Shipping methods keep charging as today.
 *
 * Checkout math (j5_rates_quote()):
 *   Per cart item, matching rate rules decide its role. Precedence: free > flat > surcharge;
 *   exclude_free applies regardless.
 *     free      : item leaves the calculation (no source, no class charge, never blocks free shipping)
 *     flat      : item counts toward the flat override (highest matching amount replaces the base),
 *                 adds no source fee and no class charge
 *     surcharge : each matching rule's amount is added once per cart
 *   Base, from the remaining ("normal") items, by settings.mode:
 *     flat : settings.flat
 *     fee  : first + extra_fee for each additional ship-from class
 *     each : class_charges summed over the distinct ship-from classes
 *   Ship-from class = the item's WC shipping class, or default_class when it has none.
 *   The oversize class is not a ship-from source: its class charge is added once on top in
 *   every mode. A cart with only oversize items is charged as one default_class source.
 *   Free shipping (free_enabled, subtotal after coupons >= free_min, no exclude_free item, and
 *   no oversize item when free_excludes_oversize) zeroes the base; surcharges and the oversize
 *   charge still apply.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5_RATES_DP_OPTION = 'j5_shipping_rates_dp';

function j5_rates_dp_stored() {
	$s = get_option( J5_RATES_DP_OPTION, array() );
	return is_array( $s ) ? $s : array();
}

/** "14.95" -> 14.95; null/'' -> null; anything else -> false. */
function j5_rates_money( $v ) {
	if ( null === $v || '' === $v ) {
		return null;
	}
	if ( is_int( $v ) || is_float( $v ) ) {
		$v = (string) $v;
	}
	if ( ! is_string( $v ) || ! preg_match( '/^\d{1,6}(\.\d{1,2})?$/', trim( $v ) ) ) {
		return false;
	}
	return round( (float) $v, 2 );
}

/** Money back out as the DP's decimal string. */
function j5_rates_money_str( $f ) {
	return null === $f ? null : number_format( (float) $f, 2, '.', '' );
}

/**
 * Validate a pushed rates body. Returns [ settings, rules, warnings ] or WP_Error (400).
 */
function j5_rates_dp_validate( $body ) {
	if ( ! is_array( $body ) || ! isset( $body['settings'] ) || ! is_array( $body['settings'] ) || ! isset( $body['rules'] ) || ! is_array( $body['rules'] ) ) {
		return new WP_Error( 'j5_rates_invalid', 'Shipping rates not saved: expected {"source","version","settings":{...},"rules":[...]}.', array( 'status' => 400 ) );
	}
	$bad      = array();
	$warnings = array();
	$in       = $body['settings'];

	$mode = (string) ( $in['mode'] ?? '' );
	if ( ! in_array( $mode, array( 'flat', 'fee', 'each' ), true ) ) {
		$bad[] = 'settings.mode must be flat, fee or each (got "' . $mode . '")';
	}
	$money = array();
	foreach ( array( 'flat', 'first', 'extra_fee', 'free_min' ) as $k ) {
		$m = j5_rates_money( $in[ $k ] ?? null );
		if ( false === $m ) {
			$bad[] = 'settings.' . $k . ' is not a decimal like "14.95"';
			$m     = null;
		}
		$money[ $k ] = $m;
	}
	$free_enabled = ! empty( $in['free_enabled'] );
	if ( 'flat' === $mode && null === $money['flat'] ) {
		$bad[] = 'settings.flat is required when mode is flat';
	}
	if ( 'fee' === $mode && null === $money['first'] ) {
		$bad[] = 'settings.first is required when mode is fee';
	}
	if ( $free_enabled && null === $money['free_min'] ) {
		$bad[] = 'settings.free_min is required when free_enabled is true';
	}

	$charges = array();
	foreach ( (array) ( $in['class_charges'] ?? array() ) as $slug => $amt ) {
		$slug = sanitize_title( (string) $slug );
		$m    = j5_rates_money( $amt );
		if ( '' === $slug ) { continue; }
		if ( false === $m ) {
			$bad[] = 'settings.class_charges.' . $slug . ' is not a decimal like "14.95"';
			continue;
		}
		if ( ! get_term_by( 'slug', $slug, 'product_shipping_class' ) ) {
			$warnings[] = 'class_charges: shipping class "' . $slug . '" is not on the store yet';
		}
		$charges[ $slug ] = j5_rates_money_str( $m );
	}
	ksort( $charges );
	$default_class  = sanitize_title( (string) ( $in['default_class'] ?? '' ) );
	$oversize_class = sanitize_title( (string) ( $in['oversize_class'] ?? '' ) );
	if ( 'each' === $mode ) {
		if ( '' === $default_class || null === ( $charges[ $default_class ] ?? null ) ) {
			$bad[] = 'mode each needs default_class with a charge in class_charges';
		}
		foreach ( $charges as $slug => $c ) {
			if ( null === $c && $slug !== $oversize_class ) {
				$warnings[] = 'class_charges.' . $slug . ' is empty: items in that class are charged the default_class amount';
			}
		}
	}
	if ( '' !== $oversize_class && null === ( $charges[ $oversize_class ] ?? null ) ) {
		$warnings[] = 'oversize_class "' . $oversize_class . '" has no charge in class_charges: no oversize charge will be added';
	}

	$settings = array(
		'mode'                   => $mode,
		'flat'                   => j5_rates_money_str( $money['flat'] ),
		'first'                  => j5_rates_money_str( $money['first'] ),
		'extra_fee'              => j5_rates_money_str( $money['extra_fee'] ),
		'free_enabled'           => $free_enabled,
		'free_min'               => j5_rates_money_str( $money['free_min'] ),
		'free_excludes_oversize' => ! empty( $in['free_excludes_oversize'] ),
		'checkout_label'         => isset( $in['checkout_label'] ) && '' !== trim( (string) $in['checkout_label'] ) ? sanitize_text_field( (string) $in['checkout_label'] ) : null,
		'class_charges'          => $charges,
		'default_class'          => '' !== $default_class ? $default_class : null,
		'oversize_class'         => '' !== $oversize_class ? $oversize_class : null,
	);

	$rules = array();
	foreach ( $body['rules'] as $i => $r ) {
		if ( ! is_array( $r ) ) {
			$bad[] = 'rate rule ' . ( $i + 1 ) . ' is not an object';
			continue;
		}
		if ( isset( $r['active'] ) && ! $r['active'] ) {
			continue;
		}
		$label  = sanitize_text_field( (string) ( $r['label'] ?? '' ) );
		$name   = '' !== $label ? '"' . $label . '"' : 'rate rule ' . ( $i + 1 );
		$t      = j5_ship_dp_validate_targets( $r, $name, $bad, $warnings );
		$effect = (string) ( $r['effect'] ?? '' );
		$amount = null;
		if ( ! in_array( $effect, array( 'surcharge', 'flat', 'exclude_free', 'free' ), true ) ) {
			$bad[] = $name . ': effect must be surcharge, flat, exclude_free or free';
		} elseif ( in_array( $effect, array( 'surcharge', 'flat' ), true ) ) {
			$m = j5_rates_money( $r['amount'] ?? null );
			if ( null === $m || false === $m ) {
				$bad[] = $name . ': ' . $effect . ' needs an amount like "14.95"';
			} else {
				$amount = j5_rates_money_str( $m );
			}
		}
		$rules[] = array(
			'id'         => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $r['id'] ?? '' ) ),
			'label'      => $label,
			'categories' => $t['categories'],
			'tags'       => $t['tags'],
			'skus'       => $t['skus'],
			'effect'     => $effect,
			'amount'     => $amount,
		);
	}

	if ( $bad ) {
		return new WP_Error( 'j5_rates_invalid', 'Shipping rates not saved. Fix these and push again: ' . implode( '; ', array_values( array_unique( $bad ) ) ), array( 'status' => 400 ) );
	}
	return array( $settings, $rules, $warnings );
}

/** Stored rate rules resolved to ids for the matcher. */
function j5_rates_dp_rules_resolved() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache   = array();
	$missing = array();
	foreach ( (array) ( j5_rates_dp_stored()['rules'] ?? array() ) as $r ) {
		$cache[] = array_merge( j5_ship_dp_resolve_targets( $r, $missing ), array(
			'id'     => (string) ( $r['id'] ?? '' ),
			'label'  => (string) ( $r['label'] ?? '' ),
			'effect' => (string) ( $r['effect'] ?? '' ),
			'amount' => null === ( $r['amount'] ?? null ) ? null : (float) $r['amount'],
		) );
	}
	$GLOBALS['j5_rates_dp_missing'] = array_values( array_unique( $missing ) );
	return $cache;
}

/**
 * The checkout math. Pure apart from the rule matcher, so it can be previewed and tested.
 *
 * @param array $items    each [ 'product_id', 'variation_id', 'class' (slug or ''), 'line_total' ]
 * @param array $settings stored settings
 * @param array $rules    resolved rate rules
 * @return array|null [ 'cost' => float, 'label' => string, 'lines' => [ why ] ], null when no rate can be given
 */
function j5_rates_quote( $items, $settings, $rules ) {
	if ( empty( $settings['mode'] ) || ! $items ) {
		return null;
	}
	$charges  = (array) ( $settings['class_charges'] ?? array() );
	$default  = (string) ( $settings['default_class'] ?? '' );
	$oversize = (string) ( $settings['oversize_class'] ?? '' );
	$subtotal = 0.0;
	$sources  = array();
	$flat_amt = null;
	$sur      = array(); // rule id => amount, once per cart
	$excl     = false;
	$has_over = false;
	$has_norm = false;
	$why      = array();

	foreach ( $items as $it ) {
		$subtotal += (float) ( $it['line_total'] ?? 0 );
		$matched   = array();
		foreach ( $rules as $r ) {
			if ( j5_ship_item_matches( $it, $r ) ) {
				$matched[] = $r;
			}
		}
		$effects = array_column( $matched, 'effect' );
		if ( in_array( 'exclude_free', $effects, true ) ) {
			$excl = true;
		}
		if ( in_array( 'free', $effects, true ) ) {
			continue;
		}
		if ( in_array( 'flat', $effects, true ) ) {
			foreach ( $matched as $r ) {
				if ( 'flat' === $r['effect'] ) {
					$flat_amt = null === $flat_amt ? $r['amount'] : max( $flat_amt, $r['amount'] );
				}
			}
			continue;
		}
		foreach ( $matched as $r ) {
			if ( 'surcharge' === $r['effect'] ) {
				$sur[ $r['id'] . '|' . $r['label'] ] = (float) $r['amount'];
			}
		}
		$class = '' !== (string) ( $it['class'] ?? '' ) ? (string) $it['class'] : $default;
		if ( '' !== $oversize && $class === $oversize ) {
			$has_over = true;
			continue;
		}
		$has_norm         = true;
		$sources[ $class ] = true;
	}
	if ( $has_over && ! $has_norm ) {
		$sources[ $default ] = true; // oversize only: still pays one base source
		$has_norm            = true;
	}

	$mode = $settings['mode'];
	$base = 0.0;
	if ( null !== $flat_amt ) {
		$base  = (float) $flat_amt;
		$why[] = 'flat rule ' . j5_rates_money_str( $base );
	} elseif ( $has_norm ) {
		if ( 'flat' === $mode ) {
			$base  = (float) $settings['flat'];
			$why[] = 'flat ' . j5_rates_money_str( $base );
		} elseif ( 'fee' === $mode ) {
			$n     = count( $sources );
			$base  = (float) $settings['first'] + max( 0, $n - 1 ) * (float) ( $settings['extra_fee'] ?? 0 );
			$why[] = 'fee: ' . $n . ' source(s) ' . j5_rates_money_str( $base );
		} else {
			foreach ( array_keys( $sources ) as $cls ) {
				$c = $charges[ $cls ] ?? null;
				if ( null === $c ) {
					$c = $charges[ $default ] ?? null;
				}
				if ( null === $c ) {
					return null; // no charge for this class and no default: offer no rate rather than undercharge
				}
				$base += (float) $c;
				$why[] = $cls . ' ' . j5_rates_money_str( $c );
			}
		}
	}

	$free = ! empty( $settings['free_enabled'] ) && null !== ( $settings['free_min'] ?? null )
		&& round( $subtotal, 2 ) >= (float) $settings['free_min']
		&& ! $excl
		&& ! ( ! empty( $settings['free_excludes_oversize'] ) && $has_over );
	if ( $free && $base > 0 ) {
		$why[] = 'free shipping (subtotal ' . j5_rates_money_str( $subtotal ) . ')';
		$base  = 0.0;
	}

	$cost = $base;
	foreach ( $sur as $k => $amt ) {
		$cost += $amt;
		$why[] = 'surcharge ' . substr( $k, strpos( $k, '|' ) + 1 ) . ' ' . j5_rates_money_str( $amt );
	}
	if ( $has_over && null !== ( $charges[ $oversize ] ?? null ) ) {
		$cost += (float) $charges[ $oversize ];
		$why[] = 'oversize ' . j5_rates_money_str( $charges[ $oversize ] );
	}
	return array(
		'cost'  => round( $cost, 2 ),
		'label' => ! empty( $settings['checkout_label'] ) ? $settings['checkout_label'] : 'Shipping',
		'lines' => $why,
	);
}

/** Cart package -> items for j5_rates_quote(). */
function j5_rates_package_items( $package ) {
	$items = array();
	foreach ( (array) ( $package['contents'] ?? array() ) as $it ) {
		$p = isset( $it['data'] ) && $it['data'] instanceof WC_Product ? $it['data'] : null;
		if ( ! $p || ! $p->needs_shipping() ) {
			continue;
		}
		$items[] = array(
			'product_id'   => (int) ( $it['product_id'] ?? 0 ),
			'variation_id' => (int) ( $it['variation_id'] ?? 0 ),
			'class'        => (string) $p->get_shipping_class(),
			'line_total'   => (float) ( $it['line_total'] ?? 0 ),
		);
	}
	return $items;
}

/**
 * Quote any list of SKUs without a cart, for checking against the current shipping on the server:
 *   wp eval 'print_r( j5_rates_preview( array( "HES-4400-L" => 1, "SAM-SPLINT" => 2 ) ) );'
 */
function j5_rates_preview( $skus ) {
	$items = array();
	foreach ( (array) $skus as $sku => $qty ) {
		$id = j5_ship_dp_sku_id( $sku );
		$p  = $id ? wc_get_product( $id ) : null;
		if ( ! $p ) {
			return 'Unknown SKU ' . $sku;
		}
		$items[] = array(
			'product_id'   => $p->get_parent_id() ? $p->get_parent_id() : $p->get_id(),
			'variation_id' => $p->get_parent_id() ? $p->get_id() : 0,
			'class'        => (string) $p->get_shipping_class(),
			'line_total'   => (float) $p->get_price() * max( 1, (int) $qty ),
		);
	}
	return j5_rates_quote( $items, (array) ( j5_rates_dp_stored()['settings'] ?? array() ), j5_rates_dp_rules_resolved() );
}

/* ---------------------------------------------------------------- REST */

add_action( 'rest_api_init', function () {
	if ( ! function_exists( 'j5_hub_defs_permission' ) ) {
		return;
	}
	register_rest_route( 'j5/v1', '/shipping-rates', array(
		array(
			'methods'             => 'PUT',
			'permission_callback' => 'j5_hub_defs_permission',
			'callback'            => function ( WP_REST_Request $request ) {
				$body = $request->get_json_params();
				$v    = j5_rates_dp_validate( $body );
				if ( is_wp_error( $v ) ) {
					return $v;
				}
				list( $settings, $rules, $warnings ) = $v;
				update_option( J5_RATES_DP_OPTION, array(
					'source'    => sanitize_key( (string) ( $body['source'] ?? 'dp' ) ),
					'version'   => sanitize_text_field( (string) ( $body['version'] ?? '' ) ),
					'stored_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'settings'  => $settings,
					'rules'     => $rules,
				), true );
				// Cached shipping quotes would keep the old prices until the cart changes.
				if ( class_exists( 'WC_Cache_Helper' ) ) {
					WC_Cache_Helper::get_transient_version( 'shipping', true );
				}
				$res = rest_ensure_response( array( 'ok' => true, 'rules' => count( $rules ), 'warnings' => $warnings, 'hash' => j5_ship_dp_hash( array( 'settings' => $settings, 'rules' => array_values( $rules ) ) ) ) );
				$res->header( 'Cache-Control', 'no-store, private' );
				return $res;
			},
		),
		array(
			'methods'             => 'GET',
			'permission_callback' => 'j5_hub_defs_permission',
			'callback'            => function () {
				$s        = j5_rates_dp_stored();
				$settings = (array) ( $s['settings'] ?? array() );
				$rules    = array_values( (array) ( $s['rules'] ?? array() ) );
				$res      = rest_ensure_response( array( 'settings' => $settings, 'rules' => $rules, 'hash' => j5_ship_dp_hash( array( 'settings' => $settings, 'rules' => $rules ) ), 'version' => (string) ( $s['version'] ?? '' ), 'source' => (string) ( $s['source'] ?? '' ) ) );
				$res->header( 'Cache-Control', 'no-store, private' );
				return $res;
			},
		),
	) );
} );

/* ---------------------------------------------------------------- shipping method */

add_action( 'woocommerce_shipping_init', function () {
	if ( class_exists( 'J5_DP_Shipping_Method' ) || ! class_exists( 'WC_Shipping_Method' ) ) {
		return;
	}
	class J5_DP_Shipping_Method extends WC_Shipping_Method {
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'j5_dp_shipping';
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = 'J5 Shipping (dealer portal)';
			$this->method_description = 'One shipping line priced by the rates and rules pushed from the dealer portal (/wp-json/j5/v1/shipping-rates). Edit prices in the dealer portal, not here.';
			$this->supports           = array( 'shipping-zones', 'instance-settings' );
			$this->instance_form_fields = array();
			$this->title              = 'J5 Shipping';
			$this->init_settings();
			$this->enabled = 'yes';
		}

		public function calculate_shipping( $package = array() ) {
			$stored = j5_rates_dp_stored();
			if ( empty( $stored['settings']['mode'] ) ) {
				return; // nothing pushed yet: offer no rate rather than $0
			}
			$q = j5_rates_quote( j5_rates_package_items( $package ), $stored['settings'], j5_rates_dp_rules_resolved() );
			if ( ! $q ) {
				return;
			}
			$this->add_rate( array(
				'id'        => $this->get_rate_id(),
				'label'     => $q['label'],
				'cost'      => $q['cost'],
				'package'   => $package,
				'meta_data' => array( '_j5_rate_breakdown' => implode( '; ', $q['lines'] ) ),
			) );
		}
	}
} );

add_filter( 'woocommerce_shipping_methods', function ( $methods ) {
	if ( class_exists( 'J5_DP_Shipping_Method' ) ) {
		$methods['j5_dp_shipping'] = 'J5_DP_Shipping_Method';
	}
	return $methods;
} );
