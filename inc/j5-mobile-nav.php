<?php
/**
 * J5 Mobile Nav — hamburger + category drawer for archive/shop pages
 *
 * The homepage already has a mobile menu via j5-home-setup.php
 * (j5_home_mobile_menu_script). That script is gated to homepage only.
 * This module provides a similar pattern, scoped to product archives and
 * shop pages, listing top-level product categories as the primary
 * navigation in the mobile drawer.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }


/**
 * Returns a deterministic list of top-level category links for the
 * mobile drawer. Mirrors the homepage card list so the two stay in sync.
 */
function j5_mobile_archive_nav_items() {
    return array(
        array( 'name' => 'Ballistic Armor',     'url' => site_url( '/armor/' ) ),
        array( 'name' => 'Nylon Gear',          'url' => site_url( '/nylon-gear/' ) ),
        array( 'name' => 'Accessories & Tools', 'url' => site_url( '/tools-knives/' ) ),
        array( 'name' => 'Med Kit Supplies',    'url' => site_url( '/medical/' ) ),
        array( 'name' => 'Medical Bags',        'url' => site_url( '/bags-cases/' ) ),
        array( 'name' => 'Optics',              'url' => site_url( '/optics-weapon-accessories/' ) ),
        array( 'name' => 'Less-Lethal',         'url' => site_url( '/less-lethal/' ) ),
        array( 'name' => 'Lights',              'url' => site_url( '/lights/' ) ),
        array( 'name' => 'Firearms Parts',      'url' => site_url( '/firearms-parts/' ) ),
        array( 'name' => 'Shop All',            'url' => site_url( '/shop/' ) ),
    );
}


/**
 * Inject the hamburger + drawer markup + script on shop/archive pages.
 */
add_action( 'wp_footer', 'j5_archive_mobile_menu', 99 );
function j5_archive_mobile_menu() {
    // Skip homepage (it has its own); skip non-WC pages
    if ( function_exists( 'is_page_template' ) && is_page_template( 'template-j5-home.php' ) ) {
        return;
    }
    if ( ! function_exists( 'is_shop' ) ) {
        return;
    }
    if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_post_type_archive( 'product' ) || is_product() ) ) {
        return;
    }

    $items = j5_mobile_archive_nav_items();
    ?>
    <div class="j5-amobile-overlay" data-j5-amobile-overlay></div>
    <aside class="j5-amobile-panel" data-j5-amobile-panel aria-label="Mobile navigation">
        <div class="j5-amobile-head">
            <span class="j5-amobile-head-title">SHOP BY CATEGORY</span>
            <button type="button" class="j5-amobile-close" aria-label="Close menu" data-j5-amobile-close>&times;</button>
        </div>
        <nav class="j5-amobile-nav" aria-label="Categories">
            <ul>
                <?php foreach ( $items as $it ) : ?>
                    <li><a href="<?php echo esc_url( $it['url'] ); ?>"><?php echo esc_html( $it['name'] ); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>
    <button type="button" class="j5-amobile-trigger" aria-label="Open category menu" data-j5-amobile-open>
        <span class="j5-amobile-lines"><span></span><span></span><span></span></span>
        <span class="j5-amobile-trigger-label">SHOP</span>
    </button>

    <style>
    .j5-amobile-trigger {
        display: none;
        position: fixed;
        right: 16px;
        bottom: 16px;
        z-index: 998;
        width: auto;
        min-width: 64px;
        height: 56px;
        padding: 0 18px 0 14px;
        background: var(--j5-gold, #d4a044);
        color: var(--j5-dark, #0a0b0d);
        border: 0;
        border-radius: 28px;
        align-items: center;
        gap: 10px;
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 700;
        font-size: 13px;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        cursor: pointer;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(212, 160, 68, 0.4);
    }
    .j5-amobile-lines {
        display: inline-flex;
        flex-direction: column;
        gap: 3px;
        width: 18px;
    }
    .j5-amobile-lines span {
        display: block;
        height: 2px;
        background: var(--j5-dark, #0a0b0d);
        border-radius: 1px;
    }
    .j5-amobile-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
        z-index: 999;
    }
    .j5-amobile-panel {
        position: fixed;
        top: 0;
        right: 0;
        width: 320px;
        max-width: 90vw;
        height: 100vh;
        background: var(--j5-dark, #0a0b0d);
        color: var(--j5-bone, #f5f1e8);
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.25s ease;
        overflow-y: auto;
        border-left: 1px solid var(--j5-border-2, rgba(245, 241, 232, 0.20));
    }
    body.j5-amobile-open .j5-amobile-overlay {
        opacity: 1;
        pointer-events: auto;
    }
    body.j5-amobile-open .j5-amobile-panel {
        transform: translateX(0);
    }
    body.j5-amobile-open {
        overflow: hidden;
    }
    .j5-amobile-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 20px;
        border-bottom: 1px solid var(--j5-border, rgba(245, 241, 232, 0.10));
    }
    .j5-amobile-head-title {
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--j5-gold, #d4a044);
        font-weight: 600;
    }
    .j5-amobile-close {
        background: transparent;
        border: 0;
        color: var(--j5-bone, #f5f1e8);
        font-size: 28px;
        line-height: 1;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
    }
    .j5-amobile-nav ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .j5-amobile-nav li {
        border-bottom: 1px solid var(--j5-border, rgba(245, 241, 232, 0.10));
    }
    .j5-amobile-nav a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 600;
        font-size: 15px;
        letter-spacing: 0.10em;
        text-transform: uppercase;
        color: var(--j5-bone, #f5f1e8);
        text-decoration: none;
        transition: background 0.15s, color 0.15s, padding-left 0.15s;
    }
    .j5-amobile-nav a:hover,
    .j5-amobile-nav a:focus {
        background: var(--j5-dark-3, #1c1f25);
        color: var(--j5-gold, #d4a044);
        padding-left: 26px;
    }
    .j5-amobile-nav a::after {
        content: "→";
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        opacity: 0.4;
    }
    @media (max-width: 900px) {
        .j5-amobile-trigger { display: inline-flex; }
    }
    </style>

    <script>
    (function(){
        var trigger = document.querySelector('[data-j5-amobile-open]');
        var closeBtn = document.querySelector('[data-j5-amobile-close]');
        var overlay = document.querySelector('[data-j5-amobile-overlay]');
        var panel = document.querySelector('[data-j5-amobile-panel]');
        if (!trigger || !panel) return;

        function open() {
            document.body.classList.add('j5-amobile-open');
            trigger.setAttribute('aria-expanded', 'true');
        }
        function close() {
            document.body.classList.remove('j5-amobile-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', open);
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (overlay) overlay.addEventListener('click', close);
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && document.body.classList.contains('j5-amobile-open')) close();
        });
        // Close on link click (browser will navigate; close prevents stale state on back-button)
        panel.addEventListener('click', function(e){
            if (e.target.tagName === 'A' && e.target.href) close();
        });
    })();
    </script>
    <?php
}
