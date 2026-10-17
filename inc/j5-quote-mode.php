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
 * The button opens the DP portal quote form (portal.j5rescue.com/quote)
 * pre-filled with sku, name, qty and the product page it came from. On
 * variable products the SKU follows the picked variation, and qty follows
 * the quantity box.
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

/**
 * Quote requests go to the DP portal, which accepts ?sku=&name=&qty=&from=
 * on /quote (and /instant-quote): it fills the line, looks up the SKU for
 * pricing, and records the product page the request came from.
 */
function j5_quote_base() {
	return apply_filters( 'j5_quote_base', 'https://portal.j5rescue.com/quote' ); // or /instant-quote
}

function j5_quote_url( $product, $qty = 1 ) {
	$args = array_filter( array(
		'sku'  => $product->get_sku(),
		'name' => $product->get_name(),
		'qty'  => max( 1, (int) $qty ),
		'from' => get_permalink( $product->get_parent_id() ?: $product->get_id() ),
	) );
	return add_query_arg( array_map( 'rawurlencode', $args ), j5_quote_base() );
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
		<a class="j5-quote-btn" href="<?php echo esc_url( j5_quote_url( $product ) ); ?>"
		   data-base="<?php echo esc_attr( j5_quote_base() ); ?>"
		   data-sku="<?php echo esc_attr( $product->get_sku() ); ?>"
		   data-name="<?php echo esc_attr( $product->get_name() ); ?>"
		   data-from="<?php echo esc_attr( get_permalink( $product->get_id() ) ); ?>"><?php echo esc_html( $label ); ?> →</a>
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

/* Keep the quote link in sync with the picked variation's SKU and the quantity box. */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
	?>
	<script>
	jQuery(function ($) {
	  var $b = $('.j5-quote-btn'); if (!$b.length) return;
	  var base = $b.data('base'), parentSku = String($b.data('sku') || ''), sku = parentSku;
	  function qty() { return Math.max(1, parseInt($('.j5-qty-input, form.cart input.qty').first().val(), 10) || 1); }
	  function update() {
	    var p = new URLSearchParams();
	    if (sku) p.set('sku', sku);
	    p.set('name', $b.data('name')); p.set('qty', qty()); p.set('from', $b.data('from'));
	    $b.attr('href', base + '?' + p.toString());
	  }
	  $('form.variations_form')
	    .on('found_variation', function (e, v) { sku = (v && v.sku) || parentSku; update(); })
	    .on('reset_data', function () { sku = parentSku; update(); });
	  $(document).on('change input click', '.j5-qty-input, .j5-qty-btn, form.cart input.qty', function () { setTimeout(update, 0); });
	  update();
	});
	</script>
	<?php
}, 50 );

