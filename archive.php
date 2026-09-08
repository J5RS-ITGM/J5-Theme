<?php
/**
 * J5 News Archive Template
 *
 * Used for all archive views: the main posts index, categories,
 * tags, authors, date archives. This file is loaded automatically
 * by WordPress when viewing any archive.
 *
 * Renders as: featured post (hero) + category filter pills +
 * 3-column card grid + pagination.
 *
 * Uses the new chrome (get_header / get_footer).
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

/*
 * Header copy — archive page title, subtitle, eyebrow label.
 * Falls back through:
 *   1. Category/tag archive title if we're on a taxonomy archive
 *   2. Date archive title if we're on a date archive
 *   3. Default "News" title if we're on the main posts index
 *
 * To customize the default title, edit j5_news_get_archive_title()
 * in inc/j5-news-setup.php.
 */
if ( function_exists( 'j5_news_get_archive_header' ) ) {
	$j5_news_header = j5_news_get_archive_header();
} else {
	$j5_news_header = array(
		'eyebrow' => 'News &amp; field reports',
		'title'   => 'News',
		'desc'    => '',
	);
}

/*
 * Featured post = sticky posts first, else the most recent post.
 * Falls back to the current query's first post if nothing sticky.
 *
 * Only show the hero on page 1 of the main posts index.
 * On paginated pages and taxonomy archives, skip the hero so the
 * grid starts from the top — user is browsing, not landing.
 */
$j5_show_hero = ( is_home() || is_front_page() ) && ! is_paged();

$j5_featured_post = null;
if ( $j5_show_hero && function_exists( 'j5_news_get_featured_post' ) ) {
	$j5_featured_post = j5_news_get_featured_post();
}

/*
 * Collect IDs to exclude from the main loop so the featured post
 * doesn't appear twice (once in hero, once in grid).
 */
$j5_exclude_ids = array();
if ( $j5_featured_post ) {
	$j5_exclude_ids[] = $j5_featured_post->ID;
}

/*
 * Modify the main query: exclude featured post. Keep WP's default
 * pagination, orderby, posts_per_page.
 *
 * Using a filter rather than a new WP_Query so pagination, SEO,
 * and caching plugins all see the normal main query.
 */
if ( ! empty( $j5_exclude_ids ) ) {
	add_filter( 'posts_where', function ( $where ) use ( $j5_exclude_ids ) {
		global $wpdb;
		$ids_csv = implode( ',', array_map( 'intval', $j5_exclude_ids ) );
		$where  .= " AND {$wpdb->posts}.ID NOT IN ($ids_csv) ";
		return $where;
	} );
}

/*
 * Total count for the "Showing X-Y of Z" display.
 * Uses the main query's found_posts AFTER the posts_where filter
 * has excluded the featured post.
 */
global $wp_query;
$j5_per_page = (int) $wp_query->get( 'posts_per_page' );
$j5_current  = max( 1, (int) get_query_var( 'paged' ) );
$j5_total    = (int) $wp_query->found_posts;
$j5_from     = $j5_total ? ( ( $j5_current - 1 ) * $j5_per_page ) + 1 : 0;
$j5_to       = min( $j5_total, $j5_current * $j5_per_page );
?>

<div class="j5-news">

	<!-- === J5-NEWS-PAGE-HEADER-START === -->
	<section class="j5-news-header">
		<div class="j5-container">
			<p class="j5-news-eyebrow"><?php echo wp_kses_post( $j5_news_header['eyebrow'] ); ?></p>
			<h1 class="j5-news-page-title"><?php echo esc_html( $j5_news_header['title'] ); ?></h1>
			<?php if ( ! empty( $j5_news_header['desc'] ) ) : ?>
				<p class="j5-news-page-desc"><?php echo esc_html( $j5_news_header['desc'] ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<!-- === J5-NEWS-PAGE-HEADER-END === -->

	<?php if ( $j5_show_hero && $j5_featured_post ) : ?>
	<!-- === J5-NEWS-HERO-START === -->
	<section class="j5-news-hero-section">
		<div class="j5-container">
			<?php if ( function_exists( 'j5_news_render_hero_card' ) ) { j5_news_render_hero_card( $j5_featured_post ); } ?>
		</div>
	</section>
	<!-- === J5-NEWS-HERO-END === -->
	<?php endif; ?>

	<!-- === J5-NEWS-FILTER-BAR-START === -->
	<section class="j5-news-filter-section">
		<div class="j5-container">
			<div class="j5-news-filter-bar">
				<span class="j5-news-filter-label">Filter</span>
				<?php if ( function_exists( 'j5_news_render_filter_pills' ) ) { j5_news_render_filter_pills(); } ?>
				<?php if ( $j5_total > 0 ) : ?>
					<span class="j5-news-count">
						Showing <?php echo esc_html( $j5_from ); ?>&ndash;<?php echo esc_html( $j5_to ); ?> of <?php echo esc_html( $j5_total ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<!-- === J5-NEWS-FILTER-BAR-END === -->

	<!-- === J5-NEWS-GRID-START === -->
	<section class="j5-news-grid-section">
		<div class="j5-container">
			<?php if ( have_posts() ) : ?>
				<div class="j5-news-grid">
					<?php
					while ( have_posts() ) :
						the_post();
						if ( function_exists( 'j5_news_render_card' ) ) {
							j5_news_render_card( get_post() );
						}
					endwhile;
					?>
				</div>
			<?php else : ?>
				<div class="j5-news-empty">
					<p>No posts found. <?php if ( is_search() ) { echo 'Try a different search term.'; } else { echo 'Check back soon.'; } ?></p>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<!-- === J5-NEWS-GRID-END === -->

	<?php
	/*
	 * Pagination. Uses paginate_links() with J5-styled markup.
	 * If there's only one page, paginate_links() returns null and we
	 * skip rendering the entire section.
	 */
	$j5_pagination = paginate_links( array(
		'mid_size'  => 2,
		'prev_text' => '&larr; Prev',
		'next_text' => 'Next &rarr;',
		'type'      => 'array',
	) );
	?>
	<?php if ( ! empty( $j5_pagination ) ) : ?>
	<!-- === J5-NEWS-PAGINATION-START === -->
	<section class="j5-news-pagination-section">
		<div class="j5-container">
			<nav class="j5-news-pagination" aria-label="News pagination">
				<?php foreach ( $j5_pagination as $link ) : ?>
					<?php
					// paginate_links returns <a class="page-numbers">, <span class="current">, etc.
					// We reclass these to j5-page-btn for styling.
					$link = str_replace( 'class="page-numbers"', 'class="j5-page-btn"', $link );
					$link = str_replace( 'class="page-numbers current"', 'class="j5-page-btn is-active"', $link );
					$link = str_replace( 'class="prev page-numbers"', 'class="j5-page-btn j5-page-btn--wide"', $link );
					$link = str_replace( 'class="next page-numbers"', 'class="j5-page-btn j5-page-btn--wide"', $link );
					$link = str_replace( 'class="page-numbers dots"', 'class="j5-page-btn j5-page-btn--dots"', $link );
					echo wp_kses_post( $link );
					?>
				<?php endforeach; ?>
			</nav>
		</div>
	</section>
	<!-- === J5-NEWS-PAGINATION-END === -->
	<?php endif; ?>

</div><!-- .j5-news -->

<?php
get_footer();
