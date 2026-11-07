/**
 * J5 ArmorSync units on the product page. Markup shell: j5as_render() in inc/j5-armorsync-units.php.
 * After cut and size are chosen, asks wc-ajax=j5_as_units for that variation's in-stock
 * units and lists them under "Factory new". Picking one posts j5_as_unit with Add to Cart
 * and locks quantity at 1. Nothing here is in the page HTML, so search engines never see it.
 */
( function ( $ ) {
	'use strict';

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	$( function () {
		var $box = $( '[data-j5-as]' );
		if ( ! $box.length ) { return; }
		var $form = $box.closest( 'form.variations_form' );
		var $list = $box.find( '[data-j5-as-list]' );
		var $note = $box.find( '[data-j5-as-note]' );
		var $input = $box.find( '[data-j5-as-input]' );
		var endpoint = $box.attr( 'data-endpoint' );
		var factory = $box.attr( 'data-factory' ) !== '0';
		var $help = $box.find( '[data-j5-as-help]' );
		var name = 'j5as-' + ( $form.attr( 'data-product_id' ) || '0' );
		var seq = 0, cache = {};

		function qtyLock( on ) {
			var $q = $form.find( 'input[name="quantity"]' );
			if ( on ) { $q.val( 1 ); }
			$q.prop( 'readonly', on ).attr( 'aria-readonly', on ? 'true' : null );
			$form.find( '.j5-qty-btn' ).prop( 'disabled', on );
			$form.find( '.j5-qty-wrap' ).toggleClass( 'is-locked', on );
		}

		function pick( id ) {
			$input.val( id || '' );
			qtyLock( !! id );
			// The factory order callout doesn't apply to a plate from the shelf.
			$( '[data-j5-fs]' ).toggleClass( 'j5-as-hide', !! id );
			$list.find( '.j5-as__choice' ).each( function () {
				$( this ).toggleClass( 'is-on', $( this ).find( 'input' ).val() === ( id || '' ) );
			} );
		}

		function clear() {
			seq++;
			pick( '' );
			$list.empty();
			$note.prop( 'hidden', true );
			$box.prop( 'hidden', true );
		}

		function row( u, i ) {
			var id = name + '-' + ( i + 1 );
			var sn = ( u.serials || [] ).slice().sort( function ( a, b ) { return String( a ).localeCompare( String( b ), undefined, { numeric: true } ); } );
			var sub = [];
			if ( sn.length === 1 ) { sub.push( 'SN: ' + esc( sn[ 0 ] ) ); }
			else if ( sn.length > 1 ) { sub.push( sn.map( function ( s, k ) { return 'Plate ' + ( k + 1 ) + ' SN: ' + esc( s ); } ).join( ' <span class="j5-as__sep" aria-hidden="true">|</span> ' ) ); }
			if ( u.made ) { sub.push( 'MFG Date: ' + esc( u.made ) ); }
			return '<label class="j5-as__choice" for="' + id + '">'
				+ '<input type="radio" id="' + id + '" name="' + name + '" value="' + esc( u.id ) + '" />'
				+ '<span class="j5-as__text">'
					+ '<span class="j5-as__label">In stock</span>'
					+ sub.map( function ( t ) { return '<span class="j5-as__desc">' + t + '</span>'; } ).join( '' )
				+ '</span>'
				+ ( u.pct > 0 ? '<span class="j5-as__pct">' + esc( u.pct ) + '% off</span>' : '' )
			+ '</label>';
		}

		function render( units ) {
			if ( ! units || ! units.length ) { clear(); return; }
			var n = units.length, html = '';
			var left = n + ( n === 1 ? ' plate' : ' plates' );
			if ( factory ) {
				html = '<label class="j5-as__choice is-on" for="' + name + '-0">'
					+ '<input type="radio" id="' + name + '-0" name="' + name + '" value="" checked />'
					+ '<span class="j5-as__text"><span class="j5-as__label">Factory new</span>'
					+ '<span class="j5-as__desc">Newest production, lead time as shown above</span></span>'
				+ '</label>';
				$help.html( 'Order factory new, or pick one of the <strong>' + left + '</strong> we have on the shelf. Older plates are discounted by age.' );
			} else {
				$help.html( '<strong>' + ( n === 1 ? 'Last plate' : left + ' remaining' ) + '.</strong> Pick the one you want. Older plates are discounted by age.' );
			}
			for ( var i = 0; i < n; i++ ) { html += row( units[ i ], i ); }
			$list.html( html );
			$note.prop( 'hidden', ! units.some( function ( u ) { return u.pct > 0; } ) );
			$box.prop( 'hidden', false );
			if ( factory ) { pick( '' ); }
			else {
				// No factory option: start on the first plate (biggest discount, from the DP's order).
				$list.find( 'input[type="radio"]' ).first().prop( 'checked', true );
				pick( units[ 0 ].id );
			}
		}

		function load( vid ) {
			var mine = ++seq;
			pick( '' );
			if ( cache[ vid ] && Date.now() - cache[ vid ].t < 30000 ) { render( cache[ vid ].u ); return; }
			$.ajax( { url: endpoint, data: { variation_id: vid }, dataType: 'json', timeout: 8000 } )
				.done( function ( r ) {
					if ( mine !== seq ) { return; }
					var units = r && r.units ? r.units : [];
					cache[ vid ] = { t: Date.now(), u: units };
					render( units );
				} )
				.fail( function () { if ( mine === seq ) { clear(); } } );
		}

		$list.on( 'change', 'input[type="radio"]', function () { pick( this.value ); } );

		$form
			.on( 'found_variation', function ( e, v ) {
				if ( v && v.variation_id && v.is_purchasable !== false ) { load( v.variation_id ); }
				else { clear(); }
			} )
			.on( 'hide_variation reset_data', clear );

		// The size may already be chosen when the page loads (one option, or a default), and
		// Woo announces it before this script is listening. Pick that up.
		var vid0 = parseInt( $form.find( 'input.variation_id' ).val(), 10 );
		if ( vid0 > 0 ) { load( vid0 ); }
		else { setTimeout( function () { var v = parseInt( $form.find( 'input.variation_id' ).val(), 10 ); if ( v > 0 && ! $input.val() && $box.prop( 'hidden' ) ) { load( v ); } }, 400 ); }

		// Back after a failed add to cart: drop the stale cache so a sold plate disappears.
		$( document.body ).on( 'added_to_cart wc_fragments_refreshed', function () { cache = {}; } );
	} );
} )( jQuery );
