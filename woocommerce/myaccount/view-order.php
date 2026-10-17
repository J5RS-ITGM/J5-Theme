<?php
/**
 * My Account > View order: same layout as the order received page
 * (woocommerce/j5-order-status.php). WC's default details table is removed
 * from woocommerce_view_order in inc/j5-order-view.php; the hook still fires
 * for other plugins.
 *
 * @var int $order_id
 */
defined( 'ABSPATH' ) || exit;

$order = wc_get_order( $order_id );
if ( ! $order ) return;

$context = 'account';
include get_stylesheet_directory() . '/woocommerce/j5-order-status.php';

do_action( 'woocommerce_view_order', $order_id );
