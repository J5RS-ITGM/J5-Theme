<?php
/**
 * J5 Portal Visibility Flag
 * -------------------------
 * Single switch controlling whether LE / Agency Portal UI is shown across the
 * storefront (banners, badges, CTAs, inline mentions).
 *
 * Context: the original portal pointed at ops.j5rescue.com (Odoo). That is
 * being replaced with a separate, non-Odoo portal. Rather than deleting the
 * UI from ~17 templates and losing the markup, everything is gated on this
 * flag. When the new portal is ready: set J5_PORTAL_ENABLED to true and update
 * j5_portal_url() to the new address — the UI returns everywhere at once.
 *
 * NOTE: this flag governs PRESENTATION only. The J5 Forms → Odoo integration
 * is a separate system with its own enable toggle (currently off by default in
 * inc/j5-forms.php) and is intentionally untouched here.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Master switch for all portal-related UI.
 * Set to true to restore portal banners, badges, and links sitewide.
 */
if ( ! defined( 'J5_PORTAL_ENABLED' ) ) {
	define( 'J5_PORTAL_ENABLED', false );
}

/**
 * Whether portal UI should render.
 *
 * @return bool
 */
function j5_portal_enabled() {
	return defined( 'J5_PORTAL_ENABLED' ) && J5_PORTAL_ENABLED;
}

/**
 * The portal URL. Change this once when the replacement portal goes live.
 *
 * @return string
 */
function j5_portal_url() {
	return apply_filters( 'j5_portal_url', 'https://ops.j5rescue.com' );
}
