<?php
/**
 * J5 Factory Order Savings (WC Factory Order Savings Contract 1, 2026-10-20).
 *
 * DP pushes:
 *   _j5_factory_discount  on the variation (or simple product): percent off,
 *                         "8" / "7.5"; "" when none or not a factory order.
 *   _j5_factory_savings   on the variable parent: "yes" when any option has one.
 *
 * Plates carry MAP, so the product page, schema and feeds keep the regular
 * price. The page only says "Factory order savings, see price in cart"; the
 * number appears in the cart as an auto-applied virtual coupon
 * (factory-order-savings) that lands in the order's coupon_lines and
 * discount_total. It stacks with any other coupon.
 *
 * A line is eligible only while its variation / simple product has a percent
 * AND is still on backorder, checked live at every cart calculation, so a DP
 * flip to in stock drops the savings on the next recalculation.
 *
 * WooCommerce only: DP estimates and Quartermaster orders are not touched.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5FS_CODE  = 'factory-order-savings';
const J5FS_LABEL = 'Factory order savings';
const J5FS_LINE  = 'Factory order savings, see price in cart';

/* =========================================================================
 * 1. ELIGIBILITY
 * ====================================================================== */

/**
 * Live percent off for a variation or simple product. 0 when not eligible.
 *
 * @param WC_Product|null $product
 * @return float
 */
function j5fs_pct( $product ) {
	if ( ! $product instanceof WC_Product || $product->is_type( 'variable' ) ) {
		return 0.0;
	}
	if ( 'onbackorder' !== $product->get_stock_status() ) {
		return 0.0;
	}
	$raw = trim( (string) $product->get_meta( '_j5_factory_discount', true ) );
	if ( '' === $raw || ! is_numeric( $raw ) ) {
		return 0.0;
	}
	$pct = (float) $raw;
	return ( $pct > 0 && $pct < 100 ) ? $pct : 0.0;
}

/** Variable parent flag: at least one option carries savings. */
function j5fs_parent_flag( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}
	if ( $product->is_type( 'variable' ) ) {
		return 'yes' === strtolower( trim( (string) $product->get_meta( '_j5_factory_savings', true ) ) );
	}
	return j5fs_pct( $product ) > 0;
}

/* =========================================================================
 * 2. PRODUCT PAGE (no number, ever)
 * ====================================================================== */

/** Variations JSON: a yes/no only. The percent never reaches the page. */
add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {
	$data['j5_factory_savings'] = j5fs_pct( $variation ) > 0;
	return $data;
}, 22, 3 );

/**
 * The savings line, printed inside the stock banner (both banner variants).
 * Shown up front when the parent flag / simple product qualifies; on variable
 * products the inline script then follows the selected variation.
 *
 * @param WC_Product $product
 */
function j5fs_render_line( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$flag = j5fs_parent_flag( $product );
	if ( ! $flag && ! $product->is_type( 'variable' ) ) {
		return;
	}
	if ( $product->is_type( 'variable' ) && ! $flag ) {
		return; // DP says no option has savings: nothing to toggle.
	}
	printf(
		'<span class="j5-fs-line" data-j5-fs data-parent="%s">%s</span>',
		$flag ? '1' : '0',
		esc_html( J5FS_LINE )
	);
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product || ! $product->is_type( 'variable' ) || ! j5fs_parent_flag( $product ) ) {
		return;
	}
	$js = <<<'JS'
( function () {
	if ( ! window.jQuery ) { return; }
	jQuery( function ( $ ) {
		var $line = $( '[data-j5-fs]' );
		if ( ! $line.length ) { return; }
		var parent = $line.attr( 'data-parent' ) === '1';
		$( '.j5-variations-form' )
			.on( 'found_variation', function ( e, v ) {
				$line.prop( 'hidden', ! ( v && v.j5_factory_savings ) );
			} )
			.on( 'hide_variation reset_data', function () {
				$line.prop( 'hidden', ! parent );
			} );
	} );
} )();
JS;
	wp_add_inline_script( 'j5-shop', $js );
}, 40 );

/* =========================================================================
 * 3. CART: virtual auto-applied coupon
 * ====================================================================== */

/** True for our code (WC lowercases codes). */
function j5fs_is_code( $coupon_or_code ) {
	$code = $coupon_or_code instanceof WC_Coupon ? $coupon_or_code->get_code() : (string) $coupon_or_code;
	return wc_format_coupon_code( $code ) === J5FS_CODE;
}

/** Virtual coupon: no post needed. Nominal percent; the real one is per line below. */
add_filter( 'woocommerce_get_shop_coupon_data', function ( $data, $code ) {
	if ( ! j5fs_is_code( $code ) ) {
		return $data;
	}
	return array(
		'discount_type'  => 'percent',
		'amount'         => 1,
		'individual_use' => false,
		'usage_limit'    => 0,
		'description'    => J5FS_LABEL,
	);
}, 10, 2 );

/** Only factory order lines that still qualify. */
add_filter( 'woocommerce_coupon_is_valid_for_product', function ( $valid, $product, $coupon, $values ) {
	if ( ! j5fs_is_code( $coupon ) ) {
		return $valid;
	}
	return j5fs_pct( $product ) > 0;
}, 20, 4 );

/** Each line at its own live percent (8% and 7.5% lines in one coupon). */
add_filter( 'woocommerce_coupon_get_discount_amount', function ( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
	if ( ! j5fs_is_code( $coupon ) ) {
		return $discount;
	}
	$product = is_array( $cart_item ) && isset( $cart_item['data'] ) ? $cart_item['data'] : null;
	$pct     = j5fs_pct( $product );
	return $pct > 0 ? (float) $discounting_amount * $pct / 100 : 0;
}, 20, 5 );

/** True when the cart holds at least one eligible line. */
function j5fs_cart_eligible( $cart ) {
	foreach ( $cart->get_cart() as $item ) {
		if ( isset( $item['data'] ) && j5fs_pct( $item['data'] ) > 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * Auto apply / remove. Writes the applied list directly (no apply_coupon():
 * no success notice, no nested calculate_totals). Runs before totals are built.
 */
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
	$has     = in_array( J5FS_CODE, $applied, true );
	$want    = j5fs_cart_eligible( $cart );
	if ( $want && ! $has ) {
		$applied[] = J5FS_CODE;
		$cart->set_applied_coupons( $applied );
	} elseif ( ! $want && $has ) {
		$cart->set_applied_coupons( array_values( array_diff( $applied, array( J5FS_CODE ) ) ) );
	}
	$busy = false;
}, 5 );

/** Label "Factory order savings" instead of "Coupon: factory-order-savings". */
add_filter( 'woocommerce_cart_totals_coupon_label', function ( $label, $coupon ) {
	return j5fs_is_code( $coupon ) ? J5FS_LABEL : $label;
}, 20, 2 );

/** No [Remove] link: customers never type or remove it. */
add_filter( 'woocommerce_cart_totals_coupon_html', function ( $html, $coupon, $discount_amount_html ) {
	return j5fs_is_code( $coupon ) ? $discount_amount_html : $html;
}, 20, 3 );

/** Note under eligible cart / checkout lines. */
add_filter( 'woocommerce_get_item_data', function ( $item_data, $item ) {
	if ( isset( $item['data'] ) && j5fs_pct( $item['data'] ) > 0 && WC()->cart && in_array( J5FS_CODE, WC()->cart->get_applied_coupons(), true ) ) {
		$item_data[] = array(
			'key'   => J5FS_LABEL,
			'value' => 'Applied',
		);
	}
	return $item_data;
}, 30, 2 );
