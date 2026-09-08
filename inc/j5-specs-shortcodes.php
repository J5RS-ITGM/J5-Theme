<?php
/**
 * J5 Specifications Shortcodes
 *
 * Structured specifications-tab content rendered alongside the WooCommerce
 * attribute table. Mirrors the architecture of j5-description-shortcodes.php.
 *
 * Content storage: postmeta key `_j5_spec_content` per product. Long-term this
 * is intended to be populated by the Odoo -> WooCommerce sync (woo_sync_family),
 * the same way product descriptions are routed.
 *
 * Six shortcodes:
 *   [j5_techspecs]   - Key:value sub-table (one per line, split on first colon)
 *   [j5_materials]   - Materials & Construction (prose)
 *   [j5_fitment]     - Compatibility & Fitment (prose)
 *   [j5_in_the_box]  - What's in the Box (newline-split list)
 *   [j5_care]        - Care & Maintenance (prose)
 *   [j5_spec_notes]  - Freeform Specification Notes (prose)
 *
 * Render entry point: j5_render_product_specs( $product ).
 * Returns empty string when the product has no structured spec content, so
 * existing products are unaffected.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * [j5_techspecs] - Additional technical-spec rows as a key:value sub-table.
 * Split on newlines; each line splits on the FIRST colon into label / value.
 * Lines without a colon are skipped.
 */
function j5_shortcode_techspecs( $atts, $content = '' ) {
	$content = trim( wp_strip_all_tags( (string) $content ) );
	if ( $content === '' ) { return ''; }
	$atts = shortcode_atts( array( 'label' => 'Technical Specifications' ), $atts, 'j5_techspecs' );
	$lines = preg_split( '/\r\n|\r|\n/', $content );
	$rows = '';
	foreach ( (array) $lines as $line ) {
		$line = trim( $line );
		if ( $line === '' ) { continue; }
		$pos = strpos( $line, ':' );
		if ( $pos === false ) { continue; }
		$lbl = trim( substr( $line, 0, $pos ) );
		$val = trim( substr( $line, $pos + 1 ) );
		if ( $lbl === '' || $val === '' ) { continue; }
		$rows .= '<tr><th>' . esc_html( $lbl ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
	}
	if ( $rows === '' ) { return ''; }
	return '<div class="j5-desc-block j5-specs-block-techspecs">'
		. '<div class="j5-desc-lbl">' . esc_html( $atts['label'] ) . '</div>'
		. '<table class="j5-specs-table j5-specs-table-extra">' . $rows . '</table>'
		. '</div>';
}
add_shortcode( 'j5_techspecs', 'j5_shortcode_techspecs' );

/**
 * Internal: prose-block factory used by materials, fitment, care, spec_notes.
 */
function j5_specs_prose_block( $content, $label, $extra_class = '' ) {
	$content = trim( (string) $content );
	if ( $content === '' ) { return ''; }
	$body = wpautop( do_shortcode( $content ) );
	$class = trim( 'j5-desc-block ' . $extra_class );
	return '<div class="' . esc_attr( $class ) . '">'
		. '<div class="j5-desc-lbl">' . esc_html( $label ) . '</div>'
		. '<div class="j5-desc-p">' . wp_kses_post( $body ) . '</div>'
		. '</div>';
}

function j5_shortcode_materials( $atts, $content = '' ) {
	$atts = shortcode_atts( array( 'label' => 'Materials & Construction' ), $atts, 'j5_materials' );
	return j5_specs_prose_block( $content, $atts['label'], 'j5-specs-block-materials' );
}
add_shortcode( 'j5_materials', 'j5_shortcode_materials' );

function j5_shortcode_fitment( $atts, $content = '' ) {
	$atts = shortcode_atts( array( 'label' => 'Compatibility & Fitment' ), $atts, 'j5_fitment' );
	return j5_specs_prose_block( $content, $atts['label'], 'j5-specs-block-fitment' );
}
add_shortcode( 'j5_fitment', 'j5_shortcode_fitment' );

function j5_shortcode_care( $atts, $content = '' ) {
	$atts = shortcode_atts( array( 'label' => 'Care & Maintenance' ), $atts, 'j5_care' );
	return j5_specs_prose_block( $content, $atts['label'], 'j5-specs-block-care' );
}
add_shortcode( 'j5_care', 'j5_shortcode_care' );

function j5_shortcode_spec_notes( $atts, $content = '' ) {
	$atts = shortcode_atts( array( 'label' => 'Specification Notes' ), $atts, 'j5_spec_notes' );
	return j5_specs_prose_block( $content, $atts['label'], 'j5-specs-block-notes' );
}
add_shortcode( 'j5_spec_notes', 'j5_shortcode_spec_notes' );

/**
 * [j5_in_the_box] - Newline-split list. Leading bullets/dashes stripped automatically.
 */
function j5_shortcode_in_the_box( $atts, $content = '' ) {
	$content = trim( (string) $content );
	if ( $content === '' ) { return ''; }
	$atts = shortcode_atts( array( 'label' => "What's in the Box" ), $atts, 'j5_in_the_box' );
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
	return '<div class="j5-desc-block j5-specs-block-box">'
		. '<div class="j5-desc-lbl">' . esc_html( $atts['label'] ) . '</div>'
		. '<ul class="j5-desc-feat">' . $li . '</ul>'
		. '</div>';
}
add_shortcode( 'j5_in_the_box', 'j5_shortcode_in_the_box' );

/**
 * Render the structured specs region for a product.
 *
 * @param int|WP_Post|WC_Product $product_or_id  Source for the _j5_spec_content meta.
 * @return string HTML region, or empty string when no structured content is set.
 */
function j5_render_product_specs( $product_or_id ) {
	$post_id = 0;
	if ( is_numeric( $product_or_id ) ) {
		$post_id = (int) $product_or_id;
	} elseif ( $product_or_id instanceof WP_Post ) {
		$post_id = (int) $product_or_id->ID;
	} elseif ( is_object( $product_or_id ) && method_exists( $product_or_id, 'get_id' ) ) {
		$post_id = (int) $product_or_id->get_id();
	}
	if ( $post_id <= 0 ) { return ''; }

	$content = (string) get_post_meta( $post_id, '_j5_spec_content', true );
	if ( trim( $content ) === '' ) { return ''; }

	$has_structured = (bool) preg_match( '/\[j5_(techspecs|materials|fitment|in_the_box|care|spec_notes)\b/', $content );
	if ( ! $has_structured ) { return ''; }

	$rendered = do_shortcode( $content );
	return '<div class="j5-product-specs j5-specs-structured">' . wp_kses_post( $rendered ) . '</div>';
}
