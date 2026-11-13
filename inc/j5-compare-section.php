<?php
/**
 * J5 armor comparison section: [j5_compare]
 * -----------------------------------------
 * Renders the "A vs B" comparison the dealer portal pushes to each PARENT
 * product as post meta `_j5_armor_compare` (JSON string, sent with the other
 * _j5_* parent meta on every DP push).
 *
 *   ""        remove the section            absent   product never had one
 *   bad JSON / fewer than 2 columns / no rows        render nothing
 *
 *   {
 *     "heading": "A vs B",                 section H2 (worded for search)
 *     "summary": "Plain sentences or ''",  above the table
 *     "columns": [{"name","sku","this"}],  2 to 4; columns[0] is this product
 *     "rows":    [{"group","label","values":[..],"differs":bool}],
 *     "same_threats": 3                    count for the footer line
 *   }
 *
 * Every string is printed exactly as sent. Nothing is reworded, computed or
 * converted: "compliant (not certified)" stays that, "Not listed" stays that.
 *
 * Where it shows:
 *   - End of the Ballistics tab (inc/j5-threat-matrix.php), or the end of the
 *     Specifications tab when the product has no Ballistics tab
 *     (woocommerce/single-product.php), unless the description already
 *     carries the shortcode (then it shows there, not twice).
 *   - [j5_compare] anywhere in the product description.
 *   - [j5_compare id="123"] for another product.
 *
 * Other columns link to their product page by SKU (a variation SKU links to
 * its parent). A SKU that is not on the store prints the name with no link.
 *
 * Rows that are the same on every plate ("differs": false) are in the HTML
 * (crawlable) and sit behind a "Show all specs" toggle that assets/js/j5-shop.js
 * adds; without JS every row shows. Phones get stacked cards, no side scroll.
 * Styles: assets/css/j5-shop.css, J5-COMPARE-SECTION-1.
 *
 * @package j5-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5_COMPARE_META = '_j5_armor_compare';

/** Parent product ID for a product / variation / ID. */
function j5cmp_pid( $product_or_id ) {
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

/** A scalar as a string; anything else is ''. Never trimmed or reworded. */
function j5cmp_s( $v ) {
	return is_scalar( $v ) && ! is_bool( $v ) ? (string) $v : '';
}

/**
 * Decoded, shape-checked payload, or null when there is nothing to show.
 *
 * @return array{heading:string,summary:string,columns:array,rows:array,same_threats:int}|null
 */
function j5cmp_data( $product_or_id ) {
	static $cache = array();
	$pid = j5cmp_pid( $product_or_id );
	if ( $pid <= 0 ) return null;
	if ( array_key_exists( $pid, $cache ) ) return $cache[ $pid ];

	$raw  = get_post_meta( $pid, J5_COMPARE_META, true );
	$data = is_string( $raw ) && '' !== trim( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
	if ( ! is_array( $data ) ) return $cache[ $pid ] = null;

	$cols = array();
	foreach ( (array) ( $data['columns'] ?? array() ) as $c ) {
		if ( ! is_array( $c ) ) continue;
		$name = j5cmp_s( $c['name'] ?? '' );
		if ( '' === trim( $name ) ) continue;
		$cols[] = array( 'name' => $name, 'sku' => trim( j5cmp_s( $c['sku'] ?? '' ) ), 'this' => ! empty( $c['this'] ) );
		if ( count( $cols ) >= 4 ) break;
	}
	$n = count( $cols );
	if ( $n < 2 ) return $cache[ $pid ] = null;

	$rows = array();
	foreach ( (array) ( $data['rows'] ?? array() ) as $r ) {
		if ( ! is_array( $r ) ) continue;
		$label = j5cmp_s( $r['label'] ?? '' );
		if ( '' === trim( $label ) ) continue;
		$vals = array_map( 'j5cmp_s', array_values( (array) ( $r['values'] ?? array() ) ) );
		$vals = array_slice( array_pad( $vals, $n, '' ), 0, $n );
		$rows[] = array(
			'group'   => j5cmp_s( $r['group'] ?? '' ),
			'label'   => $label,
			'values'  => $vals,
			// A row with no flag is treated as differing, so it is never hidden by mistake.
			'differs' => array_key_exists( 'differs', $r ) ? ! empty( $r['differs'] ) : true,
		);
	}
	if ( ! $rows ) return $cache[ $pid ] = null;

	return $cache[ $pid ] = array(
		'heading'      => j5cmp_s( $data['heading'] ?? '' ),
		'summary'      => j5cmp_s( $data['summary'] ?? '' ),
		'columns'      => $cols,
		'rows'         => $rows,
		'same_threats' => isset( $data['same_threats'] ) && is_numeric( $data['same_threats'] ) ? max( 0, (int) $data['same_threats'] ) : 0,
	);
}

/** Product page URL for a SKU ('' when the SKU is not a published product on the store). */
function j5cmp_sku_url( $sku ) {
	if ( '' === $sku || ! function_exists( 'wc_get_product_id_by_sku' ) ) return '';
	$id = (int) wc_get_product_id_by_sku( $sku );
	if ( $id <= 0 ) return '';
	if ( 'product_variation' === get_post_type( $id ) ) {
		$id = (int) wp_get_post_parent_id( $id );
	}
	if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) return '';
	$url = get_permalink( $id );
	return $url ? $url : '';
}

/** Section HTML, or '' when the meta is missing, empty or unusable. */
function j5cmp_render( $product_or_id ) {
	$d = j5cmp_data( $product_or_id );
	if ( ! $d ) return '';
	$pid  = j5cmp_pid( $product_or_id );
	$cols = $d['columns'];
	$n    = count( $cols );

	$urls = array();
	foreach ( $cols as $i => $c ) {
		$urls[ $i ] = ( 0 === $i || $c['this'] ) ? '' : j5cmp_sku_url( $c['sku'] );
	}

	// Which groups have at least one differing row (the rest hide with the "same" rows).
	$group_differs = array();
	$same_rows     = 0;
	foreach ( $d['rows'] as $r ) {
		if ( $r['differs'] ) $group_differs[ $r['group'] ] = true;
		else $same_rows++;
	}

	$uid = 'j5cmp-' . $pid;
	$out = '<section class="j5cmp" data-j5cmp data-cols="' . (int) $n . '" data-same="' . (int) $same_rows . '">';
	if ( '' !== trim( $d['heading'] ) ) {
		$out .= '<h2 class="j5cmp__h" id="' . esc_attr( $uid ) . '-h">' . esc_html( $d['heading'] ) . '</h2>';
	}
	if ( '' !== trim( $d['summary'] ) ) {
		$out .= '<p class="j5cmp__sum">' . esc_html( $d['summary'] ) . '</p>';
	}

	$out .= '<div class="j5cmp__wrap"><table class="j5cmp__t j5-xh"' . ( '' !== trim( $d['heading'] ) ? ' aria-labelledby="' . esc_attr( $uid ) . '-h"' : '' ) . '>';
	$out .= '<thead><tr><th scope="col" class="j5cmp__corner"><span class="screen-reader-text">Specification</span></th>';
	foreach ( $cols as $i => $c ) {
		$name = esc_html( $c['name'] );
		$out .= '<th scope="col" class="j5cmp__col' . ( 0 === $i ? ' is-this' : '' ) . '">';
		if ( 0 === $i ) {
			$out .= '<span class="j5cmp__tag">This product</span><span class="j5cmp__name">' . $name . '</span>';
		} elseif ( '' !== $urls[ $i ] ) {
			$out .= '<a class="j5cmp__name" href="' . esc_url( $urls[ $i ] ) . '">' . $name . '</a>';
		} else {
			$out .= '<span class="j5cmp__name">' . $name . '</span>';
		}
		$out .= '</th>';
	}
	$out .= '</tr></thead><tbody>';

	$group = null;
	foreach ( $d['rows'] as $r ) {
		if ( $r['group'] !== $group ) {
			$group = $r['group'];
			if ( '' !== trim( $group ) ) {
				$out .= '<tr class="j5cmp__grp' . ( empty( $group_differs[ $group ] ) ? ' is-same' : '' ) . '"><th scope="rowgroup" colspan="' . (int) ( $n + 1 ) . '">' . esc_html( $group ) . '</th></tr>';
			}
		}
		$out .= '<tr class="j5cmp__row ' . ( $r['differs'] ? 'is-diff' : 'is-same' ) . '">';
		$out .= '<th scope="row" class="j5cmp__label">' . esc_html( $r['label'] ) . '</th>';
		foreach ( $r['values'] as $i => $v ) {
			$cls = 'j5cmp__v' . ( 0 === $i ? ' is-this' : '' ) . ( '' === trim( $v ) ? ' is-empty' : '' );
			$out .= '<td class="' . $cls . '" data-label="' . esc_attr( $cols[ $i ]['name'] ) . '">' . esc_html( $v ) . '</td>';
		}
		$out .= '</tr>';
	}
	$out .= '</tbody></table></div>';

	if ( $d['same_threats'] > 0 ) {
		$t    = $d['same_threats'];
		$out .= '<p class="j5cmp__foot">' . esc_html( 1 === $t ? '1 other round is rated the same on every plate.' : $t . ' other rounds are rated the same on every plate.' ) . '</p>';
	}

	// Phones stack the table and drop its header row, so the links repeat here.
	$links = '';
	foreach ( $cols as $i => $c ) {
		if ( '' !== $urls[ $i ] ) {
			$links .= '<li><a href="' . esc_url( $urls[ $i ] ) . '">' . esc_html( $c['name'] ) . ' <span aria-hidden="true">&rarr;</span></a></li>';
		}
	}
	if ( '' !== $links ) {
		$out .= '<ul class="j5cmp__links" aria-label="Compared products">' . $links . '</ul>';
	}

	return $out . '</section>';
}

add_shortcode( 'j5_compare', function ( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'j5_compare' );
	$pid  = (int) $atts['id'];
	if ( $pid <= 0 ) {
		global $product;
		$pid = $product instanceof WC_Product ? $product->get_id() : (int) get_queried_object_id();
	}
	return $pid > 0 ? j5cmp_render( $pid ) : '';
} );

/** Tab placement: the section, unless the description already places it. */
function j5cmp_for_tab( $product ) {
	$pid = j5cmp_pid( $product );
	if ( $pid <= 0 ) return '';
	$desc = (string) get_post_field( 'post_content', $pid );
	if ( false !== strpos( $desc, '[j5_compare' ) ) return '';
	return j5cmp_render( $pid );
}

/**
 * Description rendering helper: the legacy (non-structured) description path
 * runs wpautop + kses without shortcodes, so expand only ours there.
 */
function j5cmp_expand_in( $html ) {
	if ( false === strpos( $html, '[j5_compare' ) ) return $html;
	$html = shortcode_unautop( $html );
	return preg_replace_callback( '/\[j5_compare(\s[^\]]*)?\]/', function ( $m ) {
		return do_shortcode( $m[0] );
	}, $html );
}
