<?php
/**
 * J5 order status view: wiring.
 *
 * One layout (woocommerce/j5-order-status.php) for the order received page and
 * My Account > View order, plus a "View your order" button in customer order
 * emails that opens it:
 *   - account customers -> My Account > View order (logs in first if needed)
 *   - guest orders      -> the order received page (WC asks for the billing
 *                          email if the session doesn't match)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* Styles: order received page + View order endpoint. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_order_received_page' ) ) return;
	if ( ! is_order_received_page() && ! is_wc_endpoint_url( 'view-order' ) ) return;
	$f = get_stylesheet_directory() . '/assets/css/j5-order.css';
	wp_enqueue_style( 'j5-order', get_stylesheet_directory_uri() . '/assets/css/j5-order.css', array(), file_exists( $f ) ? (string) filemtime( $f ) : null );
}, 21 );

/* View order: our template renders the details, so drop WC's default table (the hook still fires for other plugins). */
add_action( 'init', function () {
	remove_action( 'woocommerce_view_order', 'woocommerce_order_details_table', 10 );
} );

/* Where an email should send the customer to see this order. */
function j5_order_status_url( $order ) {
	return $order->get_customer_id() ? $order->get_view_order_url() : $order->get_checkout_order_received_url();
}

/* "View your order" button at the top of customer order emails. */
add_action( 'woocommerce_email_order_details', function ( $order, $sent_to_admin, $plain_text, $email = null ) {
	if ( $sent_to_admin || ! $order instanceof WC_Order ) return;
	if ( $email && empty( $email->customer_email ) ) return;
	$url = j5_order_status_url( $order );
	if ( $plain_text ) {
		echo "View your order status: " . esc_url_raw( $url ) . "\n\n";
		return;
	}
	echo '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px"><tr><td style="background:#C41E24;border-radius:2px">'
		. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:13px 26px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#ffffff;text-decoration:none">View your order status</a>'
		. '</td></tr></table>';
}, 5, 4 );
