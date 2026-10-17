<?php
/**
 * Creates (or updates) the Client Portal & QuarterMaster page on
 * template-j5-portal.php. Re-runnable.
 * Run: wp eval-file wp-content/themes/j5-theme/content/pages/create-portal-page.php
 */
$slug = 'client-portal';
$page = get_page_by_path( $slug );
$args = array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Client Portal & QuarterMaster',
	'post_name'    => $slug,
	'post_content' => '',
	'post_excerpt' => 'One portal for agency ordering, sizing, tracking and issuance of uniforms and equipment.',
);
if ( $page ) $args['ID'] = $page->ID;
$id = wp_insert_post( $args, true );
if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
update_post_meta( $id, '_wp_page_template', 'template-j5-portal.php' );
if ( function_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
	update_post_meta( $id, 'rank_math_title', 'Client Portal & QuarterMaster for Agencies | J5 Rescue Supply' );
	update_post_meta( $id, 'rank_math_description', 'Order, size, track and issue uniforms and body armor for your agency in one portal. QuarterMaster workflows built around how your department runs.' );
}
WP_CLI::success( "Page $id: " . get_permalink( $id ) );
