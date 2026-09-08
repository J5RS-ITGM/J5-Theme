<?php
/**
 * J5 Variation Display Config
 * ---------------------------
 * Site-specific presentation choices layered on top of the generic variation
 * engine in inc/j5-variation-display.php. Kept separate so the engine stays
 * product-agnostic and these decisions are easy to find and revise.
 *
 * Two things live here:
 *
 * 1. DISPLAY TYPE — pa_cut and pa_size render as pills rather than radio
 *    cards. The engine auto-selects radio-cards when price varies across
 *    options (price becomes part of the choice), but per-option prices are
 *    now suppressed (see J5-VAR-PRICE-1), which left full-width cards holding
 *    a single short label and a lot of empty space. Pills wrap compactly and
 *    suit short labels like "Small" or "SAPI Cut".
 *
 * 2. OPTION ORDER — sizes arrive unsorted from the catalog (XL, Small,
 *    Medium, Large). Ordering is properly the hub's responsibility; this is a
 *    presentation-layer safety net. NOTE: if term order is later fixed
 *    hub-side, this filter will still override it — remove or adjust then.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Force pills for short-label attributes.
 *
 * NOTE: j5_attr_display_map takes no arguments and returns a MAP of
 * sanitized attribute slug => display type ('pills' | 'radio-cards' |
 * 'dropdown' | 'image-swatches'), consulted before all auto-detection.
 *
 * @param array $map
 * @return array
 */
add_filter( 'j5_attr_display_map', function ( $map ) {
	$pills = apply_filters( 'j5_pill_attributes', array( 'pa_cut', 'pa_size' ) );
	foreach ( $pills as $slug ) {
		$map[ $slug ] = 'pills';
	}
	return $map;
} );

/**
 * Canonical size ordering, smallest → largest. Matching is done on a
 * normalized form so "X-Large", "XL", and "x large" all resolve alike.
 *
 * @return array Ordered list of normalized size keys.
 */
function j5_size_order() {
	return apply_filters( 'j5_size_order', array(
		'xxs', 'xs',
		'small', 's',
		'medium', 'm',
		'large', 'l',
		'xl', 'xlarge',
		'xxl', 'xxlarge',
		'2xl', '3xl',
	) );
}

/**
 * Normalize a size label for order matching.
 *
 * @param string $label
 * @return string
 */
function j5_normalize_size( $label ) {
	$s = strtolower( trim( wp_strip_all_tags( (string) $label ) ) );
	$s = str_replace( array( '-', '_', ' ' ), '', $s );
	// Common long forms → short keys.
	$map = array(
		'extralarge'  => 'xl',
		'extrasmall'  => 'xs',
		'xlarge'      => 'xl',
		'xxlarge'     => 'xxl',
	);
	return isset( $map[ $s ] ) ? $map[ $s ] : $s;
}

/**
 * Sort size options into a sensible order.
 *
 * Terms not present in the canonical list are NOT dropped — they are appended
 * in their original order. A new size (e.g. "Small-Short") therefore still
 * renders, just at the end, rather than silently disappearing.
 *
 * @param array  $labels         value => label map.
 * @param string $attribute_name Raw attribute name.
 * @return array
 */
add_filter( 'j5_variation_option_labels', function ( $labels, $attribute_name ) {
	$sortable = apply_filters( 'j5_sorted_size_attributes', array( 'pa_size' ) );
	if ( ! in_array( $attribute_name, $sortable, true ) || count( $labels ) < 2 ) {
		return $labels;
	}

	$order = array_flip( j5_size_order() );
	$known = array();
	$other = array();

	foreach ( $labels as $value => $label ) {
		$key = j5_normalize_size( $label );
		if ( isset( $order[ $key ] ) ) {
			$known[ $value ] = array( 'label' => $label, 'pos' => $order[ $key ] );
		} else {
			$other[ $value ] = $label;
		}
	}

	uasort( $known, function ( $a, $b ) {
		return $a['pos'] <=> $b['pos'];
	} );

	$out = array();
	foreach ( $known as $value => $data ) {
		$out[ $value ] = $data['label'];
	}
	foreach ( $other as $value => $label ) {
		$out[ $value ] = $label;
	}

	return $out;
}, 10, 2 );
