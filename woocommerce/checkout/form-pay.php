<?php
/**
 * J5 Rescue Supply — Pay for order (order-pay endpoint, payment links)
 * Overrides: woocommerce/checkout/form-pay.php (WC 10.9.0)
 *
 * Two columns, same look as checkout:
 *   left   order summary (thumbnails, item meta, totals) + billing / shipping
 *   right  payment methods, terms, Pay button (reuses the checkout sidebar,
 *          payment tile and button styles from j5-checkout.css)
 * Page-specific layout: assets/css/j5-order-pay.css.
 *
 * Kept from core so gateways keep working: form#order_review, div#payment,
 * ul.wc_payment_methods via checkout/payment-method.php, button#place_order,
 * woocommerce_pay=1, the woocommerce-pay nonce, and every core hook/filter.
 *
 * @package WooCommerce\Templates
 * @version 10.9.0
 */

defined( 'ABSPATH' ) || exit;

$totals = $order->get_order_item_totals(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
unset( $totals['payment_method'] ); // not chosen yet on this page

$j5_billing  = $order->get_formatted_billing_address();
$j5_shipping = $order->needs_shipping_address() ? $order->get_formatted_shipping_address() : '';
$j5_phone    = $order->get_billing_phone();
$j5_email    = $order->get_billing_email();
?>
<div class="j5-pay">

	<header class="j5-pay__head">
		<p class="j5-pay__eyebrow">Secure payment</p>
		<h1 class="j5-pay__title">Complete your order</h1>
		<p class="j5-pay__meta">
			Order #<?php echo esc_html( $order->get_order_number() ); ?>
			<?php if ( $order->get_date_created() ) : ?>
				<span aria-hidden="true">·</span> <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
			<?php endif; ?>
			<span aria-hidden="true">·</span> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
		</p>
	</header>

	<form id="order_review" method="post" class="j5-pay__form">
		<div class="j5-pay__grid">

			<?php // ── LEFT: summary + addresses ── ?>
			<div class="j5-pay__main">

				<section class="j5-pay__card" aria-labelledby="j5-pay-summary">
					<div class="j5-checkout__section-header">
						<span class="j5-checkout__section-number">01</span>
						<h2 class="j5-checkout__section-title" id="j5-pay-summary">Order summary</h2>
					</div>

					<ul class="j5-pay__items">
						<?php foreach ( $order->get_items() as $item_id => $item ) :
							if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
								continue;
							}
							$j5_product = $item->get_product();
							?>
							<li class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'order_item', $item, $order ) ); ?> j5-pay__item">
								<div class="j5-pay__thumb">
									<?php
									echo $j5_product
										? wp_kses_post( $j5_product->get_image( array( 72, 72 ) ) )
										: wp_kses_post( wc_placeholder_img( array( 72, 72 ) ) );
									?>
								</div>
								<div class="j5-pay__info">
									<div class="j5-pay__name">
										<?php echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) ); ?>
									</div>
									<div class="j5-pay__item-meta">
										<?php
										do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, false );
										wc_display_item_meta( $item );
										do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, false );
										?>
									</div>
									<div class="j5-pay__qty">
										<?php echo apply_filters( 'woocommerce_order_item_quantity_html', 'Qty ' . esc_html( $item->get_quantity() ), $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								</div>
								<div class="j5-pay__line"><?php echo $order->get_formatted_line_subtotal( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php if ( $totals ) : ?>
						<dl class="j5-pay__totals">
							<?php foreach ( $totals as $key => $total ) : ?>
								<div class="j5-pay__total-row j5-pay__total-row--<?php echo esc_attr( sanitize_html_class( $key ) ); ?>">
									<dt><?php echo wp_kses_post( rtrim( $total['label'], ': ' ) ); ?></dt>
									<dd><?php echo wp_kses_post( $total['value'] ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				</section>

				<section class="j5-pay__card" aria-labelledby="j5-pay-addresses">
					<div class="j5-checkout__section-header">
						<span class="j5-checkout__section-number">02</span>
						<h2 class="j5-checkout__section-title" id="j5-pay-addresses">Billing &amp; shipping</h2>
					</div>
					<div class="j5-pay__addresses">
						<div class="j5-pay__address">
							<h3 class="j5-pay__address-title">Billing</h3>
							<address>
								<?php echo $j5_billing ? wp_kses_post( $j5_billing ) : esc_html__( 'N/A', 'woocommerce' ); ?>
								<?php if ( $j5_phone ) : ?><span class="j5-pay__contact"><?php echo esc_html( $j5_phone ); ?></span><?php endif; ?>
								<?php if ( $j5_email ) : ?><span class="j5-pay__contact"><?php echo esc_html( $j5_email ); ?></span><?php endif; ?>
							</address>
						</div>
						<?php if ( $order->needs_shipping_address() ) : ?>
							<div class="j5-pay__address">
								<h3 class="j5-pay__address-title">Shipping</h3>
								<address><?php echo $j5_shipping ? wp_kses_post( $j5_shipping ) : wp_kses_post( $j5_billing ); ?></address>
							</div>
						<?php endif; ?>
					</div>
					<p class="j5-pay__help">Something need changing? Call <a href="tel:+16304424938">(630) 442-4938</a> or email <a href="mailto:info@j5rescue.com">info@j5rescue.com</a> before paying and we'll update the order.</p>
				</section>

			</div>

			<?php // ── RIGHT: payment (checkout sidebar styles) ── ?>
			<div class="j5-pay__side j5-checkout__sidebar">
				<div class="j5-checkout__sidebar-sticky">

					<?php
					/**
					 * Triggered from within the checkout/form-pay.php template, immediately before the payment section.
					 *
					 * @since 8.2.0
					 */
					do_action( 'woocommerce_pay_order_before_payment' );
					?>

					<div id="payment" class="woocommerce-checkout-payment j5-payment">
						<div class="j5-checkout__section-header j5-checkout__section-header--payment">
							<span class="j5-checkout__section-number">03</span>
							<h2 class="j5-checkout__section-title">Payment</h2>
						</div>

						<?php if ( $order->needs_payment() ) : ?>
							<ul class="wc_payment_methods payment_methods methods j5-payment__methods" aria-label="<?php esc_attr_e( 'Payment methods', 'woocommerce' ); ?>">
								<?php
								if ( ! empty( $available_gateways ) ) {
									foreach ( $available_gateways as $gateway ) {
										wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
									}
								} else {
									echo '<li>';
									wc_print_notice( apply_filters( 'woocommerce_no_available_payment_methods_message', esc_html__( 'Sorry, it seems that there are no available payment methods for your location. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) ), 'notice' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
									echo '</li>';
								}
								?>
							</ul>
						<?php endif; ?>

						<div class="form-row place-order j5-payment__place-order">
							<input type="hidden" name="woocommerce_pay" value="1" />

							<?php wc_get_template( 'checkout/terms.php' ); ?>

							<?php do_action( 'woocommerce_pay_order_before_submit' ); ?>

							<?php
							echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								'woocommerce_pay_order_button_html',
								'<button type="submit" class="button alt j5-btn-place-order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '"><span class="j5-btn-place-order__text">' . esc_html( $order_button_text ) . '</span><span class="j5-btn-place-order__icon">&#10140;</span></button>'
							);
							?>

							<?php do_action( 'woocommerce_pay_order_after_submit' ); ?>

							<?php wp_nonce_field( 'woocommerce-pay', 'woocommerce-pay-nonce' ); ?>
						</div>

						<div class="j5-trust-badges">
							<div class="j5-trust-badge">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
								<span>SSL Encrypted</span>
							</div>
							<div class="j5-trust-badge">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
								<span>Secure Checkout</span>
							</div>
							<div class="j5-trust-badge">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
								<span>Veteran Owned</span>
							</div>
						</div>
					</div>

				</div>
			</div>

		</div>
	</form>
</div>
