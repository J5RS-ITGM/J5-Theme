<?php
/**
 * ============================================================================
 *  J5 Landing — Category Archive Template (v1.1)
 * ============================================================================
 *
 *  CHANGELOG
 *  ---------
 *  v1.1:
 *    - Single-word hero support (Armor style — one big word, gold underline)
 *    - hero.subtitle support (alternative to hero.phrase for non-acronym pages)
 *    - hero.stats_variant — 'default' (big number) or 'small' (text-list)
 *    - hero.eyebrow_color — 'red' | 'gold' | 'blue'
 *    - Inline Agency & Department Buyers banner above the cat grid.
 *      Global default ON; controlled by config 'show_agency_banner' flag.
 *    - Generic secondary_callout config object replaces the hardcoded
 *      Kit Builder callout. Any page can ship any second callout.
 *    - section_heading / section_subheading config overrides
 *    - related_heading config override
 * ============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Resolve config for the current category ----------------------------------

$j5_queried = get_queried_object();
$j5_slug    = ( $j5_queried && ! empty( $j5_queried->slug ) ) ? $j5_queried->slug : '';
$j5_config  = $j5_slug ? j5_landing_config_for( $j5_slug ) : null;

if ( empty( $j5_config ) || ! empty( $j5_config['_stub'] ) ) {
	$j5_wc_path = WC()->plugin_path() . '/templates/archive-product.php';
	if ( file_exists( $j5_wc_path ) ) {
		include $j5_wc_path;
		return;
	}
	return;
}

// Defaults ------------------------------------------------------------------

$j5_show_banner         = isset( $j5_config['show_agency_banner'] ) ? (bool) $j5_config['show_agency_banner'] : true;
// Portal UI gated globally — see inc/j5-portal-flag.php.
$j5_portal_on           = function_exists( 'j5_portal_enabled' ) && j5_portal_enabled();
if ( ! $j5_portal_on ) {
	$j5_show_banner = false;
}
$j5_show_life_safety    = isset( $j5_config['show_life_safety_callout'] ) ? (bool) $j5_config['show_life_safety_callout'] : true;
$j5_secondary           = isset( $j5_config['secondary_callout'] ) ? $j5_config['secondary_callout'] : null;
// Suppress any secondary callout that points at the portal while it's disabled.
if ( ! $j5_portal_on && is_array( $j5_secondary ) && ! empty( $j5_secondary['link_url'] )
	&& false !== strpos( $j5_secondary['link_url'], 'ops.j5rescue.com' ) ) {
	$j5_secondary = null;
}
$j5_hero                = isset( $j5_config['hero'] ) ? $j5_config['hero'] : array();
$j5_section_heading     = ! empty( $j5_config['section_heading'] ) ? $j5_config['section_heading'] : ( $j5_queried->name ? strtoupper( $j5_queried->name ) : 'CATEGORIES' );
$j5_section_subheading  = isset( $j5_config['section_subheading'] ) ? $j5_config['section_subheading'] : '';

// Hero rendering helpers ----------------------------------------------------

$j5_eyebrow_color = ! empty( $j5_hero['eyebrow_color'] ) ? $j5_hero['eyebrow_color'] : 'red';
$j5_title_arr     = isset( $j5_hero['title'] ) ? $j5_hero['title'] : array();
$j5_title_is_word = ( count( $j5_title_arr ) === 1 && strlen( $j5_title_arr[0] ) > 1 );
$j5_stats_variant = ! empty( $j5_hero['stats_variant'] ) ? $j5_hero['stats_variant'] : 'default';

get_header();

?>

<div class="j5-landing-root">

	<?php
	// ========================================================================
	// HERO
	// ========================================================================
	?>
	<section class="j5-hero">
		<div class="j5-hero__inner">

			<?php if ( ! empty( $j5_hero['eyebrow'] ) ) : ?>
				<div class="j5-hero__eyebrow j5-hero__eyebrow--<?php echo esc_attr( $j5_eyebrow_color ); ?>">
					<span class="j5-hero__eyebrow-dot" aria-hidden="true"></span>
					<?php echo esc_html( $j5_hero['eyebrow'] ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $j5_title_arr ) ) : ?>
				<?php if ( $j5_title_is_word ) : ?>
					<?php // Single-word hero (Armor style) — one big word, one color underline ?>
					<h1 class="j5-hero__title j5-hero__title--word">
						<?php
						$j5_single_color = isset( $j5_hero['colors'][0] ) ? $j5_hero['colors'][0] : '#d4a044';
						$j5_word         = $j5_title_arr[0];
						?>
						<span class="j5-hero__word" style="--word-color: <?php echo esc_attr( $j5_single_color ); ?>;">
							<?php // Split the word into letters for consistent visual weight ?>
							<?php foreach ( str_split( $j5_word ) as $j5_char ) : ?>
								<span class="j5-hero__word-letter"><?php echo esc_html( $j5_char ); ?></span>
							<?php endforeach; ?>
						</span>
					</h1>
				<?php else : ?>
					<?php // Multi-letter hero (MARCH style) — per-letter accent underlines ?>
					<h1 class="j5-hero__title">
						<?php foreach ( $j5_title_arr as $j5_i => $j5_char ) :
							$j5_color = isset( $j5_hero['colors'][ $j5_i ] ) ? $j5_hero['colors'][ $j5_i ] : '#d4a044';
						?>
							<span class="j5-hero__title-letter" style="--letter-color: <?php echo esc_attr( $j5_color ); ?>;">
								<?php echo esc_html( $j5_char ); ?>
							</span>
						<?php endforeach; ?>
					</h1>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! empty( $j5_hero['phrase'] ) && is_array( $j5_hero['phrase'] ) ) : ?>
				<div class="j5-hero__phrase">
					<?php foreach ( $j5_hero['phrase'] as $j5_j => $j5_word ) : ?>
						<?php if ( $j5_j > 0 ) : ?>
							<span class="j5-hero__phrase-sep" aria-hidden="true">·</span>
						<?php endif; ?>
						<span><?php echo esc_html( $j5_word ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php elseif ( ! empty( $j5_hero['subtitle'] ) && is_array( $j5_hero['subtitle'] ) ) : ?>
				<div class="j5-hero__subtitle">
					<?php foreach ( $j5_hero['subtitle'] as $j5_j => $j5_word ) : ?>
						<?php if ( $j5_j > 0 ) : ?>
							<span class="j5-hero__subtitle-sep" aria-hidden="true">·</span>
						<?php endif; ?>
						<span><?php echo esc_html( $j5_word ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $j5_hero['body'] ) ) : ?>
				<p class="j5-hero__body"><?php echo wp_kses_post( $j5_hero['body'] ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $j5_hero['stats'] ) && is_array( $j5_hero['stats'] ) ) : ?>
				<div class="j5-hero__stats">
					<?php foreach ( $j5_hero['stats'] as $j5_stat ) :
						if ( ! is_array( $j5_stat ) || count( $j5_stat ) < 2 ) continue;
					?>
						<div class="j5-hero__stat">
							<div class="j5-hero__stat-num j5-hero__stat-num--<?php echo esc_attr( $j5_stats_variant ); ?>"><?php echo esc_html( $j5_stat[0] ); ?></div>
							<div class="j5-hero__stat-label"><?php echo esc_html( $j5_stat[1] ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		</div>
	</section>

	<?php
	// ========================================================================
	// SUBCATEGORY GRID (with optional inline agency banner above)
	// ========================================================================
	$j5_subcats = isset( $j5_config['subcats'] ) ? $j5_config['subcats'] : array();
	if ( ! empty( $j5_subcats ) ) :
		$j5_cols = min( 5, max( 1, count( $j5_subcats ) ) );
	?>
		<section class="j5-section">
			<div class="j5-section__head">
				<div>
					<h2 class="j5-section__title"><?php echo esc_html( $j5_section_heading ); ?></h2>
					<?php if ( $j5_section_subheading ) : ?>
						<div class="j5-section__subtitle"><?php echo esc_html( $j5_section_subheading ); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $j5_show_banner ) : ?>
				<div class="j5-inline-banner">
					<div class="j5-inline-banner__left">
						<span class="j5-inline-banner__label">Agency &amp; Department Buyers</span>
						<span class="j5-inline-banner__msg">Specialized pricing, Net 30 terms, and dedicated POCs are available on our agency portal</span>
					</div>
					<?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?><a href="<?php echo esc_url( j5_portal_url() ); ?>" class="j5-inline-banner__link" target="_blank" rel="noopener">ops.j5rescue.com &nbsp;→</a><?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="j5-cat-grid j5-cat-grid--<?php echo (int) $j5_cols; ?>-col" style="--cat-grid-cols: <?php echo (int) $j5_cols; ?>;">
				<?php foreach ( $j5_subcats as $j5_sub ) :
					$j5_info = j5_get_category_info( $j5_sub['slug'] );
					if ( ! $j5_info ) continue;

					// Resolve tile color
					$j5_tile_color = '';
					if ( ! empty( $j5_sub['color'] ) ) {
						$j5_tile_color = $j5_sub['color'];
					} elseif ( ! empty( $j5_sub['letter'] ) && ! empty( $j5_hero['title'] ) && ! empty( $j5_hero['colors'] ) ) {
						$j5_pos = array_search( $j5_sub['letter'], $j5_hero['title'], true );
						if ( false !== $j5_pos && isset( $j5_hero['colors'][ $j5_pos ] ) ) {
							$j5_tile_color = $j5_hero['colors'][ $j5_pos ];
						}
					}
					if ( ! $j5_tile_color ) { $j5_tile_color = '#d4a044'; }

					$j5_cta  = ! empty( $j5_sub['cta'] ) ? $j5_sub['cta'] : 'Shop ' . $j5_info['name'];
					$j5_icon = ! empty( $j5_sub['icon'] ) ? $j5_sub['icon'] : '';
				?>
					<a href="<?php echo esc_url( $j5_info['url'] ); ?>"
					   class="j5-cat-tile"
					   data-letter="<?php echo esc_attr( ! empty( $j5_sub['letter'] ) ? $j5_sub['letter'] : '' ); ?>"
					   style="--tile-color: <?php echo esc_attr( $j5_tile_color ); ?>;">

						<div class="j5-cat-tile__top">
							<?php if ( $j5_icon ) : ?>
								<div class="j5-cat-tile__icon" aria-hidden="true">
									<svg><use href="#icon-<?php echo esc_attr( $j5_icon ); ?>"></use></svg>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $j5_sub['eyebrow'] ) ) : ?>
								<div class="j5-cat-tile__eyebrow"><?php echo esc_html( $j5_sub['eyebrow'] ); ?></div>
							<?php endif; ?>

							<?php if ( ! empty( $j5_sub['name_html'] ) ) : ?>
								<div class="j5-cat-tile__name"><?php echo wp_kses_post( $j5_sub['name_html'] ); ?></div>
							<?php else : ?>
								<div class="j5-cat-tile__name"><?php echo esc_html( $j5_info['name'] ); ?></div>
							<?php endif; ?>

							<?php if ( ! empty( $j5_sub['desc'] ) ) : ?>
								<p class="j5-cat-tile__desc"><?php echo esc_html( $j5_sub['desc'] ); ?></p>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $j5_sub['chips'] ) && is_array( $j5_sub['chips'] ) ) : ?>
							<div class="j5-cat-tile__middle">
								<div class="j5-chip-row">
									<?php foreach ( $j5_sub['chips'] as $j5_chip ) : ?>
										<span class="j5-chip"><?php echo esc_html( $j5_chip ); ?></span>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>

						<div class="j5-cat-tile__cta"><?php echo esc_html( $j5_cta ); ?> &nbsp;→</div>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	// ========================================================================
	// FEATURED PRODUCTS
	// ========================================================================
	$j5_featured = array();
	if ( ! empty( $j5_config['featured_product_ids'] ) || ! empty( $j5_queried->term_id ) ) {
		$j5_featured = j5_get_landing_featured_products(
			isset( $j5_config['featured_product_ids'] ) ? $j5_config['featured_product_ids'] : array(),
			isset( $j5_queried->term_id ) ? (int) $j5_queried->term_id : 0,
			4
		);
	}

	if ( ! empty( $j5_featured ) ) :
	?>
		<section class="j5-products">
			<div class="j5-section">
				<div class="j5-section__head">
					<div>
						<h2 class="j5-section__title">FIELD <span class="j5-section__title-accent">ESSENTIALS</span></h2>
						<div class="j5-section__subtitle">Most-ordered · Always in stock</div>
					</div>
				</div>

				<div class="j5-product-grid">
					<?php foreach ( $j5_featured as $j5_post ) :
						$j5_product = wc_get_product( $j5_post->ID );
						if ( ! $j5_product ) continue;

						$j5_thumb_id   = $j5_product->get_image_id();
						$j5_thumb_url  = $j5_thumb_id ? wp_get_attachment_image_url( $j5_thumb_id, 'woocommerce_thumbnail' ) : '';
						$j5_sku        = $j5_product->get_sku();
						$j5_in_stock   = $j5_product->is_in_stock();
						$j5_price_html = $j5_product->get_price_html();
						$j5_url        = get_permalink( $j5_post->ID );
						$j5_brand      = '';
					?>
						<article class="j5-product">
							<a href="<?php echo esc_url( $j5_url ); ?>" class="j5-product__image">
								<?php if ( $j5_thumb_url ) : ?>
									<img src="<?php echo esc_url( $j5_thumb_url ); ?>" alt="<?php echo esc_attr( $j5_post->post_title ); ?>" loading="lazy" />
								<?php else : ?>
									<div class="j5-product__image-placeholder" aria-hidden="true"></div>
								<?php endif; ?>

								<div class="j5-product__badges">
									<?php if ( $j5_in_stock ) : ?>
										<span class="j5-badge j5-badge--instock">In Stock</span>
									<?php endif; ?>
								</div>

								<?php if ( $j5_sku ) : ?>
									<div class="j5-product__sku">SKU · <?php echo esc_html( $j5_sku ); ?></div>
								<?php endif; ?>
							</a>

							<div class="j5-product__info">
								<?php if ( $j5_brand ) : ?>
									<div class="j5-product__brand"><?php echo esc_html( $j5_brand ); ?></div>
								<?php endif; ?>
								<h3 class="j5-product__name"><a href="<?php echo esc_url( $j5_url ); ?>" style="color:inherit;"><?php echo esc_html( $j5_post->post_title ); ?></a></h3>

								<div class="j5-product__meta-row">
									<div class="j5-product__price"><?php echo wp_kses_post( $j5_price_html ); ?></div>
									<a href="<?php echo esc_url( $j5_url ); ?>" class="j5-product__cta">View →</a>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
	// ========================================================================
	// DUAL CALLOUT — Life Safety (fixed) + secondary (config-driven)
	// ========================================================================
	if ( $j5_show_life_safety || ! empty( $j5_secondary ) ) :
	?>
		<section class="j5-callout-row">

			<?php if ( $j5_show_life_safety ) : ?>
				<div class="j5-callout j5-callout--guarantee">
					<div class="j5-callout__kicker">Life Safety Replacement Guarantee</div>
					<h3 class="j5-callout__title">Use It. We&rsquo;ll<br>Replace It.</h3>
					<p class="j5-callout__body">
						When equipment marked with the <strong>Life Safety Guarantee</strong> is used in the field to save a life, it&rsquo;s eligible for free replacement. Deploy the tourniquet. Open the chest seal. Pack the wound. Then send us the report &mdash; we&rsquo;ll restock your kit. No forms to fight through.
					</p>
					<a href="<?php echo esc_url( home_url( '/life-safety-replacement-guarantee/' ) ); ?>" class="j5-callout__link">See what qualifies &nbsp;→</a>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $j5_secondary ) ) :
				$j5_sec_target = ! empty( $j5_secondary['link_target'] ) ? $j5_secondary['link_target'] : '_self';
				$j5_sec_rel    = ( '_blank' === $j5_sec_target ) ? ' rel="noopener"' : '';
			?>
				<div class="j5-callout j5-callout--secondary">
					<?php if ( ! empty( $j5_secondary['kicker'] ) ) : ?>
						<div class="j5-callout__kicker"><?php echo esc_html( $j5_secondary['kicker'] ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $j5_secondary['title_html'] ) ) : ?>
						<h3 class="j5-callout__title"><?php echo wp_kses_post( $j5_secondary['title_html'] ); ?></h3>
					<?php endif; ?>
					<?php if ( ! empty( $j5_secondary['body_html'] ) ) : ?>
						<p class="j5-callout__body"><?php echo wp_kses_post( $j5_secondary['body_html'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $j5_secondary['link_url'] ) ) :
						$j5_sec_url  = ( 0 === strpos( $j5_secondary['link_url'], 'http' ) )
							? $j5_secondary['link_url']
							: home_url( $j5_secondary['link_url'] );
						$j5_sec_text = ! empty( $j5_secondary['link_text'] ) ? $j5_secondary['link_text'] : 'Learn more';
					?>
						<a href="<?php echo esc_url( $j5_sec_url ); ?>" class="j5-callout__link" target="<?php echo esc_attr( $j5_sec_target ); ?>"<?php echo $j5_sec_rel; ?>><?php echo esc_html( $j5_sec_text ); ?> &nbsp;→</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		</section>
	<?php endif; ?>

	<?php
	// ========================================================================
	// RELATED
	// ========================================================================
	$j5_related = isset( $j5_config['related_category_slugs'] ) ? $j5_config['related_category_slugs'] : array();
	$j5_related_heading_html = isset( $j5_config['related_heading'] ) ? $j5_config['related_heading'] : '';

	if ( ! empty( $j5_related ) ) :
		$j5_related_resolved = array();
		foreach ( $j5_related as $j5_r_slug ) {
			$j5_r_info = j5_get_category_info( $j5_r_slug );
			if ( $j5_r_info ) { $j5_related_resolved[] = $j5_r_info; }
		}

		if ( ! empty( $j5_related_resolved ) ) :
			if ( empty( $j5_related_heading_html ) ) {
				$j5_parent_name = '';
				if ( $j5_queried->parent ) {
					$j5_p = get_term( $j5_queried->parent, 'product_cat' );
					if ( $j5_p && ! is_wp_error( $j5_p ) ) {
						$j5_parent_name = html_entity_decode( $j5_p->name, ENT_QUOTES, 'UTF-8' );
					}
				}
				$j5_related_heading_html = $j5_parent_name
					? 'Related in <span class="j5-accent">' . esc_html( $j5_parent_name ) . '</span>'
					: 'Related';
			}
	?>
			<section class="j5-related">
				<div class="j5-related__head">
					<div>
						<h2 class="j5-related__title"><?php echo wp_kses_post( $j5_related_heading_html ); ?></h2>
						<div class="j5-section__subtitle">Jump to adjacent categories</div>
					</div>
				</div>
				<div class="j5-related__grid">
					<?php foreach ( $j5_related_resolved as $j5_r ) : ?>
						<a href="<?php echo esc_url( $j5_r['url'] ); ?>" class="j5-related__chip">
							<span class="j5-related__chip-name"><?php echo esc_html( $j5_r['name'] ); ?></span>
							<span class="j5-related__chip-count"><?php echo (int) $j5_r['count']; ?> <?php echo 1 === (int) $j5_r['count'] ? 'item' : 'items'; ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
	<?php
		endif;
	endif;
	?>

</div>

<?php
get_footer();
