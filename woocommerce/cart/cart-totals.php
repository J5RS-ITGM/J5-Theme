<?php
/**
 * Cart totals — J5 custom override
 *
 * Overrides woocommerce/templates/cart/cart-totals.php
 *
 * @package J5
 * @version batch 10 (derived from WC 7.9 cart-totals.php)
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="j5-cart-sum">

    <?php do_action( 'woocommerce_before_cart_totals' ); ?>

    <div class="j5-cart-sum__header"><?php esc_html_e( 'Order summary', 'woocommerce' ); ?></div>

    <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
        <div class="j5-cart-ship">
            <span class="j5-cart-ship__label"><?php esc_html_e( 'Ship to', 'woocommerce' ); ?></span>
            <?php woocommerce_shipping_calculator(); ?>
        </div>
    <?php endif; ?>

    <div class="j5-cart-sum__row cart-subtotal">
        <span class="j5-cart-sum__label"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></span>
        <span class="j5-cart-sum__value"><?php wc_cart_totals_subtotal_html(); ?></span>
    </div>

    <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
        <div class="j5-cart-sum__row cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
            <span class="j5-cart-sum__label"><?php wc_cart_totals_coupon_label( $coupon ); ?></span>
            <span class="j5-cart-sum__value j5-cart-sum__value--discount"><?php wc_cart_totals_coupon_html( $coupon ); ?></span>
        </div>
    <?php endforeach; ?>

    <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

        <?php do_action( 'woocommerce_cart_totals_before_shipping' ); ?>

        <?php wc_cart_totals_shipping_html(); ?>

        <?php do_action( 'woocommerce_cart_totals_after_shipping' ); ?>

    <?php elseif ( WC()->cart->needs_shipping() && 'yes' === get_option( 'woocommerce_enable_shipping_calc' ) ) : ?>

        <div class="j5-cart-sum__row shipping">
            <span class="j5-cart-sum__label"><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></span>
            <span class="j5-cart-sum__value j5-cart-sum__value--muted"><?php esc_html_e( 'at checkout', 'woocommerce' ); ?></span>
        </div>

    <?php endif; ?>

    <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
        <div class="j5-cart-sum__row fee">
            <span class="j5-cart-sum__label"><?php echo esc_html( $fee->name ); ?></span>
            <span class="j5-cart-sum__value"><?php wc_cart_totals_fee_html( $fee ); ?></span>
        </div>
    <?php endforeach; ?>

    <?php
    if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) {
        $taxable_address  = WC()->customer->get_taxable_address();
        $country_code     = isset( $taxable_address[0] ) ? $taxable_address[0] : '';
        $country_name     = $country_code && isset( WC()->countries->countries[ $country_code ] )
            ? WC()->countries->countries[ $country_code ]
            : '';
        $estimated_text   = WC()->customer->is_customer_outside_base() && ! WC()->customer->has_calculated_shipping()
            ? sprintf( ' <small>' . esc_html__( '(estimated for %s)', 'woocommerce' ) . '</small>', WC()->countries->estimated_for_prefix( $country_code ) . esc_html( $country_name ) )
            : '';

        if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
            foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
                <div class="j5-cart-sum__row tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
                    <span class="j5-cart-sum__label"><?php echo esc_html( $tax->label ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="j5-cart-sum__value"><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
                </div>
            <?php endforeach;
        } else { ?>
            <div class="j5-cart-sum__row tax-total">
                <span class="j5-cart-sum__label"><?php echo esc_html( WC()->countries->tax_or_vat() ) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="j5-cart-sum__value"><?php wc_cart_totals_taxes_total_html(); ?></span>
            </div>
            <?php
        }
    } elseif ( ! wc_tax_enabled() || WC()->cart->display_prices_including_tax() ) {
        // Tax disabled or included — show nothing extra, total already reflects it.
    } else { ?>
        <div class="j5-cart-sum__row tax-total">
            <span class="j5-cart-sum__label"><?php esc_html_e( 'Tax', 'woocommerce' ); ?></span>
            <span class="j5-cart-sum__value j5-cart-sum__value--muted"><?php esc_html_e( 'at checkout', 'woocommerce' ); ?></span>
        </div>
        <?php
    }
    ?>

    <?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

    <div class="j5-cart-sum__divider"></div>

    <div class="j5-cart-sum__row j5-cart-sum__row--total order-total">
        <span class="j5-cart-sum__total-label"><?php esc_html_e( 'Total', 'woocommerce' ); ?></span>
        <span class="j5-cart-sum__total-value"><?php wc_cart_totals_order_total_html(); ?></span>
    </div>

    <?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>

    <div class="wc-proceed-to-checkout">
        <?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
    </div>

    <div class="j5-cart-trust"><?php esc_html_e( 'Secure checkout', 'woocommerce' ); ?></div>

    <?php do_action( 'woocommerce_after_cart_totals' ); ?>

</div>
