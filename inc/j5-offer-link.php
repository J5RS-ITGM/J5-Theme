<?php
/**
 * Offer links (J5-OFFER-LINK-1, 2026-11-20).
 *
 * A link like https://www.j5rescue.com/cart/?j5_offer=HESCO10-0A2691 fills the
 * visitor's cart with the items stored on that coupon, applies the coupon, and
 * lands on the cart. The customer then checks out normally (name, address,
 * email). Coupon rules (email restriction, usage limit, products, individual
 * use) are still enforced by WooCommerce; email restrictions at checkout.
 *
 * Only coupons carrying _j5_offer_items work as links, so a plain coupon code
 * in the URL does nothing. _j5_offer_items is JSON: {"<product or variation ID>": qty}.
 *
 * Items are added with WC()->cart->add_to_cart(), which skips the product-page
 * acknowledgment checkbox; the acknowledgment checkout backstop still collects it.
 *
 * Loaded from the bottom of inc/j5-factory-savings.php.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'template_redirect', function () {
	if ( empty( $_GET['j5_offer'] ) || is_admin() || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$code     = wc_format_coupon_code( wc_clean( wp_unslash( $_GET['j5_offer'] ) ) );
	$cart_url = wc_get_cart_url();
	$coupon   = new WC_Coupon( $code );
	$items    = $coupon->get_id() ? json_decode( (string) $coupon->get_meta( '_j5_offer_items', true ), true ) : null;

	if ( ! is_array( $items ) || ! $items ) {
		wc_add_notice( 'That offer link is not valid. Please contact us and we will sort it out.', 'error' );
		wp_safe_redirect( $cart_url );
		exit;
	}

	if ( WC()->session && ! WC()->session->has_session() ) {
		WC()->session->set_customer_session_cookie( true );
	}
	WC()->cart->empty_cart();

	$added = 0;
	foreach ( $items as $id => $qty ) {
		$p   = wc_get_product( (int) $id );
		$qty = max( 1, (int) $qty );
		if ( ! $p || ! $p->is_purchasable() ) {
			continue;
		}
		if ( $p->is_type( 'variation' ) ) {
			$key = WC()->cart->add_to_cart( $p->get_parent_id(), $qty, $p->get_id(), $p->get_variation_attributes() );
		} else {
			$key = WC()->cart->add_to_cart( $p->get_id(), $qty );
		}
		if ( $key ) {
			$added++;
		}
	}

	if ( $added !== count( $items ) ) {
		wc_add_notice( 'Some items from this offer could not be added. Please contact us before checking out.', 'error' );
	}
	if ( $added ) {
		WC()->cart->apply_coupon( $code ); // WooCommerce shows its own success / error notice
	}
	wp_safe_redirect( $cart_url );
	exit;
}, 5 );
