<?php
/**
 * J5 Rescue Supply — default page template
 *
 * Standalone replacement for Astra's page.php. Regular pages AND the
 * checkout page render through this (cart is routed to single-j5-cart.php
 * by inc/j5-cart-checkout-setup.php; checkout falls through to here).
 *
 * MARKUP COMPAT: the .site-content / .ast-container wrappers are kept
 * because assets/css/j5-checkout.css carries 15 selectors targeting them
 * (all scoped to body.woocommerce-checkout). .j5-container is the
 * forward-looking class — new CSS should target it; renaming the .ast-*
 * selectors is a later cleanup pass, not a cutover risk.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>

<main id="main" class="site-main">
	<div id="content" class="site-content">
		<div class="ast-container j5-container">

			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'j5-page' ); ?>>

					<?php if ( ! is_cart() && ! is_checkout() && ! is_front_page() ) : ?>
						<header class="j5-page__header">
							<h1 class="j5-page__title"><?php the_title(); ?></h1>
						</header>
					<?php endif; ?>

					<div class="j5-page__content entry-content">
						<?php the_content(); ?>
					</div>

				</article>
				<?php
			endwhile;
			?>

		</div>
	</div>
</main>

<?php
get_footer();
