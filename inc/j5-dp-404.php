<?php
/**
 * J5 DP 404 monitor feed: WC_404_REDIRECTS_CONTRACT_1 (Oct 28, 2026).
 *
 *   GET /wp-json/j5/v1/404s?days=7&limit=1000
 *       Redirection plugin's 404 log (fallback: Rank Math 404 Monitor),
 *       grouped by path, newest first.
 *       {"rows": [{path, hits, last_seen, referer, user_agent}]}
 *
 *   PUT /wp-json/j5/v1/redirects
 *       {"redirects": [{from, to, code}]}  ->  {"ok": true, "created": n, "updated": n}
 *       Upserts 301s into the Redirection plugin, group 1 (where the October
 *       Hesco redirects live). An existing redirect from the same path, in any
 *       group, is updated in place; a second one is never created.
 *
 * Auth: X-J5-Hub-Secret, same as brands / shipping-rules / armor-reference
 * (j5_armor_ref_permission). Wrong secret -> 401.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5_DP404_GROUP = 1;

add_action( 'rest_api_init', function () {
	$perm = function ( $request ) {
		return function_exists( 'j5_armor_ref_permission' )
			? j5_armor_ref_permission( $request )
			: new WP_Error( 'j5_dp404_forbidden', 'Authentication unavailable.', array( 'status' => 401 ) );
	};

	register_rest_route( 'j5/v1', '/404s', array(
		'methods'             => 'GET',
		'permission_callback' => $perm,
		'callback'            => 'j5_dp404_get_log',
		'args'                => array(
			'days'  => array( 'default' => 7 ),
			'limit' => array( 'default' => 1000 ),
		),
	) );

	register_rest_route( 'j5/v1', '/redirects', array(
		'methods'             => 'PUT',
		'permission_callback' => $perm,
		'callback'            => 'j5_dp404_put_redirects',
	) );
} );

/* -------------------------------------------------------------------------
 * 1. 404 log
 * ---------------------------------------------------------------------- */

function j5_dp404_get_log( WP_REST_Request $request ) {
	global $wpdb;
	$days  = max( 1, min( 365, (int) $request->get_param( 'days' ) ) );
	$limit = max( 1, min( 5000, (int) $request->get_param( 'limit' ) ) );
	// Both logs write their dates in site time (current_time('mysql')).
	$cut = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );

	// Source 1: Redirection's 404 log (one row per hit). This is the log that
	// is actually running; Rank Math's monitor stopped writing on 2026-07-20.
	$t = $wpdb->prefix . 'redirection_404';
	if ( j5_dp404_table_exists( $t ) ) {
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$t}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( array( 'id', 'url', 'created' ) as $need ) {
			if ( ! in_array( $need, $cols, true ) ) {
				return new WP_Error( 'j5_dp404_schema', 'Redirection 404 log is missing column ' . $need . '. Columns: ' . implode( ', ', $cols ), array( 'status' => 500 ) );
			}
		}
		$ref = in_array( 'referrer', $cols, true ) ? 'l.referrer' : "''";
		$ua  = in_array( 'agent', $cols, true ) ? 'l.agent' : "''";
		$sql = "SELECT l.url AS path, g.hits, g.last_seen, {$ref} AS referer, {$ua} AS user_agent
			FROM `{$t}` l
			JOIN ( SELECT url, COUNT(*) AS hits, MAX(created) AS last_seen, MAX(id) AS mid
			       FROM `{$t}` WHERE created >= %s GROUP BY url ) g ON l.id = g.mid
			ORDER BY g.last_seen DESC
			LIMIT %d";
		return rest_ensure_response( array( 'rows' => j5_dp404_rows( $wpdb->get_results( $wpdb->prepare( $sql, $cut, $limit ), ARRAY_A ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	// Source 2 (fallback): Rank Math 404 Monitor.
	$t = $wpdb->prefix . 'rank_math_404_logs';
	if ( ! j5_dp404_table_exists( $t ) ) {
		return rest_ensure_response( array( 'rows' => array(), 'note' => 'No 404 log found (Redirection 404 logging and Rank Math 404 Monitor both off).' ) );
	}
	$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$t}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( array( 'id', 'uri', 'accessed', 'times_accessed' ) as $need ) {
		if ( ! in_array( $need, $cols, true ) ) {
			return new WP_Error( 'j5_dp404_schema', 'Rank Math 404 log is missing column ' . $need . '. Columns: ' . implode( ', ', $cols ), array( 'status' => 500 ) );
		}
	}
	$ref = in_array( 'referer', $cols, true ) ? 'l.referer' : "''";
	$ua  = in_array( 'user_agent', $cols, true ) ? 'l.user_agent' : "''";
	$sql = "SELECT l.uri AS path, g.hits, g.last_seen, {$ref} AS referer, {$ua} AS user_agent
		FROM `{$t}` l
		JOIN ( SELECT uri, SUM(times_accessed) AS hits, MAX(accessed) AS last_seen, MAX(id) AS mid
		       FROM `{$t}` WHERE accessed >= %s GROUP BY uri ) g ON l.id = g.mid
		ORDER BY g.last_seen DESC
		LIMIT %d";
	return rest_ensure_response( array( 'rows' => j5_dp404_rows( $wpdb->get_results( $wpdb->prepare( $sql, $cut, $limit ), ARRAY_A ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

function j5_dp404_table_exists( $t ) {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) === $t;
}

function j5_dp404_rows( $rows ) {
	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'path'       => (string) $r['path'],
			'hits'       => (int) $r['hits'],
			'last_seen'  => (string) $r['last_seen'],
			'referer'    => (string) $r['referer'],
			'user_agent' => (string) $r['user_agent'],
		);
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * 2. Redirects
 * ---------------------------------------------------------------------- */

/** Site path from whatever DP sent: no domain, no query/fragment, leading slash. */
function j5_dp404_path( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) return '';
	if ( preg_match( '#^https?://#i', $v ) ) {
		$p = wp_parse_url( $v );
		$v = isset( $p['path'] ) ? $p['path'] : '/';
	}
	$v = preg_replace( '/[?#].*$/', '', $v );
	return '/' . ltrim( $v, '/' );
}

/** Redirection's comparison key: lowercase, no trailing slash. */
function j5_dp404_key( $path ) {
	$k = strtolower( untrailingslashit( $path ) );
	return '' === $k ? '/' : $k;
}

/**
 * Target as stored. WordPress pages and products live at trailing-slash URLs,
 * so a bare /product/x would cost the visitor a second 301; add the slash
 * unless the path looks like a file (/file.pdf) or is the home page.
 */
function j5_dp404_target( $path ) {
	if ( '/' === $path || preg_match( '#\.[a-z0-9]{2,5}$#i', $path ) ) return $path;
	return trailingslashit( $path );
}

function j5_dp404_put_redirects( WP_REST_Request $request ) {
	if ( ! class_exists( 'Red_Item' ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'The Redirection plugin is not active on the store.' ), 503 );
	}
	$body = $request->get_json_params();
	if ( ! is_array( $body ) || empty( $body['redirects'] ) || ! is_array( $body['redirects'] ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'Expected {"redirects": [{"from","to","code"}]}.' ), 400 );
	}

	// Validate the whole batch before writing anything.
	$todo = array();
	foreach ( $body['redirects'] as $i => $r ) {
		$from = is_array( $r ) && isset( $r['from'] ) ? j5_dp404_path( $r['from'] ) : '';
		$to   = is_array( $r ) && isset( $r['to'] ) ? j5_dp404_path( $r['to'] ) : '';
		if ( '' === $from || '' === $to ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => "Redirect {$i}: from and to are both required." ), 400 );
		}
		if ( '/' === $from ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => "Redirect {$i}: refusing to redirect the home page." ), 400 );
		}
		if ( j5_dp404_key( $from ) === j5_dp404_key( $to ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => "Redirect {$i}: to equals from ({$from})." ), 400 );
		}
		$code = isset( $r['code'] ) ? (int) $r['code'] : 301;
		$todo[] = array( 'from' => $from, 'to' => j5_dp404_target( $to ), 'code' => in_array( $code, array( 301, 302, 307, 308 ), true ) ? $code : 301 );
	}

	global $wpdb;
	$created = 0;
	$updated = 0;
	$purge   = array();
	foreach ( $todo as $r ) {
		$key = j5_dp404_key( $r['from'] );
		// Any existing plain (non-regex) redirect from this path, in any group.
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, group_id, position, title FROM {$wpdb->prefix}redirection_items WHERE regex = 0 AND ( match_url = %s OR LOWER(url) IN ( %s, %s ) ) ORDER BY id ASC LIMIT 1",
			$key, $key, trailingslashit( $key )
		) );
		$details = array(
			'url'         => $r['from'],
			'match_type'  => 'url',
			'action_type' => 'url',
			'action_code' => $r['code'],
			'action_data' => array( 'url' => $r['to'] ),
			'regex'       => 0,
			// Same as the Hesco set: ignore query strings, case and trailing slash.
			'match_data'  => array( 'source' => array( 'flag_query' => 'ignore', 'flag_case' => true, 'flag_trailing' => true, 'flag_regex' => false ) ),
			'group_id'    => $existing ? (int) $existing->group_id : J5_DP404_GROUP,
			'title'       => $existing && $existing->title ? $existing->title : 'DP 404 monitor',
		);
		if ( $existing ) {
			$details['position'] = (int) $existing->position;
			$item = Red_Item::get_by_id( (int) $existing->id );
			$res  = $item ? $item->update( $details ) : new WP_Error( 'missing', 'Redirect ' . $existing->id . ' vanished.' );
			if ( is_wp_error( $res ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => $r['from'] . ': ' . $res->get_error_message(), 'created' => $created, 'updated' => $updated ), 500 );
			}
			$updated++;
		} else {
			$res = Red_Item::create( $details );
			if ( is_wp_error( $res ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => $r['from'] . ': ' . $res->get_error_message(), 'created' => $created, 'updated' => $updated ), 500 );
			}
			$created++;
		}
		$purge[] = home_url( untrailingslashit( $r['from'] ) );
		$purge[] = home_url( trailingslashit( $r['from'] ) );
	}

	// Cloudflare may hold the old 404 for this URL.
	if ( $purge && function_exists( 'j5_armor_ref_cloudflare_purge' ) ) {
		j5_armor_ref_cloudflare_purge( array_values( array_unique( $purge ) ) );
	}

	return rest_ensure_response( array( 'ok' => true, 'created' => $created, 'updated' => $updated ) );
}
