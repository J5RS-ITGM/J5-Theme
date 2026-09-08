<?php
/**
 * J5 Archive Render — render functions for per-category archive enhancements
 *
 * All rendering keyed off j5_get_current_archive_config(). Each function
 * silently returns if no config is present, so they can be sprinkled into
 * archive-product.php without conditional guards in the template.
 *
 * Functions:
 *   j5_archive_render_hero()           — Per-category hero band (breadcrumb,
 *                                         eyebrow, title, body, meta strip,
 *                                         side stat or MARCH marker)
 *   j5_archive_render_brand_strip()    — Brand list strip below hero
 *   j5_archive_render_subcat_strip()   — Quick-filter chip strip above toolbar
 *   j5_archive_render_brand_grouped()  — Alternative product display, grouped
 *                                         by brand (used when layout =
 *                                         'brand-grouped' in config)
 *   j5_archive_should_use_brand_grouped() — true if config says so
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================================
 *  J5-GLOBAL-HOOKS — fire on every request, regardless of which archive
 *  template runs (Astra's default vs our custom astra-child/woocommerce/
 *  archive-product.php). Without these, /lights/ and other categories that
 *  route through Astra never get our body class or hero band.
 * ============================================================================ */

if ( ! has_filter( 'body_class', 'j5_archive_global_body_class' ) ) {
    function j5_archive_global_body_class( $classes ) {
        if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() || is_post_type_archive( 'product' ) ) ) {
            $classes[] = 'j5-shop-template';
        }
        return $classes;
    }
    add_filter( 'body_class', 'j5_archive_global_body_class' );
}

if ( ! has_action( 'woocommerce_before_main_content', 'j5_archive_global_render_hero' ) ) {
    function j5_archive_global_render_hero() {
        if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
            return;
        }
        if ( function_exists( 'j5_has_full_landing' ) ) {
            $q = get_queried_object();
            if ( ! empty( $q->slug ) && j5_has_full_landing( $q->slug ) ) {
                return;
            }
        }
        $count = 0;
        $q = get_queried_object();
        if ( ! empty( $q->count ) ) {
            $count = (int) $q->count;
        }
        if ( function_exists( 'j5_archive_render_hero' ) ) {
            j5_archive_render_hero( $count );
        }
        if ( function_exists( 'j5_archive_render_brand_strip' ) ) {
            j5_archive_render_brand_strip();
        }
    }
    add_action( 'woocommerce_before_main_content', 'j5_archive_global_render_hero', 5 );
}

/* ============================================================================
 *  J5-CATEGORY-ROUTING — Force product category and tag pages to render
 *  through our custom archive-product.php template instead of Astra's default.
 *  Without this, /lights/ etc. route through Astra's archive.php which
 *  produces a light-themed product list that doesn't match the dark j5-shop
 *  chrome we apply.
 *
 *  The /shop/ page already uses our template via WC's normal template
 *  hierarchy. This filter extends that behavior to categories and tags.
 *
 *  Categories with a configured landing page (armor, m-a-r-c-h) bypass
 *  this filter so they continue to use the landing template via the
 *  woocommerce_locate_template hook in j5-landing-bootstrap.php.
 * ============================================================================ */

if ( ! has_filter( 'template_include', 'j5_archive_force_template' ) ) {
    function j5_archive_force_template( $template ) {
        if ( ! function_exists( 'is_product_category' ) ) {
            return $template;
        }
        if ( ! is_product_category() && ! is_product_tag() ) {
            return $template;
        }
        // Skip if this slug has a full landing page configured.
        if ( function_exists( 'j5_has_full_landing' ) ) {
            $q = get_queried_object();
            if ( ! empty( $q->slug ) && j5_has_full_landing( $q->slug ) ) {
                return $template;
            }
        }
        $custom = get_stylesheet_directory() . '/woocommerce/archive-product.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
        return $template;
    }
    add_filter( 'template_include', 'j5_archive_force_template', 99 );
}




/* ============================================================================
 *  HERO
 * ============================================================================ */

/**
 * Render the per-category archive hero band.
 *
 * Echoes nothing if no config. Otherwise echoes the full hero markup.
 *
 * @param int $product_count Total products in the current archive (used to
 *                           auto-fill side stat 'num' if config left it blank)
 */
function j5_archive_render_hero( $product_count = 0 ) {
	$config = j5_get_current_archive_config();
	if ( empty( $config['hero'] ) ) {
		return;
	}

	$hero = $config['hero'];

	// Breadcrumb
	$crumbs = isset( $hero['breadcrumb'] ) && is_array( $hero['breadcrumb'] ) ? $hero['breadcrumb'] : array();

	// Eyebrow color: gold | red | blue
	$eyebrow_color = isset( $hero['eyebrow_color'] ) ? $hero['eyebrow_color'] : 'gold';
	$eyebrow_class = 'j5-archive-eyebrow';
	if ( in_array( $eyebrow_color, array( 'red', 'blue' ), true ) ) {
		$eyebrow_class .= ' ' . esc_attr( $eyebrow_color );
	}

	// Title accent color
	$accent_color = isset( $hero['title_accent_color'] ) ? $hero['title_accent_color'] : 'gold';
	$accent_class = 'j5-archive-title-accent';
	if ( $accent_color === 'red' ) {
		$accent_class .= ' red';
	}

	?>
	<section class="j5-archive-hero">
		<div class="j5-archive-hero-inner">
			<div class="j5-archive-hero-main">

				<?php if ( ! empty( $crumbs ) ) : ?>
					<nav class="j5-archive-breadcrumb" aria-label="Breadcrumb">
						<?php
						$last = count( $crumbs ) - 1;
						foreach ( $crumbs as $i => $crumb ) :
							$label = isset( $crumb['label'] ) ? $crumb['label'] : '';
							$url   = isset( $crumb['url'] ) ? $crumb['url'] : '';
							if ( $i > 0 ) echo '<span class="sep">/</span>';
							if ( $i === $last || empty( $url ) ) {
								echo '<span class="current">' . esc_html( $label ) . '</span>';
							} else {
								echo '<a href="' . esc_url( site_url( $url ) ) . '">' . esc_html( $label ) . '</a>';
							}
						endforeach;
						?>
					</nav>
				<?php endif; ?>

				<?php if ( ! empty( $hero['eyebrow'] ) ) : ?>
					<div class="<?php echo esc_attr( $eyebrow_class ); ?>"><?php echo esc_html( $hero['eyebrow'] ); ?></div>
				<?php endif; ?>

				<?php if ( ! empty( $hero['title_main'] ) || ! empty( $hero['title_accent'] ) ) : ?>
					<h1 class="j5-archive-title">
						<?php
						if ( ! empty( $hero['title_main'] ) ) {
							echo esc_html( $hero['title_main'] );
						}
						if ( ! empty( $hero['title_accent'] ) ) {
							if ( ! empty( $hero['title_main'] ) ) echo ' ';
							echo '<span class="' . esc_attr( $accent_class ) . '">' . esc_html( $hero['title_accent'] ) . '</span>';
						}
						?>
					</h1>
				<?php endif; ?>

				<?php if ( ! empty( $hero['body'] ) ) : ?>
					<p class="j5-archive-desc"><?php echo wp_kses( $hero['body'], array( 'strong' => array(), 'em' => array() ) ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $hero['meta_strip'] ) && is_array( $hero['meta_strip'] ) ) : ?>
					<div class="j5-archive-meta-strip">
						<?php foreach ( $hero['meta_strip'] as $meta ) :
							if ( empty( $meta['label'] ) || empty( $meta['value'] ) ) continue;
						?>
							<span><strong><?php echo esc_html( $meta['label'] ); ?>:</strong> <?php echo esc_html( $meta['value'] ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			</div>

			<?php
			// Side: stat callout OR MARCH letter marker
			if ( ! empty( $hero['side']['type'] ) ) :
				$side = $hero['side'];
				if ( $side['type'] === 'march' ) :
					$letter = isset( $side['letter'] ) ? $side['letter'] : 'M';
					$word   = isset( $side['word'] )   ? $side['word']   : '';
					$sub    = isset( $side['sub'] )    ? $side['sub']    : '';
				?>
					<div class="j5-archive-side">
						<div class="j5-archive-march-marker">
							<span class="j5-archive-march-letter" data-letter="<?php echo esc_attr( strtoupper( $letter ) ); ?>"><?php echo esc_html( strtoupper( $letter ) ); ?></span>
							<div class="j5-archive-march-text">
								<?php if ( $word !== '' ) : ?><strong><?php echo esc_html( $word ); ?></strong><?php endif; ?>
								<?php if ( $sub !== '' ) : ?><?php echo esc_html( $sub ); ?><?php endif; ?>
							</div>
						</div>
					</div>
				<?php
				else : // 'stat'
					$num = isset( $side['num'] ) ? trim( (string) $side['num'] ) : '';
					if ( $num === '' && $product_count > 0 ) {
						$num = (string) $product_count;
					}
					if ( $num === '' ) $num = '—';
				?>
					<div class="j5-archive-side">
						<?php if ( ! empty( $side['label'] ) ) : ?>
							<div class="j5-archive-side-label"><?php echo esc_html( $side['label'] ); ?></div>
						<?php endif; ?>
						<div class="j5-archive-side-num"><?php echo esc_html( $num ); ?></div>
						<?php if ( ! empty( $side['sub'] ) ) : ?>
							<div class="j5-archive-side-sub"><?php echo esc_html( $side['sub'] ); ?></div>
						<?php endif; ?>
					</div>
				<?php endif;
			endif; ?>

		</div>
	</section>
	<?php
}


/* ============================================================================
 *  BRAND STRIP
 * ============================================================================ */

/**
 * Render the brand list strip below the hero (if configured).
 */
function j5_archive_render_brand_strip() {
	$config = j5_get_current_archive_config();
	if ( empty( $config['brand_strip']['brands'] ) ) {
		return;
	}
	$brands = $config['brand_strip']['brands'];
	$label  = isset( $config['brand_strip']['label'] ) ? $config['brand_strip']['label'] : 'Trusted Lines';
	?>
	<div class="j5-archive-brand-strip">
		<div class="j5-archive-brand-strip-inner">
			<div class="j5-archive-brand-strip-label"><?php echo esc_html( $label ); ?></div>
			<div class="j5-archive-brand-strip-items">
				<?php foreach ( $brands as $brand ) : ?>
					<span class="j5-archive-brand-item"><?php echo esc_html( $brand ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}


/* ============================================================================
 *  SUBCATEGORY CHIP STRIP
 * ============================================================================ */

/**
 * Render the quick-filter chip strip above the toolbar (if configured).
 *
 * Chips link to ?subcat= URL params, allowing one-click jump filtering.
 * Production wiring of these to the query builder is left as a follow-up;
 * for now they're presentational links.
 */
function j5_archive_render_subcat_strip() {
	$config = j5_get_current_archive_config();
	if ( empty( $config['subcategory_strip']['chips'] ) ) {
		return;
	}
	$chips = $config['subcategory_strip']['chips'];
	$label = isset( $config['subcategory_strip']['label'] ) ? $config['subcategory_strip']['label'] : 'Quick Filter';
	$current = isset( $_GET['subcat'] ) ? sanitize_key( $_GET['subcat'] ) : '';
	?>
	<div class="j5-archive-subcat-strip">
		<span class="j5-archive-subcat-strip-label"><?php echo esc_html( $label ); ?></span>
		<?php foreach ( $chips as $chip ) :
			$slug    = isset( $chip['slug'] ) ? sanitize_key( $chip['slug'] ) : '';
			$is_active = ( $slug === '' && $current === '' ) || ( $slug !== '' && $slug === $current );
			$base    = strtok( $_SERVER['REQUEST_URI'], '?' );
			$args    = $_GET;
			if ( $slug === '' ) unset( $args['subcat'] ); else $args['subcat'] = $slug;
			$href    = $base . ( empty( $args ) ? '' : '?' . http_build_query( $args ) );
		?>
			<a href="<?php echo esc_url( $href ); ?>" class="j5-archive-subcat-chip<?php echo $is_active ? ' active' : ''; ?>">
				<?php echo esc_html( $chip['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
}


/* ============================================================================
 *  BRAND-GROUPED LAYOUT
 * ============================================================================ */

/**
 * @return bool true if the current category should render with brand-grouped
 *              layout instead of a flat product grid.
 */
function j5_archive_should_use_brand_grouped() {
	$config = j5_get_current_archive_config();
	return ! empty( $config['layout'] ) && $config['layout'] === 'brand-grouped';
}

/**
 * Render brand-grouped product layout.
 *
 * Buckets products into sections by their primary brand attribute
 * (pa_product-brands). Each brand section gets its own header and grid,
 * showing up to N products per brand with a "View all M →" link to the
 * full filtered shop URL.
 *
 * @param array $products  Array of WC_Product objects (already filtered/queried)
 * @param int   $per_brand Max products to show per brand (default 3)
 */
function j5_archive_render_brand_grouped( $products, $per_brand = 3 ) {
	if ( ! is_array( $products ) || empty( $products ) ) {
		return;
	}

	// Bucket by brand
	$buckets = array();
	$unbranded = array();

	foreach ( $products as $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) continue;
		$pid    = $product->get_id();
		$brands = wp_get_post_terms( $pid, 'pa_product-brands' );
		if ( is_wp_error( $brands ) || empty( $brands ) ) {
			$unbranded[] = $product;
			continue;
		}
		$brand = $brands[0];
		if ( ! isset( $buckets[ $brand->slug ] ) ) {
			$buckets[ $brand->slug ] = array(
				'name'     => $brand->name,
				'slug'     => $brand->slug,
				'products' => array(),
			);
		}
		$buckets[ $brand->slug ]['products'][] = $product;
	}

	// Render each brand bucket
	foreach ( $buckets as $bucket ) :
		$total = count( $bucket['products'] );
		$show  = array_slice( $bucket['products'], 0, $per_brand );
		$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : site_url( '/shop/' );
		$brand_url = add_query_arg( 'brand', $bucket['slug'], $shop_url );
	?>
		<section class="j5-archive-brand-group">
			<div class="j5-archive-brand-group-header">
				<span class="j5-archive-brand-group-name"><?php echo esc_html( $bucket['name'] ); ?></span>
				<span class="j5-archive-brand-group-count"><?php echo esc_html( $total ); ?> products in stock</span>
				<?php if ( $total > $per_brand ) : ?>
					<a href="<?php echo esc_url( $brand_url ); ?>" class="j5-archive-brand-group-link">View all <?php echo esc_html( $total ); ?> →</a>
				<?php endif; ?>
			</div>
			<div class="j5-prod-grid">
				<?php foreach ( $show as $product ) : ?>
					<?php if ( function_exists( 'j5_render_shop_product_card' ) ) : ?>
						<?php j5_render_shop_product_card( $product ); ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach;

	// Unbranded products (if any) get a final catch-all section
	if ( ! empty( $unbranded ) ) :
	?>
		<section class="j5-archive-brand-group">
			<div class="j5-archive-brand-group-header">
				<span class="j5-archive-brand-group-name">Other</span>
				<span class="j5-archive-brand-group-count"><?php echo esc_html( count( $unbranded ) ); ?> products</span>
			</div>
			<div class="j5-prod-grid">
				<?php foreach ( $unbranded as $product ) : ?>
					<?php if ( function_exists( 'j5_render_shop_product_card' ) ) : ?>
						<?php j5_render_shop_product_card( $product ); ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php
	endif;
}
