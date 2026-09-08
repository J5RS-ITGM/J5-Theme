<?php
/**
 * J5 Cart & Checkout Setup
 *
 * Handles template routing, asset enqueueing, and Elementor CSS
 * dequeueing for the custom cart and checkout templates.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Helper: are we on a page where the J5 cart/checkout template
 * should be active? Returns 'cart', 'checkout', or false.
 */
function j5_cart_checkout_active_context() {
	if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
		return false;
	}

	$preview = isset( $_GET['j5_preview'] );

	// Cart
	$is_cart_page = is_cart() || ( function_exists( 'is_page' ) && is_page( 9566 ) );
	if ( $is_cart_page ) {
		$live = defined( 'J5_CART_LIVE' ) && J5_CART_LIVE;
		if ( $preview || $live ) {
			return 'cart';
		}
	}

	// Checkout
	$is_checkout_page = is_checkout() || ( function_exists( 'is_page' ) && is_page( 8 ) );
	if ( $is_checkout_page ) {
		$live = defined( 'J5_CHECKOUT_LIVE' ) && J5_CHECKOUT_LIVE;
		if ( $preview || $live ) {
			return 'checkout';
		}
	}

	return false;
}

/**
 * Route cart and checkout pages to custom templates.
 * Hooked at priority 9999 to win against Elementor Pro Theme Builder.
 */
function j5_cart_checkout_template( $template ) {
	$ctx = j5_cart_checkout_active_context();

	if ( 'cart' === $ctx ) {
		$candidate = get_stylesheet_directory() . '/single-j5-cart.php';
		if ( file_exists( $candidate ) ) {
			return $candidate;
		}
	}

	if ( 'checkout' === $ctx ) {
		$candidate = get_stylesheet_directory() . '/single-j5-checkout.php';
		if ( file_exists( $candidate ) ) {
			return $candidate;
		}
	}

	return $template;
}
add_filter( 'template_include', 'j5_cart_checkout_template', 9999 );

/**
 * Enqueue cart/checkout stylesheet + shop chrome on relevant pages.
 *
 * The cart/checkout templates reuse the J5 site chrome (util bar,
 * header, nav, mini footer) from the shop template. Those styles
 * live in /assets/css/j5-shop.css along with the Bebas/Barlow
 * font stack. We enqueue both here so our cart pages get the full
 * J5 look without duplicating any CSS.
 */
function j5_cart_checkout_enqueue_assets() {
	if ( ! j5_cart_checkout_active_context() ) {
		return;
	}

	$version = defined( 'CHILD_THEME_ASTRA_CHILD_VERSION' )
		? CHILD_THEME_ASTRA_CHILD_VERSION
		: '1.0.0';

	// Brand fonts are self-hosted and enqueued site-wide via inc/j5-fonts.php
	// (j5_enqueue_fonts, priority 5). No Google Fonts request here.

	// Shop chrome — header, util bar, nav, mini footer styles
	wp_enqueue_style(
		'j5-shop',
		get_stylesheet_directory_uri() . '/assets/css/j5-shop.css',
		array(),
		'1.0.0'
	);

	// Shop chrome part 2 — header/nav component styles live in j5-home.css
	// (see j5-home-setup.php). Duplicated enqueue here so cart/checkout pages
	// get the header bar, main nav, and footer styling that the home template
	// loads conditionally. TODO: extract to a shared j5-chrome.css.
	wp_enqueue_style(
		'j5-home',
		get_stylesheet_directory_uri() . '/assets/css/j5-home.css',
		array(),
		'1.0.0'
	);

	// Our cart/checkout-specific overrides + components
	wp_enqueue_style(
		'j5-cart-checkout',
		get_stylesheet_directory_uri() . '/css/j5-cart-checkout.css',
		array( 'j5-shop', 'j5-home', 'astra-child-theme-css' ),
		$version,
		'all'
	);
}
add_action( 'wp_enqueue_scripts', 'j5_cart_checkout_enqueue_assets', 20 );

/**
 * Helper: thumbnail fallback label from product name.
 */
function j5_cart_product_thumb_fallback( $product ) {
	$name  = $product ? $product->get_name() : '';
	$words = preg_split( '/\s+/', wp_strip_all_tags( $name ) );
	$words = array_slice( array_filter( $words ), 0, 2 );
	return strtoupper( implode( ' ', $words ) );
}

/**
 * Helper: total savings across all cart lines (regular - current).
 */
function j5_cart_total_savings() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}

	$savings = 0;
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( empty( $cart_item['data'] ) ) { continue; }

		$product  = $cart_item['data'];
		$quantity = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
		$regular  = (float) $product->get_regular_price();
		$current  = (float) $product->get_price();

		if ( $regular > $current && $current > 0 ) {
			$savings += ( $regular - $current ) * $quantity;
		}
	}

	return $savings;
}
