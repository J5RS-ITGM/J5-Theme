<?php
/**
 * WooCommerce primary archive template — J5 Shop (Odoo Style)
 *
 * Migrated from theme-root template-j5-shop.php on 2026-04-21. Now serves as
 * the default for /shop/, /product-category/*, /product-tag/*, and any other
 * product archive URL. The original template-j5-shop.php is preserved at
 * theme root for the /shop-v2/ preview page and can still be assigned via
 * Page Attributes -> Template.
 *
 * Uses site chrome (get_header / get_footer). The previous standalone HTML
 * shell (util bar, main header, mini footer) has been removed; those now
 * come from header.php and footer.php.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Preserve the body class `j5-shop-template` across every WooCommerce
 * archive view so the existing scoped CSS (selectors under
 * `body.j5-shop-template`) keeps matching after the chrome migration.
 */
add_filter( 'body_class', function ( $classes ) {
	if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() || is_post_type_archive( 'product' ) ) ) {
		$classes[] = 'j5-shop-template';
	}
	return $classes;
} );

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

get_header();
?>

<div class="j5-shop">


<?php /* J5-ARCHIVE-RENDER-HERO-START — moved to j5-archive-render.php */ ?>
<?php /* J5-ARCHIVE-RENDER-HERO-END */ ?>

<?php /* J5-DYNAMIC-TITLE-START */ ?>
<?php
// Resolve dynamic title and breadcrumb based on queried object.
$j5_page_title = 'SHOP ALL';
$j5_crumb_label = 'Shop All';
$j5_is_category_or_tag = false;
if ( function_exists( 'is_product_category' ) && is_product_category() ) {
    $j5_term = get_queried_object();
    if ( $j5_term && ! empty( $j5_term->name ) ) {
        /* Term names are stored entity-encoded ("Lights &amp; Lasers");
         * decode before transforming so strtoupper doesn't mangle the
         * entity and esc_html doesn't double-encode. J5-ENTITY-FIX-1 */
        $j5_term_name   = wp_specialchars_decode( $j5_term->name, ENT_QUOTES );
        $j5_page_title  = strtoupper( $j5_term_name );
        $j5_crumb_label = $j5_term_name;
        $j5_is_category_or_tag = true;
    }
} elseif ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
    $j5_term = get_queried_object();
    if ( $j5_term && ! empty( $j5_term->name ) ) {
        $j5_term_name   = wp_specialchars_decode( $j5_term->name, ENT_QUOTES );
        $j5_page_title  = strtoupper( $j5_term_name );
        $j5_crumb_label = $j5_term_name;
        $j5_is_category_or_tag = true;
    }
}
?>
<?php /* J5-DYNAMIC-TITLE-END */ ?>
<!-- SHOP HERO -->
<section class="j5-shop-hero">
    <div class="j5-container j5-shop-hero-inner">
        <div>
            <div class="j5-crumbs">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
                <span class="sep">/</span>
                <a href="<?php echo esc_url( $j5_shop_url ); ?>">Catalog</a>
                <span class="sep">/</span>
                <span class="current"><?php echo esc_html( $j5_crumb_label ); ?></span>
            </div>
            <h1 class="j5-shop-title"><?php echo esc_html( $j5_page_title ); ?></h1>
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

<?php /* J5-CAT-CONTENT-INTRO-START — above-grid editable category intro */ ?>
<?php
if ( function_exists( 'j5_render_category_intro' ) ) {
	j5_render_category_intro( 'Filter by brand, protection level, price, or agency fitment. No drop-ship surprises.' );
}
?>
<?php /* J5-CAT-CONTENT-INTRO-END */ ?>

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
        <div class="j5-result-count"><?php /* product count removed per J5 preference */ ?></div>
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
            <?php /* J5-ARCHIVE-SEARCH-START */ ?>
            <form action="<?php echo esc_attr( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>" method="get" class="j5-archive-search" role="search">
                <?php
                // Preserve existing filters except 's', 'page', and 'all' (we rebuild those here)
                foreach ( $_GET as $j5_k => $j5_v ) {
                    if ( in_array( $j5_k, array( 's', 'page', 'all' ) ) ) { continue; }
                    if ( is_array( $j5_v ) ) { $j5_v = reset( $j5_v ); }
                    echo '<input type="hidden" name="' . esc_attr( $j5_k ) . '" value="' . esc_attr( $j5_v ) . '" />';
                }
                $j5_search_value = isset( $_GET['s'] ) ? trim( (string) $_GET['s'] ) : '';
                $j5_in_category  = function_exists( 'is_product_category' ) && is_product_category();
                $j5_search_all   = ! empty( $_GET['all'] );
                ?>
                <input type="text" name="s" class="j5-archive-search__input" placeholder="<?php echo $j5_in_category && ! $j5_search_all ? 'Search this category...' : 'Search all products...'; ?>" value="<?php echo esc_attr( $j5_search_value ); ?>" autocomplete="off" />
                <?php if ( $j5_in_category ) : ?>
                    <label class="j5-archive-search__toggle" title="Search all products instead of just this category">
                        <input type="checkbox" name="all" value="1" <?php checked( $j5_search_all ); ?> onchange="this.form.submit()" />
                        <span class="j5-archive-search__toggle-label">All</span>
                    </label>
                <?php endif; ?>
                <button type="submit" class="j5-archive-search__btn" aria-label="Search">
                    <svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="5"/><line x1="12" y1="12" x2="17" y2="17" stroke-linecap="round"/></svg>
                </button>
            </form>
            <?php /* J5-ARCHIVE-SEARCH-END */ ?>

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

<?php /* J5-CAT-CONTENT-GUIDE-START — below-grid editable buyer's guide */ ?>
<?php
if ( function_exists( 'j5_render_category_guide' ) ) {
	j5_render_category_guide();
}
?>
<?php /* J5-CAT-CONTENT-GUIDE-END */ ?>

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
                    <div class="name"><?php echo esc_html( wp_specialchars_decode( $term->name, ENT_QUOTES ) ); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


</div><!-- .j5-shop -->

<?php
get_footer();

