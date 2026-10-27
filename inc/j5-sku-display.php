<?php
/**
 * J5-SKU-DISPLAY: what the storefront shows after "SKU:".
 *
 * SKUs come from the dealer portal. Variable parents usually have no SKU
 * of their own (only the variations do), so the old '#<ID>' fallback put
 * the WC post ID in the SKU slot. This shows a real, DP-searchable code
 * instead:
 *   - the product's own SKU when it has one
 *   - variable parent without one: the shared stem of its variation SKUs
 *     (L211-SH-SC-S-SET + L211-SH-SC-L-SET -> L211-SH-SC), or the only
 *     variation's SKU when there's just one
 *   - nothing at all: '' (callers hide the SKU line, never show the ID)
 *
 * On the product page j5-shop.js swaps in the selected variation's full
 * SKU on found_variation and restores this value on reset.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'j5_sku_common_stem' ) ) {
	/**
	 * Longest shared prefix of the SKUs, cut back to a whole segment.
	 */
	function j5_sku_common_stem( array $skus ) {
		$skus = array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $skus ) ) ) ) );
		if ( ! $skus ) {
			return '';
		}
		if ( 1 === count( $skus ) ) {
			return $skus[0];
		}
		$prefix = $skus[0];
		foreach ( $skus as $s ) {
			$max = min( strlen( $prefix ), strlen( $s ) );
			$i   = 0;
			while ( $i < $max && strtoupper( $prefix[ $i ] ) === strtoupper( $s[ $i ] ) ) {
				$i++;
			}
			$prefix = substr( $prefix, 0, $i );
			if ( '' === $prefix ) {
				return '';
			}
		}
		// Only keep whole segments: everything up to the last separator,
		// unless the prefix already ends exactly on one in every SKU.
		$whole = true;
		foreach ( $skus as $s ) {
			if ( strlen( $s ) > strlen( $prefix ) && ! in_array( $s[ strlen( $prefix ) ], array( '-', '_', '.', ' ', '/' ), true ) ) {
				$whole = false;
				break;
			}
		}
		if ( ! $whole ) {
			$cut    = max( strrpos( $prefix, '-' ), strrpos( $prefix, '_' ), strrpos( $prefix, '.' ), strrpos( $prefix, ' ' ), strrpos( $prefix, '/' ) );
			$prefix = false === $cut ? '' : substr( $prefix, 0, $cut );
		}
		$prefix = rtrim( $prefix, "-_./ " );
		return strlen( $prefix ) >= 3 ? $prefix : '';
	}
}

if ( ! function_exists( 'j5_display_sku' ) ) {
	/**
	 * @param WC_Product $product
	 * @return string SKU to display, or '' to hide the SKU line.
	 */
	function j5_display_sku( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		$own = trim( (string) $product->get_sku( 'edit' ) );
		if ( '' !== $own ) {
			return $own;
		}
		if ( ! $product->is_type( 'variable' ) ) {
			return '';
		}
		$skus = array();
		foreach ( $product->get_children() as $child_id ) {
			$skus[] = get_post_meta( $child_id, '_sku', true );
		}
		return j5_sku_common_stem( $skus );
	}
}
