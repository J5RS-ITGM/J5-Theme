<?php
/**
 * J5 Description Shortcodes
 * Structured product description shortcodes rendered on single product pages.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function j5_shortcode_overview( $atts, $content = '' ) {
$content = trim( (string) $content );
if ( $content === '' ) { return ''; }
$atts = shortcode_atts( array( 'label' => 'Overview' ), $atts, 'j5_overview' );
$body = wpautop( do_shortcode( $content ) );
return '<div class="j5-desc-block j5-desc-block-overview">'
. '<div class="j5-desc-lbl">' . esc_html( $atts['label'] ) . '</div>'
. '<div class="j5-desc-p">' . wp_kses_post( $body ) . '</div>'
. '</div>';
}
add_shortcode( 'j5_overview', 'j5_shortcode_overview' );

function j5_shortcode_features( $atts, $content = '' ) {
$content = trim( (string) $content );
if ( $content === '' ) { return ''; }
$atts = shortcode_atts( array( 'label' => 'Key Features' ), $atts, 'j5_features' );
$raw   = wp_strip_all_tags( $content );
$lines = preg_split( '/\r\n|\r|\n/', $raw );
$items = array();
foreach ( (array) $lines as $line ) {
$line = trim( $line );
if ( $line === '' ) { continue; }
$line = preg_replace( '/^[\-\*\x{2022}]\s*/u', '', $line );
if ( $line !== '' ) { $items[] = $line; }
}
if ( empty( $items ) ) { return ''; }
$li = '';
foreach ( $items as $item ) { $li .= '<li>' . esc_html( $item ) . '</li>'; }
return '<div class="j5-desc-block j5-desc-block-features">'
. '<div class="j5-desc-lbl">' . esc_html( $atts['label'] ) . '</div>'
. '<ul class="j5-desc-feat">' . $li . '</ul>'
. '</div>';
}
add_shortcode( 'j5_features', 'j5_shortcode_features' );

function j5_shortcode_context( $atts, $content = '' ) {
$content = trim( (string) $content );
if ( $content === '' ) { return ''; }
$atts = shortcode_atts( array( 'label' => 'Operational Context' ), $atts, 'j5_context' );
$body = wpautop( do_shortcode( $content ) );
return '<div class="j5-desc-block j5-desc-block-context">'
. '<div class="j5-desc-lbl">' . esc_html( $atts['label'] ) . '</div>'
. '<div class="j5-desc-case">' . wp_kses_post( $body ) . '</div>'
. '</div>';
}
add_shortcode( 'j5_context', 'j5_shortcode_context' );

function j5_shortcode_compliance( $atts, $content = '' ) {
$content = trim( wp_strip_all_tags( (string) $content ) );
if ( $content === '' ) { return ''; }
$atts = shortcode_atts( array( 'label' => 'Compliance &amp; Approvals' ), $atts, 'j5_compliance' );
$items = array_filter( array_map( 'trim', explode( ',', $content ) ) );
if ( empty( $items ) ) { return ''; }
$badges = '';
foreach ( $items as $item ) { $badges .= '<span class="j5-desc-badge">' . esc_html( $item ) . '</span>'; }
return '<div class="j5-desc-block j5-desc-block-compliance">'
. '<div class="j5-desc-lbl">' . wp_kses( $atts['label'], array() ) . '</div>'
. '<div class="j5-desc-comp">' . $badges . '</div>'
. '</div>';
}
add_shortcode( 'j5_compliance', 'j5_shortcode_compliance' );

function j5_render_product_description( $description ) {
if ( ! $description ) {
return '<p class="j5-tab-empty">No extended description available for this product. Contact us with any questions about fit, spec, or use-case.</p>';
}
$has_structured = (bool) preg_match( '/\[j5_(overview|features|context|compliance)\b/', $description );
if ( $has_structured ) {
$rendered = do_shortcode( $description );
return '<div class="j5-product-description j5-desc-structured">' . wp_kses_post( $rendered ) . '</div>';
}
return '<div class="j5-product-description j5-desc-legacy">' . wp_kses_post( wpautop( $description ) ) . '</div>';
}
