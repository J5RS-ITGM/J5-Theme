<?php
/**
 * J5 order received ("thank you") page.
 *
 * Layout lives in woocommerce/j5-order-status.php (shared with My Account >
 * View order). This file keeps the WC hooks payment gateways and analytics
 * rely on: woocommerce_before_thankyou, woocommerce_thankyou_{method} (inside
 * the partial) and woocommerce_thankyou.
 *
 * @var WC_Order|false $order
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order ) {
	echo '<div class="j5-ty"><div class="j5-ty__hero"><h1 class="j5-ty__title">Thank you</h1><p class="j5-ty__lead">Your order has been received.</p></div></div>';
	return;
}

do_action( 'woocommerce_before_thankyou', $order->get_id() );

$context = 'thankyou';
include get_stylesheet_directory() . '/woocommerce/j5-order-status.php';

// Other plugins (analytics, conversion tracking) hook here. The default
// order details table is removed in functions.php so it doesn't render twice.
do_action( 'woocommerce_thankyou', $order->get_id() );
