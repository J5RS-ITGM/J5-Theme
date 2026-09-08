<?php
/**
 * J5 Service Page — Block Patterns
 *
 * Registers reusable block layouts under a "J5 Service" category in the block
 * inserter. Editors can insert these into any page (especially the J5 Service
 * Page template) and edit the text/links inline — no Elementor needed.
 *
 * Patterns provided:
 *   1. Service Highlight Card     — Icon-style heading + short blurb (3-up)
 *   2. Two-Column Intro           — Lead paragraph + at-a-glance sidebar
 *   3. Process Steps              — Numbered 3-step process
 *   4. Pricing Box                — Service + price + features + CTA
 *   5. Contact CTA Strip          — Phone / email / quote button
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the pattern category so all J5 patterns group together in the inserter.
 */
add_action( 'init', 'j5_register_pattern_category' );
function j5_register_pattern_category() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}
	register_block_pattern_category(
		'j5-service',
		array( 'label' => __( 'J5 Service Page', 'astra-child' ) )
	);
}

/**
 * Register the patterns.
 */
add_action( 'init', 'j5_register_service_patterns' );
function j5_register_service_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	/* ---------------------------------------------------------------------
	 * 1. Service Highlight Card — 3-up grid of feature cards
	 * ------------------------------------------------------------------- */
	register_block_pattern(
		'j5-service/highlight-cards',
		array(
			'title'       => __( 'Service Highlight Cards (3-up)', 'astra-child' ),
			'description' => __( 'Three side-by-side cards for showcasing service features.', 'astra-child' ),
			'categories'  => array( 'j5-service' ),
			'content'     => '<!-- wp:columns {"className":"j5-highlight-cards"} -->
<div class="wp-block-columns j5-highlight-cards">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"className":"j5-highlight-title"} -->
<h3 class="wp-block-heading j5-highlight-title">FAST TURNAROUND</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Most jobs completed within 5–7 business days. Rush service available.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"className":"j5-highlight-title"} -->
<h3 class="wp-block-heading j5-highlight-title">PRO QUALITY</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Industrial-grade equipment and field-tested processes used on every job.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"className":"j5-highlight-title"} -->
<h3 class="wp-block-heading j5-highlight-title">DEPT. PRICING</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Volume discounts for departments and agencies. Contact us for a quote.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->',
		)
	);

	/* ---------------------------------------------------------------------
	 * 2. Two-Column Intro — lead paragraph + at-a-glance sidebar
	 * ------------------------------------------------------------------- */
	register_block_pattern(
		'j5-service/two-col-intro',
		array(
			'title'       => __( 'Two-Column Intro', 'astra-child' ),
			'description' => __( 'Lead paragraph on the left, quick-reference info card on the right.', 'astra-child' ),
			'categories'  => array( 'j5-service' ),
			'content'     => '<!-- wp:columns {"className":"j5-two-col-intro"} -->
<div class="wp-block-columns j5-two-col-intro">

<!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%">
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">What we offer</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Replace this with a description of the service. Cover what it includes, who it\'s for, and any prerequisites the customer needs to know about.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Add a second paragraph if more detail is needed. Keep it conversational — this is the section customers read first.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%","className":"j5-info-card"} -->
<div class="wp-block-column j5-info-card" style="flex-basis:33.33%">
<!-- wp:heading {"level":3,"className":"j5-info-title"} -->
<h3 class="wp-block-heading j5-info-title">AT A GLANCE</h3>
<!-- /wp:heading -->
<!-- wp:list -->
<ul><li><strong>Turnaround:</strong> 5–7 days</li><li><strong>Minimum:</strong> 1 piece</li><li><strong>Pricing:</strong> Per item</li><li><strong>Files:</strong> AI / EPS / PDF</li></ul>
<!-- /wp:list -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->',
		)
	);

	/* ---------------------------------------------------------------------
	 * 3. Process Steps — 3-step numbered process
	 * ------------------------------------------------------------------- */
	register_block_pattern(
		'j5-service/process-steps',
		array(
			'title'       => __( 'Process Steps (1-2-3)', 'astra-child' ),
			'description' => __( 'Numbered 3-step process explainer.', 'astra-child' ),
			'categories'  => array( 'j5-service' ),
			'content'     => '<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">How it works</h2>
<!-- /wp:heading -->

<!-- wp:columns {"className":"j5-process-steps"} -->
<div class="wp-block-columns j5-process-steps">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"className":"j5-step-num"} -->
<h3 class="wp-block-heading j5-step-num">01</h3>
<!-- /wp:heading -->
<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Submit your details</h4>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Fill out the request form or give us a call. Include any artwork, specs, or item details up front.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"className":"j5-step-num"} -->
<h3 class="wp-block-heading j5-step-num">02</h3>
<!-- /wp:heading -->
<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Confirm the quote</h4>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We send a written quote with timeline. Approve and we move to production. No surprise charges.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"className":"j5-step-num"} -->
<h3 class="wp-block-heading j5-step-num">03</h3>
<!-- /wp:heading -->
<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Pickup or ship</h4>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Local pickup at our Northern IL location, or shipped to your department/address. Tracking provided.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->',
		)
	);

	/* ---------------------------------------------------------------------
	 * 4. Pricing Box — single tier pricing card
	 * ------------------------------------------------------------------- */
	register_block_pattern(
		'j5-service/pricing-box',
		array(
			'title'       => __( 'Pricing Box', 'astra-child' ),
			'description' => __( 'Service tier card with price, features, and a CTA button.', 'astra-child' ),
			'categories'  => array( 'j5-service' ),
			'content'     => '<!-- wp:group {"className":"j5-pricing-box","layout":{"type":"constrained","contentSize":"560px"}} -->
<div class="wp-block-group j5-pricing-box">

<!-- wp:heading {"level":3,"className":"j5-pricing-tier"} -->
<h3 class="wp-block-heading j5-pricing-tier">FFL TRANSFER</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"j5-pricing-amount"} -->
<p class="j5-pricing-amount">$35<span class="j5-pricing-unit">/firearm</span></p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"j5-pricing-features"} -->
<ul class="j5-pricing-features"><li>Background check (NICS) included</li><li>Standard processing time</li><li>By appointment only</li><li>LE/military discount available</li></ul>
<!-- /wp:list -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"j5-btn-primary"} -->
<div class="wp-block-button j5-btn-primary"><a class="wp-block-button__link wp-element-button" href="/contact/">Request Transfer</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</div>
<!-- /wp:group -->',
		)
	);

	/* ---------------------------------------------------------------------
	 * 5. Contact CTA Strip — bottom-of-page contact band
	 * ------------------------------------------------------------------- */
	register_block_pattern(
		'j5-service/contact-cta',
		array(
			'title'       => __( 'Contact CTA Strip', 'astra-child' ),
			'description' => __( 'Bottom-of-page contact band with phone, email, and quote button.', 'astra-child' ),
			'categories'  => array( 'j5-service' ),
			'content'     => '<!-- wp:group {"className":"j5-contact-cta","layout":{"type":"constrained"}} -->
<div class="wp-block-group j5-contact-cta">

<!-- wp:heading {"level":2,"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Questions? Let\'s talk.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Call us, email, or request a written quote. We respond same business day.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"j5-btn-primary"} -->
<div class="wp-block-button j5-btn-primary"><a class="wp-block-button__link wp-element-button" href="tel:+16304424938">Call Us</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"j5-btn-outline"} -->
<div class="wp-block-button j5-btn-outline"><a class="wp-block-button__link wp-element-button" href="/contact/">Request a Quote</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</div>
<!-- /wp:group -->',
		)
	);
}
