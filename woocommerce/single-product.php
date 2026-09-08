<?php
/**
 * WooCommerce primary single-product template — J5 Rescue (Odoo Style)
 *
 * Migrated from theme-root single-j5-product.php on 2026-04-21. Now serves
 * as the default for all product URLs (/product/*). The original
 * single-j5-product.php is preserved at theme root for the j5_preview=1
 * filter and J5_PRODUCT_LIVE constant override defined in j5-shop-setup.php.
 *
 * Uses site chrome (get_header / get_footer). The previous standalone HTML
 * shell has been removed; chrome now comes from header.php and footer.php.
 *
 * The product-page <main> wrapper has been converted to a <div> to avoid
 * nesting inside the <main> element that header.php opens.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Preserve the body class `j5-product-template` on every product page so
 * scoped CSS (selectors under `body.j5-product-template`) keeps matching
 * after the chrome migration.
 */
add_filter( 'body_class', function ( $classes ) {
	if ( function_exists( 'is_product' ) && is_product() ) {
		$classes[] = 'j5-product-template';
	}
	return $classes;
} );

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

get_header();
?>

<div class="j5-product">


<!-- PRODUCT DETAIL -->
<div class="j5-product-main">
    <div class="j5-container">

        <!-- Breadcrumb -->
        <nav class="j5-crumbs" aria-label="Breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
            <span class="sep">/</span>
            <a href="<?php echo esc_url( $j5_shop_url ); ?>">Shop</a>
            <?php if ( $primary_cat ) : ?>
                <span class="sep">/</span>
                <a href="<?php echo esc_url( get_term_link( $primary_cat ) ); ?>"><?php echo esc_html( wp_specialchars_decode( $primary_cat->name, ENT_QUOTES ) ); ?></a>
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

                <?php
                /*
                 * Hub-owned fulfillment override, resolved before the price so
                 * a non-buyable status (out / discontinued) can strike through
                 * the price. See inc/j5-fulfillment.php.
                 */
                $j5_fulfillment = function_exists( 'j5_get_fulfillment' ) ? j5_get_fulfillment( $product ) : null;
                $j5_not_buyable = function_exists( 'j5_fulfillment_blocks_cart' ) ? j5_fulfillment_blocks_cart( $j5_fulfillment ) : false;
                ?>
                <div class="j5-product-price<?php echo $j5_not_buyable ? ' j5-price-unavailable' : ''; ?>"><?php echo wp_kses_post( $price_html ); ?></div>

                <!-- STOCK INDICATOR -->
                <?php
                /*
                 * $j5_fulfillment is resolved above (before the price block) so
                 * a non-buyable status can strike through the price. Reused here
                 * for the banner. See inc/j5-fulfillment.php.
                 */
                ?>
                <?php if ( $product->is_type( 'variable' ) ) : ?>
                    <?php
                    /*
                     * Variable products: the parent-level is_in_stock() is
                     * misleading (true if ANY variation is in stock), so the
                     * banner starts in a neutral "select options" state and
                     * j5-shop.js updates it live from the chosen variation.
                     * If the whole product is out of stock, say so up front.
                     *
                     * Only a LOCKING status pins the headline (see 'banner_lock'
                     * in inc/j5-fulfillment.php). A non-locking status such as
                     * in_stock or special seeds the initial banner but lets
                     * j5-shop.js replace it once a variation is chosen —
                     * otherwise a Small marked SPECIAL ORDER would sit under a
                     * green IN STOCK headline.
                     */
                    $j5_banner_lock = $j5_fulfillment && ! empty( $j5_fulfillment['banner_lock'] );

                    if ( $j5_banner_lock ) {
                        $j5_banner_state = $j5_fulfillment['state'];
                    } else {
                        $j5_banner_state = $product->is_in_stock() ? 'select' : 'out';
                    }
                    ?>
                    <div class="j5-product-stock j5-stock-variable" id="j5StockBanner" data-state="<?php echo esc_attr( $j5_banner_state ); ?>"<?php echo $j5_fulfillment ? ' data-j5-fulfillment="' . esc_attr( $j5_fulfillment['type'] ) . '"' : ''; ?><?php echo $j5_banner_lock ? ' data-j5-fulfillment-lock="1"' : ''; ?>>
                        <?php if ( $j5_banner_lock ) : ?>
                            <span class="dot <?php echo esc_attr( $j5_fulfillment['state'] ); ?>"></span>
                            <span class="label"><?php echo esc_html( $j5_fulfillment['label'] ); ?></span>
                            <span class="qty"><?php echo esc_html( $j5_fulfillment['note'] ); ?></span>
                        <?php elseif ( $product->is_in_stock() ) : ?>
                            <span class="dot select"></span>
                            <span class="label">SELECT OPTIONS</span>
                            <span class="qty">availability shown per configuration</span>
                        <?php else : ?>
                            <span class="dot out"></span>
                            <span class="label">OUT OF STOCK</span>
                            <span class="qty">contact us for ETA</span>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="j5-product-stock"<?php echo $j5_fulfillment ? ' data-j5-fulfillment="' . esc_attr( $j5_fulfillment['type'] ) . '"' : ''; ?>>
                        <?php if ( $j5_fulfillment ) : ?>
                            <span class="dot <?php echo esc_attr( $j5_fulfillment['state'] ); ?>"></span>
                            <span class="label"><?php echo esc_html( $j5_fulfillment['label'] ); ?></span>
                            <span class="qty"><?php echo esc_html( $j5_fulfillment['note'] ); ?></span>
                        <?php elseif ( $product->is_on_backorder() ) : ?>
                            <span class="dot special"></span>
                            <span class="label">SPECIAL ORDER</span>
                            <span class="qty"><?php echo esc_html( apply_filters( 'j5_special_order_note', 'made to order · extended lead time', $product ) ); ?></span>
                        <?php elseif ( $product->is_in_stock() ) :
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
                <?php endif; ?>

                <!-- PRODUCT ANNOUNCEMENTS (marketing notices) -->
                <?php
                if ( function_exists( 'j5_render_product_announcements' ) ) {
                    j5_render_product_announcements( $product );
                }
                ?>

                <!-- PRODUCT ACKNOWLEDGMENTS (informational; those with placement=product render as a gate inside the cart form below) -->
                <?php
                if ( function_exists( 'j5_render_ack_notices' ) ) {
                    j5_render_ack_notices( $product );
                }
                ?>

                <!-- VARIANT SELECTORS (for variable products) -->
                <?php
                $j5_cart_blocked = j5_fulfillment_blocks_cart( $j5_fulfillment );
                if ( $j5_cart_blocked ) :
                    /*
                     * Fulfillment status forbids purchase (out / discontinued).
                     * Replace the cart with a disabled control. For discontinued
                     * items, render the crawlable replacement block so link equity
                     * flows to successor products. See inc/j5-fulfillment.php.
                     */
                    ?>
                    <div class="j5-add-to-cart-row j5-cart-blocked">
                        <button type="button" class="j5-add-to-cart-btn is-disabled" disabled aria-disabled="true"><?php echo 'discontinued' === $j5_fulfillment['type'] ? 'Discontinued' : 'Currently Unavailable'; ?></button>
                    </div>
                <?php elseif ( $product->is_type( 'variable' ) ) :
                    $attributes = $product->get_variation_attributes();
                    $available_variations = $product->get_available_variations();
                ?>
                    <form class="variations_form cart j5-variations-form"
                          action="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
                          method="post" enctype='multipart/form-data'
                          data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"
                          data-j5-currency="<?php echo esc_attr( get_woocommerce_currency_symbol() ); ?>"
                          data-j5-decimals="<?php echo esc_attr( wc_get_price_decimals() ); ?>"
                          data-product_variations="<?php echo esc_attr( wp_json_encode( $available_variations ) ); ?>">

                        <?php
                        /*
                         * Acknowledgment gate (placement = 'product'). MUST be
                         * inside the form so the checkboxes post with the
                         * add-to-cart request; validation is server-side in
                         * inc/j5-acknowledgments.php.
                         */
                        if ( function_exists( 'j5_render_ack_gate' ) ) {
                            j5_render_ack_gate( $product );
                        }
                        ?>

                        <?php
                        /*
                         * Renders each attribute group in its resolved display
                         * style (pills / radio-cards / dropdown) with hidden
                         * selects for Woo's variation JS. Cascade behavior
                         * (progressive reveal, combination filtering, OOS
                         * marking) is driven by j5-shop.js.
                         * See inc/j5-variation-display.php.
                         */
                        j5_render_variation_groups( $product, $attributes, $available_variations );
                        ?>

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
                        <?php
                        // See note above — must be inside the form to post.
                        if ( function_exists( 'j5_render_ack_gate' ) ) {
                            j5_render_ack_gate( $product );
                        }
                        ?>
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

                <?php
                /*
                 * Replacement / successor block. Rendered for ANY status whose
                 * definition sets 'replacements' => true — including buyable
                 * ones (final_stock), so it must sit outside the cart-blocked
                 * branch above. See inc/j5-fulfillment.php.
                 */
                if ( $j5_fulfillment && ! empty( $j5_fulfillment['replacements'] ) && function_exists( 'j5_render_discontinued_block' ) ) {
                    j5_render_discontinued_block( $j5_fulfillment );
                }
                ?>

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

                <!-- LE Portal callout (gated: see inc/j5-portal-flag.php) -->
                <?php if ( function_exists( 'j5_portal_enabled' ) && j5_portal_enabled() ) : ?>
                <div class="j5-product-agency">
                    <div class="label">LE / FIRST RESPONDER / AGENCY ORDERING</div>
                    <p>Get net agency pricing, volume discounts, and spec sheets through the operations portal.</p>
                    <a href="<?php echo esc_url( j5_portal_url() ); ?>" target="_blank" rel="noopener">Enter Portal →</a>
                </div>
                <?php endif; ?>

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
</div><!-- .j5-product-main -->

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


</div><!-- .j5-product -->

<!-- STICKY MOBILE ADD TO CART -->
<div class="j5-sticky-cart" id="j5StickyCart">
    <div class="j5-sticky-cart-inner">
        <div class="j5-sticky-info">
            <div class="j5-sticky-name"><?php echo esc_html( wp_trim_words( $name, 6 ) ); ?></div>
            <div class="j5-sticky-price<?php echo $j5_not_buyable ? ' j5-price-unavailable' : ''; ?>"><?php echo wp_kses_post( $price_html ); ?></div>
        </div>
        <button type="button" class="j5-sticky-btn" id="j5StickyBtn">Add to Cart →</button>
    </div>
</div>


<?php
get_footer();

