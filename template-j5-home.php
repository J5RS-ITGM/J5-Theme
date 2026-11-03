<?php
/**
 * Template Name: J5 Home
 *
 * Home page, rebuilt Nov 2026: photo hero with fixed copy, then plain
 * sections (categories, best sellers, agencies, new arrivals, reviews,
 * articles, about). No rotators, counters, eyebrow labels or stat bars.
 *
 * Hero: theme photo assets/img/home/hero.jpg (picked Nov 2026 from five
 * tested on staging). Swap the file to change it; crop focus is in
 * $j5h_hero_img.
 *
 * Styles: assets/css/j5-home2.css (loaded after j5-home.css, which still
 * styles the site header on this template). Every class here is j5h-*.
 *
 * Previous home page: git history, commit fdf5f76 and earlier.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'j5-home-template';
	$classes[] = 'j5h-page';
	return $classes;
} );

/* ---------------------------------------------------------------------------
 * Copy. Edit here.
 * ------------------------------------------------------------------------ */

$j5h_free_ship = function_exists( 'j5_free_ship_amount' ) && j5_free_ship_amount() ? j5_free_ship_amount() : '$500';

$j5h_hero = array(
	'title' => 'Body armor, medical and tactical gear.',
	'lead'  => 'J5 Rescue Supply is a veteran-owned dealer in Bolingbrook, Illinois. We carry HESCO, HighCom and United Shield armor, along with the medical, rescue and duty gear that goes with it.',
);

$j5h_facts = array(
	'Free shipping on orders over ' . $j5h_free_ship . ', body armor excluded',
	'Orders in by 3 PM Central ship the same day',
	'Agencies are set up on net terms',
);

/* ---------------------------------------------------------------------------
 * Data
 * ------------------------------------------------------------------------ */

$j5h_shop_url  = class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$j5h_armor_url = get_term_link( 256, 'product_cat' ); // body-armor
if ( is_wp_error( $j5h_armor_url ) ) {
	$j5h_armor_url = home_url( '/body-armor/' );
}
$j5h_quote_url = function_exists( 'j5_quote_base' ) ? j5_quote_base() : home_url( '/request-a-quote/' );

// Hero photo (entry team), assets/img/home/hero.jpg (2000px) + hero-1000.jpg.
// Crop focus: desktop / phone, as CSS object-position.
$j5h_hero_img = array(
	'dir'    => get_stylesheet_directory_uri() . '/assets/img/home/',
	'ver'    => (string) filemtime( get_stylesheet_directory() . '/assets/img/home/hero.jpg' ),
	'focus'  => 'center 25%',
	'mobile' => '58% 20%',
);

// Top-level shop categories, in the order set under Products > Categories.
$j5h_cats = get_terms( array(
	'taxonomy'   => 'product_cat',
	'parent'     => 0,
	'hide_empty' => true,
	'orderby'    => 'menu_order',
	'exclude'    => array_filter( array( (int) get_option( 'default_product_cat' ) ) ),
	'number'     => 12,
) );
if ( is_wp_error( $j5h_cats ) ) {
	$j5h_cats = array();
}

$j5h_best = array();
$j5h_new  = array();
if ( class_exists( 'WooCommerce' ) ) {
	$j5h_best = wc_get_products( array(
		'limit' => 4, 'status' => 'publish', 'visibility' => 'catalog',
		'orderby' => 'meta_value_num', 'meta_key' => 'total_sales', 'order' => 'DESC', // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	$j5h_new = wc_get_products( array(
		'limit' => 4, 'status' => 'publish', 'visibility' => 'catalog',
		'orderby' => 'date', 'order' => 'DESC',
		'exclude' => wp_list_pluck( $j5h_best, 'id' ),
	) );
}

// Services: shown only when the page exists, so a retired page never links dead.
$j5h_services = array();
foreach ( array(
	'kit-packing'       => 'Custom IFAK and kit packing',
	'design-embroidery' => 'Embroidery and placards',
	'ffl-services'      => 'FFL services',
) as $slug => $label ) {
	$p = get_page_by_path( $slug );
	if ( $p && 'publish' === $p->post_status ) {
		$j5h_services[] = array( 'label' => $label, 'url' => get_permalink( $p ) );
	}
}

$j5h_reviews = array(
	array( 'quote' => 'Just received my M210 Hesco plates and am very pleased, both with the product and J5 Rescue service.', 'name' => 'Jason Howard', 'date' => 'May 2024' ),
	array( 'quote' => 'I bought 3 different items from them and I love them all. I bought a smaller first aid kit for my mom\'s house and everything came fast.', 'name' => 'Jaclyn Chraca', 'date' => 'June 2022' ),
	array( 'quote' => '5 Stars are not enough. Ordered something that was difficult to find anywhere. They were listed as \'in stock,\' and they actually were.', 'name' => 'Fritz Ogden', 'date' => 'March 2022' ),
);
$j5h_reviews_url = apply_filters( 'j5_google_reviews_url', 'https://www.google.com/maps/search/?api=1&query=J5+Rescue+Supply+LLC+Bolingbrook+IL' );

$j5h_posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 3, 'ignore_sticky_posts' => 1 ) );
$j5h_blog  = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/news/' );

/** Product card: photo, brand, name, price. The whole card is the link. */
$j5h_card = function ( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	// Brand as written in Products > Brands (HESCO, HighCom), not upper-cased.
	$brand = '';
	if ( taxonomy_exists( 'product_brand' ) ) {
		$bt = get_the_terms( $product->get_id(), 'product_brand' );
		if ( $bt && ! is_wp_error( $bt ) ) {
			$brand = $bt[0]->name;
		}
	}
	$img   = $product->get_image_id() ? wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'class' => 'j5h-card__img' ) ) : '';
	?>
	<a class="j5h-card" href="<?php echo esc_url( $product->get_permalink() ); ?>">
		<span class="j5h-card__photo"><?php echo $img ? $img : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<?php if ( $brand ) : ?><span class="j5h-card__brand"><?php echo esc_html( $brand ); ?></span><?php endif; ?>
		<span class="j5h-card__name"><?php echo esc_html( $product->get_name() ); ?></span>
		<span class="j5h-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
	</a>
	<?php
};

get_header();
?>

<div class="j5h">

	<section class="j5h-hero" style="--j5h-focus:<?php echo esc_attr( $j5h_hero_img['focus'] ); ?>;--j5h-focus-m:<?php echo esc_attr( $j5h_hero_img['mobile'] ); ?>">
		<img class="j5h-hero__img"
			src="<?php echo esc_url( $j5h_hero_img['dir'] . 'hero.jpg?v=' . $j5h_hero_img['ver'] ); ?>"
			srcset="<?php echo esc_url( $j5h_hero_img['dir'] . 'hero-1000.jpg?v=' . $j5h_hero_img['ver'] ); ?> 1000w, <?php echo esc_url( $j5h_hero_img['dir'] . 'hero.jpg?v=' . $j5h_hero_img['ver'] ); ?> 2000w"
			sizes="100vw" width="2000" height="1333" alt="Police entry team in body armor and helmets, rifle with weapon light" fetchpriority="high">
		<div class="j5h-wrap j5h-hero__inner">
			<h1 class="j5h-hero__title"><?php echo esc_html( $j5h_hero['title'] ); ?></h1>
			<p class="j5h-hero__lead"><?php echo esc_html( $j5h_hero['lead'] ); ?></p>
			<div class="j5h-hero__actions">
				<a class="j5h-btn j5h-btn--solid" href="<?php echo esc_url( $j5h_armor_url ); ?>">Shop body armor</a>
				<a class="j5h-btn j5h-btn--line" href="<?php echo esc_url( $j5h_shop_url ); ?>">Browse all gear</a>
			</div>
		</div>
	</section>

	<div class="j5h-facts">
		<ul class="j5h-wrap j5h-facts__list">
			<?php foreach ( $j5h_facts as $f ) : ?>
				<li><?php echo esc_html( $f ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>

	<?php if ( $j5h_cats ) : ?>
	<section class="j5h-sec">
		<div class="j5h-wrap">
			<div class="j5h-head">
				<h2 class="j5h-h2">Shop by category</h2>
				<a class="j5h-more" href="<?php echo esc_url( $j5h_shop_url ); ?>">All products</a>
			</div>
			<ul class="j5h-cats">
				<?php foreach ( $j5h_cats as $c ) : ?>
					<li>
						<a href="<?php echo esc_url( get_term_link( $c ) ); ?>">
							<span class="j5h-cats__name"><?php echo esc_html( $c->name ); ?></span>
							<span class="j5h-cats__count"><?php echo esc_html( number_format_i18n( $c->count ) ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $j5h_best ) : ?>
	<section class="j5h-sec">
		<div class="j5h-wrap">
			<div class="j5h-head">
				<h2 class="j5h-h2">Best sellers</h2>
				<a class="j5h-more" href="<?php echo esc_url( add_query_arg( 'orderby', 'popularity', $j5h_shop_url ) ); ?>">See more</a>
			</div>
			<div class="j5h-grid"><?php foreach ( $j5h_best as $p ) { $j5h_card( $p ); } ?></div>
		</div>
	</section>
	<?php endif; ?>

	<section class="j5h-sec j5h-agency">
		<div class="j5h-wrap j5h-agency__inner">
			<div>
				<h2 class="j5h-h2">Buying for an agency or department?</h2>
				<p>Agencies are set up on net terms automatically, and we take purchase orders by email. Pay by ACH or wire with no fee. For armor programs, our client portal keeps sizing, issue records and reorders in one place.</p>
				<p class="j5h-agency__links">
					<a class="j5h-btn j5h-btn--solid" href="<?php echo esc_url( $j5h_quote_url ); ?>">Request a quote</a>
					<a class="j5h-btn j5h-btn--line" href="https://portal.j5rescue.com/">Agency portal</a>
				</p>
			</div>
			<?php if ( $j5h_services ) : ?>
			<div class="j5h-agency__side">
				<h3 class="j5h-h3">We also do</h3>
				<ul>
					<?php foreach ( $j5h_services as $s ) : ?>
						<li><a href="<?php echo esc_url( $s['url'] ); ?>"><?php echo esc_html( $s['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $j5h_new ) : ?>
	<section class="j5h-sec">
		<div class="j5h-wrap">
			<div class="j5h-head">
				<h2 class="j5h-h2">New in the store</h2>
				<a class="j5h-more" href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $j5h_shop_url ) ); ?>">See more</a>
			</div>
			<div class="j5h-grid"><?php foreach ( $j5h_new as $p ) { $j5h_card( $p ); } ?></div>
		</div>
	</section>
	<?php endif; ?>

	<section class="j5h-sec">
		<div class="j5h-wrap">
			<div class="j5h-head">
				<h2 class="j5h-h2">From our Google reviews</h2>
				<a class="j5h-more" href="<?php echo esc_url( $j5h_reviews_url ); ?>" target="_blank" rel="noopener">Read them all</a>
			</div>
			<div class="j5h-reviews">
				<?php foreach ( $j5h_reviews as $r ) : ?>
					<figure class="j5h-review">
						<blockquote><p><?php echo esc_html( $r['quote'] ); ?></p></blockquote>
						<figcaption><?php echo esc_html( $r['name'] ); ?>, <?php echo esc_html( $r['date'] ); ?></figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php if ( $j5h_posts ) : ?>
	<section class="j5h-sec">
		<div class="j5h-wrap">
			<div class="j5h-head">
				<h2 class="j5h-h2">Guides and articles</h2>
				<a class="j5h-more" href="<?php echo esc_url( $j5h_blog ); ?>">All articles</a>
			</div>
			<div class="j5h-posts">
				<?php foreach ( $j5h_posts as $post_item ) : ?>
					<a class="j5h-post" href="<?php echo esc_url( get_permalink( $post_item ) ); ?>">
						<?php if ( has_post_thumbnail( $post_item ) ) : ?>
							<span class="j5h-post__photo"><?php echo get_the_post_thumbnail( $post_item, 'medium_large', array( 'loading' => 'lazy' ) ); ?></span>
						<?php endif; ?>
						<span class="j5h-post__date"><?php echo esc_html( get_the_date( 'F j, Y', $post_item ) ); ?></span>
						<span class="j5h-post__title"><?php echo esc_html( get_the_title( $post_item ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<section class="j5h-sec j5h-about">
		<div class="j5h-wrap j5h-about__inner">
			<div>
				<h2 class="j5h-h2">About J5 Rescue Supply</h2>
				<p>J5 is owned and run by a veteran who still works as a firefighter and paramedic. We sell online, through our sales reps and at trade shows, and everything ships from Bolingbrook, Illinois.</p>
				<p>If you're not sure which plate, cut or size fits your situation, call or email and talk to us before you buy.</p>
			</div>
			<dl class="j5h-about__contact">
				<dt>Phone</dt><dd><a href="tel:+16304424938">(630) 442-4938</a></dd>
				<dt>Email</dt><dd><a href="mailto:info@j5rescue.com">info@j5rescue.com</a></dd>
				<dt>Location</dt><dd>Bolingbrook, Illinois</dd>
			</dl>
		</div>
	</section>

</div>

<?php
get_footer();
