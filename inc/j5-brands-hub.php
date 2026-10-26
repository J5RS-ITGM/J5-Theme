<?php
/**
 * J5 Brands hub feed: logos and brand facts pushed from the dealer portal.
 *
 *   PUT /wp-json/j5/v1/brands   header X-J5-Hub-Secret (same as shipping-rules)
 *   body {generated_at, brands: [{name, slug, logo_url, logo_light, site_url, products, carried}]}
 *
 * Stored whole in option j5_brands_hub (replaced on every PUT). Brand pages and
 * the [j5_brands] grid in inc/j5-brands.php read logos from here first and fall
 * back to the WooCommerce brand Thumbnail. Logos are hotlinked from
 * media.j5rescue.com, never sideloaded; the URL changes when the DP swaps the
 * image, so there is nothing to cache-bust on our side.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5_BRANDS_HUB_OPTION = 'j5_brands_hub';

add_action( 'rest_api_init', function () {
	register_rest_route( 'j5/v1', '/brands', array(
		array(
			'methods'             => 'PUT',
			'permission_callback' => 'j5_armor_ref_permission',
			'callback'            => function ( WP_REST_Request $request ) {
				$body = $request->get_json_params();
				if ( ! is_array( $body ) || ! isset( $body['brands'] ) || ! is_array( $body['brands'] ) ) {
					return new WP_Error( 'j5_brands_bad_body', 'Expected {brands: [...]}.', array( 'status' => 400 ) );
				}
				$brands = array();
				foreach ( $body['brands'] as $b ) {
					if ( ! is_array( $b ) || empty( $b['name'] ) ) continue;
					$name = sanitize_text_field( (string) $b['name'] );
					$slug = ! empty( $b['slug'] ) ? sanitize_title( (string) $b['slug'] ) : sanitize_title( $name );
					$brands[ $slug ] = array(
						'name'       => $name,
						'slug'       => $slug,
						'logo_url'   => ! empty( $b['logo_url'] ) ? esc_url_raw( (string) $b['logo_url'] ) : '',
						'logo_light' => ! empty( $b['logo_light'] ),
						'site_url'   => ! empty( $b['site_url'] ) ? esc_url_raw( (string) $b['site_url'] ) : '',
						'products'   => isset( $b['products'] ) ? (int) $b['products'] : 0,
						'carried'    => ! empty( $b['carried'] ),
					);
				}
				update_option( J5_BRANDS_HUB_OPTION, array(
					'generated_at' => isset( $body['generated_at'] ) ? sanitize_text_field( (string) $body['generated_at'] ) : '',
					'received_at'  => gmdate( 'c' ),
					'brands'       => $brands,
				), false );

				// Redis, then Cloudflare for the brands index and every brand archive.
				wp_cache_flush();
				$urls = j5_brands_hub_urls( array_keys( $brands ) );
				if ( function_exists( 'j5_armor_ref_cloudflare_purge' ) && $urls ) j5_armor_ref_cloudflare_purge( $urls );
				do_action( 'j5_brands_hub_pushed', $brands );

				$response = rest_ensure_response( array( 'ok' => true, 'brands' => count( $brands ), 'received_at' => gmdate( 'c' ), 'purged' => $urls ) );
				$response->header( 'Cache-Control', 'no-store, private' );
				return $response;
			},
		),
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => 'j5_armor_ref_permission',
			'callback'            => function () {
				$response = rest_ensure_response( get_option( J5_BRANDS_HUB_OPTION, array( 'brands' => array() ) ) );
				$response->header( 'Cache-Control', 'no-store, private' );
				return $response;
			},
		),
	) );
} );

/** The hub record for a brand term (by slug, then by name), or null. */
function j5_brand_hub( $term ) {
	static $cache = null;
	if ( null === $cache ) {
		$opt   = get_option( J5_BRANDS_HUB_OPTION, array() );
		$cache = isset( $opt['brands'] ) && is_array( $opt['brands'] ) ? $opt['brands'] : array();
	}
	if ( ! $cache ) return null;
	if ( is_numeric( $term ) ) $term = get_term( (int) $term, defined( 'J5_BRAND_TAX' ) ? J5_BRAND_TAX : 'product_brand' );
	if ( ! $term || is_wp_error( $term ) ) return null;
	if ( isset( $cache[ $term->slug ] ) ) return $cache[ $term->slug ];
	$name = strtolower( trim( wp_specialchars_decode( $term->name ) ) );
	foreach ( $cache as $b ) if ( strtolower( $b['name'] ) === $name ) return $b;
	return null;
}

/** Logo for a brand term: hub first, WooCommerce Thumbnail second. Returns ['url','light','site'] or null. */
function j5_brand_hub_logo( $term ) {
	$hub = j5_brand_hub( $term );
	if ( $hub && $hub['logo_url'] ) return array( 'url' => $hub['logo_url'], 'light' => $hub['logo_light'], 'site' => $hub['site_url'] );
	$id  = is_object( $term ) ? $term->term_id : (int) $term;
	$url = function_exists( 'j5_brand_logo_url' ) ? j5_brand_logo_url( $id, 'medium' ) : '';
	return $url ? array( 'url' => $url, 'light' => false, 'site' => $hub ? $hub['site_url'] : '' ) : ( $hub && $hub['site_url'] ? array( 'url' => '', 'light' => false, 'site' => $hub['site_url'] ) : null );
}

/** URLs a push should purge: the brands index page(s) and each brand archive. */
function j5_brands_hub_urls( array $slugs ) {
	$urls = array();
	foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 5, 's' => '[j5_brands', 'fields' => 'ids' ) ) as $pid ) $urls[] = get_permalink( $pid );
	$tax = defined( 'J5_BRAND_TAX' ) ? J5_BRAND_TAX : 'product_brand';
	if ( taxonomy_exists( $tax ) ) {
		foreach ( $slugs as $slug ) {
			$t = get_term_by( 'slug', $slug, $tax );
			if ( $t && ! is_wp_error( $t ) ) { $l = get_term_link( $t ); if ( ! is_wp_error( $l ) ) $urls[] = $l; }
		}
	}
	return array_values( array_unique( array_filter( $urls ) ) );
}
