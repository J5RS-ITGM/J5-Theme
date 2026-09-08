<?php
/**
 * J5 Forms — Renderers
 *
 * HTML output for both shortcodes. Matches the approved mockup design.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared form chrome — wraps a form body with success/error message zones.
 *
 * Note: this does NOT wrap in .j5-forms-wrap — that's the responsibility of
 * the calling render function so it can include sibling elements (sidebar,
 * banner) inside the same scoped wrapper.
 */
function j5_forms_wrap_form( $form_id, $body, $extra_class = '' ) {
	$nonce = wp_create_nonce( 'j5_forms_submit' );
	$ts    = time();
	ob_start();
	?>
	<div class="j5-form-card-wrap j5-form-card-<?php echo esc_attr( $form_id ); ?> <?php echo esc_attr( $extra_class ); ?>">

		<div class="j5-forms-success" hidden>
			<h3>Message sent.</h3>
			<p>Thanks — we got your submission and will reply within one business day.</p>
		</div>

		<form class="form-card j5-form" data-form-id="<?php echo esc_attr( $form_id ); ?>" novalidate>
			<input type="hidden" name="j5f_form" value="<?php echo esc_attr( $form_id ); ?>">
			<input type="hidden" name="j5f_nonce" value="<?php echo esc_attr( $nonce ); ?>">
			<input type="hidden" name="j5f_ts" value="<?php echo esc_attr( $ts ); ?>">
			<!-- honeypot: real users never fill this -->
			<div class="j5f-hp-wrap" aria-hidden="true">
				<label>Leave this empty <input type="text" name="j5f_hp" tabindex="-1" autocomplete="off"></label>
			</div>

			<div class="j5-forms-error" hidden></div>

			<?php echo $body; // already-escaped by individual builders ?>

		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Build a single form field block.
 */
function j5_forms_field( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'type'        => 'text',
			'name'        => '',
			'label'       => '',
			'placeholder' => '',
			'required'    => false,
			'hint'        => '',
			'options'     => array(),
			'rows'        => 5,
		)
	);

	$req_marker = $args['required'] ? ' <span class="req">*</span>' : '';
	$req_attr   = $args['required'] ? ' required' : '';

	ob_start();
	?>
	<div class="field">
		<label class="field-label"><?php echo esc_html( $args['label'] ); ?><?php echo $req_marker; ?></label>
		<?php if ( 'select' === $args['type'] ) : ?>
			<select class="select" name="<?php echo esc_attr( $args['name'] ); ?>"<?php echo $req_attr; ?>>
				<option value=""><?php echo esc_html( $args['placeholder'] ?: 'Select…' ); ?></option>
				<?php foreach ( $args['options'] as $opt ) : ?>
					<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php elseif ( 'textarea' === $args['type'] ) : ?>
			<textarea
				class="textarea"
				name="<?php echo esc_attr( $args['name'] ); ?>"
				placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
				rows="<?php echo (int) $args['rows']; ?>"
				<?php echo $req_attr; ?>></textarea>
		<?php else : ?>
			<input
				class="input"
				type="<?php echo esc_attr( $args['type'] ); ?>"
				name="<?php echo esc_attr( $args['name'] ); ?>"
				placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
				<?php echo $req_attr; ?>>
		<?php endif; ?>
		<?php if ( ! empty( $args['hint'] ) ) : ?>
			<span class="field-hint"><?php echo esc_html( $args['hint'] ); ?></span>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ============================================================================
 * Contact Form
 * ==========================================================================*/

function j5_render_contact_form( $atts = array() ) {
	$topics = j5_forms_get_setting( 'contact_topics', array() );
	if ( ! is_array( $topics ) ) {
		$topics = array();
	}

	ob_start();
	?>
	<div class="j5-forms-wrap j5-forms-contact">
	<div class="contact-layout">

		<?php
		$body  = '';
		$body .= '<div class="form-section">';
		$body .= '<div class="form-section-head"><span class="form-section-num">01</span><span class="form-section-title">Your Info</span></div>';
		$body .= '<div class="field-row cols-2">';
		$body .= j5_forms_field( array( 'name' => 'first_name', 'label' => 'First Name', 'placeholder' => 'John', 'required' => true ) );
		$body .= j5_forms_field( array( 'name' => 'last_name', 'label' => 'Last Name', 'placeholder' => 'Doe', 'required' => true ) );
		$body .= '</div>';
		$body .= '<div class="field-row cols-2">';
		$body .= j5_forms_field( array( 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'placeholder' => 'you@example.com', 'required' => true ) );
		$body .= j5_forms_field( array( 'type' => 'tel', 'name' => 'phone', 'label' => 'Phone', 'placeholder' => '(555) 123-4567' ) );
		$body .= '</div>';
		$body .= '<div class="field-row"><div class="field"><label class="check-row"><input type="checkbox" name="agency_flag" value="1" id="j5f-contact-agency-flag"><span>I\'m contacting on behalf of a department or agency</span></label></div></div>';
		$body .= '<div class="field-row j5f-conditional" data-j5f-conditional-on="agency_flag">';
		$body .= j5_forms_field( array( 'name' => 'organization', 'label' => 'Department / Agency Name', 'placeholder' => 'e.g. Springfield PD', 'hint' => 'Required when contacting on behalf of a department or agency.' ) );
		$body .= '</div>';
		$body .= '</div>';

		$body .= '<div class="form-section">';
		$body .= '<div class="form-section-head"><span class="form-section-num">02</span><span class="form-section-title">How can we help?</span></div>';
		$body .= '<div class="field-row">';
		$body .= j5_forms_field( array( 'type' => 'select', 'name' => 'topic', 'label' => 'Topic', 'placeholder' => 'Select a topic…', 'options' => $topics, 'required' => true ) );
		$body .= '</div>';
		$body .= '<div class="field-row">';
		$body .= j5_forms_field( array( 'type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'placeholder' => 'Tell us what you need…', 'required' => true, 'hint' => "If you're asking about a specific order, please include the order number." ) );
		$body .= '</div>';
		$body .= '</div>';

		$body .= '<div class="form-footer">';
		$body .= '<p class="form-footer-note">We respect your privacy. Your info is only used to respond to your message.</p>';
		$body .= '<button class="btn btn-primary" type="submit">Send Message <span class="btn-arrow">→</span></button>';
		$body .= '</div>';

		echo j5_forms_wrap_form( 'contact', $body );
		?>

		<aside class="info-card">
			<h3 class="info-card-title">Other Ways to Reach Us</h3>

			<div class="info-row">
				<div class="info-icon">P</div>
				<div>
					<div class="info-label">Phone</div>
					<div class="info-value"><a href="tel:+16304424938">(630) 442-4938</a></div>
				</div>
			</div>

			<div class="info-row">
				<div class="info-icon">E</div>
				<div>
					<div class="info-label">Email</div>
					<div class="info-value"><a href="mailto:info@j5rescue.com">info@j5rescue.com</a></div>
				</div>
			</div>

			<div class="info-row">
				<div class="info-icon">L</div>
				<div>
					<div class="info-label">Location</div>
					<div class="info-value">Northern Illinois<br>By appointment only</div>
				</div>
			</div>
		</aside>

	</div>
	</div>
	<?php
	return ob_get_clean();
}

/* ============================================================================
 * Quote Form
 * ==========================================================================*/

function j5_render_quote_form( $atts = array() ) {
	$services = j5_forms_get_setting( 'quote_services', array() );
	if ( ! is_array( $services ) ) {
		$services = array();
	}

	$timelines = array(
		'ASAP / Rush',
		'Within 1–2 weeks',
		'Within 3–4 weeks',
		'1–3 months',
		'Flexible',
	);

	$budgets = array(
		'Under $500',
		'$500–$2,000',
		'$2,000–$10,000',
		'$10,000–$50,000',
		'$50,000+',
		'Need help estimating',
	);

	ob_start();
	?>
	<div class="j5-forms-wrap j5-forms-quote">
	<?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?>
	<a class="agency-banner" href="<?php echo esc_url( j5_portal_url() ); ?>/quote" target="_blank" rel="noopener">
		<div class="agency-banner-icon">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M12 2L3 6v6c0 5 3.5 9.5 9 11 5.5-1.5 9-6 9-11V6l-9-4z"/>
				<path d="M9 12l2 2 4-4"/>
			</svg>
		</div>
		<div class="agency-banner-content">
			<div class="agency-banner-eyebrow">Departments &amp; Agencies</div>
			<div class="agency-banner-title">Get an instant quote</div>
			<div class="agency-banner-text">Skip the wait. Department and agency customers can pull live pricing on armor, plates, helmets, and gear directly from our agency portal.</div>
		</div>
		<div class="agency-banner-cta">
			Open Quote Tool <span class="agency-banner-cta-arrow">→</span>
		</div>
	</a>
	<?php endif; ?>

	<?php
	$body  = '';

	// Section 01 — Your Info
	$body .= '<div class="form-section">';
	$body .= '<div class="form-section-head"><span class="form-section-num">01</span><span class="form-section-title">Your Info</span></div>';
	$body .= '<div class="field-row cols-2">';
	$body .= j5_forms_field( array( 'name' => 'full_name', 'label' => 'Full Name', 'placeholder' => 'John Doe', 'required' => true ) );
	$body .= j5_forms_field( array( 'name' => 'title', 'label' => 'Title / Role', 'placeholder' => 'Quartermaster, Patrol Sgt., etc.' ) );
	$body .= '</div>';
	$body .= '<div class="field-row cols-2">';
	$body .= j5_forms_field( array( 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'placeholder' => 'you@example.com', 'required' => true ) );
	$body .= j5_forms_field( array( 'type' => 'tel', 'name' => 'phone', 'label' => 'Phone', 'placeholder' => '(555) 123-4567', 'required' => true ) );
	$body .= '</div>';
	$body .= '<div class="field-row">';
	$body .= j5_forms_field( array( 'name' => 'organization', 'label' => 'Department / Agency / Organization', 'placeholder' => 'e.g. Springfield PD', 'hint' => 'Leave blank if individual purchase.' ) );
	$body .= '</div>';
	$body .= '</div>';

	// Section 02 — What do you need
	$body .= '<div class="form-section">';
	$body .= '<div class="form-section-head"><span class="form-section-num">02</span><span class="form-section-title">What do you need?</span></div>';
	$body .= '<div class="field-row cols-2">';
	$body .= j5_forms_field( array( 'type' => 'select', 'name' => 'service', 'label' => 'Service / Product', 'placeholder' => 'Select a service…', 'options' => $services, 'required' => true ) );
	$body .= j5_forms_field( array( 'name' => 'quantity', 'label' => 'Quantity', 'placeholder' => 'e.g. 25 vests, 1 firearm, 50 patches', 'required' => true ) );
	$body .= '</div>';
	$body .= '<div class="field-row cols-2">';
	$body .= j5_forms_field( array( 'type' => 'select', 'name' => 'timeline', 'label' => 'Timeline', 'placeholder' => 'Select…', 'options' => $timelines ) );
	$body .= j5_forms_field( array( 'type' => 'select', 'name' => 'budget', 'label' => 'Budget Range', 'placeholder' => 'Optional', 'options' => $budgets ) );
	$body .= '</div>';
	$body .= '</div>';

	// Section 03 — Project details
	$body .= '<div class="form-section">';
	$body .= '<div class="form-section-head"><span class="form-section-num">03</span><span class="form-section-title">Project Details</span></div>';
	$body .= '<div class="field-row">';
	$body .= j5_forms_field( array( 'type' => 'textarea', 'name' => 'description', 'label' => 'Describe what you need', 'placeholder' => 'Specs, sizes, colors, artwork notes, NIJ level, anything that helps us quote accurately…', 'required' => true ) );
	$body .= '</div>';

	// File upload
	$body .= '<div class="field-row"><div class="field">';
	$body .= '<label class="field-label">Attach Files</label>';
	$body .= '<label class="file-drop" tabindex="0">';
	$body .= '<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.ai,.eps,.svg" hidden>';
	$body .= '<span class="file-drop-icon" aria-hidden="true">+</span>';
	$body .= '<div class="file-drop-text">Drop files here or click to browse</div>';
	$body .= '<div class="file-drop-hint">Artwork, specs, photos — PDF, JPG, PNG, AI, EPS, SVG up to 10 MB each</div>';
	$body .= '<div class="file-drop-list" hidden></div>';
	$body .= '</label>';
	$body .= '</div></div>';

	$body .= '<div class="field-row"><div class="field"><label class="check-row"><input type="checkbox" name="discuss_call" value="1"><span>I\'d like to discuss this on a phone call before committing</span></label></div></div>';
	$body .= '</div>';

	$body .= '<div class="form-footer">';
	$body .= '<p class="form-footer-note">Quotes are typically sent within one business day. Veteran/LE discounts applied automatically when applicable.</p>';
	$body .= '<button class="btn btn-primary" type="submit">Request Quote <span class="btn-arrow">→</span></button>';
	$body .= '</div>';

	echo j5_forms_wrap_form( 'quote', $body, 'has-banner' );
	?>
	</div>
	<?php

	return ob_get_clean();
}
