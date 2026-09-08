<?php
/**
 * My Account sidebar navigation
 *
 * J5 Rescue Supply custom override of woocommerce/templates/myaccount/navigation.php
 *
 * Renders the Dashboard / Orders / Addresses / Payment Methods / Account Details
 * / Logout list with our markup so we don't fight WC's default styling.
 *
 * @package j5-rescue-supply
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="woocommerce-MyAccount-navigation j5-account-nav" aria-label="<?php esc_attr_e( 'Account pages', 'woocommerce' ); ?>">
        <ul>
                <?php
                $items   = wc_get_account_menu_items();
                $current = WC()->query->get_current_endpoint();

                // Determine active item (when on the dashboard, $current is empty).
                foreach ( $items as $endpoint => $label ) :
                        $is_active = wc_is_current_account_menu_item( $endpoint );

                        // Skip Downloads — physical-goods store, no digital products.
                        if ( 'downloads' === $endpoint ) {
                                continue;
                        }

                        $li_classes = array( 'woocommerce-MyAccount-navigation-link', 'woocommerce-MyAccount-navigation-link--' . $endpoint );
                        if ( $is_active ) {
                                $li_classes[] = 'is-active';
                        }
                        if ( 'customer-logout' === $endpoint ) {
                                $li_classes[] = 'is-logout';
                        }
                        ?>
                        <?php if ( 'customer-logout' === $endpoint ) : ?>
                                <li class="nav-divider" aria-hidden="true"></li>
                        <?php endif; ?>
                        <li class="<?php echo esc_attr( implode( ' ', $li_classes ) ); ?>">
                                <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"><?php echo esc_html( $label ); ?></a>
                        </li>
                <?php endforeach; ?>
        </ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
