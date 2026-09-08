<?php
/**
 * My Account Dashboard
 *
 * J5 Rescue Supply custom override of woocommerce/templates/myaccount/dashboard.php
 *
 * Replaces the default "Hello [name], from your account dashboard..." paragraph
 * with our brand-styled greeting + 4-tile shortcut grid + agency CTA card.
 *
 * @package j5-rescue-supply
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

$allowed_html = array(
        'a'      => array(
                'href' => array(),
        ),
        'strong' => array(),
);

$current_user = wp_get_current_user();
$first_name   = ! empty( $current_user->first_name ) ? $current_user->first_name : $current_user->display_name;
?>

<h2>Dashboard</h2>

<p>
        <?php
        printf(
                /* translators: %1$s: user first name, %2$s: orders link, %3$s: addresses link, %4$s: account details link */
                wp_kses(
                        __( 'Hello <strong>%1$s</strong> &mdash; from your account dashboard you can review your %2$s, manage your %3$s, and %4$s.', 'woocommerce' ),
                        $allowed_html
                ),
                esc_html( $first_name ),
                '<a href="' . esc_url( wc_get_endpoint_url( 'orders' ) ) . '">recent orders</a>',
                '<a href="' . esc_url( wc_get_endpoint_url( 'edit-address' ) ) . '">shipping and billing addresses</a>',
                '<a href="' . esc_url( wc_get_endpoint_url( 'edit-account' ) ) . '">update your account details</a>'
        );
        ?>
</p>

<div class="j5-dashboard-tiles">
        <a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>" class="j5-dashboard-tile">
                <div class="tile-eyebrow">Track Your Gear</div>
                <h3 class="tile-title">Recent Orders</h3>
                <p class="tile-desc">View order status, tracking numbers, and reorder past purchases.</p>
        </a>
        <a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>" class="j5-dashboard-tile">
                <div class="tile-eyebrow">Faster Checkout</div>
                <h3 class="tile-title">Saved Addresses</h3>
                <p class="tile-desc">Manage shipping and billing addresses for one-click reuse.</p>
        </a>
        <a href="<?php echo esc_url( wc_get_endpoint_url( 'payment-methods' ) ); ?>" class="j5-dashboard-tile">
                <div class="tile-eyebrow">Cards on File</div>
                <h3 class="tile-title">Payment Methods</h3>
                <p class="tile-desc">Securely save cards for faster checkout. PCI-compliant.</p>
        </a>
        <a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-account' ) ); ?>" class="j5-dashboard-tile">
                <div class="tile-eyebrow">Profile</div>
                <h3 class="tile-title">Account Details</h3>
                <p class="tile-desc">Update your name, email, and password.</p>
        </a>
</div>

<div class="j5-agency-cta-card">
        <div class="j5-agency-cta-text">
                <strong>Buying for an agency?</strong>
                Manage POs, departmental pricelists, and CJIS workflows on the agency portal.
        </div>
        <?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?><a href="<?php echo esc_url( j5_portal_url() ); ?>" class="j5-agency-cta-btn" target="_blank" rel="noopener">Agency Portal &rarr;</a><?php endif; ?>
</div>

<?php
        /**
         * My Account dashboard.
         *
         * @since 2.6.0
         */
        do_action( 'woocommerce_account_dashboard' );

        /**
         * Deprecated woocommerce_before_my_account action.
         *
         * @deprecated 2.6.0 this action has been deprecated in favor of woocommerce_account_dashboard.
         */
        do_action( 'woocommerce_before_my_account' );

        /**
         * Deprecated woocommerce_after_my_account action.
         *
         * @deprecated 2.6.0 this action has been deprecated in favor of woocommerce_account_dashboard.
         */
        do_action( 'woocommerce_after_my_account' );
?>
