<?php
/**
 * Template Name: J5 Armor Record Privacy Request
 * Template Post Type: page
 *
 * "Remove my details from armor records" form. Hands each request to the
 * dealer portal (ArmorSync > Opt-outs). WordPress keeps NO copy: no post,
 * no option, no email, no log line with the visitor's details.
 *
 * DP endpoint (spec: DP synopsis 2026-11-13, zip j5dp-20261113-1349-optout-proof):
 *   GET  {url}  -> {"brand","consent"}   consent = checkbox text, shown exactly as sent
 *   POST {url}  JSON {name,email,phone,organization,order_number,serials,message,confirm,website}
 *        200 ok · 422 {"detail"} · 404 bad token · 429 rate limited
 *
 * The URL (it carries the DP token) lives in wp-config, never in this public repo:
 *   define( 'J5_ARMOR_OPTOUT_URL', 'https://portal.j5rescue.com/api/client-api/public/armor-optout/<token>' );
 * Missing constant or a failed consent GET -> the page shows "unavailable", no form.
 *
 * Spam / abuse on the WC side, before anything reaches the DP:
 *   nonce · honeypot (j5ao_site, never forwarded) · signed time trap (3 s minimum)
 *   · 3 requests per visitor per 10 minutes, keyed by a salted hash of the IP
 *     (CF-Connecting-IP when present). Only that hash and a counter are kept,
 *     and they expire after 10 minutes.
 *
 * The POST is handled in this request (no redirect), so on any error the form
 * comes back with what the visitor typed without storing it anywhere.
 * Styles: assets/css/j5-armor-privacy.css. Page: tools/armor-privacy-apply.sh.
 *
 * @package j5-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
nocache_headers();

$j5ao_url = defined( 'J5_ARMOR_OPTOUT_URL' ) ? (string) J5_ARMOR_OPTOUT_URL : '';

/** Consent statement from the DP, cached 5 minutes. '' when unavailable. */
$j5ao_consent = '';
if ( '' !== $j5ao_url ) {
	$j5ao_consent = get_transient( 'j5ao_consent' );
	if ( ! is_string( $j5ao_consent ) || '' === $j5ao_consent ) {
		$j5ao_consent = '';
		$r = wp_remote_get( $j5ao_url, array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
			$d = json_decode( wp_remote_retrieve_body( $r ), true );
			if ( is_array( $d ) && isset( $d['consent'] ) && is_string( $d['consent'] ) && '' !== trim( $d['consent'] ) ) {
				$j5ao_consent = trim( $d['consent'] );
				set_transient( 'j5ao_consent', $j5ao_consent, 5 * MINUTE_IN_SECONDS );
			}
		}
	}
}
$j5ao_ready = '' !== $j5ao_url && '' !== $j5ao_consent;

/** Signed timestamp for the time trap (nothing stored). */
function j5ao_stamp( $t = null ) {
	$t = null === $t ? time() : (int) $t;
	return $t . '.' . substr( hash_hmac( 'sha256', 'j5ao' . $t, wp_salt( 'nonce' ) ), 0, 16 );
}
function j5ao_stamp_age( $v ) {
	$p = explode( '.', (string) $v );
	if ( 2 !== count( $p ) || ! ctype_digit( $p[0] ) ) return -1;
	if ( ! hash_equals( j5ao_stamp( $p[0] ), (string) $v ) ) return -1;
	return time() - (int) $p[0];
}

/** Rate limit: 3 per 10 minutes per visitor. Returns false when over. */
function j5ao_rate_ok() {
	$ip  = ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? (string) $_SERVER['HTTP_CF_CONNECTING_IP'] : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'j5ao_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 24 );
	$n   = (int) get_transient( $key );
	if ( $n >= 3 ) return false;
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
	return true;
}

$j5ao_fields = array(
	'name'         => array( 'Full name', 120, true ),
	'email'        => array( 'Email', 160, true ),
	'phone'        => array( 'Phone', 40, false ),
	'organization' => array( 'Agency or company', 160, false ),
	'order_number' => array( 'Order number', 60, false ),
	'serials'      => array( 'Plate serial numbers', 600, false ),
	'message'      => array( 'Anything else that helps', 2000, false ),
);
$j5ao_val   = array_fill_keys( array_keys( $j5ao_fields ), '' );
$j5ao_state = 'form';   // form | done
$j5ao_error = '';

if ( $j5ao_ready && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['j5ao_nonce'] ) ) {
	// Values back into the form only; never written anywhere.
	foreach ( $j5ao_fields as $k => $f ) {
		$raw = isset( $_POST[ $k ] ) ? wp_unslash( (string) $_POST[ $k ] ) : '';
		$v   = in_array( $k, array( 'serials', 'message' ), true ) ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		if ( 'email' === $k ) $v = sanitize_email( $v );
		$j5ao_val[ $k ] = mb_substr( $v, 0, $f[1] );
	}
	$confirm = ! empty( $_POST['confirm'] );

	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['j5ao_nonce'] ) ), 'j5ao_submit' ) ) {
		$j5ao_error = 'This page expired. Please press Send request again.';
	} elseif ( ! empty( $_POST['j5ao_site'] ) ) {
		$j5ao_state = 'done'; // bot: look successful, send nothing
	} elseif ( ( $age = j5ao_stamp_age( wp_unslash( $_POST['j5ao_t'] ?? '' ) ) ) < 3 ) {
		$j5ao_error = 'Something went wrong. Please press Send request again.';
	} elseif ( '' === trim( $j5ao_val['name'] ) ) {
		$j5ao_error = 'Please enter your full name.';
	} elseif ( ! is_email( $j5ao_val['email'] ) ) {
		$j5ao_error = 'Please enter a valid email address.';
	} elseif ( ! $confirm ) {
		$j5ao_error = 'Please tick the box to confirm your request.';
	} elseif ( ! j5ao_rate_ok() ) {
		$j5ao_error = 'You have sent several requests in a short time. Please wait 10 minutes and try again.';
	} else {
		$body = $j5ao_val;
		$body['confirm'] = true;
		$res  = wp_remote_post( $j5ao_url, array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		) );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( 200 === $code ) {
			$j5ao_state = 'done';
			$j5ao_val   = array_fill_keys( array_keys( $j5ao_fields ), '' );
		} elseif ( 422 === $code ) {
			$d = json_decode( wp_remote_retrieve_body( $res ), true );
			$j5ao_error = ( is_array( $d ) && isset( $d['detail'] ) && is_string( $d['detail'] ) && '' !== $d['detail'] )
				? $d['detail']
				: 'Please check the form and try again.';
		} elseif ( 429 === $code ) {
			$j5ao_error = 'We are getting a lot of requests right now. Please try again in a minute.';
		} else {
			// 404 (bad token), 5xx or no connection. Status code only, never the visitor's details.
			error_log( 'j5 armor privacy form: DP returned ' . ( $code ? $code : 'no response' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$j5ao_error = 'We could not send your request just now. Please try again later, or call us at (630) 442-4938.';
		}
	}
}

add_action( 'wp_enqueue_scripts', function () {
	$f = get_stylesheet_directory() . '/assets/css/j5-armor-privacy.css';
	wp_enqueue_style( 'j5-armor-privacy', get_stylesheet_directory_uri() . '/assets/css/j5-armor-privacy.css', array(), file_exists( $f ) ? (string) filemtime( $f ) : null );
} );

$j5ao_in = function ( $k, $type = 'text', $auto = '' ) use ( $j5ao_fields, $j5ao_val ) {
	$f = $j5ao_fields[ $k ];
	$id = 'j5ao-' . $k;
	echo '<div class="j5ao-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $f[0] ) . ( $f[2] ? ' <span class="j5ao-req" aria-hidden="true">*</span>' : '' ) . '</label>';
	echo '<input id="' . esc_attr( $id ) . '" name="' . esc_attr( $k ) . '" type="' . esc_attr( $type ) . '" maxlength="' . (int) $f[1] . '" value="' . esc_attr( $j5ao_val[ $k ] ) . '"' . ( $f[2] ? ' required aria-required="true"' : '' ) . ( $auto ? ' autocomplete="' . esc_attr( $auto ) . '"' : '' ) . '></div>';
};

get_header();
?>
<main id="primary" class="site-main j5ao">
	<div class="j5ao-wrap">

		<header class="j5ao-head">
			<span class="j5ao-tag">Privacy request</span>
			<h1 class="j5ao-h1">Remove my details from armor records</h1>
			<p class="j5ao-intro">We keep a record of where each armor plate goes so we can reach the owner about a recall or a warranty. If you would like your personal details removed from those records, send us this form.</p>
		</header>

		<div class="j5ao-cards">
			<div class="j5ao-card">
				<h2>What we remove</h2>
				<ul><li>Your name, email and phone</li><li>Your address, agency and role</li><li>Your order number and any notes</li></ul>
			</div>
			<div class="j5ao-card j5ao-card--keep">
				<h2>What we keep</h2>
				<ul><li>The plate and its serial number</li><li>The date it left us</li></ul>
				<p class="j5ao-card__note">After removal we cannot contact you about that armor.</p>
			</div>
		</div>

		<?php if ( 'done' === $j5ao_state ) : ?>

			<div class="j5ao-done" role="status">
				<h2>Request received</h2>
				<p>A person at J5 Rescue Supply will review your request and remove your personal details from the armor records that belong to you.</p>
			</div>

		<?php elseif ( ! $j5ao_ready ) : ?>

			<div class="j5ao-alert" role="alert">This form is unavailable right now. Please try again later, or call us at <a href="tel:+16304424938">(630) 442-4938</a>.</div>

		<?php else : ?>

			<form class="j5ao-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>" novalidate>
				<?php if ( '' !== $j5ao_error ) : ?>
					<div class="j5ao-alert" role="alert"><?php echo esc_html( $j5ao_error ); ?></div>
				<?php endif; ?>

				<input type="hidden" name="j5ao_nonce" value="<?php echo esc_attr( wp_create_nonce( 'j5ao_submit' ) ); ?>">
				<input type="hidden" name="j5ao_t" value="<?php echo esc_attr( j5ao_stamp() ); ?>">
				<div class="j5ao-hp" aria-hidden="true"><label for="j5ao-site">Leave this empty</label><input id="j5ao-site" type="text" name="j5ao_site" tabindex="-1" autocomplete="off"></div>

				<fieldset class="j5ao-sec">
					<legend><span class="j5ao-n">1</span>About you</legend>
					<div class="j5ao-grid">
						<?php $j5ao_in( 'name', 'text', 'name' ); ?>
						<?php $j5ao_in( 'email', 'email', 'email' ); ?>
						<?php $j5ao_in( 'phone', 'tel', 'tel' ); ?>
						<?php $j5ao_in( 'organization', 'text', 'organization' ); ?>
					</div>
				</fieldset>

				<fieldset class="j5ao-sec">
					<legend><span class="j5ao-n">2</span>Help us find your record <em>Optional</em></legend>
					<div class="j5ao-grid">
						<?php $j5ao_in( 'order_number' ); ?>
						<div class="j5ao-field">
							<label for="j5ao-serials">Plate serial numbers</label>
							<input id="j5ao-serials" name="serials" type="text" maxlength="600" value="<?php echo esc_attr( $j5ao_val['serials'] ); ?>" aria-describedby="j5ao-serials-hint">
							<small id="j5ao-serials-hint">Separate with commas.</small>
						</div>
						<div class="j5ao-field j5ao-field--full">
							<label for="j5ao-message">Anything else that helps</label>
							<textarea id="j5ao-message" name="message" rows="4" maxlength="2000"><?php echo esc_textarea( $j5ao_val['message'] ); ?></textarea>
						</div>
					</div>
				</fieldset>

				<fieldset class="j5ao-sec">
					<legend><span class="j5ao-n">3</span>Confirm</legend>
					<label class="j5ao-consent">
						<input type="checkbox" name="confirm" value="1" id="j5ao-confirm"<?php echo ! empty( $confirm ) ? ' checked' : ''; ?>>
						<span><?php echo esc_html( $j5ao_consent ); ?></span>
					</label>
					<button type="submit" class="j5ao-btn" id="j5ao-send">Send request</button>
					<p class="j5ao-review">A person at J5 Rescue Supply reviews every request before anything is removed.</p>
				</fieldset>
			</form>
			<script>
			(function(){
				var c=document.getElementById('j5ao-confirm'),b=document.getElementById('j5ao-send');
				if(!c||!b)return;
				function s(){b.disabled=!c.checked;}
				c.addEventListener('change',s);s();
				b.form.addEventListener('submit',function(){if(c.checked){b.disabled=true;b.textContent='Sending…';}});
			})();
			</script>

		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
