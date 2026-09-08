<?php
/**
 * J5 Rescue Supply — Site header
 * Commercial site (j5rescue.com). Replaces Astra's default header.
 *
 * Rendered on every page that calls get_header() — homepage, shop archive,
 * category pages, Elementor pages, default page template, etc.
 *
 * SELF-CONTAINED TEMPLATES (bypass this file — render own shell):
 *   - template-j5-home.php  -> page 94772 "Home V2" at /home-v2/ (preview)
 *   - template-j5-shop.php  -> page 94775 "Shop V2" at /shop-v2/ (preview)
 *   - single-j5-product.php -> single products (when assigned)
 *   - single-j5-cart.php    -> cart (stable per 2026-04-20 handoff; leave alone)
 *
 * TODO future phases:
 *   - Migrate template-j5-home.php (10 LE refs to strip) — commercial retail only
 *   - Migrate template-j5-shop.php (3 LE refs) -> woocommerce/archive-product.php
 *   - Evaluate single-j5-product.php migration (7 LE refs)
 *   - Leave single-j5-cart.php untouched — cart work is stable
 *
 * Commercial-only rule: NO "Veteran Owned" / "First Responder" / "Agencies" /
 * "LE Portal" / "Net 30" messaging. Those live on ops.j5rescue.com.
 *
 * Sentinel markers: J5-HEADER-* for partial revert.
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$j5_phone       = apply_filters( 'j5_header_phone', '(630) 442-4938' );
$j5_phone_tel   = apply_filters( 'j5_header_phone_tel', 'tel:+16304424938' );
$j5_promo       = apply_filters( 'j5_shipping_promo', 'Free shipping on orders over $99' );
$j5_cart_count  = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
$j5_account_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : wp_login_url();
$j5_orders_url  = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : $j5_account_url;
$j5_cart_url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="https://gmpg.org/xfn/11">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@400;500;600;700&display=swap">
<?php wp_head(); ?>
</head>

<body <?php body_class( 'j5-site j5-custom-chrome' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main">Skip to content</a>

<!-- === J5-HEADER-SEARCH-OVERLAY-START === -->
<div id="j5-search-overlay" class="j5-search-overlay" hidden>
	<div class="j5-search-overlay__backdrop" data-j5-search-close></div>
	<div class="j5-search-overlay__panel" role="dialog" aria-label="Search products" aria-modal="true">
		<form role="search" method="get" class="j5-search-overlay__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg class="j5-search-overlay__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="j5-search-overlay__input" name="s" placeholder="Search products&hellip;" value="<?php echo esc_attr( get_search_query() ); ?>" autocomplete="off">
			<input type="hidden" name="post_type" value="product">
			<button type="button" class="j5-search-overlay__close" data-j5-search-close aria-label="Close search">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
		</form>
	</div>
</div>
<!-- === J5-HEADER-SEARCH-OVERLAY-END === -->

<header id="j5-site-header" class="j5-site-header" role="banner">

	<!-- === J5-HEADER-UTILITY-BAR-START === -->
	<div class="j5-utility-bar">
		<div class="j5-utility-bar__inner">
			<div class="j5-utility-bar__left">
				<svg class="j5-utility-bar__icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
				<span><?php echo esc_html( $j5_promo ); ?></span>
			</div>
			<div class="j5-utility-bar__right">
				<a href="<?php echo esc_url( $j5_orders_url ); ?>">Track order</a>
				<span class="j5-utility-bar__sep" aria-hidden="true">&middot;</span>
				<a href="<?php echo esc_url( $j5_phone_tel ); ?>" class="j5-utility-bar__phone"><?php echo esc_html( $j5_phone ); ?></a>
			</div>
		</div>
	</div>
	<!-- === J5-HEADER-UTILITY-BAR-END === -->

	<!-- === J5-HEADER-MAIN-START === -->
	<div class="j5-main-header">
		<div class="j5-main-header__inner">

			<button type="button" class="j5-drawer-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="j5-mobile-drawer" data-j5-drawer-open>
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
			</button>

			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="j5-logo" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> home">
				<span class="j5-logo__mark">J5</span>
				<span class="j5-logo__wordmark"><?php bloginfo( 'name' ); ?></span>
			</a>

			<nav class="j5-primary-nav" aria-label="Primary navigation">
				<?php
				if ( has_nav_menu( 'primary' ) ) {
					wp_nav_menu( array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'j5-primary-nav__list',
						'fallback_cb'    => false,
						'depth'          => 2,
						'link_before'    => '<span>',
						'link_after'     => '</span>',
					) );
				}
				?>
			</nav>

			<div class="j5-header-actions">
				<button type="button" class="j5-icon-btn" aria-label="Search" data-j5-search-open>
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				</button>
				<a href="<?php echo esc_url( $j5_account_url ); ?>" class="j5-icon-btn j5-icon-btn--account" aria-label="My account">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
				</a>
				<a href="<?php echo esc_url( $j5_cart_url ); ?>" class="j5-cart-btn" aria-label="Cart">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
					<span class="j5-cart-btn__count" data-j5-cart-count><?php echo esc_html( $j5_cart_count ); ?></span>
				</a>
			</div>

		</div>
	</div>
	<!-- === J5-HEADER-MAIN-END === -->

	<!-- === J5-HEADER-MEGA-MENU-START === -->
	<?php if ( function_exists( 'j5_render_mega_menu' ) ) { j5_render_mega_menu(); } ?>
	<!-- === J5-HEADER-MEGA-MENU-END === -->

</header>

<!-- === J5-HEADER-MOBILE-DRAWER-START === -->
<div id="j5-mobile-drawer" class="j5-mobile-drawer" hidden>
	<div class="j5-mobile-drawer__backdrop" data-j5-drawer-close></div>
	<aside class="j5-mobile-drawer__panel" role="dialog" aria-label="Mobile menu" aria-modal="true">
		<div class="j5-mobile-drawer__header">
			<span class="j5-mobile-drawer__title">Menu</span>
			<button type="button" class="j5-mobile-drawer__close" aria-label="Close menu" data-j5-drawer-close>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
		</div>
		<form role="search" method="get" class="j5-mobile-drawer__search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" name="s" placeholder="Search products&hellip;" value="<?php echo esc_attr( get_search_query() ); ?>">
			<input type="hidden" name="post_type" value="product">
		</form>
		<nav class="j5-mobile-drawer__nav" aria-label="Mobile primary">
			<?php
			$mob_loc = has_nav_menu( 'mobile_menu' ) ? 'mobile_menu' : ( has_nav_menu( 'primary' ) ? 'primary' : '' );
			if ( $mob_loc ) {
				wp_nav_menu( array(
					'theme_location' => $mob_loc,
					'container'      => false,
					'menu_class'     => 'j5-mobile-nav',
					'fallback_cb'    => false,
					'depth'          => 2,
				) );
			}
			?>
		</nav>
		<div class="j5-mobile-drawer__footer">
			<a href="<?php echo esc_url( $j5_account_url ); ?>" class="j5-mobile-drawer__account">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
				<span>Sign in / Create account</span>
			</a>
			<div class="j5-mobile-drawer__contact">
				<a href="<?php echo esc_url( $j5_phone_tel ); ?>"><?php echo esc_html( $j5_phone ); ?></a>
				<span aria-hidden="true">&middot;</span>
				<a href="mailto:orders@j5rescue.com">orders@j5rescue.com</a>
			</div>
		</div>
	</aside>
</div>
<!-- === J5-HEADER-MOBILE-DRAWER-END === -->

<main id="main" class="j5-site-content">
