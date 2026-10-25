<?php
/**
 * J5 Armor Reference: the Armor Comparison Tool
 * ----------------------------------------------
 * The dealer portal (Commerce > Armor Reference) is the system of record. It
 * PUTs one JSON feed per category to this endpoint on change and nightly:
 *
 *   PUT /wp-json/j5/v1/armor-reference/<category>
 *   X-J5-Hub-Secret: <J5_HUB_DEFS_SECRET>
 *
 * Body (contract WC_ARMOR_REFERENCE_CONTRACT_1): category, label, generated_at,
 * fields[] (the registry, in display order), models[] (only qualifying models:
 * active spec sheet + every required field). Each body is the complete set for
 * that category and replaces what is stored. Nothing here holds its own list.
 *
 * Storage:   option j5_armor_ref_<category> (autoload off; read once per page)
 * Render:    [j5_armor_compare]                  all categories, tabbed
 *            [j5_armor_compare category="plate"] one category, no tabs
 *            The full table is server-rendered from the feed so it is indexable;
 *            assets/js/j5-armor-compare.js adds filtering, sorting and compare.
 * Schema:    ItemList of Product with additionalProperty, built from the feed.
 * Purge:     after a push, the object cache is flushed and every page that
 *            carries the shortcode is purged in Cloudflare when J5_CF_ZONE_ID
 *            and J5_CF_API_TOKEN are defined in wp-config.php. The purge also
 *            fires `j5_armor_reference_purge` with the URLs for any other
 *            cache layer.
 *
 * Computed on this side only: coverage (sq in) and weight per square inch (oz),
 * when a model has a numeric weight and a parsable size. Values from the feed
 * are shown as given; nothing else is converted.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5_ARMOR_REF_CATEGORIES = array( 'shield', 'plate', 'helmet', 'soft_armor' );
const J5_ARMOR_REF_OPTION     = 'j5_armor_ref_';           // + category
const J5_ARMOR_REF_PAGES      = 'j5_armor_ref_page_ids';   // pages carrying the shortcode

/* -------------------------------------------------------------------------
 * Storage
 * ---------------------------------------------------------------------- */

/** Stored feed for a category, or null when nothing has been pushed. */
function j5_armor_ref_feed( $category ) {
	$category = sanitize_key( $category );
	if ( ! in_array( $category, J5_ARMOR_REF_CATEGORIES, true ) ) return null;
	$feed = get_option( J5_ARMOR_REF_OPTION . $category, null );
	return ( is_array( $feed ) && isset( $feed['models'] ) ) ? $feed : null;
}

/** Human label for a category, from the feed when present. */
function j5_armor_ref_label( $category ) {
	$feed = j5_armor_ref_feed( $category );
	if ( $feed && ! empty( $feed['label'] ) ) return (string) $feed['label'];
	$fallback = array( 'shield' => 'Shields', 'plate' => 'Plates', 'helmet' => 'Helmets', 'soft_armor' => 'Soft armor' );
	return isset( $fallback[ $category ] ) ? $fallback[ $category ] : ucfirst( $category );
}

/**
 * Validate and normalize a pushed body. Returns the clean feed or WP_Error.
 * Strict on shape, lenient on content: an unknown spec key is kept (the DP
 * registry is editable and the template renders by `fields`).
 */
function j5_armor_ref_normalize( $category, $body ) {
	if ( ! is_array( $body ) ) return new WP_Error( 'j5_armor_ref_body', 'Body must be a JSON object.', array( 'status' => 400 ) );
	if ( isset( $body['category'] ) && sanitize_key( $body['category'] ) !== $category ) {
		return new WP_Error( 'j5_armor_ref_category', 'Body category does not match the URL.', array( 'status' => 400 ) );
	}
	if ( ! isset( $body['fields'] ) || ! is_array( $body['fields'] ) ) return new WP_Error( 'j5_armor_ref_fields', '`fields` must be an array.', array( 'status' => 400 ) );
	if ( ! isset( $body['models'] ) || ! is_array( $body['models'] ) ) return new WP_Error( 'j5_armor_ref_models', '`models` must be an array.', array( 'status' => 400 ) );

	$fields = array();
	foreach ( $body['fields'] as $f ) {
		if ( ! is_array( $f ) || empty( $f['key'] ) ) continue;
		$key = sanitize_key( $f['key'] );
		$type = isset( $f['type'] ) ? sanitize_key( $f['type'] ) : 'text';
		if ( ! in_array( $type, array( 'number', 'text', 'bool', 'list' ), true ) ) $type = 'text';
		$options = array();
		if ( isset( $f['options'] ) && is_array( $f['options'] ) ) {
			foreach ( $f['options'] as $o ) $options[] = sanitize_text_field( (string) $o );
		}
		$fields[] = array(
			'key'      => $key,
			'label'    => isset( $f['label'] ) ? sanitize_text_field( (string) $f['label'] ) : $key,
			'type'     => $type,
			'unit'     => isset( $f['unit'] ) && null !== $f['unit'] ? sanitize_text_field( (string) $f['unit'] ) : null,
			'required' => ! empty( $f['required'] ),
			'options'  => $options ? $options : null,
		);
	}

	$models = array();
	$seen   = array();
	foreach ( $body['models'] as $m ) {
		if ( ! is_array( $m ) || empty( $m['slug'] ) ) continue;
		$slug = sanitize_title( (string) $m['slug'] );
		if ( '' === $slug || isset( $seen[ $slug ] ) ) continue;
		$seen[ $slug ] = true;

		$specs = array();
		if ( isset( $m['specs'] ) && is_array( $m['specs'] ) ) {
			foreach ( $m['specs'] as $k => $v ) {
				$k = sanitize_key( $k );
				if ( '' === $k || null === $v || '' === $v ) continue;
				if ( is_bool( $v ) )            $specs[ $k ] = $v;
				elseif ( is_int( $v ) || is_float( $v ) ) $specs[ $k ] = $v + 0;
				elseif ( is_array( $v ) )       $specs[ $k ] = array_map( 'sanitize_text_field', array_map( 'strval', $v ) );
				else                            $specs[ $k ] = sanitize_text_field( (string) $v );
			}
		}

		$sheet = array( 'url' => '', 'pulled_at' => '', 'source_url' => '' );
		if ( isset( $m['spec_sheet'] ) && is_array( $m['spec_sheet'] ) ) {
			$sheet['url']        = isset( $m['spec_sheet']['url'] ) ? esc_url_raw( (string) $m['spec_sheet']['url'] ) : '';
			$sheet['pulled_at']  = isset( $m['spec_sheet']['pulled_at'] ) ? sanitize_text_field( (string) $m['spec_sheet']['pulled_at'] ) : '';
			$sheet['source_url'] = isset( $m['spec_sheet']['source_url'] ) ? esc_url_raw( (string) $m['spec_sheet']['source_url'] ) : '';
		}

		$wc = isset( $m['wc_product_url'] ) && $m['wc_product_url'] ? esc_url_raw( (string) $m['wc_product_url'] ) : '';
		// `carried`: the model is linked to a DP product (we sell it), whether or not it has a store page yet.
		$carried = ! empty( $m['carried'] ) || ! empty( $m['product_id'] ) || '' !== $wc;

		$models[] = array(
			'slug'           => $slug,
			'manufacturer'   => isset( $m['manufacturer'] ) ? sanitize_text_field( (string) $m['manufacturer'] ) : '',
			'model'          => isset( $m['model'] ) ? sanitize_text_field( (string) $m['model'] ) : $slug,
			'family'         => isset( $m['family'] ) ? sanitize_text_field( (string) $m['family'] ) : ( isset( $specs['family'] ) ? (string) $specs['family'] : '' ),
			'specs'          => $specs,
			'spec_sheet'     => $sheet,
			'wc_product_url' => $wc ? $wc : null,
			'carried'        => $carried,
		);
	}

	return array(
		'category'     => $category,
		'label'        => isset( $body['label'] ) ? sanitize_text_field( (string) $body['label'] ) : j5_armor_ref_label( $category ),
		'generated_at' => isset( $body['generated_at'] ) ? sanitize_text_field( (string) $body['generated_at'] ) : '',
		'received_at'  => gmdate( 'c' ),
		'fields'       => $fields,
		'models'       => $models,
	);
}

/* -------------------------------------------------------------------------
 * REST endpoint
 * ---------------------------------------------------------------------- */

/**
 * Permission: the DP secret (same header and constant as the other DP pushes),
 * a WooCommerce API key with write permission, or a logged-in store manager.
 * This endpoint writes, so a read-only key is refused.
 */
function j5_armor_ref_permission( $request ) {
	if ( defined( 'J5_HUB_DEFS_SECRET' ) && '' !== J5_HUB_DEFS_SECRET ) {
		$sent = isset( $_SERVER['HTTP_X_J5_HUB_SECRET'] ) ? (string) $_SERVER['HTTP_X_J5_HUB_SECRET'] : '';
		if ( '' !== $sent && hash_equals( (string) J5_HUB_DEFS_SECRET, $sent ) ) return true;
	}

	if ( function_exists( 'j5_hub_defs_basic_credentials' ) && function_exists( 'wc_api_hash' ) ) {
		list( $ck, $cs ) = j5_hub_defs_basic_credentials( $request );
		if ( '' !== $ck && '' !== $cs ) {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT consumer_secret, permissions FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s",
				wc_api_hash( sanitize_text_field( $ck ) )
			) );
			if ( $row && hash_equals( (string) $row->consumer_secret, $cs ) ) {
				if ( in_array( (string) $row->permissions, array( 'write', 'read_write' ), true ) ) return true;
				return new WP_Error( 'j5_armor_ref_forbidden', 'API key lacks write permission.', array( 'status' => 403 ) );
			}
		}
	}

	if ( current_user_can( 'manage_woocommerce' ) ) return true;

	return new WP_Error( 'j5_armor_ref_forbidden', 'Authentication required.', array( 'status' => 401 ) );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'j5/v1', '/armor-reference/(?P<category>[a-z_]+)', array(
		array(
			'methods'             => 'PUT',
			'permission_callback' => 'j5_armor_ref_permission',
			'callback'            => function ( WP_REST_Request $request ) {
				$category = sanitize_key( $request['category'] );
				if ( ! in_array( $category, J5_ARMOR_REF_CATEGORIES, true ) ) {
					return new WP_Error( 'j5_armor_ref_unknown', 'Unknown category.', array( 'status' => 404 ) );
				}
				$feed = j5_armor_ref_normalize( $category, $request->get_json_params() );
				if ( is_wp_error( $feed ) ) return $feed;

				update_option( J5_ARMOR_REF_OPTION . $category, $feed, false );
				j5_armor_ref_after_push( $category );

				$response = rest_ensure_response( array(
					'ok'          => true,
					'category'    => $category,
					'models'      => count( $feed['models'] ),
					'fields'      => count( $feed['fields'] ),
					'received_at' => $feed['received_at'],
					'purged'      => j5_armor_ref_page_urls(),
				) );
				$response->header( 'Cache-Control', 'no-store, private' );
				return $response;
			},
		),
		array(
			// Read-back for the DP's preview and for debugging from a logged-in session.
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => 'j5_armor_ref_permission',
			'callback'            => function ( WP_REST_Request $request ) {
				$feed = j5_armor_ref_feed( $request['category'] );
				$response = rest_ensure_response( $feed ? $feed : array( 'category' => sanitize_key( $request['category'] ), 'models' => array(), 'fields' => array() ) );
				$response->header( 'Cache-Control', 'no-store, private' );
				return $response;
			},
		),
	) );
} );

/* -------------------------------------------------------------------------
 * Cache
 * ---------------------------------------------------------------------- */

/** Permalinks of every published page or post that carries the shortcode. */
function j5_armor_ref_page_urls() {
	$ids = get_option( J5_ARMOR_REF_PAGES, array() );
	if ( ! is_array( $ids ) || ! $ids ) return array();
	$urls = array();
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id && 'publish' === get_post_status( $id ) ) $urls[] = get_permalink( $id );
	}
	return array_values( array_unique( array_filter( $urls ) ) );
}

/** Remember which pages carry the shortcode, so a push can purge them. */
add_action( 'save_post', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) return;
	$ids = get_option( J5_ARMOR_REF_PAGES, array() );
	if ( ! is_array( $ids ) ) $ids = array();
	$has = has_shortcode( (string) $post->post_content, 'j5_armor_compare' );
	$ids = array_values( array_diff( array_map( 'intval', $ids ), array( (int) $post_id ) ) );
	if ( $has ) $ids[] = (int) $post_id;
	update_option( J5_ARMOR_REF_PAGES, $ids, true );
}, 10, 2 );

function j5_armor_ref_after_push( $category ) {
	wp_cache_flush();
	$urls = j5_armor_ref_page_urls();
	j5_armor_ref_cloudflare_purge( $urls );
	do_action( 'j5_armor_reference_purge', $urls, $category );
}

/** Purge the given URLs at Cloudflare. Silent no-op without the constants. */
function j5_armor_ref_cloudflare_purge( array $urls ) {
	if ( ! $urls || ! defined( 'J5_CF_ZONE_ID' ) || ! defined( 'J5_CF_API_TOKEN' ) ) return false;
	$res = wp_remote_post( 'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( J5_CF_ZONE_ID ) . '/purge_cache', array(
		'timeout' => 8,
		'headers' => array( 'Authorization' => 'Bearer ' . J5_CF_API_TOKEN, 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array( 'files' => array_values( $urls ) ) ),
	) );
	if ( is_wp_error( $res ) ) { error_log( '[j5-armor-reference] Cloudflare purge failed: ' . $res->get_error_message() ); return false; }
	return 200 === (int) wp_remote_retrieve_response_code( $res );
}

/* -------------------------------------------------------------------------
 * Derived values
 * ---------------------------------------------------------------------- */

/** First number in a value, or null. "10 x 12 in" -> 10, "3.2 lb (M)" -> 3.2 */
function j5_armor_ref_num( $v ) {
	if ( is_int( $v ) || is_float( $v ) ) return (float) $v;
	if ( is_string( $v ) && preg_match( '/-?\d+(?:\.\d+)?/', $v, $m ) ) return (float) $m[0];
	return null;
}

/** Width and height in inches from the specs, or null. */
function j5_armor_ref_dims( array $specs ) {
	$w = isset( $specs['width_in'] ) ? j5_armor_ref_num( $specs['width_in'] ) : null;
	$h = isset( $specs['height_in'] ) ? j5_armor_ref_num( $specs['height_in'] ) : null;
	if ( $w && $h ) return array( $w, $h );
	if ( isset( $specs['size'] ) && is_string( $specs['size'] ) && preg_match( '/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)/i', $specs['size'], $m ) ) {
		return array( (float) $m[1], (float) $m[2] );
	}
	return null;
}

/** Coverage (sq in) and weight per square inch (oz), when derivable. */
function j5_armor_ref_derived( array $specs ) {
	$out  = array();
	$dims = j5_armor_ref_dims( $specs );
	$lb   = isset( $specs['weight_lb'] ) ? j5_armor_ref_num( $specs['weight_lb'] ) : null;
	if ( $dims ) {
		$out['area_sqin'] = round( $dims[0] * $dims[1], 1 );
		if ( $lb && $out['area_sqin'] > 0 ) $out['oz_per_sqin'] = round( $lb * 16 / $out['area_sqin'], 3 );
	}
	return $out;
}

/**
 * How many sizes a model is offered in, and the list.
 * Source, in order: a `sizes` spec ("S, M, L, XL" or "20x30 / 24x36"); otherwise
 * sibling models in the same feed that share manufacturer + family (one record
 * per size, which keeps per-size weights honest).
 */
function j5_armor_ref_sizes( array $m, array $all ) {
	if ( ! empty( $m['specs']['sizes'] ) && is_string( $m['specs']['sizes'] ) ) {
		$list = array_values( array_filter( array_map( 'trim', preg_split( '/[,\/;|]+/', $m['specs']['sizes'] ) ) ) );
		if ( $list ) return array( 'count' => count( $list ), 'list' => $list );
	}
	if ( ! empty( $m['family'] ) ) {
		$list = array();
		foreach ( $all as $o ) {
			if ( ! empty( $o['family'] ) && strcasecmp( $o['family'], $m['family'] ) === 0 && strcasecmp( $o['manufacturer'], $m['manufacturer'] ) === 0 ) {
				$dims = j5_armor_ref_dims( $o['specs'] );
				$list[] = $dims ? j5_armor_ref_fmt( $dims[0] ) . 'x' . j5_armor_ref_fmt( $dims[1] ) : ( isset( $o['specs']['size'] ) ? (string) $o['specs']['size'] : $o['model'] );
			}
		}
		$list = array_values( array_unique( $list ) );
		if ( count( $list ) > 1 ) return array( 'count' => count( $list ), 'list' => $list );
	}
	return null;
}

/** Normalized size display: "8.0 x 10.0 in" -> "8 x 10 in"; standard SAPI cuts get their name. */
function j5_armor_ref_size_display( array $specs ) {
	$dims = j5_armor_ref_dims( $specs );
	if ( ! $dims ) return isset( $specs['size'] ) ? (string) $specs['size'] : '';
	$sapi = array( 'SAPI XS' => array( 7.25, 11.5 ), 'SAPI Small' => array( 8.75, 11.75 ), 'SAPI Medium' => array( 9.5, 12.5 ), 'SAPI Large' => array( 10.25, 13.25 ), 'SAPI XL' => array( 11, 14 ) );
	$name = '';
	foreach ( $sapi as $n => $wh ) if ( abs( $wh[0] - $dims[0] ) <= 0.1 && abs( $wh[1] - $dims[1] ) <= 0.1 ) { $name = $n; $dims = $wh; break; }
	$out = j5_armor_ref_fmt( $dims[0] ) . ' x ' . j5_armor_ref_fmt( $dims[1] ) . ' in';
	return $name ? $out . ' <small class="j5ac-sapi">' . esc_html( $name ) . '</small>' : $out;
}

/** Display string for a spec value. */
function j5_armor_ref_fmt( $v, $field = null ) {
	if ( is_bool( $v ) ) return $v ? 'Yes' : 'No';
	if ( is_array( $v ) ) return implode( ', ', $v );
	if ( ( is_int( $v ) || is_float( $v ) ) && $field && ! empty( $field['unit'] ) ) return rtrim( rtrim( number_format( (float) $v, 2, '.', ',' ), '0' ), '.' ) . ' ' . $field['unit'];
	if ( is_int( $v ) || is_float( $v ) ) return rtrim( rtrim( number_format( (float) $v, 2, '.', ',' ), '0' ), '.' );
	return (string) $v;
}

/** Threat tier for a protection level, used to color the level pill. */
function j5_armor_ref_tier( $level ) {
	$l = strtoupper( trim( (string) $level ) );
	if ( in_array( $l, array( 'IIA', 'II', 'IIIA', 'HG1', 'HG2' ), true ) ) return 'hg';
	if ( in_array( $l, array( 'III', 'III+' ), true ) ) return 'rifle';
	if ( in_array( $l, array( 'RF1', 'RF2', 'RF3' ), true ) ) return 'rf';
	if ( 'IV' === $l ) return 'ap';
	return 'other';
}

/** Level value wrapped in its pill. */
function j5_armor_ref_pill( $level ) {
	return '<span class="j5ac-lv j5ac-lv--' . esc_attr( j5_armor_ref_tier( $level ) ) . '">' . esc_html( $level ) . '</span>';
}

/* -------------------------------------------------------------------------
 * Shortcode
 * ---------------------------------------------------------------------- */

add_shortcode( 'j5_armor_compare', 'j5_armor_ref_shortcode' );

function j5_armor_ref_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'category' => '', 'quote' => '' ), $atts, 'j5_armor_compare' );
	$only = sanitize_key( $atts['category'] );
	$cats = $only ? array( $only ) : array( 'shield', 'plate', 'helmet', 'soft_armor' );

	$quote_base = $atts['quote'] ? esc_url_raw( $atts['quote'] ) : ( function_exists( 'j5_quote_base' ) ? j5_quote_base() : home_url( '/request-a-quote/' ) );

	j5_armor_ref_assets();

	$first = '';
	foreach ( $cats as $c ) { if ( j5_armor_ref_feed( $c ) ) { $first = $c; break; } }
	if ( ! $first ) $first = $cats[0];

	ob_start();
	?>
	<div class="j5ac" data-j5ac data-quote="<?php echo esc_url( $quote_base ); ?>">
		<?php if ( count( $cats ) > 1 ) : ?>
		<nav class="j5ac-cats" aria-label="Armor category">
			<?php foreach ( $cats as $c ) :
				$feed = j5_armor_ref_feed( $c );
				$n    = $feed ? count( $feed['models'] ) : 0;
				if ( ! $feed ) : ?>
					<span class="j5ac-cat is-off"><?php echo esc_html( j5_armor_ref_label( $c ) ); ?><small>Coming soon</small></span>
				<?php else : ?>
					<a class="j5ac-cat<?php echo $c === $first ? ' is-on' : ''; ?>" href="#j5ac-<?php echo esc_attr( $c ); ?>" data-cat="<?php echo esc_attr( $c ); ?>" <?php echo $c === $first ? 'aria-current="true"' : ''; ?>><?php echo esc_html( j5_armor_ref_label( $c ) ); ?></a>
				<?php endif;
			endforeach; ?>
		</nav>
		<?php endif; ?>

		<?php foreach ( $cats as $c ) :
			$feed = j5_armor_ref_feed( $c );
			if ( ! $feed ) {
				if ( count( $cats ) === 1 ) echo '<p class="j5ac-empty">Nothing published for ' . esc_html( strtolower( j5_armor_ref_label( $c ) ) ) . ' yet.</p>';
				continue;
			}
			j5_armor_ref_render_category( $feed, $c === $first, $quote_base );
		endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * One category: filter bar + model list (server-rendered) + compare slot.
 * List columns: model, every required field, derived columns, spec sheet.
 * Optional fields appear on the compare cards.
 */
function j5_armor_ref_render_category( array $feed, $open, $quote_base ) {
	$cat    = $feed['category'];
	$fields = $feed['fields'];
	// Accessories (type = Panel) are reference records only; they never appear in the tool or the schema.
	$models = array_values( array_filter( $feed['models'], function ( $m ) { return empty( $m['specs']['type'] ) || strcasecmp( (string) $m['specs']['type'], 'Panel' ) !== 0; } ) );
	$feed['models'] = $models;

	$cols = array();
	foreach ( $fields as $f ) if ( $f['required'] ) $cols[] = $f;

	$any_eff = false; $any_sizes = false;
	foreach ( $models as $m ) { $d = j5_armor_ref_derived( $m['specs'] ); if ( isset( $d['oz_per_sqin'] ) ) $any_eff = true; if ( j5_armor_ref_sizes( $m, $models ) ) $any_sizes = true; }
	if ( 'helmet' === $cat ) $any_eff = false;

	$dates = array_filter( array_map( function ( $m ) { return $m['spec_sheet']['pulled_at']; }, $models ) );
	sort( $dates );
	$pulled = $dates ? ( count( array_unique( $dates ) ) > 1 ? j5_armor_ref_date( reset( $dates ) ) . ' to ' . j5_armor_ref_date( end( $dates ) ) : j5_armor_ref_date( reset( $dates ) ) ) : '';

	$config = array( 'category' => $cat, 'label' => $feed['label'], 'fields' => $fields, 'eff' => $any_eff, 'sizes' => $any_sizes );

	// Sort options: the efficiency metric first where it exists, then every numeric field, then name.
	$sorts = array();
	if ( $any_eff ) $sorts['oz_per_sqin'] = 'Lightest per sq in';
	foreach ( $fields as $f ) if ( 'number' === $f['type'] ) $sorts[ $f['key'] ] = $f['label'];
	if ( $any_eff ) $sorts['area_sqin'] = 'Coverage';
	if ( $any_sizes ) $sorts['size_count'] = 'Sizes';
	$sorts['maker'] = 'Manufacturer';
	$sorts['model'] = 'Model name';
	?>
	<section class="j5ac-panel" id="j5ac-<?php echo esc_attr( $cat ); ?>" data-cat="<?php echo esc_attr( $cat ); ?>" <?php echo $open ? '' : 'hidden'; ?>>
		<script type="application/json" class="j5ac-config"><?php echo wp_json_encode( $config, JSON_UNESCAPED_SLASHES ); ?></script>

		<div class="j5ac-guide" data-j5ac-guide hidden></div>

		<form class="j5ac-filters" data-j5ac-filters aria-label="Filter <?php echo esc_attr( strtolower( $feed['label'] ) ); ?>">
			<?php foreach ( $fields as $f ) :
				if ( 'list' === $f['type'] && $f['options'] ) :
					$used = array();
					foreach ( $models as $m ) if ( isset( $m['specs'][ $f['key'] ] ) ) $used[ (string) $m['specs'][ $f['key'] ] ] = true;
					$opts = array_values( array_filter( $f['options'], function ( $o ) use ( $used ) { return isset( $used[ $o ] ); } ) );
					if ( count( $opts ) < 2 ) continue;
					?>
					<fieldset class="j5ac-fs">
						<legend class="j5ac-lbl"><?php echo esc_html( $f['label'] ); ?></legend>
						<div class="j5ac-chips">
						<?php foreach ( $opts as $o ) : ?>
							<label class="j5ac-chip<?php echo 'level' === $f['key'] ? ' j5ac-chip--' . esc_attr( j5_armor_ref_tier( $o ) ) : ''; ?>"><input type="checkbox" name="<?php echo esc_attr( $f['key'] ); ?>" value="<?php echo esc_attr( $o ); ?>"><span><?php echo esc_html( 'level' === $f['key'] && preg_match( '/^(II|III)A?\+?$/', $o ) ? 'NIJ ' . $o : $o ); ?></span></label>
						<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endif;
			endforeach; ?>

			<?php
			$makers = array();
			foreach ( $models as $m ) if ( $m['manufacturer'] ) $makers[ $m['manufacturer'] ] = true;
			$makers = array_keys( $makers ); natcasesort( $makers );
			?>
			<div class="j5ac-row j5ac-row--find">
				<label class="j5ac-search"><span class="screen-reader-text">Search <?php echo esc_html( strtolower( $feed['label'] ) ); ?></span><input type="search" name="q" placeholder="Search model or manufacturer" autocomplete="off"></label>
				<?php if ( count( $makers ) > 1 ) : ?>
				<label class="j5ac-sortwrap"><span class="j5ac-lbl">Manufacturer</span>
					<select name="maker" class="j5ac-sort"><option value="">All</option><?php foreach ( $makers as $mk ) : ?><option value="<?php echo esc_attr( $mk ); ?>"><?php echo esc_html( $mk ); ?></option><?php endforeach; ?></select>
				</label>
				<?php endif; ?>
				<label class="j5ac-check"><input type="checkbox" name="carried" value="1"> <span>J5 carries only</span></label>
				<?php if ( 'plate' === $cat ) : ?><label class="j5ac-check"><input type="checkbox" name="steel" value="1"> <span>Include steel plates</span></label><?php endif; ?>
			</div>
			<div class="j5ac-row">
			<?php foreach ( $fields as $f ) :
				if ( 'number' === $f['type'] ) :
					$max = 0; $min = null;
					foreach ( $models as $m ) { $n = isset( $m['specs'][ $f['key'] ] ) ? j5_armor_ref_num( $m['specs'][ $f['key'] ] ) : null; if ( null !== $n ) { $max = max( $max, $n ); $min = null === $min ? $n : min( $min, $n ); } }
					if ( $max <= 0 || $min === $max || 'weight_lb' !== $f['key'] ) continue;
					$step = $max > 20 ? 1 : 0.1;
					$max  = ceil( $max / $step ) * $step;
					?>
					<label class="j5ac-range">
						<span class="j5ac-lbl">Max <?php echo esc_html( strtolower( $f['label'] ) ); ?></span>
						<input type="range" name="max:<?php echo esc_attr( $f['key'] ); ?>" min="<?php echo esc_attr( floor( $min / $step ) * $step ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $max ); ?>">
						<output><?php echo esc_html( j5_armor_ref_fmt( $max, $f ) ); ?></output>
					</label>
				<?php elseif ( 'bool' === $f['type'] ) : ?>
					<label class="j5ac-check"><input type="checkbox" name="only:<?php echo esc_attr( $f['key'] ); ?>" value="1"> <span><?php echo esc_html( $f['label'] ); ?> only</span></label>
				<?php endif;
			endforeach; ?>
				<?php if ( 'shield' === $cat ) : ?><label class="j5ac-check"><input type="checkbox" name="has:viewport" value="1"> <span>Viewport only</span></label><?php endif; ?>
				<?php if ( 'plate' === $cat ) : ?><label class="j5ac-check"><input type="checkbox" name="only:standalone" value="1"> <span>Standalone only</span></label><?php endif; ?>
				<label class="j5ac-sortwrap"><span class="j5ac-lbl">Sort</span>
					<select name="sort" class="j5ac-sort"><?php foreach ( $sorts as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
					<button type="button" class="j5ac-dir" data-j5ac-dir aria-label="Reverse sort order" title="Reverse sort order">&#9650;</button>
				</label>
				<span class="j5ac-count" data-j5ac-count><?php echo count( $models ); ?> of <?php echo count( $models ); ?></span>
				<button type="reset" class="j5ac-reset">Clear</button>
			</div>
		</form>

		<div class="j5ac-table-wrap">
			<table class="j5ac-table" data-j5ac-table>
				<thead>
					<tr>
						<th scope="col" class="j5ac-th--pick"><span class="screen-reader-text">Compare</span></th>
						<th scope="col">Model</th>
						<?php foreach ( $cols as $f ) : ?><th scope="col"<?php echo 'number' === $f['type'] ? ' class="is-num"' : ''; ?>><?php echo esc_html( $f['label'] ); ?></th><?php endforeach; ?>
						<?php if ( $any_eff ) : ?><th scope="col" class="is-num">Oz / sq in</th><?php endif; ?>
						<?php if ( $any_sizes ) : ?><th scope="col" class="is-num">Sizes</th><?php endif; ?>
						<th scope="col" class="j5ac-th--sheet">Sheet</th>
					</tr>
				</thead>
				<tbody>
					<?php
					usort( $models, function ( $a, $b ) use ( $any_eff ) {
						if ( $any_eff ) {
							$ea = j5_armor_ref_derived( $a['specs'] ); $eb = j5_armor_ref_derived( $b['specs'] );
							$x = isset( $ea['oz_per_sqin'] ) ? $ea['oz_per_sqin'] : INF; $y = isset( $eb['oz_per_sqin'] ) ? $eb['oz_per_sqin'] : INF;
							if ( $x !== $y ) return $x <=> $y;
						}
						return strcasecmp( $a['manufacturer'] . ' ' . $a['model'], $b['manufacturer'] . ' ' . $b['model'] );
					} );
					foreach ( $models as $m ) :
						$d  = j5_armor_ref_derived( $m['specs'] );
						$sz = j5_armor_ref_sizes( $m, $models );
						if ( $sz ) { $d['size_count'] = $sz['count']; $d['size_list'] = implode( ', ', $sz['list'] ); }
						$row = array( 'slug' => $m['slug'], 'model' => $m['model'], 'manufacturer' => $m['manufacturer'], 'specs' => (object) $m['specs'], 'derived' => (object) $d, 'sheet' => $m['spec_sheet'], 'wc' => $m['wc_product_url'], 'carried' => $m['carried'], 'family' => $m['family'] );
						?>
						<tr data-slug="<?php echo esc_attr( $m['slug'] ); ?>" data-row="<?php echo esc_attr( wp_json_encode( $row, JSON_UNESCAPED_SLASHES ) ); ?>"<?php echo $m['carried'] ? ' class="is-carried"' : ''; ?>>
							<td class="j5ac-td--pick"><input type="checkbox" class="j5ac-pick" value="<?php echo esc_attr( $m['slug'] ); ?>" aria-label="Compare <?php echo esc_attr( $m['manufacturer'] . ' ' . $m['model'] ); ?>"></td>
							<th scope="row" class="j5ac-td--model">
								<span class="j5ac-model"><?php echo esc_html( $m['model'] ); ?></span>
								<span class="j5ac-maker"><?php echo esc_html( $m['manufacturer'] ); ?><?php if ( $m['carried'] ) : ?> <span class="j5ac-stock"><?php echo $m['wc_product_url'] ? 'Sold by J5' : 'Quote from J5'; ?></span><?php endif; ?></span>
							</th>
							<?php foreach ( $cols as $f ) :
								$v = isset( $m['specs'][ $f['key'] ] ) ? $m['specs'][ $f['key'] ] : null; ?>
								<td<?php echo 'number' === $f['type'] ? ' class="is-num"' : ''; ?> data-label="<?php echo esc_attr( $f['label'] ); ?>"><?php echo null === $v ? '<span class="j5ac-na">not listed</span>' : ( 'level' === $f['key'] ? j5_armor_ref_pill( $v ) : ( 'size' === $f['key'] ? j5_armor_ref_size_display( $m['specs'] ) : esc_html( j5_armor_ref_fmt( $v, $f ) ) ) ); ?></td>
							<?php endforeach; ?>
							<?php if ( $any_eff ) : ?><td class="is-num j5ac-td--eff" data-label="Oz / sq in"><?php echo isset( $d['oz_per_sqin'] ) ? esc_html( number_format( $d['oz_per_sqin'], 3 ) ) : '<span class="j5ac-na">not listed</span>'; ?></td><?php endif; ?>
							<?php if ( $any_sizes ) : ?><td class="is-num" data-label="Sizes"><?php echo $sz ? '<span title="' . esc_attr( $d['size_list'] ) . '">' . (int) $sz['count'] . '</span>' : '1'; ?></td><?php endif; ?>
							<td class="j5ac-td--sheet"><?php if ( $m['spec_sheet']['url'] ) : ?><a href="<?php echo esc_url( $m['spec_sheet']['url'] ); ?>" target="_blank" rel="noopener">PDF</a><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="j5ac-none" data-j5ac-none hidden>No <?php echo esc_html( strtolower( $feed['label'] ) ); ?> match. Widen the filters.</p>
		</div>

		<p class="j5ac-hint"><span data-j5ac-hint>Check up to 6 to compare side by side.</span> <button type="button" class="j5ac-clear" data-j5ac-clear hidden>Clear selection</button></p>
		<div class="j5ac-compare" data-j5ac-compare-out hidden tabindex="-1"></div>

		<p class="j5ac-foot">Specs as published by each manufacturer<?php echo $pulled ? ', sheets pulled ' . esc_html( $pulled ) : ''; ?>. Ounces per square inch is calculated from the listed size and weight. NIJ certification claims are taken from manufacturer spec sheets and cross-checked against the <a href="https://cpl.nij.ojp.gov/" target="_blank" rel="noopener">NIJ Compliant Products List</a>; "Not certified" means the model is not on the CPL, whatever the sheet says it was tested to. Confirm current specs with the manufacturer or with us before you buy.</p>
	</section>
	<?php
	j5_armor_ref_jsonld( $feed );
}

function j5_armor_ref_date( $iso ) {
	$t = strtotime( $iso );
	return $t ? date_i18n( 'M Y', $t ) : $iso;
}

/* -------------------------------------------------------------------------
 * Schema
 * ---------------------------------------------------------------------- */

/**
 * ItemList of Product. No offers, ratings or reviews are invented: a Product
 * with none of those gets no rich result, which is correct for a model we
 * may not sell. Linked models point at the store page via `url`.
 */
function j5_armor_ref_jsonld( array $feed ) {
	$by_key = array();
	foreach ( $feed['fields'] as $f ) $by_key[ $f['key'] ] = $f;

	$items = array();
	$pos   = 1;
	foreach ( $feed['models'] as $m ) {
		$props = array();
		foreach ( $m['specs'] as $k => $v ) {
			$f = isset( $by_key[ $k ] ) ? $by_key[ $k ] : null;
			$p = array( '@type' => 'PropertyValue', 'name' => $f ? $f['label'] : $k, 'value' => is_array( $v ) ? implode( ', ', $v ) : ( is_bool( $v ) ? ( $v ? 'Yes' : 'No' ) : $v ) );
			if ( $f && ! empty( $f['unit'] ) && ( is_int( $v ) || is_float( $v ) ) ) $p['unitText'] = $f['unit'];
			$props[] = $p;
		}
		$product = array(
			'@type'              => 'Product',
			'name'               => trim( $m['manufacturer'] . ' ' . $m['model'] ),
			'brand'              => array( '@type' => 'Brand', 'name' => $m['manufacturer'] ),
			'model'              => $m['model'],
			'category'           => $feed['label'],
			'additionalProperty' => $props,
		);
		if ( $m['wc_product_url'] ) $product['url'] = $m['wc_product_url'];
		if ( $m['spec_sheet']['url'] ) $product['subjectOf'] = array( '@type' => 'DigitalDocument', 'name' => $m['manufacturer'] . ' ' . $m['model'] . ' spec sheet', 'url' => $m['spec_sheet']['url'], 'encodingFormat' => 'application/pdf' );
		$items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'item' => $product );
	}
	if ( ! $items ) return;

	$graph = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'name'            => $feed['label'] . ' compared',
		'numberOfItems'   => count( $items ),
		'itemListOrder'   => 'https://schema.org/ItemListUnordered',
		'itemListElement' => $items,
	);
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}

/* -------------------------------------------------------------------------
 * Assets: only on pages that render the shortcode.
 * ---------------------------------------------------------------------- */

function j5_armor_ref_assets() {
	static $done = false;
	if ( $done ) return;
	$done = true;
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/j5-armor-compare.css';
	$js  = $dir . '/assets/js/j5-armor-compare.js';
	wp_enqueue_style( 'j5-armor-compare', $uri . '/assets/css/j5-armor-compare.css', array(), file_exists( $css ) ? (string) filemtime( $css ) : null );
	wp_enqueue_script( 'j5-armor-compare', $uri . '/assets/js/j5-armor-compare.js', array(), file_exists( $js ) ? (string) filemtime( $js ) : null, true );
	if ( function_exists( 'wp_script_add_data' ) ) wp_script_add_data( 'j5-armor-compare', 'strategy', 'defer' );
}

// Shortcodes render inside the_content, after wp_enqueue_scripts: enqueue early when the page carries it.
add_action( 'wp', function () {
	if ( ! is_singular() ) return;
	$post = get_post();
	if ( $post && has_shortcode( (string) $post->post_content, 'j5_armor_compare' ) ) add_action( 'wp_enqueue_scripts', 'j5_armor_ref_assets' );
} );
