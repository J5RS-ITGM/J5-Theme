<?php
/**
 * J5 Apps — consolidated admin menu
 * ---------------------------------
 * Creates a single top-level "J5 Apps" menu in wp-admin and registers all J5
 * app pages under it: Fulfillment, Acknowledgments, Announcements.
 *
 * The individual admin modules define their page-render callbacks but no
 * longer register their own menu items (they check J5_APPS_MENU to skip
 * self-registration). This file is the single source of menu structure.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flag the individual admin modules check so they don't add their own menu
 * items (avoids duplicate Settings entries).
 */
const J5_APPS_MENU = true;

add_action( 'admin_menu', function () {
	$cap  = 'manage_options';
	$slug = 'j5-apps';

	add_menu_page(
		'J5 Apps',
		'J5 Apps',
		$cap,
		$slug,
		'j5_apps_landing_page',
		'dashicons-screenoptions',
		56 // just below WooCommerce.
	);

	// Landing (rename the auto-created first submenu).
	add_submenu_page( $slug, 'J5 Apps', 'Overview', $cap, $slug, 'j5_apps_landing_page' );

	if ( function_exists( 'j5_fulfillment_settings_page' ) ) {
		add_submenu_page( $slug, 'Fulfillment Statuses', 'Fulfillment', $cap, 'j5-fulfillment', 'j5_fulfillment_settings_page' );
	}
	if ( function_exists( 'j5_ack_settings_page' ) ) {
		add_submenu_page( $slug, 'Product Acknowledgments', 'Acknowledgments', $cap, 'j5-acknowledgments', 'j5_ack_settings_page' );
	}
	if ( function_exists( 'j5_announce_settings_page' ) ) {
		add_submenu_page( $slug, 'Announcements', 'Announcements', $cap, 'j5-announcements', 'j5_announce_settings_page' );
	}
	if ( function_exists( 'j5_ship_settings_page' ) ) {
		add_submenu_page( $slug, 'Shipping Restrictions', 'Shipping Restrictions', $cap, 'j5-shipping-restrictions', 'j5_ship_settings_page' );
	}
}, 9 );

/**
 * Simple landing page linking the apps.
 */
function j5_apps_landing_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>J5 Apps</h1>
		<p class="description">Custom storefront tools for J5 Rescue Supply.</p>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;margin-top:20px;max-width:800px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=j5-fulfillment' ) ); ?>" class="card" style="display:block;padding:18px;background:#fff;border:1px solid #ccd0d4;text-decoration:none;">
				<strong style="font-size:15px;">Fulfillment</strong>
				<p style="margin:6px 0 0;color:#555;">Stock/lead-time labels: In Stock, Special Order, Made to Order, Out of Stock, Discontinued, plus custom.</p>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=j5-acknowledgments' ) ); ?>" class="card" style="display:block;padding:18px;background:#fff;border:1px solid #ccd0d4;text-decoration:none;">
				<strong style="font-size:15px;">Acknowledgments</strong>
				<p style="margin:6px 0 0;color:#555;">Purchase acknowledgments (ITAR, age 18+, etc.) enforced at checkout and recorded on orders.</p>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=j5-announcements' ) ); ?>" class="card" style="display:block;padding:18px;background:#fff;border:1px solid #ccd0d4;text-decoration:none;">
				<strong style="font-size:15px;">Announcements</strong>
				<p style="margin:6px 0 0;color:#555;">Site-wide, category, and product banners with header, body, color, and icon.</p>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=j5-shipping-restrictions' ) ); ?>" class="card" style="display:block;padding:18px;background:#fff;border:1px solid #ccd0d4;text-decoration:none;">
				<strong style="font-size:15px;">Shipping Restrictions</strong>
				<p style="margin:6px 0 0;color:#555;">Block products (by category, tag, or ID) from shipping to certain states or cities. Hub + local rules, enforced at checkout.</p>
			</a>
		</div>
	</div>
	<?php
}
