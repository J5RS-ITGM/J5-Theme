<?php
/**
 * Template Name: J5 Shop (Odoo Style)
 *
 * Staging shop page. Assign this template to a new WP page called "Shop V2"
 * to preview at /shop-v2/ without affecting your live /shop/.
 *
 * When ready to go live, copy this file's contents into
 * woocommerce/archive-product.php (see j5-shop-setup.php step 7).
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Build product query from URL params
$query_args = j5_build_product_query_args();
$results    = class_exists( 'WooCommerce' ) ? wc_get_products( $query_args ) : null;

$products   = $results && isset( $results->products ) ? $results->products : array();
$total      = $results && isset( $results->total ) ? (int) $results->total : 0;
$max_pages  = $results && isset( $results->max_num_pages ) ? (int) $results->max_num_pages : 1;
$page       = max( 1, absint( $_GET['page'] ?? 1 ) );
$per_page   = 36;
$start      = $total > 0 ? ( ( $page - 1 ) * $per_page ) + 1 : 0;
$end        = min( $page * $per_page, $total );

$active_chips = j5_get_active_filters();

// Header helpers
$j5_has_logo      = function_exists( 'has_custom_logo' ) && has_custom_logo();
$j5_cart_url      = class_exists( 'WooCommerce' ) ? wc_get_cart_url() : '#';
$j5_cart_count    = class_exists( 'WooCommerce' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
$j5_account_url   = class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
$j5_account_label = is_user_logged_in() ? 'Account' : 'Login';
$j5_shop_url      = class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'shop' ) : site_url( '/shop/' );

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<?php wp_head(); ?>
</head>
<body <?php body_class( 'j5-shop-template' ); ?>>
<?php if ( function_exists( 'wp_body_open' ) ) { wp_body_open(); } ?>

<!-- UTIL BAR -->
<div class="j5-util-bar">
    <div class="j5-container j5-util-inner">
        <div class="j5-util-left">
            <a href="tel:+16304424938"><span>☏</span> (630) 442-4938</a>
            <a href="<?php echo esc_url( site_url( '/contact-us/' ) ); ?>">✉ CONTACT</a>
            <a href="<?php echo esc_url( site_url( '/shipment-tracking/' ) ); ?>">◉ TRACK SHIPMENT</a>
        </div>
        <div class="j5-util-right">
            <a href="<?php echo esc_url( site_url( '/ffl-services/' ) ); ?>">FFL SERVICES</a>
            <a href="<?php echo esc_url( site_url( '/request-a-quote/' ) ); ?>">REQUEST A QUOTE</a>
            <?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?><a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener" class="j5-le-badge"><span class="j5-pulse-dot"></span>LE / AGENCY PORTAL →</a><?php endif; ?>
        </div>
    </div>
</div>

<!-- HEADER -->
<header class="j5-site-header">
    <div class="j5-container j5-header-inner">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="j5-brand">
            <?php if ( $j5_has_logo ) : the_custom_logo(); else : ?>
                <div class="j5-brand-mark"></div>
                <div class="j5-brand-text">
                    <div class="j5-brand-name"><?php bloginfo( 'name' ); ?></div>
                    <div class="j5-brand-sub">MEDICAL &middot; TACTICAL &middot; ARMOR</div>
                </div>
            <?php endif; ?>
        </a>
        <nav class="j5-main-nav" aria-label="Primary">
            <?php
            if ( has_nav_menu( 'primary' ) ) {
                wp_nav_menu( array(
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'j5-menu',
                    'fallback_cb'    => '__return_false',
                    'depth'          => 2,
                    'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
                ) );
            }
            ?>
        </nav>
        <div class="j5-header-actions">
            <a href="<?php echo esc_url( $j5_shop_url ); ?>" class="j5-btn-icon" aria-label="Search">⌕</a>
            <a href="<?php echo esc_url( $j5_account_url ); ?>" class="j5-btn-icon"><?php echo esc_html( $j5_account_label ); ?></a>
            <a href="<?php echo esc_url( $j5_cart_url ); ?>" class="j5-cart-trigger">
                Cart <span class="cart-count"><?php echo esc_html( str_pad( $j5_cart_count, 2, '0', STR_PAD_LEFT ) ); ?></span>
            </a>
        </div>
    </div>
</header>

<!-- SHOP HERO -->
<section class="j5-shop-hero">
    <div class="j5-container j5-shop-hero-inner">
        <div>
            <div class="j5-crumbs">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
                <span class="sep">/</span>
                <a href="<?php echo esc_url( $j5_shop_url ); ?>">Catalog</a>
                <span class="sep">/</span>
                <span class="current">Shop All</span>
            </div>
            <h1 class="j5-shop-title">SHOP ALL <span class="count">/ <?php echo esc_html( number_format( $total ) ); ?></span></h1>
            <p class="j5-shop-desc">Every in-stock SKU across medical, armor, nylon, less-lethal, and tools. Filter by brand, protection level, price, or agency fitment. Real stock counts. No drop-ship surprises.</p>
        </div>
        <div class="j5-shop-meta">
            <div class="j5-shop-meta-card">
                <div class="label">// STATUS</div>
                <div class="val">SHIPPING DAILY</div>
            </div>
            <div class="j5-shop-meta-card">
                <div class="label">// CUT-OFF</div>
                <div class="val">3:00 PM CT</div>
            </div>
        </div>
    </div>
</section>

<!-- TOOLBAR -->
<?php
/* J5-SHOP-SEARCH-PATCH-1-BANNER-START */
?>
<!-- J5-SHOP-SEARCH-PATCH-1-BANNER-START -->
<?php
$j5_search_term = isset( $_GET['s'] ) ? trim( (string) $_GET['s'] ) : '';
if ( $j5_search_term !== '' ) :
    $j5_search_term_display = sanitize_text_field( $j5_search_term );
    $j5_result_count = is_array( $results ?? null ) || is_object( $results ?? null )
        ? ( is_object( $results ) && isset( $results->total ) ? (int) $results->total : 0 )
        : 0;
    // Build a "clear search" URL by removing 's' but keeping other filters
    $j5_clear_args = $_GET;
    unset( $j5_clear_args['s'] );
    unset( $j5_clear_args['post_type'] );
    unset( $j5_clear_args['page'] );
    $j5_clear_url = add_query_arg( $j5_clear_args, home_url( '/shop/' ) );
    ?>
    <div class="j5-search-banner" role="region" aria-label="Search results">
        <div class="j5-container j5-search-banner__inner">
            <div>
                <span class="j5-search-banner__label">Searching for:</span>
                <span class="j5-search-banner__term"><?php echo esc_html( $j5_search_term_display ); ?></span>
                <?php if ( $j5_result_count > 0 ) : ?>
                    <span class="j5-search-banner__count">
                        <?php echo esc_html( sprintf( _n( '%d result', '%d results', $j5_result_count, 'astra-child' ), $j5_result_count ) ); ?>
                    </span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( $j5_clear_url ); ?>" class="j5-search-banner__clear">Clear search</a>
        </div>
    </div>
<?php endif; ?>
<!-- J5-SHOP-SEARCH-PATCH-1-BANNER-END -->
<?php
/* J5-SHOP-SEARCH-PATCH-1-BANNER-END */
?>
<div class="j5-toolbar">
    <div class="j5-container j5-toolbar-inner">
        <div class="j5-result-count">
            SHOWING <strong><?php echo esc_html( $start ); ?>–<?php echo esc_html( $end ); ?></strong> OF <strong><?php echo esc_html( number_format( $total ) ); ?></strong>
        </div>
        <div class="j5-active-filters">
            <?php foreach ( $active_chips as $chip ) : ?>
                <a href="<?php echo esc_url( $chip['remove_url'] ); ?>" class="j5-chip">
                    <?php echo esc_html( $chip['label'] ); ?> <span class="x">×</span>
                </a>
            <?php endforeach; ?>
            <?php if ( count( $active_chips ) > 1 ) :
                $base = strtok( $_SERVER['REQUEST_URI'], '?' );
            ?>
                <a href="<?php echo esc_url( $base ); ?>" class="j5-chip clear-all">Clear All <span class="x">×</span></a>
            <?php endif; ?>
        </div>
        <div class="j5-toolbar-actions">
            <form action="<?php echo esc_attr( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>" method="get" class="j5-sort-form">
                <?php foreach ( $_GET as $k => $v ) :
                    if ( in_array( $k, array( 'sort', 'page' ) ) ) continue;
                ?>
                    <input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>" />
                <?php endforeach; ?>
                <select name="sort" class="j5-sort-select" onchange="this.form.submit()">
                    <?php
                    $sort_opts = array(
                        'menu_order' => 'Sort: Featured',
                        'date'       => 'Newest',
                        'price'      => 'Price: Low to High',
                        'price-desc' => 'Price: High to Low',
                        'popularity' => 'Best Selling',
                        'rating'     => 'Top Rated',
                    );
                    $current_sort = sanitize_key( $_GET['sort'] ?? 'menu_order' );
                    foreach ( $sort_opts as $key => $lbl ) :
                    ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_sort, $key ); ?>><?php echo esc_html( $lbl ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form class="j5-perpage-form" method="get" action="">
                <?php foreach ( $_GET as $k => $v ) : if ( $k === 'per_page' ) continue; ?>
                    <input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( is_array( $v ) ? reset( $v ) : $v ); ?>" />
                <?php endforeach; ?>
                <label class="j5-perpage-label">Show
                    <select name="per_page" onchange="this.form.submit()">
                        <?php
                        $j5_pp = isset( $_GET['per_page'] ) ? (int) $_GET['per_page'] : 48;
                        foreach ( array( 24, 48, 72, -1 ) as $opt ) :
                            $label = ( $opt === -1 ) ? 'All' : $opt;
                        ?>
                            <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $j5_pp, $opt ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
            <div class="j5-view-toggle">
                <button class="active" data-j5-view="grid" title="Grid" aria-label="Grid view">▦</button>
                <button data-j5-view="list" title="List" aria-label="List view">☰</button>
            </div>
        </div>
    </div>
</div>

<!-- MAIN LAYOUT -->
<div class="j5-container j5-shop-layout">

    <?php j5_render_filter_sidebar(); ?>

    <div class="j5-shop-main">
        <?php if ( empty( $products ) ) : ?>
            <div class="j5-no-results">
                <div class="label">// NO MATCH</div>
                <h3>Nothing meets those filters.</h3>
                <p>Try loosening a constraint or clearing all filters.</p>
                <a href="<?php echo esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>" class="j5-btn j5-btn-primary">Clear All Filters →</a>
            </div>
        <?php else : ?>
            <div class="j5-prod-grid">
                <?php foreach ( $products as $i => $product ) :
                    j5_render_shop_product_card( $product );

                    // Inject a promo card every 8th slot on page 1 only
                    if ( $page === 1 && $i === 7 && count( $products ) > 10 ) : ?>
                        <article class="j5-promo-card">
                            <div>
                                <div class="j5-promo-label">// FEATURED COLLECTION</div>
                                <h3 class="j5-promo-title">HESCO<br/><span class="accent">PLATES.</span><br/>ON SALE.</h3>
                                <p class="j5-promo-desc">U.S.-made hard armor. NIJ IV certified. Limited pricing while inventory lasts.</p>
                            </div>
                            <a href="<?php echo esc_url( add_query_arg( 'brand', 'hesco', $j5_shop_url ) ); ?>" class="j5-promo-cta">Shop HESCO →</a>
                        </article>
                    <?php endif;
                endforeach; ?>
            </div>

            <!-- PAGINATION -->
            <?php if ( $max_pages > 1 ) : ?>
                <nav class="j5-pagination" aria-label="Shop pagination">
                    <?php
                    $base_url = strtok( $_SERVER['REQUEST_URI'], '?' );
                    $query_no_page = $_GET;
                    unset( $query_no_page['page'] );
                    $page_url = function( $p ) use ( $base_url, $query_no_page ) {
                        $q = $query_no_page;
                        if ( $p > 1 ) $q['page'] = $p;
                        return $base_url . ( empty( $q ) ? '' : '?' . http_build_query( $q ) );
                    };

                    if ( $page > 1 ) : ?>
                        <a href="<?php echo esc_url( $page_url( $page - 1 ) ); ?>" class="nav">← Prev</a>
                    <?php endif;

                    // Show: 1, 2, 3, ..., current-1, current, current+1, ..., max-1, max
                    $pages_to_show = array();
                    for ( $p = 1; $p <= $max_pages; $p++ ) {
                        if ( $p <= 2 || $p > $max_pages - 2 || abs( $p - $page ) <= 1 ) {
                            $pages_to_show[] = $p;
                        }
                    }
                    $prev = 0;
                    foreach ( $pages_to_show as $p ) :
                        if ( $p - $prev > 1 ) : ?>
                            <span class="ellipsis">…</span>
                        <?php endif;
                        if ( $p === $page ) : ?>
                            <span class="current"><?php echo esc_html( $p ); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url( $page_url( $p ) ); ?>"><?php echo esc_html( $p ); ?></a>
                        <?php endif;
                        $prev = $p;
                    endforeach;

                    if ( $page < $max_pages ) : ?>
                        <a href="<?php echo esc_url( $page_url( $page + 1 ) ); ?>" class="nav">Next →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- CATEGORY SPOTLIGHT -->
<?php
$spotlight_terms = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'number'     => 12,
    'orderby'    => 'count',
    'order'      => 'DESC',
) );
if ( ! empty( $spotlight_terms ) && ! is_wp_error( $spotlight_terms ) ) : ?>
<section class="j5-cat-spotlight">
    <div class="j5-container">
        <div class="j5-cat-spotlight-head">
            <div>
                <div class="label">EXPLORE MORE</div>
                <h3>Other <span class="accent">categories.</span></h3>
            </div>
        </div>
        <div class="j5-cat-chip-grid">
            <?php foreach ( $spotlight_terms as $term ) : ?>
                <a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="j5-cat-chip-tile">
                    <div class="name"><?php echo esc_html( $term->name ); ?></div>
                    <div class="count"><?php echo esc_html( $term->count ); ?> ITEMS</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- MINI FOOTER -->
<footer class="j5-site-footer j5-footer-mini-wrap">
    <div class="j5-container j5-footer-mini">
        <div>© <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> &middot; ALL RIGHTS RESERVED</div>
        <div>
            <a href="<?php echo esc_url( site_url( '/privacy-policy/' ) ); ?>">Privacy</a>
            <a href="<?php echo esc_url( site_url( '/terms-conditions-2/' ) ); ?>">Terms</a>
            <?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?><a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener">LE Portal →</a><?php endif; ?>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
