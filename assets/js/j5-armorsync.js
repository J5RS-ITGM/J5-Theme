/**
 * J5 ArmorSync units on the product page. Markup shell: j5as_render() in inc/j5-armorsync-units.php.
 * After cut and size are chosen, asks wc-ajax=j5_as_units for that variation's in-stock units.
 *
 * J5AS-B (2026-11-08): two price cards instead of one row per unit.
 *   [ Standard  $1,242.00 ]  [ Shelf set  $1,117.80  SAVE 10% ]
 *   Serial pair: [ ...0884 / ...0885 · Oct 2024 · $1,117.80 v ]
 * "Standard" posts no unit (ships from stock or factory as usual; not shown on Final Stock).
 * "Shelf set" posts the unit picked in the dropdown as j5_as_unit and locks quantity at 1.
 * Card prices are display only: the cart's Plate Age Discount coupon is what charges them.
 * Nothing here is in the page HTML, so search engines never see it.
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
		var $help = $box.find( '[data-j5-as-help]' );
		var $legend = $box.find( '.j5-as__name' );
		var endpoint = $box.attr( 'data-endpoint' );
		var factory = $box.attr( 'data-factory' ) !== '0';
		var name = 'j5as-' + ( $form.attr( 'data-product_id' ) || '0' );
		var SHELF = '__shelf';
		var seq = 0, cache = {}, units = [], price = 0;

		var cur = $form.attr( 'data-j5-currency' ) || '$';
		var dec = parseInt( $form.attr( 'data-j5-decimals' ), 10 );
		if ( isNaN( dec ) ) { dec = 2; }
		var $btn = $form.find( '.single_add_to_cart_button' ).first();
		var btnText = $btn.text();

		function money( n ) {
			return cur + Number( n ).toLocaleString( 'en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec } );
		}
		function unitPrice( u ) {
			var f = Math.pow( 10, dec );
			return Math.round( price * ( 100 - ( u.pct || 0 ) ) / 100 * f ) / f;
		}
		function byId( id ) {
			for ( var i = 0; i < units.length; i++ ) { if ( units[ i ].id === id ) { return units[ i ]; } }
			return null;
		}
		function variationPrice( vid ) {
			var all = $form.data( 'product_variations' ) || [];
			for ( var i = 0; i < all.length; i++ ) {
				if ( parseInt( all[ i ].variation_id, 10 ) === vid ) { return parseFloat( all[ i ].display_price ) || 0; }
			}
			return 0;
		}

		function qtyLock( on ) {
			var $q = $form.find( 'input[name="quantity"]' );
			if ( on ) { $q.val( 1 ); }
			$q.prop( 'readonly', on ).attr( 'aria-readonly', on ? 'true' : null );
			$form.find( '.j5-qty-btn' ).prop( 'disabled', on );
			$form.find( '.j5-qty-wrap' ).toggleClass( 'is-locked', on );
		}

		/** id = unit id to post, or '' for Standard / nothing. */
		function pick( id ) {
			var u = id ? byId( id ) : null;
			$input.val( u ? u.id : '' );
			qtyLock( !! u );
			// The factory order callout doesn't apply to a plate from the shelf.
			$( '[data-j5-fs]' ).toggleClass( 'j5-as-hide', !! u );
			var shelfOn = $list.find( 'input[value="' + SHELF + '"]' ).prop( 'checked' );
			$list.find( '.j5-as__card' ).each( function () {
				$( this ).toggleClass( 'is-on', $( this ).find( 'input' ).prop( 'checked' ) );
			} );
			$list.find( '[data-j5-as-pick]' ).prop( 'hidden', ! shelfOn );
			if ( u && price > 0 ) {
				$list.find( '[data-j5-as-shelf-price]' ).text( money( unitPrice( u ) ) );
				$btn.text( 'Add to Cart · ' + money( unitPrice( u ) ) );
			} else {
				$btn.text( btnText );
			}
			$note.prop( 'hidden', ! ( u && u.pct > 0 ) );
		}

		function clear() {
			seq++;
			units = [];
			pick( '' );
			$list.empty();
			$note.prop( 'hidden', true );
			$box.prop( 'hidden', true );
		}

		function optionText( u ) {
			var sn = ( u.serials || [] ).slice().sort( function ( a, b ) { return String( a ).localeCompare( String( b ), undefined, { numeric: true } ); } );
			var bits = [];
			if ( sn.length ) { bits.push( sn.map( function ( s ) { s = String( s ); return '…' + s.slice( -4 ); } ).join( ' / ' ) ); }
			if ( u.made ) { bits.push( u.made ); }
			if ( price > 0 ) { bits.push( money( unitPrice( u ) ) ); }
			else if ( u.pct > 0 ) { bits.push( u.pct + '% off' ); }
			if ( u.acq ) { bits.push( 'Acq ' + u.acq ); } // staff only (sent only to logged-in staff)
			return bits.join( ' · ' );
		}

		function render( list ) {
			if ( ! list || ! list.length ) { clear(); return; }
			units = list;
			var sets = units.some( function ( u ) { return u.kind === 'set'; } );
			var noun = sets ? 'set' : 'plate';
			var pcts = units.map( function ( u ) { return u.pct || 0; } );
			var maxPct = Math.max.apply( null, pcts );
			var samePct = pcts.every( function ( p ) { return p === pcts[ 0 ]; } );
			var months = units.map( function ( u ) { return u.made; } ).filter( Boolean );
			var uniq = months.filter( function ( m, i ) { return months.indexOf( m ) === i; } )
				.sort( function ( a, b ) { return ( Date.parse( '1 ' + a ) || 0 ) - ( Date.parse( '1 ' + b ) || 0 ); } );
			var made = uniq.length === 1 ? 'Made ' + uniq[ 0 ] : ( uniq.length ? 'Made ' + uniq[ 0 ] + ' to ' + uniq[ uniq.length - 1 ] : '' );
			var left = units.length + ' left';
			var shelfPrice = '';
			if ( price > 0 ) {
				var best = units.reduce( function ( a, b ) { return ( b.pct || 0 ) > ( a.pct || 0 ) ? b : a; } );
				shelfPrice = ( samePct ? '' : 'From ' ) + money( unitPrice( best ) );
			}
			var badge = maxPct > 0 ? '<span class="j5-as__badge">Save ' + ( samePct ? '' : 'up to ' ) + esc( maxPct ) + '%</span>' : '';

			$legend.text( 'Choose your ' + noun );
			var html = '<div class="j5-as__cards' + ( factory ? '' : ' is-solo' ) + '">';
			if ( factory ) {
				html += '<label class="j5-as__card is-on" for="' + name + '-std">'
					+ '<span class="j5-as__card-head"><input type="radio" id="' + name + '-std" name="' + name + '" value="" checked /><span class="j5-as__card-name">Standard</span></span>'
					+ ( price > 0 ? '<span class="j5-as__card-price">' + esc( money( price ) ) + '</span>' : '' )
					+ '<span class="j5-as__card-sub">Newest production</span>'
				+ '</label>';
			}
			html += '<label class="j5-as__card j5-as__card--shelf" for="' + name + '-shelf">'
				+ badge
				+ '<span class="j5-as__card-head"><input type="radio" id="' + name + '-shelf" name="' + name + '" value="' + SHELF + '" /><span class="j5-as__card-name">Shelf ' + noun + '</span></span>'
				+ ( shelfPrice ? '<span class="j5-as__card-price" data-j5-as-shelf-price>' + esc( shelfPrice ) + '</span>' : '<span data-j5-as-shelf-price hidden></span>' )
				+ '<span class="j5-as__card-sub">' + esc( made ? made + ' · ' + left : left ) + '</span>'
			+ '</label></div>';

			html += '<label class="j5-as__pick" data-j5-as-pick hidden><span class="j5-as__pick-label">' + ( sets ? 'Serial pair' : 'Serial number' ) + '</span>'
				+ '<select class="j5-as__select" data-j5-as-select>'
				+ units.map( function ( u ) { return '<option value="' + esc( u.id ) + '">' + esc( optionText( u ) ) + '</option>'; } ).join( '' )
				+ '</select></label>';

			$list.html( html );

			if ( factory ) {
				$help.text( '' ).prop( 'hidden', true );
				pick( '' );
			} else {
				// Final stock: these are all that's left. No Standard card; the shelf card starts picked.
				var model = $box.attr( 'data-model' ) || '';
				$help.html( '<strong>' + ( model ? 'Be our last customer to ever buy a ' + esc( model ) + '.' : 'These are the last ones we’ll ever sell.' ) + '</strong> Older ' + noun + 's are discounted by age.' ).prop( 'hidden', false );
				$list.find( 'input[value="' + SHELF + '"]' ).prop( 'checked', true );
				pick( units[ 0 ].id );
			}
			$box.prop( 'hidden', false );
		}

		function load( vid ) {
			var mine = ++seq;
			price = variationPrice( vid ) || price;
			pick( '' );
			if ( cache[ vid ] && Date.now() - cache[ vid ].t < 30000 ) { render( cache[ vid ].u ); return; }
			$.ajax( { url: endpoint, data: { variation_id: vid }, dataType: 'json', timeout: 8000 } )
				.done( function ( r ) {
					if ( mine !== seq ) { return; }
					var list = r && r.units ? r.units : [];
					cache[ vid ] = { t: Date.now(), u: list };
					render( list );
				} )
				.fail( function () { if ( mine === seq ) { clear(); } } );
		}

		$list.on( 'change', 'input[type="radio"]', function () {
			pick( this.value === SHELF ? $list.find( '[data-j5-as-select]' ).val() : '' );
		} );
		$list.on( 'change', '[data-j5-as-select]', function () {
			if ( $list.find( 'input[value="' + SHELF + '"]' ).prop( 'checked' ) ) { pick( this.value ); }
		} );

		$form
			.on( 'found_variation', function ( e, v ) {
				if ( v && v.variation_id && v.is_purchasable !== false ) {
					price = parseFloat( v.display_price ) || 0;
					load( parseInt( v.variation_id, 10 ) );
				} else { clear(); }
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
