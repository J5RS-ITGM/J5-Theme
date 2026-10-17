<?php
/**
 * J5 Request a Quote mode (per product)
 *
 * Source of truth: `_j5_quote_mode` product meta, pushed by the DP with the
 * other _j5_* storefront meta. Values:
 *   also -> "Request a Quote" button shown alongside Add to Cart
 *   only -> price and Add to Cart hidden; quote button only
 *   ''   -> normal product
 * Fallback for quick one-offs from wp-admin: product tags `request-quote`
 * (= also) and `quote-only` (= only). Meta wins when set.
 *
 * The button links to /request-a-quote/?product=ID, and the quote form
 * pre-fills the product name, SKU and link (inc/j5-forms-render.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** @return string 'also' | 'only' | '' */
function j5_quote_mode( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) return '';
	$pid  = $product->get_parent_id() ?: $product->get_id();
	$meta = strtolower( trim( (string) get_post_meta( $pid, '_j5_quote_mode', true ) ) );
	if ( in_array( $meta, array( 'also', 'only' ), true ) ) return $meta;
	if ( has_term( 'quote-only', 'product_tag', $pid ) ) return 'only';
	if ( has_term( 'request-quote', 'product_tag', $pid ) ) return 'also';
	return '';
}

function j5_quote_url( $product ) {
	$page = get_page_by_path( 'request-a-quote' );
	$base = $page ? get_permalink( $page ) : home_url( '/request-a-quote/' );
	return add_query_arg( 'product', $product->get_id(), $base );
}

/** Button markup, used under the cart form and (quote-only) in place of it. */
function j5_render_quote_button( $product ) {
	$mode = j5_quote_mode( $product );
	if ( ! $mode ) return;
	$label = 'only' === $mode ? 'Request a Quote' : 'Request a Quote';
	$hint  = 'only' === $mode
		? 'Pricing on this item is quoted to fit your configuration and quantity.'
		: 'Buying for a department or in volume? Get a written quote.';
	?>
	<div class="j5-quote-cta j5-quote-cta--<?php echo esc_attr( $mode ); ?>">
		<a class="j5-quote-btn" href="<?php echo esc_url( j5_quote_url( $product ) ); ?>"><?php echo esc_html( $label ); ?> →</a>
		<span class="j5-quote-hint"><?php echo esc_html( $hint ); ?></span>
	</div>
	<?php
}

/* Quote-only: not purchasable anywhere (product page, archives, direct add-to-cart URLs). */
add_filter( 'woocommerce_is_purchasable', function ( $purchasable, $product ) {
	return 'only' === j5_quote_mode( $product ) ? false : $purchasable;
}, 20, 2 );
add_filter( 'woocommerce_variation_is_purchasable', function ( $purchasable, $variation ) {
	return 'only' === j5_quote_mode( $variation ) ? false : $purchasable;
}, 20, 2 );

/* Quote-only: replace the price with a prompt. */
add_filter( 'woocommerce_get_price_html', function ( $html, $product ) {
	return 'only' === j5_quote_mode( $product ) ? '<span class="j5-quote-price">Request a quote for pricing</span>' : $html;
}, 50, 2 );

/* Archive/grid cards: quote-only items link to the quote form instead of "Read more". */
add_filter( 'woocommerce_loop_add_to_cart_link', function ( $html, $product ) {
	if ( 'only' !== j5_quote_mode( $product ) ) return $html;
	return '<a class="button j5-quote-loop-btn" href="' . esc_url( j5_quote_url( $product ) ) . '">Request a Quote</a>';
}, 20, 2 );

add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
	?>
	<style>
	.j5-quote-cta{margin:14px 0 0;display:flex;flex-direction:column;gap:6px}
	.j5-quote-btn{display:inline-flex;align-items:center;justify-content:center;padding:14px 24px;border:2px solid #C8A84E;color:#C8A84E;font-family:'Bebas Neue',Impact,sans-serif;font-size:19px;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;border-radius:2px;transition:all .18s ease}
	.j5-quote-btn:hover{background:#C8A84E;color:#0B0B0B}
	.j5-quote-cta--only .j5-quote-btn{background:#C41E24;border-color:#C41E24;color:#fff}
	.j5-quote-cta--only .j5-quote-btn:hover{background:#9d181d;border-color:#9d181d;color:#fff}
	.j5-quote-hint{color:#b0b0b0;font-size:14px}
	.j5-quote-price{font-size:.7em;color:#C8A84E;letter-spacing:.02em}
	</style>
	<?php
} );
