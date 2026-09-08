<?php
/**
 * Single Product Template — J5 Rescue (Odoo Style)
 *
 * Standalone full-page template for single products. Does NOT call
 * get_header()/get_footer() — renders its own shell to stay isolated
 * from Astra + Elementor styling.
 *
 * Loaded conditionally via template_include filter in j5-shop-setup.php:
 *   - Active when URL has ?j5_preview=1
 *   - OR when J5_PRODUCT_LIVE constant is defined in wp-config.php
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Ensure we're in the loop
global $post;
if ( ! $post ) {
    $post = get_queried_object();
}

$product = wc_get_product( $post->ID );
if ( ! $product ) {
    wp_redirect( home_url() );
    exit;
}

$preview_query = isset( $_GET['j5_preview'] ) ? '?j5_preview=1' : '';

// Header helpers (same as shop)
$j5_has_logo      = function_exists( 'has_custom_logo' ) && has_custom_logo();
$j5_cart_url      = wc_get_cart_url();
$j5_cart_count    = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
$j5_account_url   = wc_get_page_permalink( 'myaccount' );
$j5_account_label = is_user_logged_in() ? 'Account' : 'Login';
$j5_shop_url      = wc_get_page_permalink( 'shop' );

// Product info
$name           = $product->get_name();
$sku            = $product->get_sku() ? $product->get_sku() : '#' . $product->get_id();
$price_html     = $product->get_price_html();
$description    = $product->get_description();
$short_desc     = $product->get_short_description();
$rating         = (float) $product->get_average_rating();
$review_cnt     = (int) $product->get_review_count();
$brand          = j5_get_product_brand_for_shop( $product );
$gallery_ids    = $product->get_gallery_image_ids();
$main_image_id  = $product->get_image_id();
$all_image_ids  = array_merge( array( $main_image_id ), $gallery_ids );
$all_image_ids  = array_filter( array_unique( $all_image_ids ) );

// Specs
$attr_display = array();
foreach ( $product->get_attributes() as $attr ) {
    if ( $attr->get_visible() && ! $attr->get_variation() ) {
        $attr_display[] = array(
            'label' => wc_attribute_label( $attr->get_name() ),
            'value' => $product->get_attribute( $attr->get_name() ),
        );
    }
}

// Categories for breadcrumb
$product_cats = get_the_terms( $product->get_id(), 'product_cat' );
$primary_cat  = ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ? $product_cats[0] : null;

// Related products
$related_ids = wc_get_related_products( $product->get_id(), 4 );
$related_products = array_filter( array_map( 'wc_get_product', $related_ids ) );

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<?php wp_head(); ?>
</head>
<body <?php body_class( 'j5-product-template' ); ?>>
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

<!-- PRODUCT DETAIL -->
<main class="j5-product-main">
    <div class="j5-container">

        <!-- Breadcrumb -->
        <nav class="j5-crumbs" aria-label="Breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
            <span class="sep">/</span>
            <a href="<?php echo esc_url( $j5_shop_url ); ?>">Shop</a>
            <?php if ( $primary_cat ) : ?>
                <span class="sep">/</span>
                <a href="<?php echo esc_url( get_term_link( $primary_cat ) ); ?>"><?php echo esc_html( $primary_cat->name ); ?></a>
            <?php endif; ?>
            <span class="sep">/</span>
            <span class="current"><?php echo esc_html( $name ); ?></span>
        </nav>

        <div class="j5-product-grid">

            <!-- GALLERY -->
            <div class="j5-product-gallery">
                <?php if ( ! empty( $all_image_ids ) ) : ?>
                    <div class="j5-gallery-main">
                        <?php $first_id = reset( $all_image_ids ); ?>
                        <img src="<?php echo esc_url( wp_get_attachment_image_url( $first_id, 'full' ) ); ?>" alt="<?php echo esc_attr( $name ); ?>" id="j5-gallery-main-img" />

                        <?php if ( $product->is_on_sale() ) :
                            $regular = floatval( $product->get_regular_price() );
                            $sale    = floatval( $product->get_sale_price() );
                            $pct     = ( $regular > 0 && $sale > 0 ) ? round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;
                        ?>
                            <span class="j5-gallery-badge">−<?php echo esc_html( $pct ); ?>% OFF</span>
                        <?php endif; ?>
                    </div>
                    <?php if ( count( $all_image_ids ) > 1 ) : ?>
                        <div class="j5-gallery-thumbs">
                            <?php foreach ( $all_image_ids as $i => $image_id ) :
                                $thumb = wp_get_attachment_image_url( $image_id, 'thumbnail' );
                                $full  = wp_get_attachment_image_url( $image_id, 'full' );
                                if ( ! $thumb ) continue;
                            ?>
                                <button type="button" class="j5-gallery-thumb <?php echo $i === 0 ? 'active' : ''; ?>" data-full="<?php echo esc_attr( $full ); ?>">
                                    <img src="<?php echo esc_url( $thumb ); ?>" alt="" />
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="j5-gallery-main">
                        <img src="<?php echo esc_url( wc_placeholder_img_src() ); ?>" alt="" />
                    </div>
                <?php endif; ?>
            </div>

            <!-- INFO -->
            <div class="j5-product-info">

                <?php if ( $brand ) : ?>
                    <div class="j5-product-brand"><?php echo esc_html( $brand ); ?></div>
                <?php endif; ?>

                <h1 class="j5-product-title"><?php echo esc_html( $name ); ?></h1>

                <div class="j5-product-meta-row">
                    <?php if ( $rating > 0 ) : ?>
                        <span class="j5-product-rating">
                            <?php
                            $filled = (int) round( $rating );
                            echo esc_html( str_repeat( '★', $filled ) . str_repeat( '☆', 5 - $filled ) );
                            ?>
                            <span class="count"><?php echo esc_html( number_format( $rating, 1 ) ); ?> (<?php echo esc_html( $review_cnt ); ?> reviews)</span>
                        </span>
                    <?php endif; ?>
                    <span class="j5-product-sku">SKU: <?php echo esc_html( strtoupper( $sku ) ); ?></span>
                </div>

                <?php if ( $short_desc ) : ?>
                    <div class="j5-product-short-desc"><?php echo wp_kses_post( $short_desc ); ?></div>
                <?php endif; ?>

                <div class="j5-product-price"><?php echo wp_kses_post( $price_html ); ?></div>

                <!-- STOCK INDICATOR -->
                <div class="j5-product-stock">
                    <?php if ( $product->is_in_stock() ) :
                        $stock_qty = $product->get_stock_quantity();
                    ?>
                        <span class="dot in"></span>
                        <span class="label">IN STOCK</span>
                        <?php if ( $stock_qty && $stock_qty <= 10 ) : ?>
                            <span class="qty">only <?php echo esc_html( $stock_qty ); ?> left</span>
                        <?php elseif ( $stock_qty ) : ?>
                            <span class="qty"><?php echo esc_html( $stock_qty ); ?>+ available</span>
                        <?php else : ?>
                            <span class="qty">ready to ship</span>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="dot out"></span>
                        <span class="label">OUT OF STOCK</span>
                        <span class="qty">contact us for ETA</span>
                    <?php endif; ?>
                </div>

                <!-- VARIANT PILLS (for variable products) -->
                <?php if ( $product->is_type( 'variable' ) ) :
                    $attributes = $product->get_variation_attributes();
                    $available_variations = $product->get_available_variations();
                ?>
                    <form class="variations_form cart j5-variations-form"
                          action="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
                          method="post" enctype='multipart/form-data'
                          data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"
                          data-product_variations="<?php echo esc_attr( wp_json_encode( $available_variations ) ); ?>">

                        <?php foreach ( $attributes as $attribute_name => $options ) :
                            $current_value = isset( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] )
                                ? $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ]
                                : $product->get_variation_default_attribute( $attribute_name );
                        ?>
                            <div class="j5-variant-group">
                                <label class="j5-variant-label"><?php echo esc_html( wc_attribute_label( $attribute_name ) ); ?></label>
                                <div class="j5-variant-pills variations" data-attribute="<?php echo esc_attr( 'attribute_' . sanitize_title( $attribute_name ) ); ?>">
                                    <?php
                                    // Hidden select for Woo's variation JS
                                    echo '<select name="attribute_' . esc_attr( sanitize_title( $attribute_name ) ) . '" data-attribute_name="attribute_' . esc_attr( sanitize_title( $attribute_name ) ) . '" style="display:none">';
                                    echo '<option value="">' . esc_html__( 'Choose', 'woocommerce' ) . '</option>';

                                    if ( taxonomy_is_product_attribute( $attribute_name ) ) {
                                        $terms = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'all' ) );
                                        foreach ( $terms as $term ) {
                                            if ( in_array( $term->slug, $options, true ) ) {
                                                echo '<option value="' . esc_attr( $term->slug ) . '" ' . selected( sanitize_title( $current_value ), $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>';
                                            }
                                        }
                                    } else {
                                        foreach ( $options as $option ) {
                                            echo '<option value="' . esc_attr( $option ) . '" ' . selected( sanitize_title( $current_value ), sanitize_title( $option ), false ) . '>' . esc_html( $option ) . '</option>';
                                        }
                                    }
                                    echo '</select>';

                                    // Visible pill buttons
                                    if ( taxonomy_is_product_attribute( $attribute_name ) ) {
                                        $terms = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'all' ) );
                                        foreach ( $terms as $term ) {
                                            if ( in_array( $term->slug, $options, true ) ) {
                                                $is_selected = sanitize_title( $current_value ) === $term->slug;
                                                echo '<button type="button" class="j5-variant-pill ' . ( $is_selected ? 'active' : '' ) . '" data-value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</button>';
                                            }
                                        }
                                    } else {
                                        foreach ( $options as $option ) {
                                            $is_selected = sanitize_title( $current_value ) === sanitize_title( $option );
                                            echo '<button type="button" class="j5-variant-pill ' . ( $is_selected ? 'active' : '' ) . '" data-value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</button>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ( count( $attributes ) > 1 ) : ?>
                            <a href="#" class="reset_variations" style="display:none;">Reset</a>
                        <?php endif; ?>

                        <div class="single_variation_wrap">
                            <div class="woocommerce-variation single_variation"></div>
                            <div class="variations_button j5-add-to-cart-row">
                                <div class="j5-qty-wrap">
                                    <button type="button" class="j5-qty-btn minus" aria-label="Decrease">−</button>
                                    <input type="number" class="j5-qty-input" name="quantity" value="1" min="1" step="1" />
                                    <button type="button" class="j5-qty-btn plus" aria-label="Increase">+</button>
                                </div>
                                <button type="submit" class="j5-add-to-cart-btn single_add_to_cart_button">Add to Cart →</button>
                            </div>
                            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" />
                            <input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>" />
                            <input type="hidden" name="variation_id" class="variation_id" value="0" />
                        </div>
                    </form>
                <?php elseif ( $product->is_type( 'simple' ) ) : ?>
                    <form class="cart j5-simple-form" action="<?php echo esc_url( $product->add_to_cart_url() ); ?>" method="post">
                        <div class="j5-add-to-cart-row">
                            <div class="j5-qty-wrap">
                                <button type="button" class="j5-qty-btn minus" aria-label="Decrease">−</button>
                                <input type="number" class="j5-qty-input" name="quantity" value="1" min="1" step="1" />
                                <button type="button" class="j5-qty-btn plus" aria-label="Increase">+</button>
                            </div>
                            <button type="submit" class="j5-add-to-cart-btn" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">Add to Cart →</button>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Trust signals -->
                <div class="j5-product-trust">
                    <div class="trust-item">
                        <span class="icon">✈</span>
                        <div><strong>Ships Daily</strong><span>3PM CT cut-off for same-day</span></div>
                    </div>
                    <?php if ( function_exists('has_term') && has_term( 'life-safety-guarantee', 'product_tag' ) ) : ?>
                    <div class="trust-item">
                        <span class="icon">⚕</span>
                        <div><strong>Life Safety Guarantee</strong><span>5-year replacement coverage</span></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- LE Portal callout -->
                <div class="j5-product-agency">
                    <div class="label">LE / FIRST RESPONDER / AGENCY ORDERING</div>
                    <p>Get net agency pricing, volume discounts, and spec sheets through the operations portal.</p>
                    <?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?><a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener">Enter Portal →</a><?php endif; ?>
                </div>

            </div>
        </div>

        <!-- TABS: Description / Specs / Reviews / Shipping -->
        <section class="j5-product-tabs">
            <div class="j5-tabs-nav">
                <button class="j5-tab-btn active" data-tab="description">Description</button>
                <button class="j5-tab-btn" data-tab="specs">Specifications</button>
                <button class="j5-tab-btn" data-tab="reviews">Reviews <?php if ( $review_cnt > 0 ) echo '<span class="count">(' . esc_html( $review_cnt ) . ')</span>'; ?></button>
                <button class="j5-tab-btn" data-tab="shipping">Shipping &amp; Returns</button>
            </div>

            <div class="j5-tab-panels">

                <div class="j5-tab-panel active" data-panel="description">
                    <?php echo j5_render_product_description( $description ); ?>
                </div>

                <div class="j5-tab-panel" data-panel="specs">
                    <?php
                    $j5_specs_extra = function_exists( 'j5_render_product_specs' ) ? j5_render_product_specs( $product ) : '';
                    if ( ! empty( $attr_display ) ) :
                    ?>
                        <table class="j5-specs-table">
                            <?php foreach ( $attr_display as $attr ) : ?>
                                <tr>
                                    <th><?php echo esc_html( $attr['label'] ); ?></th>
                                    <td><?php echo wp_kses_post( $attr['value'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th>SKU</th>
                                <td><?php echo esc_html( strtoupper( $sku ) ); ?></td>
                            </tr>
                            <?php if ( $product->get_weight() ) : ?>
                                <tr>
                                    <th>Weight</th>
                                    <td><?php echo esc_html( $product->get_weight() . ' ' . get_option( 'woocommerce_weight_unit' ) ); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php
                            $dims = $product->get_dimensions( false );
                            if ( ! empty( array_filter( $dims ) ) ) :
                            ?>
                                <tr>
                                    <th>Dimensions</th>
                                    <td><?php echo esc_html( wc_format_dimensions( $dims ) ); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    <?php elseif ( $j5_specs_extra === '' ) : ?>
                        <p class="j5-tab-empty">No additional specifications listed.</p>
                    <?php endif; ?>
                    <?php if ( $j5_specs_extra !== '' ) { echo $j5_specs_extra; } // already wp_kses_post()'d in renderer ?>
                </div>

                <div class="j5-tab-panel" data-panel="reviews">
                    <?php
                    // Use WooCommerce's native comments_template for reviews
                    if ( comments_open() ) {
                        comments_template();
                    } else {
                        echo '<p class="j5-tab-empty">Reviews are closed for this product.</p>';
                    }
                    ?>
                </div>

                <div class="j5-tab-panel" data-panel="shipping">
                    <div class="j5-shipping-content">
                        <h4>Standard Shipping</h4>
                        <p>Orders placed before 3:00 PM CT typically ship same day via UPS or USPS. Standard delivery is 3-5 business days.</p>

                        <h4>Expedited Options</h4>
                        <p>Need it faster? Select expedited shipping at checkout for 2-day or overnight delivery.</p>

                        <h4>Returns</h4>
                        <p>30-day return window on unused, unworn items in original packaging. Armor, medical consumables, and custom-kit orders are final sale. See our <a href="<?php echo esc_url( site_url( '/life-safety-replacement-guarantee/' ) ); ?>">Life Safety Guarantee</a> for our 5-year replacement policy.</p>

                        <?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?>
                        <h4>Agency Orders</h4>
                        <p>Purchase orders, quote-to-order, and volume pricing are handled through the <a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener">agency portal</a>.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </section>

    </div>
</main>

<!-- RELATED PRODUCTS -->
<?php if ( ! empty( $related_products ) ) : ?>
<section class="j5-related-section">
    <div class="j5-container">
        <div class="j5-block-head">
            <div>
                <div class="j5-block-label">YOU MIGHT ALSO NEED</div>
                <h2 class="j5-block-title">Related <span class="accent">gear.</span></h2>
            </div>
        </div>
        <div class="j5-prod-grid j5-prod-grid-4">
            <?php foreach ( $related_products as $related ) :
                j5_render_shop_product_card( $related );
            endforeach; ?>
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

<!-- STICKY MOBILE ADD TO CART -->
<div class="j5-sticky-cart" id="j5StickyCart">
    <div class="j5-sticky-cart-inner">
        <div class="j5-sticky-info">
            <div class="j5-sticky-name"><?php echo esc_html( wp_trim_words( $name, 6 ) ); ?></div>
            <div class="j5-sticky-price"><?php echo wp_kses_post( $price_html ); ?></div>
        </div>
        <button type="button" class="j5-sticky-btn" id="j5StickyBtn">Add to Cart →</button>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
