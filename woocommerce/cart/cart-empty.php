<?php
/**
 * Empty Cart Page — J5 custom override
 *
 * Overrides woocommerce/templates/cart/cart-empty.php
 *
 * @package J5
 * @version batch 10 (derived from WC 7.9 cart-empty.php)
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="j5-cart-wrap">

    <div class="j5-cart-empty">

        <h1 class="j5-cart-empty__title"><?php esc_html_e( 'Your cart is empty', 'woocommerce' ); ?></h1>

        <p class="j5-cart-empty__text">
            <?php
            /**
             * woocommerce_cart_is_empty hook — WC default prints the "empty cart"
             * notice here at priority 10. We override by emitting our own message
             * above, so suppress the default.
             */
            remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
            do_action( 'woocommerce_cart_is_empty' );
            esc_html_e( 'Browse the shop and add gear to your loadout.', 'woocommerce' );
            ?>
        </p>

        <?php if ( wc_get_page_id( 'shop' ) > 0 ) : ?>
            <a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="j5-cart-empty__cta">
                <?php echo esc_html( apply_filters( 'woocommerce_return_to_shop_text', __( 'Return to shop', 'woocommerce' ) ) ); ?>
            </a>
        <?php endif; ?>

    </div>

</div>
