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
 * price. The product page shows a callout under the stock banner, "save up to
 * X%" (J5FS-CALLOUT-1, 2026-11-04: Eric chose to advertise the percent); the
 * dollar amount appears in the cart as an auto-applied virtual coupon
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
 * 2. PRODUCT PAGE: savings callout (J5FS-CALLOUT-1)
 *    Percent shown, price never changed on the page (still regular price).
 * ====================================================================== */

/** "8" / "7.5" (no trailing zeros). */
function j5fs_fmt( $pct ) {
	return rtrim( rtrim( number_format( (float) $pct, 2, '.', '' ), '0' ), '.' );
}

/** Highest live percent across a variable product's options (or the simple product's own). */
function j5fs_max_pct( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return 0.0;
	}
	if ( ! $product->is_type( 'variable' ) ) {
		return j5fs_pct( $product );
	}
	if ( ! j5fs_parent_flag( $product ) ) {
		return 0.0;
	}
	$max = 0.0;
	foreach ( $product->get_children() as $vid ) {
		$max = max( $max, j5fs_pct( wc_get_product( $vid ) ) );
	}
	return $max;
}

/** Variations JSON: flag + percent, so the callout follows the selected option. */
add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {
	$pct = j5fs_pct( $variation );
	$data['j5_factory_savings'] = $pct > 0;
	$data['j5_factory_pct']     = $pct > 0 ? j5fs_fmt( $pct ) : '';
	return $data;
}, 22, 3 );

/**
 * The callout, printed right after the stock banner (woocommerce/single-product.php).
 * Variable products start at "Up to <max>%"; the inline script switches to the
 * selected option's own percent, or hides the callout for options without one.
 *
 * @param WC_Product $product
 */
function j5fs_render_line( $product ) {
	$max = j5fs_max_pct( $product );
	if ( $max <= 0 ) {
		return;
	}
	$m = esc_html( j5fs_fmt( $max ) );
	printf(
		'<div class="j5-fs-callout" data-j5-fs data-max="%1$s">'
			. '<div class="j5-fs-callout__badge" aria-hidden="true"><span data-j5-fs-upto>Up to</span><b><span data-j5-fs-pct>%1$s</span>%%</b><span>off</span></div>'
			. '<div class="j5-fs-callout__body">'
				. '<p class="j5-fs-callout__title">%2$s</p>'
				. '<p class="j5-fs-callout__text">Order this as a <strong>factory order</strong> and save <strong><span data-j5-fs-upto>up to </span><span data-j5-fs-pct>%1$s</span>%%</strong>. It is built for you by the manufacturer, so it takes a little longer to ship.</p>'
				. '<p class="j5-fs-callout__how">Your discount is applied automatically in the cart. No code needed.</p>'
			. '</div>'
		. '</div>',
		$m,
		esc_html( J5FS_LABEL )
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
		var $box = $( '[data-j5-fs]' );
		if ( ! $box.length ) { return; }
		var max = String( $box.attr( 'data-max' ) || '' );
		function show( pct, upto ) {
			$box.find( '[data-j5-fs-pct]' ).text( pct );
			$box.find( '[data-j5-fs-upto]' ).prop( 'hidden', ! upto );
			$box.prop( 'hidden', false );
		}
		$( '.j5-variations-form' )
			.on( 'found_variation', function ( e, v ) {
				if ( v && v.j5_factory_savings && v.j5_factory_pct ) { show( v.j5_factory_pct, false ); }
				else { $box.prop( 'hidden', true ); }
			} )
			.on( 'hide_variation reset_data', function () { show( max, true ); } );
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
	if ( is_array( $values ) && ! empty( $values['j5_as'] ) ) {
		return false; // an in-stock ArmorSync plate is not a factory order (inc/j5-armorsync-units.php)
	}
	return j5fs_pct( $product ) > 0;
}, 20, 4 );

/** Each line at its own live percent (8% and 7.5% lines in one coupon). */
add_filter( 'woocommerce_coupon_get_discount_amount', function ( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
	if ( ! j5fs_is_code( $coupon ) ) {
		return $discount;
	}
	if ( is_array( $cart_item ) && ! empty( $cart_item['j5_as'] ) ) {
		return 0;
	}
	$product = is_array( $cart_item ) && isset( $cart_item['data'] ) ? $cart_item['data'] : null;
	$pct     = j5fs_pct( $product );
	return $pct > 0 ? (float) $discounting_amount * $pct / 100 : 0;
}, 20, 5 );

/** True when the cart holds at least one eligible line. */
function j5fs_cart_eligible( $cart ) {
	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['j5_as'] ) && isset( $item['data'] ) && j5fs_pct( $item['data'] ) > 0 ) {
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
	if ( empty( $item['j5_as'] ) && isset( $item['data'] ) && j5fs_pct( $item['data'] ) > 0 && WC()->cart && in_array( J5FS_CODE, WC()->cart->get_applied_coupons(), true ) ) {
		$item_data[] = array(
			'key'   => J5FS_LABEL,
			'value' => 'Applied',
		);
	}
	return $item_data;
}, 30, 2 );
