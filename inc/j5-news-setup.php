<?php
/**
 * J5 News — helper functions
 *
 * All functions prefixed with j5_news_ for global namespace safety.
 * Included from functions.php via a require_once (see install.sh output).
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 * CSS enqueue — only loads on archive/home views.
 * ============================================================ */

if ( ! function_exists( 'j5_news_enqueue_assets' ) ) {
	function j5_news_enqueue_assets() {
		if ( ! ( is_home() || is_archive() ) ) {
			return;
		}

		$rel_path = 'assets/css/j5-news.css';
		$abs_path = get_stylesheet_directory() . '/' . $rel_path;
		if ( ! file_exists( $abs_path ) ) {
			return;
		}

		wp_enqueue_style(
			'j5-news',
			get_stylesheet_directory_uri() . '/' . $rel_path,
			array(),
			filemtime( $abs_path )
		);
	}
	add_action( 'wp_enqueue_scripts', 'j5_news_enqueue_assets', 30 );
}

/* ============================================================
 * Archive header copy
 *
 * Returns array { eyebrow, title, desc } used by archive.php to
 * render the page-top block. Automatically adapts to the archive
 * type — main index, category, tag, author, date.
 *
 * To customize titles/descriptions, edit the values here.
 * ============================================================ */

if ( ! function_exists( 'j5_news_get_archive_header' ) ) {
	function j5_news_get_archive_header() {
		$header = array(
			'eyebrow' => 'The J5 Blog',
			'title'   => 'News &amp; Articles',         /* EDIT: default page title */
			'desc'    => 'Product notes, responder resources, and buying guidance from the J5 team.', /* EDIT: default subtitle */
		);

		if ( is_category() ) {
			$header['eyebrow'] = 'Category';
			$header['title']   = single_cat_title( '', false );
			$header['desc']    = strip_tags( category_description() );
		} elseif ( is_tag() ) {
			$header['eyebrow'] = 'Tag';
			$header['title']   = single_tag_title( '', false );
			$header['desc']    = strip_tags( tag_description() );
		} elseif ( is_author() ) {
			$header['eyebrow'] = 'Author';
			$header['title']   = get_the_author();
		} elseif ( is_year() ) {
			$header['eyebrow'] = 'Archive';
			$header['title']   = get_the_date( 'Y' );
		} elseif ( is_month() ) {
			$header['eyebrow'] = 'Archive';
			$header['title']   = get_the_date( 'F Y' );
		} elseif ( is_search() ) {
			$header['eyebrow'] = 'Search results';
			$header['title']   = get_search_query();
		}

		/*
		 * Filter: j5_news_archive_header — overriding anywhere lets you
		 * customize without editing this file.
		 */
		return apply_filters( 'j5_news_archive_header', $header );
	}
}

/* ============================================================
 * Featured post for hero
 *
 * Priority:
 *   1. First sticky post (if any)
 *   2. Most recent post
 *
 * Returns WP_Post or null.
 * ============================================================ */

if ( ! function_exists( 'j5_news_get_featured_post' ) ) {
	function j5_news_get_featured_post() {
		$sticky_ids = get_option( 'sticky_posts', array() );
		if ( ! empty( $sticky_ids ) ) {
			$sticky_query = new WP_Query( array(
				'post__in'            => $sticky_ids,
				'posts_per_page'      => 1,
				'ignore_sticky_posts' => 1,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'no_found_rows'       => true,
			) );
			if ( $sticky_query->have_posts() ) {
				return $sticky_query->posts[0];
			}
		}

		/* No sticky — use most recent. */
		$recent = new WP_Query( array(
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );
		if ( $recent->have_posts() ) {
			return $recent->posts[0];
		}

		return null;
	}
}

/* ============================================================
 * Filter pill rendering
 *
 * Renders one "All" pill + one pill per category that has posts.
 * Active state is set by comparing current queried_object to each
 * category.
 *
 * No JS required — each pill is a plain link to the category
 * archive. WP handles routing.
 * ============================================================ */

if ( ! function_exists( 'j5_news_render_filter_pills' ) ) {
	function j5_news_render_filter_pills() {
		$all_url     = home_url( '/' );
		$posts_page  = (int) get_option( 'page_for_posts' );
		if ( $posts_page ) {
			$all_url = get_permalink( $posts_page );
		}

		$is_all_active = ( is_home() && ! is_category() && ! is_tag() );
		$all_class     = 'j5-news-filter' . ( $is_all_active ? ' is-active' : '' );

		printf(
			'<a href="%s" class="%s">All</a>',
			esc_url( $all_url ),
			esc_attr( $all_class )
		);

		$cats = get_categories( array(
			'orderby'    => 'count',
			'order'      => 'DESC',
			'hide_empty' => true,
			'number'     => 8, /* cap at 8 so the filter bar doesn't wrap endlessly */
		) );

		if ( empty( $cats ) ) {
			return;
		}

		$current_cat_id = is_category() ? (int) get_queried_object_id() : 0;

		foreach ( $cats as $cat ) {
			$is_active = ( $current_cat_id === (int) $cat->term_id );
			$class     = 'j5-news-filter' . ( $is_active ? ' is-active' : '' );
			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( get_category_link( $cat->term_id ) ),
				esc_attr( $class ),
				esc_html( $cat->name )
			);
		}
	}
}

/* ============================================================
 * Category accent color
 *
 * Returns 'gold', 'red', or 'news' (muted gray) based on the
 * primary category slug. Lets the cards visually encode content
 * type at a glance.
 *
 * To map a category to a color, add its slug to one of the arrays
 * below. Slugs NOT listed default to 'news' (muted gray).
 * ============================================================ */

if ( ! function_exists( 'j5_news_category_accent' ) ) {
	function j5_news_category_accent( $post_id = 0 ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		$cats    = get_the_category( $post_id );
		if ( empty( $cats ) ) {
			return 'news';
		}

		$slug = strtolower( $cats[0]->slug );

		/* EDIT these arrays to change which categories get which color. */
		$red_slugs  = array( 'product-news', 'new-arrival', 'new-arrivals', 'restock', 'product-launch', 'launch', 'sale', 'clearance' );
		$gold_slugs = array( 'gear-guides', 'gear-guide', 'guide', 'guides', 'educational', 'industry', 'training', 'how-to' );
		$news_slugs = array( 'company', 'company-news', 'news', 'events', 'partnerships' );

		if ( in_array( $slug, $red_slugs, true ) ) {
			return 'red';
		}
		if ( in_array( $slug, $gold_slugs, true ) ) {
			return 'gold';
		}
		if ( in_array( $slug, $news_slugs, true ) ) {
			return 'news';
		}

		return 'gold'; /* default = gold (editorial) */
	}
}

/* ============================================================
 * Read time estimate
 *
 * Naive word-count divided by 225 wpm. Good enough for editorial.
 * ============================================================ */

if ( ! function_exists( 'j5_news_read_time' ) ) {
	function j5_news_read_time( $post_id = 0 ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$words = str_word_count( wp_strip_all_tags( $post->post_content ) );
		$mins  = max( 1, (int) ceil( $words / 225 ) );
		return sprintf( '%d min read', $mins );
	}
}

/* ============================================================
 * Featured hero card renderer
 * ============================================================ */

if ( ! function_exists( 'j5_news_render_hero_card' ) ) {
	function j5_news_render_hero_card( $post ) {
		if ( ! $post ) {
			return;
		}
		$post_id      = $post->ID;
		$permalink    = get_permalink( $post_id );
		$title        = get_the_title( $post_id );
		$excerpt      = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( strip_shortcodes( $post->post_content ), 32, '&hellip;' );
		$date         = get_the_date( 'M j, Y', $post_id );
		$author       = get_the_author_meta( 'display_name', $post->post_author );
		$read_time    = j5_news_read_time( $post_id );
		$accent       = j5_news_category_accent( $post_id );
		$cats         = get_the_category( $post_id );
		$primary_cat  = ! empty( $cats ) ? $cats[0] : null;
		$cat_name     = $primary_cat ? $primary_cat->name : '';
		$cat_link     = $primary_cat ? get_category_link( $primary_cat->term_id ) : '';

		$pill_modifier = '';
		if ( $accent === 'red' ) {
			$pill_modifier = ' j5-cat-pill--red';
		} elseif ( $accent === 'news' ) {
			$pill_modifier = ' j5-cat-pill--news';
		}

		$thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
		?>
		<article class="j5-news-hero">
			<a class="j5-news-hero-img" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
				<?php if ( $thumb_url ) : ?>
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="eager" />
				<?php else : ?>
					<span class="j5-news-placeholder-label">No featured image</span>
				<?php endif; ?>
			</a>
			<div class="j5-news-hero-content">
				<span class="j5-news-featured-tag">Featured</span>
				<?php if ( $primary_cat ) : ?>
					<a href="<?php echo esc_url( $cat_link ); ?>" class="j5-cat-pill<?php echo esc_attr( $pill_modifier ); ?>"><?php echo esc_html( $cat_name ); ?></a>
				<?php endif; ?>
				<h2 class="j5-news-hero-title">
					<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
				</h2>
				<?php if ( $excerpt ) : ?>
					<p class="j5-news-hero-excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
				<div class="j5-news-meta-row">
					<span><?php echo esc_html( $date ); ?></span>
					<?php if ( $read_time ) : ?>
						<span class="dot">&bull;</span>
						<span><?php echo esc_html( $read_time ); ?></span>
					<?php endif; ?>
					<?php if ( $author ) : ?>
						<span class="dot">&bull;</span>
						<span><?php echo esc_html( $author ); ?></span>
					<?php endif; ?>
				</div>
				<a class="j5-news-cta-link" href="<?php echo esc_url( $permalink ); ?>">Read the story <span class="arrow">&rarr;</span></a>
			</div>
		</article>
		<?php
	}
}

/* ============================================================
 * Post card renderer (grid cell)
 * ============================================================ */

if ( ! function_exists( 'j5_news_render_card' ) ) {
	function j5_news_render_card( $post ) {
		if ( ! $post ) {
			return;
		}
		$post_id    = $post->ID;
		$permalink  = get_permalink( $post_id );
		$title      = get_the_title( $post_id );
		$excerpt    = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( strip_shortcodes( $post->post_content ), 20, '&hellip;' );
		$date       = get_the_date( 'M j', $post_id );
		$read_time  = j5_news_read_time( $post_id );
		$accent     = j5_news_category_accent( $post_id );
		$cats       = get_the_category( $post_id );
		$primary_cat = ! empty( $cats ) ? $cats[0] : null;
		$cat_name   = $primary_cat ? $primary_cat->name : '';

		$cat_class = 'j5-news-card-cat';
		if ( $accent === 'red' ) {
			$cat_class .= ' j5-news-card-cat--red';
		} elseif ( $accent === 'news' ) {
			$cat_class .= ' j5-news-card-cat--news';
		}

		$thumb_url = get_the_post_thumbnail_url( $post_id, 'medium_large' );
		?>
		<article class="j5-news-card">
			<a class="j5-news-card-img" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
				<?php if ( $thumb_url ) : ?>
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
				<?php else : ?>
					<span class="j5-news-placeholder-label">No image</span>
				<?php endif; ?>
			</a>
			<div class="j5-news-card-body">
				<?php if ( $cat_name ) : ?>
					<span class="<?php echo esc_attr( $cat_class ); ?>"><?php echo esc_html( $cat_name ); ?></span>
				<?php endif; ?>
				<h3 class="j5-news-card-title">
					<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
				</h3>
				<?php if ( $excerpt ) : ?>
					<p class="j5-news-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
				<div class="j5-news-card-meta"><?php echo esc_html( $date ); ?><?php if ( $read_time ) : ?> &middot; <?php echo esc_html( $read_time ); endif; ?></div>
			</div>
		</article>
		<?php
	}
}

/* ============================================================
 * Latest articles section
 *
 * Reusable for hand-coded templates and the [j5_latest_articles]
 * shortcode. Intended for homepage/landing sections that should
 * preview recent news without loading the full archive layout.
 * ============================================================ */

if ( ! function_exists( 'j5_news_enqueue_article_section_assets' ) ) {
	function j5_news_enqueue_article_section_assets() {
		static $j5_articles_css_enqueued = false;

		if ( $j5_articles_css_enqueued ) {
			return;
		}

		$rel_path = 'assets/css/j5-article-section.css';
		$abs_path = get_stylesheet_directory() . '/' . $rel_path;

		if ( ! file_exists( $abs_path ) ) {
			return;
		}

		wp_enqueue_style(
			'j5-article-section',
			get_stylesheet_directory_uri() . '/' . $rel_path,
			array(),
			filemtime( $abs_path )
		);

		$j5_articles_css_enqueued = true;
	}
}

if ( ! function_exists( 'j5_news_maybe_enqueue_article_section_assets' ) ) {
	function j5_news_maybe_enqueue_article_section_assets() {
		if ( is_home() || is_front_page() ) {
			j5_news_enqueue_article_section_assets();
			return;
		}

		if ( is_singular() ) {
			$post = get_post();
			if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'j5_latest_articles' ) ) {
				j5_news_enqueue_article_section_assets();
			}
		}
	}
	add_action( 'wp_enqueue_scripts', 'j5_news_maybe_enqueue_article_section_assets', 31 );
}

if ( ! function_exists( 'j5_news_get_posts_page_url' ) ) {
	function j5_news_get_posts_page_url() {
		$posts_page = (int) get_option( 'page_for_posts' );

		if ( $posts_page ) {
			return get_permalink( $posts_page );
		}

		return home_url( '/' );
	}
}

if ( ! function_exists( 'j5_news_render_latest_section' ) ) {
	function j5_news_render_latest_section( $args = array() ) {
		j5_news_enqueue_article_section_assets();

		$defaults = array(
			'posts_per_page' => 3,
			'eyebrow'        => 'FROM THE BLOG',
			'title'          => 'Latest from the news desk.',
			'description'    => 'Product notes, responder resources, and field-tested guidance from the J5 team.',
			'button_label'   => 'View All Articles',
			'button_url'     => j5_news_get_posts_page_url(),
			'category'       => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $args['posts_per_page'] ),
			'ignore_sticky_posts' => 1,
			'no_found_rows'       => true,
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['category_name'] = sanitize_title( $args['category'] );
		}

		$articles = new WP_Query( $query_args );

		if ( ! $articles->have_posts() ) {
			return;
		}
		?>
		<section class="j5-articles-section" aria-labelledby="j5-articles-title">
			<div class="j5-container">
				<div class="j5-articles-head">
					<div>
						<?php if ( ! empty( $args['eyebrow'] ) ) : ?>
							<div class="j5-block-label"><?php echo esc_html( $args['eyebrow'] ); ?></div>
						<?php endif; ?>
						<h2 id="j5-articles-title" class="j5-block-title"><?php echo esc_html( $args['title'] ); ?></h2>
						<?php if ( ! empty( $args['description'] ) ) : ?>
							<p class="j5-articles-desc"><?php echo esc_html( $args['description'] ); ?></p>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $args['button_label'] ) && ! empty( $args['button_url'] ) ) : ?>
						<a href="<?php echo esc_url( $args['button_url'] ); ?>" class="j5-btn j5-btn-ghost">
							<?php echo esc_html( $args['button_label'] ); ?> <span class="arrow">&rarr;</span>
						</a>
					<?php endif; ?>
				</div>

				<div class="j5-articles-grid">
					<?php
					while ( $articles->have_posts() ) :
						$articles->the_post();

						$post_id   = get_the_ID();
						$permalink = get_permalink( $post_id );
						$title     = get_the_title( $post_id );
						$excerpt   = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( strip_shortcodes( get_the_content( null, false, $post_id ) ), 22, '&hellip;' );
						$date      = get_the_date( 'M j, Y', $post_id );
						$read_time = function_exists( 'j5_news_read_time' ) ? j5_news_read_time( $post_id ) : '';
						$thumb_url = get_the_post_thumbnail_url( $post_id, 'medium_large' );
						$cats      = get_the_category( $post_id );
						$cat_name  = ! empty( $cats ) ? $cats[0]->name : 'News';
						?>
						<article class="j5-article-card">
							<a class="j5-article-media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
								<?php if ( $thumb_url ) : ?>
									<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
								<?php else : ?>
									<span>J5</span>
								<?php endif; ?>
							</a>
							<div class="j5-article-body">
								<div class="j5-article-kicker">
									<span><?php echo esc_html( $cat_name ); ?></span>
									<span><?php echo esc_html( $date ); ?></span>
								</div>
								<h3 class="j5-article-title">
									<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
								</h3>
								<?php if ( $excerpt ) : ?>
									<p><?php echo esc_html( $excerpt ); ?></p>
								<?php endif; ?>
								<div class="j5-article-footer">
									<span><?php echo esc_html( $read_time ); ?></span>
									<a href="<?php echo esc_url( $permalink ); ?>">Read <span class="arrow">&rarr;</span></a>
								</div>
							</div>
						</article>
					<?php endwhile; ?>
				</div>
			</div>
		</section>
		<?php

		wp_reset_postdata();
	}
}

if ( ! function_exists( 'j5_news_latest_articles_shortcode' ) ) {
	function j5_news_latest_articles_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'count'        => 3,
				'eyebrow'      => 'FROM THE BLOG',
				'title'        => 'Latest from the news desk.',
				'description'  => 'Product notes, responder resources, and field-tested guidance from the J5 team.',
				'button_label' => 'View All Articles',
				'button_url'   => '',
				'category'     => '',
			),
			$atts,
			'j5_latest_articles'
		);

		ob_start();
		j5_news_render_latest_section( array(
			'posts_per_page' => (int) $atts['count'],
			'eyebrow'        => $atts['eyebrow'],
			'title'          => $atts['title'],
			'description'    => $atts['description'],
			'button_label'   => $atts['button_label'],
			'button_url'     => $atts['button_url'] ? esc_url_raw( $atts['button_url'] ) : j5_news_get_posts_page_url(),
			'category'       => $atts['category'],
		) );
		return ob_get_clean();
	}
	add_shortcode( 'j5_latest_articles', 'j5_news_latest_articles_shortcode' );
}
