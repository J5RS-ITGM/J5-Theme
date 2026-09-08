<?php
/**
 * Cross-sells — J5 custom override
 *
 * Overrides woocommerce/templates/cart/cross-sells.php
 * Layout: "Recommended gear" header + 3-column grid of product cards.
 *
 * @package J5
 * @version batch 10 (derived from WC 7.9 cross-sells.php)
 */

defined( 'ABSPATH' ) || exit;

/** @var WC_Product[] $cross_sells */

if ( empty( $cross_sells ) ) {
    return;
}
?>

<section class="j5-cart-upsells cross-sells">

    <div class="j5-cart-upsells__head">
        <h2 class="j5-cart-upsells__title"><?php esc_html_e( 'Recommended gear', 'woocommerce' ); ?></h2>
        <span class="j5-cart-upsells__sub"><?php esc_html_e( 'Pairs well with your cart', 'woocommerce' ); ?></span>
    </div>

    <div class="j5-cart-upsells__grid">

        <?php foreach ( $cross_sells as $cross_sell ) : ?>
            <?php
            $post_object   = get_post( $cross_sell->get_id() );
            setup_postdata( $GLOBALS['post'] =& $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

            $permalink = $cross_sell->get_permalink();
            $image     = $cross_sell->get_image( 'woocommerce_thumbnail' );
            $name      = $cross_sell->get_name();
            $price     = $cross_sell->get_price_html();
            $add_url   = esc_url( '?add-to-cart=' . $cross_sell->get_id() );
            ?>
            <div class="j5-cart-upsell product">

                <a href="<?php echo esc_url( $permalink ); ?>" class="j5-cart-upsell__thumb">
                    <?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </a>

                <div class="j5-cart-upsell__name">
                    <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $name ); ?></a>
                </div>

                <div class="j5-cart-upsell__foot">
                    <span class="j5-cart-upsell__price"><?php echo $price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>

                    <?php if ( $cross_sell->is_purchasable() && $cross_sell->is_in_stock() ) : ?>
                        <a href="<?php echo esc_url( $cross_sell->add_to_cart_url() ); ?>"
                           class="j5-cart-upsell__add add_to_cart_button ajax_add_to_cart"
                           data-product_id="<?php echo esc_attr( $cross_sell->get_id() ); ?>"
                           data-product_sku="<?php echo esc_attr( $cross_sell->get_sku() ); ?>"
                           aria-label="<?php echo esc_attr( $cross_sell->add_to_cart_description() ); ?>"
                           rel="nofollow">
                            + <?php esc_html_e( 'Add', 'woocommerce' ); ?>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $permalink ); ?>" class="j5-cart-upsell__add">
                            <?php esc_html_e( 'View', 'woocommerce' ); ?>
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>

        <?php wp_reset_postdata(); ?>

    </div>

</section>
