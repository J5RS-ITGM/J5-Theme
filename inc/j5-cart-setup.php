<?php
/**
 * J5 Cart Setup
 *
 * - Conditionally enqueues j5-cart.css on the cart page
 * - Restricts the shipping calculator to postcode-only
 * - Sets cross-sells to 3 columns × 3 products
 * - Removes default WC cross-sells placement so cart.php can render them manually
 * - Injects tiny JS to auto-submit the cart form on qty change
 *
 * @package J5
 * @version batch 10
 */

defined( 'ABSPATH' ) || exit;

/* ---- Enqueue ---- */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
        return;
    }

    $css_rel  = '/assets/css/j5-cart.css';
    $css_path = get_stylesheet_directory() . $css_rel;
    $css_url  = get_stylesheet_directory_uri() . $css_rel;

    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'j5-cart',
            $css_url,
            array(),
            filemtime( $css_path )
        );
    }

    // Brand fonts are self-hosted and enqueued site-wide via inc/j5-fonts.php
    // (j5_enqueue_fonts, priority 5). No Google Fonts request here.
}, 20 );

/* ---- Shipping calculator: postcode only ---- */
add_filter( 'woocommerce_shipping_calculator_enable_country', '__return_false' );
add_filter( 'woocommerce_shipping_calculator_enable_state',   '__return_false' );
add_filter( 'woocommerce_shipping_calculator_enable_city',    '__return_false' );
add_filter( 'woocommerce_shipping_calculator_enable_postcode', '__return_true', 99 );
// postcode stays true (WC default)

/* ---- Force the calculator itself to be enabled ---- */
// The global option woocommerce_enable_shipping_calc may be 'no' (WC admin
// > Settings > Shipping > Shipping options). The mockup explicitly includes
// the estimator, so we override via pre_option filter — invisible to the
// admin setting, local to this cart page load.
add_filter( 'pre_option_woocommerce_enable_shipping_calc', function ( $pre ) {
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        return 'yes';
    }
    return $pre;
} );

/* ---- Cross-sells: 3 × 3 ---- */
add_filter( 'woocommerce_cross_sells_columns', function () { return 3; } );
add_filter( 'woocommerce_cross_sells_total',   function () { return 3; } );

/* ---- Remove default cross-sell placement ---- */
// Default WC hooks woocommerce_cross_sell_display() on woocommerce_cart_collaterals
// at priority 10. We call it manually in cart.php below the grid instead.
add_action( 'init', function () {
    remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
}, 20 );

/* ---- Replace default Proceed to Checkout button with J5 styled version ---- */
add_action( 'init', function () {
    remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
    add_action( 'woocommerce_proceed_to_checkout', 'j5_cart_proceed_to_checkout_button', 20 );
}, 20 );

if ( ! function_exists( 'j5_cart_proceed_to_checkout_button' ) ) {
    function j5_cart_proceed_to_checkout_button() {
        ?>
        <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="j5-cart-cta checkout-button button alt wc-forward">
            <?php esc_html_e( 'Proceed to checkout', 'woocommerce' ); ?> &rarr;
        </a>
        <?php
    }
}

/* ---- Auto-submit on qty change ---- */
// Default WC behavior requires clicking "Update cart" after changing qty.
// We hide that button in CSS and submit the form on qty change here.
add_action( 'wp_footer', function () {
    if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
        return;
    }
    ?>
    <script>
    (function () {
        'use strict';
        var form = document.querySelector( 'form.woocommerce-cart-form' );
        if ( ! form ) return;

        var updateBtn = form.querySelector( 'button[name="update_cart"], input[name="update_cart"]' );
        if ( ! updateBtn ) return;

        var debounceTimer = null;
        function scheduleUpdate() {
            clearTimeout( debounceTimer );
            debounceTimer = setTimeout( function () {
                updateBtn.disabled = false;
                updateBtn.click();
            }, 600 );
        }

        form.addEventListener( 'change', function ( ev ) {
            if ( ev.target.classList && ev.target.classList.contains( 'qty' ) ) {
                scheduleUpdate();
            }
        } );

        // Pressing Enter in a qty input should trigger a cart update, not
        // submit the coupon (which is the first visible submit button).
        form.addEventListener( 'keydown', function ( ev ) {
            if ( ev.key === 'Enter' && ev.target.classList && ev.target.classList.contains( 'qty' ) ) {
                ev.preventDefault();
                scheduleUpdate();
            }
        } );

        // +/- button handlers (buttons exist in the custom cart template)
        form.addEventListener( 'click', function ( ev ) {
            var btn = ev.target.closest( '.j5-cart-item__qty-btn' );
            if ( ! btn ) return;
            ev.preventDefault();
            var wrap = btn.closest( '.quantity' );
            if ( ! wrap ) return;
            var input = wrap.querySelector( 'input.qty' );
            if ( ! input ) return;

            var current = parseInt( input.value, 10 ) || 0;
            var step    = btn.getAttribute( 'data-dir' ) === 'up' ? 1 : -1;
            var min     = parseInt( input.getAttribute( 'min' ) || '0', 10 );
            var max     = parseInt( input.getAttribute( 'max' ) || '', 10 );

            var next = current + step;
            if ( next < min ) next = min;
            if ( ! isNaN( max ) && max > 0 && next > max ) next = max;

            input.value = String( next );
            input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        } );
    })();
    </script>
    <?php
}, 99 );
