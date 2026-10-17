<?php
/**
 * J5 ID decision emails (WooCommerce emails)
 *
 *   J5 — ID approved   (j5_id_approved)
 *   J5 — ID rejected   (j5_id_rejected)
 *
 * Fired when a j5-verification claim's _j5v_status changes to approved /
 * rejected — from the DP decision callback or the wp-admin ID Review screen.
 * Each email adapts to what the claim was for:
 *   - LE-only order(s) held for verification (claim meta _j5le_orders)
 *   - the Military & First Responder discount (coupon code issued on approval)
 *
 * Both are listed in WooCommerce > Settings > Emails (enable / subject /
 * heading). They send at the end of the request so the plugin has already
 * issued the coupon and the LE module has already released the orders.
 *
 * Rejection reason: if the DP decision payload includes `reason`,
 * j5-verification >= 0.5.1 stores it as _j5v_reason and it's shown here.
 * The plugin's plain-text coupon email is suppressed via j5v_send_coupon_email (>= 0.5.1).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Context: everything an email needs about a claim ─────────────────────── */

function j5_id_email_context( $claim_id ) {
	$uid  = (int) get_post_meta( $claim_id, '_j5v_user', true );
	$user = $uid ? get_userdata( $uid ) : null;
	$ctx  = array(
		'claim_id'  => (int) $claim_id,
		'email'     => $user ? $user->user_email : '',
		'first'     => $user ? ( $user->first_name ?: $user->display_name ) : '',
		'type'      => (string) get_post_meta( $claim_id, '_j5v_type', true ),
		'code'      => (string) get_post_meta( $claim_id, '_j5v_coupon_code', true ),
		'terms'     => class_exists( 'J5V_Discount' ) ? J5V_Discount::terms_line() : '',
		'reason'    => (string) get_post_meta( $claim_id, '_j5v_reason', true ),
		'orders'    => array(),
		'billing'   => array(),
		'account'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ),
		'discount'  => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'service-discount' ) : home_url( '/my-account/service-discount/' ),
		'shop'      => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
	);
	foreach ( array_filter( array_map( 'intval', (array) get_post_meta( $claim_id, '_j5le_orders', true ) ) ) as $oid ) {
		$o = function_exists( 'wc_get_order' ) ? wc_get_order( $oid ) : null;
		if ( ! $o ) continue;
		$items = array();
		foreach ( $o->get_items() as $it ) $items[] = array( 'name' => $it->get_name(), 'qty' => $it->get_quantity() );
		$ctx['billing'][] = $o->get_billing_email();
		$ctx['orders'][] = array(
			'number' => $o->get_order_number(),
			'total'  => html_entity_decode( wp_strip_all_tags( wc_price( $o->get_total(), array( 'currency' => $o->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ),
			'items'  => $items,
			'url'    => $o->get_view_order_url(),
			'released' => $o->has_status( array( 'processing', 'completed' ) ),
		);
	}
	return $ctx;
}

/* ── Rendering (self-contained, inline-styled, email-client safe) ─────────── */

function j5_id_email_html( $kind, $ctx, $heading = '' ) {
	$red = '#C41E24'; $gold = '#B8963F'; $ink = '#141414'; $dim = '#5f5f5f'; $line = '#e6e6e6';
	$approved  = ( 'approved' === $kind );
	$has_order = ! empty( $ctx['orders'] );
	$has_code  = $approved && '' !== $ctx['code'];
	$e = function ( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); };
	$btn = function ( $href, $label, $primary = true ) use ( $red, $ink, $e ) {
		return '<a href="' . $e( $href ) . '" style="display:inline-block;padding:13px 26px;background:' . ( $primary ? $red : '#ffffff' ) . ';color:' . ( $primary ? '#ffffff' : $ink ) . ';border:2px solid ' . ( $primary ? $red : $ink ) . ';border-radius:2px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;text-decoration:none">' . $e( $label ) . '</a>';
	};
	$p = function ( $html ) use ( $ink ) { return '<p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:' . $ink . '">' . $html . '</p>'; };
	$hi = $ctx['first'] ? 'Hi ' . $e( $ctx['first'] ) . ',' : 'Hi there,';

	if ( ! $heading ) {
		$heading = $approved ? ( $has_order ? 'You\'re verified. Your order is moving.' : 'You\'re verified.' ) : 'We couldn\'t verify your ID';
	}
	$badge_bg = $approved ? '#2E8B57' : $red;
	$badge    = $approved ? '&#10003;' : '!';

	$body = $p( $hi );

	if ( $approved ) {
		$what = $ctx['type'] ? ' as <strong>' . $e( $ctx['type'] ) . '</strong>' : '';
		$body .= $p( 'Thanks for your patience. We reviewed your ID and your account is now verified' . $what . '.' );
		if ( $has_order ) {
			$n = count( $ctx['orders'] );
			$all = ! in_array( false, wp_list_pluck( $ctx['orders'], 'released' ), true );
			if ( $all ) {
				$body .= $p( $n > 1 ? 'Your orders below have been released and are being prepared now. You\'ll get tracking by email as soon as they ship.' : 'Your order has been released and is being prepared now. You\'ll get tracking by email as soon as it ships.' );
			} else {
				$body .= $p( 'We\'ll release your order as soon as payment is confirmed. If your payment didn\'t go through, you can pay from your account or give us a call.' );
			}
		}
		if ( $has_code ) {
			$body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;border:2px dashed ' . $gold . ';border-radius:2px;background:#fbf8ef"><tr><td style="padding:20px;text-align:center;font-family:Arial,Helvetica,sans-serif">'
				. '<div style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:' . $dim . ';margin-bottom:6px">Your personal service discount code</div>'
				. '<div style="font-family:\'Courier New\',Courier,monospace;font-size:26px;font-weight:700;letter-spacing:.08em;color:' . $ink . '">' . $e( $ctx['code'] ) . '</div>'
				. ( $ctx['terms'] ? '<div style="font-size:13px;color:' . $dim . ';margin-top:8px;line-height:1.5">' . $e( ucfirst( $ctx['terms'] ) ) . '.</div>' : '' )
				. '</td></tr></table>';
			$body .= $p( 'Use it at checkout on any order. It\'s tied to ' . $e( $ctx['email'] ) . ' and it\'s yours to keep. You can always find it under <a href="' . $e( $ctx['discount'] ) . '" style="color:' . $red . '">Service Discount</a> in your account.' );
		}
		$le_type = defined( 'J5LE_ALLOWED_TYPES' ) ? in_array( $ctx['type'], J5LE_ALLOWED_TYPES, true ) : in_array( $ctx['type'], array( 'Law Enforcement', 'Licensed Security' ), true );
		if ( ! $has_order && ! $has_code ) {
			$body .= $p( $le_type
				? 'You can now purchase items sold only to law enforcement and licensed security professionals. You won\'t need to verify again for future orders.'
				: 'Your verification is on file with your account. You won\'t need to submit it again.' );
		} elseif ( $has_order ) {
			$body .= $p( 'You won\'t need to verify again for future orders of law-enforcement-only items.' );
		}
	} else {
		if ( $has_order ) {
			$n = count( $ctx['orders'] );
			$body .= $p( 'We weren\'t able to verify the ID submitted with your order. Because ' . ( $n > 1 ? 'these orders include' : 'this order includes' ) . ' items we can only sell to verified law enforcement and licensed security professionals, ' . ( $n > 1 ? 'they\'re' : 'it\'s' ) . ' being <strong>canceled and refunded in full</strong> to your original payment method. Refunds usually post within 5 to 10 business days, depending on your bank.' );
		} else {
			$body .= $p( 'We weren\'t able to approve your service discount request with the document you submitted.' );
		}
		if ( $ctx['reason'] ) {
			$body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 20px;border-left:4px solid ' . $red . ';background:#f7f7f7"><tr><td style="padding:14px 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:' . $ink . '">'
				. '<div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:' . $dim . ';margin-bottom:4px">Reason</div>' . nl2br( $e( $ctx['reason'] ) ) . '</td></tr></table>';
		}
		// With a specific reason, skip the generic "blurry photo" explanation.
		$next = $has_order
			? 'If you believe this was a mistake, reply to this email or call us and we\'ll take another look.'
			: 'You\'re welcome to submit a new document from your account.';
		$body .= $p( $ctx['reason'] ? $next : 'This usually happens when the photo is blurry, cropped, or doesn\'t clearly show your name and agency or license. ' . $next );
	}

	// Privacy line. Accurate: the DP deletes the ID file the moment a decision is
	// recorded, and WP already dropped its copy when the DP accepted the push.
	$privacy = '<p style="margin:22px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:' . $dim . '">For your privacy, the ID you uploaded was deleted as soon as we finished reviewing it.</p>';

	// Order summary cards
	$orders_html = '';
	foreach ( $ctx['orders'] as $o ) {
		$lines = '';
		foreach ( $o['items'] as $it ) {
			$lines .= '<tr><td style="padding:6px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:' . $ink . '">' . $e( $it['name'] ) . '</td><td style="padding:6px 0;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:' . $dim . ';white-space:nowrap">&times; ' . (int) $it['qty'] . '</td></tr>';
		}
		$status = $approved ? ( $o['released'] ? '<span style="color:#2E8B57;font-weight:700">Released for fulfillment</span>' : '<span style="color:#B8963F;font-weight:700">Awaiting payment</span>' ) : '<span style="color:' . $red . ';font-weight:700">Canceled &amp; Refunded</span>';
		$orders_html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border:1px solid ' . $line . ';border-radius:2px"><tr><td style="padding:16px 18px">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
			. '<td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:' . $ink . '">Order #' . $e( $o['number'] ) . '</td>'
			. '<td style="text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:13px">' . $status . '</td></tr></table>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;border-top:1px solid ' . $line . '">' . $lines . '</table>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid ' . $line . ';margin-top:4px"><tr><td style="padding-top:8px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:' . $dim . '">Total</td><td style="padding-top:8px;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:' . $ink . '">' . $e( $o['total'] ) . '</td></tr></table>'
			. '</td></tr></table>';
	}
	$body .= $orders_html;

	// CTA
	if ( $approved ) {
		$cta = $has_order ? $btn( $ctx['orders'][0]['url'], count( $ctx['orders'] ) > 1 ? 'View my orders' : 'View my order' ) : $btn( $ctx['shop'], 'Shop now' );
	} else {
		$cta = $has_order ? $btn( 'mailto:info@j5rescue.com?subject=' . rawurlencode( 'ID verification - order #' . $ctx['orders'][0]['number'] ), 'Contact us' ) : $btn( $ctx['discount'], 'Submit a new document' );
	}
	$body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 4px"><tr><td>' . $cta . '</td></tr></table>';
	$body .= $privacy;

	return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e( $heading ) . '</title></head>'
		. '<body style="margin:0;padding:0;background:#eeeeee">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eeeeee"><tr><td align="center" style="padding:28px 12px">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:2px;overflow:hidden">'
		// header bar
		. '<tr><td style="background:#0B0B0B;padding:20px 28px;border-bottom:3px solid ' . $red . '">'
		. '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
		. '<td style="border:2px solid ' . $gold . ';padding:4px 9px;font-family:Impact,\'Arial Narrow\',Arial,sans-serif;font-size:20px;color:' . $gold . ';letter-spacing:.04em">J5</td>'
		. '<td style="padding-left:12px;font-family:Impact,\'Arial Narrow\',Arial,sans-serif;font-size:20px;letter-spacing:.06em;color:#ffffff;text-transform:uppercase">J5 Rescue Supply</td>'
		. '</tr></table></td></tr>'
		// hero
		. '<tr><td style="padding:32px 28px 8px">'
		. '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="width:44px;height:44px;border-radius:22px;background:' . $badge_bg . ';color:#ffffff;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;line-height:44px">' . $badge . '</td></tr></table>'
		. '<h1 style="margin:16px 0 18px;font-family:Impact,\'Arial Narrow\',Arial,sans-serif;font-size:30px;line-height:1.1;font-weight:400;letter-spacing:.02em;color:' . $ink . ';text-transform:uppercase">' . $e( $heading ) . '</h1>'
		. '</td></tr>'
		// body
		. '<tr><td style="padding:0 28px 30px">' . $body . '</td></tr>'
		// footer
		. '<tr><td style="background:#f6f6f6;padding:20px 28px;border-top:1px solid ' . $line . ';font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:' . $dim . '">'
		. '<strong style="color:' . $ink . '">Questions?</strong> Reply to this email, or reach us at <a href="tel:+16304424938" style="color:' . $red . ';text-decoration:none">(630) 442-4938</a> or <a href="mailto:info@j5rescue.com" style="color:' . $red . ';text-decoration:none">info@j5rescue.com</a>.<br>'
		. 'J5 Rescue Supply LLC &middot; 388 Twin Creeks Drive, Bolingbrook, IL 60440</td></tr>'
		. '</table></td></tr></table></body></html>';
}

function j5_id_email_text( $kind, $ctx ) {
	$approved = ( 'approved' === $kind );
	$out = ( $ctx['first'] ? "Hi {$ctx['first']},\n\n" : "Hi there,\n\n" );
	if ( $approved ) {
		$out .= "We reviewed your ID and your account is now verified" . ( $ctx['type'] ? " as {$ctx['type']}" : '' ) . ".\n\n";
		if ( $ctx['orders'] ) $out .= ( in_array( false, wp_list_pluck( $ctx['orders'], 'released' ), true ) ? "We'll release your order as soon as payment is confirmed.\n\n" : "Your order has been released and is being prepared. You'll get tracking by email when it ships.\n\n" );
		if ( $ctx['code'] ) $out .= "Your personal service discount code: {$ctx['code']}\n" . ( $ctx['terms'] ? ucfirst( $ctx['terms'] ) . ".\n" : '' ) . "It's tied to {$ctx['email']} and yours to keep.\n\n";
	} else {
		$out .= $ctx['orders']
			? "We weren't able to verify the ID submitted with your order. Because it includes items we can only sell to verified law enforcement and licensed security professionals, it's being canceled and refunded in full. Refunds usually post within 5 to 10 business days.\n\n"
			: "We weren't able to approve your service discount request with the document you submitted. You can submit a new document from your account: {$ctx['discount']}\n\n";
		if ( $ctx['reason'] ) $out .= "Reason: {$ctx['reason']}\n\n";
	}
	foreach ( $ctx['orders'] as $o ) $out .= "Order #{$o['number']} ({$o['total']}): " . ( $approved ? ( $o['released'] ? 'Released for fulfillment' : 'Awaiting payment' ) : 'Canceled and Refunded' ) . "\n";
	$out .= "\nFor your privacy, the ID you uploaded was deleted as soon as we finished reviewing it.\n\nQuestions? Reply to this email, call (630) 442-4938, or email info@j5rescue.com.\nJ5 Rescue Supply LLC\n";
	return $out;
}

/* ── WooCommerce email classes ─────────────────────────────────────────────── */

add_filter( 'woocommerce_email_classes', function ( $emails ) {
	if ( ! class_exists( 'J5_Email_ID_Decision' ) ) {
		abstract class J5_Email_ID_Decision extends WC_Email {
			public $kind = 'approved';
			public $ctx  = array();
			public function __construct() {
				$this->customer_email = true;
				$this->placeholders   = array( '{site_title}' => $this->get_blogname() );
				parent::__construct();
			}
			public function trigger( $claim_id ) {
				$this->setup_locale();
				$this->ctx       = j5_id_email_context( $claim_id );
				// Account email, plus the billing email on any linked LE orders if different.
				$to = array( strtolower( $this->ctx['email'] ) => $this->ctx['email'] );
				foreach ( $this->ctx['billing'] as $b ) if ( $b ) $to[ strtolower( $b ) ] = $b;
				$this->recipient = implode( ',', array_filter( $to ) );
				if ( $this->is_enabled() && $this->get_recipient() ) {
					$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), array() );
				}
				$this->restore_locale();
			}
			public function get_content_html() { return j5_id_email_html( $this->kind, $this->ctx, $this->get_heading() ); }
			public function get_content_plain() { return j5_id_email_text( $this->kind, $this->ctx ); }
			// Our HTML is a complete, inline-styled document; skip WC's wrapper/inliner.
			public function style_inline( $content ) { return $content; }
			// Send HTML + plain-text together (multipart) by default: HTML-only mail scores worse with spam filters.
			public function get_email_type() { $t = parent::get_email_type(); return 'html' === $t ? 'multipart' : $t; }
		}
		class J5_Email_ID_Approved extends J5_Email_ID_Decision {
			public function __construct() {
				$this->id = 'j5_id_approved'; $this->kind = 'approved';
				$this->title = 'J5: ID approved';
				$this->description = 'Sent when an ID verification is approved (LE-only orders and/or service discount). Includes released orders and the discount code.';
				parent::__construct();
			}
			public function get_default_subject() {
				$n = $this->ctx['orders'][0]['number'] ?? '';
				return $n ? "Your J5 Rescue Supply order #{$n} is verified" : 'Your J5 Rescue Supply account is verified';
			}
			public function get_default_heading() { return ''; } // blank = automatic heading per situation
		}
		class J5_Email_ID_Rejected extends J5_Email_ID_Decision {
			public function __construct() {
				$this->id = 'j5_id_rejected'; $this->kind = 'rejected';
				$this->title = 'J5: ID rejected';
				$this->description = 'Sent when an ID verification is rejected (LE-only orders and/or service discount).';
				parent::__construct();
			}
			public function get_default_subject() {
				$n = $this->ctx['orders'][0]['number'] ?? '';
				return $n ? "An update on your J5 Rescue Supply order #{$n}" : 'An update on your J5 Rescue Supply request';
			}
			public function get_default_heading() { return ''; }
		}
	}
	$emails['J5_Email_ID_Approved'] = new J5_Email_ID_Approved();
	$emails['J5_Email_ID_Rejected'] = new J5_Email_ID_Rejected();
	return $emails;
} );

/* ── Triggers ──────────────────────────────────────────────────────────────── */

// Send once per claim per decision, at the END of the request: by then the
// plugin has issued the coupon and the LE module has released/flagged orders.
add_action( 'updated_post_meta', 'j5_id_email_on_status', 20, 4 );
add_action( 'added_post_meta', 'j5_id_email_on_status', 20, 4 );
function j5_id_email_on_status( $meta_id, $claim_id, $key, $value ) {
	if ( '_j5v_status' !== $key || ! in_array( $value, array( 'approved', 'rejected' ), true ) ) return;
	if ( ! class_exists( 'J5V_ID_Queue' ) || get_post_type( $claim_id ) !== J5V_ID_Queue::CPT ) return;
	if ( get_post_meta( $claim_id, '_j5_emailed_' . $value, true ) ) return;
	update_post_meta( $claim_id, '_j5_emailed_' . $value, current_time( 'mysql' ) );
	add_action( 'shutdown', function () use ( $claim_id, $value ) {
		if ( ! function_exists( 'WC' ) ) return;
		$emails = WC()->mailer()->get_emails();
		$cls    = 'approved' === $value ? 'J5_Email_ID_Approved' : 'J5_Email_ID_Rejected';
		if ( isset( $emails[ $cls ] ) ) $emails[ $cls ]->trigger( $claim_id );
	} );
}

// The plugin's plain-text coupon email is replaced by the branded approval email.
add_filter( 'j5v_send_coupon_email', function ( $send ) {
	if ( ! function_exists( 'WC' ) ) return $send;
	$emails = WC()->mailer()->get_emails();
	return isset( $emails['J5_Email_ID_Approved'] ) && $emails['J5_Email_ID_Approved']->is_enabled() ? false : $send;
} );
