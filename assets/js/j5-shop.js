/**
 * J5 Shop + Single Product — Frontend JS
 * Handles: filter group collapse, gallery thumbs, variant pills, tabs,
 *          quantity buttons, sticky mobile cart, filter checkbox auto-submit.
 */
( function() {
    'use strict';

    function $( sel, ctx ) { return ( ctx || document ).querySelector( sel ); }
    function $$( sel, ctx ) { return Array.from( ( ctx || document ).querySelectorAll( sel ) ); }

    /* ============ 1. FILTER GROUPS — collapse/expand ============ */
    $$( '.j5-filter-head' ).forEach( function( head ) {
        head.addEventListener( 'click', function() {
            var group = head.closest( '.j5-filter-group' );
            if ( group ) group.classList.toggle( 'collapsed' );
        } );
    } );

    /* ============ 2. FILTER CHECKBOXES — auto-navigate on change ============ */
    $$( '.j5-filter-list input[type="checkbox"][data-filter-url]' ).forEach( function( cb ) {
        cb.addEventListener( 'change', function() {
            var url = cb.getAttribute( 'data-filter-url' );
            if ( url ) window.location.href = url;
        } );
    } );

    /* ============ 3. QUANTITY BUTTONS ============ */
    $$( '.j5-qty-wrap' ).forEach( function( wrap ) {
        var input = $( '.j5-qty-input', wrap );
        var minus = $( '.j5-qty-btn.minus', wrap );
        var plus  = $( '.j5-qty-btn.plus', wrap );
        if ( ! input ) return;

        if ( minus ) minus.addEventListener( 'click', function() {
            var v = parseInt( input.value, 10 ) || 1;
            if ( v > 1 ) input.value = v - 1;
            input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        } );
        if ( plus ) plus.addEventListener( 'click', function() {
            var v = parseInt( input.value, 10 ) || 1;
            input.value = v + 1;
            input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        } );
    } );

    /* ============ 4. GALLERY THUMBS (single product) ============ */
    var galleryMain = $( '#j5-gallery-main-img' );
    if ( galleryMain ) {
        $$( '.j5-gallery-thumb' ).forEach( function( thumb ) {
            thumb.addEventListener( 'click', function() {
                var full = thumb.getAttribute( 'data-full' );
                if ( ! full ) return;
                galleryMain.src = full;
                $$( '.j5-gallery-thumb' ).forEach( function( t ) { t.classList.remove( 'active' ); } );
                thumb.classList.add( 'active' );
            } );
        } );
    }

    /* ============ 5. PRODUCT TABS ============ */
    $$( '.j5-tab-btn' ).forEach( function( btn ) {
        btn.addEventListener( 'click', function() {
            var target = btn.getAttribute( 'data-tab' );
            if ( ! target ) return;

            $$( '.j5-tab-btn' ).forEach( function( b ) { b.classList.remove( 'active' ); } );
            $$( '.j5-tab-panel' ).forEach( function( p ) { p.classList.remove( 'active' ); } );

            btn.classList.add( 'active' );
            var panel = $( '.j5-tab-panel[data-panel="' + target + '"]' );
            if ( panel ) panel.classList.add( 'active' );
        } );
    } );

    /* ============ 6+7. VARIATION ENGINE (J5-VAR-CASCADE-1, 2026-07-13) ============
     * Replaces the old pill-sync + disable-sync pair. Provides:
     *  - Progressive disclosure: attribute group N+1 stays hidden until
     *    group N has a selection; changing an earlier group clears and
     *    re-filters everything after it.
     *  - Combination filtering: options that cannot form an existing
     *    variation given the selections so far are HIDDEN (not greyed).
     *  - OOS marking: options whose every matching variation is out of
     *    stock stay visible and clickable but carry .j5-opt-oos.
     *  - Display types: pills, radio-cards (with live absolute prices),
     *    and visible dropdowns all drive the same Woo selects.
     *  - Stock banner + top price sync from the chosen variation.
     * Falls back to show-everything + Woo native disabling when the
     * variations JSON is unavailable (above the AJAX threshold).
     */
    ( function () {
        var form = $( '.j5-variations-form' );
        if ( ! form ) { return; }

        var raw = form.getAttribute( 'data-product_variations' );
        var variations = null;
        try { variations = JSON.parse( raw ); } catch ( e ) { variations = null; }
        if ( ! Array.isArray( variations ) ) { variations = null; }

        var groups = $$( '.j5-variant-group[data-attribute]', form );
        var banner = $( '#j5StockBanner' );
        var priceEl = $( '.j5-product-price' );
        var parentPriceHTML = priceEl ? priceEl.innerHTML : '';

        var currency = form.getAttribute( 'data-j5-currency' ) || '$';
        var decimals = parseInt( form.getAttribute( 'data-j5-decimals' ), 10 );
        if ( isNaN( decimals ) ) { decimals = 2; }

        function fmtPrice( n ) {
            return currency + Number( n ).toLocaleString( undefined, {
                minimumFractionDigits: decimals, maximumFractionDigits: decimals
            } );
        }

        function groupSelect( group ) {
            var attr = group.getAttribute( 'data-attribute' );
            return group.querySelector( 'select[name="' + attr + '"]' );
        }

        function groupOptions( group ) {
            return $$( '[data-value]', group );
        }

        function matches( variation, sel ) {
            for ( var key in sel ) {
                if ( ! sel[ key ] ) { continue; }
                var vVal = variation.attributes[ key ];
                if ( vVal !== '' && vVal !== sel[ key ] ) { return false; }
            }
            return true;
        }

        /* A LOCKING fulfillment status pins the banner: the headline
         * state/label/qty are fixed server-side and must survive variation
         * events. Per-option dots and price still update normally.
         *
         * Keyed off data-j5-fulfillment-lock, NOT the mere presence of
         * data-j5-fulfillment. The latter is set for every status including
         * in_stock/special, which used to freeze the banner on variable
         * products — a Small reading SPECIAL ORDER under a green IN STOCK
         * headline. Statuses that only restate availability don't lock; ones
         * carrying a lead time or discontinuation do. See inc/j5-fulfillment.php. */
        var fulfillmentLocked = !!( banner && banner.getAttribute( 'data-j5-fulfillment-lock' ) );

        function setBanner( state, label, qty ) {
            if ( ! banner ) { return; }
            if ( fulfillmentLocked ) { return; }
            banner.setAttribute( 'data-state', state );
            var dot = banner.querySelector( '.dot' );
            if ( dot ) { dot.className = 'dot ' + state; }
            var l = banner.querySelector( '.label' );
            if ( l ) { l.textContent = label; }
            var q = banner.querySelector( '.qty' );
            if ( q ) { q.textContent = qty; }
        }

        function bannerSelectState() {
            setBanner( 'select', 'SELECT OPTIONS', 'availability shown per configuration' );
        }

        /* Admin-configured banner copy (J5 Apps -> Fulfillment), with
         * hardcoded fallbacks so the banner still works if config is absent. */
        function bannerCopy( slug, defLabel, defNote ) {
            var c = ( window.j5Shop && window.j5Shop.banner && window.j5Shop.banner[ slug ] ) || {};
            return {
                label: c.label || defLabel,
                note:  c.note  || defNote
            };
        }

        /* Core pass: reveal/hide groups, filter options, mark OOS, price cards. */
        function refresh() {
            var selEarlier = {};
            var chainComplete = true;

            groups.forEach( function ( group, i ) {
                var attr = group.getAttribute( 'data-attribute' );
                var select = groupSelect( group );
                if ( ! select ) { return; }

                var visible = ( i === 0 ) || chainComplete;
                group.classList.toggle( 'j5-vg-hidden', ! visible );

                if ( ! visible ) {
                    if ( select.value ) {
                        select.value = '';
                        groupOptions( group ).forEach( function ( o ) { o.classList.remove( 'active' ); } );
                    }
                    chainComplete = false;
                    return;
                }

                if ( variations ) {
                    groupOptions( group ).forEach( function ( o ) {
                        var candidate = {};
                        for ( var k in selEarlier ) { candidate[ k ] = selEarlier[ k ]; }
                        candidate[ attr ] = o.getAttribute( 'data-value' );

                        var matching = variations.filter( function ( v ) { return matches( v, candidate ); } );
                        var exists = matching.length > 0;
                        o.classList.toggle( 'j5-opt-none', ! exists );

                        if ( exists ) {
                            var states = matching.map( function ( v ) {
                                if ( v.variation_is_active === false ) { return 'out'; }
                                return v.j5_stock_state || ( v.is_in_stock ? 'in' : 'out' );
                            } );
                            var st = states.indexOf( 'in' ) > -1 ? 'in'
                                : ( states.indexOf( 'special' ) > -1 ? 'special' : 'out' );
                            o.classList.toggle( 'j5-opt-in', st === 'in' );
                            o.classList.toggle( 'j5-opt-special', st === 'special' );
                            o.classList.toggle( 'j5-opt-oos', st === 'out' );

                            /* Per-option pricing is suppressed by default
                             * (J5-VAR-PRICE-1). The server emits .j5-vc-price
                             * empty but keeps data-min for this engine. Only
                             * repopulate the visible text when the server
                             * actually rendered a price — otherwise the
                             * suppressed prices would reappear on first
                             * refresh. */
                            var priceSpan = o.querySelector( '.j5-vc-price' );
                            if ( priceSpan && priceSpan.innerHTML.trim() !== '' ) {
                                var prices = matching
                                    .map( function ( v ) { return parseFloat( v.display_price ); } )
                                    .filter( function ( p ) { return ! isNaN( p ); } );
                                if ( prices.length ) {
                                    var min = Math.min.apply( null, prices );
                                    var max = Math.max.apply( null, prices );
                                    priceSpan.innerHTML = ( min === max )
                                        ? fmtPrice( min )
                                        : '<span class="from_">From</span> ' + fmtPrice( min );
                                }
                            }
                        } else {
                            o.classList.remove( 'j5-opt-in', 'j5-opt-special', 'j5-opt-oos' );
                        }
                    } );

                    /* Selection pointing at a now-nonexistent option? Clear it. */
                    if ( select.value ) {
                        var cur = group.querySelector( '[data-value="' + CSS.escape( select.value ) + '"]' );
                        if ( cur && cur.classList.contains( 'j5-opt-none' ) ) {
                            select.value = '';
                            groupOptions( group ).forEach( function ( o ) { o.classList.remove( 'active' ); } );
                        }
                    }
                }

                /* Sync visual active state from the select (pills/cards). */
                groupOptions( group ).forEach( function ( o ) {
                    o.classList.toggle( 'active', !! select.value && o.getAttribute( 'data-value' ) === select.value );
                } );

                if ( select.value ) {
                    selEarlier[ attr ] = select.value;
                } else {
                    chainComplete = false;
                }
            } );
        }

        /* Without variation data, reveal everything and let Woo's native
         * option-disabling drive a legacy grey-out sync. */
        if ( ! variations ) {
            groups.forEach( function ( g ) { g.classList.remove( 'j5-vg-hidden' ); } );
        }

        function clearAfter( index ) {
            groups.forEach( function ( g, j ) {
                if ( j <= index ) { return; }
                var s = groupSelect( g );
                if ( s && s.value ) { s.value = ''; }
                groupOptions( g ).forEach( function ( o ) { o.classList.remove( 'active' ); } );
            } );
        }

        function triggerWoo( select ) {
            if ( window.jQuery ) {
                window.jQuery( select ).trigger( 'change' );
            } else {
                select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
            }
        }

        groups.forEach( function ( group, i ) {
            var select = groupSelect( group );
            if ( ! select ) { return; }

            /* Button-style options (pills + radio cards). */
            groupOptions( group ).forEach( function ( opt ) {
                opt.addEventListener( 'click', function ( e ) {
                    e.preventDefault();
                    var value = opt.getAttribute( 'data-value' );
                    if ( opt.classList.contains( 'active' ) ) {
                        select.value = '';
                    } else {
                        select.value = value;
                    }
                    clearAfter( i );
                    refresh();
                    triggerWoo( select );
                } );
            } );

            /* Visible dropdown displays: the select IS the UI. */
            if ( group.getAttribute( 'data-display' ) === 'dropdown' ) {
                select.addEventListener( 'change', function () {
                    clearAfter( i );
                    refresh();
                } );
            }
        } );

        /* ----- Woo event wiring: banner, price, legacy disabled-sync ----- */
        if ( window.jQuery ) {
            var $form = window.jQuery( form );

            $form.on( 'found_variation', function ( event, v ) {
                if ( ! v ) { return; }
                var vState = v.j5_stock_state || ( v.is_in_stock ? 'in' : 'out' );
                if ( vState === 'special' ) {
                    var cS = bannerCopy( 'special', 'SPECIAL ORDER', 'made to order \u00b7 extended lead time' );
                    setBanner( 'special', cS.label, cS.note );
                } else if ( vState === 'in' ) {
                    var cI = bannerCopy( 'in_stock', 'IN STOCK', 'ready to ship' );
                    var maxQty = parseInt( v.max_qty, 10 );
                    var qtyText = cI.note;
                    if ( ! isNaN( maxQty ) && maxQty > 0 ) {
                        qtyText = maxQty <= 10 ? 'only ' + maxQty + ' left' : maxQty + '+ available';
                    }
                    setBanner( 'in', cI.label, qtyText );
                } else {
                    var cO = bannerCopy( 'out', 'OUT OF STOCK', 'contact us for ETA' );
                    setBanner( 'out', cO.label, cO.note );
                }
                if ( priceEl && v.price_html ) {
                    priceEl.innerHTML = '<span class="j5-price-eyebrow">Your price</span>' + v.price_html;
                }
            } );

            $form.on( 'hide_variation', function () {
                bannerSelectState();
                if ( priceEl ) { priceEl.innerHTML = parentPriceHTML; }
            } );

            $form.on( 'reset_data', function () {
                bannerSelectState();
                if ( priceEl ) { priceEl.innerHTML = parentPriceHTML; }
                refresh();
            } );

            /* Legacy fallback sync when no variations JSON: grey pills whose
             * <option> Woo disabled. */
            if ( ! variations ) {
                $form.on( 'woocommerce_update_variation_values', function () {
                    setTimeout( function () {
                        groups.forEach( function ( group ) {
                            var select = groupSelect( group );
                            if ( ! select ) { return; }
                            groupOptions( group ).forEach( function ( o ) {
                                var opt = select.querySelector( 'option[value="' + CSS.escape( o.getAttribute( 'data-value' ) ) + '"]' );
                                o.classList.toggle( 'j5-opt-oos', !! ( opt && opt.disabled ) );
                            } );
                        } );
                    }, 50 );
                } );
            }
        }

        refresh();
    } )();

    /* ============ 8. STICKY MOBILE CART ============ */
    var stickyCart = $( '#j5StickyCart' );
    var mainAddBtn = $( '.j5-add-to-cart-btn' ) || $( 'button.single_add_to_cart_button' );

    /* Suppress the sticky cart when purchase is blocked (out / discontinued):
     * the main control is a disabled button, so a floating "Add to Cart" bar
     * would lead nowhere. */
    var cartBlocked = !!$( '.j5-add-to-cart-row.j5-cart-blocked' );

    if ( stickyCart && mainAddBtn && ! cartBlocked ) {
        var observer = new IntersectionObserver( function( entries ) {
            entries.forEach( function( entry ) {
                if ( entry.isIntersecting ) {
                    stickyCart.classList.remove( 'visible' );
                } else {
                    // Only show on mobile viewports
                    if ( window.innerWidth <= 700 ) {
                        stickyCart.classList.add( 'visible' );
                    }
                }
            } );
        }, { threshold: 0 } );

        observer.observe( mainAddBtn );

        var stickyBtn = $( '#j5StickyBtn' );
        if ( stickyBtn ) {
            stickyBtn.addEventListener( 'click', function() {
                // Scroll to main add-to-cart or click it
                mainAddBtn.scrollIntoView( { behavior: 'smooth', block: 'center' } );
                setTimeout( function() { mainAddBtn.focus(); }, 400 );
            } );
        }
    }

    /* ============ 9. HOVER QUICK-VIEW BUTTONS (stub) ============ */
    // Prevent default for placeholder compare/wishlist buttons — Phase B hookup
    $$( '.j5-prod-quick button' ).forEach( function( btn ) {
        btn.addEventListener( 'click', function( e ) {
            e.preventDefault();
            e.stopPropagation();
            // Phase B: wire to compare plugin / wishlist plugin / native endpoints
        } );
    } );

} )();

/* ============================================================================
 *  Grid / List view toggle - Batch 2 2026-04-19
 * ========================================================================== */
(function(){
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.j5-view-toggle button[data-j5-view]');
        if (!btn) return;

        var toggle = btn.parentElement;
        toggle.querySelectorAll('button').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');

        var view = btn.getAttribute('data-j5-view');
        var grid = document.querySelector('.j5-prod-grid');
        if (!grid) return;

        if (view === 'list') {
            grid.classList.add('j5-view-list');
            grid.classList.remove('j5-view-grid');
        } else {
            grid.classList.remove('j5-view-list');
            grid.classList.add('j5-view-grid');
        }

        try { localStorage.setItem('j5_shop_view', view); } catch (err) {}
    });

    // Restore saved view on load
    try {
        var saved = localStorage.getItem('j5_shop_view');
        if (saved === 'list') {
            var btn = document.querySelector('.j5-view-toggle button[data-j5-view="list"]');
            if (btn) btn.click();
        }
    } catch (err) {}
})();


/* ============================================================
 * J5-VARIATION-IMAGE-PATCH-1 (2026-04-21)
 * Swap the main product gallery image when a variation is found.
 *
 * Context: our single-product template (both the migrated
 * woocommerce/single-product.php and the theme-root
 * single-j5-product.php) renders the main gallery image as:
 *     <img id="j5-gallery-main-img" src="...">
 *
 * WooCommerce's built-in variation image swap code looks for
 * .woocommerce-product-gallery__image and our template doesn't
 * use that class, so the WC swap is a no-op on our pages.
 *
 * This listener hooks the jQuery `found_variation` event that
 * wc-add-to-cart-variation.js fires when a user-picked combination
 * maps to a real variation, reads v.image.src, and writes it to
 * our #j5-gallery-main-img element.
 *
 * On `reset_data` we intentionally DO NOT revert the image — the
 * last-selected image stays shown until the user picks a new one.
 * Reverting mid-flow is jarring and unnecessary; the user only
 * resets when they're about to pick something else.
 * ============================================================ */
/* J5-VARIATION-IMAGE-PATCH-1-START */
( function () {
	if ( ! window.jQuery ) { return; }
	var $ = window.jQuery;

	$( function () {
		var $form = $( '.j5-variations-form' );
		if ( ! $form.length ) { return; }

		// Cache the original src on first run so we can detect whether
		// WC has populated a real image. (Not used for revert, but
		// guards against WC passing empty/placeholder images.)
		var $img = $( '#j5-gallery-main-img' );
		if ( ! $img.length ) { return; }
		var originalSrc = $img.attr( 'src' );

		$form.on( 'found_variation', function ( event, variation ) {
			if ( ! variation || ! variation.image || ! variation.image.src ) {
				return;
			}
			var newSrc = variation.image.src;
			// Only update if different — avoids unnecessary re-render
			// and layout shift when the same variation is re-selected.
			if ( newSrc && newSrc !== $img.attr( 'src' ) ) {
				$img.attr( 'src', newSrc );
				// Update alt text if WC supplied one
				if ( variation.image.alt ) {
					$img.attr( 'alt', variation.image.alt );
				}
			}
		} );
	} );
} )();
/* J5-VARIATION-IMAGE-PATCH-1-END */
