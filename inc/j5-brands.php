<?php
/**
 * J5 Brands
 *
 * Brand pages built on WooCommerce's built-in Brands (Products -> Brands,
 * taxonomy `product_brand`, part of WooCommerce core since 9.6). Each brand
 * gets a page at /brand/<slug>/ (base set in Settings -> Permalinks) that
 * uses the J5 archive template and the same editable layout as rich
 * category pages (inc/j5-rich-category.php): edit at Products -> Brands ->
 * (brand) -> "Brand Page".
 *
 * Also provides:
 *   - Brand logo in the hero (the brand's Thumbnail field in wp-admin).
 *   - [j5_brands] shortcode: a logo grid of brands for a /brands/ page.
 *   - j5_brands_sync_from_attribute(): one-time WP-CLI helper that creates
 *     Brands from the existing "Product Brands" attribute (pa_product-brands)
 *     and assigns each product to its brand.
 *   - j5_brands_assign_by_title(): fills in missing brands from product titles.
 *   - j5_brands_seed_all(): loads starter content (inc/j5-brand-seeds.php).
 *   - j5_brands_index_page(): builds the /brands/ page from [j5_brands].
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5_BRAND_TAX = 'product_brand';

require_once __DIR__ . '/j5-brand-seeds.php';

/* =========================================================================
 * 1. TAXONOMY — fallback only. WooCommerce 9.6+ registers product_brand
 *    itself; this registers a compatible one only on older installs.
 * ========================================================================= */

add_action( 'init', function () {
	if ( taxonomy_exists( J5_BRAND_TAX ) ) {
		return;
	}
	register_taxonomy( J5_BRAND_TAX, array( 'product' ), array(
		'hierarchical'      => true,
		'label'             => 'Brands',
		'labels'            => array( 'name' => 'Brands', 'singular_name' => 'Brand', 'menu_name' => 'Brands', 'add_new_item' => 'Add new brand', 'edit_item' => 'Edit brand' ),
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'brand', 'with_front' => false, 'hierarchical' => false ),
		'capabilities'      => array(
			'manage_terms' => 'manage_product_terms',
			'edit_terms'   => 'edit_product_terms',
			'delete_terms' => 'delete_product_terms',
			'assign_terms' => 'assign_product_terms',
		),
	) );
}, 99 );

/** True on a single brand's archive. */
function j5_is_brand_archive() {
	return taxonomy_exists( J5_BRAND_TAX ) && is_tax( J5_BRAND_TAX );
}

/* =========================================================================
 * 2. FRONT END — brand archives use the J5 archive template
 * ========================================================================= */

add_filter( 'template_include', function ( $template ) {
	if ( ! j5_is_brand_archive() ) {
		return $template;
	}
	$custom = get_stylesheet_directory() . '/woocommerce/archive-product.php';
	return file_exists( $custom ) ? $custom : $template;
}, 99 );

add_filter( 'body_class', function ( $classes ) {
	if ( j5_is_brand_archive() ) {
		$classes[] = 'j5-shop-template';
		$classes[] = 'j5-brand-page';
	}
	return $classes;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! j5_is_brand_archive() ) {
		return;
	}
	$abs = get_stylesheet_directory() . '/assets/css/j5-archive.css';
	if ( file_exists( $abs ) ) {
		wp_enqueue_style( 'j5-archive', get_stylesheet_directory_uri() . '/assets/css/j5-archive.css', array(), (string) filemtime( $abs ) );
	}
}, 26 );

/** Scope the archive's product query to the current brand. */
add_filter( 'j5_product_query_args', function ( $args ) {
	if ( ! j5_is_brand_archive() || ! empty( $_GET['all'] ) ) {
		return $args;
	}
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return $args;
	}
	$args['tax_query']   = isset( $args['tax_query'] ) ? $args['tax_query'] : array();
	$args['tax_query'][] = array(
		'taxonomy' => J5_BRAND_TAX,
		'field'    => 'term_id',
		'terms'    => array( (int) $term->term_id ),
	);
	return $args;
} );

/** Breadcrumb on brand pages: Home / Brands / <brand>. */
add_filter( 'j5_archive_crumb_parent', function ( $crumb ) {
	if ( ! j5_is_brand_archive() ) {
		return $crumb;
	}
	$page = get_page_by_path( 'brands' );
	return array(
		'label' => 'Brands',
		'url'   => $page ? get_permalink( $page ) : $crumb['url'],
	);
} );

/** Brand logo above the eyebrow/title when the brand has a Thumbnail. */
add_action( 'j5_archive_hero_before_title', function () {
	if ( ! j5_is_brand_archive() ) {
		return;
	}
	$logo = j5_brand_logo_url( get_queried_object_id(), 'medium' );
	if ( $logo ) {
		printf( '<div class="j5rc-brand-logo"><img src="%s" alt="%s" /></div>', esc_url( $logo ), esc_attr( single_term_title( '', false ) ) );
	}
}, 5 );

/** URL of a brand's logo (WooCommerce stores it as term meta thumbnail_id). */
function j5_brand_logo_url( $term_id, $size = 'medium' ) {
	$att = (int) get_term_meta( (int) $term_id, 'thumbnail_id', true );
	return $att ? wp_get_attachment_image_url( $att, $size ) : '';
}

/* =========================================================================
 * 3. [j5_brands] — brand grid for a /brands/ page
 *    [j5_brands]                      all brands with products
 *    [j5_brands only="hesco,highcom"] just these, in this order
 *    [j5_brands exclude="a,b"]        all except these (default: test)
 * ========================================================================= */

add_shortcode( 'j5_brands', function ( $atts ) {
	if ( ! taxonomy_exists( J5_BRAND_TAX ) ) {
		return '';
	}
	$atts = shortcode_atts( array( 'only' => '', 'exclude' => 'test' ), $atts, 'j5_brands' );
	$args = array( 'taxonomy' => J5_BRAND_TAX, 'hide_empty' => true, 'orderby' => 'name' );
	if ( '' !== trim( $atts['only'] ) ) {
		$args['slug']    = array_map( 'sanitize_title', array_map( 'trim', explode( ',', $atts['only'] ) ) );
		$args['orderby'] = 'slug__in';
	}
	$terms = get_terms( $args );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}
	$skip  = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) $atts['exclude'] ) ) ) );
	$terms = array_values( array_filter( $terms, function ( $t ) use ( $skip ) {
		return ! in_array( $t->slug, $skip, true );
	} ) );
	if ( empty( $terms ) ) {
		return '';
	}
	$abs = get_stylesheet_directory() . '/assets/css/j5-rich-category.css';
	if ( file_exists( $abs ) ) {
		wp_enqueue_style( 'j5-rich-category', get_stylesheet_directory_uri() . '/assets/css/j5-rich-category.css', array(), (string) filemtime( $abs ) );
	}
	ob_start();
	?>
	<div class="j5rc-brand-grid">
		<?php foreach ( $terms as $t ) :
			$logo = j5_brand_logo_url( $t->term_id, 'medium' );
		?>
			<a class="j5rc-brand-card" href="<?php echo esc_url( get_term_link( $t ) ); ?>">
				<span class="j5rc-brand-card-logo">
					<?php if ( $logo ) : ?>
						<img src="<?php echo esc_url( $logo ); ?>" alt="" loading="lazy" />
					<?php else : ?>
						<span><?php echo esc_html( wp_specialchars_decode( $t->name ) ); ?></span>
					<?php endif; ?>
				</span>
				<span class="j5rc-brand-card-name"><?php echo esc_html( wp_specialchars_decode( $t->name ) ); ?></span>
				<span class="j5rc-brand-card-count"><?php echo (int) $t->count; ?> <?php echo 1 === (int) $t->count ? 'product' : 'products'; ?> &rarr;</span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
} );

/* =========================================================================
 * 4. ONE-TIME SYNC — Brands from the "Product Brands" attribute
 *
 *    Preview (changes nothing):
 *        wp eval 'j5_brands_sync_from_attribute();'
 *    Apply:
 *        wp eval 'j5_brands_sync_from_attribute( true );'
 *
 *    For each pa_product-brands term, creates a Brand with the same name and
 *    slug (or reuses one that exists) and adds every product carrying that
 *    attribute to it. Existing brand assignments are kept. Safe to re-run.
 * ========================================================================= */

function j5_brands_sync_from_attribute( $apply = false, $attribute = 'pa_product-brands' ) {
	$out = function ( $line ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $line );
		} else {
			echo $line . PHP_EOL;
		}
	};
	if ( ! taxonomy_exists( $attribute ) ) {
		$out( "Attribute taxonomy '$attribute' not found." );
		return;
	}
	if ( ! taxonomy_exists( J5_BRAND_TAX ) ) {
		$out( 'Brands taxonomy not registered.' );
		return;
	}
	$terms = get_terms( array( 'taxonomy' => $attribute, 'hide_empty' => false ) );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		$out( 'No attribute terms found.' );
		return;
	}
	$out( $apply ? 'APPLYING' : 'PREVIEW (run with true to apply)' );
	$total = 0;
	foreach ( $terms as $attr_term ) {
		$product_ids = get_posts( array(
			'post_type'      => array( 'product', 'product_variation' ),
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'tax_query'      => array( array( 'taxonomy' => $attribute, 'field' => 'term_id', 'terms' => $attr_term->term_id ) ),
		) );
		// Brands belong on the parent product, not variations.
		$parents = array();
		foreach ( $product_ids as $pid ) {
			$parent    = (int) wp_get_post_parent_id( $pid );
			$parents[] = $parent ? $parent : (int) $pid;
		}
		$parents = array_values( array_unique( $parents ) );

		$brand = get_term_by( 'slug', $attr_term->slug, J5_BRAND_TAX );
		$out( sprintf( '%-40s %3d products  %s', $attr_term->name, count( $parents ), $brand ? '(brand exists)' : '(new brand)' ) );
		$total += count( $parents );

		if ( ! $apply ) {
			continue;
		}
		if ( ! $brand ) {
			$made = wp_insert_term( $attr_term->name, J5_BRAND_TAX, array( 'slug' => $attr_term->slug ) );
			if ( is_wp_error( $made ) ) {
				$out( '  ! ' . $made->get_error_message() );
				continue;
			}
			$brand_id = (int) $made['term_id'];
		} else {
			$brand_id = (int) $brand->term_id;
		}
		foreach ( $parents as $pid ) {
			wp_set_object_terms( $pid, array( $brand_id ), J5_BRAND_TAX, true );
		}
	}
	$out( "Done. $total product assignments " . ( $apply ? 'made.' : 'would be made.' ) );
}


/** Line output for the WP-CLI helpers below. */
function j5_brands_out( $line ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( $line );
	} else {
		echo $line . PHP_EOL;
	}
}

/* =========================================================================
 * 5. ASSIGN BRANDS FROM PRODUCT TITLES
 *
 *    Preview (changes nothing):
 *        wp eval 'j5_brands_assign_by_title();'
 *    Apply:
 *        wp eval 'j5_brands_assign_by_title( true );'
 *
 *    Only touches products that have NO brand yet. A title must match
 *    exactly one rule below; titles matching two rules are listed and
 *    skipped. Brands that don't exist yet are created on apply. Products
 *    left without a brand are listed at the end so gaps are visible.
 *    Brand is catalog data: the dealer portal should own it going forward,
 *    this backfills what WooCommerce is missing today.
 * ========================================================================= */

function j5_brand_title_rules() {
	return apply_filters( 'j5_brand_title_rules', array(
		// slug                            => array( name, title regex )
		'hesco-armor'                   => array( 'Hesco Armor', '/\bhesco\b/i' ),
		'highcom-armor'                 => array( 'HighCom Armor', '/\bhighcom\b/i' ),
		'united-shield-international'   => array( 'United Shield International', '/\b(usi|united shield)\b/i' ),
		'surefire'                      => array( 'Surefire', '/\bsurefire\b/i' ),
		'eotech'                        => array( 'EOTECH', '/\beotech\b/i' ),
		'leupold'                       => array( 'Leupold', '/\b(leupold|deltapoint)\b/i' ),
		'ops-core'                      => array( 'Ops-Core', '/\bops-?core\b/i' ),
		'byrna'                         => array( 'Byrna', '/\bbyrna\b/i' ),
		'pavashot'                      => array( 'PavaShot', '/\bpavashot\b/i' ),
		'north-american-rescue'         => array( 'North American Rescue', '/\b(nar|north american rescue|hyfin|combat application tourniquet)\b/i' ),
		'sam-medical'                   => array( 'SAM Medical', '/\b(sam|chitosam)\b/i' ),
		'celox'                         => array( 'Celox', '/\bcelox\b/i' ),
		'quikclot'                      => array( 'QuikClot', '/\bquikclot\b/i' ),
		'tacmed-solutions'              => array( 'TacMed Solutions', '/\b(tacmed|sof tourniquet|olaes)\b/i' ),
		'tekto'                         => array( 'Tekto', '/\btekto\b/i' ),
		'tasmanian-tiger'               => array( 'Tasmanian Tiger', '/\b(tasmanian tiger|tt)\b/i' ),
		'magpul'                        => array( 'Magpul', '/\bmagpul\b/i' ),
		'ess'                           => array( 'ESS', '/\b(ess|crossbow)\b/i' ),
		'scalarworks'                   => array( 'Scalarworks', '/\bscalarworks\b/i' ),
		// Brands that may not exist yet; created on apply if a title matches.
		// Existing slugs (london-bridge-trading, 5-11, zoll-medical) are reused.
		'london-bridge-trading'         => array( 'London Bridge Trading', '/\b(lbt|london bridge trading)\b/i' ),
		'5-11'                          => array( '5.11 Tactical', '/\b5\.11\b/' ),
		'zoll-medical'                  => array( 'Zoll Medical', '/\bzoll\b/i' ),
		'agilite'                       => array( 'Agilite', '/\bagilite\b/i' ),
		'condor'                        => array( 'Condor', '/\bcondor\b/i' ),
		'benchmade'                     => array( 'Benchmade', '/\bbenchmade\b/i' ),
		'blade-tech'                    => array( 'Blade-Tech', '/\bblade-?tech\b/i' ),
		'dji'                           => array( 'DJI', '/\bdji\b/i' ),
		'mckesson'                      => array( 'McKesson', '/\bmckesson\b/i' ),
		'spyderco'                      => array( 'Spyderco', '/\bspyderco\b/i' ),
		'skb'                           => array( 'SKB', '/\bskb\b/i' ),
		'streamlight'                   => array( 'Streamlight', '/\bstreamlight\b/i' ),
		'esstac'                        => array( 'Esstac', '/\besstac\b/i' ),
		'foxfury'                       => array( 'FoxFury', '/\bfoxfury\b/i' ),
		'gerber'                        => array( 'Gerber', '/\bgerber\b/i' ),
		'sog'                           => array( 'SOG', '/\bsog\b/i' ),
		'sordin'                        => array( 'Sordin', '/\bsordin\b/i' ),
		'water-jel'                     => array( 'Water-Jel', '/\bwater-jel\b/i' ),
		'breachpen'                     => array( 'Breachpen', '/\bbreachpen\b/i' ),
		'xshear'                        => array( 'XShear', '/\bxshears?\b/i' ),
		'3m'                            => array( '3M', '/\b3m\b/i' ),
	) );
}

function j5_brands_assign_by_title( $apply = false ) {
	if ( ! taxonomy_exists( J5_BRAND_TAX ) ) {
		j5_brands_out( 'Brands taxonomy not registered.' );
		return;
	}
	$rules = j5_brand_title_rules();
	$ids   = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	j5_brands_out( $apply ? 'APPLYING' : 'PREVIEW (run with true to apply)' );

	$assign = array();
	$ambig  = array();
	$none   = array();
	$had    = 0;
	foreach ( $ids as $pid ) {
		$current = wp_get_object_terms( $pid, J5_BRAND_TAX, array( 'fields' => 'ids' ) );
		if ( ! empty( $current ) && ! is_wp_error( $current ) ) {
			$had++;
			continue;
		}
		$title = wp_specialchars_decode( get_the_title( $pid ) );
		$hits  = array();
		foreach ( $rules as $slug => $rule ) {
			if ( preg_match( $rule[1], $title ) ) {
				$hits[] = $slug;
			}
		}
		if ( 1 === count( $hits ) ) {
			$assign[ $pid ] = array( $hits[0], $title );
		} elseif ( count( $hits ) > 1 ) {
			$ambig[ $pid ] = array( implode( ' + ', $hits ), $title );
		} else {
			$none[ $pid ] = $title;
		}
	}

	j5_brands_out( sprintf( '%d products scanned, %d already have a brand.', count( $ids ), $had ) );
	j5_brands_out( '' );
	j5_brands_out( sprintf( 'ASSIGN (%d)', count( $assign ) ) );
	$made = array();
	foreach ( $assign as $pid => $row ) {
		list( $slug, $title ) = $row;
		$term = get_term_by( 'slug', $slug, J5_BRAND_TAX );
		$flag = $term ? '' : '  (new brand)';
		j5_brands_out( sprintf( '  %6d  %-28s %s%s', $pid, $slug, $title, $flag ) );
		if ( ! $apply ) {
			continue;
		}
		if ( ! $term ) {
			if ( ! isset( $made[ $slug ] ) ) {
				$res = wp_insert_term( $rules[ $slug ][0], J5_BRAND_TAX, array( 'slug' => $slug ) );
				if ( is_wp_error( $res ) ) {
					j5_brands_out( '          ! ' . $res->get_error_message() );
					continue;
				}
				$made[ $slug ] = (int) $res['term_id'];
			}
			$term_id = $made[ $slug ];
		} else {
			$term_id = (int) $term->term_id;
		}
		wp_set_object_terms( $pid, array( $term_id ), J5_BRAND_TAX, false );
	}
	j5_brands_out( '' );
	j5_brands_out( sprintf( 'SKIPPED, matches two brands (%d)', count( $ambig ) ) );
	foreach ( $ambig as $pid => $row ) {
		j5_brands_out( sprintf( '  %6d  %-28s %s', $pid, $row[0], $row[1] ) );
	}
	j5_brands_out( '' );
	j5_brands_out( sprintf( 'STILL NO BRAND (%d)', count( $none ) ) );
	foreach ( $none as $pid => $title ) {
		j5_brands_out( sprintf( '  %6d  %s', $pid, $title ) );
	}
	j5_brands_out( '' );
	j5_brands_out( $apply ? sprintf( 'Done. %d products assigned.', count( $assign ) ) : sprintf( 'Done. %d products would be assigned.', count( $assign ) ) );
}

/* =========================================================================
 * 6. LOAD STARTER CONTENT FOR EVERY BRAND (inc/j5-brand-seeds.php)
 *
 *    Preview:                 wp eval 'j5_brands_seed_all();'
 *    Apply (skip filled-in):  wp eval 'j5_brands_seed_all( true );'
 *    Apply + overwrite some:  wp eval 'j5_brands_seed_all( true, array( "pavashot" ) );'
 *
 *    Brands that already have Brand Page content are skipped unless listed
 *    in the second argument; overwriting replaces any wp-admin edits.
 * ========================================================================= */

function j5_brands_seed_all( $apply = false, $force = array() ) {
	if ( ! taxonomy_exists( J5_BRAND_TAX ) || ! function_exists( 'j5rc_seed_category' ) ) {
		j5_brands_out( 'Brands taxonomy or rich category module not loaded.' );
		return;
	}
	$force = (array) $force;
	$seeds = apply_filters( 'j5rc_brand_seeds', array() );
	j5_brands_out( $apply ? 'APPLYING' : 'PREVIEW (run with true to apply)' );
	foreach ( $seeds as $slug => $seed ) {
		$term = get_term_by( 'slug', $slug, J5_BRAND_TAX );
		if ( ! $term ) {
			j5_brands_out( sprintf( '  %-30s brand not found, skipped', $slug ) );
			continue;
		}
		$has   = ! empty( get_term_meta( $term->term_id, J5RC_META, true ) );
		$force_this = in_array( $slug, $force, true );
		if ( $has && ! $force_this ) {
			j5_brands_out( sprintf( '  %-30s has content already, skipped', $slug ) );
			continue;
		}
		if ( ! $apply ) {
			j5_brands_out( sprintf( '  %-30s would load (%d products)%s', $slug, (int) $term->count, $has ? ', OVERWRITES existing' : '' ) );
			continue;
		}
		j5_brands_out( '  ' . j5rc_seed_category( $slug, $force_this, J5_BRAND_TAX ) );
	}
	$all = get_terms( array( 'taxonomy' => J5_BRAND_TAX, 'hide_empty' => false ) );
	if ( ! is_wp_error( $all ) ) {
		foreach ( $all as $t ) {
			if ( ! isset( $seeds[ $t->slug ] ) ) {
				j5_brands_out( sprintf( '  %-30s no starter content (%d products)', $t->slug, (int) $t->count ) );
			}
		}
	}
}

/* =========================================================================
 * 7. /brands/ INDEX PAGE
 *
 *    Preview:  wp eval 'j5_brands_index_page();'
 *    Apply:    wp eval 'j5_brands_index_page( true );'
 *
 *    Creates the "Brands" page (slug brands) or rewrites the existing one
 *    with an intro and the [j5_brands] logo grid. The old content is kept
 *    in post meta _j5_pre_brands_content. On an Elementor-built page the
 *    Elementor data is left in place but switched off, so WordPress renders
 *    the new content.
 * ========================================================================= */

function j5_brands_index_content() {
	return "<!-- wp:paragraph -->\n<p>Every manufacturer we carry, from HESCO and HighCom armor to SureFire weapon lights and North American Rescue medical gear. Pick a brand to see its full lineup.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[j5_brands]\n<!-- /wp:shortcode -->";
}

function j5_brands_index_page( $apply = false ) {
	j5_brands_out( $apply ? 'APPLYING' : 'PREVIEW (run with true to apply)' );
	$page = get_page_by_path( 'brands' );
	if ( $page ) {
		$elementor = 'builder' === get_post_meta( $page->ID, '_elementor_edit_mode', true );
		j5_brands_out( sprintf( '  Existing page %d "%s" (%s)%s, content %d chars', $page->ID, $page->post_title, $page->post_status, $elementor ? ', Elementor-built' : '', strlen( $page->post_content ) ) );
	} else {
		j5_brands_out( '  No page at /brands/; one will be created.' );
	}
	$others = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => array( 'publish', 'draft', 'private' ),
		'posts_per_page' => -1,
		'exclude'        => $page ? array( $page->ID ) : array(),
	) );
	foreach ( $others as $o ) {
		if ( false !== strpos( $o->post_name, 'brand' ) ) {
			j5_brands_out( sprintf( '  Other brand page: %d "%s" /%s/ (%s), 301 it to /brands/', $o->ID, $o->post_title, $o->post_name, $o->post_status ) );
		}
	}
	if ( ! $apply ) {
		return;
	}
	if ( $page ) {
		update_post_meta( $page->ID, '_j5_pre_brands_content', $page->post_content );
		delete_post_meta( $page->ID, '_elementor_edit_mode' );
		$id = wp_update_post( array(
			'ID'           => $page->ID,
			'post_content' => j5_brands_index_content(),
			'post_status'  => 'publish',
		), true );
	} else {
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_name'    => 'brands',
			'post_title'   => 'Brands',
			'post_content' => j5_brands_index_content(),
			'post_status'  => 'publish',
		), true );
	}
	j5_brands_out( is_wp_error( $id ) ? '  ! ' . $id->get_error_message() : '  Brands page ready: ' . get_permalink( $id ) );
}

/* =========================================================================
 * 8. FOOTER "BRANDS" COLUMN (called from footer.php)
 *
 *    Lists featured brands, then "All brands" when the /brands/ page exists.
 *    Featured list is filterable (j5_footer_brand_slugs); brands that don't
 *    exist or have no products are skipped, and the column tops up from the
 *    brands with the most products.
 * ========================================================================= */

function j5_render_footer_brands() {
	if ( ! taxonomy_exists( J5_BRAND_TAX ) ) {
		return;
	}
	$max   = 6;
	$slugs = apply_filters( 'j5_footer_brand_slugs', array( 'hesco-armor', 'highcom-armor', 'united-shield-international', 'surefire', 'eotech', 'north-american-rescue' ) );
	$skip  = array( 'test', 'j5-rescue-supply' );
	$picked = array();
	foreach ( $slugs as $slug ) {
		$t = get_term_by( 'slug', $slug, J5_BRAND_TAX );
		if ( $t && ! is_wp_error( $t ) && $t->count > 0 ) {
			$picked[ $t->term_id ] = $t;
		}
		if ( count( $picked ) >= $max ) {
			break;
		}
	}
	if ( count( $picked ) < $max ) {
		$top = get_terms( array( 'taxonomy' => J5_BRAND_TAX, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 20 ) );
		if ( ! is_wp_error( $top ) ) {
			foreach ( $top as $t ) {
				if ( count( $picked ) >= $max ) {
					break;
				}
				if ( ! isset( $picked[ $t->term_id ] ) && ! in_array( $t->slug, $skip, true ) ) {
					$picked[ $t->term_id ] = $t;
				}
			}
		}
	}
	if ( empty( $picked ) ) {
		return;
	}
	$page = get_page_by_path( 'brands' );
	?>
	<div class="j5-footer-col j5-footer-col--brands">
		<h2 class="j5-footer-col__title">Brands</h2>
		<ul class="j5-footer-links">
			<?php foreach ( $picked as $t ) :
				$link = get_term_link( $t );
				if ( is_wp_error( $link ) ) {
					continue;
				}
			?>
				<li><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( wp_specialchars_decode( $t->name ) ); ?></a></li>
			<?php endforeach; ?>
			<?php if ( $page && 'publish' === $page->post_status ) : ?>
				<li class="j5-footer-links__all"><a href="<?php echo esc_url( get_permalink( $page ) ); ?>">All brands &rarr;</a></li>
			<?php endif; ?>
		</ul>
	</div>
	<?php
}
