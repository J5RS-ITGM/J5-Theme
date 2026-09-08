<?php
/**
 * J5 Hub — read-only definitions endpoint for vocabulary sync.
 *
 * Exposes the fulfillment status and acknowledgment DEFINITIONS so the hub can
 * mirror them for its product editor. Definitions are wp-admin owned (edited in
 * J5 Apps); per-product assignment stays hub owned. This endpoint is therefore
 * read-only by design and never writes.
 *
 * Two things worth preserving if this is ever edited:
 *
 * 1. It returns the NORMALIZED definitions from j5_fulfillment_types() and
 *    j5_ack_definitions(), not the raw options. Saved option data predates the
 *    'ack' and 'placement' fields, and the accessors backfill them. Reading
 *    get_option() directly hands the hub an incomplete vocabulary.
 *
 * 2. Authentication reuses the WooCommerce API keys the hub already sends, so
 *    there is no second credential to issue or rotate. See
 *    j5_hub_defs_permission().
 *
 * 3. Fulfillment entries carry 'label'; acknowledgment entries carry 'title'.
 *    Both are emitted, plus a normalized 'name' on each so a consumer can read
 *    one key regardless of type.
 *
 * Added 2026-07-28. @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Extract HTTP Basic credentials, tolerating the several places they may
 * arrive.
 *
 * nginx + PHP-FPM frequently drops the Authorization header unless it is
 * explicitly forwarded via fastcgi_param, which is why WooCommerce's own
 * authentication also consults PHP_AUTH_USER / PHP_AUTH_PW. Reading only
 * HTTP_AUTHORIZATION will reject valid requests on a default nginx config.
 *
 * Credentials are deliberately NOT accepted from the query string: a secret in
 * a URL ends up in access logs, Cloudflare analytics, and browser history.
 *
 * @param WP_REST_Request $request
 * @return array{0:string,1:string} consumer key, consumer secret (both '' if absent)
 */
function j5_hub_defs_basic_credentials( $request ) {
	// 1. PHP's parsed Basic auth, when the SAPI provides it.
	if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
		return array(
			(string) $_SERVER['PHP_AUTH_USER'],
			isset( $_SERVER['PHP_AUTH_PW'] ) ? (string) $_SERVER['PHP_AUTH_PW'] : '',
		);
	}

	// 2. Raw header, including the REDIRECT_ prefixed copy left by a rewrite.
	$header = (string) $request->get_header( 'authorization' );
	if ( '' === $header && ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
		$header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
	}
	if ( '' === $header || 0 !== stripos( $header, 'basic ' ) ) {
		return array( '', '' );
	}

	$decoded = base64_decode( substr( $header, 6 ), true );
	if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
		return array( '', '' );
	}

	$parts = explode( ':', $decoded, 2 );

	return array( (string) $parts[0], isset( $parts[1] ) ? (string) $parts[1] : '' );
}

/**
 * Permission gate.
 *
 * Validates the WooCommerce consumer key/secret the hub already sends against
 * WC's own key table — the same credentials that guard products and orders, so
 * no parallel secret has to be issued or rotated.
 *
 * Accepted, in order:
 *   1. A valid WC API key with read permission (the hub's normal path).
 *   2. J5_HUB_DEFS_SECRET via X-J5-Hub-Secret, if that constant is defined —
 *      an escape hatch for the case where Basic auth genuinely cannot reach
 *      PHP. Undefined by default, so it costs nothing.
 *   3. A logged-in user with manage_woocommerce, so the endpoint is testable
 *      from a browser session.
 *
 * Anything else is refused. The endpoint is never anonymously public.
 *
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function j5_hub_defs_permission( $request ) {
	list( $ck, $cs ) = j5_hub_defs_basic_credentials( $request );

	if ( '' !== $ck && '' !== $cs && function_exists( 'wc_api_hash' ) ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT consumer_secret, permissions FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s",
				wc_api_hash( sanitize_text_field( $ck ) )
			)
		);

		// WC stores consumer_secret in plaintext and hashes only the key, so a
		// direct constant-time comparison mirrors WC's own authentication.
		if ( $row && hash_equals( (string) $row->consumer_secret, $cs ) ) {
			// A read-only key is sufficient for a read endpoint; a write-only
			// key is not a reader and is refused rather than silently allowed.
			if ( in_array( (string) $row->permissions, array( 'read', 'read_write' ), true ) ) {
				return true;
			}

			return new WP_Error(
				'j5_hub_defs_forbidden',
				'API key lacks read permission.',
				array( 'status' => 403 )
			);
		}
	}

	if ( defined( 'J5_HUB_DEFS_SECRET' ) && '' !== J5_HUB_DEFS_SECRET ) {
		$sent = isset( $_SERVER['HTTP_X_J5_HUB_SECRET'] ) ? (string) $_SERVER['HTTP_X_J5_HUB_SECRET'] : '';
		if ( '' !== $sent && hash_equals( (string) J5_HUB_DEFS_SECRET, $sent ) ) {
			return true;
		}
	}

	if ( current_user_can( 'manage_woocommerce' ) ) {
		return true;
	}

	return new WP_Error(
		'j5_hub_defs_forbidden',
		'Authentication required.',
		array( 'status' => 401 )
	);
}

/**
 * Build the payload. Normalized definitions only — no product data.
 *
 * @return array
 */
function j5_hub_defs_payload() {
	$fulfillment = function_exists( 'j5_fulfillment_types' ) ? j5_fulfillment_types() : array();
	$acks        = function_exists( 'j5_ack_definitions' ) ? j5_ack_definitions() : array();

	// Emit a common 'name' alongside the type-specific key so a consumer does
	// not have to know that one uses 'label' and the other 'title'.
	foreach ( $fulfillment as $slug => $def ) {
		$fulfillment[ $slug ]['name'] = isset( $def['label'] ) ? (string) $def['label'] : $slug;
	}
	foreach ( $acks as $slug => $def ) {
		$acks[ $slug ]['name'] = isset( $def['title'] ) ? (string) $def['title'] : $slug;
	}

	return array(
		'schema'               => 1,
		'generated'            => current_time( 'mysql' ),
		'fulfillment_types'    => (object) $fulfillment,
		'acknowledgment_types' => (object) $acks,
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route(
		'j5/v1',
		'/definitions',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => 'j5_hub_defs_permission',
			'callback'            => function () {
				$response = rest_ensure_response( j5_hub_defs_payload() );
				// Definitions change rarely but must not be served stale after
				// an edit in J5 Apps, and this must never land in a shared cache.
				$response->header( 'Cache-Control', 'no-store, private' );
				return $response;
			},
		)
	);
} );
