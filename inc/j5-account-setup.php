<?php
/**
 * J5 Rescue Supply — My Account setup (v2.0.0 — custom-template edition)
 * ============================================================================
 *
 *  - Enqueues j5-account.css conditionally on the WC My Account page
 *  - Loads Google Fonts directly
 *  - Injects an agency banner above the WC content for logged-out visitors
 *  - The dashboard, navigation, and form-login views are now rendered by
 *    custom WC templates in /woocommerce/myaccount/, NOT by hooks here.
 *
 *  Sentinel: J5-ACCOUNT-V1
 * ============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Is the current request the WC My Account page (or any of its endpoints)?
 */
function j5_account_is_my_account_page() {
        if ( ! function_exists( 'is_account_page' ) ) {
                return false;
        }
        return is_account_page();
}

/**
 * Enqueue the account CSS conditionally.
 */
add_action( 'wp_enqueue_scripts', function () {
        if ( ! j5_account_is_my_account_page() ) { return; }

        $version = '2.0.0';

        $deps = array();
        foreach ( array( 'woocommerce-general', 'woocommerce-layout' ) as $handle ) {
                if ( wp_style_is( $handle, 'registered' ) ) {
                        $deps[] = $handle;
                }
        }

        wp_enqueue_style(
                'j5-account',
                get_stylesheet_directory_uri() . '/assets/css/j5-account.css',
                $deps,
                $version
        );
}, 999 );

/**
 * Brand fonts are self-hosted and enqueued site-wide via inc/j5-fonts.php
 * (j5_enqueue_fonts, priority 5), so the My Account page no longer prints
 * Google Fonts <link> tags into the head.
 */

/**
 * Inject the agency banner ABOVE the WC content for logged-out visitors.
 *
 * Hook: woocommerce_before_customer_login_form fires inside the
 * form-login.php template before our hero. We hook earlier-priority so
 * the banner sits at the very top.
 */
add_action( 'woocommerce_before_customer_login_form', function () {
        // Portal UI gated — see inc/j5-portal-flag.php.
        if ( ! function_exists( 'j5_portal_enabled' ) || ! j5_portal_enabled() ) {
                return;
        }
        ?>
        <div class="j5-agency-banner">
                <div class="j5-agency-banner-inner">
                        <div class="j5-agency-banner-text">
                                <span class="j5-agency-banner-pill">For Agencies</span>
                                <span class="j5-agency-banner-msg">
                                        j5rescue.com is our <strong>commercial retail site</strong>. Agencies, departments, and dealers &mdash; manage POs, departmental pricelists, and CJIS-aware tools on our agency portal.
                                </span>
                        </div>
                        <a href="<?php echo esc_url( j5_portal_url() ); ?>" class="j5-agency-banner-cta" target="_blank" rel="noopener">
                                Agency Portal &rarr;
                        </a>
                </div>
        </div>
        <?php
}, 5 );
