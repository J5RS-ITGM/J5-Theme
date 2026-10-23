<?php
/**
 * J5 Documents: order -> plain document array.
 *
 * Everything the templates print comes from j5doc_build(). The templates
 * only read this array, so they can be previewed and tested without
 * WooCommerce, and nothing else decides what a document says.
 *
 * Types:
 *   receipt       paid order (date paid / transaction / paid status)
 *   invoice       not paid yet: amount due, pay-online link
 *   packing-slip  no prices; SKUs, options, acknowledgments, LE hold banner
 * j5doc_billing_type() picks receipt vs invoice from the order itself.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Receipt when the order is paid, Invoice when money is still owed. */
function j5doc_billing_type( $order ) {
	return j5doc_is_paid( $order ) ? 'receipt' : 'invoice';
}

/** Paid = WC paid status, or a payment date / transaction on record (covers On Hold LE orders). */
function j5doc_is_paid( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}
	if ( $order->has_status( array( 'refunded' ) ) ) {
		return true;
	}
	return $order->is_paid() || (bool) $order->get_date_paid() || '' !== (string) $order->get_transaction_id();
}

/** Seller block. Filter j5doc_seller to change it. */
function j5doc_seller() {
	return apply_filters( 'j5doc_seller', array(
		'name'    => 'J5 Rescue Supply LLC',
		'lines'   => array( '388 Twin Creeks Drive', 'Bolingbrook, IL 60440' ),
		'phone'   => '(630) 442-4938',
		'email'   => 'info@j5rescue.com',
		'website' => 'j5rescue.com',
	) );
}

/** Money as plain text for the PDF (no WC price HTML). */
function j5doc_money( $amount, $order = null ) {
	$currency = $order instanceof WC_Order ? $order->get_currency() : get_woocommerce_currency();
	$sym      = html_entity_decode( get_woocommerce_currency_symbol( $currency ), ENT_QUOTES, 'UTF-8' );
	$neg      = (float) $amount < 0;
	$num      = number_format( abs( (float) $amount ), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
	return ( $neg ? '-' : '' ) . $sym . $num;
}

/** Date in the store format. */
function j5doc_date( $date, $with_time = false ) {
	if ( ! $date ) {
		return '';
	}
	$fmt = get_option( 'date_format', 'F j, Y' ) . ( $with_time ? ' ' . get_option( 'time_format', 'g:i a' ) : '' );
	return $date instanceof WC_DateTime ? $date->date_i18n( $fmt ) : date_i18n( $fmt, strtotime( (string) $date ) );
}

/** Local file path of a product's thumbnail (for the PDF), or ''. */
function j5doc_image_path( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}
	$id = $product->get_image_id();
	if ( ! $id && $product->get_parent_id() ) {
		$parent = wc_get_product( $product->get_parent_id() );
		$id     = $parent ? $parent->get_image_id() : 0;
	}
	if ( ! $id ) {
		return '';
	}
	$file = get_attached_file( $id );
	if ( ! $file ) {
		return '';
	}
	$meta = wp_get_attachment_metadata( $id );
	foreach ( array( 'woocommerce_gallery_thumbnail', 'thumbnail', 'woocommerce_thumbnail' ) as $size ) {
		if ( ! empty( $meta['sizes'][ $size ]['file'] ) ) {
			$thumb = trailingslashit( dirname( $file ) ) . $meta['sizes'][ $size ]['file'];
			if ( is_readable( $thumb ) ) {
				return $thumb;
			}
		}
	}
	return is_readable( $file ) && filesize( $file ) < 2 * MB_IN_BYTES ? $file : '';
}

/** Public URL of the same thumbnail (for the HTML preview). */
function j5doc_image_url( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}
	$id = $product->get_image_id();
	if ( ! $id && $product->get_parent_id() ) {
		$parent = wc_get_product( $product->get_parent_id() );
		$id     = $parent ? $parent->get_image_id() : 0;
	}
	$src = $id ? wp_get_attachment_image_src( $id, 'thumbnail' ) : false;
	return $src ? $src[0] : '';
}

/** PO number from common meta keys (filter j5doc_po_meta_keys to add yours). */
function j5doc_po_number( $order ) {
	foreach ( (array) apply_filters( 'j5doc_po_meta_keys', array( '_j5_po_number', '_po_number', 'po_number', '_billing_po', 'billing_po', '_purchase_order_number' ) ) as $key ) {
		$v = trim( (string) $order->get_meta( $key ) );
		if ( '' !== $v ) {
			return $v;
		}
	}
	return '';
}

/** Lead time / special-order note for a line, from the theme's fulfillment helpers. */
function j5doc_fulfillment_note( $product ) {
	if ( ! $product instanceof WC_Product || 'onbackorder' !== $product->get_stock_status() ) {
		return '';
	}
	if ( function_exists( 'j5_product_lead_weeks' ) ) {
		$lead = j5_product_lead_weeks( $product );
		if ( $lead ) {
			return 'Factory order · ' . $lead['text'];
		}
	}
	return 'Special order';
}

/**
 * Build the document.
 *
 * @param WC_Order $order
 * @param string   $type receipt|invoice|packing-slip ('billing' picks receipt/invoice)
 * @return array
 */
function j5doc_build( $order, $type = 'billing' ) {
	if ( 'billing' === $type || 'invoice' === $type || 'receipt' === $type ) {
		$type = j5doc_billing_type( $order );
	}
	$is_slip  = 'packing-slip' === $type;
	$paid     = j5doc_is_paid( $order );
	$refunded = (float) $order->get_total_refunded();

	// Status badge.
	if ( $is_slip ) {
		$badge = array( 'text' => '', 'tone' => '' );
	} elseif ( $order->has_status( 'refunded' ) || ( $refunded > 0 && $refunded >= (float) $order->get_total() ) ) {
		$badge = array( 'text' => 'Refunded', 'tone' => 'muted' );
	} elseif ( $refunded > 0 ) {
		$badge = array( 'text' => 'Paid · Partially refunded', 'tone' => 'good' );
	} elseif ( $paid ) {
		$badge = array( 'text' => 'Paid', 'tone' => 'good' );
	} elseif ( $order->has_status( 'cancelled' ) ) {
		$badge = array( 'text' => 'Cancelled', 'tone' => 'muted' );
	} else {
		$badge = array( 'text' => 'Payment due', 'tone' => 'due' );
	}

	// Header facts (label => value), only the ones that exist.
	$facts = array( 'Order' => '#' . $order->get_order_number() );
	$facts['Order date'] = j5doc_date( $order->get_date_created() );
	if ( ! $is_slip ) {
		if ( $paid && $order->get_date_paid() ) {
			$facts['Paid'] = j5doc_date( $order->get_date_paid() );
		}
		if ( $order->get_payment_method_title() ) {
			$facts['Payment'] = $order->get_payment_method_title();
		}
		if ( $paid && $order->get_transaction_id() ) {
			$facts['Transaction'] = $order->get_transaction_id();
		}
	} else {
		$ship_methods = $order->get_shipping_method();
		if ( $ship_methods ) {
			$facts['Ship via'] = $ship_methods;
		}
	}
	$po = j5doc_po_number( $order );
	if ( '' !== $po ) {
		$facts['PO'] = $po;
	}

	// Addresses.
	$billing  = $order->get_formatted_billing_address();
	$shipping = $order->has_shipping_address() ? $order->get_formatted_shipping_address() : '';
	$parties  = array(
		'bill_to'  => $billing ? wp_kses( $billing, array( 'br' => array() ) ) : '',
		'ship_to'  => $shipping ? wp_kses( $shipping, array( 'br' => array() ) ) : ( $billing ? wp_kses( $billing, array( 'br' => array() ) ) : '' ),
		'email'    => $order->get_billing_email(),
		'phone'    => $order->get_billing_phone(),
		'shipping' => $order->get_shipping_method(),
	);

	// Lines.
	$ack_defs = function_exists( 'j5_ack_definitions' ) ? j5_ack_definitions() : array();
	$items    = array();
	foreach ( $order->get_items() as $item_id => $item ) {
		if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
			continue;
		}
		$product = $item->get_product();
		$qty     = (float) $item->get_quantity();
		$sku     = (string) $item->get_meta( '_j5_sku' );
		if ( '' === $sku && $product ) {
			$sku = (string) $product->get_sku();
		}

		// Variation attributes + option picks (visible meta; N/A already filtered by the theme).
		$meta = array();
		foreach ( $item->get_formatted_meta_data( '_' ) as $m ) {
			$meta[] = array(
				'label' => wp_strip_all_tags( $m->display_key ),
				'value' => trim( wp_strip_all_tags( html_entity_decode( $m->display_value, ENT_QUOTES, 'UTF-8' ) ) ),
			);
		}

		// Product-page acknowledgments affirmed on this line.
		$acks     = array();
		$affirmed = $item->get_meta( '_j5_ack_affirmed' );
		if ( is_array( $affirmed ) ) {
			foreach ( $affirmed as $slug => $rec ) {
				$acks[] = array(
					'title' => isset( $rec['title'] ) ? (string) $rec['title'] : ( isset( $ack_defs[ $slug ]['title'] ) ? $ack_defs[ $slug ]['title'] : (string) $slug ),
					'when'  => isset( $rec['timestamp'] ) ? j5doc_date( $rec['timestamp'], true ) : '',
				);
			}
		}

		$items[] = array(
			'name'      => wp_strip_all_tags( $item->get_name() ),
			'sku'       => $sku,
			'qty'       => 0.0 === fmod( $qty, 1 ) ? (string) (int) $qty : (string) $qty,
			'unit'      => $qty ? j5doc_money( (float) $item->get_subtotal() / $qty, $order ) : '',
			'total'     => j5doc_money( (float) $item->get_subtotal(), $order ),
			'meta'      => $meta,
			'acks'      => $acks,
			'note'      => j5doc_fulfillment_note( $product ),
			'le_only'   => $product && function_exists( 'j5_is_le_only' ) && j5_is_le_only( $product ),
			'img_path'  => j5doc_image_path( $product ),
			'img_url'   => j5doc_image_url( $product ),
		);
	}

	// Totals (WC's own rows: subtotal, discounts, shipping, fees, tax, refunds, total).
	$totals = array();
	if ( ! $is_slip ) {
		foreach ( $order->get_order_item_totals() as $key => $row ) {
			if ( 'payment_method' === $key ) {
				continue;
			}
			$totals[] = array(
				'key'   => $key,
				'label' => rtrim( wp_strip_all_tags( $row['label'] ), ': ' ),
				'value' => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( $row['value'], ENT_QUOTES, 'UTF-8' ) ) ) ),
			);
		}
	}

	// Checkout acknowledgments (order level).
	$order_acks = array();
	$record     = $order->get_meta( '_j5_acknowledgments_record' );
	if ( is_array( $record ) ) {
		foreach ( $record as $r ) {
			if ( empty( $r['affirmed'] ) ) {
				continue;
			}
			$order_acks[] = array(
				'title' => isset( $r['title'] ) ? (string) $r['title'] : '',
				'when'  => isset( $r['timestamp'] ) ? j5doc_date( $r['timestamp'], true ) : '',
			);
		}
	}

	// LE verification (packing slip banner only).
	$le = (string) $order->get_meta( '_j5le_status' );

	// Amount due / pay link on invoices.
	$due = array();
	if ( 'invoice' === $type ) {
		$amount = (float) $order->get_total() - $refunded;
		$due    = array(
			'amount' => j5doc_money( $amount, $order ),
			'link'   => $order->needs_payment() ? $order->get_checkout_payment_url() : '',
		);
	}

	$titles = array( 'receipt' => 'Receipt', 'invoice' => 'Invoice', 'packing-slip' => 'Packing Slip' );

	return apply_filters( 'j5doc_data', array(
		'type'       => $type,
		'title'      => $titles[ $type ],
		'badge'      => $badge,
		'facts'      => $facts,
		'seller'     => j5doc_seller(),
		'parties'    => $parties,
		'items'      => $items,
		'totals'     => $totals,
		'order_acks' => $order_acks,
		'note'       => trim( (string) $order->get_customer_note() ),
		'le_status'  => $le,
		'due'        => $due,
		'number'     => $order->get_order_number(),
	), $order, $type );
}
