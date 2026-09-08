<?php
/**
 * Template Name: J5 Home (Odoo Style)
 *
 * Homepage content template. Relies on the new site chrome (header.php +
 * footer.php) for utility bar, main header, mega menu, and footer.
 *
 * Previously self-contained (rendered its own <!DOCTYPE> -> </html>). That
 * shell was removed during the 2026-04 chrome migration so all pages share
 * one consistent header/footer and the commercial-site identity stays
 * coherent across the site.
 *
 * Commercial identity rule: j5rescue.com is primarily a retail storefront.
 * The "LE & First Responders" section is one tasteful entry point routing
 * sworn/licensed users to ops.j5rescue.com — NOT the primary identity of
 * every hero, stat, or trust element on this page.
 *
 * Assign this template in: Page Editor -> Page Attributes -> Template.
 * When used on the homepage (page 94772), it becomes the live front page.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Preserve the legacy body class `j5-home-template` so the existing
 * assets/css/j5-home.css styles (which scope everything under
 * `body.j5-home-template`) still match. Without this, hero + LE banner +
 * block titles + impact stats all inherit default colors and render as
 * dark text on dark background.
 */
add_filter( 'body_class', function ( $classes ) {
	if ( is_page_template( 'template-j5-home.php' ) || ( is_front_page() && get_page_template_slug() === 'template-j5-home.php' ) ) {
		$classes[] = 'j5-home-template';
	}
	return $classes;
} );

// ============================================================================
//  Hero + category config
// ============================================================================

// Eight category tiles on the homepage (matches your current homepage layout)
$j5_categories = array(
        array( 'name' => 'Ballistic Armor',      'sub' => 'Hard plates, soft armor, shields, helmets', 'url' => site_url( '/armor/' ),                       'icon' => 'shield' ),
        array( 'name' => 'Nylon Gear',           'sub' => 'Carriers, pouches, packs & placards',       'url' => site_url( '/nylon-gear/' ),                  'icon' => 'pouch'  ),
        array( 'name' => 'Accessories & Tools',  'sub' => 'Knives, multi-tools, breaching kits',       'url' => site_url( '/tools-knives/' ),                'icon' => 'tool'   ),
        array( 'name' => 'Med Kit Supplies',     'sub' => 'IFAK & aid bag components',                 'url' => site_url( '/medical/' ),                     'icon' => 'cross'  ),
        array( 'name' => 'Medical Bags',         'sub' => 'Jump bags, aid bags, MCI packs',            'url' => site_url( '/bags-cases/' ),                  'icon' => 'bag'    ),
        array( 'name' => 'Optics',               'sub' => 'Sights, scopes, mounts & rails',            'url' => site_url( '/optics-weapon-accessories/' ),   'icon' => 'comms'  ),
        array( 'name' => 'Less-Lethal',          'sub' => 'Launchers, projectiles, TASER',             'url' => site_url( '/less-lethal/' ),                 'icon' => 'bolt'   ),
        array( 'name' => 'Lights',               'sub' => 'Handheld, WML, helmet, scene',              'url' => site_url( '/lights/' ),                      'icon' => 'light'  ),
        array( 'name' => 'Firearms Parts',       'sub' => 'Components, parts & accessories',           'url' => site_url( '/firearms-parts/' ),              'icon' => 'tool'   ),
);

// ============================================================================
//  WooCommerce product queries
// ============================================================================

$j5_new_arrivals = array();
$j5_best_sellers = array();

if ( class_exists( 'WooCommerce' ) ) {
	$j5_new_arrivals = wc_get_products( array(
		'limit'      => 4,
		'orderby'    => 'date',
		'order'      => 'DESC',
		'status'     => 'publish',
		'visibility' => 'catalog',
	) );

	$j5_best_sellers = wc_get_products( array(
		'limit'      => 4,
		'orderby'    => 'meta_value_num',
		'meta_key'   => 'total_sales',
		'order'      => 'DESC',
		'status'     => 'publish',
		'visibility' => 'catalog',
	) );
}

$j5_shop_url = class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'shop' ) : site_url( '/shop/' );

// ============================================================================
//  Render — site header (from header.php) is already open by this point.
//  <main id="main" class="j5-site-content"> is also already open.
// ============================================================================

get_header();
?>

<div class="j5-home">

	<!-- === J5-HOME-HERO-START === -->
	<section class="j5-hero">
		<div class="j5-container j5-hero-grid">

			<div>
				<h1 class="j5-hero-title">
					MISSION-READY GEAR.<br/>
					<span class="j5-hero-rotator-lead">Trusted By</span> <span class="rotator" data-j5-rotator='["Professionals","Enthusiasts","The Prepared"]'>Professionals</span>
				</h1>
				<p class="j5-hero-desc">
					Specially curated medical, rescue, and tactical equipment.
				</p>
				<div class="j5-hero-ctas">
					<a href="<?php echo esc_url( $j5_shop_url ); ?>" class="j5-btn j5-btn-primary">Shop Gear <span class="arrow">&rarr;</span></a>
					<a href="<?php echo esc_url( site_url( '/request-a-quote/' ) ); ?>" class="j5-btn j5-btn-ghost">Request a Quote</a>
				</div>
				<!-- J5-HERO-CAPABILITIES-START — 2026-05-20 — replaces former j5-hero-stats -->
				<div class="j5-hero-capabilities">
					<div class="j5-hero-cap">
						<svg class="j5-hero-cap-icon" viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5l-8-3z"/><path d="M9 12l2 2 4-4"/></svg>
						<div class="j5-hero-cap-label">USA-Made<br/>Body Armor</div>
					</div>
					<div class="j5-hero-cap">
						<svg class="j5-hero-cap-icon" viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 17h10v-6H3z"/><path d="M13 11h4l4 3v3h-8z"/><circle cx="7" cy="19" r="2"/><circle cx="17" cy="19" r="2"/></svg>
						<div class="j5-hero-cap-label">Fast<br/>Shipping</div>
					</div>
					<div class="j5-hero-cap">
						<svg class="j5-hero-cap-icon" viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m7.5 4.27 9 5.15"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
						<div class="j5-hero-cap-label"><?php echo esc_html( function_exists( 'j5_count_products' ) ? j5_count_products() : '600' ); ?>+ SKUs<br/>In Stock</div>
					</div>
					<div class="j5-hero-cap">
						<svg class="j5-hero-cap-icon" viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.5 12.5 17 21l-5-3-5 3 1.5-8.5"/></svg>
						<div class="j5-hero-cap-label">Veteran &amp; First<br/>Responder Owned</div>
					</div>
				</div>
				<!-- J5-HERO-CAPABILITIES-END -->
			</div>

			<!-- === J5-HERO-SEARCH-CARD-START === -->


			<?php /* Hero search card removed 2026-05-20. Wrapped, not deleted, for easy restore. */ ?>


			<?php /*


			<aside class="j5-hero-card j5-hero-search-card">
				<div class="j5-hc-label">// FIND IT FAST</div>
				<h2 class="j5-hc-title">Search 600+ SKUs</h2>
				<form class="j5-hero-search-form" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
					<div class="j5-hero-search-input-wrap">
						<input type="search" name="s" class="j5-hero-search-input" placeholder="Tourniquet, IFAK, plate carrier..." autocomplete="off" aria-label="Search products" />
						<input type="hidden" name="post_type" value="product" />
						<button type="submit" class="j5-hero-search-btn">Search</button>
					</div>
					<div class="j5-hero-search-results" aria-live="polite"></div>
				</form>
				<div class="j5-hero-popular-label">POPULAR SEARCHES</div>
				<div class="j5-hero-popular-pills">
					<?php
					$j5_popular = array( 'Tourniquet', 'IFAK', 'Chest Seal', 'Plate Carrier', 'Hemostatic' );
					foreach ( $j5_popular as $term ) :
						$u = add_query_arg( array( 's' => rawurlencode( $term ), 'post_type' => 'product' ), home_url( '/' ) );
						?>
						<a href="<?php echo esc_url( $u ); ?>" class="j5-hero-pill"><?php echo esc_html( $term ); ?></a>
					<?php endforeach; ?>
				</div>
			</aside>


			*/ ?>


			<!-- === J5-HERO-SEARCH-CARD-END === -->

		</div>
	</section>
	<!-- === J5-HOME-HERO-END === -->

	<!-- === J5-HOME-TRUST-STRIP-START === -->
	<div class="j5-trust-strip">
		<div class="j5-container j5-trust-inner">
			<div class="j5-trust-item">
				<span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
				Free Shipping <?php echo esc_html( function_exists( 'j5_free_ship_label' ) ? j5_free_ship_label() : 'on orders over $99' ); ?>
			</div>
			<span class="j5-trust-sep">&#9670;</span>
			<div class="j5-trust-item">
				<span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5l-8-3z"/><path d="M9 12l2 2 4-4"/></svg></span>
				Life Safety Guarantee
			</div>
			<span class="j5-trust-sep">&#9670;</span>
			<div class="j5-trust-item">
				<span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h18v13H3z"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/></svg></span>
				Custom Kit Packing
			</div>
		</div>
	</div>
	<!-- === J5-HOME-TRUST-STRIP-END === -->

	<!-- === J5-HOME-LE-BANNER-START === -->
	<?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?>
	<section class="j5-le-banner">
		<div class="j5-container j5-le-inner">
			<div class="j5-le-badge-big">LE</div>
			<div class="j5-le-content">
				<h3>LAW ENFORCEMENT <span class="gold">&amp; FIRST RESPONDERS</span></h3>
				<p>Active credentials unlock agency pricing, department ordering, and officer sizing through the secure operations portal. For sworn law enforcement, fire/EMS, and licensed professionals.</p>
			</div>
			<a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener" class="j5-btn j5-btn-primary">Enter Portal <span class="arrow">&rarr;</span></a>
		</div>
	</section>
	<?php endif; ?>
	<!-- === J5-HOME-LE-BANNER-END === -->

	<!-- === J5-HOME-CATEGORIES-START === -->
	<section class="j5-block">
		<div class="j5-container">
			<div class="j5-block-head">
				<div>
					<h2 class="j5-block-title">Shop by <span class="accent">Category</span>.</h2>
				</div>
			</div>

			<div class="j5-cat-grid">
				<?php foreach ( $j5_categories as $i => $cat ) : ?>
					<a href="<?php echo esc_url( $cat['url'] ); ?>" class="j5-cat-card">
						<div class="j5-cat-num"><?php echo esc_html( str_pad( $i + 1, 2, '0', STR_PAD_LEFT ) ); ?> / <?php echo count( $j5_categories ); ?></div>
						<div class="j5-cat-icon"><?php echo function_exists( 'j5_category_icon' ) ? j5_category_icon( $cat['icon'] ) : ''; ?></div>
						<div>
							<div class="j5-cat-name"><?php echo esc_html( $cat['name'] ); ?></div>
							<div class="j5-cat-sub"><?php echo esc_html( $cat['sub'] ); ?></div>
							<div class="j5-cat-link">Shop <?php echo esc_html( $cat['name'] ); ?> <span class="arrow">&rarr;</span></div>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<!-- === J5-HOME-CATEGORIES-END === -->

	<!-- === J5-HOME-NEW-ARRIVALS-START === -->
	<?php if ( ! empty( $j5_new_arrivals ) ) : ?>
	<section class="j5-block">
		<div class="j5-container">
			<div class="j5-block-head">
				<div>
					<div class="j5-block-label">JUST IN</div>
					<h2 class="j5-block-title">New <span class="accent">arrivals.</span></h2>
				</div>
				<a href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $j5_shop_url ) ); ?>" class="j5-btn j5-btn-ghost">View All New <span class="arrow">&rarr;</span></a>
			</div>
			<div class="j5-prod-grid">
				<?php foreach ( $j5_new_arrivals as $product ) : ?>
					<?php if ( function_exists( 'j5_render_product_card' ) ) { j5_render_product_card( $product, 'new' ); } ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>
	<!-- === J5-HOME-NEW-ARRIVALS-END === -->

	<!-- === J5-HOME-BEST-SELLERS-START === -->
	<?php if ( ! empty( $j5_best_sellers ) ) : ?>
	<section class="j5-block j5-block-alt">
		<div class="j5-container">
			<div class="j5-block-head">
				<div>
					<div class="j5-block-label">TOP OF THE KIT</div>
					<h2 class="j5-block-title">Best <span class="accent">sellers.</span></h2>
				</div>
				<a href="<?php echo esc_url( add_query_arg( 'orderby', 'popularity', $j5_shop_url ) ); ?>" class="j5-btn j5-btn-ghost">View All Best Sellers <span class="arrow">&rarr;</span></a>
			</div>
			<div class="j5-prod-grid">
				<?php foreach ( $j5_best_sellers as $i => $product ) : ?>
					<?php if ( function_exists( 'j5_render_product_card' ) ) { j5_render_product_card( $product, $i === 0 ? 'top' : 'seller' ); } ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>
	<!-- === J5-HOME-BEST-SELLERS-END === -->

	<!-- === J5-HOME-SERVICES-START === -->
	<section class="j5-block j5-services">
		<div class="j5-container">
			<div class="j5-block-head">
				<div>
					<div class="j5-block-label">SERVICES / BEYOND THE CART</div>
					<h2 class="j5-block-title">Build it with <span class="accent">us.</span></h2>
				</div>
				<p class="j5-block-desc">Custom kit packing, embroidery, FFL transfers, and quotes for complex orders. Tell us what you need and we'll get it done.</p>
			</div>

			<div class="j5-services-grid">
				<div class="j5-service-card">
					<div class="num">01</div>
					<h4>Custom Kit Packing</h4>
					<p>Pre-packed IFAKs and aid bags built to your spec. Sealed, labeled, lot-tracked.</p>
					<a href="<?php echo esc_url( site_url( '/kit-packing/' ) ); ?>" class="j5-cat-link">Start an Order <span class="arrow">&rarr;</span></a>
				</div>
				<div class="j5-service-card">
					<div class="num">02</div>
					<h4>Design &amp; Embroidery</h4>
					<p>Patches, morale patches, unit IDs, and custom placards. Low minimums, fast turnaround.</p>
					<a href="<?php echo esc_url( site_url( '/design-embroidery/' ) ); ?>" class="j5-cat-link">Submit Artwork <span class="arrow">&rarr;</span></a>
				</div>
				<div class="j5-service-card">
					<div class="num">03</div>
					<h4>FFL Services</h4>
					<p>Transfers, less-lethal and controlled item receiving, and destination verification &mdash; handled correctly.</p>
					<a href="<?php echo esc_url( site_url( '/ffl-services/' ) ); ?>" class="j5-cat-link">View FFL Info <span class="arrow">&rarr;</span></a>
				</div>
				<div class="j5-service-card">
					<div class="num">04</div>
					<h4>Request a Quote</h4>
					<p>Bulk buys, department orders, and specialty items. Quotes returned within 24 hours, typically faster.</p>
					<a href="<?php echo esc_url( site_url( '/request-a-quote/' ) ); ?>" class="j5-cat-link">Get a Quote <span class="arrow">&rarr;</span></a>
				</div>
			</div>
		</div>
	</section>
	<!-- === J5-HOME-SERVICES-END === -->

	<!-- === J5-HOME-IMPACT-START === -->
	<section class="j5-impact">
		<div class="j5-container">
			<div class="j5-impact-grid">
				<div class="j5-impact-cell">
					<div class="num"><?php echo esc_html( function_exists( 'j5_count_products' ) ? j5_count_products() : '600' ); ?><span class="plus">+</span></div>
					<div class="label">Active SKUs Stocked</div>
				</div>
				<div class="j5-impact-cell">
					<div class="num">5.0<span class="plus">&#9733;</span></div>
					<div class="label">43 Google Reviews</div>
				</div>
				<div class="j5-impact-cell">
					<div class="num j5-impact-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5l-8-3z"/><path d="M9 12l2 2 4-4"/></svg>
					</div>
					<div class="label">Life Safety Guarantee</div>
				</div>
				<div class="j5-impact-cell">
					<div class="num j5-impact-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
					</div>
					<div class="label">Quick Turn Around Quotes</div>
				</div>
			</div>
		</div>
	</section>
	<!-- === J5-HOME-IMPACT-END === -->

	<!-- === J5-HOME-TESTIMONIALS-START === -->
	<section class="j5-block">
		<div class="j5-container">
			<div class="j5-block-head">
				<div>
					<div class="j5-block-label">FIELD REPORTS</div>
					<h2 class="j5-block-title">What our <span class="accent">customers</span> are saying.</h2>
					<div class="j5-testi-meta">
						<span class="j5-testi-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
						<span class="j5-testi-rating">5.0</span>
						<span class="j5-testi-source">from 43 verified Google reviews</span>
					</div>
				</div>
			</div>

			<div class="j5-testi-grid">
				<div class="j5-testi">
					<p class="quote">Just received my M210 Hesco plates and am very pleased, both with the product and J5 Rescue service.</p>
					<div class="author">
						<div class="avatar j5-avatar-google" aria-label="Google review">
							<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
						</div>
						<div>
							<div class="name">Jason Howard</div>
							<div class="role">Verified Google Review &middot; May 2024</div>
						</div>
					</div>
				</div>
				<div class="j5-testi">
					<p class="quote">I bought 3 different items from them and I love them all. I bought a smaller first aid kit for my mom's house and everything came fast.</p>
					<div class="author">
						<div class="avatar j5-avatar-google" aria-label="Google review">
							<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
						</div>
						<div>
							<div class="name">Jaclyn Chraca</div>
							<div class="role">Verified Google Review &middot; Jun 2022</div>
						</div>
					</div>
				</div>
				<div class="j5-testi">
					<p class="quote">5 Stars are not enough. Ordered something that was difficult to find anywhere. They were listed as 'in stock,' and they actually were.</p>
					<div class="author">
						<div class="avatar j5-avatar-google" aria-label="Google review">
							<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
						</div>
						<div>
							<div class="name">Fritz Ogden</div>
							<div class="role">Verified Google Review &middot; Mar 2022</div>
						</div>
					</div>
				</div>
			</div>

			<?php
			/**
			 * Google Reviews link. Filterable so you can swap in an exact Place ID
			 * URL later — drop this in functions.php to override:
			 *   add_filter( 'j5_google_reviews_url', function () {
			 *       return 'https://g.page/your-exact-place-id-url';
			 *   } );
			 */
			$j5_google_reviews_url = apply_filters(
				'j5_google_reviews_url',
				'https://www.google.com/maps/search/?api=1&query=J5+Rescue+Supply+LLC+Bolingbrook+IL'
			);
			?>
			<div class="j5-testi-cta">
				<a href="<?php echo esc_url( $j5_google_reviews_url ); ?>" target="_blank" rel="noopener" class="j5-btn j5-btn-ghost">
					See all 43 reviews on Google <span class="arrow">&rarr;</span>
				</a>
			</div>
		</div>
	</section>
	<!-- === J5-HOME-TESTIMONIALS-END === -->

	<!-- === J5-HOME-ARTICLES-START === -->
	<?php
	if ( function_exists( 'j5_news_render_latest_section' ) ) {
		j5_news_render_latest_section( array(
			'posts_per_page' => 3,
			'eyebrow'        => 'FROM THE BLOG',
			'title'          => 'Latest from the news desk.',
			'description'    => 'Product notes, responder resources, and field-tested guidance from the J5 team.',
			'button_label'   => 'View All Articles',
		) );
	}
	?>
	<!-- === J5-HOME-ARTICLES-END === -->

	<!-- === J5-HOME-NEWSLETTER-START === -->
	<section class="j5-news">
		<div class="j5-container j5-news-inner">
			<div class="j5-block-label" style="justify-content: center;">SIGNALS</div>
			<h2 class="j5-news-title">Don't miss <span class="gold">the drop.</span></h2>
			<p>New product launches, clearance lists, member pricing alerts, and occasional educational articles. No spam &mdash; we hate it too.</p>
			<form class="j5-news-form" action="#" method="post" onsubmit="event.preventDefault(); alert('Hook this up to your mailing list provider.');">
				<input type="email" name="email" placeholder="your@email.com" required />
				<button type="submit">Subscribe</button>
			</form>
		</div>
	</section>
	<!-- === J5-HOME-NEWSLETTER-END === -->

</div><!-- .j5-home -->

<?php
get_footer();
