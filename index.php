<?php
/**
 * J5 Rescue Supply — fallback template
 *
 * Required for a valid standalone theme. In practice almost nothing lands
 * here: pages use page.php, posts use single-post.php, the blog uses
 * home.php, shop/category archives use woocommerce/ templates, and search
 * plus date/tag archives use archive.php. This catches whatever is left
 * (and 404s, since no 404.php is defined).
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>

<main id="main" class="site-main">
	<div id="content" class="site-content">
		<div class="ast-container j5-container">

			<?php if ( have_posts() ) : ?>

				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'j5-page' ); ?>>
						<header class="j5-page__header">
							<h2 class="j5-page__title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
						</header>
						<div class="j5-page__content entry-content">
							<?php the_excerpt(); ?>
						</div>
					</article>
					<?php
				endwhile;

				the_posts_pagination();
				?>

			<?php else : ?>

				<article class="j5-page j5-page--not-found">
					<header class="j5-page__header">
						<h1 class="j5-page__title"><?php esc_html_e( 'Nothing found', 'astra-child' ); ?></h1>
					</header>
					<div class="j5-page__content entry-content">
						<p><?php esc_html_e( 'The page you are looking for does not exist or has moved.', 'astra-child' ); ?></p>
						<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'astra-child' ); ?></a></p>
					</div>
				</article>

			<?php endif; ?>

		</div>
	</div>
</main>

<?php
get_footer();
