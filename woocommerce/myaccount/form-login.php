<?php
/**
 * Login + Register form
 *
 * J5 Rescue Supply custom override of woocommerce/templates/myaccount/form-login.php
 *
 * Replaces the default WC layout (which uses float-based .col2-set / .col-1 / .col-2)
 * with a flexbox layout that's not constrained by Elementor's widget rendering.
 *
 * This template is rendered when the visitor is NOT logged in and views /my-account/.
 *
 * Sentinel: J5-ACCOUNT-V1
 *
 * @package j5-rescue-supply
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

do_action( 'woocommerce_before_customer_login_form' );
?>

<div class="j5-account-hero">
        <div class="j5-account-eyebrow"><?php esc_html_e( 'My Account', 'woocommerce' ); ?></div>
        <h1 class="j5-account-title">
                <?php esc_html_e( 'Sign in', 'woocommerce' ); ?>
                <span class="j5-account-title-accent">/</span>
                <?php esc_html_e( 'Register', 'woocommerce' ); ?>
        </h1>
        <p class="j5-account-subtitle">Access order history, saved addresses, and faster checkout. Already have an account? Sign in below. New here? Set one up in under a minute.</p>
</div>

<div class="j5-login-wrapper">
        <div class="j5-login-grid">

                <?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>
                        <div class="j5-login-card">
                <?php else : ?>
                        <div class="j5-login-card j5-login-card--full">
                <?php endif; ?>

                        <div class="j5-card-eyebrow">Returning Customer</div>
                        <h2 class="j5-card-title"><?php esc_html_e( 'Login', 'woocommerce' ); ?></h2>

                        <form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

                                <?php do_action( 'woocommerce_login_form_start' ); ?>

                                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide j5-form-row">
                                        <label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                                        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" />
                                </p>
                                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide j5-form-row">
                                        <label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                                        <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
                                </p>

                                <?php do_action( 'woocommerce_login_form' ); ?>

                                <p class="form-row j5-form-row">
                                        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme j5-checkbox-row">
                                                <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
                                        </label>
                                        <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                                        <button type="submit" class="woocommerce-button button woocommerce-form-login__submit j5-btn-primary<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php esc_html_e( 'Log in', 'woocommerce' ); ?></button>
                                </p>
                                <p class="woocommerce-LostPassword lost_password">
                                        <a class="j5-lost-password" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
                                </p>

                                <?php do_action( 'woocommerce_login_form_end' ); ?>

                        </form>
                </div>

                <?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>

                        <div class="j5-register-card">

                                <div class="j5-card-eyebrow">New Customer</div>
                                <h2 class="j5-card-title"><?php esc_html_e( 'Register', 'woocommerce' ); ?></h2>

                                <form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >

                                        <?php do_action( 'woocommerce_register_form_start' ); ?>

                                        <?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
                                                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide j5-form-row">
                                                        <label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                                                        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" />
                                                </p>
                                        <?php endif; ?>

                                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide j5-form-row">
                                                <label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                                                <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required aria-required="true" />
                                                <?php if ( 'yes' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
                                                        <small>A link to set a new password will be sent to your email address.</small>
                                                <?php endif; ?>
                                        </p>

                                        <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
                                                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide j5-form-row">
                                                        <label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                                                        <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
                                                </p>
                                        <?php endif; ?>

                                        <?php do_action( 'woocommerce_register_form' ); ?>

                                        <p class="woocommerce-form-row form-row j5-form-row">
                                                <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                                                <button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit j5-btn-secondary<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Register', 'woocommerce' ); ?></button>
                                        </p>

                                        <?php do_action( 'woocommerce_register_form_end' ); ?>

                                </form>

                                <?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?>
                                <div class="j5-helper-note">
                                        <strong>Buying for an agency or department?</strong><br>
                                        Don't register here &mdash; set up an agency account on <a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener">our agency portal</a> for departmental pricelists, PO checkout, and CJIS-aware tools.
                                </div>
                                <?php endif; ?>

                        </div>

                <?php endif; ?>

        </div>
</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
