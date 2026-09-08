<?php
/**
 * J5 Checkout — checkout field customizations.
 *
 * Currently: makes the billing phone number a required field on the
 * classic (shortcode) checkout. WooCommerce validates required fields
 * server-side automatically, so no extra validation hook is needed.
 *
 * Priority 20 so this runs after any plugin that adjusts checkout
 * fields at the default priority.
 *
 * Added 2026-07-13. @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	if ( isset( $fields['billing']['billing_phone'] ) ) {
		$fields['billing']['billing_phone']['required'] = true;
	} else {
		// Field was removed by something else — restore it as required.
		$fields['billing']['billing_phone'] = array(
			'label'        => __( 'Phone', 'woocommerce' ),
			'required'     => true,
			'type'         => 'tel',
			'class'        => array( 'form-row-wide' ),
			'validate'     => array( 'phone' ),
			'autocomplete' => 'tel',
			'priority'     => 100,
		);
	}
	return $fields;
}, 20 );
