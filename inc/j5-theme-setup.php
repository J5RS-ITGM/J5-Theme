<?php
/**
 * J5 Theme Setup — standalone theme declarations
 *
 * Everything the Astra parent used to declare on this site's behalf.
 * Added 2026-09 as part of the astra-child -> standalone migration.
 *
 * NOTE: register_nav_menu( 'primary' ) is NOT here — inc/j5-home-setup.php
 * already registers it via j5_register_menus_fallback() when no theme has.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function j5_theme_setup() {

	// Let WordPress manage <title>.
	add_theme_support( 'title-tag' );

	// Featured images (product images require this).
	add_theme_support( 'post-thumbnails' );

	// Customizer logo — templates call has_custom_logo()/the_custom_logo().
	add_theme_support( 'custom-logo', array(
		'flex-height' => true,
		'flex-width'  => true,
	) );

	// Modern markup for core-generated fragments.
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );

	// RSS feed links in <head>.
	add_theme_support( 'automatic-feed-links' );

	// WooCommerce: without this, WC flags the theme as unsupported and
	// alters its wrappers. Gallery features match what Astra enabled.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'j5_theme_setup' );

/**
 * Content width (some plugins and embeds read this global).
 */
function j5_theme_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'j5_content_width', 1200 );
}
add_action( 'after_setup_theme', 'j5_theme_content_width', 0 );
