<?php
/**
 * J5 Documents: receipts, invoices and packing slips for WooCommerce orders.
 *
 *   Receipt       the order is paid (card / PayPal, or a payment on record).
 *   Invoice       money is still owed (terms, pending); shows amount due and
 *                 the pay-online link. Becomes a Receipt once paid.
 *   Packing slip  no prices; SKUs, options, acknowledgments, LE hold banner.
 *
 * Where they appear:
 *   - PDF attached to the customer order emails (Processing, On hold,
 *     Completed, Order details / invoice, Refunded).
 *   - "Receipt" / "Invoice" button on My Account orders, View order and the
 *     order status page (same customer action list).
 *   - wp-admin: a Documents box on the order screen and icons in the orders
 *     list action column (receipt/invoice + packing slip).
 *
 * URLs: /?j5-doc=<billing|packing-slip>&order=<id>&key=<order key>[&format=html]
 * Staff (edit_shop_orders) can open any document; customers need the order
 * key (or to own the order), and only the billing document.
 *
 * Self-contained so it can move into a plugin later: data.php builds a plain
 * array from the order, template.php renders it, pdf.php runs the bundled
 * dompdf 3.1.0 (lib/dompdf, LGPL) with Barlow / Bebas Neue (fonts/, OFL).
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'J5DOC_DIR', trailingslashit( __DIR__ ) );

require_once J5DOC_DIR . 'includes/data.php';
require_once J5DOC_DIR . 'includes/template.php';
require_once J5DOC_DIR . 'includes/pdf.php';

/* =========================================================================
 * 1. URLS + RENDER
 * ====================================================================== */

/** Document URL. $type: billing | packing-slip. */
function j5doc_url( $order, $type = 'billing', $format = 'pdf' ) {
	$args = array(
		'j5-doc' => $type,
		'order'  => $order->get_id(),
		'key'    => $order->get_order_key(),
	);
	if ( 'html' === $format ) {
		$args['format'] = 'html';
	}
	return add_query_arg( $args, home_url( '/' ) );
}

/** Writable cache for dompdf font metrics (not web-served content). */
function j5doc_cache_dir() {
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'j5-documents/cache';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
	}
	return $dir;
}

/**
 * Render a document.
 *
 * @param WC_Order $order
 * @param string   $type   billing | packing-slip
 * @param string   $format pdf | html
 * @return array [ 'body' => string, 'filename' => string, 'data' => array ]
 */
function j5doc_render( $order, $type = 'billing', $format = 'pdf' ) {
	$data = j5doc_build( $order, 'packing-slip' === $type ? 'packing-slip' : 'billing' );
	$name = 'J5-' . str_replace( ' ', '-', $data['title'] ) . '-' . $data['number'];

	if ( 'html' === $format ) {
		$html = j5doc_html( $data, 'html', array( 'fonts' => get_stylesheet_directory_uri() . '/inc/j5-documents/fonts' ) );
		return array( 'body' => $html, 'filename' => $name . '.html', 'data' => $data );
	}

	$up   = wp_upload_dir( null, false );
	$html = j5doc_html( $data, 'pdf', array( 'fonts' => J5DOC_DIR . 'fonts' ) );
	$pdf  = j5doc_pdf( $html, array(
		'cache' => j5doc_cache_dir(),
		'roots' => array( J5DOC_DIR, $up['basedir'] ),
	) );
	return array( 'body' => $pdf, 'filename' => $name . '.pdf', 'data' => $data );
}

/** Who may open this document. */
function j5doc_can_view( $order, $type ) {
	if ( current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' ) ) {
		return true;
	}
	if ( 'packing-slip' === $type ) {
		return false;
	}
	$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' !== $key && hash_equals( (string) $order->get_order_key(), $key ) ) {
		return true;
	}
	return is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
}

add_action( 'template_redirect', function () {
	if ( empty( $_GET['j5-doc'] ) || empty( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$type   = 'packing-slip' === $_GET['j5-doc'] ? 'packing-slip' : 'billing'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$format = ( isset( $_GET['format'] ) && 'html' === $_GET['format'] ) ? 'html' : 'pdf'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$order  = wc_get_order( absint( $_GET['order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! $order || $order instanceof WC_Order_Refund || ! j5doc_can_view( $order, $type ) ) {
		wp_die( esc_html__( 'This document is not available.', 'woocommerce' ), '', array( 'response' => 403 ) );
	}

	try {
		$doc = j5doc_render( $order, $type, $format );
	} catch ( \Throwable $t ) {
		wc_get_logger()->error( 'J5 Documents: ' . $t->getMessage(), array( 'source' => 'j5-documents', 'order' => $order->get_id() ) );
		wp_die( esc_html__( 'The document could not be created. Please contact us.', 'woocommerce' ), '', array( 'response' => 500 ) );
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	if ( 'html' === $format ) {
		header( 'Content-Type: text/html; charset=utf-8' );
	} else {
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $doc['filename'] . '"' );
		header( 'Content-Length: ' . strlen( $doc['body'] ) );
	}
	echo $doc['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF bytes / escaped template
	exit;
}, 1 );

/* =========================================================================
 * 2. EMAIL ATTACHMENTS
 * ====================================================================== */

/** Customer emails that carry the Receipt / Invoice PDF. Filter j5doc_email_ids. */
function j5doc_email_ids() {
	return (array) apply_filters( 'j5doc_email_ids', array(
		'customer_processing_order',
		'customer_on_hold_order',
		'customer_completed_order',
		'customer_invoice',
		'customer_refunded_order',
		'customer_partially_refunded_order',
	) );
}

add_filter( 'woocommerce_email_attachments', function ( $attachments, $email_id, $object = null, $email = null ) {
	if ( ! $object instanceof WC_Order || $object instanceof WC_Order_Refund || ! in_array( $email_id, j5doc_email_ids(), true ) ) {
		return $attachments;
	}
	if ( ! apply_filters( 'j5doc_email_attach', true, $email_id, $object ) ) {
		return $attachments;
	}
	try {
		$doc = j5doc_render( $object, 'billing', 'pdf' );
	} catch ( \Throwable $t ) {
		wc_get_logger()->error( 'J5 Documents (email): ' . $t->getMessage(), array( 'source' => 'j5-documents', 'order' => $object->get_id() ) );
		return $attachments; // never block the email
	}
	// Private temp folder (not web-served), friendly file name, removed after the request.
	$dir = trailingslashit( get_temp_dir() ) . 'j5doc-' . wp_generate_password( 12, false );
	if ( ! wp_mkdir_p( $dir ) ) {
		return $attachments;
	}
	$file = $dir . '/' . $doc['filename'];
	if ( false === file_put_contents( $file, $doc['body'] ) ) { // phpcs:ignore
		return $attachments;
	}
	j5doc_cleanup_later( $file );
	$attachments[] = $file;
	return $attachments;
}, 20, 4 );

/** Delete temp attachments after WooCommerce's deferred emails (shutdown, priority 10) have gone. */
function j5doc_cleanup_later( $file ) {
	static $files = array();
	static $hooked = false;
	$files[] = $file;
	if ( ! $hooked ) {
		$hooked = true;
		add_action( 'shutdown', function () use ( &$files ) {
			foreach ( $files as $f ) {
				@unlink( $f );       // phpcs:ignore
				@rmdir( dirname( $f ) ); // phpcs:ignore
			}
		}, 1000 );
	}
}

/* =========================================================================
 * 3. CUSTOMER: My Account orders, View order, order status page
 * ====================================================================== */

add_filter( 'woocommerce_my_account_my_orders_actions', function ( $actions, $order ) {
	if ( ! $order instanceof WC_Order || $order->has_status( array( 'failed', 'checkout-draft' ) ) ) {
		return $actions;
	}
	$actions['j5-doc'] = array(
		'url'  => j5doc_url( $order, 'billing' ),
		'name' => 'receipt' === j5doc_billing_type( $order ) ? 'Receipt' : 'Invoice',
	);
	return $actions;
}, 20, 2 );

/* =========================================================================
 * 4. WP-ADMIN
 * ====================================================================== */

add_action( 'add_meta_boxes', function () {
	$screens = array( 'shop_order' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' );
	}
	foreach ( array_unique( $screens ) as $screen ) {
		add_meta_box( 'j5-documents', 'Documents', 'j5doc_admin_box', $screen, 'side', 'high' );
	}
} );

function j5doc_admin_box( $post_or_order ) {
	$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order instanceof WP_Post ? $post_or_order->ID : 0 );
	if ( ! $order || ! $order->get_id() ) {
		echo '<p>Save the order first.</p>';
		return;
	}
	$label = 'receipt' === j5doc_billing_type( $order ) ? 'Receipt' : 'Invoice';
	$btn   = 'display:block;width:100%;text-align:center;margin:0 0 6px;';
	printf( '<a class="button button-primary" style="%s" target="_blank" href="%s">%s (PDF)</a>', esc_attr( $btn ), esc_url( j5doc_url( $order, 'billing' ) ), esc_html( $label ) );
	printf( '<a class="button" style="%s" target="_blank" href="%s">Packing slip (PDF)</a>', esc_attr( $btn ), esc_url( j5doc_url( $order, 'packing-slip' ) ) );
	printf( '<p style="margin:6px 0 0;font-size:12px;color:#646970">%s is chosen from the payment status. <a target="_blank" href="%s">Preview in browser</a></p>', esc_html( $label ), esc_url( j5doc_url( $order, 'billing', 'html' ) ) );
}

add_filter( 'woocommerce_admin_order_actions', function ( $actions, $order ) {
	if ( ! $order instanceof WC_Order ) {
		return $actions;
	}
	$actions['j5doc_billing'] = array(
		'url'    => j5doc_url( $order, 'billing' ),
		'name'   => 'receipt' === j5doc_billing_type( $order ) ? 'Receipt' : 'Invoice',
		'action' => 'j5doc-billing',
	);
	$actions['j5doc_slip'] = array(
		'url'    => j5doc_url( $order, 'packing-slip' ),
		'name'   => 'Packing slip',
		'action' => 'j5doc-slip',
	);
	return $actions;
}, 20, 2 );

add_action( 'admin_head', function () {
	echo '<style>
	.wc-action-button-j5doc-billing::after{font-family:dashicons!important;content:"\f497"!important}
	.wc-action-button-j5doc-slip::after{font-family:dashicons!important;content:"\f163"!important}
	</style>';
	// Open document icons in a new tab.
	echo "<script>document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a.wc-action-button-j5doc-billing,a.wc-action-button-j5doc-slip');if(a){a.target='_blank';}},true);</script>";
} );
