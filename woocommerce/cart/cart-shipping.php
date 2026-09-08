<?php
/**
 * Shipping Methods Display — J5 context-aware override
 * Cart page:     <div class="j5-cart-sum__row"> (styled by j5-cart.css)
 * Checkout page: WC-default <tr>/<ul>/<li> markup (styled by j5-checkout.css)
 */
defined( 'ABSPATH' ) || exit;

$j5_is_checkout_ctx = function_exists( 'is_checkout' )
    && is_checkout()
    && ! is_wc_endpoint_url( 'order-received' );

if ( $j5_is_checkout_ctx ) : ?>

    <tr class="shipping woocommerce-shipping-totals">
        <th><?php echo wp_kses_post( $package_name ); ?></th>
        <td data-title="<?php echo esc_attr( $package_name ); ?>">
            <?php if ( $available_methods ) : ?>
                <ul id="shipping_method" class="woocommerce-shipping-methods">
                    <?php foreach ( $available_methods as $method ) : ?>
                        <li>
                            <input type="radio"
                                   name="shipping_method[<?php echo esc_attr( $index ); ?>]"
                                   data-index="<?php echo esc_attr( $index ); ?>"
                                   id="shipping_method_<?php echo esc_attr( $index ); ?>_<?php echo esc_attr( sanitize_title( $method->id ) ); ?>"
                                   value="<?php echo esc_attr( $method->id ); ?>"
                                   class="shipping_method"
                                   <?php checked( $method->id, $chosen_method ); ?> />
                            <label for="shipping_method_<?php echo esc_attr( $index ); ?>_<?php echo esc_attr( sanitize_title( $method->id ) ); ?>">
                                <?php echo wp_kses_post( wc_cart_totals_shipping_method_label( $method ) ); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif ( ! WC()->customer->has_calculated_shipping() ) : ?>
                <p class="woocommerce-shipping-destination"><?php esc_html_e( 'Enter your address to calculate shipping.', 'woocommerce' ); ?></p>
            <?php else : ?>
                <p><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_no_shipping_available_html', esc_html__( 'No shipping options were found.', 'woocommerce' ) ) ); ?></p>
            <?php endif; ?>
        </td>
    </tr>

<?php else : ?>

<div class="j5-cart-sum__row shipping woocommerce-shipping-totals">

    <span class="j5-cart-sum__label">
        <?php echo wp_kses_post( $package_name ); ?>
    </span>

    <span class="j5-cart-sum__value">
    <?php if ( $available_methods && count( $available_methods ) > 1 ) : ?>

        <?php foreach ( $available_methods as $method ) : ?>
            <label class="j5-cart-shipping__method" style="display:block; font-size:12px; margin-bottom:4px;">
                <input type="radio"
                       name="shipping_method[<?php echo esc_attr( $index ); ?>]"
                       data-index="<?php echo esc_attr( $index ); ?>"
                       id="shipping_method_<?php echo esc_attr( $index ); ?>_<?php echo esc_attr( sanitize_title( $method->id ) ); ?>"
                       value="<?php echo esc_attr( $method->id ); ?>"
                       class="shipping_method"
                       <?php checked( $method->id, $chosen_method ); ?> />
                <?php echo wp_kses_post( wc_cart_totals_shipping_method_label( $method ) ); ?>
            </label>
        <?php endforeach; ?>

    <?php elseif ( $available_methods ) : $method = current( $available_methods ); ?>

        <?php echo wp_kses_post( wc_cart_totals_shipping_method_label( $method ) ); ?>
        <input type="hidden"
               name="shipping_method[<?php echo esc_attr( $index ); ?>]"
               data-index="<?php echo esc_attr( $index ); ?>"
               id="shipping_method_<?php echo esc_attr( $index ); ?>"
               value="<?php echo esc_attr( $method->id ); ?>"
               class="shipping_method" />

    <?php elseif ( ! WC()->customer->has_calculated_shipping() ) : ?>

        <span class="j5-cart-sum__value--muted"><?php esc_html_e( 'enter ZIP above', 'woocommerce' ); ?></span>

    <?php elseif ( ! $show_package_details ) : ?>

        <span class="j5-cart-sum__value--muted">
            <?php echo wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', esc_html__( 'No shipping options for this ZIP', 'woocommerce' ) ) ); ?>
        </span>

    <?php else : ?>

        <span class="j5-cart-sum__value--muted">
            <?php echo wp_kses_post( apply_filters( 'woocommerce_cart_no_shipping_available_html', esc_html__( 'No shipping options were found.', 'woocommerce' ) ) ); ?>
        </span>

    <?php endif; ?>
    </span>

</div>

<?php endif; ?>
