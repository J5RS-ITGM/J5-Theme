<?php
/**
 * My Account page wrapper (logged-in)
 *
 * J5 Rescue Supply custom override of woocommerce/templates/myaccount/my-account.php
 *
 * Renders the welcome hero bar + sidebar nav + content layout when the user
 * is logged in. The standard WC navigation hook is replaced with our own
 * custom nav (see navigation.php) so we control the markup.
 *
 * @package j5-rescue-supply
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

$current_user = wp_get_current_user();
$first_name   = ! empty( $current_user->first_name ) ? $current_user->first_name : $current_user->display_name;
if ( empty( $first_name ) ) {
        $first_name = 'there';
}
?>

<div class="j5-account-hero-bar">
        <div class="j5-account-hero-inner">
                <div>
                        <div class="j5-account-eyebrow">My Account</div>
                        <h1 class="j5-account-title">Welcome back<em>,</em> <?php echo esc_html( $first_name ); ?></h1>
                </div>
                <div class="j5-quick-actions">
                        <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="j5-btn j5-btn-primary">Continue Shopping &rarr;</a>
                </div>
        </div>
</div>

<div class="j5-account-layout">

        <?php
                /**
                 * My Account navigation (sidebar).
                 *
                 * Hooked: woocommerce_account_navigation() — uses the navigation.php template override.
                 *
                 * @hooked woocommerce_account_navigation - 10
                 */
                do_action( 'woocommerce_account_navigation' );
        ?>

        <div class="woocommerce-MyAccount-content j5-account-content">
                <?php
                        /**
                         * My Account content.
                         *
                         * @since 2.6.0
                         */
                        do_action( 'woocommerce_account_content' );
                ?>
        </div>

</div>
