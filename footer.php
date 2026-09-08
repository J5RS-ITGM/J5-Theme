<?php
/**
 * J5 Rescue Supply — Site footer
 * 5-column main layout: Brand | Shop | Support | Legal | Account
 * Support/Legal come from "Footer menu" grouped by parent (Option C).
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$j5_phone     = apply_filters( 'j5_header_phone', '(630) 442-4938' );
$j5_phone_tel = apply_filters( 'j5_header_phone_tel', 'tel:+16304424938' );
$j5_year      = date_i18n( 'Y' );
?>

</main>

<!-- === J5-FOOTER-MAIN-START === -->
<footer id="j5-site-footer" class="j5-site-footer" role="contentinfo">

	<div class="j5-footer-main">
		<div class="j5-footer-main__inner">

			<div class="j5-footer-col j5-footer-col--brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="j5-logo j5-logo--footer" rel="home">
					<span class="j5-logo__mark">J5</span>
					<span class="j5-logo__wordmark"><?php bloginfo( 'name' ); ?></span>
				</a>
				<address class="j5-footer-contact">
					Northern Illinois<br>
					<a href="<?php echo esc_url( $j5_phone_tel ); ?>"><?php echo esc_html( $j5_phone ); ?></a><br>
					<a href="mailto:orders@j5rescue.com">orders@j5rescue.com</a>
				</address>
			</div>

			<div class="j5-footer-col j5-footer-col--shop">
				<h2 class="j5-footer-col__title">Shop</h2>
				<?php if ( function_exists( 'j5_render_footer_shop_menu' ) ) { j5_render_footer_shop_menu(); } ?>
			</div>

			<?php if ( function_exists( 'j5_render_footer_menu_groups' ) ) { j5_render_footer_menu_groups(); } ?>

			<div class="j5-footer-col j5-footer-col--account">
				<h2 class="j5-footer-col__title">Account</h2>
				<ul class="j5-footer-links">
					<?php if ( function_exists( 'wc_get_account_endpoint_url' ) ) : ?>
					<li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>">Sign in</a></li>
					<li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">My orders</a></li>
					<li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">Track order</a></li>
					<li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">Addresses</a></li>
					<?php else : ?>
					<li><a href="<?php echo esc_url( wp_login_url() ); ?>">Sign in</a></li>
					<?php endif; ?>
				</ul>
			</div>

		</div>
	</div>
	<!-- === J5-FOOTER-MAIN-END === -->

	<!-- === J5-FOOTER-BOTTOM-START === -->
	<div class="j5-footer-bottom">
		<div class="j5-footer-bottom__inner">
			<div class="j5-footer-bottom__copy">&copy; <?php echo esc_html( $j5_year ); ?> J5 Rescue Supply LLC</div>
			<div class="j5-footer-bottom__legal">
				<?php if ( function_exists( 'j5_render_footer_legal_mirror' ) ) { j5_render_footer_legal_mirror(); } ?>
			</div>
		</div>
	</div>
	<!-- === J5-FOOTER-BOTTOM-END === -->

</footer>

<?php wp_footer(); ?>
</body>
</html>
