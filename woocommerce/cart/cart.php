<?php
/**
 * Cart Page — J5 custom override
 *
 * Overrides woocommerce/templates/cart/cart.php
 * Layout: header row + two-column grid (items / summary) + upsells row below.
 *
 * @package J5
 * @version batch 10 (derived from WC 7.9 cart.php)
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

$cart_count = WC()->cart->get_cart_contents_count();
?>

<div class="j5-cart-wrap">

    <div class="j5-cart-header">
        <h1 class="j5-cart-title"><?php esc_html_e( 'Your cart', 'woocommerce' ); ?></h1>
        <span class="j5-cart-count">
            <?php
            /* translators: %d = number of items in cart */
            echo esc_html( sprintf( _n( '%d item', '%d items', $cart_count, 'woocommerce' ), $cart_count ) );
            ?>
        </span>
    </div>

    <form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
        <?php do_action( 'woocommerce_before_cart_table' ); ?>

        <div class="j5-cart-layout">

            <div class="j5-cart-main">

                <div class="j5-cart-items">
                <?php
                do_action( 'woocommerce_before_cart_contents' );

                foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                    $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                    $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

                    if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                        continue;
                    }

                    $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                    $item_classes      = apply_filters( 'woocommerce_cart_item_class', 'j5-cart-item cart_item', $cart_item, $cart_item_key );
                    ?>
                    <div class="<?php echo esc_attr( $item_classes ); ?>" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>">

                        <div class="j5-cart-item__thumb">
                            <?php
                            $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
                            if ( ! $product_permalink ) {
                                echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            } else {
                                printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                        </div>

                        <div class="j5-cart-item__info">
                            <div class="j5-cart-item__name">
                                <?php
                                if ( ! $product_permalink ) {
                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) );
                                } else {
                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
                                }
                                do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

                                if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
                                }
                                ?>
                            </div>

                            <div class="j5-cart-item__meta">
                                <?php
                                if ( $_product->get_sku() ) {
                                    echo 'SKU: ' . esc_html( $_product->get_sku() );
                                }
                                $formatted_meta = wc_get_formatted_cart_item_data( $cart_item );
                                if ( $formatted_meta ) {
                                    if ( $_product->get_sku() ) {
                                        echo ' · ';
                                    }
                                    echo wp_kses_post( $formatted_meta );
                                }
                                ?>
                            </div>

                            <div class="j5-cart-item__qty">
                                <?php
                                if ( $_product->is_sold_individually() ) {
                                    $product_quantity = sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key );
                                } else {
                                    $product_quantity = woocommerce_quantity_input(
                                        array(
                                            'input_name'   => "cart[{$cart_item_key}][qty]",
                                            'input_value'  => $cart_item['quantity'],
                                            'max_value'    => $_product->get_max_purchase_quantity(),
                                            'min_value'    => '0',
                                            'product_name' => $_product->get_name(),
                                            'classes'      => array( 'input-text', 'qty', 'text' ),
                                        ),
                                        $_product,
                                        false
                                    );

                                    // Wrap the default qty input with +/- buttons
                                    $product_quantity = preg_replace(
                                        '/<div class="quantity([^"]*)"([^>]*)>/',
                                        '<div class="quantity$1"$2>'
                                        . '<button type="button" class="j5-cart-item__qty-btn" data-dir="down" aria-label="' . esc_attr__( 'Decrease quantity', 'woocommerce' ) . '">−</button>',
                                        $product_quantity
                                    );
                                    $product_quantity = str_replace(
                                        '</div>',
                                        '<button type="button" class="j5-cart-item__qty-btn" data-dir="up" aria-label="' . esc_attr__( 'Increase quantity', 'woocommerce' ) . '">+</button></div>',
                                        $product_quantity
                                    );
                                }
                                echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                            </div>
                        </div>

                        <div class="j5-cart-item__price-col">
                            <div class="j5-cart-item__total">
                                <?php
                                echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                            </div>
                            <?php if ( $cart_item['quantity'] > 1 ) : ?>
                                <div class="j5-cart-item__unit">
                                    @ <?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php
                        echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            'woocommerce_cart_item_remove_link',
                            sprintf(
                                '<a href="%s" class="j5-cart-item__remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                /* translators: %s is the product name */
                                esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $_product->get_name() ) ) ),
                                esc_attr( $product_id ),
                                esc_attr( $_product->get_sku() )
                            ),
                            $cart_item_key
                        );
                        ?>

                    </div>
                    <?php
                endforeach;

                do_action( 'woocommerce_cart_contents' );
                ?>
                </div><!-- .j5-cart-items -->

                <?php if ( wc_coupons_enabled() ) : ?>
                    <div class="j5-cart-coupon">
                        <label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
                        <input type="text" name="coupon_code" class="j5-cart-coupon__input input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" />
                        <button type="submit" class="j5-cart-coupon__btn button" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply', 'woocommerce' ); ?></button>
                    </div>
                <?php endif; ?>

                <a href="<?php echo esc_url( apply_filters( 'woocommerce_continue_shopping_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="j5-cart-continue">
                    &larr; <?php esc_html_e( 'Continue shopping', 'woocommerce' ); ?>
                </a>

                <button type="submit" class="button" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>
                <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>

                <?php do_action( 'woocommerce_cart_actions' ); ?>

            </div><!-- .j5-cart-main -->

            <aside class="j5-cart-side">
                <div class="cart-collaterals">
                    <?php
                        /**
                         * woocommerce_cart_collaterals hook — default fires:
                         *   - woocommerce_cart_totals (priority 10) — renders cart-totals.php
                         * We removed woocommerce_cross_sell_display in j5-cart-setup.php
                         * and render it manually below.
                         */
                        do_action( 'woocommerce_cart_collaterals' );
                    ?>
                </div>
            </aside>

        </div><!-- .j5-cart-layout -->

        <?php do_action( 'woocommerce_after_cart_table' ); ?>
    </form>

    <?php
    // Upsells / cross-sells — rendered below the main grid, full-width.
    woocommerce_cross_sell_display();
    ?>

    <?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

</div><!-- .j5-cart-wrap -->

<?php do_action( 'woocommerce_after_cart' ); ?>
