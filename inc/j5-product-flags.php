<?php
/**
 * J5 Product Flags
 * ----------------
 * Two small product-level behaviors that sit on top of hub/DP-published data:
 *
 * 1. NOT-APPLICABLE ATTRIBUTES ("Carrier Only" has no Level)
 *    When every variation that matches the shopper's earlier choices uses a
 *    not-applicable term for an attribute (Level = "N/A"), that attribute's
 *    selector is hidden and the N/A value is chosen automatically, so WC can
 *    still match the variation. N/A is also hidden from the variation name,
 *    the cart and the order, so nobody sees "Level: N/A".
 *
 *    Data contract (hub/DP side): give the variations that have no value for
 *    an attribute an explicit N/A term, e.g.
 *        Model = Carrier Only, Level = N/A
 *        Model = Airius,       Level = II
 *        Model = Airius,       Level = IIIA
 *    Recognized N/A slugs: n-a, na, none, not-applicable (filter
 *    j5_na_attribute_slugs). WC's "Any Level" is deliberately NOT treated as
 *    N/A: "Any" legitimately means "the shopper still picks, price is the
 *    same" on other products (e.g. Any Size).
 *    The client-side half lives in assets/js/j5-shop.js (J5-VAR-NA-1).
 *
 * 2. LAW ENFORCEMENT ONLY
 *    Products flagged LE-only get a banner on the product page and an
 *    "LE ONLY" badge on product cards. Detection (any one is enough):
 *      - product meta in j5_le_only_meta_keys (default _j5_le_only) set to
 *        1 / yes / true / on
 *      - product tag in j5_le_only_tags (default le-only, law-enforcement-only)
 *    Variations inherit the parent's flag. Display only: this does not block
 *    purchase. Pair it with an acknowledgment (J5 Apps -> Acknowledgments)
 *    if buyers should certify eligibility at checkout.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 1. NOT-APPLICABLE ATTRIBUTES
 * ========================================================================= */

/** Attribute term slugs that mean "this attribute doesn't apply". */
function j5_na_attribute_slugs() {
	return array_values( array_unique( array_map( 'sanitize_title', (array) apply_filters(
		'j5_na_attribute_slugs',
		array( 'n-a', 'na', 'none', 'not-applicable' )
	) ) ) );
}

/** True if an attribute value (slug or display name) is a not-applicable term. */
function j5_is_na_value( $value ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );
	return '' !== $value && in_array( sanitize_title( $value ), j5_na_attribute_slugs(), true );
}

/** Hand the N/A slugs to j5-shop.js before it runs. */
add_action( 'wp_enqueue_scripts', function () {
	if ( wp_script_is( 'j5-shop', 'enqueued' ) ) {
		wp_add_inline_script( 'j5-shop', 'window.j5NaSlugs = ' . wp_json_encode( j5_na_attribute_slugs() ) . ';', 'before' );
	}
}, 20 );

/**
 * Strip N/A values from a variation name. With two or fewer attributes WC
 * builds names like "UPT Moody BC - Carrier Only, N/A"; this returns
 * "UPT Moody BC - Carrier Only". Names without N/A pass through untouched.
 *
 * @param string $name
 * @return string
 */
function j5_strip_na_from_name( $name ) {
	$pos = strrpos( (string) $name, ' - ' );
	if ( false === $pos ) {
		return $name;
	}
	$base  = substr( $name, 0, $pos );
	$parts = array_map( 'trim', explode( ',', substr( $name, $pos + 3 ) ) );
	$keep  = array_values( array_filter( $parts, function ( $p ) {
		return ! j5_is_na_value( $p );
	} ) );
	if ( count( $keep ) === count( $parts ) ) {
		return $name;
	}
	return $keep ? $base . ' - ' . implode( ', ', $keep ) : $base;
}

add_filter( 'woocommerce_product_variation_get_name', 'j5_strip_na_from_name', 20 );
add_filter( 'woocommerce_cart_item_name', function ( $name ) {
	return is_string( $name ) ? j5_strip_na_from_name( $name ) : $name;
}, 20 );
add_filter( 'woocommerce_order_item_name', function ( $name ) {
	return is_string( $name ) ? j5_strip_na_from_name( $name ) : $name;
}, 20 );

/** Cart / mini-cart / checkout: drop "Level: N/A" lines. */
add_filter( 'woocommerce_get_item_data', function ( $item_data ) {
	if ( ! is_array( $item_data ) ) {
		return $item_data;
	}
	return array_values( array_filter( $item_data, function ( $row ) {
		$v = isset( $row['display'] ) && '' !== $row['display'] ? $row['display'] : ( isset( $row['value'] ) ? $row['value'] : '' );
		return ! j5_is_na_value( $v );
	} ) );
}, 20 );

/** Order screens, emails, invoices: hide N/A attribute meta (it stays stored). */
add_filter( 'woocommerce_order_item_get_formatted_meta_data', function ( $meta ) {
	foreach ( (array) $meta as $id => $m ) {
		if ( isset( $m->value ) && j5_is_na_value( $m->value ) ) {
			unset( $meta[ $id ] );
		}
	}
	return $meta;
}, 20 );

/* =========================================================================
 * 2. LAW ENFORCEMENT ONLY
 * ========================================================================= */

/**
 * Whether a product is flagged law-enforcement-only.
 *
 * @param WC_Product|int $product
 * @return bool
 */
function j5_is_le_only( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) {
		return false;
	}
	$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

	$flag = false;
	foreach ( (array) apply_filters( 'j5_le_only_meta_keys', array( '_j5_le_only' ) ) as $key ) {
		$raw = get_post_meta( $id, $key, true );
		if ( is_bool( $raw ) ? $raw : in_array( strtolower( trim( (string) $raw ) ), array( '1', 'yes', 'true', 'on' ), true ) ) {
			$flag = true;
			break;
		}
	}
	if ( ! $flag ) {
		$tags = (array) apply_filters( 'j5_le_only_tags', array( 'le-only', 'law-enforcement-only' ) );
		if ( $tags && has_term( $tags, 'product_tag', $id ) ) {
			$flag = true;
		}
	}
	return (bool) apply_filters( 'j5_is_le_only', $flag, $product );
}

/** Product page banner. Called from woocommerce/single-product.php. */
function j5_render_le_only_banner( $product ) {
	if ( ! j5_is_le_only( $product ) ) {
		return;
	}
	$title = apply_filters( 'j5_le_only_title', 'Law Enforcement Only', $product );
	$body  = apply_filters(
		'j5_le_only_body',
		'Sold to law enforcement agencies, sworn officers, and licensed security professionals only. <a href="' . esc_url( home_url( '/contact-us/' ) ) . '">Questions?</a>',
		$product
	);
	?>
	<div class="j5-le-only" role="note">
		<span class="j5-le-only__tag">
			<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2.5 4 5.5v6c0 5 3.4 8.6 8 10 4.6-1.4 8-5 8-10v-6l-8-3Z"/></svg>
			<?php echo esc_html( $title ); ?>
		</span>
		<span class="j5-le-only__body"><?php echo wp_kses_post( $body ); ?></span>
	</div>
	<?php
}

/** "LE ONLY" badge on product cards (shop grid, brand/category pages, related). */
add_filter( 'j5_product_card_badges', function ( $badges, $product ) {
	if ( j5_is_le_only( $product ) ) {
		array_unshift( $badges, array( 'class' => 'le-only', 'label' => 'LE ONLY' ) );
	}
	return $badges;
}, 10, 2 );
