<?php
/**
 * J5 Shop & Single Product — Setup & Helpers
 *
 * Enqueues shop CSS+JS, provides the URL-based filter query builder,
 * helper renderers for product cards, filter sidebar, variant pills,
 * and registers a conditional single-product template_include hook
 * (staging-safe: only activates when ?j5_preview=1 is present).
 *
 * Included from the child theme's functions.php via:
 *   require_once get_stylesheet_directory() . '/inc/j5-shop-setup.php';
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================================
 *  1.  ASSET ENQUEUE — only on pages/products using our templates
 * ========================================================================== */

function j5_shop_enqueue_assets() {
    $should_load = false;

    // Load on the page using the J5 Shop template
    if ( is_page_template( 'template-j5-shop.php' ) ) {
        $should_load = true;
    }

    // Load on single product pages when ?j5_preview=1 is set (staging)
    if ( function_exists( 'is_product' ) && is_product() && isset( $_GET['j5_preview'] ) && $_GET['j5_preview'] === '1' ) {
        $should_load = true;
    }

    // Load on real shop/archive pages once we flip the switch (see Step 7 below)
    if ( defined( 'J5_SHOP_LIVE' ) && J5_SHOP_LIVE && ( is_shop() || is_product_taxonomy() || is_product() ) ) {
        $should_load = true;
    }

    if ( ! $should_load ) {
        return;
    }

    wp_enqueue_style(
        'j5-shop-fonts',
        'https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap',
        array(),
        null
    );

    /* Version = file mtime: every deployed change busts LiteSpeed/browser
     * caches automatically. No more manual bumps. J5-AUTOVER-1 */
    wp_enqueue_style(
        'j5-shop',
        get_stylesheet_directory_uri() . '/assets/css/j5-shop.css',
        array(),
        (string) filemtime( get_stylesheet_directory() . '/assets/css/j5-shop.css' )
    );

    // Force WooCommerce variation script on j5 product pages
    // (WC sometimes skips this enqueue when our custom template is in use)
    if ( function_exists( 'is_product' ) && is_product() ) {
        wp_enqueue_script( 'wc-add-to-cart-variation' );
    }

    wp_enqueue_script(
        'j5-shop',
        get_stylesheet_directory_uri() . '/assets/js/j5-shop.js',
        array( 'jquery' ),
        (string) filemtime( get_stylesheet_directory() . '/assets/js/j5-shop.js' ),
        true
    );

    // Pass cart/ajax URLs + admin-configured banner copy to JS.
    // Banner copy comes from J5 Apps -> Fulfillment (j5_fulfillment_types),
    // so label/note edits there take effect on the live banner without a
    // code change. JS keeps hardcoded fallbacks if this is absent.
    $j5_banner_copy = array();
    if ( function_exists( 'j5_fulfillment_types' ) ) {
        $j5_ftypes = j5_fulfillment_types();
        foreach ( array( 'in_stock', 'special', 'out' ) as $j5_fslug ) {
            if ( isset( $j5_ftypes[ $j5_fslug ] ) && is_array( $j5_ftypes[ $j5_fslug ] ) ) {
                $j5_banner_copy[ $j5_fslug ] = array(
                    'label' => isset( $j5_ftypes[ $j5_fslug ]['label'] ) ? (string) $j5_ftypes[ $j5_fslug ]['label'] : '',
                    'note'  => isset( $j5_ftypes[ $j5_fslug ]['note'] ) ? (string) $j5_ftypes[ $j5_fslug ]['note'] : '',
                );
            }
        }
    }

    wp_localize_script( 'j5-shop', 'j5Shop', array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'cartUrl'   => class_exists( 'WooCommerce' ) ? wc_get_cart_url() : '',
        'nonce'     => wp_create_nonce( 'j5_shop' ),
        'banner'    => $j5_banner_copy,
    ) );
}
add_action( 'wp_enqueue_scripts', 'j5_shop_enqueue_assets' );

/* ============================================================================
 *  2.  CONDITIONAL SINGLE-PRODUCT TEMPLATE — staging guard
 *      Loads our custom single-j5-product.php only when ?j5_preview=1 is in
 *      the URL. When ready to go live, define J5_PRODUCT_LIVE in wp-config.php
 *      or uncomment the line below, and our template takes over all products.
 * ========================================================================== */

function j5_conditional_product_template( $template ) {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return $template;
    }

    $should_override = false;

    if ( isset( $_GET['j5_preview'] ) && $_GET['j5_preview'] === '1' ) {
        $should_override = true;
    }

    if ( defined( 'J5_PRODUCT_LIVE' ) && J5_PRODUCT_LIVE ) {
        $should_override = true;
    }

    if ( $should_override ) {
        $custom = get_stylesheet_directory() . '/single-j5-product.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }

    return $template;
}
add_filter( 'template_include', 'j5_conditional_product_template', 9999 );

/* ============================================================================
 *  3.  PRODUCT QUERY BUILDER — reads URL params, returns wc_get_products() args
 * ========================================================================== */

function j5_build_product_query_args() {
    /* J5-AUTOSCOPE-START */
    // Auto-scope to the current category/tag when on a taxonomy archive,
    // unless URL params explicitly override. This makes archive-product.php
    // produce category-filtered results when used as a category template.
    if ( function_exists( 'is_product_category' ) && is_product_category() && empty( $_GET['category'] ) && empty( $_GET['all'] ) ) {
        $j5_auto_term = get_queried_object();
        if ( $j5_auto_term && ! empty( $j5_auto_term->slug ) ) {
            $_GET['category'] = $j5_auto_term->slug;
        }
    }
    if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
        $j5_auto_term = get_queried_object();
        if ( $j5_auto_term && ! empty( $j5_auto_term->slug ) ) {
            // Tags use a separate tax_query path; just set product_tag filter directly.
            if ( empty( $_GET['product_tag'] ) ) {
                $_GET['product_tag'] = $j5_auto_term->slug;
            }
        }
    }
    /* J5-AUTOSCOPE-END */
    $per_page = 48;
    if ( isset( $_GET['per_page'] ) ) {
        $requested = (int) $_GET['per_page'];
        if ( $requested === -1 ) {
            $per_page = 500;
        } elseif ( $requested > 0 && $requested <= 200 ) {
            $per_page = $requested;
        }
    }

    $args = array(
        'status'     => 'publish',
        'visibility' => 'catalog',
        'limit'      => $per_page,
        'paginate'   => true,
        'page'       => max( 1, absint( $_GET['page'] ?? 1 ) ),
    );

    // Sort
    $sort = sanitize_key( $_GET['sort'] ?? 'menu_order' );
    switch ( $sort ) {
        case 'date':        $args['orderby'] = 'date';       $args['order'] = 'DESC'; break;
        case 'price':       $args['orderby'] = 'price';      $args['order'] = 'ASC';  break;
        case 'price-desc':  $args['orderby'] = 'price';      $args['order'] = 'DESC'; break;
        case 'popularity':  $args['orderby'] = 'popularity'; break;
        case 'rating':      $args['orderby'] = 'rating';     break;
        default:            $args['orderby'] = 'menu_order';
    }

    // Category filter (product_cat slugs)
    if ( ! empty( $_GET['category'] ) ) {
        $args['category'] = array_map( 'sanitize_key', explode( ',', $_GET['category'] ) );
    }

    // Price filter
    if ( ! empty( $_GET['min_price'] ) ) $args['min_price'] = floatval( $_GET['min_price'] );
    if ( ! empty( $_GET['max_price'] ) ) $args['max_price'] = floatval( $_GET['max_price'] );

    // Attribute filters → tax_query
    // Slugs map URL params to ACTUAL taxonomies that exist on products.
    // pa_brand / pa_protection-level / pa_cut / pa_size were empty taxonomies;
    // the populated ones are pa_product-brands / pa_threat-level / pa_plate-cut / pa_plate-size.
    $tax_query = array();
    $attr_map  = array(
        'brand'      => 'pa_product-brands',
        'protection' => 'pa_threat-level',
        'cut'        => 'pa_plate-cut',
        'size'       => 'pa_plate-size',
        'curve'      => 'pa_plate-curve',
        'nij-cert'   => 'pa_nij-certified',
    );
    foreach ( $attr_map as $param => $tax ) {
        if ( ! empty( $_GET[ $param ] ) && taxonomy_exists( $tax ) ) {
            $tax_query[] = array(
                'taxonomy' => $tax,
                'field'    => 'slug',
                'terms'    => array_map( 'sanitize_key', explode( ',', $_GET[ $param ] ) ),
                'operator' => 'IN',
            );
        }
    }

    // Availability
    $availability = sanitize_key( $_GET['availability'] ?? '' );
    if ( $availability === 'in-stock' ) {
        $args['stock_status'] = 'instock';
    } elseif ( $availability === 'on-sale' ) {
        $sale_ids = wc_get_product_ids_on_sale();
        $args['include'] = ! empty( $sale_ids ) ? $sale_ids : array( 0 );
    } elseif ( $availability === 'new' ) {
        $args['date_created'] = '>' . strtotime( '-30 days' );
    }

    if ( ! empty( $tax_query ) ) {
        $args['tax_query'] = $tax_query;
    }

    // Rating
    if ( ! empty( $_GET['rating'] ) ) {
        $min_rating = max( 1, min( 5, absint( $_GET['rating'] ) ) );
        $args['meta_query'][] = array(
            'key'     => '_wc_average_rating',
            'value'   => $min_rating,
            'compare' => '>=',
            'type'    => 'DECIMAL(3,2)',
        );
    }


    /* J5-SHOP-SEARCH-PATCH-1-PHP */
    // Search term (handles ?s= from homepage pills, header search, etc.)
    // wc_get_products() honors 's' as a title+SKU search.
    if ( isset( $_GET['s'] ) ) {
        $s = trim( (string) $_GET['s'] );
        if ( $s !== '' ) {
            $args['s'] = sanitize_text_field( $s );
        }
    }
    /* END J5-SHOP-SEARCH-PATCH-1-PHP */
    return $args;
}

/* ============================================================================
 *  4.  ACTIVE FILTER CHIPS — parse URL, return display-ready array
 * ========================================================================== */

function j5_get_active_filters() {
    $chips = array();

    // Category chips
    if ( ! empty( $_GET['category'] ) ) {
        foreach ( explode( ',', $_GET['category'] ) as $slug ) {
            $term = get_term_by( 'slug', sanitize_key( $slug ), 'product_cat' );
            if ( $term ) {
                $chips[] = array(
                    'label'      => $term->name,
                    'remove_url' => j5_filter_remove_url( 'category', $slug ),
                );
            }
        }
    }

    // Attribute chips
    $attr_map = array(
        'brand'      => 'pa_brand',
        'protection' => 'pa_protection-level',
        'cut'        => 'pa_cut',
        'size'       => 'pa_size',
    );
    foreach ( $attr_map as $param => $tax ) {
        if ( ! empty( $_GET[ $param ] ) && taxonomy_exists( $tax ) ) {
            foreach ( explode( ',', $_GET[ $param ] ) as $slug ) {
                $term = get_term_by( 'slug', sanitize_key( $slug ), $tax );
                if ( $term ) {
                    $chips[] = array(
                        'label'      => $term->name,
                        'remove_url' => j5_filter_remove_url( $param, $slug ),
                    );
                }
            }
        }
    }

    // Price chip
    if ( ! empty( $_GET['min_price'] ) || ! empty( $_GET['max_price'] ) ) {
        $min = ! empty( $_GET['min_price'] ) ? '$' . number_format( floatval( $_GET['min_price'] ) ) : '$0';
        $max = ! empty( $_GET['max_price'] ) ? '$' . number_format( floatval( $_GET['max_price'] ) ) : 'Any';
        $chips[] = array(
            'label'      => $min . '–' . $max,
            'remove_url' => j5_filter_remove_url( array( 'min_price', 'max_price' ), null ),
        );
    }

    // Availability chip
    if ( ! empty( $_GET['availability'] ) ) {
        $avail_labels = array(
            'in-stock' => 'In Stock',
            'on-sale'  => 'On Sale',
            'new'      => 'New Arrivals',
        );
        $key = sanitize_key( $_GET['availability'] );
        if ( isset( $avail_labels[ $key ] ) ) {
            $chips[] = array(
                'label'      => $avail_labels[ $key ],
                'remove_url' => j5_filter_remove_url( 'availability', null ),
            );
        }
    }

    // Rating chip
    if ( ! empty( $_GET['rating'] ) ) {
        $rating = absint( $_GET['rating'] );
        $chips[] = array(
            'label'      => str_repeat( '★', $rating ) . ' & up',
            'remove_url' => j5_filter_remove_url( 'rating', null ),
        );
    }

    return $chips;
}

/**
 * Build a URL with a filter value removed.
 * @param string|array $param  Param name(s) to remove
 * @param string|null  $value  Specific value to remove from CSV list, or null to remove the whole param
 */
function j5_filter_remove_url( $param, $value = null ) {
    $base_url = strtok( $_SERVER['REQUEST_URI'], '?' );
    $query    = $_GET;

    $params = (array) $param;
    foreach ( $params as $p ) {
        if ( $value === null ) {
            unset( $query[ $p ] );
        } elseif ( isset( $query[ $p ] ) ) {
            $values = array_diff( explode( ',', $query[ $p ] ), array( $value ) );
            if ( empty( $values ) ) {
                unset( $query[ $p ] );
            } else {
                $query[ $p ] = implode( ',', $values );
            }
        }
    }

    unset( $query['page'] ); // reset pagination when filters change

    return $base_url . ( empty( $query ) ? '' : '?' . http_build_query( $query ) );
}

/**
 * Build a URL to toggle a filter value.
 */
function j5_filter_toggle_url( $param, $value, $multi = true ) {
    $base_url = strtok( $_SERVER['REQUEST_URI'], '?' );
    $query    = $_GET;

    if ( $multi ) {
        $current = ! empty( $query[ $param ] ) ? explode( ',', $query[ $param ] ) : array();
        if ( in_array( $value, $current ) ) {
            $current = array_diff( $current, array( $value ) );
        } else {
            $current[] = $value;
        }
        if ( empty( $current ) ) {
            unset( $query[ $param ] );
        } else {
            $query[ $param ] = implode( ',', $current );
        }
    } else {
        if ( isset( $query[ $param ] ) && $query[ $param ] === $value ) {
            unset( $query[ $param ] );
        } else {
            $query[ $param ] = $value;
        }
    }

    unset( $query['page'] );

    return $base_url . ( empty( $query ) ? '' : '?' . http_build_query( $query ) );
}

function j5_filter_is_active( $param, $value ) {
    if ( empty( $_GET[ $param ] ) ) return false;
    $current = explode( ',', $_GET[ $param ] );
    return in_array( $value, $current );
}

/* ============================================================================
 *  5.  RENDER — single product card (used in shop grid + related products)
 * ========================================================================== */

function j5_render_shop_product_card( $product, $compact = false ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return;

    $permalink  = $product->get_permalink();
    // Append ?j5_preview=1 on staging so clicks from /shop-v2/ land on previewed products
    if ( isset( $_GET['j5_preview'] ) || is_page_template( 'template-j5-shop.php' ) ) {
        $permalink = add_query_arg( 'j5_preview', '1', $permalink );
    }

    $name       = $product->get_name();
    $sku        = $product->get_sku() ? $product->get_sku() : '#' . $product->get_id();
    $price_html = $product->get_price_html();
    $image_id   = $product->get_image_id();
    $image_url  = $image_id
        ? wp_get_attachment_image_url( $image_id, 'medium_large' )
        : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src() : '' );

    $brand      = j5_get_product_brand_for_shop( $product );
    $rating     = (float) $product->get_average_rating();
    $review_cnt = (int) $product->get_review_count();

    // Badges
    $badges = array();
    if ( $product->is_on_sale() ) {
        $regular = floatval( $product->get_regular_price() );
        $sale    = floatval( $product->get_sale_price() );
        if ( $regular > 0 && $sale > 0 ) {
            $pct = round( ( ( $regular - $sale ) / $regular ) * 100 );
            $badges[] = array( 'class' => 'sale', 'label' => '−' . $pct . '%' );
        } else {
            $badges[] = array( 'class' => 'sale', 'label' => 'SALE' );
        }
    }
    if ( j5_is_product_new( $product ) ) {
        $badges[] = array( 'class' => '', 'label' => 'NEW' );
    }
    if ( $product->is_in_stock() ) {
        $badges[] = array( 'class' => 'stock', 'label' => '● IN STOCK' );
    }

    // Specs (from key attributes)
    $specs = j5_get_product_specs( $product );

    // Add to cart
    $add_text = $product->add_to_cart_text();
    $add_url  = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock()
        ? $product->add_to_cart_url()
        : $permalink;
    ?>
    <article class="j5-prod-card">
        <a href="<?php echo esc_url( $permalink ); ?>" class="j5-prod-img-wrap">
            <?php if ( ! empty( $badges ) ) : ?>
                <div class="j5-prod-badges">
                    <?php foreach ( $badges as $b ) : ?>
                        <span class="j5-prod-badge <?php echo esc_attr( $b['class'] ); ?>"><?php echo esc_html( $b['label'] ); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ( $image_url ) : ?>
                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
            <?php endif; ?>
        </a>
        <div class="j5-prod-body">
            <div class="j5-prod-brand-row">
                <?php
                $j5_cat_label = '';
                if ( isset( $product ) && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
                    $j5_cats = get_the_terms( $product->get_id(), 'product_cat' );
                    if ( $j5_cats && ! is_wp_error( $j5_cats ) ) {
                        $j5_primary = reset( $j5_cats );
                        if ( isset( $j5_primary->name ) ) {
                            $j5_cat_label = strtoupper( $j5_primary->name );
                        }
                    }
                }
                ?>
                <span class="j5-prod-brand"><?php echo esc_html( $j5_cat_label ?: 'J5 RESCUE' ); ?></span>
                <?php if ( $rating > 0 ) : ?>
                    <span class="j5-prod-rating">
                        <?php
                        $filled = (int) round( $rating );
                        echo esc_html( str_repeat( '★', $filled ) . str_repeat( '☆', 5 - $filled ) );
                        ?>
                        <?php if ( $review_cnt > 0 ) : ?><span class="count">(<?php echo esc_html( $review_cnt ); ?>)</span><?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( $permalink ); ?>" class="j5-prod-name"><?php echo esc_html( $name ); ?></a>
            <?php if ( ! empty( $specs ) ) : ?>
                <div class="j5-prod-specs">
                    <?php foreach ( array_slice( $specs, 0, 3 ) as $spec ) : ?>
                        <span class="j5-prod-spec"><?php echo esc_html( $spec ); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="j5-prod-price-row">
                <div class="j5-prod-price"><?php echo wp_kses_post( $price_html ); ?></div>
                <a href="<?php echo esc_url( $add_url ); ?>" class="j5-prod-add" <?php if ( $product->is_type( 'simple' ) ) : ?>data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" rel="nofollow"<?php endif; ?>>
                    <?php echo esc_html( $product->is_type( 'simple' ) && $product->is_purchasable() ? 'Add' : 'Options' ); ?>
                </a>
            </div>
            <div class="j5-prod-sku">SKU: <?php echo esc_html( strtoupper( $sku ) ); ?></div>
        </div>
    </article>
    <?php
}

function j5_get_product_brand_for_shop( $product ) {
    if ( function_exists( 'j5_get_product_brand' ) ) {
        return j5_get_product_brand( $product );
    }
    foreach ( array( 'product_brand', 'yith_product_brand', 'pwb-brand' ) as $tax ) {
        if ( taxonomy_exists( $tax ) ) {
            $terms = get_the_terms( $product->get_id(), $tax );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                return strtoupper( $terms[0]->name );
            }
        }
    }
    $attr = $product->get_attribute( 'pa_brand' );
    if ( $attr ) return strtoupper( $attr );
    $tags = get_the_terms( $product->get_id(), 'product_tag' );
    if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) return strtoupper( $tags[0]->name );
    return '';
}

function j5_is_product_new( $product ) {
    $created = $product->get_date_created();
    if ( ! $created ) return false;
    return ( strtotime( $created ) > strtotime( '-30 days' ) );
}

function j5_get_product_specs( $product ) {
    $specs = array();
    $attrs = array( 'pa_protection-level', 'pa_cut', 'pa_size', 'pa_material', 'pa_rated-for' );
    foreach ( $attrs as $tax ) {
        if ( taxonomy_exists( $tax ) ) {
            $terms = get_the_terms( $product->get_id(), $tax );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $specs[] = strtoupper( $terms[0]->name );
            }
        }
    }
    return $specs;
}

/* ============================================================================
 *  6.  FILTER SIDEBAR RENDERING
 * ========================================================================== */

function j5_render_filter_sidebar() {
    ?>
    <aside class="j5-filters">
        <?php
        j5_render_filter_category();
        j5_render_filter_taxonomy_checkbox( 'brand', 'pa_product-brands', 'Brand' );
        j5_render_filter_price();
        j5_render_filter_taxonomy_pill( 'protection', 'pa_threat-level', 'Protection Level' );
        j5_render_filter_taxonomy_pill( 'nij-cert', 'pa_nij-certified', 'NIJ Certified' );
        j5_render_filter_taxonomy_pill( 'cut', 'pa_plate-cut', 'Cut' );
        j5_render_filter_taxonomy_pill( 'size', 'pa_plate-size', 'Size' );
        j5_render_filter_taxonomy_pill( 'curve', 'pa_plate-curve', 'Curve' );
        j5_render_filter_availability();
        j5_render_filter_rating();
        ?>
        <div class="j5-agency-cta">
            <h5>AGENCY PRICING</h5>
            <p>LE/Gov customers see net pricing, volume tiers, and spec sheets after sign-in.</p>
            <?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?><a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener">Agency Portal →</a><?php endif; ?>
        </div>
    </aside>
    <?php
}

function j5_render_filter_category() {
    $terms = get_terms( array(
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => true,
    ) );
    if ( empty( $terms ) || is_wp_error( $terms ) ) return;
    ?>
    <div class="j5-filter-group">
        <div class="j5-filter-head"><h4>Category</h4><span class="toggle">−</span></div>
        <ul class="j5-filter-list">
            <?php foreach ( $terms as $term ) :
                $children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => true ) );
                $has_children = ! empty( $children ) && ! is_wp_error( $children );
                $is_active = j5_filter_is_active( 'category', $term->slug );
            ?>
                <li class="<?php echo $has_children ? 'has-children' : ''; ?>">
                    <label>
                        <input type="checkbox" data-filter-url="<?php echo esc_attr( j5_filter_toggle_url( 'category', $term->slug ) ); ?>" <?php checked( $is_active ); ?> />
                        <?php echo esc_html( $term->name ); ?>
                    </label>
                    <?php if ( $has_children ) : ?>
                        <ul class="children">
                            <?php foreach ( $children as $child ) :
                                $child_active = j5_filter_is_active( 'category', $child->slug );
                            ?>
                                <li>
                                    <label>
                                        <input type="checkbox" data-filter-url="<?php echo esc_attr( j5_filter_toggle_url( 'category', $child->slug ) ); ?>" <?php checked( $child_active ); ?> />
                                        <?php echo esc_html( $child->name ); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

function j5_render_filter_taxonomy_checkbox( $param, $taxonomy, $label ) {
    if ( ! taxonomy_exists( $taxonomy ) ) return;
    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
    if ( empty( $terms ) || is_wp_error( $terms ) ) return;
    ?>
    <div class="j5-filter-group">
        <div class="j5-filter-head"><h4><?php echo esc_html( $label ); ?></h4><span class="toggle">−</span></div>
        <ul class="j5-filter-list">
            <?php foreach ( $terms as $term ) :
                $is_active = j5_filter_is_active( $param, $term->slug );
            ?>
                <li>
                    <label>
                        <input type="checkbox" data-filter-url="<?php echo esc_attr( j5_filter_toggle_url( $param, $term->slug ) ); ?>" <?php checked( $is_active ); ?> />
                        <?php echo esc_html( $term->name ); ?>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

function j5_render_filter_taxonomy_pill( $param, $taxonomy, $label ) {
    if ( ! taxonomy_exists( $taxonomy ) ) return;
    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
    if ( empty( $terms ) || is_wp_error( $terms ) ) return;
    ?>
    <div class="j5-filter-group">
        <div class="j5-filter-head"><h4><?php echo esc_html( $label ); ?></h4><span class="toggle">−</span></div>
        <div class="j5-pill-row">
            <?php foreach ( $terms as $term ) :
                $is_active = j5_filter_is_active( $param, $term->slug );
            ?>
                <a href="<?php echo esc_url( j5_filter_toggle_url( $param, $term->slug ) ); ?>" class="j5-pill <?php echo $is_active ? 'active' : ''; ?>">
                    <?php echo esc_html( $term->name ); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function j5_render_filter_price() {
    $min = isset( $_GET['min_price'] ) ? floatval( $_GET['min_price'] ) : '';
    $max = isset( $_GET['max_price'] ) ? floatval( $_GET['max_price'] ) : '';
    $base_url = strtok( $_SERVER['REQUEST_URI'], '?' );
    ?>
    <div class="j5-filter-group">
        <div class="j5-filter-head"><h4>Price</h4><span class="toggle">−</span></div>
        <form class="j5-price-form" action="<?php echo esc_attr( $base_url ); ?>" method="get">
            <?php foreach ( $_GET as $key => $val ) :
                if ( in_array( $key, array( 'min_price', 'max_price', 'page' ) ) ) continue;
            ?>
                <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>" />
            <?php endforeach; ?>
            <div class="j5-price-inputs">
                <input type="number" name="min_price" value="<?php echo esc_attr( $min ); ?>" placeholder="Min" />
                <span class="dash">—</span>
                <input type="number" name="max_price" value="<?php echo esc_attr( $max ); ?>" placeholder="Max" />
            </div>
            <div class="j5-price-quick">
                <?php
                $ranges = array(
                    array( 'label' => 'Under $50',    'min' => '',    'max' => '50'   ),
                    array( 'label' => '$50–$200',     'min' => '50',  'max' => '200'  ),
                    array( 'label' => '$200–$500',    'min' => '200', 'max' => '500'  ),
                    array( 'label' => '$500–$1.5K',   'min' => '500', 'max' => '1500' ),
                    array( 'label' => '$1.5K+',       'min' => '1500','max' => ''     ),
                );
                foreach ( $ranges as $r ) :
                    $active = ( (string) $min === $r['min'] && (string) $max === $r['max'] );
                    $query = $_GET;
                    unset( $query['min_price'], $query['max_price'], $query['page'] );
                    if ( $r['min'] !== '' ) $query['min_price'] = $r['min'];
                    if ( $r['max'] !== '' ) $query['max_price'] = $r['max'];
                    $url = $base_url . ( empty( $query ) ? '' : '?' . http_build_query( $query ) );
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $active ? 'active' : ''; ?>"><?php echo esc_html( $r['label'] ); ?></a>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="j5-price-apply">Apply</button>
        </form>
    </div>
    <?php
}

function j5_render_filter_availability() {
    $current = sanitize_key( $_GET['availability'] ?? '' );
    $options = array(
        'in-stock' => 'In Stock',
        'on-sale'  => 'On Sale',
        'new'      => 'New Arrivals',
    );
    ?>
    <div class="j5-filter-group">
        <div class="j5-filter-head"><h4>Availability</h4><span class="toggle">−</span></div>
        <div class="j5-pill-row">
            <?php foreach ( $options as $key => $lbl ) :
                $url = j5_filter_toggle_url( 'availability', $key, false );
                $active = ( $current === $key );
            ?>
                <a href="<?php echo esc_url( $url ); ?>" class="j5-pill <?php echo $active ? 'active' : ''; ?>"><?php echo esc_html( $lbl ); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function j5_render_filter_rating() {
    $current = absint( $_GET['rating'] ?? 0 );
    ?>
    <div class="j5-filter-group">
        <div class="j5-filter-head"><h4>Rating</h4><span class="toggle">−</span></div>
        <ul class="j5-filter-list j5-rating-list">
            <?php for ( $i = 5; $i >= 3; $i-- ) :
                $url = j5_filter_toggle_url( 'rating', $i, false );
                $checked = ( $current === $i );
            ?>
                <li>
                    <label>
                        <input type="checkbox" data-filter-url="<?php echo esc_attr( $url ); ?>" <?php checked( $checked ); ?> />
                        <span class="stars"><?php echo str_repeat( '★', $i ) . str_repeat( '☆', 5 - $i ); ?></span>
                        &amp; up
                    </label>
                </li>
            <?php endfor; ?>
        </ul>
    </div>
    <?php
}

/* ============================================================================
 *  7.  INSTRUCTIONS FOR GOING LIVE
 * ============================================================================
 *
 *  Staging URLs:
 *    /shop-v2/       → J5 Shop template (created as a regular WP page)
 *    /product-url/?j5_preview=1 → J5 single product template
 *
 *  To flip the shop live (take over default /shop/):
 *    1. Copy template-j5-shop.php → woocommerce/archive-product.php
 *       (WooCommerce hierarchy picks it up automatically for shop + categories)
 *    2. Add to wp-config.php:  define( 'J5_SHOP_LIVE', true );
 *
 *  To flip single products live:
 *    1. Add to wp-config.php:  define( 'J5_PRODUCT_LIVE', true );
 *       (this makes the template_include filter active for all products,
 *        no more ?j5_preview=1 required)
 *
 *  Instant rollback: remove the define() line from wp-config.php.
 * ========================================================================== */

/* ============================================================================
 *  ELEMENTOR THEME BUILDER OVERRIDE — disable Elementor's header/footer/
 *  single-product theme-builder locations when our J5 template is active.
 *  Uses Elementor Pro's official filter, no hacks.
 * ========================================================================== */
function j5_disable_elementor_theme_builder_on_j5_templates( $should_print ) {
    // Disable on single product previews
    if ( function_exists( 'is_product' ) && is_product() ) {
        if ( isset( $_GET['j5_preview'] ) && $_GET['j5_preview'] === '1' ) {
            return false;
        }
        if ( defined( 'J5_PRODUCT_LIVE' ) && J5_PRODUCT_LIVE ) {
            return false;
        }
    }
    // Disable on our shop-v2 staging page
    if ( is_page_template( 'template-j5-shop.php' ) ) {
        return false;
    }
    if ( defined( 'J5_SHOP_LIVE' ) && J5_SHOP_LIVE && ( is_shop() || is_product_taxonomy() ) ) {
        return false;
    }
    return $should_print;
}
add_filter( 'elementor/theme/should_do_location', 'j5_disable_elementor_theme_builder_on_j5_templates' );

/* ============================================================================
 *  Per-page dropdown handler (batch 3B 2026-04-19 — strict scoping)
 *  Only fires on the frontend main query when the j5 shop template is used.
 * ========================================================================== */
function j5_apply_per_page( $q ) {
    // Skip admin and REST immediately
    if ( is_admin() ) return;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
    if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) return;
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
    if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;
    // Only main query
    if ( ! $q->is_main_query() ) return;
    // Only if per_page is actually set by user
    if ( ! isset( $_GET['per_page'] ) ) return;
    // Only on pages using our j5 shop template
    if ( ! is_page_template( 'template-j5-shop.php' ) ) return;

    $pp = (int) $_GET['per_page'];
    if ( $pp === -1 ) {
        $q->set( 'posts_per_page', 500 );
    } elseif ( $pp > 0 && $pp <= 200 ) {
        $q->set( 'posts_per_page', $pp );
    }
}
add_action( 'pre_get_posts', 'j5_apply_per_page' );
