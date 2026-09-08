<?php
/**
 * J5 custom typeahead REST endpoint
 * Searches title + SKU only, orders by relevance, returns lean payload.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', 'j5_register_typeahead_endpoint' );
function j5_register_typeahead_endpoint() {
    register_rest_route( 'j5/v1', '/search', array(
        'methods'             => 'GET',
        'callback'            => 'j5_typeahead_search',
        'permission_callback' => '__return_true',
        'args'                => array(
            'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
        ),
    ) );
}

function j5_typeahead_search( $request ) {
    $q = trim( (string) $request->get_param( 'q' ) );
    if ( strlen( $q ) < 2 ) return array();
    if ( ! class_exists( 'WooCommerce' ) ) return array();

    $words = preg_split( '/\s+/', $q );
    $words = array_filter( $words, function( $w ) { return strlen( $w ) >= 2; } );
    if ( empty( $words ) ) return array();

    global $wpdb;
    $title_clauses = array();
    $sku_clauses   = array();
    $params        = array();
    foreach ( $words as $w ) {
        $like            = '%' . $wpdb->esc_like( $w ) . '%';
        $title_clauses[] = 'p.post_title LIKE %s';
        $sku_clauses[]   = 'pm.meta_value LIKE %s';
        $params[]        = $like;
    }
    $title_where = '(' . implode( ' AND ', $title_clauses ) . ')';
    $sku_where   = '(pm.meta_key = \'_sku\' AND ' . implode( ' AND ', $sku_clauses ) . ')';

    $all_params = array_merge( $params, $params, array( $wpdb->esc_like( $q ) . '%' ) );

    $sql = $wpdb->prepare(
        "SELECT DISTINCT p.ID, p.post_title
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
         WHERE p.post_type = 'product'
           AND p.post_status = 'publish'
           AND ( {$title_where} OR {$sku_where} )
         ORDER BY
           CASE WHEN p.post_title LIKE %s THEN 0 ELSE 1 END,
           LENGTH(p.post_title) ASC
         LIMIT 8",
        $all_params
    );

    $rows = $wpdb->get_results( $sql );
    $out  = array();
    foreach ( $rows as $row ) {
        $product = wc_get_product( $row->ID );
        if ( ! $product || ! $product->is_visible() ) continue;

        $img_id  = $product->get_image_id();
        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';

        $out[] = array(
            'id'    => $row->ID,
            'title' => $row->post_title,
            'link'  => get_permalink( $row->ID ),
            'sku'   => $product->get_sku(),
            'price' => wp_strip_all_tags( wc_price( $product->get_price() ) ),
            'img'   => $img_url,
        );
    }
    return $out;
}
