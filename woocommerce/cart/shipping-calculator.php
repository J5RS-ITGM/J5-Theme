<?php
/**
 * Shipping Calculator — J5 minimal forced-visible override
 * Inline !important styles so no CSS or JS can hide it.
 */
defined( 'ABSPATH' ) || exit;
?>
<form class="woocommerce-shipping-calculator shipping-calculator-form"
      action="<?php echo esc_url( wc_get_cart_url() ); ?>"
      method="post"
      style="display:block !important; visibility:visible !important; opacity:1 !important; height:auto !important; overflow:visible !important;">

    <div style="display:flex !important; gap:6px; width:100%; flex-wrap:wrap; margin:0; visibility:visible !important; opacity:1 !important;">

        <p class="form-row form-row-wide"
           id="calc_shipping_postcode_field"
           style="display:block !important; visibility:visible !important; flex:1 1 auto; min-width:0; margin:0;">
            <input type="text"
                   class="input-text j5-cart-ship__input"
                   value="<?php echo esc_attr( WC()->customer->get_shipping_postcode() ); ?>"
                   placeholder="<?php esc_attr_e( 'ZIP', 'woocommerce' ); ?>"
                   name="calc_shipping_postcode"
                   id="calc_shipping_postcode"
                   maxlength="10"
                   style="display:block !important; visibility:visible !important; width:100%;" />
        </p>

        <p class="form-row" style="flex:0 0 auto; margin:0; display:block !important;">
            <button type="submit"
                    name="calc_shipping"
                    value="1"
                    class="button"
                    style="display:inline-block !important;"
                    aria-label="<?php esc_attr_e( 'Update shipping estimate', 'woocommerce' ); ?>">&rarr;</button>
        </p>

        <?php wp_nonce_field( 'woocommerce-shipping-calculator', 'woocommerce-shipping-calculator-nonce' ); ?>
    </div>

</form>
