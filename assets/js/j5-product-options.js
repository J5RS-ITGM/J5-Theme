/**
 * J5 Product Options: live price + required-group check.
 * Markup from inc/j5-product-options.php (j5_render_product_options).
 *
 * Price = base + sum of chosen data-adjust.
 *   simple   base = data-base on .j5-opts
 *   variable base = display_price of the variation WC found; until one is
 *            found the parent "From" price is left alone.
 * Writes the same element j5-shop.js writes on found_variation
 * (.j5-product-price), always after it, so the two don't fight.
 */
( function () {
	'use strict';

	function init( wrap ) {
		var form = wrap.closest( 'form' );
		if ( ! form ) { return; }
		var isVariable = form.classList.contains( 'variations_form' );
		var priceEl  = document.querySelector( '.j5-product-price' );
		var stickyEl = document.querySelector( '.j5-sticky-price' );
		var origPrice  = priceEl ? priceEl.innerHTML : '';
		var origSticky = stickyEl ? stickyEl.innerHTML : '';

		var cur  = wrap.getAttribute( 'data-currency' ) || '$';
		var dec  = parseInt( wrap.getAttribute( 'data-decimals' ), 10 );
		if ( isNaN( dec ) ) { dec = 2; }
		var dSep = wrap.getAttribute( 'data-dec-sep' ) || '.';
		var tSep = wrap.getAttribute( 'data-thou-sep' ) || ',';

		var base = isVariable ? null : parseFloat( wrap.getAttribute( 'data-base' ) );
		if ( ! isVariable && isNaN( base ) ) { base = null; }
		var wcHTML = null; // price HTML j5-shop.js wrote for the found variation

		function money( n ) {
			var parts = Math.abs( n ).toFixed( dec ).split( '.' );
			parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, tSep );
			return ( n < 0 ? '-' : '' ) + cur + parts.join( dSep );
		}

		function adjustTotal() {
			var t = 0;
			wrap.querySelectorAll( 'input:checked, select' ).forEach( function ( el ) {
				var src = el.tagName === 'SELECT' ? el.options[ el.selectedIndex ] : el;
				var v = src ? parseFloat( src.getAttribute( 'data-adjust' ) ) : 0;
				if ( ! isNaN( v ) ) { t += v; }
			} );
			return Math.round( t * 100 ) / 100;
		}

		function render() {
			if ( ! priceEl ) { return; }
			var adj = adjustTotal();
			if ( base === null ) { return; } // variable, no variation yet
			if ( Math.abs( adj ) < 0.005 ) {
				priceEl.innerHTML = isVariable && wcHTML !== null ? wcHTML : origPrice;
				if ( stickyEl ) { stickyEl.innerHTML = origSticky; }
				return;
			}
			var amount = '<span class="woocommerce-Price-amount amount"><bdi>' + money( Math.max( 0, base + adj ) ) + '</bdi></span>';
			priceEl.innerHTML = '<span class="j5-price-eyebrow">Your price</span>' + amount + '<span class="j5-opts-price-note">includes options</span>';
			if ( stickyEl ) { stickyEl.innerHTML = amount; }
		}

		function groupFilled( fs ) {
			var sel = fs.querySelector( 'select' );
			if ( sel ) { return sel.value !== ''; }
			var hit = false;
			fs.querySelectorAll( 'input:checked' ).forEach( function ( i ) { if ( i.value !== '' ) { hit = true; } } );
			return hit;
		}

		function check( show ) {
			var first = null;
			wrap.querySelectorAll( '.j5-opts__group[data-required="1"]' ).forEach( function ( fs ) {
				var ok = groupFilled( fs );
				var err = fs.querySelector( '.j5-opts__err' );
				if ( ok ) {
					fs.classList.remove( 'is-invalid' );
					if ( err ) { err.hidden = true; }
				} else if ( show ) {
					fs.classList.add( 'is-invalid' );
					if ( err ) { err.hidden = false; }
					if ( ! first ) { first = fs; }
				}
			} );
			return first;
		}

		wrap.addEventListener( 'change', function () {
			render();
			check( false );
		} );

		// Capture phase: runs before WC / theme submit handlers.
		form.addEventListener( 'submit', function ( e ) {
			var bad = check( true );
			if ( bad ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				bad.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				var f = bad.querySelector( 'input, select' );
				if ( f ) { f.focus( { preventScroll: true } ); }
			}
		}, true );

		if ( isVariable && window.jQuery ) {
			var $form = window.jQuery( form );
			$form.on( 'found_variation', function ( e, v ) {
				// Let j5-shop.js write the variation price first.
				setTimeout( function () {
					var p = v ? parseFloat( v.display_price ) : NaN;
					base = isNaN( p ) ? null : p;
					wcHTML = priceEl ? priceEl.innerHTML : null;
					render();
				}, 0 );
			} );
			$form.on( 'hide_variation reset_data', function () {
				base = null;
				wcHTML = null;
				setTimeout( function () { if ( stickyEl ) { stickyEl.innerHTML = origSticky; } }, 0 );
			} );
		}

		render();
	}

	function boot() {
		document.querySelectorAll( '[data-j5-opts]' ).forEach( init );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
