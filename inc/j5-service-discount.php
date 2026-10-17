<?php
/**
 * My Account > Service Discount: J5 layout for the j5-verification plugin's
 * endpoint (the plugin still handles the upload form POST).
 *
 * Fixes vs the plugin's own page:
 *   - Shows the customer's discount code from ANY approved claim that has
 *     one. The plugin only looked at the newest claim, so a later LE-purchase
 *     claim (approved without a coupon, or still pending) hid the code.
 *   - "Approve" without a coupon no longer shows an empty code box.
 *   - Dashboard gets a small card with the code too.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function () {
	if ( class_exists( 'J5V_Account' ) ) {
		remove_action( 'woocommerce_account_service-discount_endpoint', array( 'J5V_Account', 'render' ) );
		add_action( 'woocommerce_account_service-discount_endpoint', 'j5sd_render' );
	}
}, 20 );

/** Everything the page needs about the current user's claims. */
function j5sd_state( $uid ) {
	$st = array( 'code' => '', 'code_type' => '', 'verified' => array(), 'pending' => false, 'latest' => '', 'can_submit' => true );
	if ( ! $uid || ! class_exists( 'J5V_ID_Queue' ) ) return $st;
	$ids = get_posts( array(
		'post_type' => J5V_ID_Queue::CPT, 'post_status' => 'private', 'numberposts' => 30,
		'meta_key' => '_j5v_user', 'meta_value' => $uid, 'orderby' => 'ID', 'order' => 'DESC', 'fields' => 'ids',
	) );
	foreach ( $ids as $i => $id ) {
		$status = get_post_meta( $id, '_j5v_status', true );
		$type   = (string) get_post_meta( $id, '_j5v_type', true );
		if ( 0 === $i ) $st['latest'] = $status;
		if ( 'pending' === $status ) $st['pending'] = true;
		if ( 'approved' === $status ) {
			if ( $type && ! in_array( $type, $st['verified'], true ) ) $st['verified'][] = $type;
			$code = (string) get_post_meta( $id, '_j5v_coupon_code', true );
			$cid  = (int) get_post_meta( $id, '_j5v_coupon_id', true );
			if ( ! $st['code'] && $code && ( ! $cid || 'publish' === get_post_status( $cid ) ) ) {
				$st['code'] = $code; $st['code_type'] = $type;
			}
		}
	}
	// The plugin's upload handler refuses a new claim while the newest one is pending or approved.
	$st['can_submit'] = ! in_array( $st['latest'], array( 'pending', 'approved' ), true );
	return $st;
}

function j5sd_render() {
	$st    = j5sd_state( get_current_user_id() );
	$terms = class_exists( 'J5V_Discount' ) ? J5V_Discount::terms_line() : '';
	?>
	<div class="j5sd">
		<h2 class="j5sd__h">Military &amp; First Responder Discount</h2>

		<?php if ( $st['code'] ) : ?>
			<div class="j5sd__code">
				<span class="j5sd__label">Your personal discount code</span>
				<div class="j5sd__row">
					<code id="j5sd-code"><?php echo esc_html( $st['code'] ); ?></code>
					<button type="button" class="j5sd__copy" data-copy="<?php echo esc_attr( $st['code'] ); ?>">Copy</button>
				</div>
				<?php if ( $terms ) : ?><p class="j5sd__terms"><?php echo esc_html( ucfirst( $terms ) ); ?>.</p><?php endif; ?>
				<p class="j5sd__muted">Enter it in the coupon box at checkout on any order. It's tied to your account email and it's yours to keep.</p>
			</div>
		<?php endif; ?>

		<?php if ( $st['verified'] ) : ?>
			<p class="j5sd__ok">&#10003; Verified: <?php echo esc_html( implode( ', ', $st['verified'] ) ); ?></p>
		<?php endif; ?>

		<?php if ( $st['verified'] && ! $st['code'] ) : ?>
			<p class="j5sd__muted">Your verification is approved, but no discount code was issued with it. If you think you should have one, <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">contact us</a>.</p>
		<?php endif; ?>

		<?php if ( $st['pending'] ) : ?>
			<p class="j5sd__note">Your ID is under review, typically 1 to 2 business days. We'll email you as soon as it's done.</p>
		<?php endif; ?>

		<?php if ( 'revoked' === $st['latest'] && ! $st['code'] ) : ?>
			<p class="j5sd__note">Your discount was revoked. <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Contact us</a> with questions.</p>
		<?php elseif ( $st['can_submit'] && ! $st['code'] ) : ?>
			<?php if ( 'rejected' === $st['latest'] ) : ?>
				<p class="j5sd__note">Your last submission wasn't approved. You're welcome to submit a new document.</p>
			<?php endif; ?>
			<p class="j5sd__muted">Upload a government-issued ID or service credential (military ID, agency ID, DD-214 excerpt). We review it, delete the document, and issue your personal ongoing discount code.</p>
			<form method="post" enctype="multipart/form-data" class="j5sd__form">
				<?php wp_nonce_field( 'j5v_claim', '_j5v_claim' ); ?>
				<label for="j5sd-type">I am</label>
				<select name="j5v_claim_type" id="j5sd-type">
					<option>Military / Veteran</option>
					<option>Law Enforcement</option>
					<option>Licensed Security</option>
					<option>Fire / EMS</option>
					<option>Other First Responder</option>
				</select>
				<input type="file" name="j5v_id_file" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
				<button type="submit" name="j5v_claim_submit" value="1" class="j5sd__btn">Submit for review</button>
			</form>
		<?php endif; ?>
	</div>
	<style>
	.j5sd__h{font-family:'Bebas Neue',Impact,sans-serif;font-weight:400;font-size:30px;letter-spacing:.03em;color:#fff;margin:0 0 18px;text-transform:uppercase}
	.j5sd__code{border:2px dashed #C8A84E;background:rgba(200,168,78,.06);padding:20px 22px;margin:0 0 18px;border-radius:2px}
	.j5sd__label{display:block;font-family:'Bebas Neue',Impact,sans-serif;letter-spacing:.16em;font-size:15px;color:#C8A84E;margin-bottom:8px}
	.j5sd__row{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
	.j5sd__row code{font-family:'JetBrains Mono',ui-monospace,monospace;font-size:26px;font-weight:600;letter-spacing:.08em;color:#fff;background:none;padding:0}
	.j5sd__copy{background:transparent;border:1px solid #C8A84E;color:#C8A84E;padding:6px 14px;font-family:'Bebas Neue',Impact,sans-serif;letter-spacing:.1em;font-size:16px;cursor:pointer;border-radius:2px}
	.j5sd__copy:hover{background:#C8A84E;color:#0B0B0B}
	.j5sd__terms{color:#f2f2f2;margin:12px 0 4px}
	.j5sd__muted{color:#a8a8a8;font-size:15px;margin:6px 0 12px}
	.j5sd__ok{color:#7fd17f;font-weight:600;margin:0 0 8px}
	.j5sd__note{border-left:3px solid #E0A93B;background:#161616;padding:12px 14px;color:#f2f2f2;margin:0 0 14px}
	.j5sd a{color:#C8A84E}
	.j5sd__form{display:grid;gap:10px;max-width:460px;margin-top:8px}
	.j5sd__form label{color:#a8a8a8;font-size:14px}
	.j5sd__btn{background:#C41E24;color:#fff;border:0;padding:14px 22px;font-family:'Bebas Neue',Impact,sans-serif;font-size:19px;letter-spacing:.08em;cursor:pointer;border-radius:2px;justify-self:start}
	</style>
	<script>
	document.addEventListener('click', function (e) {
	  var b = e.target.closest && e.target.closest('.j5sd__copy'); if (!b) return;
	  var t = b.getAttribute('data-copy');
	  (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1500); }).catch(function () {
	    var r = document.createRange(); r.selectNodeContents(document.getElementById('j5sd-code')); var s = getSelection(); s.removeAllRanges(); s.addRange(r);
	  });
	});
	</script>
	<?php
}

/* Small card on the My Account dashboard when the customer has a code. */
add_action( 'woocommerce_account_dashboard', function () {
	$st = j5sd_state( get_current_user_id() );
	if ( ! $st['code'] ) return;
	echo '<div style="margin:18px 0;padding:14px 18px;border:1px dashed #C8A84E;background:rgba(200,168,78,.06);border-radius:2px">'
		. '<span style="display:block;font-family:\'Bebas Neue\',Impact,sans-serif;letter-spacing:.14em;color:#C8A84E;font-size:14px">Your service discount code</span>'
		. '<code style="font-family:\'JetBrains Mono\',ui-monospace,monospace;font-size:20px;color:#fff;background:none;padding:0;letter-spacing:.06em">' . esc_html( $st['code'] ) . '</code>'
		. ' <a href="' . esc_url( wc_get_account_endpoint_url( 'service-discount' ) ) . '" style="color:#C8A84E;margin-left:10px;font-size:14px">Details</a></div>';
}, 20 );
