<?php
/**
 * J5 Rescue Supply — Checkout Payment
 * Overrides: woocommerce/checkout/payment.php
 *
 * Styled payment method selection + Place Order CTA.
 * All WooCommerce payment hooks preserved.
 */

defined( 'ABSPATH' ) || exit;

if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}
?>

<div id="payment" class="woocommerce-checkout-payment j5-payment">

	<div class="j5-checkout__section-header j5-checkout__section-header--payment">
		<span class="j5-checkout__section-number">05</span>
		<h3 class="j5-checkout__section-title"><?php esc_html_e( 'Payment', 'woocommerce' ); ?></h3>
	</div>

	<?php if ( WC()->cart->needs_payment() ) : ?>
		<ul class="wc_payment_methods payment_methods methods j5-payment__methods">
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
				}
			} else {
				echo '<li class="j5-payment__no-methods">';
				echo wp_kses_post( apply_filters( 'woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country()
					? esc_html__( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' )
					: esc_html__( 'Please fill in your details above to see available payment methods.', 'woocommerce' )
				) );
				echo '</li>';
			}
			?>
		</ul>
	<?php endif; ?>

	<div class="form-row place-order j5-payment__place-order">
		<noscript>
			<?php esc_html_e( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the &lt;em&gt;Update Totals&lt;/em&gt; button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce' ); ?>
			<br/>
			<button type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>"><?php esc_html_e( 'Update totals', 'woocommerce' ); ?></button>
		</noscript>

		<?php wc_get_template( 'checkout/terms.php' ); ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<?php echo apply_filters( // phpcs:ignore
			'woocommerce_order_button_html',
			'<button type="submit" class="button alt j5-btn-place-order" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '"><span class="j5-btn-place-order__text">' . esc_html( $order_button_text ) . '</span><span class="j5-btn-place-order__icon">&#10140;</span></button>'
		); ?>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>

	<div class="j5-trust-badges">
		<div class="j5-trust-badge">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
			<span>SSL Encrypted</span>
		</div>
		<div class="j5-trust-badge">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
			<span>Secure Checkout</span>
		</div>
		<div class="j5-trust-badge">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
			<span>Veteran Owned</span>
		</div>
	</div>

</div>

<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
?>
