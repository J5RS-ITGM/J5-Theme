<?php
/**
 * J5 Rescue Supply — Checkout Setup
 * inc/j5-checkout-setup.php
 *
 * Wired from functions.php:
 *   require_once get_stylesheet_directory() . '/inc/j5-checkout-setup.php';
 *
 * Handles:
 *  - CSS/JS enqueue (checkout pages only)
 *  - Checkout field reordering & placeholder cleanup
 *  - Elementor Theme Builder override for checkout page
 *  - Coupon form relocation into sidebar
 *  - Shipping section numbering
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================================
 *  ENQUEUE ASSETS — checkout pages only
 * ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_checkout() ) {
		return;
	}

	wp_enqueue_style(
		'j5-checkout-css',
		get_stylesheet_directory_uri() . '/assets/css/j5-checkout.css',
		array(),
		filemtime( get_stylesheet_directory() . '/assets/css/j5-checkout.css' )
	);

	// Google Fonts — same stack as homepage/shop
	wp_enqueue_style(
		'j5-google-fonts-checkout',
		'https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap',
		array(),
		null
	);
}, 20 );


/* ============================================================================
 *  ELEMENTOR THEME BUILDER OVERRIDE — prevent Elementor from hijacking checkout
 * ========================================================================== */
add_filter( 'elementor/theme/should_do_location', function ( $should_print ) {
	if ( is_checkout() ) {
		return false; // Our template handles everything
	}
	return $should_print;
} );


/* ============================================================================
 *  CHECKOUT FIELD CUSTOMIZATION
 * ========================================================================== */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {

	// ── Reorder billing fields ──
	$priority = 10;
	$order    = array(
		'billing_first_name',
		'billing_last_name',
		'billing_email',
		'billing_phone',
		'billing_company',
		'billing_country',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
	);

	foreach ( $order as $field_key ) {
		if ( isset( $fields['billing'][ $field_key ] ) ) {
			$fields['billing'][ $field_key ]['priority'] = $priority;
			$priority += 10;
		}
	}

	// ── Half-width pairs ──
	$half_width = array(
		'billing_first_name',
		'billing_last_name',
		'billing_email',
		'billing_phone',
		'billing_city',
		'billing_postcode',
	);
	foreach ( $half_width as $key ) {
		if ( isset( $fields['billing'][ $key ] ) ) {
			$fields['billing'][ $key ]['class'] = array( 'form-row-wide', 'j5-field-half' );
		}
	}

	// State + country inline
	if ( isset( $fields['billing']['billing_state'] ) ) {
		$fields['billing']['billing_state']['class'] = array( 'form-row-wide', 'j5-field-half' );
	}

	// ── Cleaner placeholders ──
	$placeholders = array(
		'billing_first_name' => 'First name',
		'billing_last_name'  => 'Last name',
		'billing_email'      => 'Email address',
		'billing_phone'      => 'Phone number',
		'billing_company'    => 'Company (optional)',
		'billing_address_1'  => 'Street address',
		'billing_address_2'  => 'Apt, suite, unit (optional)',
		'billing_city'       => 'City',
		'billing_postcode'   => 'ZIP / Postal code',
	);
	foreach ( $placeholders as $key => $ph ) {
		if ( isset( $fields['billing'][ $key ] ) ) {
			$fields['billing'][ $key ]['placeholder'] = $ph;
		}
	}

	// ── Same for shipping ──
	$ship_placeholders = array(
		'shipping_first_name' => 'First name',
		'shipping_last_name'  => 'Last name',
		'shipping_company'    => 'Company (optional)',
		'shipping_address_1'  => 'Street address',
		'shipping_address_2'  => 'Apt, suite, unit (optional)',
		'shipping_city'       => 'City',
		'shipping_postcode'   => 'ZIP / Postal code',
	);
	foreach ( $ship_placeholders as $key => $ph ) {
		if ( isset( $fields['shipping'][ $key ] ) ) {
			$fields['shipping'][ $key ]['placeholder'] = $ph;
		}
	}

	$ship_half = array(
		'shipping_first_name',
		'shipping_last_name',
		'shipping_city',
		'shipping_postcode',
	);
	foreach ( $ship_half as $key ) {
		if ( isset( $fields['shipping'][ $key ] ) ) {
			$fields['shipping'][ $key ]['class'] = array( 'form-row-wide', 'j5-field-half' );
		}
	}

	if ( isset( $fields['shipping']['shipping_state'] ) ) {
		$fields['shipping']['shipping_state']['class'] = array( 'form-row-wide', 'j5-field-half' );
	}

	// ── Order notes placeholder ──
	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['placeholder'] = 'Special instructions for your order (optional)';
	}

	return $fields;
} );


/* ============================================================================
 *  SHIPPING SECTION NUMBER
 * ========================================================================== */
add_action( 'woocommerce_before_checkout_shipping_form', function () {
	echo '<div class="j5-checkout__section-header j5-checkout__section-header--shipping">';
	echo '<span class="j5-checkout__section-number">02</span>';
	echo '<h3 class="j5-checkout__section-title">Shipping Details</h3>';
	echo '</div>';
} );


/* ============================================================================
 *  COUPON — relocate into order summary area
 * ========================================================================== */
// Remove default coupon form from above checkout
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

// Re-add it inside the order review area
add_action( 'woocommerce_review_order_before_order_total', function () {
	if ( wc_coupons_enabled() ) {
		?>
		<tr class="j5-coupon-row">
			<td colspan="3">
				<div class="j5-coupon-form">
					<input type="text" name="coupon_code" class="j5-coupon-form__input" id="j5_coupon_code" placeholder="Coupon code" />
					<button type="button" class="j5-coupon-form__btn" id="j5_apply_coupon">Apply</button>
				</div>
			</td>
		</tr>
		<?php
	}
} );


/* ============================================================================
 *  INLINE COUPON JS — fires the WooCommerce AJAX coupon action
 * ========================================================================== */
add_action( 'wp_footer', function () {
	if ( ! is_checkout() ) {
		return;
	}
	?>
	<script>
	(function(){
		var btn = document.getElementById('j5_apply_coupon');
		if (!btn) return;
		btn.addEventListener('click', function(){
			var code = document.getElementById('j5_coupon_code').value.trim();
			if (!code) return;
			var form = document.querySelector('form.checkout');
			if (!form) return;
			// Create hidden input and trigger WC coupon
			var inp = document.createElement('input');
			inp.type = 'hidden';
			inp.name = 'coupon_code';
			inp.value = code;
			form.appendChild(inp);
			jQuery(document.body).trigger('applied_coupon', [code]);
			jQuery.ajax({
				type: 'POST',
				url: wc_checkout_params.ajax_url,
				data: {
					action: 'woocommerce_apply_coupon',
					security: wc_checkout_params.apply_coupon_nonce,
					coupon_code: code
				},
				success: function(result){
					jQuery('.woocommerce-error, .woocommerce-message, .woocommerce-info').remove();
					if (result) {
						var wrapper = document.querySelector('.j5-checkout__sidebar') || document.querySelector('.woocommerce-checkout');
						if (wrapper) wrapper.insertAdjacentHTML('afterbegin', result);
					}
					jQuery(document.body).trigger('update_checkout');
					document.getElementById('j5_coupon_code').value = '';
				}
			});
		});
	})();
	</script>
	<?php
} );


/* ============================================================================
 *  ASTRA OVERRIDES — prevent Astra from injecting its checkout markup
 * ========================================================================== */
add_filter( 'astra_woo_checkout_layout', function () {
	return 'default'; // Prevent Astra Modern/Distraction Free checkout hijack
} );

/* === J5-BATCH-7-PHP-START === */
/**
 * Batch 7 — Coupon form at top of billing
 * Removes WC default coupon form (and our previous in-review placement),
 * adds a custom coupon bar above billing details.
 */

/* Remove any prior coupon hooks */
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
remove_action( 'woocommerce_review_order_before_order_total', 'j5_render_sidebar_coupon_row' );

/* Render custom coupon bar immediately inside the checkout form top */
add_action( 'woocommerce_before_checkout_billing_form', function() {
    if ( ! wc_coupons_enabled() ) {
        return;
    }
    ?>
    <div class="j5-coupon-bar">
        <input type="text"
               id="j5_coupon_code_top"
               class="j5-coupon-bar__input"
               placeholder="Coupon code"
               autocomplete="off" />
        <button type="button"
                id="j5_apply_coupon_top"
                class="j5-coupon-bar__btn">Apply</button>
    </div>
    <?php
}, 5 );

/* Inline JS to apply the coupon via WC AJAX */
add_action( 'wp_footer', function() {
    if ( ! is_checkout() ) {
        return;
    }
    ?>
    <script>
    (function() {
        var btn = document.getElementById('j5_apply_coupon_top');
        if (!btn) return;
        btn.addEventListener('click', function() {
            var inp = document.getElementById('j5_coupon_code_top');
            var code = (inp && inp.value || '').trim();
            if (!code) { inp && inp.focus(); return; }
            if (typeof jQuery === 'undefined' || typeof wc_checkout_params === 'undefined') return;
            jQuery.ajax({
                type: 'POST',
                url: wc_checkout_params.wc_ajax_url
                    ? wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon')
                    : wc_checkout_params.ajax_url,
                data: wc_checkout_params.wc_ajax_url
                    ? { security: wc_checkout_params.apply_coupon_nonce, coupon_code: code }
                    : { action: 'woocommerce_apply_coupon',
                         security: wc_checkout_params.apply_coupon_nonce,
                         coupon_code: code },
                success: function(result) {
                    jQuery('.woocommerce-error, .woocommerce-message, .woocommerce-info').remove();
                    if (result) {
                        var formEl = document.querySelector('form.checkout');
                        if (formEl) formEl.insertAdjacentHTML('afterbegin', result);
                    }
                    jQuery(document.body).trigger('applied_coupon_in_checkout', [code]);
                    jQuery(document.body).trigger('update_checkout');
                    if (inp) inp.value = '';
                }
            });
        });
        /* Enter-key submits coupon */
        var inpField = document.getElementById('j5_coupon_code_top');
        if (inpField) {
            inpField.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    btn.click();
                }
            });
        }
    })();
    </script>
    <?php
} );
/* === J5-BATCH-7-PHP-END === */
