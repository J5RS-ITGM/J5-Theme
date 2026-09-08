<?php
/**
 * J5 Rescue Supply — standalone theme functions
 *
 * Migrated from astra-child (2026-09). No parent theme.
 * Loads inc/ modules via J5-<AREA>-REQUIRES marker blocks.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Version constant. Name kept from the child theme for compatibility —
 * inc/j5-cart-checkout-setup.php (and possibly future modules) reference it.
 */
define( 'CHILD_THEME_ASTRA_CHILD_VERSION', '2.0.0' );

// === J5-THEME-SETUP-REQUIRES-START ===
// Standalone theme declarations (title tag, thumbnails, WooCommerce support,
// custom logo, HTML5). Previously supplied by the Astra parent.
require_once get_stylesheet_directory() . '/inc/j5-theme-setup.php';
// === J5-THEME-SETUP-REQUIRES-END ===

/**
 * Enqueue the base stylesheet. No parent dependency — this theme is the base.
 */
function child_enqueue_styles() {
	wp_enqueue_style(
		'astra-child-theme-css',
		get_stylesheet_directory_uri() . '/style.css',
		array(),
		CHILD_THEME_ASTRA_CHILD_VERSION,
		'all'
	);
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );


add_action('wp_logout','auto_redirect_after_logout');

function auto_redirect_after_logout(){
  wp_safe_redirect( home_url() );
  exit;
}
// === J5-CAT-CONTENT-REQUIRES-START ===
// Editable category pages: native description above grid + buyer's guide below.
require_once get_stylesheet_directory() . '/inc/j5-category-content.php';
// === J5-CAT-CONTENT-REQUIRES-END ===

// J5 Home Template setup
require_once get_stylesheet_directory() . '/inc/j5-home-setup.php';
require_once get_stylesheet_directory() . '/inc/j5-typeahead-endpoint.php';

// J5 Shop + Single Product templates
require_once get_stylesheet_directory() . '/inc/j5-shop-setup.php';


// J5 Dev Pages - no-cache enforcement (2026-04-17)
require_once get_stylesheet_directory() . '/inc/j5-dev-nocache.php';

// J5 structured product description shortcodes
require_once get_stylesheet_directory() . '/inc/j5-description-shortcodes.php';

// J5 structured product specifications shortcodes
require_once get_stylesheet_directory() . '/inc/j5-specs-shortcodes.php';

// J5 specifications metabox (admin UI for _j5_spec_content)
require_once get_stylesheet_directory() . '/inc/j5-specs-metabox.php';

// J5 Cart & Checkout template routing + assets
require_once get_stylesheet_directory() . '/inc/j5-cart-checkout-setup.php';

// J5 Checkout
require_once get_stylesheet_directory() . '/inc/j5-checkout-setup.php';

/* === J5-CART-REQUIRE-START === */
if ( file_exists( get_stylesheet_directory() . '/inc/j5-cart-setup.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/j5-cart-setup.php';
}
/* === J5-CART-REQUIRE-END === */
add_filter( 'woocommerce_shipping_calculator_enable_postcode', '__return_true', 99 );


/* === J5-CHROME-REQUIRE-START === */
if ( file_exists( get_stylesheet_directory() . '/inc/j5-chrome-setup.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/j5-chrome-setup.php';
}
/* === J5-CHROME-REQUIRE-END === */
// ----- J5 News archive helpers -----
   require_once get_stylesheet_directory() . '/inc/j5-news-setup.php';

// === J5-SINGLE-POST-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-single-setup.php';
// === J5-SINGLE-POST-REQUIRES-END ===
// === J5-LANDING-REQUIRES-START ===
// Added by j5-landing-patch install.sh. Remove this block (including
// sentinels) to uninstall the landing page system.
require_once get_stylesheet_directory() . '/inc/j5-landing-config.php';
require_once get_stylesheet_directory() . '/inc/j5-landing-bootstrap.php';
// === J5-LANDING-REQUIRES-END ===

// === J5-ARCHIVE-RENDER-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-archive-config.php';
require_once get_stylesheet_directory() . '/inc/j5-archive-render.php';

add_action( 'wp_enqueue_scripts', function() {
    if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() || is_post_type_archive( 'product' ) ) ) {
        wp_enqueue_style(
            'j5-archive',
            get_stylesheet_directory_uri() . '/assets/css/j5-archive.css',
            array(),
            '1.0.0'
        );
    }
}, 25 );
// === J5-ARCHIVE-RENDER-REQUIRES-END ===

// === J5-MOBILE-UX-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-variation-price.php';
require_once get_stylesheet_directory() . '/inc/j5-mobile-nav.php';
// === J5-MOBILE-UX-REQUIRES-END ===

// === J5-VAR-CASCADE-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-variation-display.php';
// === J5-VAR-CASCADE-REQUIRES-END ===

// === J5-VAR-CONFIG-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-variation-config.php';
// === J5-VAR-CONFIG-REQUIRES-END ===

// === J5-PORTAL-FLAG-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-portal-flag.php';
// === J5-PORTAL-FLAG-REQUIRES-END ===

// === J5-FULFILLMENT-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-fulfillment.php';
if ( is_admin() ) {
	require_once get_stylesheet_directory() . '/inc/j5-fulfillment-admin.php';
}
// === J5-FULFILLMENT-REQUIRES-END ===

// === J5-HUB-DEFINITIONS-REQUIRES-START ===
// Read-only REST endpoint exposing fulfillment + acknowledgment definitions
// for hub vocabulary sync. Loaded unconditionally: rest_api_init fires on
// front-end requests, not only in wp-admin.
require_once get_stylesheet_directory() . '/inc/j5-hub-definitions.php';
// === J5-HUB-DEFINITIONS-REQUIRES-END ===

// === J5-ACKNOWLEDGMENTS-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-acknowledgments.php';
if ( is_admin() ) {
	require_once get_stylesheet_directory() . '/inc/j5-acknowledgments-admin.php';
}
// === J5-ACKNOWLEDGMENTS-REQUIRES-END ===

// === J5-ANNOUNCEMENTS-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-announcements.php';
if ( is_admin() ) {
	// Menu module defines J5_APPS_MENU (checked by the admin pages), load first.
	require_once get_stylesheet_directory() . '/inc/j5-apps-menu.php';
	require_once get_stylesheet_directory() . '/inc/j5-announcements-admin.php';
}
// === J5-ANNOUNCEMENTS-REQUIRES-END ===

// === J5-SHIPPING-RESTRICTIONS-REQUIRES-START ===
require_once get_stylesheet_directory() . '/inc/j5-shipping-restrictions.php';
if ( is_admin() ) {
	require_once get_stylesheet_directory() . '/inc/j5-shipping-restrictions-admin.php';
}
// === J5-SHIPPING-RESTRICTIONS-REQUIRES-END ===

/* === J5-MOBILE-INLINE-START === */
/**
 * J5 Mobile CSS — inlined via wp_head priority 999
 * 
 * External stylesheet approach was unable to override desktop rules on this
 * site (LiteSpeed optimization pipeline interaction). Inline injection at
 * priority 999 runs after every other stylesheet load and applies reliably.
 * Real DOM selectors confirmed via grep on rendered HTML.
 */
add_action( 'wp_head', 'j5_mobile_inline_css', 999 );
function j5_mobile_inline_css() {
    if ( ! function_exists( 'is_shop' ) ) return;
    if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_post_type_archive( 'product' ) ) ) return;
    ?>
<style id="j5-mobile-inline">
/* Universal overflow guards (apply at all widths) */
body.j5-shop-template,
body.j5-shop-template .j5-shop,
body.j5-shop-template .j5-container {
    overflow-x: hidden;
    max-width: 100%;
}

@media (max-width: 640px) {
    body.j5-shop-template * {
        box-sizing: border-box;
    }
    body.j5-shop-template .j5-shop-layout,
    body.j5-shop-template .j5-container {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    body.j5-shop-template .j5-shop-layout {
        grid-template-columns: 1fr !important;
    }
    body.j5-shop-template .j5-filters {
        margin-bottom: 20px !important;
    }
    body.j5-shop-template .j5-prod-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    body.j5-shop-template .j5-prod-card {
        min-width: 0 !important;
        max-width: 100% !important;
        overflow: hidden !important;
    }
    body.j5-shop-template .j5-prod-img-wrap,
    body.j5-shop-template .j5-prod-img-wrap img {
        max-width: 100% !important;
    }
    body.j5-shop-template .j5-prod-img-wrap img {
        height: auto !important;
    }
    body.j5-shop-template .j5-prod-body {
        padding: 10px !important;
    }
    body.j5-shop-template .j5-prod-brand-row {
        margin-bottom: 4px !important;
    }
    body.j5-shop-template .j5-prod-brand {
        font-size: 9px !important;
        letter-spacing: 0.10em !important;
    }
    body.j5-shop-template .j5-prod-rating {
        font-size: 10px !important;
    }
    body.j5-shop-template .j5-prod-name {
        font-size: 12px !important;
        line-height: 1.3 !important;
        margin-bottom: 6px !important;
        display: -webkit-box !important;
        -webkit-line-clamp: 2 !important;
        -webkit-box-orient: vertical !important;
        overflow: hidden !important;
    }
    body.j5-shop-template .j5-prod-specs {
        display: none !important;
    }
    body.j5-shop-template .j5-prod-price-row {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 6px !important;
        padding-top: 6px !important;
    }
    body.j5-shop-template .j5-prod-price,
    body.j5-shop-template .j5-prod-price .woocommerce-Price-amount,
    body.j5-shop-template .j5-prod-price .amount {
        font-size: 14px !important;
        line-height: 1 !important;
        text-align: left !important;
        width: 100% !important;
    }
    body.j5-shop-template .j5-prod-add {
        font-size: 11px !important;
        padding: 8px 10px !important;
        width: 100% !important;
        text-align: center !important;
        white-space: nowrap !important;
        letter-spacing: 0.10em !important;
    }
    body.j5-shop-template .j5-prod-badge {
        font-size: 8px !important;
        padding: 2px 5px !important;
        letter-spacing: 0.10em !important;
    }
    body.j5-shop-template .j5-prod-sku {
        font-size: 9px !important;
        margin-top: 6px !important;
    }
    body.j5-shop-template .j5-toolbar-inner {
        flex-wrap: wrap !important;
        gap: 8px !important;
        padding-right: 0 !important;
    }
    body.j5-shop-template .j5-result-count {
        flex-basis: 100% !important;
        font-size: 11px !important;
    }
    body.j5-shop-template .j5-toolbar-actions {
        flex-wrap: wrap !important;
        gap: 6px !important;
        width: 100% !important;
    }
    body.j5-shop-template .j5-sort-form,
    body.j5-shop-template .j5-perpage-form {
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }
    body.j5-shop-template .j5-sort-select,
    body.j5-shop-template .j5-perpage-form select {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }
    body.j5-shop-template .j5-view-toggle {
        flex-shrink: 0 !important;
    }
    body.j5-shop-template .j5-view-toggle button {
        width: 36px !important;
        height: 36px !important;
        flex-shrink: 0 !important;
    }
    body.j5-shop-template .j5-pill {
        font-size: 9px !important;
        padding: 4px 7px !important;
    }
}
@media (max-width: 380px) {
    body.j5-shop-template .j5-prod-name {
        font-size: 11px !important;
    }
    body.j5-shop-template .j5-prod-price,
    body.j5-shop-template .j5-prod-price .amount {
        font-size: 13px !important;
    }
}
</style>
    <?php
}
/* === J5-MOBILE-INLINE-END === */


/* === J5-SERVICE-START === */

// Require the metabox + block patterns modules.
require_once get_stylesheet_directory() . '/inc/j5-service-meta.php';
require_once get_stylesheet_directory() . '/inc/j5-service-patterns.php';

/**
 * Inline-inject service page CSS at wp_head priority 999.
 *
 * Uses the same workaround pattern as J5-MOBILE-INLINE — the external
 * stylesheet pipeline is unreliable through LiteSpeed UCSS / Critical CSS,
 * so we inject the CSS directly into <head>. Single source of truth is
 * still the file at assets/css/j5-service.css.
 *
 * Gated to pages that actually use the service template, so we don't add
 * weight to every page on the site.
 */
add_action( 'wp_head', 'j5_service_inline_css', 999 );
function j5_service_inline_css() {
	if ( ! is_page() ) {
		return;
	}
	if ( ! is_page_template( 'template-j5-service.php' ) ) {
		return;
	}

	$css_path = get_stylesheet_directory() . '/assets/css/j5-service.css';
	if ( ! file_exists( $css_path ) ) {
		return;
	}

	// Read once per request — small file, cached by OPcache file stat.
	$css = file_get_contents( $css_path );
	if ( empty( $css ) ) {
		return;
	}

	echo "\n<style id=\"j5-service-inline\">\n" . $css . "\n</style>\n";
}

/**
 * Also enqueue the external stylesheet as a backup. If the LiteSpeed pipeline
 * issue is ever resolved, this will pick up the rules cleanly. If it's still
 * broken, the inline injection above is authoritative — duplicates don't hurt.
 *
 * Comment this out if you want to rely only on inline injection.
 */
add_action( 'wp_enqueue_scripts', 'j5_service_enqueue_css' );
function j5_service_enqueue_css() {
	if ( ! is_page() || ! is_page_template( 'template-j5-service.php' ) ) {
		return;
	}
	wp_enqueue_style(
		'j5-service',
		get_stylesheet_directory_uri() . '/assets/css/j5-service.css',
		array(),
		filemtime( get_stylesheet_directory() . '/assets/css/j5-service.css' )
	);
}

/* === J5-SERVICE-END === */

/* === J5-FORMS-START === */

// Native contact + quote form engine (replaces Formidable).
// Provides [j5_contact_form] and [j5_quote_form] shortcodes,
// j5_submission custom post type, Settings → J5 Forms admin page,
// honeypot + time-trap spam protection, and optional Odoo CRM push.
require_once get_stylesheet_directory() . '/inc/j5-forms.php';

/* === J5-FORMS-END === */

// === J5-ACCOUNT-V1 START ===
require_once get_stylesheet_directory() . '/inc/j5-account-setup.php'; // J5-ACCOUNT-V1
// === J5-ACCOUNT-V1 END ===
