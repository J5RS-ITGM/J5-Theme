<?php
/**
 * J5 Dev Pages — No-Cache Enforcement
 *
 * Purpose: make /home-v2/ and /shop-v2/ (and any other dev preview pages)
 * instantly reflect code changes without fighting LiteSpeed, browser cache,
 * or carrier proxies.
 *
 * Added 2026-04-17 to stop wasting dev time on cache purges.
 *
 * Targets: URLs containing home-v2, shop-v2, j5_preview=1, or ?dev=1
 *
 * When one of these pages is requested, we:
 *   1. Tell LiteSpeed: do not cache this response
 *   2. Tell the browser: do not cache (no-store, no-cache, must-revalidate)
 *   3. Tell proxies / CDNs / carriers: private, no-cache
 *   4. Add noindex so Google doesn't index dev preview URLs
 *   5. Force fresh asset URLs by appending a timestamp-based query string
 *      to our j5-home.css / j5-shop.css enqueues so the browser can never
 *      hold a stale CSS file.
 *
 * To disable everything: comment out the require_once line in functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Detect if current request is a dev preview page
 */
function j5_is_dev_page() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( strpos( $uri, '/home-v2' ) !== false ) return true;
    if ( strpos( $uri, '/shop-v2' ) !== false ) return true;
    if ( isset( $_GET['j5_preview'] ) && $_GET['j5_preview'] === '1' ) return true;
    if ( isset( $_GET['dev'] ) && $_GET['dev'] === '1' ) return true;
    return false;
}

/**
 * Send no-cache headers on dev pages
 */
function j5_dev_nocache_headers() {
    if ( ! j5_is_dev_page() ) return;

    // Browser + proxy no-cache
    nocache_headers();
    header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'Pragma: no-cache' );
    header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );

    // LiteSpeed Cache explicit opt-out
    header( 'X-LiteSpeed-Cache-Control: no-cache, no-store, private' );

    // Tell Google not to index dev previews
    header( 'X-Robots-Tag: noindex, nofollow' );
}
add_action( 'send_headers', 'j5_dev_nocache_headers', 1 );

/**
 * Same opt-out via LiteSpeed's own action hook for good measure
 */
function j5_dev_litespeed_nocache() {
    if ( ! j5_is_dev_page() ) return;
    if ( defined( 'LSCWP_V' ) ) {
        do_action( 'litespeed_control_set_nocache', 'J5 dev page' );
        do_action( 'litespeed_control_set_private' );
    }
}
add_action( 'wp', 'j5_dev_litespeed_nocache' );

/**
 * On dev pages, re-version our J5 stylesheets with the current timestamp
 * so the browser refetches them on every request.
 *
 * Hooks at priority 999 so it runs AFTER j5-home-setup.php's enqueue.
 */
function j5_dev_bust_asset_versions() {
    if ( ! j5_is_dev_page() ) return;

    global $wp_styles, $wp_scripts;
    $ts = (string) time();

    $targets_css = array( 'j5-home', 'j5-home-style', 'j5-shop', 'j5-shop-style' );
    $targets_js  = array( 'j5-shop', 'j5-shop-script', 'j5-home-script' );

    if ( isset( $wp_styles ) && ! empty( $wp_styles->registered ) ) {
        foreach ( $wp_styles->registered as $handle => $obj ) {
            foreach ( $targets_css as $match ) {
                if ( $handle === $match || strpos( $handle, 'j5-' ) === 0 ) {
                    $obj->ver = $ts;
                    break;
                }
            }
        }
    }
    if ( isset( $wp_scripts ) && ! empty( $wp_scripts->registered ) ) {
        foreach ( $wp_scripts->registered as $handle => $obj ) {
            if ( strpos( $handle, 'j5-' ) === 0 ) {
                $obj->ver = $ts;
            }
        }
    }
}
add_action( 'wp_enqueue_scripts', 'j5_dev_bust_asset_versions', 999 );

/**
 * Add a small visible dev-mode badge in the bottom-left corner so you never
 * forget you're on a dev URL. Only shown to logged-in admins.
 */
function j5_dev_badge() {
    if ( ! j5_is_dev_page() ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div id="j5-dev-badge" style="position:fixed;bottom:12px;left:12px;z-index:999999;background:#c8102e;color:#fff;font:600 11px/1 system-ui,sans-serif;padding:6px 10px;border-radius:3px;letter-spacing:0.5px;text-transform:uppercase;box-shadow:0 2px 8px rgba(0,0,0,0.4);pointer-events:none;user-select:none;">DEV · NO CACHE</div>
    <?php
}
add_action( 'wp_footer', 'j5_dev_badge', 999 );
