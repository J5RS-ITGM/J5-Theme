<?php
/**
 * J5 registration guard (2026-11-03)
 * ----------------------------------
 * My Account registration was switched off after the Sep 2026 spam-signup
 * attack (197 bot accounts). This turns it back on safely. Three cheap
 * checks on the WooCommerce register form, no third-party captcha:
 *
 *   1. Honeypot   a field people never see (bots fill every input).
 *   2. Time trap  a signed timestamp; submitting under 3 seconds or more
 *                 than a day after the page loaded is refused.
 *   3. Rate limit at most 3 new accounts per IP per hour.
 *
 * Checkout account creation is untouched (it can't be scripted without
 * placing an order). wp-login.php registration (users_can_register) stays off.
 *
 * Switch My Account registration on/off:
 *   wp option update woocommerce_enable_myaccount_registration yes|no
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5RG_MIN_SECONDS = 3;
const J5RG_MAX_AGE     = DAY_IN_SECONDS;
const J5RG_PER_HOUR    = 3;

/** Hidden fields on the register form. */
add_action( 'woocommerce_register_form', function () {
	$t = time();
	?>
	<div class="j5rg-trap" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
		<label for="j5rg_website">Leave this field empty</label>
		<input type="text" name="j5rg_website" id="j5rg_website" value="" tabindex="-1" autocomplete="off">
	</div>
	<input type="hidden" name="j5rg_t" value="<?php echo esc_attr( $t . '.' . wp_hash( 'j5rg|' . $t ) ); ?>">
	<?php
} );

/** Client IP (Cloudflare first). */
function j5rg_ip() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			$ip = trim( explode( ',', (string) wp_unslash( $_SERVER[ $k ] ) )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '0.0.0.0';
}

/** Validate My Account registrations only (checkout has no j5rg_t field). */
add_filter( 'woocommerce_process_registration_errors', function ( $errors, $username, $password, $email ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WC verified its nonce before this filter.
	if ( ! isset( $_POST['register'] ) ) {
		return $errors;
	}
	$generic = 'We couldn\'t create your account. Please try again, or call us at (630) 442-4938.';

	if ( ! empty( $_POST['j5rg_website'] ) ) {
		$errors->add( 'j5rg_trap', $generic );
		return $errors;
	}

	$raw   = isset( $_POST['j5rg_t'] ) ? (string) wp_unslash( $_POST['j5rg_t'] ) : '';
	$parts = explode( '.', $raw, 2 );
	$t     = (int) $parts[0];
	$sig   = isset( $parts[1] ) ? $parts[1] : '';
	$age   = time() - $t;
	if ( ! $t || ! hash_equals( wp_hash( 'j5rg|' . $t ), $sig ) || $age > J5RG_MAX_AGE ) {
		$errors->add( 'j5rg_time', 'This page expired. Please reload it and try again.' );
		return $errors;
	}
	if ( $age < J5RG_MIN_SECONDS ) {
		$errors->add( 'j5rg_fast', $generic );
		return $errors;
	}
	// phpcs:enable

	$key   = 'j5rg_' . md5( j5rg_ip() );
	$count = (int) get_transient( $key );
	if ( $count >= J5RG_PER_HOUR ) {
		$errors->add( 'j5rg_rate', 'Too many new accounts from this connection. Please try again in an hour, or call us at (630) 442-4938.' );
		return $errors;
	}
	return $errors;
}, 10, 4 );

/** Count successful My Account registrations per IP. */
add_action( 'woocommerce_created_customer', function () {
	if ( ! isset( $_POST['register'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$key = 'j5rg_' . md5( j5rg_ip() );
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
} );
