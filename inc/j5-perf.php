<?php
/**
 * J5 front-end performance (render-blocking + layout shift).
 *
 * 1. Fonts: the @font-face rules are printed inline in <head> (no separate
 *    j5-fonts.css request), so the browser finds the font files right away
 *    instead of after a CSS round trip. Above-the-fold faces are preloaded.
 * 2. WooCommerce's generic stylesheets (woocommerce.css, -layout, -smallscreen)
 *    are skipped on pages that don't use them: home, blog/posts and content
 *    pages without WooCommerce shortcodes. The theme styles its own product
 *    cards; cart/checkout/account/shop/product pages are untouched.
 * 3. MX Merchant's mxm.css only loads on checkout / payment pages.
 * 4. Non-critical scripts (order attribution, sourcebuster, Searchanise
 *    widgets) are deferred so they don't block the first paint.
 *
 * Revert any piece by removing its block; nothing here changes content.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ── 1. Fonts: inline @font-face + preload ────────────────────────────────── */

add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'j5-fonts' );
}, 100 );

add_action( 'wp_head', function () {
	$dir  = get_stylesheet_directory() . '/assets/fonts/';
	$base = get_stylesheet_directory_uri() . '/assets/fonts/';
	$css  = @file_get_contents( $dir . 'j5-fonts.css' );
	if ( ! $css ) {
		// Fallback: keep the stylesheet if the file can't be read.
		echo '<link rel="stylesheet" href="' . esc_url( $base . 'j5-fonts.css' ) . '">' . "\n";
		return;
	}
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = preg_replace_callback( "#url\\(\\s*['\"]?([a-z0-9\\-_.]+\\.woff2)['\"]?\\s*\\)#i", function ( $m ) use ( $base ) {
		return "url('" . esc_url( $base . $m[1] ) . "')";
	}, $css );
	echo "<style id=\"j5-fonts-inline\">" . trim( preg_replace( '/\s+/', ' ', $css ) ) . "</style>\n";
}, 2 );

// Preloads live in inc/j5-fonts.php (header, hero and trust-strip faces).

/* ── 2. WooCommerce generic CSS only where WooCommerce renders ───────────── */

function j5perf_needs_wc_css() {
	if ( ! function_exists( 'is_woocommerce' ) ) return true;
	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) return true;
	if ( is_singular() ) {
		$content = (string) get_post_field( 'post_content', get_queried_object_id() );
		foreach ( array( 'products', 'product', 'product_page', 'product_category', 'add_to_cart', 'woocommerce_cart', 'woocommerce_checkout', 'woocommerce_my_account', 'woocommerce_order_tracking' ) as $sc ) {
			if ( has_shortcode( $content, $sc ) ) return true;
		}
		if ( false !== strpos( $content, 'wp:woocommerce/' ) ) return true;
	}
	return false;
}

add_filter( 'woocommerce_enqueue_styles', function ( $styles ) {
	return j5perf_needs_wc_css() ? $styles : array();
}, 50 );

/* ── 3. MX Merchant CSS only on checkout / payment pages ─────────────────── */

add_action( 'wp_enqueue_scripts', function () {
	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_wc_endpoint_url( 'add-payment-method' ) || is_wc_endpoint_url( 'order-pay' ) ) ) return;
	$styles = wp_styles();
	foreach ( (array) $styles->queue as $h ) {
		$src = isset( $styles->registered[ $h ] ) ? (string) $styles->registered[ $h ]->src : '';
		if ( false !== strpos( $src, 'mxmerchant' ) || preg_match( '#/mxm\.css$#', $src ) ) wp_dequeue_style( $h );
	}
}, 100 );

/* ── 4. Defer non-critical scripts ───────────────────────────────────────── */

// WordPress's own loading strategy: it only applies defer when it's safe
// (no inline "after" scripts or blocking dependents), otherwise it leaves the tag alone.
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) return;
	$scripts = wp_scripts();
	foreach ( (array) $scripts->queue as $h ) {
		$src = isset( $scripts->registered[ $h ] ) ? (string) $scripts->registered[ $h ]->src : '';
		if ( in_array( $h, array( 'sourcebuster-js', 'wc-order-attribution' ), true ) || false !== strpos( $src, '/se-widgets.js' ) ) {
			wp_script_add_data( $h, 'strategy', 'defer' );
		}
	}
	// Dependencies of a deferred script must be deferrable too.
	if ( isset( $scripts->registered['sourcebuster-js'] ) ) wp_script_add_data( 'sourcebuster-js', 'strategy', 'defer' );
}, 100 );
