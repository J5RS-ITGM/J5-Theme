<?php
/**
 * Legacy URLs: 301s for old category / brand / page paths, 410 Gone for junk.
 * --------------------------------------------------------------------------
 * From the DP 404 monitor export of 2026-11-16.
 *
 * Only acts on a request that is ALREADY a 404 (runs on template_redirect after
 * WordPress decided is_404()). A live page, product or category can never be
 * redirected or 410'd by this file, whatever its rules say.
 *
 * 301 targets are looked up when the request comes in: a category or brand by
 * its term ID (so a slug change in the DP doesn't break the redirect), a post by
 * its ID, or a slug that must exist as a published product, page, post or product
 * category. A target that can't be found is skipped and the 404 stands.
 *
 * Paths are matched lowercase, without the trailing slash and without any
 * /page/N on the end. A rule ending in "*" matches that path and everything below it.
 *
 * Not handled here, on purpose: scanner probes (.env, credentials.json, /graphql ...)
 * stay plain 404s; /cdn-cgi/l/email-protection is Cloudflare's; /credit is undecided.
 *
 * robots.txt: adds Disallow lines for the old filter archives (robots_txt filter;
 * has no effect if a physical robots.txt file sits in the web root).
 *
 * @package j5-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** 301s. Target: array( 'cat', term_id ) | array( 'brand', term_id ) | array( 'post', post_id ) | array( 'slug', 'slug' ) | array( 'url', '/path/' ) */
function j5lu_redirects() {
	return array(
		'/product-category/flashlights'                                => array( 'cat', 201 ),  // Handheld Flashlights
		'/handheld-lights*'                                            => array( 'cat', 201 ),
		'/product-category/bags/assault-packs'                         => array( 'cat', 118 ),  // Backpacks & Assault Packs
		'/product-category/body-armor/tactical-outer-carriers'         => array( 'cat', 172 ),  // Plate Carriers
		'/product-category/shields/handgun-rated-shield'               => array( 'cat', 1106 ), // Level IIIA Shields
		'/handgun-rated-shield'                                        => array( 'cat', 1106 ),
		'/product-category/less-lethal-launchers*'                     => array( 'cat', 541 ),  // Launchers
		'/product-category/stocked-ifaks'                              => array( 'cat', 1071 ), // IFAKs & Trauma Kits
		'/product-category/loaded-jump-bags'                           => array( 'cat', 343 ),  // Medical & EMS Bags
		'/product-category/over-the-counter'                           => array( 'cat', 54 ),   // First Aid & OTC
		'/product-category/m-a-r-c-h*'                                 => array( 'cat', 948 ),  // Medical & EMS
		'/m-a-r-c-h*'                                                  => array( 'cat', 948 ),
		'/product-category/hand-tools'                                 => array( 'cat', 954 ),  // Knives & Tools
		'/hydration-pouches'                                           => array( 'cat', 181 ),  // Pouches
		'/pouches/hydration-pouches'                                   => array( 'cat', 181 ),
		'/product-category/automated-external-defibrillator-aed*'      => array( 'cat', 104 ),  // AEDs & Accessories
		'/product-category/plate-carrier-accessories'                  => array( 'cat', 1068 ), // Carrier Accessories
		'/level/hemostatic-agent'                                      => array( 'cat', 127 ),  // Hemostatic Agents
		'/eotech-g43-3x-magnifier-nm-blk'                              => array( 'cat', 1067 ), // Rifle Scopes & Magnifiers
		'/brand/z-medica'                                              => array( 'brand', 1082 ), // QuikClot
		'/product-category/pouches/handcuff-pouches'                   => array( 'slug', 'handcuff-cases' ),
		'/quickship-instock-armor'                                     => array( 'slug', 'quick-ship-in-stock-armor' ),
		'/tactical-and-rescue-product/body-armor-carriers/united-shield-international-the-modern-officers-armor-carrier' => array( 'post', 85760 ),
		'/product-sitemap3.xml'                                        => array( 'url', '/sitemap_index.xml' ),
		'/wp-sitemap.xml'                                              => array( 'url', '/sitemap_index.xml' ),
		'/sitemap-index.xml'                                           => array( 'url', '/sitemap_index.xml' ),
	);
}

/** 410 Gone: old filter archives, an old plugin's pages, and things no longer sold. */
function j5lu_gone() {
	return array(
		'/color*', '/size*', '/level*', '/product-table*', '/coupon*',
		'/__trashed', '/yith-deposit', '/yith-deposit-2',
		'/brand/hsgi*', '/brand/hh-medical-corporation*', '/brand/elzetta*', '/brand/innovative-trauma-care*',
		'/brand/tactical-medical-solutions/null',
		'/elzetta-shield-light-and-horizontal-handle',
		'/red-z-spill-control-solidifier-buckets', '/red-z-spill-control-solidifier-multi-use-bottle', '/spill-kits-solidifiers',
		'/first-aid/cpr-first-aid-courses-certification',
	);
}

/** Request path, lowercase, no trailing slash, no /page/N. */
function j5lu_path() {
	$p = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
	$p = strtolower( rawurldecode( $p ) );
	$p = preg_replace( '#/page/\d+/?$#', '', $p );
	return '/' . trim( $p, '/' );
}

function j5lu_match( $path, $rule ) {
	if ( '*' === substr( $rule, -1 ) ) {
		$base = substr( $rule, 0, -1 );
		return $path === $base || 0 === strpos( $path, rtrim( $base, '/' ) . '/' );
	}
	return $path === $rule;
}

/** Resolve a target to a live URL, or '' when it isn't there. */
function j5lu_resolve( $t ) {
	list( $kind, $v ) = $t;
	if ( 'cat' === $kind || 'brand' === $kind ) {
		$tax  = 'cat' === $kind ? 'product_cat' : ( defined( 'J5_BRAND_TAX' ) ? J5_BRAND_TAX : 'product_brand' );
		$term = get_term( (int) $v, $tax );
		if ( ! $term || is_wp_error( $term ) ) return '';
		$url = get_term_link( $term );
		return is_wp_error( $url ) ? '' : $url;
	}
	if ( 'post' === $kind ) {
		return 'publish' === get_post_status( (int) $v ) ? (string) get_permalink( (int) $v ) : '';
	}
	if ( 'slug' === $kind ) {
		$p = get_page_by_path( $v, OBJECT, array( 'product', 'page', 'post' ) );
		if ( $p && 'publish' === $p->post_status ) return (string) get_permalink( $p );
		$term = get_term_by( 'slug', $v, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) { $url = get_term_link( $term ); return is_wp_error( $url ) ? '' : $url; }
		return '';
	}
	if ( 'url' === $kind ) return home_url( $v );
	return '';
}

add_action( 'template_redirect', function () {
	if ( ! is_404() ) return; // never touch a page that exists
	$path = j5lu_path();

	foreach ( j5lu_redirects() as $rule => $target ) {
		if ( ! j5lu_match( $path, $rule ) ) continue;
		$url = j5lu_resolve( $target );
		if ( '' !== $url ) {
			wp_safe_redirect( $url, 301, 'J5 legacy URLs' );
			exit;
		}
		return; // target not found: leave the 404
	}

	foreach ( j5lu_gone() as $rule ) {
		if ( j5lu_match( $path, $rule ) ) {
			status_header( 410 );
			header( 'X-Robots-Tag: noindex', true );
			return; // the normal 404 page renders, with a 410 status
		}
	}
}, 1 );

/** Keep crawlers out of the old filter archives (virtual robots.txt only). */
add_filter( 'robots_txt', function ( $out, $public ) {
	if ( ! $public ) return $out;
	$add = "\n# J5: retired filter archives (inc/j5-legacy-urls.php)\nUser-agent: *\nDisallow: /color/\nDisallow: /size/\nDisallow: /level/\nDisallow: /product-table/\n";
	return false === strpos( $out, 'Disallow: /color/' ) ? rtrim( $out ) . "\n" . $add : $out;
}, 99, 2 );
