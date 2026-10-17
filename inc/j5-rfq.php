<?php
/**
 * J5 RFQ — native "Request a Quote" page backed by the DP catalog.
 *
 * Shortcode: [j5_rfq]  (placed on /request-a-quote/)
 *
 * The browser talks directly to the dealer portal's public quote API — the
 * same endpoints the portal's own /quote page uses — so the WooCommerce site
 * searches the FULL DP catalog (not just what's published to WC) and quotes
 * land in the DP quote inbox:
 *
 *   GET  {api}/facets                     manufacturers + categories
 *   GET  {api}/products?q=&manufacturer=  live search (no prices)
 *   POST {api}/guide                      "I don't know what I want" finder
 *   POST {api}/submit                     the request (honeypot + rate limited)
 *
 * Calls go browser -> DP (not through WordPress) so the DP's per-IP rate
 * limits apply per visitor. The DP must list this site in CORS_ORIGINS.
 *
 * Prefill: ?sku=&name=&qty=&from= (product page "Request a Quote" button).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function j5_rfq_api_base() {
	return apply_filters( 'j5_rfq_api_base', 'https://portal.j5rescue.com/api/public/portal/quote' );
}

add_shortcode( 'j5_rfq', function () {
	$css = get_stylesheet_directory() . '/assets/css/j5-rfq.css';
	$js  = get_stylesheet_directory() . '/assets/js/j5-rfq.js';
	wp_enqueue_style( 'j5-rfq', get_stylesheet_directory_uri() . '/assets/css/j5-rfq.css', array(), file_exists( $css ) ? (string) filemtime( $css ) : null );
	wp_enqueue_script( 'j5-rfq', get_stylesheet_directory_uri() . '/assets/js/j5-rfq.js', array(), file_exists( $js ) ? (string) filemtime( $js ) : null, true );

	$sku  = isset( $_GET['sku'] ) ? sanitize_text_field( wp_unslash( $_GET['sku'] ) ) : '';
	$name = isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( $_GET['name'] ) ) : '';
	$qty  = isset( $_GET['qty'] ) ? max( 1, absint( $_GET['qty'] ) ) : 1;
	$from = isset( $_GET['from'] ) ? esc_url_raw( wp_unslash( $_GET['from'] ) ) : '';
	// Only accept "from" links that point back at this site.
	if ( $from && wp_parse_url( $from, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) $from = '';

	$cfg = array(
		'api'     => j5_rfq_api_base(),
		'prefill' => ( $sku || $name ) ? array( 'sku' => $sku, 'name' => $name ?: $sku, 'qty' => $qty, 'from' => $from ) : null,
		'contact' => array( 'phone' => '(630) 442-4938', 'tel' => '+16304424938', 'email' => 'info@j5rescue.com' ),
		'portal'  => 'https://portal.j5rescue.com/',
	);

	ob_start();
	?>
	<div class="j5rfq" id="j5rfq" data-config="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
		<div class="j5rfq__grid">
			<form class="j5rfq__card j5rfq__form" id="j5rfq-form" novalidate>
				<h2 class="j5rfq__h">Request a quote</h2>
				<p class="j5rfq__sub">Tell us what you need and who it's for. We reply with a written quote, usually the same business day.</p>

				<div class="j5rfq__sec">Who it's for</div>
				<div class="j5rfq__fields">
					<input class="is-full" name="organization" placeholder="Department / agency / company" autocomplete="organization" required>
					<input name="name" placeholder="Your name" autocomplete="name" required>
					<input name="title" placeholder="Title / rank" autocomplete="organization-title">
					<input name="email" type="email" placeholder="Work email" autocomplete="email" required>
					<input name="phone" type="tel" placeholder="Phone" autocomplete="tel">
					<select name="role" aria-label="Organization type">
						<?php
						// Label shown => DP agency_type (the DP API accepts police|fire|corrections|business|other).
						// The exact label is also prepended to the notes so the DP inbox shows it.
						$j5_roles = array(
							'Individual / Other'      => 'other',
							'Business'                => 'business',
							'NGO / Non-profit'        => 'other',
							'Law Enforcement'         => 'police',
							'Federal Law Enforcement' => 'police',
							'Corrections'             => 'corrections',
							'Private Security'        => 'business',
						);
						foreach ( $j5_roles as $label => $type ) : ?>
							<option value="<?php echo esc_attr( $label ); ?>" data-type="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="state" aria-label="State">
						<option value="">State</option>
						<?php foreach ( explode( ' ', 'AL AK AZ AR CA CO CT DE DC FL GA HI ID IL IN IA KS KY LA ME MD MA MI MN MS MO MT NE NV NH NJ NM NY NC ND OH OK OR PA RI SC SD TN TX UT VT VA WA WV WI WY' ) as $s ) : ?>
							<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $s ); ?></option>
						<?php endforeach; ?>
					</select>
					<input name="officers" inputmode="numeric" placeholder="Officers / personnel">
					<select name="timeline" aria-label="Timeline">
						<option value="">Timeline</option>
						<?php foreach ( array( 'Immediately', '30 days', '30–60 days', '60–90 days', 'Budgeting / next FY', 'Not sure' ) as $t ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="j5rfq__sec">What you need</div>
				<div class="j5rfq__finder" id="j5rfq-finder"><p class="j5rfq__muted">Loading catalog…</p></div>
				<div id="j5rfq-items"></div>

				<textarea name="notes" rows="4" placeholder="What do you need? Sizes, quantities, colors, deadlines, contract or grant details."></textarea>
				<input name="heard_from" placeholder="How did you hear about us? (optional)">
				<div class="j5rfq__hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>

				<div class="j5rfq__msg" id="j5rfq-msg" role="alert" hidden></div>
				<button type="submit" class="j5rfq__submit" id="j5rfq-submit">Submit for quote</button>
				<p class="j5rfq__fine">No obligation. Agencies: no credit card, no credit application. Tax exemption goes on file once.</p>
			</form>

			<aside class="j5rfq__side">
				<p class="j5rfq__eyebrow">Agency / business quote</p>
				<h1 class="j5rfq__title">Written quotes for <span>agencies &amp; businesses.</span></h1>
				<p class="j5rfq__lead">Armor, shields, helmets, less lethal, medical and duty gear, quoted to your department on letterhead you can take to purchasing.</p>
				<div class="j5rfq__point"><b>Same business day</b><span>Most quotes go out the day they come in.</span></div>
				<div class="j5rfq__point"><b>Contract &amp; grant ready</b><span>Line-item quotes with SKUs, lead times and shipping for purchasing and grant files.</span></div>
				<div class="j5rfq__point"><b>Department pricing</b><span>Tiered by agency size and order value. Businesses and private security welcome.</span></div>
				<div class="j5rfq__point"><b>Rather talk it through?</b><span><a href="tel:+16304424938">(630) 442-4938</a> · <a href="mailto:info@j5rescue.com">info@j5rescue.com</a></span></div>
			</aside>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );
