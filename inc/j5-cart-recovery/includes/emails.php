<?php
/**
 * J5 Cart Recovery: email HTML / text. Same look as the ID decision emails
 * (inc/j5-id-emails.php): black header with the J5 mark, red rule, white
 * body, inline styles only (email-client safe).
 *
 * j5cr_email_html() takes a plain array so it can be previewed without
 * WooCommerce:
 *   kind      reminder | failed
 *   heading, intro, first, button, url, unsub_url
 *   items     [ name, meta[], qty, line (formatted), image ]
 *   total     formatted string
 *   coupon    [ code, pct, expiry_text, scope_text ] or empty
 *   order     order number (failed) or ''
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function j5cr_email_html( $a ) {
	$red = '#C41E24'; $gold = '#B8963F'; $ink = '#141414'; $dim = '#5f5f5f'; $line = '#e6e6e6';
	$e   = function ( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); };
	$f   = 'font-family:Arial,Helvetica,sans-serif;';
	$p   = function ( $html ) use ( $ink, $f ) { return '<p style="margin:0 0 16px;' . $f . 'font-size:16px;line-height:1.6;color:' . $ink . '">' . $html . '</p>'; };
	$btn = '<a href="' . $e( $a['url'] ) . '" style="display:inline-block;padding:14px 30px;background:' . $red . ';color:#ffffff;border:2px solid ' . $red . ';border-radius:2px;' . $f . 'font-size:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;text-decoration:none">' . $e( $a['button'] ) . '</a>';
	$failed = 'failed' === $a['kind'];

	$body  = $p( $a['first'] ? 'Hi ' . $e( $a['first'] ) . ',' : 'Hi there,' );
	foreach ( preg_split( "/\n\s*\n/", trim( (string) $a['intro'] ) ) as $para ) {
		$body .= $p( nl2br( $e( trim( $para ) ) ) );
	}

	// Coupon block.
	if ( ! empty( $a['coupon']['code'] ) ) {
		$c     = $a['coupon'];
		$body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 22px;border:2px dashed ' . $gold . ';border-radius:2px;background:#fbf8ef"><tr><td style="padding:18px;text-align:center;' . $f . '">'
			. '<div style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:' . $dim . ';margin-bottom:6px">' . $e( rtrim( rtrim( number_format( (float) $c['pct'], 2 ), '0' ), '.' ) ) . '% off, applied automatically</div>'
			. '<div style="font-family:\'Courier New\',Courier,monospace;font-size:24px;font-weight:700;letter-spacing:.08em;color:' . $ink . '">' . $e( $c['code'] ) . '</div>'
			. '<div style="font-size:13px;color:' . $dim . ';margin-top:8px;line-height:1.5">' . $e( $c['scope_text'] ) . ( $c['expiry_text'] ? ' ' . $e( $c['expiry_text'] ) : '' ) . '</div>'
			. '</td></tr></table>';
	}

	// Cart card.
	$rows = '';
	foreach ( $a['items'] as $it ) {
		$img   = $it['image'] ? '<img src="' . $e( $it['image'] ) . '" width="56" height="56" alt="" style="display:block;width:56px;height:56px;object-fit:contain;border:1px solid ' . $line . ';background:#ffffff">' : '<div style="width:56px;height:56px;background:#f3f3f3;border:1px solid ' . $line . '"></div>';
		$meta  = $it['meta'] ? '<div style="' . $f . 'font-size:13px;line-height:1.5;color:' . $dim . ';margin-top:2px">' . implode( '<br>', array_map( $e, $it['meta'] ) ) . '</div>' : '';
		$rows .= '<tr>'
			. '<td width="56" style="padding:12px 12px 12px 0;vertical-align:middle">' . $img . '</td>'
			. '<td style="padding:12px 0;vertical-align:middle;' . $f . 'font-size:15px;color:' . $ink . '"><strong>' . $e( $it['name'] ) . '</strong>' . $meta
			. '<div style="' . $f . 'font-size:13px;color:' . $dim . ';margin-top:2px">Qty ' . (int) $it['qty'] . '</div></td>'
			. '<td style="padding:12px 0 12px 12px;vertical-align:middle;text-align:right;white-space:nowrap;' . $f . 'font-size:15px;font-weight:700;color:' . $ink . '">' . $e( $it['line'] ) . '</td>'
			. '</tr>';
	}
	$label = $failed ? ( $a['order'] ? 'Order #' . $e( $a['order'] ) : 'Your order' ) : 'Your cart';
	$body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;border:1px solid ' . $line . ';border-radius:2px"><tr><td style="padding:14px 18px 6px">'
		. '<div style="' . $f . 'font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:' . $gold . ';font-weight:700">' . $label . '</div>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid ' . $line . '"><tr>'
		. '<td style="padding:12px 0;' . $f . 'font-size:14px;color:' . $dim . '">' . ( $failed ? 'Order total' : 'Subtotal' ) . '</td>'
		. '<td style="padding:12px 0;text-align:right;' . $f . 'font-size:16px;font-weight:700;color:' . $ink . '">' . $e( $a['total'] ) . '</td></tr></table>'
		. '</td></tr></table>';

	$body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 8px"><tr><td>' . $btn . '</td></tr></table>';
	if ( $failed ) {
		$body .= '<p style="margin:16px 0 0;' . $f . 'font-size:14px;line-height:1.6;color:' . $dim . '">Prefer to order by phone? Call <a href="tel:+16304424938" style="color:' . $red . ';text-decoration:none">(630) 442-4938</a> and we\'ll finish it with you. We also accept ACH, wire and agency purchase orders.</p>';
	}

	$unsub = $a['unsub_url'] ? '<br><a href="' . $e( $a['unsub_url'] ) . '" style="color:' . $dim . ';text-decoration:underline">Stop cart reminders</a>' : '';

	return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e( $a['heading'] ) . '</title></head>'
		. '<body style="margin:0;padding:0;background:#eeeeee">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eeeeee"><tr><td align="center" style="padding:28px 12px">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:2px;overflow:hidden">'
		. '<tr><td style="background:#0B0B0B;padding:20px 28px;border-bottom:3px solid ' . $red . '">'
		. '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
		. '<td style="border:2px solid ' . $gold . ';padding:4px 9px;font-family:Impact,\'Arial Narrow\',Arial,sans-serif;font-size:20px;color:' . $gold . ';letter-spacing:.04em">J5</td>'
		. '<td style="padding-left:12px;font-family:Impact,\'Arial Narrow\',Arial,sans-serif;font-size:20px;letter-spacing:.06em;color:#ffffff;text-transform:uppercase">J5 Rescue Supply</td>'
		. '</tr></table></td></tr>'
		. '<tr><td style="padding:32px 28px 4px">'
		. '<h1 style="margin:0 0 18px;font-family:Impact,\'Arial Narrow\',Arial,sans-serif;font-size:30px;line-height:1.1;font-weight:400;letter-spacing:.02em;color:' . $ink . ';text-transform:uppercase">' . $e( $a['heading'] ) . '</h1>'
		. '</td></tr>'
		. '<tr><td style="padding:0 28px 30px">' . $body . '</td></tr>'
		. '<tr><td style="background:#f6f6f6;padding:20px 28px;border-top:1px solid ' . $line . ';' . $f . 'font-size:13px;line-height:1.6;color:' . $dim . '">'
		. '<strong style="color:' . $ink . '">Questions?</strong> Reply to this email, or reach us at <a href="tel:+16304424938" style="color:' . $red . ';text-decoration:none">(630) 442-4938</a> or <a href="mailto:info@j5rescue.com" style="color:' . $red . ';text-decoration:none">info@j5rescue.com</a>.<br>'
		. 'J5 Rescue Supply LLC &middot; 388 Twin Creeks Drive, Bolingbrook, IL 60440' . $unsub . '</td></tr>'
		. '</table></td></tr></table></body></html>';
}

function j5cr_email_text( $a ) {
	$out = ( $a['first'] ? "Hi {$a['first']},\n\n" : "Hi there,\n\n" ) . trim( $a['intro'] ) . "\n\n";
	if ( ! empty( $a['coupon']['code'] ) ) {
		$out .= "Your code: {$a['coupon']['code']} ({$a['coupon']['pct']}% off, applied automatically). {$a['coupon']['scope_text']} {$a['coupon']['expiry_text']}\n\n";
	}
	foreach ( $a['items'] as $it ) {
		$out .= "- {$it['name']}" . ( $it['meta'] ? ' (' . implode( '; ', $it['meta'] ) . ')' : '' ) . " x{$it['qty']}  {$it['line']}\n";
	}
	$out .= ( 'failed' === $a['kind'] ? 'Order total: ' : 'Subtotal: ' ) . $a['total'] . "\n\n{$a['button']}: {$a['url']}\n\n";
	$out .= "Questions? Reply to this email, call (630) 442-4938, or email info@j5rescue.com.\nJ5 Rescue Supply LLC, 388 Twin Creeks Drive, Bolingbrook, IL 60440\n";
	if ( $a['unsub_url'] ) {
		$out .= "Stop cart reminders: {$a['unsub_url']}\n";
	}
	return $out;
}

/** Plain internal alert for a failed checkout payment. */
function j5cr_admin_alert_html( $a ) {
	$e    = function ( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); };
	$rows = '';
	foreach ( $a['rows'] as $k => $v ) {
		$rows .= '<tr><td style="padding:4px 12px 4px 0;color:#5f5f5f;white-space:nowrap;vertical-align:top">' . $e( $k ) . '</td><td style="padding:4px 0;color:#141414">' . $v . '</td></tr>';
	}
	return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#141414;max-width:640px">'
		. '<div style="border-left:4px solid #C41E24;padding:8px 12px;background:#f7f7f7;margin-bottom:14px"><strong>Unpaid checkout: order #' . $e( $a['order'] ) . '</strong><br>' . $e( $a['summary'] ) . '</div>'
		. '<table cellpadding="0" cellspacing="0">' . $rows . '</table>'
		. '<p style="margin-top:16px"><a href="' . $e( $a['edit_url'] ) . '" style="color:#C41E24">Open the order in wp-admin</a></p></div>';
}
