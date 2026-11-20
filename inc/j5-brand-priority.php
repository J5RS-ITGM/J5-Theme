<?php
/**
 * Brand priority on product listings (J5-BRAND-FIRST-1, 2026-11-19).
 *
 * HESCO first, HighCom second, every other brand after, on the shop page,
 * product categories / tags, brand archives, product search and [products]
 * shortcode grids. Within each brand the normal order is kept (menu_order,
 * then title; relevance on search).
 *
 * The theme grid (inc/j5-shop-setup.php) builds its query with wc_get_products()
 * and sets orderby=menu_order on "Sort: Featured" without an order, so it fell to
 * WC's default DESC and showed menu_order backwards (HighCom 262-273 above HESCO).
 * On the front end this sets menu_order ASC, then title, for those queries.
 *
 * Only on the default sort: when the customer picks a sort (price, newest,
 * popularity, rating), their choice wins.
 *
 * A product counts as a brand when either brand taxonomy says so
 * (product_brand or pwb-brand; the two don't match exactly).
 * Order is set by slug: filter j5_brand_priority_slugs.
 *
 * Pages rendered by Searchanise itself are not WP queries and are not
 * affected; set brand boosts in the Searchanise dashboard for those.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Brand slugs in priority order. */
function j5bp_slugs() {
	return (array) apply_filters( 'j5_brand_priority_slugs', array( 'hesco-armor', 'highcom-armor' ) );
}

/** rank => term_ids across both brand taxonomies. Cached per request. */
function j5bp_rank_terms() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map = array();
	foreach ( array_values( j5bp_slugs() ) as $rank => $slug ) {
		foreach ( array( 'product_brand', 'pwb-brand' ) as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$t = get_term_by( 'slug', $slug, $tax );
			if ( $t && ! is_wp_error( $t ) ) {
				$map[ $rank ][] = (int) $t->term_id;
			}
		}
	}
	return $map;
}

/** True when the requested sort is the store default (or search relevance). */
function j5bp_is_default_sort( $orderby ) {
	$orderby = strtolower( trim( (string) $orderby ) );
	return in_array( $orderby, array( '', 'menu_order', 'menu_order title', 'relevance', 'title' ), true );
}

/** Main shop / category / tag / brand / product search query. */
add_action( 'woocommerce_product_query', function ( $q ) {
	$orderby = isset( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );
	if ( j5bp_is_default_sort( $orderby ) ) {
		$q->set( 'j5_brand_first', 1 );
	}
}, 20 );

/** [products] / product category shortcodes and grids built on them. */
add_filter( 'woocommerce_shortcode_products_query', function ( $args, $atts ) {
	$orderby = isset( $atts['orderby'] ) ? $atts['orderby'] : '';
	if ( j5bp_is_default_sort( $orderby ) ) {
		$args['j5_brand_first'] = 1;
	}
	return $args;
}, 20, 2 );

/**
 * Theme grid + any front-end wc_get_products() on menu_order:
 * menu_order ASC, then title, with brand rank first.
 * Not in wp-admin (except AJAX) and not on REST requests (DP pushes, feeds).
 */
add_filter( 'woocommerce_product_data_store_cpt_get_products_query', function ( $wp_query_args, $query_vars ) {
	if ( ( is_admin() && ! wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $wp_query_args;
	}
	$orderby = isset( $query_vars['orderby'] ) ? $query_vars['orderby'] : '';
	if ( is_string( $orderby ) && 'menu_order' === strtolower( trim( $orderby ) ) ) {
		$wp_query_args['orderby']        = array( 'menu_order' => 'ASC', 'title' => 'ASC' );
		$wp_query_args['order']          = 'ASC';
		$wp_query_args['j5_brand_first'] = 1;
	}
	return $wp_query_args;
}, 20, 2 );

/** Brand rank first, then whatever order the query already had. */
add_filter( 'posts_clauses', function ( $clauses, $q ) {
	if ( ! $q instanceof WP_Query || ! $q->get( 'j5_brand_first' ) ) {
		return $clauses;
	}
	$map = j5bp_rank_terms();
	if ( ! $map ) {
		return $clauses;
	}
	global $wpdb;
	$cases = '';
	$all   = array();
	foreach ( $map as $rank => $ids ) {
		$ids    = array_map( 'intval', $ids );
		$all    = array_merge( $all, $ids );
		$cases .= ' WHEN tt.term_id IN (' . implode( ',', $ids ) . ') THEN ' . (int) $rank;
	}
	$other = count( j5bp_slugs() );
	$clauses['join'] .= " LEFT JOIN ( SELECT tr.object_id, MIN( CASE{$cases} END ) AS j5_brank"
		. " FROM {$wpdb->term_relationships} tr"
		. " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
		. ' WHERE tt.term_id IN (' . implode( ',', array_unique( $all ) ) . ')'
		. " GROUP BY tr.object_id ) j5bp ON j5bp.object_id = {$wpdb->posts}.ID";
	$rank_sql           = "COALESCE( j5bp.j5_brank, {$other} ) ASC";
	$clauses['orderby'] = $clauses['orderby'] ? $rank_sql . ', ' . $clauses['orderby'] : $rank_sql . ", {$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_title ASC";
	return $clauses;
}, 20, 2 );
