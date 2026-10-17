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
		'intro'   => 'Veteran and first-responder owned. We sell what we use and pack what we carry.',
		'cta'     => ['Contact Us', '/contact-us/'],
	],
	9041 => [
		'file'    => 'j5-faq-9041.html',
		'eyebrow' => 'Support',
		'intro'   => 'Answers to common questions about orders, shipping, discounts, and working with J5 Rescue Supply.',
		'cta'     => null,
	],
];
foreach ( $pages as $id => $p ) {
	$html = file_get_contents( "$dir/{$p['file']}" );
	if ( ! $html || ! get_post( $id ) ) { echo "$id: SKIPPED (missing file or page)\n"; continue; }
	$r = wp_update_post( [ 'ID' => $id, 'post_content' => $html, 'post_excerpt' => $p['intro'] ], true );
	if ( is_wp_error( $r ) ) { echo "$id: ERROR " . $r->get_error_message() . "\n"; continue; }
	update_post_meta( $id, '_wp_page_template', 'template-j5-service.php' );
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
