<?php
/**
 * J5 Product Acknowledgments
 * --------------------------
 * Reusable purchase acknowledgments (ITAR export restriction, age 18+,
 * medical-device certification, etc.). A buyer must affirm each required
 * acknowledgment attached to any product in their cart before the order can
 * be placed; the affirmation is recorded on the order as proof.
 *
 * Architecture (mirrors the fulfillment module):
 *   - Definitions (slug, title, body, required flag) are GLOBAL config,
 *     editable in wp-admin (Settings → J5 Acknowledgments). Stored in an
 *     option. Editing them does not conflict with the hub owning product data.
 *   - Attachment is PER-PRODUCT data: meta `_j5_acknowledgments`, a comma/space
 *     list of acknowledgment slugs. The hub publishes this; a manual WP-CLI /
 *     product-field fallback also works.
 *
 * Enforcement:
 *   - Product page: each attached acknowledgment renders as an informational
 *     notice near the cart (see single-product.php).
 *   - Checkout: the union of acknowledgments across all cart items renders as
 *     checkboxes; required ones block order placement until ticked.
 *   - Order: affirmed acknowledgments are stored in order meta and shown in
 *     the order admin.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option key holding the editable acknowledgment definitions.
 */
const J5_ACK_OPTION = 'j5_acknowledgments';

/**
 * Per-product meta key: comma/space list of acknowledgment slugs.
 */
const J5_ACK_META = '_j5_acknowledgments';

/**
 * Seed/default acknowledgments — used to initialize the option on first load.
 *
 * @return array slug => [ title, body, required ]
 */
function j5_ack_defaults() {
	return array(
		'itar' => array(
			'title'    => 'ITAR / Export-Controlled Item',
			'body'     => 'This item is subject to U.S. export control laws (ITAR/EAR). I certify that I am a U.S. person, that this item is for use within the United States, and that I will not export or re-export it without proper authorization.',
			'required' => true,
				'placement' => 'checkout',
		),
		'age18' => array(
			'title'    => 'Age Verification (18+)',
			'body'     => 'I certify that I am at least 18 years of age.',
			'required' => true,
				'placement' => 'checkout',
		),
		'special_order' => array(
			'title'    => 'Special Order — Extended Lead Time',
			'body'     => 'I understand this item is not held in stock and is ordered from the manufacturer specifically for me. Lead times are estimates provided by the manufacturer and are not guaranteed. I accept that this order is placed on my instruction and agree to the special-order terms.',
			'required' => true,
				'placement' => 'product',
		),
		'made_to_order' => array(
			'title'    => 'Made to Order — Built to Specification',
			'body'     => 'I understand this item is manufactured to the specification I have selected and production begins after my order is placed. Lead times are estimates and are not guaranteed. I accept that this order is placed on my instruction and agree to the made-to-order terms.',
			'required' => true,
				'placement' => 'product',
		),
	);
}

/**
 * Active acknowledgment definitions — editable via Settings → J5 Acknowledgments.
 * Reads the option, seeding from defaults the first time, and validates each
 * entry so a malformed option can never break checkout.
 *
 * @return array slug => [ title, body, required ]
 */
function j5_ack_definitions() {
	$stored = get_option( J5_ACK_OPTION, null );

	if ( ! is_array( $stored ) ) {
		return j5_ack_defaults();
	}
	if ( empty( $stored ) ) {
		// Explicitly emptied by the admin = no acknowledgments. Honor it.
		return array();
	}

	$clean = array();
	foreach ( $stored as $slug => $def ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug || ! is_array( $def ) ) {
			continue;
		}
		$placement = isset( $def['placement'] ) ? sanitize_key( $def['placement'] ) : '';
		$clean[ $slug ] = array(
			'title'     => isset( $def['title'] ) ? (string) $def['title'] : ucfirst( str_replace( array( '_', '-' ), ' ', $slug ) ),
			'body'      => isset( $def['body'] ) ? (string) $def['body'] : '',
			'required'  => ! empty( $def['required'] ),
			// Where the buyer affirms this. 'checkout' is the original
			// behavior and stays the default for definitions saved before this
			// field existed, so nothing changes for an acknowledgment nobody
			// has revisited.
			'placement' => in_array( $placement, array( 'checkout', 'product' ), true ) ? $placement : 'checkout',
		);
	}
	return $clean;
}

/**
 * Acknowledgment slugs attached to a product.
 *
 * @param WC_Product|int $product
 * @return array slugs
 */
function j5_ack_for_product( $product ) {
	if ( is_numeric( $product ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return array();
	}

	// Variations inherit the parent's acknowledgments.
	$id  = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
	$raw = (string) get_post_meta( $id, J5_ACK_META, true );

	$defs  = j5_ack_definitions();
	$slugs = array();

	// Product-attached acknowledgments (hub-published meta).
	if ( '' !== $raw ) {
		foreach ( preg_split( '/[\s,]+/', $raw ) as $slug ) {
			$slug = sanitize_key( $slug );
			if ( '' !== $slug && isset( $defs[ $slug ] ) ) {
				$slugs[] = $slug;
			}
		}
	}

	// Fulfillment-status-implied acknowledgment. A status such as
	// made_to_order promises a lead time rather than stock on hand; the buyer
	// is agreeing to wait, and that agreement is worth capturing without
	// having to remember to attach the acknowledgment to every such product by
	// hand. Resolved from the same parent ID, so variations inherit it exactly
	// as they inherit the product-attached list.
	//
	// Unknown or unset slugs are dropped by the isset() check, so a status
	// pointing at an acknowledgment that no longer exists degrades to no gate
	// rather than a fatal or an unaffirmable checkbox.
	foreach ( j5_ack_status_slugs( $id ) as $status_ack ) {
		if ( isset( $defs[ $status_ack ] ) ) {
			$slugs[] = $status_ack;
		}
	}

	return array_values( array_unique( $slugs ) );
}

/**
 * Acknowledgment slugs a product inherits from its FULFILLMENT STATUS, as
 * opposed to those attached directly via _j5_acknowledgments.
 *
 * The distinction matters for variable products. Fulfillment status is
 * parent-level meta, so a status of 'special' covers the whole product even
 * when individual variations differ. An acknowledgment attached directly
 * (ITAR, age 18+) is a property of the item itself and always applies; one
 * inherited from a lead-time status is a claim about availability, and that
 * claim is false for a variation sitting on the shelf.
 *
 * @param int $id Parent product ID.
 * @return array slugs
 */
function j5_ack_status_slugs( $id ) {
	if ( ! function_exists( 'j5_fulfillment_ack_slug' ) ) {
		return array();
	}
	$slug = j5_fulfillment_ack_slug( $id );

	return ( '' !== $slug ) ? array( $slug ) : array();
}

/**
 * Whether a specific variation is genuinely on the shelf, and therefore
 * exempt from acknowledgments inherited from a lead-time fulfillment status.
 *
 * Mirrors the three-state logic in inc/j5-variation-display.php: a backorder
 * (managed stock, qty <= 0, backorders allowed) is the special-order pipeline
 * and is NOT exempt. Only real stock is.
 *
 * @param int $variation_id
 * @return bool
 */
function j5_ack_variation_in_stock( $variation_id ) {
	$variation_id = absint( $variation_id );
	if ( ! $variation_id ) {
		return false;
	}
	$variation = wc_get_product( $variation_id );
	if ( ! $variation instanceof WC_Product ) {
		return false;
	}

	return ! $variation->is_on_backorder() && $variation->is_in_stock();
}

/**
 * Acknowledgment slugs that actually apply to a specific variation at a given
 * placement. Status-inherited slugs drop out when the variation is in stock.
 *
 * @param int    $product_id
 * @param int    $variation_id 0 for simple products / unknown selection.
 * @param string $placement
 * @return array slugs
 */
function j5_ack_for_variation_placed( $product_id, $variation_id, $placement ) {
	$slugs = j5_ack_for_product_placed( $product_id, $placement );
	if ( ! $variation_id || ! j5_ack_variation_in_stock( $variation_id ) ) {
		return $slugs;
	}

	$status = j5_ack_status_slugs( $product_id );

	return array_values( array_diff( $slugs, $status ) );
}

/**
 * The set of acknowledgment slugs required across everything in the cart.
 *
 * @return array slug => definition (only those present on cart items)
 */
function j5_ack_in_cart() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}
	$defs   = j5_ack_definitions();
	$result = array();
	foreach ( WC()->cart->get_cart() as $item ) {
		$pid = ! empty( $item['product_id'] ) ? $item['product_id'] : 0;
		if ( ! $pid ) {
			continue;
		}

		// Affirmations already captured on the product page for THIS item.
		$affirmed = j5_ack_cart_item_affirmed( $item );

		$vid = ! empty( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;

		// Status-inherited acknowledgments do not apply to a stocked variation.
		$exempt = ( $vid && j5_ack_variation_in_stock( $vid ) ) ? j5_ack_status_slugs( $pid ) : array();

		foreach ( j5_ack_for_product( $pid ) as $slug ) {
			if ( ! isset( $defs[ $slug ] ) || in_array( $slug, $exempt, true ) ) {
				continue;
			}

			// Backstop, not a second gate: a product-placement acknowledgment
			// that this item already carries was affirmed at add-to-cart, so
			// asking again at checkout would be redundant. It reappears only
			// for items that reached the cart without passing the product page
			// — ?add-to-cart= links, Order Again, restored session carts.
			if ( 'product' === $defs[ $slug ]['placement'] && isset( $affirmed[ $slug ] ) ) {
				continue;
			}

			$result[ $slug ] = $defs[ $slug ];
		}
	}
	return $result;
}

/**
 * Acknowledgment slugs on a product, filtered to one placement.
 *
 * @param WC_Product|int $product
 * @param string         $placement 'checkout' or 'product'.
 * @return array slugs
 */
function j5_ack_for_product_placed( $product, $placement ) {
	$defs = j5_ack_definitions();
	$out  = array();
	foreach ( j5_ack_for_product( $product ) as $slug ) {
		if ( isset( $defs[ $slug ] ) && $defs[ $slug ]['placement'] === $placement ) {
			$out[] = $slug;
		}
	}
	return $out;
}

/**
 * Cart-item key under which product-page affirmations travel.
 */
const J5_ACK_CART_KEY = 'j5_ack_affirmed';

/* =========================================================================
 * Product page: informational notices
 * ====================================================================== */

/**
 * Render acknowledgment notices for a product (informational, on the product
 * page). Called from single-product.php.
 *
 * @param WC_Product $product
 */
function j5_render_ack_notices( $product ) {
	// Only acknowledgments affirmed later (at checkout) render as passive
	// notices here. Product-placement ones render as a real gate inside the
	// add-to-cart form instead — see j5_render_ack_gate().
	$slugs = j5_ack_for_product_placed( $product, 'checkout' );
	if ( empty( $slugs ) ) {
		return;
	}
	$defs = j5_ack_definitions();

	echo '<div class="j5-ack-notices">';
	foreach ( $slugs as $slug ) {
		if ( ! isset( $defs[ $slug ] ) ) {
			continue;
		}
		$d = $defs[ $slug ];
		printf(
			'<div class="j5-ack-notice"><span class="j5-ack-badge">%1$s</span><div class="j5-ack-text"><strong>%2$s</strong><span>%3$s</span></div></div>',
			$d['required'] ? 'REQUIRED' : 'NOTICE',
			esc_html( $d['title'] ),
			esc_html( $d['body'] )
		);
	}
	echo '</div>';
}

/* =========================================================================
 * Product page: enforced gate (placement = 'product')
 *
 * Rendered INSIDE the add-to-cart form so it posts with the add-to-cart
 * request, validated server-side, then carried on the cart item and finally
 * onto the order line item. This captures the affirmation at the moment of
 * the decision and per item, rather than once per order at payment time.
 *
 * The checkout block still exists as a backstop: several paths reach the cart
 * without a product page (?add-to-cart= links, Order Again, restored session
 * carts), and those items must still be gated somewhere.
 * ====================================================================== */

/**
 * Render the product-page acknowledgment gate. MUST be called from inside the
 * add-to-cart <form>, or the checkboxes will not post and every add will fail
 * validation.
 *
 * @param WC_Product $product
 */
function j5_render_ack_gate( $product ) {
	$slugs = j5_ack_for_product_placed( $product, 'product' );
	if ( empty( $slugs ) ) {
		return;
	}
	$defs = j5_ack_definitions();

	// Status-inherited rows are conditional: they assert a lead time that does
	// not hold for a variation that is actually in stock. j5-shop.js hides them
	// when such a variation is selected; the server enforces the same rule in
	// j5_ack_for_variation_placed(), which is authoritative.
	$pid         = is_numeric( $product ) ? absint( $product ) : $product->get_id();
	$conditional = j5_ack_status_slugs( $pid );
	$is_variable = ! is_numeric( $product ) && $product->is_type( 'variable' );

	echo '<div class="j5-ack-gate">';
	foreach ( $slugs as $slug ) {
		if ( ! isset( $defs[ $slug ] ) ) {
			continue;
		}
		$d       = $defs[ $slug ];
		$id      = 'j5_ack_gate_' . $slug;
		$is_cond = in_array( $slug, $conditional, true );

		// On a variable product a conditional row starts hidden: until a
		// variation is chosen we cannot know whether the lead time applies,
		// and showing a gate that may vanish is worse than revealing one.
		$classes = 'j5-ack-gate-row';
		if ( $is_cond ) {
			$classes .= ' j5-ack-cond';
			if ( $is_variable ) {
				$classes .= ' j5-ack-cond-hidden';
			}
		}

		printf(
			'<label class="%1$s" for="%2$s" data-j5-ack-slug="%3$s"><input type="checkbox" name="j5_ack[%3$s]" id="%2$s" value="1" %4$s /><span class="j5-ack-gate-copy"><strong>%5$s</strong><span>%6$s</span></span></label>',
			esc_attr( $classes ),
			esc_attr( $id ),
			esc_attr( $slug ),
			$d['required'] ? 'data-j5-ack-required="1"' : '',
			esc_html( $d['title'] ),
			esc_html( $d['body'] )
		);
	}
	echo '</div>';
}

/**
 * Block add-to-cart when a required product-placement acknowledgment was not
 * affirmed. This is the authoritative gate; the JS button-disable is only
 * convenience.
 *
 * Adds from a shop archive (quick add, ?add-to-cart= link) post no checkbox,
 * so they are refused with a pointer to the product page rather than silently
 * bypassing the gate.
 */
add_filter( 'woocommerce_add_to_cart_validation', function ( $passed, $product_id, $quantity = 1, $variation_id = 0 ) {
	$slugs = j5_ack_for_variation_placed( $product_id, $variation_id, 'product' );
	if ( empty( $slugs ) ) {
		return $passed;
	}

	$defs   = j5_ack_definitions();
	$posted = isset( $_POST['j5_ack'] ) && is_array( $_POST['j5_ack'] ) ? wp_unslash( $_POST['j5_ack'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- add-to-cart is not nonce-protected by WC.

	foreach ( $slugs as $slug ) {
		if ( empty( $defs[ $slug ]['required'] ) ) {
			continue;
		}
		if ( empty( $posted[ $slug ] ) ) {
			wc_add_notice(
				sprintf(
					/* translators: 1: product title, 2: acknowledgment title */
					__( '%1$s requires an acknowledgment before it can be added: %2$s', 'astra-child' ),
					esc_html( get_the_title( $product_id ) ),
					esc_html( $defs[ $slug ]['title'] )
				),
				'error'
			);
			$passed = false;
		}
	}
	return $passed;
}, 10, 4 );

/**
 * Attach affirmations to the cart item so they survive to the order.
 *
 * Distinct affirmation sets also make WC treat the lines as separate cart
 * items, which is correct: an affirmed unit and an unaffirmed one are not
 * interchangeable.
 */
add_filter( 'woocommerce_add_cart_item_data', function ( $data, $product_id, $variation_id = 0 ) {
	$slugs = j5_ack_for_variation_placed( $product_id, $variation_id, 'product' );
	if ( empty( $slugs ) ) {
		return $data;
	}

	$defs   = j5_ack_definitions();
	$posted = isset( $_POST['j5_ack'] ) && is_array( $_POST['j5_ack'] ) ? wp_unslash( $_POST['j5_ack'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- add-to-cart is not nonce-protected by WC.
	$record = array();

	foreach ( $slugs as $slug ) {
		if ( empty( $posted[ $slug ] ) || ! isset( $defs[ $slug ] ) ) {
			continue;
		}
		$record[ $slug ] = array(
			'title'     => $defs[ $slug ]['title'],
			'timestamp' => current_time( 'mysql' ),
		);
	}

	if ( ! empty( $record ) ) {
		$data[ J5_ACK_CART_KEY ] = $record;
	}
	return $data;
}, 10, 3 );

/**
 * Affirmations already captured for a cart item.
 *
 * @param array $item Cart item.
 * @return array slug => [ title, timestamp ]
 */
function j5_ack_cart_item_affirmed( $item ) {
	return ( ! empty( $item[ J5_ACK_CART_KEY ] ) && is_array( $item[ J5_ACK_CART_KEY ] ) )
		? $item[ J5_ACK_CART_KEY ]
		: array();
}

/**
 * Show the affirmation on the cart / checkout line so the buyer can see what
 * they agreed to per item.
 */
add_filter( 'woocommerce_get_item_data', function ( $item_data, $item ) {
	foreach ( j5_ack_cart_item_affirmed( $item ) as $rec ) {
		$item_data[] = array(
			'key'     => $rec['title'],
			'value'   => __( 'Acknowledged', 'astra-child' ),
			'display' => '',
		);
	}
	return $item_data;
}, 10, 2 );

/**
 * Persist the affirmation onto the order line item — the per-item record.
 */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $line_item, $cart_item_key, $values ) {
	$affirmed = j5_ack_cart_item_affirmed( $values );
	if ( ! empty( $affirmed ) ) {
		$line_item->add_meta_data( '_j5_ack_affirmed', $affirmed, true );
	}
}, 10, 3 );

/* =========================================================================
 * Checkout: enforced checkboxes
 * ====================================================================== */

/**
 * Render acknowledgment checkboxes on the classic checkout, before the submit
 * button. Only shows acknowledgments actually present on cart items.
 */
add_action( 'woocommerce_review_order_before_submit', function () {
	$acks = j5_ack_in_cart();
	if ( empty( $acks ) ) {
		return;
	}

	echo '<div class="j5-ack-checkout">';
	echo '<div class="j5-ack-checkout-head">Required Acknowledgments</div>';
	foreach ( $acks as $slug => $d ) {
		$field_id = 'j5_ack_' . $slug;
		printf(
			'<p class="form-row j5-ack-row %1$s"><label class="j5-ack-label"><input type="checkbox" name="%2$s" id="%2$s" value="1" %3$s /> <span class="j5-ack-copy"><strong>%4$s</strong> — %5$s</span></label></p>',
			$d['required'] ? 'validate-required' : '',
			esc_attr( $field_id ),
			$d['required'] ? 'data-required="1"' : '',
			esc_html( $d['title'] ),
			esc_html( $d['body'] )
		);
	}
	echo '</div>';
}, 9 );

/**
 * Server-side enforcement: block order placement if any required
 * acknowledgment in the cart was not checked. This is the authoritative gate
 * (client JS is convenience only).
 */
add_action( 'woocommerce_checkout_process', function () {
	foreach ( j5_ack_in_cart() as $slug => $d ) {
		if ( empty( $d['required'] ) ) {
			continue;
		}
		if ( empty( $_POST[ 'j5_ack_' . $slug ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout nonce covers this request.
			wc_add_notice(
				sprintf(
					/* translators: %s: acknowledgment title */
					__( 'Please acknowledge: %s', 'astra-child' ),
					esc_html( $d['title'] )
				),
				'error'
			);
		}
	}
} );

/**
 * Record affirmed acknowledgments on the order as proof. Stored as order meta
 * (slug, title, timestamp) for each acknowledgment that applied to the cart.
 *
 * @param int $order_id
 */
add_action( 'woocommerce_checkout_update_order_meta', function ( $order_id ) {
	$acks = j5_ack_in_cart();
	if ( empty( $acks ) ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$record = array();
	foreach ( $acks as $slug => $d ) {
		$affirmed = ! empty( $_POST[ 'j5_ack_' . $slug ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout nonce covers this request.
		$record[] = array(
			'slug'      => $slug,
			'title'     => $d['title'],
			'required'  => (bool) $d['required'],
			'affirmed'  => $affirmed,
			'timestamp' => current_time( 'mysql' ),
		);
	}

	$order->update_meta_data( '_j5_acknowledgments_record', $record );
	$order->save();
} );

/**
 * Show the recorded acknowledgments in the order admin.
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	$record = $order->get_meta( '_j5_acknowledgments_record' );
	if ( empty( $record ) || ! is_array( $record ) ) {
		return;
	}
	echo '<div class="j5-ack-order-record" style="margin-top:12px;"><h4 style="margin:0 0 6px;">Acknowledgments</h4>';
	foreach ( $record as $r ) {
		$mark  = ! empty( $r['affirmed'] ) ? '&#10004;' : '&#10008;';
		$color = ! empty( $r['affirmed'] ) ? '#2e7d32' : '#c62828';
		printf(
			'<p style="margin:2px 0;color:%1$s;">%2$s %3$s <span style="color:#888;">(%4$s)</span></p>',
			esc_attr( $color ),
			$mark, // safe literal.
			esc_html( $r['title'] ),
			esc_html( $r['timestamp'] )
		);
	}
	echo '</div>';
} );

/* =========================================================================
 * Optional client-side convenience: disable Place Order until required boxes
 * are ticked. Server-side enforcement above is authoritative.
 * ====================================================================== */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	?>
	<script>
	( function () {
		function sync() {
			var boxes = document.querySelectorAll( '.j5-ack-row input[data-required="1"]' );
			if ( ! boxes.length ) { return; }
			var btn = document.getElementById( 'place_order' );
			if ( ! btn ) { return; }
			var all = true;
			boxes.forEach( function ( b ) { if ( ! b.checked ) { all = false; } } );
			btn.disabled = ! all;
			btn.classList.toggle( 'j5-ack-blocked', ! all );
		}
		document.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.closest && e.target.closest( '.j5-ack-row' ) ) { sync(); }
		} );
		// Re-sync after WC updates the order review (AJAX).
		if ( window.jQuery ) { jQuery( document.body ).on( 'updated_checkout', sync ); }
		// Variable products: reveal or hide the conditional (status-inherited)
		// rows based on the selected variation's real stock state. A variation
		// on the shelf is not a special order, so the lead-time acknowledgment
		// must not gate it.
		if ( window.jQuery ) {
			jQuery( function ( $ ) {
				$( '.variations_form' ).each( function () {
					var $form = $( this );
					$form.on( 'found_variation', function ( e, v ) {
						var state = ( v && v.j5_stock_state ) ? v.j5_stock_state : '';
						var stocked = ( 'in' === state );
						$form.find( '.j5-ack-cond' ).each( function () {
							this.classList.toggle( 'j5-ack-cond-hidden', stocked );
							if ( stocked ) {
								var cb = this.querySelector( 'input[type="checkbox"]' );
								if ( cb ) { cb.checked = false; }
							}
						} );
						sync();
					} );
					$form.on( 'reset_data hide_variation', function () {
						$form.find( '.j5-ack-cond' ).addClass( 'j5-ack-cond-hidden' );
						sync();
					} );
				} );
			} );
		}
		document.addEventListener( 'DOMContentLoaded', sync );
	} )();
	</script>
	<?php
}, 100 );

/**
 * Show per-item affirmations in the order admin. The meta key is underscored
 * (hidden from WC's default item-meta list) because the raw value is an array;
 * this renders it readably instead.
 */
add_action( 'woocommerce_after_order_itemmeta', function ( $item_id, $item ) {
	if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
		return;
	}
	$affirmed = $item->get_meta( '_j5_ack_affirmed', true );
	if ( empty( $affirmed ) || ! is_array( $affirmed ) ) {
		return;
	}
	echo '<div class="j5-ack-item-record" style="margin-top:6px;">';
	foreach ( $affirmed as $rec ) {
		printf(
			'<p style="margin:2px 0;color:#2e7d32;font-size:12px;">&#10004; %1$s <span style="color:#888;">(%2$s)</span></p>',
			esc_html( isset( $rec['title'] ) ? $rec['title'] : '' ),
			esc_html( isset( $rec['timestamp'] ) ? $rec['timestamp'] : '' )
		);
	}
	echo '</div>';
}, 10, 2 );

/**
 * Product page: disable Add to Cart until required gate boxes are ticked.
 * Convenience only — woocommerce_add_to_cart_validation is authoritative.
 */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	?>
	<script>
	( function () {
		function sync() {
			document.querySelectorAll( 'form.cart' ).forEach( function ( form ) {
				// Rows hidden because the selected variation is in stock are not
				// required — mirrors j5_ack_for_variation_placed() server-side.
				var rows = form.querySelectorAll( '.j5-ack-gate-row:not(.j5-ack-cond-hidden) input[data-j5-ack-required="1"]' );
				var gate = form.querySelector( '.j5-ack-gate' );
				if ( gate ) {
					var anyVisible = form.querySelectorAll( '.j5-ack-gate-row:not(.j5-ack-cond-hidden)' ).length;
					gate.style.display = anyVisible ? '' : 'none';
				}
				if ( ! rows.length ) { form.setAttribute( 'data-j5-ack-ok', '1' ); return; }
				var boxes = rows, ok = true;
				boxes.forEach( function ( b ) { if ( ! b.checked ) { ok = false; } } );
				form.querySelectorAll( '.j5-add-to-cart-btn, .single_add_to_cart_button' ).forEach( function ( btn ) {
					btn.classList.toggle( 'j5-ack-blocked', ! ok );
					btn.setAttribute( 'aria-disabled', ok ? 'false' : 'true' );
				} );
				form.setAttribute( 'data-j5-ack-ok', ok ? '1' : '0' );
			} );
		}
		document.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.closest && e.target.closest( '.j5-ack-gate' ) ) { sync(); }
		} );
		// Block submit rather than disabling the button: a disabled button on a
		// variable product reads as "the site is broken" when the real reason is
		// an unticked box further up the page.
		document.addEventListener( 'submit', function ( e ) {
			var form = e.target;
			if ( ! form || ! form.classList || ! form.classList.contains( 'cart' ) ) { return; }
			if ( '0' === form.getAttribute( 'data-j5-ack-ok' ) ) {
				e.preventDefault();
				var gate = form.querySelector( '.j5-ack-gate' );
				if ( gate ) {
					gate.classList.add( 'j5-ack-gate-flash' );
					gate.scrollIntoView( { behavior: 'smooth', block: 'center' } );
					setTimeout( function () { gate.classList.remove( 'j5-ack-gate-flash' ); }, 1200 );
				}
			}
		}, true );
		document.addEventListener( 'DOMContentLoaded', sync );
	} )();
	</script>
	<?php
}, 100 );
