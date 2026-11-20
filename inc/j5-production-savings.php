<?php
/**
 * J5 In Production Savings (J5IP-1, 2026-11-19).
 *
 * A plate the manufacturer is already building (DP _j5_fulfillment = in_production)
 * gets half of the factory order rate. Rate per variation / simple product:
 *   1. _j5_production_discount on the item, if the DP ever pushes one ("5" / "2.5");
 *   2. otherwise half of the highest _j5_factory_discount among its sibling options
 *      (Hesco 3612 siblings at 10% -> 5%; Hesco 4403 siblings at 5% -> 2.5%).
 * Nothing to halve (no override, no sibling rate) -> no discount.
 *
 * In production items carry no factory rate of their own, so they never get
 * factory order savings. If one ever does, factory savings wins and this skips it.
 *
 * Same mechanics as inc/j5-factory-savings.php: auto applied virtual coupon
 * (in-production-savings), checked live at every cart calculation, MAP safe
 * (product page price unchanged), no Remove link, stacks with typed coupons.
 * ArmorSync in-stock plates (cart item j5_as) are excluded.
 * Named order rows and per-line "Savings" meta: inc/j5-order-discounts.php.
 *
 * Loaded from the bottom of inc/j5-factory-savings.php (uses j5fs_pct / j5fs_fmt).
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5IP_CODE  = 'in-production-savings';
const J5IP_LABEL = 'In production savings';
const J5IP_SHARE = 0.5; // share of the factory order rate

/* =========================================================================
 * 1. ELIGIBILITY
 * ====================================================================== */

/** Highest raw factory rate among a variable parent's options. Cached per request. */
function j5ip_parent_factory_max( $parent_id ) {
	static $cache = array();
	$parent_id = (int) $parent_id;
	if ( $parent_id <= 0 ) {
		return 0.0;
	}
	if ( isset( $cache[ $parent_id ] ) ) {
		return $cache[ $parent_id ];
	}
	$max    = 0.0;
	$parent = wc_get_product( $parent_id );
	if ( $parent && $parent->is_type( 'variable' ) ) {
		foreach ( $parent->get_children() as $vid ) {
			$raw = trim( (string) get_post_meta( $vid, '_j5_factory_discount', true ) );
			if ( '' !== $raw && is_numeric( $raw ) ) {
				$max = max( $max, (float) $raw );
			}
		}
	}
	$cache[ $parent_id ] = ( $max > 0 && $max < 100 ) ? $max : 0.0;
	return $cache[ $parent_id ];
}

/**
 * Live In Production percent for a variation or simple product. 0 when not eligible.
 *
 * @param WC_Product|null $product
 * @return float
 */
function j5ip_pct( $product ) {
	if ( ! $product instanceof WC_Product || $product->is_type( 'variable' ) ) {
		return 0.0;
	}
	if ( 'in_production' !== strtolower( trim( (string) $product->get_meta( '_j5_fulfillment', true ) ) ) ) {
		return 0.0;
	}
	if ( function_exists( 'j5fs_pct' ) && j5fs_pct( $product ) > 0 ) {
		return 0.0; // carries its own factory rate: factory savings applies instead
	}
	$own = trim( (string) $product->get_meta( '_j5_production_discount', true ) );
	if ( '' !== $own && is_numeric( $own ) ) {
		$pct = (float) $own;
	} else {
		$pct = j5ip_parent_factory_max( $product->get_parent_id() ) * J5IP_SHARE;
	}
	$pct = round( $pct, 2 );
	return ( $pct > 0 && $pct < 100 ) ? $pct : 0.0;
}

/** "5" / "2.5". */
function j5ip_fmt( $pct ) {
	return function_exists( 'j5fs_fmt' ) ? j5fs_fmt( $pct ) : rtrim( rtrim( number_format( (float) $pct, 2, '.', '' ), '0' ), '.' );
}

/** Highest live percent across a variable product's options (or the simple product's own). */
function j5ip_max_pct( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return 0.0;
	}
	if ( ! $product->is_type( 'variable' ) ) {
		return j5ip_pct( $product );
	}
	$max = 0.0;
	foreach ( $product->get_children() as $vid ) {
		$max = max( $max, j5ip_pct( wc_get_product( $vid ) ) );
	}
	return $max;
}

/* =========================================================================
 * 2. PRODUCT PAGE: callout (shown only for an In Production option)
 *    Price on the page never changes (MAP).
 * ====================================================================== */

/** Variations JSON: percent, so the callout follows the selected option. */
add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {
	$pct              = j5ip_pct( $variation );
	$data['j5_ip_pct'] = $pct > 0 ? j5ip_fmt( $pct ) : '';
	return $data;
}, 23, 3 );

/**
 * Printed by j5fs_render_line() (right after the stock banner). Variable products
 * render it hidden; the inline script shows it when an In Production option is picked.
 *
 * @param WC_Product $product
 */
function j5ip_render_line( $product ) {
	$variable = $product instanceof WC_Product && $product->is_type( 'variable' );
	$pct      = $variable ? j5ip_max_pct( $product ) : j5ip_pct( $product );
	if ( $pct <= 0 ) {
		return;
	}
	printf(
		'<div class="j5-fs-callout j5-ip-callout" data-j5-ip%1$s>'
			. '<div class="j5-fs-callout__badge" aria-hidden="true"><b><span data-j5-ip-pct>%2$s</span>%%</b><span>off</span></div>'
			. '<div class="j5-fs-callout__body">'
				. '<p class="j5-fs-callout__title">%3$s</p>'
				. '<p class="j5-fs-callout__text">These plates are <strong>in production</strong>, on an open order the manufacturer has confirmed. Get in line now and save <strong><span data-j5-ip-pct>%2$s</span>%%</strong>. We ship them to you as soon as they arrive.</p>'
				. '<p class="j5-fs-callout__how">Discount applied automatically in your cart. No code needed.%4$s</p>'
			. '</div>'
		. '</div>',
		$variable ? ' hidden' : '',
		esc_html( j5ip_fmt( $pct ) ),
		esc_html( J5IP_LABEL ),
		( $faq = j5ip_faq_url() ) ? ' <a class="j5-fs-callout__link" href="' . esc_url( $faq ) . '">What in production means &rarr;</a>' : ''
	);
}

/** FAQ answer the callout links to (#in-production, added by content/pages/add-in-production-faq.php). */
function j5ip_faq_url() {
	$id  = (int) apply_filters( 'j5_faq_page_id', 9041 );
	$url = $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '';
	return $url ? $url . '#in-production' : '';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product || ! $product->is_type( 'variable' ) || j5ip_max_pct( $product ) <= 0 ) {
		return;
	}
	$js = <<<'JS'
( function () {
	if ( ! window.jQuery ) { return; }
	jQuery( function ( $ ) {
		var $box = $( '[data-j5-ip]' );
		if ( ! $box.length ) { return; }
		$( '.j5-variations-form' )
			.on( 'found_variation', function ( e, v ) {
				if ( v && v.j5_ip_pct ) {
					$box.find( '[data-j5-ip-pct]' ).text( v.j5_ip_pct );
					$box.prop( 'hidden', false );
				} else {
					$box.prop( 'hidden', true );
				}
			} )
			.on( 'hide_variation reset_data', function () { $box.prop( 'hidden', true ); } );
	} );
} )();
JS;
	wp_add_inline_script( 'j5-shop', $js );
}, 41 );

/* =========================================================================
 * 3. CART: virtual auto-applied coupon
 * ====================================================================== */

function j5ip_is_code( $coupon_or_code ) {
	$code = $coupon_or_code instanceof WC_Coupon ? $coupon_or_code->get_code() : (string) $coupon_or_code;
	return wc_format_coupon_code( $code ) === J5IP_CODE;
}

/** True when a cart line qualifies. */
function j5ip_line_pct( $item ) {
	if ( ! is_array( $item ) || ! empty( $item['j5_as'] ) || ! isset( $item['data'] ) ) {
		return 0.0;
	}
	return j5ip_pct( $item['data'] );
}

add_filter( 'woocommerce_get_shop_coupon_data', function ( $data, $code ) {
	if ( ! j5ip_is_code( $code ) ) {
		return $data;
	}
	return array(
		'discount_type'  => 'percent',
		'amount'         => 1,
		'individual_use' => false,
		'usage_limit'    => 0,
		'description'    => J5IP_LABEL,
	);
}, 10, 2 );

add_filter( 'woocommerce_coupon_is_valid_for_product', function ( $valid, $product, $coupon, $values ) {
	if ( ! j5ip_is_code( $coupon ) ) {
		return $valid;
	}
	if ( is_array( $values ) && ! empty( $values['j5_as'] ) ) {
		return false;
	}
	return j5ip_pct( $product ) > 0;
}, 20, 4 );

add_filter( 'woocommerce_coupon_get_discount_amount', function ( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
	if ( ! j5ip_is_code( $coupon ) ) {
		return $discount;
	}
	$pct = j5ip_line_pct( $cart_item );
	return $pct > 0 ? (float) $discounting_amount * $pct / 100 : 0;
}, 20, 5 );

function j5ip_cart_eligible( $cart ) {
	foreach ( $cart->get_cart() as $item ) {
		if ( j5ip_line_pct( $item ) > 0 ) {
			return true;
		}
	}
	return false;
}

/** Auto apply / remove, same pattern as factory savings (no notice, no nested totals). */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	static $busy = false;
	if ( $busy || ! $cart instanceof WC_Cart ) {
		return;
	}
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	$busy    = true;
	$applied = $cart->get_applied_coupons();
	$has     = in_array( J5IP_CODE, $applied, true );
	$want    = j5ip_cart_eligible( $cart );
	if ( $want && ! $has ) {
		$applied[] = J5IP_CODE;
		$cart->set_applied_coupons( $applied );
	} elseif ( ! $want && $has ) {
		$cart->set_applied_coupons( array_values( array_diff( $applied, array( J5IP_CODE ) ) ) );
	}
	$busy = false;
}, 6 );

add_filter( 'woocommerce_cart_totals_coupon_label', function ( $label, $coupon ) {
	return j5ip_is_code( $coupon ) ? J5IP_LABEL : $label;
}, 20, 2 );

add_filter( 'woocommerce_cart_totals_coupon_html', function ( $html, $coupon, $discount_amount_html ) {
	return j5ip_is_code( $coupon ) ? $discount_amount_html : $html;
}, 20, 3 );

add_filter( 'woocommerce_get_item_data', function ( $item_data, $item ) {
	if ( j5ip_line_pct( $item ) > 0 && WC()->cart && in_array( J5IP_CODE, WC()->cart->get_applied_coupons(), true ) ) {
		$item_data[] = array(
			'key'   => J5IP_LABEL,
			'value' => 'Applied',
		);
	}
	return $item_data;
}, 31, 2 );
