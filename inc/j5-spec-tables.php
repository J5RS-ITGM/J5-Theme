<?php
/**
 * J5 spec tables: [j5_spec_tables]
 * --------------------------------
 * Renders the verified spec tables the dealer portal pushes to each PARENT
 * product as post meta `_j5_spec_tables` (a JSON-encoded string, replaced
 * whole on every publish; "" = no tables).
 *
 *   {
 *     "version": 1,
 *     "verified_at": "2026-11-03T15:20:00Z",
 *     "tables": [{ "id", "title", "source": {"label","url"|null},
 *                  "columns": [..], "rows": [[..],..], "note": "" }],
 *     "properties": [{ "name", "value", "unit"? }]
 *   }
 *
 * All cells are strings; "–" (or "-" / "—" / "") means no value.
 *
 * Where it shows:
 *   - Specifications tab, automatically, above the [j5_techspecs] block
 *     (woocommerce/single-product.php), unless the description already
 *     carries the shortcode (then it shows there, not twice).
 *   - [j5_spec_tables] anywhere in the product description.
 *   - [j5_spec_tables id="123"] for another product.
 *
 * Server-rendered HTML only (no JS), so it is crawlable. Phones scroll wide
 * tables sideways inside .j5-spec-table-scroll with a CSS-only hint
 * (assets/css/j5-shop.css, J5-SPEC-TABLES section).
 *
 * Schema: `properties` become additionalProperty PropertyValue entries on
 * Rank Math's Product (or our ProductGroup) node. Merged via
 * rank_math/snippet/rich_snippet_product_entity, and again in
 * inc/j5-product-schema.php's rank_math/json_ld rewrite as a backstop; the
 * merge is by name, so nothing is ever listed twice.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5_SPEC_TABLES_META = '_j5_spec_tables';

/**
 * Parent product ID for a product / variation / ID.
 */
function j5_spec_tables_pid( $product_or_id ) {
	$pid = 0;
	if ( is_numeric( $product_or_id ) ) {
		$pid = (int) $product_or_id;
	} elseif ( is_object( $product_or_id ) && method_exists( $product_or_id, 'get_id' ) ) {
		$pid = (int) $product_or_id->get_id();
	}
	if ( $pid > 0 && 'product_variation' === get_post_type( $pid ) ) {
		$pid = (int) wp_get_post_parent_id( $pid );
	}
	return $pid;
}

/**
 * Decoded, cleaned payload, or null when there is nothing to show.
 *
 * @return array{tables: array, properties: array, verified_at: string}|null
 */
function j5_spec_tables_data( $product_or_id ) {
	static $cache = array();
	$pid = j5_spec_tables_pid( $product_or_id );
	if ( $pid <= 0 ) return null;
	if ( array_key_exists( $pid, $cache ) ) return $cache[ $pid ];

	$raw  = get_post_meta( $pid, J5_SPEC_TABLES_META, true );
	$data = is_string( $raw ) && '' !== trim( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
	if ( ! is_array( $data ) ) {
		return $cache[ $pid ] = null;
	}

	$tables = array();
	foreach ( (array) ( $data['tables'] ?? array() ) as $t ) {
		if ( ! is_array( $t ) ) continue;
		$cols = array_values( array_map( 'strval', array_filter( (array) ( $t['columns'] ?? array() ), 'is_scalar' ) ) );
		$rows = array();
		foreach ( (array) ( $t['rows'] ?? array() ) as $r ) {
			if ( ! is_array( $r ) ) continue;
			$r = array_values( array_map( function ( $c ) { return is_scalar( $c ) ? (string) $c : ''; }, $r ) );
			if ( $cols ) {
				$r = array_slice( array_pad( $r, count( $cols ), '' ), 0, count( $cols ) );
			}
			$rows[] = $r;
		}
		if ( ! $rows ) continue;
		$src = is_array( $t['source'] ?? null ) ? $t['source'] : array();
		$url = isset( $src['url'] ) && is_string( $src['url'] ) && preg_match( '#^https?://#i', $src['url'] ) ? $src['url'] : '';
		$tables[] = array(
			'id'      => sanitize_html_class( (string) ( $t['id'] ?? '' ) ),
			'title'   => (string) ( $t['title'] ?? '' ),
			'columns' => $cols,
			'rows'    => $rows,
			'note'    => trim( (string) ( $t['note'] ?? '' ) ),
			'source'  => array( 'label' => trim( (string) ( $src['label'] ?? '' ) ), 'url' => $url ),
		);
	}

	$props = array();
	foreach ( (array) ( $data['properties'] ?? array() ) as $p ) {
		if ( ! is_array( $p ) ) continue;
		$name  = trim( (string) ( $p['name'] ?? '' ) );
		$value = trim( (string) ( $p['value'] ?? '' ) );
		if ( '' === $name || '' === $value || j5_spec_tables_is_blank( $value ) ) continue;
		$pv = array( '@type' => 'PropertyValue', 'name' => $name, 'value' => $value );
		$unit = trim( (string) ( $p['unit'] ?? '' ) );
		if ( '' !== $unit ) $pv['unitText'] = $unit;
		$props[ strtolower( $name ) ] = $pv; // last one wins on a repeated name
	}

	if ( ! $tables && ! $props ) {
		return $cache[ $pid ] = null;
	}
	return $cache[ $pid ] = array(
		'tables'      => $tables,
		'properties'  => array_values( $props ),
		'verified_at' => (string) ( $data['verified_at'] ?? '' ),
	);
}

/** "–", "—", "-" or empty: no value. */
function j5_spec_tables_is_blank( $v ) {
	$v = trim( (string) $v );
	return '' === $v || in_array( $v, array( '–', '—', '-' ), true );
}

/**
 * HTML for every table, or '' when there are none.
 */
function j5_spec_tables_render( $product_or_id ) {
	$data = j5_spec_tables_data( $product_or_id );
	if ( ! $data || ! $data['tables'] ) return '';

	$out = '<div class="j5-spec-tables">';
	foreach ( $data['tables'] as $i => $t ) {
		$ncols = $t['columns'] ? count( $t['columns'] ) : max( array_map( 'count', $t['rows'] ) );
		$id    = $t['id'] ? 'j5-spec-' . $t['id'] : 'j5-spec-t' . ( $i + 1 );

		$out .= '<figure class="j5-spec-table-wrap" id="' . esc_attr( $id ) . '">';
		$out .= '<div class="j5-spec-table-scroll' . ( $ncols >= 3 ? ' is-wide' : '' ) . '" role="region" tabindex="0" aria-label="' . esc_attr( $t['title'] ? $t['title'] : 'Specifications' ) . '">';
		$out .= '<table class="j5-spec-table">';
		if ( '' !== $t['title'] ) {
			$out .= '<caption>' . esc_html( $t['title'] ) . '</caption>';
		}
		if ( $t['columns'] ) {
			$out .= '<thead><tr>';
			foreach ( $t['columns'] as $c ) {
				$out .= '<th scope="col">' . esc_html( $c ) . '</th>';
			}
			$out .= '</tr></thead>';
		}
		$out .= '<tbody>';
		foreach ( $t['rows'] as $r ) {
			$out .= '<tr>';
			foreach ( $r as $ci => $cell ) {
				$val = j5_spec_tables_is_blank( $cell )
					? '<span class="j5-spec-na" aria-label="No value">–</span>'
					: esc_html( $cell );
				$out .= 0 === $ci ? '<th scope="row">' . $val . '</th>' : '<td>' . $val . '</td>';
			}
			$out .= '</tr>';
		}
		$out .= '</tbody></table></div>';

		if ( $ncols >= 3 ) {
			$out .= '<p class="j5-spec-table-hint" aria-hidden="true">Swipe the table to see every column &rarr;</p>';
		}
		if ( '' !== $t['note'] || ( '' !== $t['source']['url'] ) ) {
			$out .= '<figcaption class="j5-spec-table-foot">';
			if ( '' !== $t['note'] ) {
				$out .= '<p class="j5-spec-table-note">' . nl2br( esc_html( $t['note'] ) ) . '</p>';
			}
			if ( '' !== $t['source']['url'] ) {
				$label = '' !== $t['source']['label'] ? $t['source']['label'] : 'Manufacturer data';
				$out  .= '<p class="j5-spec-table-source">Source: <a href="' . esc_url( $t['source']['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a></p>';
			}
			$out .= '</figcaption>';
		}
		$out .= '</figure>';
	}
	return $out . '</div>';
}

add_shortcode( 'j5_spec_tables', function ( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'j5_spec_tables' );
	$pid  = (int) $atts['id'];
	if ( $pid <= 0 ) {
		global $product;
		$pid = $product instanceof WC_Product ? $product->get_id() : (int) get_queried_object_id();
	}
	return j5_spec_tables_render( $pid );
} );

/**
 * Specifications tab: tables, unless the description already places them.
 */
function j5_spec_tables_for_specs_tab( $product ) {
	$pid = j5_spec_tables_pid( $product );
	if ( $pid <= 0 ) return '';
	$desc = (string) get_post_field( 'post_content', $pid );
	if ( false !== strpos( $desc, '[j5_spec_tables' ) ) return '';
	return j5_spec_tables_render( $pid );
}

/**
 * Description rendering helper: the legacy (non-structured) description path
 * runs wpautop + kses without shortcodes, so expand only ours there.
 */
function j5_spec_tables_expand_in( $html ) {
	if ( false === strpos( $html, '[j5_spec_tables' ) ) return $html;
	$html = shortcode_unautop( $html );
	return preg_replace_callback( '/\[j5_spec_tables(\s[^\]]*)?\]/', function ( $m ) {
		return do_shortcode( $m[0] );
	}, $html );
}

/* ============================================================================
 *  Schema: additionalProperty
 * ========================================================================== */

/**
 * Merge the DP properties into a Product / ProductGroup node, by name.
 */
function j5_spec_tables_merge_props( $node, $product_or_id ) {
	if ( ! is_array( $node ) ) return $node;
	$data = j5_spec_tables_data( $product_or_id );
	if ( ! $data || ! $data['properties'] ) return $node;

	$have = array();
	$list = array();
	if ( ! empty( $node['additionalProperty'] ) && is_array( $node['additionalProperty'] ) ) {
		$existing = isset( $node['additionalProperty']['@type'] ) ? array( $node['additionalProperty'] ) : $node['additionalProperty'];
		foreach ( $existing as $p ) {
			if ( is_array( $p ) && isset( $p['name'] ) ) {
				$have[ strtolower( (string) $p['name'] ) ] = count( $list );
			}
			$list[] = $p;
		}
	}
	foreach ( $data['properties'] as $p ) {
		$k = strtolower( $p['name'] );
		if ( isset( $have[ $k ] ) ) {
			$list[ $have[ $k ] ] = $p; // DP value wins
		} else {
			$have[ $k ] = count( $list );
			$list[]     = $p;
		}
	}
	$node['additionalProperty'] = array_values( $list );
	return $node;
}

add_filter( 'rank_math/snippet/rich_snippet_product_entity', function ( $entity ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) return $entity;
	try {
		return j5_spec_tables_merge_props( $entity, get_queried_object_id() );
	} catch ( \Throwable $e ) {
		return $entity;
	}
} );
