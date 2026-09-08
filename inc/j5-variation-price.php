<?php
/**
 * J5 Variation Price — display "From $XXX" for variable products
 *
 * Replaces WooCommerce's default price range ($X – $Y) with the cheapest
 * variation price prefixed by "From". Only fires for variable products with
 * multiple price points; simple products and single-price variables are
 * unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'woocommerce_get_price_html', 'j5_variation_price_html', 99, 2 );
function j5_variation_price_html( $price_html, $product ) {
    if ( ! $product instanceof WC_Product ) {
        return $price_html;
    }
    if ( ! $product->is_type( 'variable' ) ) {
        return $price_html;
    }

    // Get the active price range (sale or regular) across variations
    $min_price = $product->get_variation_price( 'min', true );
    $max_price = $product->get_variation_price( 'max', true );

    // If no variations or single price, leave WC default
    if ( ! $min_price ) {
        return $price_html;
    }

    // If all variations are the same price, just show the price (no "From")
    if ( $min_price === $max_price ) {
        return wc_price( $min_price ) . $product->get_price_suffix();
    }

    // Otherwise: "From $XXX"
    return '<span class="j5-from-price"><span class="j5-from-label">From </span>' . wc_price( $min_price ) . '</span>' . $product->get_price_suffix();
}
