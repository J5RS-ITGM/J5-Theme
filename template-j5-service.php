<?php
/**
 * Template Name: J5 Service Page
 * Template Post Type: page
 *
 * Custom service page template for J5 Rescue Supply.
 * Use for FFL Services, Design & Embroidery, Engraving, etc.
 *
 * Editable fields per page:
 *   - Page Title              (standard WP title field)
 *   - Featured Image          (used as hero background; falls back to dark gradient)
 *   - Eyebrow Text            (custom metabox - small uppercase line above title)
 *   - Hero CTA Label + URL    (custom metabox - optional button under intro)
 *   - Page Content            (block editor - paragraphs, headings, columns,
 *                              and J5 block patterns)
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$j5_eyebrow      = get_post_meta( get_the_ID(), '_j5_service_eyebrow', true );
$j5_cta_label    = get_post_meta( get_the_ID(), '_j5_service_cta_label', true );
$j5_cta_url      = get_post_meta( get_the_ID(), '_j5_service_cta_url', true );
$j5_hero_bg      = get_the_post_thumbnail_url( get_the_ID(), 'full' );
$j5_hero_classes = $j5_hero_bg ? 'j5-service-hero has-image' : 'j5-service-hero no-image';
?>

<main id="primary" class="site-main j5-service-page">

	<?php while ( have_posts() ) : the_post(); ?>

		<section class="<?php echo esc_attr( $j5_hero_classes ); ?>"
			<?php if ( $j5_hero_bg ) : ?>
				style="background-image: url('<?php echo esc_url( $j5_hero_bg ); ?>');"
			<?php endif; ?>
		>
			<div class="j5-service-hero-overlay"></div>

			<div class="j5-container j5-service-hero-inner">

				<?php if ( ! empty( $j5_eyebrow ) ) : ?>
					<p class="j5-service-eyebrow"><?php echo esc_html( $j5_eyebrow ); ?></p>
				<?php endif; ?>

				<h1 class="j5-service-title"><?php the_title(); ?></h1>

				<?php
				// Use the page excerpt as the hero subtitle/intro line if set.
				$j5_intro = has_excerpt() ? get_the_excerpt() : '';
				if ( ! empty( $j5_intro ) ) :
					?>
					<p class="j5-service-intro"><?php echo esc_html( $j5_intro ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $j5_cta_label ) && ! empty( $j5_cta_url ) ) : ?>
					<p class="j5-service-cta-row">
						<a class="j5-btn j5-btn-primary" href="<?php echo esc_url( $j5_cta_url ); ?>">
							<?php echo esc_html( $j5_cta_label ); ?>
						</a>
					</p>
				<?php endif; ?>

			</div>
		</section>

		<section class="j5-service-body">
			<div class="j5-container">
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'j5-service-article' ); ?>>
					<div class="j5-service-content entry-content">
						<?php the_content(); ?>
					</div>
				</article>
			</div>
		</section>

	<?php endwhile; ?>

</main>

<?php
get_footer();
