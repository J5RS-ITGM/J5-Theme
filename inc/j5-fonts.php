<?php
/**
 * J5 Self-Hosted Fonts
 *
 * Single source of truth for the site's web fonts. Replaces the scattered
 * per-page Google Fonts <link>/wp_enqueue_style calls that previously lived in
 * j5-home-setup.php, j5-cart-checkout-setup.php, j5-account-setup.php and
 * j5-cart-setup.php.
 *
 * Fonts are served locally from assets/fonts/ (WOFF2) via assets/fonts/j5-fonts.css.
 * This removes render-blocking requests to fonts.googleapis.com / fonts.gstatic.com,
 * removes the third-party IP leak (GDPR), and lets Cloudflare edge-cache the files.
 *
 * USAGE: call j5_enqueue_fonts() from any enqueue callback that needs the brand
 * fonts. It is safe to call multiple times per request — wp_enqueue_style
 * deduplicates by handle, so the stylesheet is only emitted once.
 *
 * Font families provided: Bebas Neue, Barlow, Barlow Condensed, JetBrains Mono,
 * Chakra Petch. Weights match the union previously requested across all pages.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue the single local font stylesheet.
 *
 * Auto-versioned with filemtime() so cache busting is automatic when the CSS
 * changes — consistent with the theme's existing convention in j5-shop-setup.php.
 */
function j5_enqueue_fonts() {
	$rel  = '/assets/fonts/j5-fonts.css';
	$path = get_stylesheet_directory() . $rel;
	$ver  = file_exists( $path ) ? (string) filemtime( $path ) : null;

	wp_enqueue_style(
		'j5-fonts',
		get_stylesheet_directory_uri() . $rel,
		array(),
		$ver
	);
}

/**
 * Load the brand fonts site-wide.
 *
 * A single, low-priority enqueue on every front-end page. The fonts are small
 * (WOFF2, subset to latin) and edge-cached by Cloudflare, so loading them
 * globally is simpler and more reliable than the previous per-template gating,
 * and guarantees no page is left with a missing brand font.
 */
add_action( 'wp_enqueue_scripts', 'j5_enqueue_fonts', 5 );

/**
 * Belt-and-suspenders: preload the two most above-the-fold font files so the
 * browser fetches them in parallel with the stylesheet rather than after it.
 * These are the display + primary body faces used in the header on every page.
 */
add_action( 'wp_head', function () {
	$base = get_stylesheet_directory_uri() . '/assets/fonts/';
	$preload = array(
		'bebas-neue-400.woff2',
		'barlow-400.woff2',
	);
	foreach ( $preload as $file ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( $base . $file )
		);
	}
}, 1 );
