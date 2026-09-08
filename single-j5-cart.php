<?php
/**
 * J5 Cart Template — Phase 1
 *
 * Standalone full-page template for the WooCommerce cart. Does NOT
 * call get_header()/get_footer() — renders its own shell to stay
 * isolated from Astra + Elementor styling, matching the pattern of
 * single-j5-product.php.
 *
 * Loaded conditionally via template_include filter in
 * j5-cart-checkout-setup.php:
 *   - Active when URL has ?j5_preview=1 on the cart page
 *   - OR when J5_CART_LIVE constant is defined in wp-config.php
 *
 * Phase 1: full-page-reload cart updates. AJAX qty/remove comes in Phase 2.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Safety guard
if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
	wp_redirect( home_url() );
	exit;
}

// Header helpers (same pattern as single-j5-product.php)
$j5_has_logo      = function_exists( 'has_custom_logo' ) && has_custom_logo();
$j5_cart_url      = wc_get_cart_url();
$j5_cart_count    = WC()->cart->get_cart_contents_count();
$j5_account_url   = wc_get_page_permalink( 'myaccount' );
$j5_account_label = is_user_logged_in() ? 'Account' : 'Login';
$j5_shop_url      = wc_get_page_permalink( 'shop' );
$j5_checkout_url  = wc_get_checkout_url();

$cart_items       = WC()->cart->get_cart();
$cart_is_empty    = empty( $cart_items );

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<?php wp_head(); ?>
</head>
<body <?php body_class( 'j5-cart-template' ); ?>>
<?php if ( function_exists( 'wp_body_open' ) ) { wp_body_open(); } ?>

<!-- UTIL BAR -->
<div class="j5-util-bar">
	<div class="j5-container j5-util-inner">
		<div class="j5-util-left">
			<a href="tel:+16304424938"><span>☏</span> (630) 442-4938</a>
			<a href="<?php echo esc_url( site_url( '/contact-us/' ) ); ?>">✉ CONTACT</a>
			<a href="<?php echo esc_url( site_url( '/shipment-tracking/' ) ); ?>">◉ TRACK SHIPMENT</a>
		</div>
		<div class="j5-util-right">
			<a href="<?php echo esc_url( site_url( '/ffl-services/' ) ); ?>">FFL SERVICES</a>
			<a href="<?php echo esc_url( site_url( '/request-a-quote/' ) ); ?>">REQUEST A QUOTE</a>
			<?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?><a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener" class="j5-le-badge"><span class="j5-pulse-dot"></span>LE / AGENCY PORTAL →</a><?php endif; ?>
		</div>
	</div>
</div>

<!-- HEADER -->
<header class="j5-site-header">
	<div class="j5-container j5-header-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="j5-brand">
			<?php if ( $j5_has_logo ) : the_custom_logo(); else : ?>
				<div class="j5-brand-mark"></div>
				<div class="j5-brand-text">
					<div class="j5-brand-name"><?php bloginfo( 'name' ); ?></div>
					<div class="j5-brand-sub">MEDICAL &middot; TACTICAL &middot; ARMOR</div>
				</div>
			<?php endif; ?>
		</a>
		<nav class="j5-main-nav" aria-label="Primary">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'j5-menu',
					'fallback_cb'    => '__return_false',
					'depth'          => 2,
					'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
				) );
			}
			?>
		</nav>
		<div class="j5-header-actions" style="display:none !important;">
			<a href="<?php echo esc_url( $j5_shop_url ); ?>" class="j5-btn-icon" aria-label="Search">⌕</a>
			<a href="<?php echo esc_url( $j5_account_url ); ?>" class="j5-btn-icon"><?php echo esc_html( $j5_account_label ); ?></a>
			<a href="<?php echo esc_url( $j5_cart_url ); ?>" class="j5-cart-trigger">
				Cart <span class="cart-count"><?php echo esc_html( str_pad( $j5_cart_count, 2, '0', STR_PAD_LEFT ) ); ?></span>
			</a>
		</div>
	</div>
</header>

<!-- CART MAIN -->
<main class="j5cart-main">
	<div class="j5-container">

		<!-- Breadcrumb steps -->
		<nav class="j5cart-steps" aria-label="Checkout progress">
			<span class="active">Cart</span>
			<span class="sep">›</span>
			<span class="dim">Checkout</span>
			<span class="sep">›</span>
			<span class="dim">Confirmation</span>
		</nav>

		<?php if ( $cart_is_empty ) : ?>

			<!-- EMPTY CART STATE -->
			<div class="j5cart-empty">
				<div class="j5cart-empty-icon">⊘</div>
				<h1 class="j5cart-empty-h">Your Cart is Empty</h1>
				<p class="j5cart-empty-p">No items queued up yet. Head to the shop to find gear that keeps you ready.</p>
				<a href="<?php echo esc_url( $j5_shop_url ); ?>" class="j5cart-empty-cta">Browse the Shop →</a>
			</div>

		<?php else : ?>

			<div class="j5cart-headrow">
				<h1 class="j5cart-h1">Your Cart</h1>
				<div class="j5cart-subcount"><?php echo esc_html( $j5_cart_count ); ?> item<?php echo ( $j5_cart_count === 1 ? '' : 's' ); ?> &nbsp;·&nbsp; ready for checkout</div>
			</div>

			<?php
			// Display any WooCommerce notices (coupon applied, error messages, etc.)
			if ( function_exists( 'wc_print_notices' ) ) {
				wc_print_notices();
			}
			?>

			<form class="j5cart-form woocommerce-cart-form" action="<?php echo esc_url( $j5_cart_url ); ?>" method="post">
				<?php do_action( 'woocommerce_before_cart_contents' ); ?>

				<div class="j5cart-grid">

					<!-- ITEMS COLUMN -->
					<div class="j5cart-items">

						<?php
						foreach ( $cart_items as $cart_item_key => $cart_item ) :
							$product_id  = $cart_item['product_id'];
							$variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
							$product     = $cart_item['data'];

							if ( ! $product || ! $product->exists() || $cart_item['quantity'] <= 0 ) {
								continue;
							}

							// Price calculations
							$qty          = (int) $cart_item['quantity'];
							$regular_price = (float) $product->get_regular_price();
							$sale_price    = (float) $product->get_price();
							$line_total    = $sale_price * $qty;
							$line_regular  = $regular_price * $qty;
							$has_discount  = ( $regular_price > $sale_price && $sale_price > 0 );
							$discount_pct  = $has_discount ? round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100, 1 ) : 0;
							$line_savings  = $has_discount ? ( $line_regular - $line_total ) : 0;

							// Product details
							$name        = $product->get_name();
							$sku         = $product->get_sku();
							$permalink   = apply_filters( 'woocommerce_cart_item_permalink',
								$product->is_visible() ? $product->get_permalink( $cart_item ) : '',
								$cart_item,
								$cart_item_key
							);
							$thumb_id    = $product->get_image_id();
							$thumb_url   = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';
							$fallback    = j5_cart_product_thumb_fallback( $product );

							// Variation attributes (color, size, etc.)
							$meta_parts = array();
							if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
								foreach ( $cart_item['variation'] as $attr_key => $attr_val ) {
									if ( empty( $attr_val ) ) { continue; }
									$label = str_replace( array( 'attribute_', 'pa_' ), '', $attr_key );
									$label = ucwords( str_replace( array( '-', '_' ), ' ', $label ) );
									$meta_parts[] = '<span>' . esc_html( $label ) . ':</span> ' . esc_html( $attr_val );
								}
							}
							if ( $sku ) {
								$meta_parts[] = '<span>SKU:</span> ' . esc_html( $sku );
							}

							$remove_url = wc_get_cart_remove_url( $cart_item_key );
						?>

						<div class="j5cart-card">

							<!-- Top row: thumb + title + qty -->
							<div class="j5cart-card-top">
								<div class="j5cart-thumb">
									<?php if ( $thumb_url ) : ?>
										<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" />
									<?php else : ?>
										<span class="j5cart-thumb-fb"><?php echo esc_html( $fallback ); ?></span>
									<?php endif; ?>
								</div>

								<div class="j5cart-titlewrap">
									<?php if ( $permalink ) : ?>
										<a href="<?php echo esc_url( $permalink ); ?>" class="j5cart-title"><?php echo esc_html( $name ); ?></a>
									<?php else : ?>
										<span class="j5cart-title"><?php echo esc_html( $name ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $meta_parts ) ) : ?>
										<div class="j5cart-meta"><?php echo wp_kses_post( implode( ' &nbsp;·&nbsp; ', $meta_parts ) ); ?></div>
									<?php endif; ?>
								</div>

								<div class="j5cart-qty">
									<button type="button" class="j5cart-qbtn j5cart-qminus" aria-label="Decrease quantity">−</button>
									<input type="number"
										class="j5cart-qin"
										name="cart[<?php echo esc_attr( $cart_item_key ); ?>][qty]"
										value="<?php echo esc_attr( $qty ); ?>"
										min="1"
										step="1"
										aria-label="Quantity" />
									<button type="button" class="j5cart-qbtn j5cart-qplus" aria-label="Increase quantity">+</button>
								</div>
							</div>

							<hr class="j5cart-hr" />

							<!-- Rates row: MSRP / Your Price / Qty / Line Total -->
							<div class="j5cart-rates">
								<div class="j5cart-rcol">
									<div class="j5cart-rlbl">MSRP</div>
									<?php if ( $has_discount ) : ?>
										<div class="j5cart-rmsrp"><?php echo wp_kses_post( wc_price( $regular_price ) ); ?></div>
									<?php else : ?>
										<div class="j5cart-rval"><?php echo wp_kses_post( wc_price( $regular_price ) ); ?></div>
									<?php endif; ?>
								</div>
								<div class="j5cart-rcol">
									<div class="j5cart-rlbl">Your Price</div>
									<div class="j5cart-rval"><?php echo wp_kses_post( wc_price( $sale_price ) ); ?></div>
								</div>
								<div class="j5cart-rcol">
									<div class="j5cart-rlbl">Quantity</div>
									<div class="j5cart-rval"><?php echo esc_html( $qty ); ?></div>
								</div>
								<div class="j5cart-rcol right">
									<div class="j5cart-rlbl">Line Total</div>
									<div class="j5cart-rtotal"><?php echo wp_kses_post( wc_price( $line_total ) ); ?></div>
								</div>
							</div>

							<?php if ( $has_discount ) : ?>
								<div class="j5cart-disc">
									<div class="j5cart-disc-l"><strong>Discount Active:</strong> −<?php echo esc_html( $discount_pct ); ?>%</div>
									<div class="j5cart-disc-r">Saving <?php echo wp_kses_post( wc_price( $line_savings ) ); ?> on this line</div>
								</div>
							<?php endif; ?>

							<div class="j5cart-acts">
								<a href="<?php echo esc_url( $remove_url ); ?>"
									class="j5cart-rm"
									aria-label="Remove <?php echo esc_attr( $name ); ?> from cart">Remove</a>
							</div>
						</div>

						<?php endforeach; ?>

						<!-- Update cart (for qty changes on full reload) -->
						<div class="j5cart-update">
							<button type="submit" name="update_cart" value="Update Cart" class="j5cart-update-btn">Update Cart</button>
							<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
						</div>

						<?php do_action( 'woocommerce_cart_contents' ); ?>
						<?php do_action( 'woocommerce_after_cart_contents' ); ?>

					</div>

					<!-- SUMMARY SIDEBAR -->
					<div class="j5cart-summary-wrap">
						<aside class="j5cart-summary">
							<div class="j5cart-sumlbl">Order Summary</div>

							<!-- Promo code (full reload; uses WC's native coupon handler) -->
							<?php if ( wc_coupons_enabled() ) : ?>
								<div class="j5cart-promo">
									<input
										type="text"
										name="coupon_code"
										class="j5cart-promo-input"
										placeholder="Promo code"
										id="coupon_code" />
									<button type="submit" name="apply_coupon" value="Apply" class="j5cart-promo-btn">Apply</button>
								</div>

								<?php if ( ! empty( WC()->cart->get_applied_coupons() ) ) : ?>
									<div class="j5cart-applied-coupons">
										<?php foreach ( WC()->cart->get_applied_coupons() as $code ) :
											$remove_url = wc_get_cart_remove_coupon_url( $code );
										?>
											<div class="j5cart-ac-row">
												<span><?php echo esc_html( strtoupper( $code ) ); ?> applied</span>
												<a href="<?php echo esc_url( $remove_url ); ?>">Remove</a>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							<?php endif; ?>

							<!-- Totals -->
							<?php
							$subtotal   = WC()->cart->get_subtotal();
							$total      = WC()->cart->get_total( 'edit' );
							$shipping   = WC()->cart->get_shipping_total();
							$tax_total  = WC()->cart->get_total_tax();
							$savings    = j5_cart_total_savings();
							?>

							<div class="j5cart-line">
								<span>Subtotal</span>
								<span><?php echo wp_kses_post( wc_price( $subtotal ) ); ?></span>
							</div>

							<?php if ( $savings > 0 ) : ?>
								<div class="j5cart-line save">
									<span>Total savings</span>
									<span>−<?php echo wp_kses_post( wc_price( $savings ) ); ?></span>
								</div>
							<?php endif; ?>

							<div class="j5cart-line">
								<span>Shipping</span>
								<?php if ( $shipping > 0 ) : ?>
									<span><?php echo wp_kses_post( wc_price( $shipping ) ); ?></span>
								<?php elseif ( WC()->cart->needs_shipping() ) : ?>
									<span style="color:#7DBF4B;">FREE</span>
								<?php else : ?>
									<span>—</span>
								<?php endif; ?>
							</div>

							<?php if ( $tax_total > 0 ) : ?>
								<div class="j5cart-line">
									<span>Est. tax</span>
									<span><?php echo wp_kses_post( wc_price( $tax_total ) ); ?></span>
								</div>
							<?php endif; ?>

							<div class="j5cart-line total">
								<span>Total</span>
								<span class="amt"><?php echo wp_kses_post( WC()->cart->get_total() ); ?></span>
							</div>

							<a href="<?php echo esc_url( $j5_checkout_url ); ?>" class="j5cart-cta">Checkout →</a>
							<a href="<?php echo esc_url( $j5_shop_url ); ?>" class="j5cart-continue">← Continue shopping</a>

							<!-- Life Safety Guarantee -->
							<div class="j5cart-lsg">
								<div class="j5cart-lsg-ico">⚕</div>
								<div>
									<strong>Life Safety Guarantee</strong>
									5-year replacement coverage
								</div>
							</div>

							<!-- SSL Trust -->
							<div class="j5cart-trust">
								<span class="j5cart-tico">🔒</span>
								<div>256-bit SSL encrypted checkout<br/>PCI DSS Level 1 compliant</div>
							</div>
						</aside>
					</div>

				</div>
			</form>

		<?php endif; ?>

	</div>
</main>

<!-- MINI FOOTER -->
<footer class="j5-site-footer j5-footer-mini-wrap">
	<div class="j5-container j5-footer-mini">
		<div>© <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> &middot; ALL RIGHTS RESERVED</div>
		<div>
			<a href="<?php echo esc_url( site_url( '/privacy-policy/' ) ); ?>">Privacy</a>
			<a href="<?php echo esc_url( site_url( '/terms-conditions-2/' ) ); ?>">Terms</a>
			<?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?><a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener">LE Portal →</a><?php endif; ?>
		</div>
	</div>
</footer>

<script>
// Phase 1: basic qty +/− buttons that update the input value.
// On next submit (or "Update Cart" button), the form posts and WC recalculates.
// Phase 2 will replace this with AJAX fragments.
document.addEventListener('DOMContentLoaded', function(){
	document.querySelectorAll('.j5cart-card').forEach(function(card){
		var input = card.querySelector('.j5cart-qin');
		var minus = card.querySelector('.j5cart-qminus');
		var plus  = card.querySelector('.j5cart-qplus');
		if (!input) return;
		if (minus) minus.addEventListener('click', function(){
			var v = parseInt(input.value, 10) || 1;
			if (v > 1) input.value = v - 1;
		});
		if (plus) plus.addEventListener('click', function(){
			var v = parseInt(input.value, 10) || 1;
			input.value = v + 1;
		});
	});
});
</script>

<?php wp_footer(); ?>
</body>
</html>
