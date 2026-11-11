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
			'/k9-cbd-oil/',
			'/full-spectrum-cbd-gummies/',
			// Leftover slug from a trashed page.
			'/__trashed/',
			// Course booking, retired with the courses (Oct 2026).
			'/bookingpress-appointment-product/',
			'/appointment/',
		)
	);
}

/**
 * Path prefixes that are gone along with everything under them (J5-GONE-2, 2026-11-11).
 * /color/ was an old color archive; there is no color filter to send it to.
 * Add prefixes with the j5_gone_prefixes filter (lowercase, leading and trailing slash).
 */
function j5_gone_prefixes() {
	return (array) apply_filters( 'j5_gone_prefixes', array( '/color/' ) );
}

add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = strtolower( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path = '/' . trim( $path, '/' ) . '/';
	$gone = in_array( $path, j5_gone_paths(), true );
	if ( ! $gone ) {
		foreach ( j5_gone_prefixes() as $prefix ) {
			if ( '' !== $prefix && 0 === strpos( $path, $prefix ) ) {
				$gone = true;
				break;
			}
		}
	}
	if ( ! $gone ) {
		return;
	}
	status_header( 410 );
	nocache_headers();
	header( 'X-Robots-Tag: noindex', true );
}, 1 ); // before redirect_canonical (10), which would otherwise guess a "closest" URL
