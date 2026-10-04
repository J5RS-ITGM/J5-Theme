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
 *   - Starter content for HESCO, United Shield International, HighCom and
 *     Pavashot brand pages.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5_BRAND_TAX = 'product_brand';

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
 * ========================================================================= */

add_shortcode( 'j5_brands', function ( $atts ) {
	if ( ! taxonomy_exists( J5_BRAND_TAX ) ) {
		return '';
	}
	$atts = shortcode_atts( array( 'only' => '' ), $atts, 'j5_brands' );
	$args = array( 'taxonomy' => J5_BRAND_TAX, 'hide_empty' => true, 'orderby' => 'name' );
	if ( '' !== trim( $atts['only'] ) ) {
		$args['slug']    = array_map( 'sanitize_title', array_map( 'trim', explode( ',', $atts['only'] ) ) );
		$args['orderby'] = 'slug__in';
	}
	$terms = get_terms( $args );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
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

/* =========================================================================
 * 5. STARTER CONTENT — load one brand at a time:
 *        wp eval 'echo j5rc_seed_category( "hesco", false, "product_brand" ) . PHP_EOL;'
 *    Brand slugs must match the brands in Products -> Brands.
 *    Product links point at existing product URLs; check them after loading.
 * ========================================================================= */

add_filter( 'j5rc_brand_seeds', function ( $seeds ) {
	$common_related = array( 'body-armor', 'shields', 'helmets', 'plate-carriers' );

	$seeds['hesco'] = array(
		'enabled'      => 1,
		'title'        => 'HESCO',
		'title_accent' => 'Armor',
		'eyebrow'      => 'Hard Armor Plates · Level IV & Special Threat',
		'chips'        => 'Level IV, Special Threat, Plate Sets, NIJ Certified',
		'_description' => 'HESCO hard armor plates for patrol, tactical, and active threat response. Level IV and Special Threat plate sets, including the <strong>L210, M210, U210, 4400, 4601 and 4800</strong> lines. New to plate ratings? Our <a href="/just-another-useful-guide-to-body-armor-hesco-plates/">guide to body armor and HESCO plates</a> explains the differences.',

		'tiles_label'  => 'Popular HESCO plates',
		'tiles_title'  => 'Shop by plate line',
		'tiles'        => array(
			array( 'url' => '/hesco-l210-special-threat-plate-set-2/', 'kicker' => '01 — Special Threat', 'title' => 'HESCO L210', 'text' => 'Special threat plate set and our most popular HESCO plate.', 'chips' => 'Special Threat, Plate Set', 'cta' => 'View the L210' ),
			array( 'url' => '/hesco-m210-special-threat-plate-set/', 'kicker' => '02 — Special Threat', 'title' => 'HESCO M210', 'text' => 'Special threat plate set in the 210 family.', 'chips' => 'Special Threat', 'cta' => 'View the M210' ),
			array( 'url' => '/hesco-4400-level-iv-plate-set/', 'kicker' => '03 — Level IV', 'title' => 'HESCO 4400', 'text' => 'Level IV plate set for armor-piercing rifle threats.', 'chips' => 'Level IV', 'cta' => 'View the 4400' ),
			array( 'url' => '/hesco-4800-level-iv-plate-set/', 'kicker' => '04 — Level IV', 'title' => 'HESCO 4800', 'text' => 'Level IV plate set in the 4800 line.', 'chips' => 'Level IV', 'cta' => 'View the 4800' ),
		),

		'cmp_label'    => 'Special Threat vs Level IV',
		'cmp_title'    => 'Which HESCO plate do you need?',
		'cmp_intro'    => 'HESCO builds plates for two kinds of threat. Match the plate to what you expect to face rather than defaulting to the heaviest option.',
		'cmp_col_a'    => 'Special Threat (L210, M210, U210)',
		'cmp_col_b'    => 'Level IV (4400, 4601, 4800)',
		'cmp_rows'     => array(
			array( 'label' => 'Built for', 'a' => 'Common patrol rifle threats, tested against specific rounds listed on each spec sheet', 'b' => 'Armor-piercing rifle rounds under the NIJ Level IV rating' ),
			array( 'label' => 'Weight', 'a' => 'Lighter. Easier to wear for a full shift', 'b' => 'Heavier. Plan for longer-wear comfort' ),
			array( 'label' => 'Best for', 'a' => 'Patrol officers and daily-wear rifle protection', 'b' => 'Tactical teams and high-threat callouts' ),
			array( 'label' => 'Check', 'a' => 'The threat list on the product page', 'b' => 'Plate size and cut for your carrier' ),
		),

		'faq_title'    => 'HESCO plate questions',
		'faqs'         => array(
			array( 'q' => 'What is the difference between HESCO Special Threat and Level IV plates?', 'a' => 'Special Threat plates are tested against specific rifle rounds and are usually lighter. Level IV plates are rated to stop armor-piercing rifle rounds and weigh more. Each product page lists the exact threats.' ),
			array( 'q' => 'Are HESCO plates NIJ certified?', 'a' => 'Many HESCO plates carry NIJ certification. Certification status is listed on each product page; check the model you are buying.' ),
			array( 'q' => 'Will HESCO plates fit my plate carrier?', 'a' => 'Match the plate size and cut to your carrier\'s plate pocket. Sizes and cuts are listed on each product page, and you can <a href="/contact-us/">contact us</a> if you are unsure.' ),
			array( 'q' => 'Do you sell HESCO plates to agencies?', 'a' => 'Yes. We accept agency purchase orders and quote multi-unit plate orders. <a href="/contact-us/">Contact us</a> for a quote.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'HESCO and body armor guides',
		'art_pinned'   => 'just-another-useful-guide-to-body-armor-hesco-plates',
		'art_tag'      => 'hesco',
		'art_count'    => 3,

		'agency_on'    => 1,
		'agency_title' => 'Outfitting a department?',
		'agency_text'  => 'Purchase orders accepted and quotes on multi-unit HESCO plate orders.',
		'agency_btn'   => 'Request a quote',
		'agency_url'   => '/contact-us/',

		'related_title' => 'Shop related',
		'related_slugs' => array( 'body-armor', 'plate-carriers', 'shields', 'helmets' ),
	);

	$seeds['united-shield-international'] = array(
		'enabled'      => 1,
		'title'        => 'United Shield',
		'title_accent' => 'International',
		'eyebrow'      => 'Ballistic Shields · Soft Armor · Carriers',
		'chips'        => 'Phantom EVO, Rifle Rated Shields, Training Shields, Soft Armor',
		'_description' => 'United Shield International (USI) ballistic shields, soft armor, and carriers. Handgun rated and rifle rated shields including the <strong>Phantom EVO</strong> and <strong>Lightweight Level III</strong>, matching training shields, and the <strong>Spec Ops Delta</strong>. Compare shield ratings on our <a href="/shields/">ballistic shields</a> page.',

		'tiles_label'  => 'Shop USI',
		'tiles_title'  => 'Shields and armor',
		'tiles'        => array(
			array( 'url' => '/usi-phantom-evo-ballistic-shield/', 'kicker' => '01 — Ballistic Shield', 'title' => 'Phantom EVO Shield', 'text' => 'USI\'s ballistic shield, with handle and viewport options.', 'chips' => 'Shield, Viewport Option', 'cta' => 'View the Phantom EVO' ),
			array( 'url' => '/usi-lightweight-level-iii-rrs-rifle-rated/', 'kicker' => '02 — Rifle Rated', 'title' => 'Lightweight Level III', 'text' => 'Rifle rated shield in multiple sizes, with or without viewport.', 'chips' => 'Level III, Rifle Rated', 'cta' => 'View the Level III' ),
			array( 'url' => '/usi-phantom-evo-training-shield/', 'kicker' => '03 — Training', 'title' => 'Phantom EVO Training Shield', 'text' => 'Non-rated training shield so teams can train without wearing out duty shields.', 'chips' => 'Training', 'cta' => 'View the trainer' ),
			array( 'url' => '/usi-spec-ops-delta-gen-ii/', 'kicker' => '04 — Armor', 'title' => 'Spec Ops Delta Gen II', 'text' => 'USI tactical armor system.', 'chips' => 'Armor', 'cta' => 'View the Delta' ),
		),

		'faq_title'    => 'United Shield International questions',
		'faqs'         => array(
			array( 'q' => 'What is the difference between USI handgun rated and rifle rated shields?', 'a' => 'Handgun rated (Level IIIA) shields are lighter and built for handgun threats. Rifle rated (Level III) shields stop common rifle rounds and weigh more. See our <a href="/shields/">ballistic shields</a> page for a side-by-side comparison.' ),
			array( 'q' => 'Do you carry USI training shields?', 'a' => 'Yes. The Phantom EVO training shield matches the size and feel of the duty shield, so teams can train without wearing out rated equipment.' ),
			array( 'q' => 'Do you sell USI products to agencies?', 'a' => 'Yes. We accept agency purchase orders and quote multi-unit orders. <a href="/contact-us/">Contact us</a> for a quote.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'United Shield articles',
		'art_pinned'   => 'united-shield-international-the-modern-officers-armor-carrier,ballistic-shields-the-complete-buyers-guide',
		'art_tag'      => 'united-shield',
		'art_count'    => 3,

		'agency_on'    => 1,
		'agency_title' => 'Outfitting a team?',
		'agency_text'  => 'Purchase orders accepted and quotes on multi-unit USI shield and armor orders.',
		'agency_btn'   => 'Request a quote',
		'agency_url'   => '/contact-us/',

		'related_title' => 'Shop related',
		'related_slugs' => array( 'shields', 'rifle-rated-shields', 'shield-accessories', 'body-armor' ),
	);

	$seeds['highcom'] = array(
		'enabled'      => 1,
		'title'        => 'HighCom',
		'title_accent' => 'Armor',
		'eyebrow'      => 'Ballistic Shields · Hard Armor Plates',
		'chips'        => 'Bellfire Shields, Titan III, Multi-Curve Plates, Rifle Rated',
		'_description' => 'HighCom ballistic shields and hard armor plates. The <strong>Bellfire</strong> shield family in handgun and rifle rated versions, the lightweight <strong>Titan III</strong> shield, and multi-curve plates such as the <strong>4SAS7</strong>. Compare shield ratings on our <a href="/shields/">ballistic shields</a> page.',

		'tiles_label'  => 'Shop HighCom',
		'tiles_title'  => 'Shields and plates',
		'tiles'        => array(
			array( 'url' => '/highcom-bellfire-b3-ballistic-shield-rifle-rated/', 'kicker' => '01 — Rifle Rated', 'title' => 'Bellfire B3', 'text' => 'Rifle rated Bellfire shield, flat or single curve, with viewport options.', 'chips' => 'Rifle Rated, Viewport Option', 'cta' => 'View the B3' ),
			array( 'url' => '/highcom-bellfire-b3a-ballistic-shield-handgun-rated/', 'kicker' => '02 — Handgun Rated', 'title' => 'Bellfire B3A', 'text' => 'Handgun rated version of the Bellfire, lighter for patrol carry.', 'chips' => 'Level IIIA, Patrol', 'cta' => 'View the B3A' ),
			array( 'url' => '/highcom-titan-iii-lightweight-ballistic-shield-rifle-rated/', 'kicker' => '03 — Lightweight', 'title' => 'Titan III', 'text' => 'Lightweight rifle rated shield.', 'chips' => 'Rifle Rated, Lightweight', 'cta' => 'View the Titan III' ),
			array( 'url' => '/highcom-4sas7-multi-curve-level-iv-plate-set/', 'kicker' => '04 — Plates', 'title' => '4SAS7 Plate Set', 'text' => 'Multi-curve Level IV hard armor plate set.', 'chips' => 'Level IV, Multi-Curve', 'cta' => 'View the 4SAS7' ),
		),

		'faq_title'    => 'HighCom questions',
		'faqs'         => array(
			array( 'q' => 'What is the difference between the HighCom Bellfire B3 and B3A?', 'a' => 'The B3 is rifle rated and the B3A is handgun rated. The B3A is lighter; the B3 stops common rifle threats. Both come in several sizes and shapes.' ),
			array( 'q' => 'Which HighCom shield is the lightest?', 'a' => 'Handgun rated shields and HighCom\'s lightweight lines (LTS and Titan) are the lightest. Exact weights for each size are on the product pages.' ),
			array( 'q' => 'Does HighCom make body armor plates?', 'a' => 'Yes. HighCom makes hard armor plates, including multi-curve Level IV plate sets like the 4SAS7.' ),
			array( 'q' => 'Do you sell HighCom to agencies?', 'a' => 'Yes. We accept agency purchase orders and quote multi-unit shield and plate orders. <a href="/contact-us/">Contact us</a> for a quote.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'Shield and armor guides',
		'art_pinned'   => 'ballistic-shields-the-complete-buyers-guide',
		'art_tag'      => 'highcom',
		'art_count'    => 3,

		'agency_on'    => 1,
		'agency_title' => 'Outfitting a team?',
		'agency_text'  => 'Purchase orders accepted and quotes on multi-unit HighCom shield and plate orders.',
		'agency_btn'   => 'Request a quote',
		'agency_url'   => '/contact-us/',

		'related_title' => 'Shop related',
		'related_slugs' => $common_related,
	);

	$seeds['pavashot'] = array(
		'enabled'      => 1,
		'title'        => 'Pavashot',
		'title_accent' => '',
		'eyebrow'      => 'Less Lethal · PAVA / OC Launchers',
		'chips'        => 'StickShot, NPDD, PavaBall Projectiles, Reloads',
		'_description' => 'Pavashot less lethal launchers and projectiles. Pavashot devices deliver PAVA, a synthetic pepper compound, to stop a threat at a distance without a firearm. Shop the <strong>StickShot</strong>, the <strong>NPDD</strong> non-pyrotechnic diversionary device, and <strong>PavaBall</strong> projectiles and reloads.',

		'tiles_label'  => 'Shop Pavashot',
		'tiles_title'  => 'Launchers and reloads',
		'tiles'        => array(
			array( 'url' => '/?s=stickshot&post_type=product', 'kicker' => '01 — Launcher', 'title' => 'StickShot', 'text' => 'Handheld reloadable PAVA launcher.', 'chips' => 'Handheld, Reloadable', 'cta' => 'View the StickShot' ),
			array( 'url' => '/?s=npdd&post_type=product', 'kicker' => '02 — Diversionary', 'title' => 'NPDD', 'text' => 'Non-pyrotechnic diversionary device and reload kits.', 'chips' => 'Diversionary, Reload Kits', 'cta' => 'View the NPDD' ),
			array( 'url' => '/?s=pavaball&post_type=product', 'kicker' => '03 — Projectiles', 'title' => 'PavaBall Projectiles', 'text' => 'Capsaicin projectiles and reloads for Pavashot launchers.', 'chips' => 'Projectiles, Reloads', 'cta' => 'View projectiles' ),
		),

		'cmp_label'    => 'Pavashot vs Byrna',
		'cmp_title'    => 'Pavashot or Byrna?',
		'cmp_intro'    => 'We carry both. They stop a threat at a distance in different ways.',
		'cmp_col_a'    => 'Pavashot',
		'cmp_col_b'    => 'Byrna',
		'cmp_rows'     => array(
			array( 'label' => 'Agent', 'a' => 'PAVA (synthetic capsaicin)', 'b' => 'OC / PAVA pepper projectiles, plus kinetic rounds' ),
			array( 'label' => 'Format', 'a' => 'Handheld launchers and diversionary devices', 'b' => 'CO2 launchers, pistol and rifle style' ),
			array( 'label' => 'Shop', 'a' => '<a href="/brand/pavashot/">Pavashot &rarr;</a>', 'b' => '<a href="/brand/byrna/">Byrna &rarr;</a>' ),
		),

		'faq_title'    => 'Pavashot questions',
		'faqs'         => array(
			array( 'q' => 'What is PAVA?', 'a' => 'PAVA (pelargonic acid vanillylamide) is a synthetic form of capsaicin, the compound that makes pepper spray work. It causes intense eye and skin irritation that stops a threat without lasting injury in most cases.' ),
			array( 'q' => 'Is Pavashot legal where I live?', 'a' => 'Laws on pepper-based devices vary by state and city. Check your local laws before ordering. Checkout will tell you if we cannot ship a product to your address.' ),
			array( 'q' => 'Where can I buy Pavashot reloads?', 'a' => 'Right here. We stock PavaBall projectiles and reload kits alongside the launchers.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'Less lethal guides',
		'art_pinned'   => '',
		'art_tag'      => 'pavashot',
		'art_count'    => 3,

		'agency_on'    => 0,

		'related_title' => 'Shop related',
		'related_slugs' => array( 'less-lethal' ),
	);

	return $seeds;
} );
