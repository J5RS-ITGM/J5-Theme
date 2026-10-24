<?php
/**
 * Customer invoice / Order details email (theme override).
 *
 * Same as WooCommerce's template, with one change: for an unpaid order the
 * "Pay for this order" text link becomes a red button, and the sentence above
 * it is plain. Everything else (order table, addresses, additional content)
 * is unchanged, so WooCommerce's hooks and settings still apply.
 *
 * Based on woocommerce/templates/emails/customer-invoice.php @version 9.8.0
 *
 * @package astra-child
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$pay_url = $order->get_checkout_payment_url();
?>

<?php if ( $order->has_status( 'pending' ) ) : ?>
	<p style="margin:0 0 16px;">
		<?php
		printf(
			/* translators: %s: Site name */
			esc_html__( 'An order has been created for you on %s. The details are below. Use the button to pay when you are ready.', 'woocommerce' ),
			esc_html( get_bloginfo( 'name', 'display' ) )
		);
		?>
	</p>
	<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 28px;">
		<tr>
			<td style="border-radius:4px;background:#c8102e;">
				<a href="<?php echo esc_url( $pay_url ); ?>" style="display:inline-block;padding:14px 28px;font-family:Helvetica,Arial,sans-serif;font-size:15px;font-weight:bold;letter-spacing:.04em;color:#ffffff;text-decoration:none;border-radius:4px;">
					<?php
					/* translators: %s: order total */
					printf( esc_html__( 'Pay %s now', 'woocommerce' ), wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) );
					?>
				</a>
			</td>
		</tr>
	</table>
	<p style="margin:0 0 24px;font-size:13px;color:#6e6e66;">
		<?php esc_html_e( 'If the button does not work, copy this link into your browser:', 'woocommerce' ); ?><br>
		<a href="<?php echo esc_url( $pay_url ); ?>" style="color:#6e6e66;word-break:break-all;"><?php echo esc_html( $pay_url ); ?></a>
	</p>
<?php else : ?>
	<p>
		<?php
		printf(
			/* translators: %s: Order date */
			esc_html__( 'Here are the details of your order placed on %s:', 'woocommerce' ),
			esc_html( wc_format_datetime( $order->get_date_created() ) )
		);
		?>
	</p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
