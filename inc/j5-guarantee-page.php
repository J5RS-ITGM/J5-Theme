<?php
/**
 * Life Safety Replacement Guarantee page
 * --------------------------------------
 * Page 83814 (content: tools/life-safety-guarantee-apply.sh). This module:
 *   - loads assets/css/j5-guarantee.css on that page only (filemtime versioning)
 *   - gives the product badge (inc/j5-product-badges.php) its link: j5_guarantee_page_url()
 *
 * @package j5-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'J5_GUARANTEE_PAGE_ID' ) ) define( 'J5_GUARANTEE_PAGE_ID', 83814 );

/** Permalink of the guarantee page, or '' when it isn't published. */
function j5_guarantee_page_url() {
	$id = (int) apply_filters( 'j5_guarantee_page_id', J5_GUARANTEE_PAGE_ID );
	return $id && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : '';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_page( (int) apply_filters( 'j5_guarantee_page_id', J5_GUARANTEE_PAGE_ID ) ) ) return;
	$rel = 'assets/css/j5-guarantee.css';
	$abs = get_stylesheet_directory() . '/' . $rel;
	if ( file_exists( $abs ) ) wp_enqueue_style( 'j5-guarantee', get_stylesheet_directory_uri() . '/' . $rel, array(), (string) filemtime( $abs ) );
}, 30 );
