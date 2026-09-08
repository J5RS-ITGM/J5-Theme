<?php
/**
 * ============================================================================
 *  J5 Rescue Supply — Landing Pages Bootstrap  (v1.0.1 — font fix)
 * ============================================================================
 *
 *  CHANGE FROM v1.0.0:
 *    - Google Fonts are now printed directly into <head> via wp_head action
 *      instead of wp_enqueue_style, to bypass LiteSpeed's Google Fonts
 *      optimizer which was stripping the font stylesheet from the output.
 * ============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ===========================================================================
//  Template override: swap in our landing template for configured categories
// ===========================================================================

function j5_landing_current_slug() {
	if ( ! function_exists( 'is_product_category' ) ) { return ''; }
	if ( ! is_product_category() ) { return ''; }

	$queried = get_queried_object();
	if ( empty( $queried->slug ) ) { return ''; }

	return j5_has_full_landing( $queried->slug ) ? $queried->slug : '';
}

add_filter( 'woocommerce_locate_template', function ( $template, $template_name, $template_path ) {
	if ( 'archive-product.php' !== $template_name ) { return $template; }
	if ( ! j5_landing_current_slug() ) { return $template; }

	$override = get_stylesheet_directory() . '/landing-templates/category-landing.php';
	if ( file_exists( $override ) ) {
		return $override;
	}
	return $template;
}, 10, 3 );

// ===========================================================================
//  Body class
// ===========================================================================

add_filter( 'body_class', function ( $classes ) {
	$slug = j5_landing_current_slug();
	if ( $slug ) {
		$classes[] = 'j5-landing';
		$classes[] = 'j5-landing--' . sanitize_html_class( $slug );
		$classes[] = 'j5-shop-template';
	}
	return $classes;
} );

// ===========================================================================
//  Enqueue CSS — only on landing pages
// ===========================================================================

add_action( 'wp_enqueue_scripts', function () {
	if ( ! j5_landing_current_slug() ) { return; }

	$version = '1.0.1';
	wp_enqueue_style(
		'j5-landing',
		get_stylesheet_directory_uri() . '/landing-assets/j5-landing.css',
		array(),
		$version
	);
}, 20 );

// ===========================================================================
//  Google Fonts — direct <link> in <head>
//  Bypasses LiteSpeed's Google Fonts optimizer which was dropping the
//  stylesheet when enqueued via wp_enqueue_style.
// ===========================================================================

add_action( 'wp_head', function () {
	if ( ! j5_landing_current_slug() ) { return; }
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">' . "\n";
}, 5 );

// ===========================================================================
//  SVG icon sprite — injected inline at top of landing body
// ===========================================================================

add_action( 'wp_footer', function () {
	if ( ! j5_landing_current_slug() ) { return; }

	$sprite = get_stylesheet_directory() . '/landing-assets/icons.svg';
	if ( ! file_exists( $sprite ) ) { return; }

	echo '<div style="position:absolute;width:0;height:0;overflow:hidden;" aria-hidden="true">';
	readfile( $sprite );
	echo '</div>';
} );

// ===========================================================================
//  Helpers
// ===========================================================================

function j5_get_category_info( $slug ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) { return null; }
	return array(
		'term_id' => (int) $term->term_id,
		'name'    => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
		'url'     => get_term_link( $term ),
		'count'   => (int) $term->count,
	);
}

function j5_get_landing_featured_products( $product_ids, $fallback_cat_id = 0, $limit = 4 ) {
	if ( ! empty( $product_ids ) ) {
		$posts = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__in'       => array_slice( $product_ids, 0, $limit ),
			'orderby'        => 'post__in',
		) );
		if ( ! empty( $posts ) ) { return $posts; }
	}

	if ( $fallback_cat_id ) {
		$posts = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => array( array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $fallback_cat_id,
				'include_children' => true,
			) ),
		) );
		return $posts;
	}

	return array();
}
