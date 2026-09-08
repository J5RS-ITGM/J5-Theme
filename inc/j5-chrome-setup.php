<?php
/**
 * J5 Chrome — Setup (enqueue, customizer, helpers bootstrap)
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/j5-chrome-helpers.php';

add_action( 'wp_enqueue_scripts', function () {
	$theme_uri = get_stylesheet_directory_uri();
	$theme_dir = get_stylesheet_directory();

	$css_rel = '/assets/css/j5-chrome.css';
	$css_ver = file_exists( $theme_dir . $css_rel ) ? filemtime( $theme_dir . $css_rel ) : '1.0.0';
	wp_enqueue_style( 'j5-chrome', $theme_uri . $css_rel, array(), $css_ver );

	$js_rel = '/assets/js/j5-chrome.js';
	$js_ver = file_exists( $theme_dir . $js_rel ) ? filemtime( $theme_dir . $js_rel ) : '1.0.0';
	wp_enqueue_script( 'j5-chrome', $theme_uri . $js_rel, array(), $js_ver, true );
}, 20 );

add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) return $fragments;
	$count = WC()->cart->get_cart_contents_count();
	$fragments['[data-j5-cart-count]'] =
		'<span class="j5-cart-btn__count" data-j5-cart-count>' . esc_html( $count ) . '</span>';
	return $fragments;
} );

add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_section( 'j5_header', array( 'title' => 'J5 Header', 'priority' => 30 ) );

	$controls = array(
		'j5_shipping_promo'   => array( 'default' => 'Free shipping on orders over $99', 'label' => 'Shipping promo message' ),
		'j5_header_phone'     => array( 'default' => '(630) 442-4938',                    'label' => 'Header phone (display)' ),
		'j5_header_phone_tel' => array( 'default' => 'tel:+16304424938',                  'label' => 'Header phone (tel: link)' ),
	);
	foreach ( $controls as $id => $cfg ) {
		$wp_customize->add_setting( $id, array(
			'default'           => $cfg['default'],
			'sanitize_callback' => 'sanitize_text_field',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $id, array(
			'label'   => $cfg['label'],
			'section' => 'j5_header',
			'type'    => 'text',
		) );
	}
} );

foreach ( array( 'j5_shipping_promo', 'j5_header_phone', 'j5_header_phone_tel' ) as $f ) {
	add_filter( $f, function ( $default ) use ( $f ) {
		$val = get_theme_mod( $f, $default );
		return $val ?: $default;
	} );
}
