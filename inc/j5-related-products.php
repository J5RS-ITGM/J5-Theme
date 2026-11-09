<?php
/**
 * Related gear on the product page (J5-RELATED-1, 2026-11-08).
 *
 * WooCommerce's wc_get_related_products() picks at random from ANY category or tag the
 * product shares, so a rifle plate could show a ballistic shield. This picks tier by tier:
 *   1. the product's most specific category (e.g. Body Armor > Rifle Plates > Rifle Plates),
 *   2. only if that runs short, its parent category (and the parent's other subcategories),
 *   3. and so on up to the top-level category, never outside it.
 * Within a tier: in-stock first, then best sellers. Tags are ignored.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * @param WC_Product $product
 * @param int        $limit
 * @return int[] product IDs, most relevant first
 */
function j5_related_product_ids( $product, $limit = 4 ) {
	if ( ! $product instanceof WC_Product ) {
		return array();
	}
	$pid   = $product->get_id();
	$limit = max( 1, (int) $limit );
	$ck    = 'rel_' . $pid . '_' . $limit;
	$hit   = wp_cache_get( $ck, 'j5_related' );
	if ( is_array( $hit ) ) {
		return $hit;
	}

	$terms = get_the_terms( $pid, 'product_cat' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}
	// Most specific category = the one with the most ancestors.
	$best  = null;
	$depth = -1;
	foreach ( $terms as $t ) {
		if ( 'uncategorized' === $t->slug ) {
			continue;
		}
		$d = count( get_ancestors( $t->term_id, 'product_cat', 'taxonomy' ) );
		if ( $d > $depth ) {
			$best  = $t;
			$depth = $d;
		}
	}
	if ( ! $best ) {
		return array();
	}

	$chain = array_merge( array( (int) $best->term_id ), array_map( 'intval', get_ancestors( $best->term_id, 'product_cat', 'taxonomy' ) ) );
	$ids   = array();
	foreach ( $chain as $tid ) {
		$term = get_term( $tid, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}
		$found = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'category'   => array( $term->slug ), // includes the term's subcategories
				'exclude'    => array_merge( array( $pid ), $ids ),
				'limit'      => $limit * 4,
				'orderby'    => 'popularity',
				'order'      => 'DESC',
				'return'     => 'objects',
			)
		);
		// In stock first; keep best-seller order inside each group.
		$in  = array();
		$out = array();
		foreach ( $found as $p ) {
			if ( 'outofstock' === $p->get_stock_status() ) {
				$out[] = $p->get_id();
			} else {
				$in[] = $p->get_id();
			}
		}
		$ids = array_merge( $ids, $in, $out );
		if ( count( $ids ) >= $limit ) {
			break;
		}
	}

	$ids = array_slice( array_values( array_unique( $ids ) ), 0, $limit );
	wp_cache_set( $ck, $ids, 'j5_related', HOUR_IN_SECONDS );
	return $ids;
}
