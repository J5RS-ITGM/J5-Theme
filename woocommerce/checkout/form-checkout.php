<?php
/**
 * J5 Rescue Supply — Checkout Form
 * Overrides: woocommerce/checkout/form-checkout.php
 *
 * Preserves ALL WooCommerce hooks for payment gateway + plugin compatibility.
 * Brand: dark tactical aesthetic, two-column layout.
 *
 * @see https://woocommerce.com/document/template-structure/
 */

defined( 'ABSPATH' ) || exit;

// Allow plugins/themes to add content before the checkout form
do_action( 'woocommerce_before_checkout_form', $checkout );

// If registration is disabled and user is not logged in, show message
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout j5-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<div class="j5-checkout__wrapper">

		<?php // ── LEFT COLUMN: Customer Details ── ?>
		<div class="j5-checkout__customer">

			<div class="j5-checkout__section-header">
				<span class="j5-checkout__section-number">01</span>
				<h3 class="j5-checkout__section-title"><?php esc_html_e( 'Billing Details', 'woocommerce' ); ?></h3>
			</div>

			<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

			<div id="customer_details">
				<div class="j5-checkout__billing">
					<?php do_action( 'woocommerce_checkout_billing' ); ?>
				</div>

				<div class="j5-checkout__shipping">
					<?php do_action( 'woocommerce_checkout_shipping' ); ?>
				</div>
			</div>

			<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<?php // ── Additional fields / Order Notes ── ?>
			<?php if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) : ?>
				<div class="j5-checkout__section-header j5-checkout__section-header--notes">
					<span class="j5-checkout__section-number">03</span>
					<h3 class="j5-checkout__section-title"><?php esc_html_e( 'Additional Information', 'woocommerce' ); ?></h3>
				</div>
			<?php endif; ?>

		</div>

		<?php // ── RIGHT COLUMN: Order Summary + Payment ── ?>
		<div class="j5-checkout__sidebar">

			<div class="j5-checkout__sidebar-sticky">
				<div class="j5-checkout__section-header">
					<span class="j5-checkout__section-number">
						<?php echo apply_filters( 'j5_checkout_order_section_number', '04' ); ?>
					</span>
					<h3 class="j5-checkout__section-title" id="order_review_heading">
						<?php esc_html_e( 'Your Order', 'woocommerce' ); ?>
					</h3>
				</div>

				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

				<div id="order_review" class="woocommerce-checkout-review-order j5-checkout__review">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>

				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
			</div>

		</div>

	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
