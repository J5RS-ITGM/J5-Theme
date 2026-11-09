<?php
/**
 * 410 Gone for URLs that were removed on purpose and are not coming back (J5-GONE-1, 2026-11-08).
 *
 * A 410 tells Google to drop the URL faster than a 404. Only fires when WordPress would
 * otherwise 404, so a page or category that still exists at one of these paths is never
 * blocked. Redirection's own 301 rules run before this and still win.
 *
 * Add paths with the j5_gone_paths filter (lowercase, leading and trailing slash).
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function j5_gone_paths() {
	return (array) apply_filters(
		'j5_gone_paths',
		array(
			// CBD / hemp products, retired (trashed in WC before Nov 2026).
			'/stay-frosty-cooling-hemp-roll-on/',
			'/thc-free-hemp-infused-gummies/',
			'/pure-cbd-oil/',
			'/restore-hemp-lotion/',
			'/cbd-products/',
		)
	);
}

add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = strtolower( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path = '/' . trim( $path, '/' ) . '/';
	if ( ! in_array( $path, j5_gone_paths(), true ) ) {
		return;
	}
	status_header( 410 );
	nocache_headers();
	header( 'X-Robots-Tag: noindex', true );
}, 1 ); // before redirect_canonical (10), which would otherwise guess a "closest" URL
