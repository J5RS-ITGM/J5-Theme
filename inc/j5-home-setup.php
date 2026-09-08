<?php
/**
 * J5 Home Template — Setup & Helpers
 *
 * Enqueues the template CSS, registers cart fragments for live count updates,
 * and provides rendering helpers used by template-j5-home.php.
 *
 * Included from the child theme's functions.php via:
 *   require_once get_stylesheet_directory() . '/inc/j5-home-setup.php';
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ============================================================================
//  1.  ENQUEUE STYLES — only on pages using the J5 Home template
// ============================================================================

function j5_home_enqueue_assets() {
    if ( ! is_page_template( 'template-j5-home.php' ) ) {
        return;
    }

    // Brand fonts are self-hosted and enqueued site-wide via inc/j5-fonts.php
    // (j5_enqueue_fonts, priority 5). No Google Fonts request here.

    // Template stylesheet
    wp_enqueue_style(
        'j5-home',
        get_stylesheet_directory_uri() . '/assets/css/j5-home.css',
        array(),
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'j5_home_enqueue_assets' );

// ============================================================================
//  2.  LIVE CART COUNT — Woo fragment so the header badge updates via AJAX
//      when customers add items to the cart without reloading the page.
// ============================================================================

function j5_header_cart_fragment( $fragments ) {
    $count = class_exists( 'WooCommerce' ) && WC()->cart
        ? WC()->cart->get_cart_contents_count()
        : 0;

    ob_start();
    ?><span class="cart-count"><?php echo esc_html( str_pad( $count, 2, '0', STR_PAD_LEFT ) ); ?></span><?php
    $fragments['span.cart-count'] = ob_get_clean();

    return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'j5_header_cart_fragment' );

// ============================================================================
//  3.  HELPER — total published product count (used in hero stats + impact strip)
// ============================================================================

function j5_count_products() {
    $cached = get_transient( 'j5_product_count' );
    if ( false !== $cached ) {
        return $cached;
    }

    if ( ! class_exists( 'WooCommerce' ) ) {
        return 0;
    }

    $counts = wp_count_posts( 'product' );
    $total  = isset( $counts->publish ) ? (int) $counts->publish : 0;

    // Round down to nearest 100 so hero number doesn't flicker daily
    $rounded = floor( $total / 100 ) * 100;

    set_transient( 'j5_product_count', $rounded, HOUR_IN_SECONDS * 6 );
    return $rounded;
}

// ============================================================================
//  4.  HELPER — render a single product card (used in New Arrivals + Best Sellers)
// ============================================================================

function j5_render_product_card( $product, $context = '' ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        return;
    }

    $permalink  = $product->get_permalink();
    $name       = $product->get_name();
    $sku        = $product->get_sku() ? $product->get_sku() : '#' . $product->get_id();
    $price_html = $product->get_price_html();
    $image_id   = $product->get_image_id();
    $image_url  = $image_id
        ? wp_get_attachment_image_url( $image_id, 'medium_large' )
        : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src() : '' );

    $brand      = j5_get_product_brand( $product );
    $rating     = (float) $product->get_average_rating();

    // Badge logic
    $badge_label = '';
    $badge_class = '';
    if ( $product->is_on_sale() ) {
        $badge_label = 'SALE';
        $badge_class = '';
    } elseif ( $context === 'top' ) {
        $badge_label = '#1 SELLER';
        $badge_class = '';
    } elseif ( $context === 'new' || j5_is_new_product( $product ) ) {
        $badge_label = 'NEW';
        $badge_class = '';
    } elseif ( $product->is_in_stock() && $product->is_featured() ) {
        $badge_label = 'IN STOCK';
        $badge_class = 'gold';
    }

    // Add-to-cart button text (handles variable products via "Select options")
    $add_text = $product->add_to_cart_text();
    $add_url  = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock()
        ? $product->add_to_cart_url()
        : $permalink;
    ?>
    <article class="j5-prod-card">
        <a href="<?php echo esc_url( $permalink ); ?>" class="j5-prod-img-wrap">
            <?php if ( $badge_label ) : ?>
                <span class="j5-prod-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span>
            <?php endif; ?>
            <?php if ( $image_url ) : ?>
                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
            <?php endif; ?>
        </a>
        <div class="j5-prod-body">
            <?php if ( $brand ) : ?>
                <div class="j5-prod-brand"><?php echo esc_html( $brand ); ?></div>
            <?php endif; ?>
            <a href="<?php echo esc_url( $permalink ); ?>" class="j5-prod-name"><?php echo esc_html( $name ); ?></a>
            <div class="j5-prod-meta">
                <span class="j5-prod-sku">SKU: <?php echo esc_html( strtoupper( $sku ) ); ?></span>
                <?php if ( $rating > 0 ) : ?>
                    <span class="j5-prod-rating">
                        <?php
                        $filled = (int) round( $rating );
                        echo esc_html( str_repeat( '★', $filled ) . str_repeat( '☆', 5 - $filled ) );
                        ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="j5-prod-price-row">
                <div class="j5-prod-price"><?php echo wp_kses_post( $price_html ); ?></div>
                <a href="<?php echo esc_url( $add_url ); ?>" class="j5-prod-add" <?php if ( $product->is_type( 'simple' ) ) : ?>data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" rel="nofollow"<?php endif; ?>>
                    <?php echo esc_html( $add_text ); ?>
                </a>
            </div>
        </div>
    </article>
    <?php
}

// ============================================================================
//  5.  HELPER — product brand (defensive: tries several common sources)
// ============================================================================

function j5_get_product_brand( $product ) {
    if ( ! $product ) { return ''; }

    // Try common brand taxonomies (used by popular Woo brand plugins)
    foreach ( array( 'product_brand', 'yith_product_brand', 'pwb-brand' ) as $tax ) {
        if ( taxonomy_exists( $tax ) ) {
            $terms = get_the_terms( $product->get_id(), $tax );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                return strtoupper( $terms[0]->name );
            }
        }
    }

    // Try pa_brand attribute
    $attr = $product->get_attribute( 'pa_brand' );
    if ( $attr ) {
        return strtoupper( $attr );
    }

    // Try first product tag (Eric is heavy on tags)
    $tags = get_the_terms( $product->get_id(), 'product_tag' );
    if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
        return strtoupper( $tags[0]->name );
    }

    // Nothing — return empty, card hides the brand line
    return '';
}

// ============================================================================
//  6.  HELPER — is this product "new"? (published within last 30 days)
// ============================================================================

function j5_is_new_product( $product ) {
    if ( ! $product ) { return false; }
    $created = $product->get_date_created();
    if ( ! $created ) { return false; }
    return ( strtotime( $created ) > strtotime( '-30 days' ) );
}

// ============================================================================
//  7.  HELPER — simple SVG category icons (used in category grid)
// ============================================================================

function j5_category_icon( $key ) {
    $icons = array(
        'shield' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor"><path d="M32 10 L48 18 V34 C48 44 40 52 32 56 C24 52 16 44 16 34 V18 Z"/><path d="M32 24 V40 M24 32 H40"/></svg>',
        'pouch'  => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor"><rect x="14" y="16" width="36" height="38"/><path d="M22 16 V10 H42 V16 M14 28 H50 M14 40 H50"/></svg>',
        'tool'   => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor"><path d="M14 32 L30 10 L50 30 L34 52 Z"/><circle cx="36" cy="28" r="3"/></svg>',
        'cross'  => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor"><path d="M32 10 V54 M10 32 H54" stroke-width="4"/><rect x="18" y="18" width="28" height="28"/></svg>',
        'bag'    => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor"><path d="M16 20 H48 V54 H16 Z"/><path d="M22 20 V14 H42 V20 M24 32 H40 M24 40 H36"/></svg>',
        'comms'  => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor"><circle cx="32" cy="32" r="6"/><path d="M32 16 A16 16 0 0 1 48 32 M32 8 A24 24 0 0 1 56 32 M32 48 A16 16 0 0 0 16 32 M32 56 A24 24 0 0 0 8 32"/></svg>',
        'bolt'   => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor"><path d="M12 32 L28 16 L52 40 L36 56 Z"/><path d="M40 20 L48 28"/></svg>',
        'light'  => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor"><circle cx="32" cy="24" r="10"/><path d="M22 34 L14 54 H50 L42 34"/></svg>',
    );

    return isset( $icons[ $key ] ) ? $icons[ $key ] : '';
}

// ============================================================================
//  8.  OPTIONAL — register 'primary' menu if the active theme hasn't already.
//      Astra registers 'primary' by default, so this is a safety net.
// ============================================================================

function j5_register_menus_fallback() {
    $locations = get_registered_nav_menus();
    if ( ! isset( $locations['primary'] ) ) {
        register_nav_menu( 'primary', __( 'Primary Menu', 'astra-child' ) );
    }
}
add_action( 'after_setup_theme', 'j5_register_menus_fallback', 20 );

/* ============================================================================
 *  MOBILE HAMBURGER MENU - injects button, panel, overlay, and wires events
 *  Added 2026-04-17
 * ========================================================================== */
add_action( 'wp_footer', 'j5_home_mobile_menu_script', 99 );
function j5_home_mobile_menu_script() {
    if ( ! is_page_template( 'template-j5-home.php' ) ) {
        return;
    }
    ?>
    <script>
    (function(){
        if (!document.body.classList.contains('j5-home-template')) return;

        var header = document.querySelector('.j5-site-header .j5-header-inner');
        var nav = document.querySelector('.j5-main-nav');
        var actions = document.querySelector('.j5-header-actions');
        if (!header) return;

        // Build hamburger button
        var btn = document.createElement('button');
        btn.className = 'j5-hamburger';
        btn.setAttribute('aria-label', 'Open menu');
        btn.setAttribute('aria-expanded', 'false');
        btn.innerHTML = '<span class="j5-hamburger-lines"><span></span></span>';
        header.appendChild(btn);

        // Overlay
        var overlay = document.createElement('div');
        overlay.className = 'j5-mobile-overlay';

        // Panel
        var panel = document.createElement('aside');
        panel.className = 'j5-mobile-panel';
        panel.setAttribute('aria-label', 'Mobile navigation');

        // Clone nav into panel
        var navHtml = '';
        if (nav) {
            var ul = nav.querySelector('.j5-menu');
            if (ul) {
                var navClone = ul.cloneNode(true);
                navClone.querySelectorAll('.ast-menu-toggle').forEach(function(b){ b.remove(); });
                navClone.className = 'j5-panel-nav';
                navHtml = navClone.outerHTML;
            }
        }

        // Clone action buttons
        var actionsHtml = '';
        if (actions) {
            actionsHtml = '<div class="j5-panel-actions">';
            actions.querySelectorAll('a').forEach(function(a){
                var label = (a.textContent || '').trim() || a.getAttribute('aria-label') || 'Link';
                var cls = a.classList.contains('j5-cart-trigger') ? ' class="j5-panel-cart"' : '';
                actionsHtml += '<a href="' + a.href + '"' + cls + '>' + label + '</a>';
            });
            actionsHtml += '</div>';
        }

        panel.innerHTML =
            '<div class="j5-panel-head">' +
                '<span class="j5-panel-head-title">MENU</span>' +
                '<button class="j5-panel-close" aria-label="Close menu">&times;</button>' +
            '</div>' +
            navHtml +
            actionsHtml;

        document.body.appendChild(overlay);
        document.body.appendChild(panel);

        function openMenu() {
            document.body.classList.add('j5-menu-open');
            btn.setAttribute('aria-expanded', 'true');
        }
        function closeMenu() {
            document.body.classList.remove('j5-menu-open');
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', openMenu);
        overlay.addEventListener('click', closeMenu);
        panel.querySelector('.j5-panel-close').addEventListener('click', closeMenu);
        panel.addEventListener('click', function(e){
            if (e.target.tagName === 'A' && e.target.href) closeMenu();
        });
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && document.body.classList.contains('j5-menu-open')) closeMenu();
        });
        window.addEventListener('resize', function(){
            if (window.innerWidth > 921 && document.body.classList.contains('j5-menu-open')) closeMenu();
        });
    })();
    </script>
    <?php
}

/* ============================================================================
 *  HERO ROTATOR - 2026-04-18
 *  Swaps <span class="rotator" data-j5-rotator='[...]'> text every 3s
 * ========================================================================== */
add_action( 'wp_footer', 'j5_home_hero_rotator_script', 100 );
function j5_home_hero_rotator_script() {
    if ( ! is_page_template( 'template-j5-home.php' ) ) {
        return;
    }
    ?>
    <script>
    (function(){
        var el = document.querySelector('.j5-hero-title .rotator[data-j5-rotator]');
        if (!el) return;

        var words;
        try {
            words = JSON.parse(el.getAttribute('data-j5-rotator'));
        } catch (e) {
            return;
        }
        if (!Array.isArray(words) || words.length < 2) return;

        var i = 0;
        var interval = 3000;
        var fadeMs = 350;

        // Reserve width so layout doesn't shift when words change length
        var longest = words.reduce(function(a, b){ return a.length >= b.length ? a : b; });
        el.style.minWidth = (longest.length * 0.55) + 'em';

        el.classList.add('j5-rotator-in');

        function cycle() {
            el.classList.remove('j5-rotator-in');
            el.classList.add('j5-rotator-out');
            setTimeout(function(){
                i = (i + 1) % words.length;
                el.textContent = words[i];
                el.classList.remove('j5-rotator-out');
                el.classList.add('j5-rotator-in');
            }, fadeMs);
        }
        setInterval(cycle, interval);
    })();
    </script>
    <?php
}

/* ============================================================================
 *  FREE-SHIP THRESHOLD — editable via Appearance > Customize > J5 Home
 *  Added 2026-04-18
 * ========================================================================== */
function j5_free_ship_label() {
    $default = '$99+';
    $val = get_theme_mod( 'j5_free_ship_threshold', $default );
    $val = trim( $val );
    return $val === '' ? $default : $val;
}
add_action( 'customize_register', 'j5_home_customize_free_ship' );
function j5_home_customize_free_ship( $wp_customize ) {
    $wp_customize->add_section( 'j5_home_section', array(
        'title'    => __( 'J5 Home Settings', 'astra-child' ),
        'priority' => 40,
    ) );
    $wp_customize->add_setting( 'j5_free_ship_threshold', array(
        'default'           => '$99+',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ) );
    $wp_customize->add_control( 'j5_free_ship_threshold', array(
        'label'       => __( 'Free-Shipping Threshold Label', 'astra-child' ),
        'description' => __( 'Shown in the trust bar after "Free Shipping". Examples: $99+, $75+, Over $100, On Orders $99+', 'astra-child' ),
        'section'     => 'j5_home_section',
        'type'        => 'text',
    ) );
}

/* ============================================================================
 *  Hero Search Typeahead (batch 3B 2026-04-19)
 * ========================================================================== */
add_action( 'wp_footer', 'j5_hero_search_typeahead_script', 101 );
function j5_hero_search_typeahead_script() {
    if ( ! is_page_template( 'template-j5-home.php' ) ) return;
    ?>
    <script>
    (function(){
        var form = document.querySelector('.j5-hero-search-form');
        if (!form) return;
        var input = form.querySelector('.j5-hero-search-input');
        var results = form.querySelector('.j5-hero-search-results');
        if (!input || !results) return;

        var restBase = '<?php echo esc_js( rest_url( 'wp/v2/product' ) ); ?>';
        var shopBase = '<?php echo esc_js( home_url( '/?post_type=product&s=' ) ); ?>';
        var debounceTimer = null;
        var currentReq = null;
        var focused = -1;

        function hide() { results.classList.remove('active'); results.innerHTML = ''; focused = -1; }
        function show() { results.classList.add('active'); }

        function render(items, query) {
            if (!items || items.length === 0) {
                results.innerHTML = '<div class="j5-result-empty">No matches for "' + escapeHtml(query) + '"</div>';
                show();
                return;
            }
            var html = items.map(function(p) {
                var title = (p.title && p.title.rendered) ? p.title.rendered : 'Product';
                var link = p.link || '#';
                var price = '';
                if (p.meta && p.meta._price) price = '$' + p.meta._price;
                var img = '';
                if (p._embedded && p._embedded['wp:featuredmedia'] && p._embedded['wp:featuredmedia'][0]) {
                    var m = p._embedded['wp:featuredmedia'][0];
                    if (m.source_url) img = '<img class="j5-result-img" src="' + escapeAttr(m.source_url) + '" alt="" />';
                }
                if (!img) img = '<div class="j5-result-img"></div>';
                return '<a class="j5-result" href="' + escapeAttr(link) + '">' +
                       img +
                       '<div class="j5-result-body"><div class="j5-result-title">' + title + '</div></div>' +
                       (price ? '<div class="j5-result-price">' + price + '</div>' : '') +
                       '</a>';
            }).join('');
            html += '<a class="j5-result" href="' + escapeAttr(shopBase + encodeURIComponent(query)) + '" style="justify-content:center;color:var(--j5-gold,#c8a84e);font-weight:600;">See all results for "' + escapeHtml(query) + '" →</a>';
            results.innerHTML = html;
            show();
        }

        function search(q) {
            if (currentReq) currentReq.abort();
            currentReq = new XMLHttpRequest();
            var url = restBase + '?search=' + encodeURIComponent(q) + '&per_page=6&_embed=1';
            currentReq.open('GET', url);
            currentReq.onload = function() {
                if (currentReq.status >= 200 && currentReq.status < 300) {
                    try { var data = JSON.parse(currentReq.responseText); render(data, q); } catch (e) { hide(); }
                }
            };
            currentReq.onerror = function() { hide(); };
            currentReq.send();
        }

        function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
        function escapeAttr(s) { return String(s).replace(/"/g, '&quot;'); }

        input.addEventListener('input', function() {
            var q = input.value.trim();
            clearTimeout(debounceTimer);
            if (q.length < 2) { hide(); return; }
            debounceTimer = setTimeout(function(){ search(q); }, 220);
        });
        input.addEventListener('focus', function() {
            if (input.value.trim().length >= 2 && results.innerHTML) show();
        });
        document.addEventListener('click', function(e) { if (!form.contains(e.target)) hide(); });
        input.addEventListener('keydown', function(e) {
            var items = results.querySelectorAll('.j5-result');
            if (!items.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); focused = Math.min(focused + 1, items.length - 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); focused = Math.max(focused - 1, 0); }
            else if (e.key === 'Enter' && focused >= 0) { e.preventDefault(); items[focused].click(); return; }
            else if (e.key === 'Escape') { hide(); return; }
            else { return; }
            items.forEach(function(it, i){ it.classList.toggle('focused', i === focused); });
            if (items[focused]) items[focused].scrollIntoView({block:'nearest'});
        });
    })();
    </script>
    <?php
}
