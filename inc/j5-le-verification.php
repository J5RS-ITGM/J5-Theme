<?php
/**
 * J5 LE-Only Purchase Verification
 *
 * Any order containing a Law Enforcement Only product (j5_is_le_only(): the
 * DP-pushed _j5_le_only meta or an le-only tag) requires an APPROVED
 * Law Enforcement or Licensed Security ID claim, using the existing
 * j5-verification plugin's claim pipeline:
 *
 *   ID upload  ->  j5v_claim (private CPT)  ->  pushed to the DP review queue
 *   DP decision (or wp-admin ID Review)  ->  _j5v_status on the claim
 *
 * Flow:
 *   - Verified customer (approved LE/Security claim): checks out normally.
 *   - Unverified: must have an account (registration forced at checkout) and
 *     uploads an ID on the checkout page. The order is placed, payment is
 *     taken, and the order goes ON HOLD. The claim goes to the DP.
 *       approved -> held orders move to Processing automatically
 *       rejected -> orders stay On Hold, flagged, and the shop is emailed to
 *                   cancel and refund
 *   - Already has a pending LE claim: order placed and held against it.
 *
 * Requires the j5-verification plugin (J5V_ID_Queue, J5V_ID_Store,
 * J5V_Portal). If it's missing, LE-only checkout is blocked rather than
 * silently allowed.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Claim types that unlock LE-only purchases. Must match the upload options. */
const J5LE_ALLOWED_TYPES = array( 'Law Enforcement', 'Licensed Security' );

function j5le_available() {
	return class_exists( 'J5V_ID_Queue' ) && class_exists( 'J5V_ID_Store' ) && class_exists( 'J5V_Portal' );
}

/** Does the current cart contain an LE-only product? */
function j5le_cart_has_le_only() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! function_exists( 'j5_is_le_only' ) ) return false;
	foreach ( WC()->cart->get_cart() as $item ) {
		$p = wc_get_product( $item['product_id'] );
		if ( $p && j5_is_le_only( $p ) ) return true;
	}
	return false;
}

/** Order contains an LE-only product? */
function j5le_order_has_le_only( $order ) {
	if ( ! function_exists( 'j5_is_le_only' ) ) return false;
	foreach ( $order->get_items() as $item ) {
		$p = wc_get_product( $item->get_product_id() );
		if ( $p && j5_is_le_only( $p ) ) return true;
	}
	return false;
}

/**
 * The user's LE verification state across ALL their claims (a separate
 * approved Military discount claim must not hide an LE claim, or vice versa).
 * @return array{state:string, claim:int}  state = verified | pending | none
 */
function j5le_user_state( $user_id ) {
	if ( ! $user_id || ! j5le_available() ) return array( 'state' => 'none', 'claim' => 0 );
	$claims = get_posts( array(
		'post_type' => J5V_ID_Queue::CPT, 'post_status' => 'private', 'numberposts' => 20,
		'meta_key' => '_j5v_user', 'meta_value' => $user_id, 'orderby' => 'ID', 'order' => 'DESC', 'fields' => 'ids',
	) );
	$pending = 0;
	foreach ( $claims as $cid ) {
		if ( ! in_array( get_post_meta( $cid, '_j5v_type', true ), J5LE_ALLOWED_TYPES, true ) ) continue;
		$status = get_post_meta( $cid, '_j5v_status', true );
		if ( 'approved' === $status ) return array( 'state' => 'verified', 'claim' => $cid );
		if ( 'pending' === $status && ! $pending ) $pending = $cid;
	}
	return $pending ? array( 'state' => 'pending', 'claim' => $pending ) : array( 'state' => 'none', 'claim' => 0 );
}

/* ── Checkout: force an account when LE-only items are in the cart ───────── */

add_filter( 'woocommerce_checkout_registration_required', function ( $required ) {
	if ( ! j5le_cart_has_le_only() || is_user_logged_in() ) return $required;
	// The email already has an account: don't make them log in. The order is
	// linked to that account after it's placed (see order_processed below) and
	// they upload an ID like anyone else. Guests never inherit the account's
	// verified status, since we can't confirm who's typing the email.
	$email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
	if ( $email && email_exists( $email ) ) return $required;
	return true;
} );
add_filter( 'woocommerce_checkout_registration_enabled', function ( $enabled ) {
	return j5le_cart_has_le_only() ? true : $enabled;
} );

/* ── Checkout: verification panel ─────────────────────────────────────────── */

add_action( 'woocommerce_checkout_after_customer_details', function () {
	if ( ! j5le_cart_has_le_only() ) return;
	$st      = j5le_user_state( get_current_user_id() );
	$upload  = WC()->session ? WC()->session->get( 'j5le_upload' ) : null;
	$nonce   = wp_create_nonce( 'j5le_upload' );
	?>
	<div class="j5le-panel" id="j5le-panel">
		<div class="j5le-panel__title">Law enforcement verification</div>
		<?php if ( ! j5le_available() ) : ?>
			<p class="j5le-panel__msg is-error">Online ordering for law-enforcement-only items is temporarily unavailable. Please <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">contact us</a> to place this order.</p>
		<?php elseif ( 'verified' === $st['state'] ) : ?>
			<p class="j5le-panel__msg is-ok">✓ Your account is verified for law-enforcement-only items.</p>
		<?php elseif ( 'pending' === $st['state'] ) : ?>
			<p class="j5le-panel__msg">Your ID is already under review. You can place this order now; it will be held and released as soon as your verification is approved (typically 1–2 business days).</p>
		<?php else : ?>
			<p class="j5le-panel__hint">Your cart includes items sold only to law enforcement and licensed security professionals. Upload a photo of your agency ID or security license. You can place your order right away; it will be held until we verify your ID, typically within 1–2 business days. Your document is stored securely and deleted after review.</p>
			<?php if ( ! is_user_logged_in() ) : ?>
				<p class="j5le-panel__hint">Already verified with us? <a href="#" class="showlogin">Log in</a> and you won't need to upload again.</p>
			<?php endif; ?>
			<div class="j5le-panel__row">
				<select id="j5le-type" aria-label="I am">
					<?php foreach ( J5LE_ALLOWED_TYPES as $t ) : ?>
						<option value="<?php echo esc_attr( $t ); ?>"<?php selected( $upload['type'] ?? '', $t ); ?>><?php echo esc_html( 'Law Enforcement' === $t ? 'Law enforcement officer' : 'Licensed security professional' ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="file" id="j5le-file" accept=".jpg,.jpeg,.png,.webp,.pdf">
				<button type="button" class="button" id="j5le-send" data-nonce="<?php echo esc_attr( $nonce ); ?>">Upload ID</button>
			</div>
			<p class="j5le-panel__msg<?php echo $upload ? ' is-ok' : ''; ?>" id="j5le-msg" role="status"><?php echo $upload ? '✓ ID uploaded. Place your order to submit it for review.' : ''; ?></p>
		<?php endif; ?>
	</div>
	<?php
} );

/** Upload endpoint (works for guests creating an account at checkout). */
add_action( 'wp_ajax_j5le_upload', 'j5le_ajax_upload' );
add_action( 'wp_ajax_nopriv_j5le_upload', 'j5le_ajax_upload' );
function j5le_ajax_upload() {
	if ( ! check_ajax_referer( 'j5le_upload', 'nonce', false ) ) wp_send_json_error( array( 'msg' => 'Session expired. Refresh the page and try again.' ) );
	if ( ! j5le_available() || ! WC()->session ) wp_send_json_error( array( 'msg' => 'Verification is unavailable right now. Please contact us.' ) );
	$type = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
	if ( ! in_array( $type, J5LE_ALLOWED_TYPES, true ) ) wp_send_json_error( array( 'msg' => 'Choose who you are.' ) );
	if ( empty( $_FILES['file']['tmp_name'] ) ) wp_send_json_error( array( 'msg' => 'Choose a file to upload.' ) );

	$key = 'j5le_up_' . md5( WC()->session->get_customer_id() . ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	if ( (int) get_transient( $key ) >= 5 ) wp_send_json_error( array( 'msg' => 'Too many uploads. Wait an hour or contact us.' ) );

	$stored = J5V_ID_Store::store( $_FILES['file']['tmp_name'], $_FILES['file']['name'] ?? '' );
	if ( is_wp_error( $stored ) ) wp_send_json_error( array( 'msg' => $stored->get_error_message() ) );

	$prev = WC()->session->get( 'j5le_upload' );
	if ( ! empty( $prev['file'] ) ) J5V_ID_Store::delete( $prev['file'] );
	if ( ! WC()->session->has_session() ) WC()->session->set_customer_session_cookie( true );
	WC()->session->set( 'j5le_upload', array( 'file' => $stored['file'], 'ext' => $stored['ext'], 'type' => $type ) );
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	wp_send_json_success( array( 'msg' => '✓ ID uploaded. Place your order to submit it for review.' ) );
}

/** Block placing the order with no verification path. */
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( ! j5le_cart_has_le_only() ) return;
	if ( ! j5le_available() ) {
		$errors->add( 'j5le', 'Law-enforcement-only items can\'t be ordered online right now. Please contact us.' );
		return;
	}
	$st = j5le_user_state( get_current_user_id() );
	if ( 'none' === $st['state'] && empty( WC()->session->get( 'j5le_upload' ) ) ) {
		$errors->add( 'j5le', '<strong>ID verification required.</strong> Upload your agency ID or security license in the Law enforcement verification box, then place your order.' );
	}
}, 20, 2 );

/* ── Order created: attach verification, create claim, push to DP ────────── */

add_action( 'woocommerce_checkout_order_processed', function ( $order_id, $posted, $order ) {
	if ( ! j5le_order_has_le_only( $order ) || ! j5le_available() ) return;
	$uid = (int) $order->get_customer_id();
	$guest = ! is_user_logged_in();
	if ( ! $uid && ( $u = get_user_by( 'email', $order->get_billing_email() ) ) ) {
		// Guest checkout with an email that has an account: file the order (and the ID claim) under it.
		$uid = (int) $u->ID;
		$order->set_customer_id( $uid );
		$order->add_order_note( 'Placed as a guest with the email of account #' . $uid . '; order linked to that account.' );
		$order->save();
	}
	// A guest never inherits the account's verified/pending status: they upload an ID.
	$st = $guest ? array( 'state' => 'none', 'claim' => 0 ) : j5le_user_state( $uid );

	if ( 'verified' === $st['state'] ) {
		$order->update_meta_data( '_j5le_status', 'verified' );
		$order->update_meta_data( '_j5le_claim', $st['claim'] );
		$order->add_order_note( 'LE verification: customer already verified (claim #' . $st['claim'] . ').' );
		$order->save();
		return;
	}

	$claim = $st['claim'];
	if ( ! $claim ) {
		$up = WC()->session ? WC()->session->get( 'j5le_upload' ) : null;
		if ( empty( $up['file'] ) || ! $uid ) {
			// Validation should make this impossible; never ship unverified.
			$order->update_meta_data( '_j5le_status', 'missing' );
			$order->add_order_note( '⚠ LE verification: no ID on file. Do not ship. Contact the customer for verification.' );
			$order->save();
			return;
		}
		$claim = J5V_ID_Queue::create_claim( $uid, $up['type'], array( 'file' => $up['file'] ) );
		if ( is_wp_error( $claim ) ) {
			$order->update_meta_data( '_j5le_status', 'missing' );
			$order->add_order_note( '⚠ LE verification: could not create the ID claim (' . $claim->get_error_message() . '). Do not ship.' );
			$order->save();
			return;
		}
		update_post_meta( $claim, '_j5le_purpose', 'le_purchase' );
		WC()->session->__unset( 'j5le_upload' );
	}

	$orders   = (array) get_post_meta( $claim, '_j5le_orders', true );
	$orders[] = (int) $order_id;
	update_post_meta( $claim, '_j5le_orders', array_values( array_unique( array_filter( $orders ) ) ) );

	$order->update_meta_data( '_j5le_status', 'pending' );
	$order->update_meta_data( '_j5le_claim', $claim );
	$order->add_order_note( 'LE verification pending (claim #' . $claim . '). Order will stay On Hold until the ID is approved. Do not ship.' );
	$order->save();

	J5V_Portal::queue_push( $claim ); // to the DP review queue (retried by cron if the DP is down)
}, 20, 3 );

/** Paid orders awaiting verification go On Hold instead of Processing. */
add_filter( 'woocommerce_payment_complete_order_status', function ( $status, $order_id, $order = null ) {
	$order = $order ?: wc_get_order( $order_id );
	if ( $order && in_array( $order->get_meta( '_j5le_status' ), array( 'pending', 'missing' ), true ) ) return 'on-hold';
	return $status;
}, 20, 3 );

/** Belt and braces: if anything moves a held order to Processing, pull it back. */
add_action( 'woocommerce_order_status_processing', function ( $order_id, $order ) {
	if ( in_array( $order->get_meta( '_j5le_status' ), array( 'pending', 'missing' ), true ) ) {
		$order->update_status( 'on-hold', 'Held: LE ID verification not yet approved.' );
	}
}, 5, 2 );

/* ── Decision (DP callback or wp-admin ID Review) releases or flags orders ─ */

add_action( 'updated_post_meta', 'j5le_on_claim_status', 10, 4 );
add_action( 'added_post_meta', 'j5le_on_claim_status', 10, 4 );
function j5le_on_claim_status( $meta_id, $claim_id, $key, $value ) {
	if ( '_j5v_status' !== $key || ! j5le_available() || get_post_type( $claim_id ) !== J5V_ID_Queue::CPT ) return;
	$orders = (array) get_post_meta( $claim_id, '_j5le_orders', true );
	// Approval of ANY allowed-type claim also releases the user's other held orders.
	if ( 'approved' === $value && in_array( get_post_meta( $claim_id, '_j5v_type', true ), J5LE_ALLOWED_TYPES, true ) ) {
		$uid = (int) get_post_meta( $claim_id, '_j5v_user', true );
		if ( $uid ) {
			$held = wc_get_orders( array( 'customer_id' => $uid, 'status' => 'on-hold', 'limit' => 50, 'return' => 'ids',
				'meta_query' => array( array( 'key' => '_j5le_status', 'value' => 'pending' ) ) ) );
			$orders = array_merge( $orders, $held );
		}
	}
	foreach ( array_unique( array_filter( array_map( 'intval', $orders ) ) ) as $oid ) {
		$order = wc_get_order( $oid );
		if ( ! $order || 'pending' !== $order->get_meta( '_j5le_status' ) ) continue;
		if ( 'approved' === $value ) {
			$order->update_meta_data( '_j5le_status', 'verified' );
			$order->save();
			// On Hold isn't a WC "paid" status, so check the payment record itself.
			$paid = $order->get_date_paid() || $order->get_transaction_id();
			if ( $order->has_status( 'on-hold' ) && $paid ) {
				$order->update_status( 'processing', 'LE ID verified (claim #' . $claim_id . '). Released for fulfillment.' );
			} else {
				$order->add_order_note( 'LE ID verified (claim #' . $claim_id . '). Order can be released once payment is confirmed.' );
			}
		} elseif ( 'rejected' === $value ) {
			$order->update_meta_data( '_j5le_status', 'rejected' );
			$order->add_order_note( '⚠ LE ID verification REJECTED (claim #' . $claim_id . '). Do not ship. Cancel and refund this order.' );
			$order->save();
			wp_mail(
				get_option( 'admin_email' ),
				'Action needed: LE verification rejected for order #' . $order->get_order_number(),
				"The ID submitted for order #{$order->get_order_number()} was rejected.\n\nThe order is On Hold. Cancel and refund it:\n" . $order->get_edit_order_url()
			);
		}
	}
}

/* ── Customer messaging ───────────────────────────────────────────────────── */

add_action( 'woocommerce_email_before_order_table', function ( $order, $sent_to_admin, $plain, $email ) {
	if ( $sent_to_admin || ! $order || 'pending' !== $order->get_meta( '_j5le_status' ) ) return;
	$msg = 'Your order includes items sold only to law enforcement and licensed security professionals. It is on hold while we verify your ID, typically within 1–2 business days. We\'ll email you when it ships.';
	echo $plain ? "\n" . $msg . "\n\n" : '<p style="margin:0 0 16px;padding:12px 14px;border-left:4px solid #C41E24;background:#f6f6f6">' . esc_html( $msg ) . '</p>';
}, 5, 4 );

// Thank-you page status for held LE orders is rendered by woocommerce/checkout/thankyou.php.

add_filter( 'j5_le_only_body', function ( $body ) {
	return 'Sold to law enforcement and licensed security professionals only. ID verification is required at checkout. <a href="' . esc_url( home_url( '/contact-us/' ) ) . '">Questions?</a>';
} );

/* ── Front-end assets (checkout only) ─────────────────────────────────────── */

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || ! j5le_cart_has_le_only() ) return;
	?>
	<style>
	.j5le-panel{margin:24px 0;padding:18px 20px;border:1px solid #2a2a2a;border-left:3px solid #C41E24;background:#161616;border-radius:2px}
	.j5le-panel__title{font-family:'Bebas Neue',Impact,sans-serif;letter-spacing:.08em;font-size:20px;color:#fff;margin-bottom:6px}
	.j5le-panel__hint,.j5le-panel__msg{color:#b0b0b0;font-size:15px;margin:0 0 10px}
	.j5le-panel__msg.is-ok{color:#7fd17f}.j5le-panel__msg.is-error{color:#ff8a80}
	.j5le-panel__row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
	.j5le-panel__row select,.j5le-panel__row input[type=file]{max-width:100%}
	</style>
	<script>
	(function(){
	  document.addEventListener('click', function(e){
	    var b = e.target.closest('#j5le-send'); if(!b) return;
	    var f = document.getElementById('j5le-file'), m = document.getElementById('j5le-msg');
	    if(!f.files.length){ m.className='j5le-panel__msg is-error'; m.textContent='Choose a file first.'; return; }
	    var fd = new FormData();
	    fd.append('action','j5le_upload'); fd.append('nonce', b.dataset.nonce);
	    fd.append('type', document.getElementById('j5le-type').value); fd.append('file', f.files[0]);
	    b.disabled = true; m.className='j5le-panel__msg'; m.textContent='Uploading…';
	    fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {method:'POST', body:fd, credentials:'same-origin'})
	      .then(function(r){ return r.json(); })
	      .then(function(j){ m.className='j5le-panel__msg ' + (j.success ? 'is-ok' : 'is-error'); m.textContent=(j.data && j.data.msg) || 'Upload failed.'; })
	      .catch(function(){ m.className='j5le-panel__msg is-error'; m.textContent='Upload failed. Check your connection and try again.'; })
	      .finally(function(){ b.disabled = false; });
	  });
	})();
	</script>
	<?php
}, 50 );

/* ── DP push: tell the reviewer this claim is holding an order ───────────── */
// Needs the j5v_portal_record filter (added to j5-verification 0.5.0). The DP
// accepts extra record fields; it stores claim_type today and can surface
// purpose / orders once its review screen reads them.
add_filter( 'j5v_portal_record', function ( $record, $claim_id ) {
	$orders = array_filter( array_map( 'intval', (array) get_post_meta( $claim_id, '_j5le_orders', true ) ) );
	if ( ! $orders && 'le_purchase' !== get_post_meta( $claim_id, '_j5le_purpose', true ) ) return $record;
	$record['purpose'] = 'le_purchase';
	$record['orders']  = array();
	foreach ( $orders as $oid ) {
		$o = wc_get_order( $oid );
		if ( ! $o ) continue;
		$items = array();
		foreach ( $o->get_items() as $it ) $items[] = $it->get_name() . ' x' . $it->get_quantity();
		$record['orders'][] = array(
			'wp_order_id' => $oid,
			'number'      => $o->get_order_number(),
			'total'       => $o->get_total(),
			'items'       => $items,
			'ship_to'     => trim( $o->get_shipping_city() . ', ' . $o->get_shipping_state() ),
		);
	}
	// Plain list too: the DP review card reads order_numbers for the "Order hold" banner.
	$record['order_numbers'] = array_values( wp_list_pluck( $record['orders'], 'number' ) );
	return $record;
}, 10, 2 );
