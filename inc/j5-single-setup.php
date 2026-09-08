<?php
/**
 * J5 Single Post Setup
 *
 * Asset enqueue + small helpers for the single blog post template
 * (single-post.php). Applies ONLY to post type 'post' — products,
 * pages, and other single-* templates are untouched.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 * CSS enqueue — single blog posts only. filemtime versioning.
 * ============================================================ */

if ( ! function_exists( 'j5_single_enqueue_assets' ) ) {
	function j5_single_enqueue_assets() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$rel_path = 'assets/css/j5-single.css';
		$abs_path = get_stylesheet_directory() . '/' . $rel_path;
		if ( ! file_exists( $abs_path ) ) {
			return;
		}

		wp_enqueue_style(
			'j5-single',
			get_stylesheet_directory_uri() . '/' . $rel_path,
			array(),
			filemtime( $abs_path )
		);
	}
	add_action( 'wp_enqueue_scripts', 'j5_single_enqueue_assets', 30 );
}

/* ============================================================
 * Body class — lets CSS scope cleanly and helps debugging.
 * ============================================================ */

if ( ! function_exists( 'j5_single_body_class' ) ) {
	function j5_single_body_class( $classes ) {
		if ( is_singular( 'post' ) ) {
			$classes[] = 'j5-single-template';
		}
		return $classes;
	}
	add_filter( 'body_class', 'j5_single_body_class' );
}

/* ============================================================
 * Related posts — same first category, exclude current,
 * fall back to most recent if the category is thin.
 * ============================================================ */

if ( ! function_exists( 'j5_single_get_related_posts' ) ) {
	function j5_single_get_related_posts( $post_id, $count = 3 ) {
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		$cats = get_the_category( $post_id );
		if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
			$args['cat'] = (int) $cats[0]->term_id;
		}

		$related = get_posts( $args );

		// Category too thin? Top up with recent posts.
		if ( count( $related ) < $count ) {
			$have_ids = wp_list_pluck( $related, 'ID' );
			$have_ids[] = $post_id;
			$fill = get_posts( array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => $count - count( $related ),
				'post__not_in'        => $have_ids,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			) );
			$related = array_merge( $related, $fill );
		}

		return $related;
	}
}

/* ============================================================
 * Post-footer CTA copy. Filterable so it can be customized
 * (or per-category later) without editing the template.
 * ============================================================ */

if ( ! function_exists( 'j5_single_get_cta' ) ) {
	function j5_single_get_cta( $post_id = 0 ) {
		$cta = array(
			'heading' => 'Questions about the gear in this article?',
			'text'    => 'Every in-stock SKU with real stock counts — no drop-ship surprises.',
			'label'   => 'Shop All Gear',
			'url'     => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
		);
		return apply_filters( 'j5_single_cta', $cta, $post_id );
	}
}
