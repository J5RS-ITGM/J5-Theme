<?php
/**
 * J5 Single Blog Post Template
 *
 * WordPress template hierarchy: single-post.php applies ONLY to
 * post type 'post'. Products (woocommerce/single-product.php),
 * pages, and other single-* templates are unaffected.
 *
 * Layout: category link / title / subtitle (excerpt) / meta line /
 * featured image / 700px reading column / tags / CTA / prev-next /
 * related grid. Styles in assets/css/j5-single.css, enqueued by
 * inc/j5-single-setup.php.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

while ( have_posts() ) :
	the_post();

	$j5_post_id  = get_the_ID();
	$j5_cats     = get_the_category();
	$j5_cat      = ( ! empty( $j5_cats ) && ! is_wp_error( $j5_cats ) ) ? $j5_cats[0] : null;
	$j5_excerpt  = has_excerpt() ? get_the_excerpt() : '';
	$j5_readtime = function_exists( 'j5_news_read_time' ) ? j5_news_read_time( $j5_post_id ) : '';

	/*
	 * Show "Updated" only when the modified date is meaningfully
	 * later than publish (> 30 days) — otherwise it's noise.
	 */
	$j5_show_updated = ( get_the_modified_time( 'U' ) - get_the_time( 'U' ) ) > 30 * DAY_IN_SECONDS;

	$j5_prev = get_previous_post();
	$j5_next = get_next_post();
	$j5_related = function_exists( 'j5_single_get_related_posts' ) ? j5_single_get_related_posts( $j5_post_id, 3 ) : array();
	$j5_cta = function_exists( 'j5_single_get_cta' ) ? j5_single_get_cta( $j5_post_id ) : array();
	?>

	<article <?php post_class( 'j5-single' ); ?>>

		<!-- === J5-SINGLE-HEADER-START === -->
		<header class="j5-single-head">
			<?php if ( $j5_cat ) : ?>
				<div class="j5-single-cat">
					<a href="<?php echo esc_url( get_category_link( $j5_cat ) ); ?>"><?php echo esc_html( wp_specialchars_decode( $j5_cat->name ) ); ?></a>
				</div>
			<?php endif; ?>

			<h1 class="j5-single-title"><?php the_title(); ?></h1>

			<?php if ( $j5_excerpt ) : ?>
				<p class="j5-single-sub"><?php echo esc_html( $j5_excerpt ); ?></p>
			<?php endif; ?>

			<div class="j5-single-meta">
				By <b><?php the_author(); ?></b>
				&nbsp;&middot;&nbsp; <?php echo esc_html( get_the_date() ); ?>
				<?php if ( $j5_show_updated ) : ?>
					&nbsp;&middot;&nbsp; Updated <?php echo esc_html( get_the_modified_date( 'F Y' ) ); ?>
				<?php endif; ?>
				<?php if ( $j5_readtime ) : ?>
					&nbsp;&middot;&nbsp; <?php echo esc_html( $j5_readtime ); ?>
				<?php endif; ?>
			</div>
		</header>
		<!-- === J5-SINGLE-HEADER-END === -->

		<?php if ( has_post_thumbnail() ) : ?>
		<!-- === J5-SINGLE-HERO-START === -->
		<figure class="j5-single-hero">
			<?php the_post_thumbnail( 'large' ); ?>
			<?php
			$j5_thumb_caption = get_the_post_thumbnail_caption();
			if ( $j5_thumb_caption ) :
			?>
				<figcaption><?php echo esc_html( $j5_thumb_caption ); ?></figcaption>
			<?php endif; ?>
		</figure>
		<!-- === J5-SINGLE-HERO-END === -->
		<?php endif; ?>

		<!-- === J5-SINGLE-BODY-START === -->
		<div class="j5-single-body">
			<?php the_content(); ?>
		</div>
		<!-- === J5-SINGLE-BODY-END === -->

		<?php
		$j5_tags = get_the_tags();
		if ( $j5_tags && ! is_wp_error( $j5_tags ) ) :
		?>
		<!-- === J5-SINGLE-TAGS-START === -->
		<div class="j5-single-tags">
			Tagged:
			<?php foreach ( $j5_tags as $j5_tag ) : ?>
				<a href="<?php echo esc_url( get_tag_link( $j5_tag ) ); ?>"><?php echo esc_html( wp_specialchars_decode( $j5_tag->name ) ); ?></a>
			<?php endforeach; ?>
		</div>
		<!-- === J5-SINGLE-TAGS-END === -->
		<?php endif; ?>

		<?php if ( ! empty( $j5_cta['url'] ) ) : ?>
		<!-- === J5-SINGLE-CTA-START === -->
		<div class="j5-single-cta">
			<p><b><?php echo esc_html( $j5_cta['heading'] ); ?></b><?php echo esc_html( $j5_cta['text'] ); ?></p>
			<a class="j5-single-btn" href="<?php echo esc_url( $j5_cta['url'] ); ?>"><?php echo esc_html( $j5_cta['label'] ); ?></a>
		</div>
		<!-- === J5-SINGLE-CTA-END === -->
		<?php endif; ?>

		<?php if ( $j5_prev || $j5_next ) : ?>
		<!-- === J5-SINGLE-PREVNEXT-START === -->
		<nav class="j5-single-nav" aria-label="Post navigation">
			<?php if ( $j5_prev ) : ?>
				<a href="<?php echo esc_url( get_permalink( $j5_prev ) ); ?>">
					<span class="dir">&larr; Previous</span>
					<span class="t"><?php echo esc_html( get_the_title( $j5_prev ) ); ?></span>
				</a>
			<?php else : ?>
				<span></span>
			<?php endif; ?>
			<?php if ( $j5_next ) : ?>
				<a href="<?php echo esc_url( get_permalink( $j5_next ) ); ?>" class="next">
					<span class="dir">Next &rarr;</span>
					<span class="t"><?php echo esc_html( get_the_title( $j5_next ) ); ?></span>
				</a>
			<?php endif; ?>
		</nav>
		<!-- === J5-SINGLE-PREVNEXT-END === -->
		<?php endif; ?>

		<?php if ( ! empty( $j5_related ) ) : ?>
		<!-- === J5-SINGLE-RELATED-START === -->
		<section class="j5-single-related">
			<h3>More from the news desk</h3>
			<div class="grid">
				<?php foreach ( $j5_related as $j5_rel ) :
					$j5_rel_cats = get_the_category( $j5_rel->ID );
					$j5_rel_cat  = ( ! empty( $j5_rel_cats ) && ! is_wp_error( $j5_rel_cats ) ) ? $j5_rel_cats[0] : null;
				?>
					<a class="j5-single-rcard" href="<?php echo esc_url( get_permalink( $j5_rel ) ); ?>">
						<span class="thumb">
							<?php if ( has_post_thumbnail( $j5_rel ) ) { echo get_the_post_thumbnail( $j5_rel, 'medium_large' ); } ?>
						</span>
						<?php if ( $j5_rel_cat ) : ?>
							<span class="cat"><?php echo esc_html( wp_specialchars_decode( $j5_rel_cat->name ) ); ?></span>
						<?php endif; ?>
						<span class="t"><?php echo esc_html( get_the_title( $j5_rel ) ); ?></span>
						<span class="d"><?php echo esc_html( get_the_date( '', $j5_rel ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<!-- === J5-SINGLE-RELATED-END === -->
		<?php endif; ?>

		<?php
		// Comments render only if enabled for this post (or existing).
		if ( comments_open() || get_comments_number() ) {
			echo '<div class="j5-single-comments">';
			comments_template();
			echo '</div>';
		}
		?>

	</article>

	<?php
endwhile;

get_footer();
