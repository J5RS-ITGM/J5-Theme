<?php
/**
 * Applies the content/pages/*.html files to their WordPress pages and moves
 * them onto the J5 Service Page template (off Elementor).
 *
 * Run:  wp eval-file wp-content/themes/j5-theme/content/pages/apply-pages.php
 *
 * Re-runnable: overwrites page content each run. Elementor layout data is
 * left in place until the final Elementor cleanup.
 */
$dir = __DIR__;
$pages = [
	6183 => [
		'file'    => 'j5-about-us-6183.html',
		'eyebrow' => 'Our Story · Est. 2017',
		'intro'   => 'Built by working responders. Trusted by agencies.',
		'cta'     => ['Contact Us', '/contact-us/'],
	],
	9041 => [
		'file'    => 'j5-faq-9041.html',
		'eyebrow' => 'Support',
		'intro'   => 'Answers to common questions about orders, shipping, discounts, and working with J5 Rescue Supply.',
		'cta'     => null,
	],
	86942 => [
		'file'    => 'j5-ffl-services-86942.html',
		'eyebrow' => 'Services · FFL',
		'intro'   => 'Type 07 FFL / Class 2 SOT manufacturer in Northern Illinois, focused on prototyping and concept design.',
		'cta'     => ['Contact Us', '/contact-us/'],
	],
	84492 => [
		'file'    => 'j5-kit-packing-84492.html',
		'eyebrow' => 'Services · Custom Kit Packing',
		'intro'   => 'Built to spec. Ready to deploy. Medical, trauma, and tactical kits for departments, agencies, and individual responders.',
		'cta'     => ['Request a Quote', '/request-a-quote/'],
	],
	'services' => [
		'file'    => 'j5-services.html',
		'title'   => 'Services',
		'eyebrow' => 'What We Do',
		'intro'   => 'Kit packing, FFL services, agency procurement, and the guidance to get it right.',
		'cta'     => ['Request a Quote', '/request-a-quote/'],
	],
];
foreach ( $pages as $id => $p ) {
	// String keys are slugs: find the page, or create it as a draft-free published page.
	if ( ! is_int( $id ) ) {
		$existing = get_page_by_path( $id );
		$id = $existing ? $existing->ID : wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $id, 'post_title' => $p['title'] ] );
		if ( ! $id || is_wp_error( $id ) ) { echo "Could not create page {$p['title']}\n"; continue; }
	}
	$html = file_get_contents( "$dir/{$p['file']}" );
	if ( ! $html || ! get_post( $id ) ) { echo "$id: SKIPPED (missing file or page)\n"; continue; }
	// Set the template first: wp_update_post rejects a save when the page still
	// points at a template that no longer exists (old Elementor/Astra ones).
	update_post_meta( $id, '_wp_page_template', 'template-j5-service.php' );
	$r = wp_update_post( [ 'ID' => $id, 'post_content' => $html, 'post_excerpt' => $p['intro'] ], true );
	if ( is_wp_error( $r ) ) { echo "$id: ERROR " . $r->get_error_message() . "\n"; continue; }
	update_post_meta( $id, '_j5_service_eyebrow', $p['eyebrow'] );
	if ( $p['cta'] ) {
		update_post_meta( $id, '_j5_service_cta_label', $p['cta'][0] );
		update_post_meta( $id, '_j5_service_cta_url', $p['cta'][1] );
	} else {
		delete_post_meta( $id, '_j5_service_cta_label' );
		delete_post_meta( $id, '_j5_service_cta_url' );
	}
	delete_post_meta( $id, '_elementor_edit_mode' );
	clean_post_cache( $id );
	echo "$id: " . get_the_title( $id ) . " -> " . get_permalink( $id ) . "\n";
}

// Point the header's "Services" dropdown parent at the Services page instead of "#".
$services = get_page_by_path( 'services' );
$primary  = get_nav_menu_locations()['primary'] ?? 0;
if ( $services && $primary ) {
	foreach ( (array) wp_get_nav_menu_items( $primary ) as $item ) {
		if ( $item->title === 'Services' && ! $item->menu_item_parent && $item->type === 'custom' ) {
			wp_update_nav_menu_item( $primary, $item->ID, [
				'menu-item-title'     => 'Services',
				'menu-item-object-id' => $services->ID,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $item->menu_order,
			] );
			echo "Header menu: Services now links to " . get_permalink( $services ) . "\n";
		}
	}
}
