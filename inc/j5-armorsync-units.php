<?php
/**
 * J5 ArmorSync units on the product page (WC ArmorSync Units Contract 1, 2026-11-06).
 *
 * The DP (dealer.j5rescue.com) is the system of record for serialized plates.
 * WooCommerce stores no units: after the customer picks cut and size, the page
 * asks WC (wc-ajax=j5_as_units), WC asks the DP live, and the list shows
 * "Standard order" (not on Final Stock products) plus each in-stock unit the DP's website rules display (masked serials, month made, % off).
 * Nothing is in page HTML, schema, sitemap or feeds.
 *
 * Picking a unit:
 *   - add to cart: WC re-reads the unit from the DP (never trusts the post),
 *     holds it for this cart (POST /hold), forces quantity 1.
 *   - cart: virtual auto coupon "plate-age-discount" (Plate Age Discount) takes discount_pct off
 *     that line only. Factory order savings never applies to a unit line.
 *   - checkout: hold again (extends it); a 409 stops checkout with a message.
 *   - order line meta: _j5_as_unit_id, _j5_as_serials, _j5_as_mfg,
 *     _j5_as_discount_pct (the DP reads these on import) + a visible "Plate" line.
 *   - removed from cart: POST /release.
 *
 * Off unless wp-config defines J5_ARMORSYNC_SECRET (same value as ArmorSync >
 * Settings > Website Units). Optional J5_ARMORSYNC_URL overrides the DP base URL.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5AS_CART_KEY = 'j5_as';
const J5AS_CODE     = 'plate-age-discount';
const J5AS_LABEL    = 'Plate Age Discount';
const J5AS_OLD_CODE = 'in-stock-plate-savings'; // pre-2026-11-07 name; dropped from live carts, still named on old orders
const J5AS_HOLD_MIN = 30;

/* =========================================================================
 * 1. DP CLIENT
 * ====================================================================== */

function j5as_on() {
	return defined( 'J5_ARMORSYNC_SECRET' ) && '' !== (string) J5_ARMORSYNC_SECRET;
}

function j5as_base() {
	$base = defined( 'J5_ARMORSYNC_URL' ) && J5_ARMORSYNC_URL ? J5_ARMORSYNC_URL : 'https://dealer.j5rescue.com';
	return untrailingslashit( $base ) . '/api/armorsync';
}

/** This cart's reference for holds. Empty when there is no WC session. */
function j5as_wc_ref() {
	if ( function_exists( 'WC' ) && WC()->session ) {
		return (string) WC()->session->get_customer_id();
	}
	return '';
}

/**
 * Call the DP. Returns array( 'code' => int, 'body' => array ) or WP_Error.
 *
 * @param string $method GET|POST
 * @param string $path   /available, /hold, /release
 * @param array  $data   query (GET) or JSON body (POST)
 * @param bool   $wait   false = fire and forget
 */
function j5as_call( $method, $path, $data = array(), $wait = true ) {
	if ( ! j5as_on() ) {
		return new WP_Error( 'j5as_off', 'ArmorSync is not configured.' );
	}
	$url  = j5as_base() . $path;
	$args = array(
		'timeout'  => 'GET' === $method ? 4 : 6,
		'blocking' => $wait,
		'headers'  => array(
			'X-J5-ArmorSync-Secret' => (string) J5_ARMORSYNC_SECRET,
			'Accept'                => 'application/json',
		),
	);
	if ( 'GET' === $method ) {
		$res = wp_remote_get( add_query_arg( array_map( 'rawurlencode', $data ), $url ), $args );
	} else {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $data );
		$res                             = wp_remote_post( $url, $args );
	}
	if ( is_wp_error( $res ) || ! $wait ) {
		return $res;
	}
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	return array(
		'code' => (int) wp_remote_retrieve_response_code( $res ),
		'body' => is_array( $body ) ? $body : array(),
	);
}

/** Keep only the last 4 characters of a serial (the DP already masks; this is a backstop). */
function j5as_mask( $serial ) {
	$s = trim( (string) $serial );
	if ( preg_match( '/^X{2,}/i', $s ) ) {
		return strtoupper( substr( $s, 0, -4 ) ) . substr( $s, -4 );
	}
	return strlen( $s ) > 4 ? 'XXXX' . substr( $s, -4 ) : $s;
}

/** "2025-03" -> "Mar 2025". */
function j5as_month( $ym ) {
	$d = DateTime::createFromFormat( '!Y-m', substr( (string) $ym, 0, 7 ) );
	return $d ? $d->format( 'M Y' ) : '';
}

/** ArmorSync acquisition number: short plain text only. */
function j5as_acq_clean( $v ) {
	return substr( preg_replace( '/[^A-Za-z0-9 #._\/\-]/', '', trim( (string) $v ) ), 0, 40 );
}

/** "A-1042" when every plate shares one, else "Plate 1: A-1042 | Plate 2: A-1043". */
function j5as_acq_text( $acqs ) {
	$acqs = array_values( array_filter( (array) $acqs, 'strlen' ) );
	if ( ! $acqs ) {
		return '';
	}
	if ( 1 === count( array_unique( $acqs ) ) ) {
		return $acqs[0];
	}
	$out = array();
	foreach ( $acqs as $i => $a ) {
		$out[] = 'Plate ' . ( $i + 1 ) . ': ' . $a;
	}
	return implode( ' | ', $out );
}

/** One DP unit -> the clean record WC keeps (no prices). Null when unusable. */
function j5as_clean_unit( $u ) {
	if ( ! is_array( $u ) || empty( $u['unit_id'] ) ) {
		return null;
	}
	$id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $u['unit_id'] );
	if ( '' === $id ) {
		return null;
	}
	// Serials (masked) paired with ArmorSync acquisition numbers (internal, never sent to the browser),
	// then sorted lower serial first so Plate 1 / Plate 2 stay matched to their acquisition #.
	$raw  = array_values( (array) ( $u['serials'] ?? array() ) );
	$acqs = isset( $u['acquisition_nos'] ) ? array_values( (array) $u['acquisition_nos'] ) : array();
	$one  = isset( $u['acquisition_no'] ) ? j5as_acq_clean( $u['acquisition_no'] ) : '';
	$rows = array();
	foreach ( $raw as $i => $sn ) {
		$m = j5as_mask( $sn );
		if ( '' === $m ) {
			continue;
		}
		$rows[] = array( 'sn' => $m, 'acq' => isset( $acqs[ $i ] ) ? j5as_acq_clean( $acqs[ $i ] ) : $one );
	}
	usort( $rows, function ( $a, $b ) { return strnatcasecmp( $a['sn'], $b['sn'] ); } );
	$serials = array_column( $rows, 'sn' );
	$acq     = array_column( $rows, 'acq' );
	if ( ! $rows && '' !== $one ) {
		$acq = array( $one );
	}
	$pct     = isset( $u['discount_pct'] ) ? (int) round( (float) $u['discount_pct'] ) : 0;
	return array(
		'unit_id'        => $id,
		'kind'           => 'set' === ( $u['kind'] ?? '' ) ? 'set' : 'single',
		'serials'        => $serials,
		'acquisitions'   => array_values( array_filter( $acq, 'strlen' ) ) ? $acq : array(),
		'mfg_date'       => preg_match( '/^\d{4}-\d{2}/', (string) ( $u['mfg_date'] ?? '' ) ) ? substr( $u['mfg_date'], 0, 7 ) : '',
		'age_months'     => isset( $u['age_months'] ) ? max( 0, (int) $u['age_months'] ) : null,
		'warranty_years' => isset( $u['warranty_years'] ) ? (float) $u['warranty_years'] : null,
		'warranty_ends'  => preg_match( '/^\d{4}-\d{2}/', (string) ( $u['warranty_ends'] ?? '' ) ) ? substr( $u['warranty_ends'], 0, 7 ) : '',
		'discount_pct'   => max( 0, min( 90, $pct ) ),
	);
}

/**
 * Available units for a SKU. Cached 60 s per SKU unless this cart holds one of
 * them (then live with wc_ref, so the held unit still lists).
 *
 * @return array|null list of clean units, or null when the DP did not answer
 */
function j5as_available( $sku, $live = false ) {
	$sku = (string) $sku;
	if ( '' === $sku || ! j5as_on() ) {
		return null;
	}
	$ref = j5as_wc_ref();
	if ( ! $live && $ref && j5as_cart_has_sku( $sku ) ) {
		$live = true;
	}
	$ck = 'j5as_' . md5( $sku );
	if ( ! $live ) {
		$hit = get_transient( $ck );
		if ( is_array( $hit ) ) {
			return $hit;
		}
	}
	$q = array( 'sku' => $sku );
	foreach ( j5as_cart_items() as $item ) {
		if ( ( $item[ J5AS_CART_KEY ]['sku'] ?? '' ) === $sku && ! empty( $item[ J5AS_CART_KEY ]['wc_ref'] ) ) {
			$ref = $item[ J5AS_CART_KEY ]['wc_ref']; // the ref this cart's hold is under
			break;
		}
	}
	if ( $ref ) {
		$q['wc_ref'] = $ref;
	}
	$res = j5as_call( 'GET', '/available', $q );
	if ( is_wp_error( $res ) || 200 !== $res['code'] ) {
		return null;
	}
	$units = array();
	foreach ( (array) ( $res['body']['units'] ?? array() ) as $u ) {
		$c = j5as_clean_unit( $u );
		if ( $c ) {
			$units[] = $c;
		}
	}
	if ( ! $live || ! j5as_cart_has_sku( $sku ) ) {
		set_transient( $ck, $units, 60 );
	}
	return $units;
}

/**
 * Hold a unit for this cart. $ref: the wc_ref the unit was first held under (kept on the
 * cart item), so the hold still matches after a guest logs in at checkout and WC's
 * session id changes.
 */
function j5as_hold( $unit_id, $ref = '' ) {
	$res = j5as_call( 'POST', '/hold', array( 'unit_id' => (string) $unit_id, 'wc_ref' => $ref ? $ref : j5as_wc_ref(), 'minutes' => J5AS_HOLD_MIN ) );
	if ( is_wp_error( $res ) ) {
		return 'error';
	}
	if ( 200 === $res['code'] && ! empty( $res['body']['ok'] ) ) {
		return 'ok';
	}
	if ( 409 === $res['code'] ) {
		$r = $res['body']['detail']['reason'] ?? ( $res['body']['reason'] ?? 'sold' );
		return 'held' === $r ? 'held' : 'sold';
	}
	return 'error';
}

function j5as_release( $unit_id, $ref = '' ) {
	j5as_call( 'POST', '/release', array( 'unit_id' => (string) $unit_id, 'wc_ref' => $ref ? $ref : j5as_wc_ref() ), false );
}

/* =========================================================================
 * 2. WHERE IT SHOWS
 * ====================================================================== */

/** Plate products (and anything the j5_as_enabled filter adds). */
function j5as_enabled_for( $product ) {
	if ( ! j5as_on() || ! $product instanceof WC_Product ) {
		return false;
	}
	$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
		return false;
	}
	$on = function_exists( 'j5_product_is_plate' ) ? j5_product_is_plate( $parent ) : has_term( 'body-armor', 'product_cat', $parent->get_id() );
	return (bool) apply_filters( 'j5_as_enabled', $on, $parent );
}

/* =========================================================================
 * 3. PRODUCT PAGE
 * ====================================================================== */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product = wc_get_product( get_queried_object_id() );
	if ( ! j5as_enabled_for( $product ) ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	wp_enqueue_style( 'j5-armorsync', $uri . '/assets/css/j5-armorsync.css', array(), (string) filemtime( $dir . '/assets/css/j5-armorsync.css' ) );
	wp_enqueue_script( 'j5-armorsync', $uri . '/assets/js/j5-armorsync.js', array( 'jquery' ), (string) filemtime( $dir . '/assets/js/j5-armorsync.js' ), true );
}, 30 );

/**
 * Empty shell inside the variations form (woocommerce/single-product.php).
 * The list is filled by assets/js/j5-armorsync.js after a variation is chosen.
 *
 * @param WC_Product $product
 */
function j5as_render( $product ) {
	if ( ! j5as_enabled_for( $product ) ) {
		return;
	}
	// Final stock / discontinued: the shelf sets ARE the remaining stock, so no "Standard order" row;
	// the customer picks one (first set pre-picked) and gets its age discount. Model ("4400") comes
	// from the first word in the product name that has a digit in it, for the "last customer" line.
	$f       = function_exists( 'j5_get_fulfillment' ) ? j5_get_fulfillment( $product ) : null;
	$factory = ! ( $f && in_array( $f['type'] ?? '', array( 'final_stock', 'discontinued', 'out' ), true ) );
	$model   = preg_match( '/(?<![A-Za-z0-9])([A-Za-z0-9-]*\d[A-Za-z0-9-]*)(?![A-Za-z0-9])/', wp_strip_all_tags( $product->get_name() ), $mm ) ? $mm[1] : '';
	printf(
		'<fieldset class="j5-as" data-j5-as data-endpoint="%s" data-factory="%s" data-model="%s" hidden>'
			. '<legend class="j5-as__name">Choose your plate</legend>'
			. '<p class="j5-as__help" data-j5-as-help></p>'
			. '<div class="j5-as__list" data-j5-as-list></div>'
			. '<p class="j5-as__note" data-j5-as-note hidden>The discount is applied in your cart.</p>'
			. '<input type="hidden" name="j5_as_unit" value="" data-j5-as-input />'
		. '</fieldset>',
		esc_url( WC_AJAX::get_endpoint( 'j5_as_units' ) ),
		$factory ? '1' : '0',
		esc_attr( $model )
	);
}

/** wc-ajax=j5_as_units&variation_id=N -> { units: [...] } (no prices, masked serials). */
function j5as_ajax_units() {
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	$vid = isset( $_GET['variation_id'] ) ? absint( $_GET['variation_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$var = $vid ? wc_get_product( $vid ) : null;
	// Published products for everyone; drafts only for staff previewing them.
	$pid = $var && $var->is_type( 'variation' ) ? $var->get_parent_id() : 0;
	$vis = $pid && ( 'publish' === get_post_status( $pid ) || current_user_can( 'edit_post', $pid ) );
	if ( ! $vis || ! j5as_enabled_for( $var ) ) {
		wp_send_json( array( 'units' => array() ) );
	}
	$units = j5as_available( $var->get_sku() );
	$out   = array();
	$staff = current_user_can( 'edit_shop_orders' ); // acquisition # is internal: staff see it, customers don't
	foreach ( (array) $units as $u ) {
		$row = array(
			'id'       => $u['unit_id'],
			'kind'     => $u['kind'],
			'serials'  => $u['serials'],
			'made'     => j5as_month( $u['mfg_date'] ),
			'warranty' => j5as_month( $u['warranty_ends'] ),
			'pct'      => $u['discount_pct'],
		);
		if ( $staff && ! empty( $u['acquisitions'] ) ) {
			$row['acq'] = j5as_acq_text( $u['acquisitions'] );
		}
		$out[] = $row;
	}
	wp_send_json( array( 'units' => $out, 'ok' => null !== $units ) );
}
add_action( 'wc_ajax_j5_as_units', 'j5as_ajax_units' );

/* =========================================================================
 * 4. ADD TO CART
 * ====================================================================== */

function j5as_posted_unit() {
	return isset( $_POST['j5_as_unit'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', wp_unslash( (string) $_POST['j5_as_unit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
}

function j5as_cart_items() {
	$out = array();
	if ( function_exists( 'WC' ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( ! empty( $item[ J5AS_CART_KEY ]['unit_id'] ) ) {
				$out[ $key ] = $item;
			}
		}
	}
	return $out;
}

function j5as_cart_has_sku( $sku ) {
	foreach ( j5as_cart_items() as $item ) {
		if ( ( $item[ J5AS_CART_KEY ]['sku'] ?? '' ) === $sku ) {
			return true;
		}
	}
	return false;
}

/** Unit picked: re-read it from the DP, hold it, stash it for the cart item. */
add_filter( 'woocommerce_add_to_cart_validation', function ( $passed, $product_id, $quantity = 1, $variation_id = 0 ) {
	$uid = j5as_posted_unit();
	if ( ! $passed || '' === $uid ) {
		return $passed;
	}
	$var = $variation_id ? wc_get_product( $variation_id ) : null;
	if ( ! $var || ! j5as_enabled_for( $var ) ) {
		wc_add_notice( 'That in-stock plate is not available for this option. Please choose again.', 'error' );
		return false;
	}
	foreach ( j5as_cart_items() as $item ) {
		if ( $item[ J5AS_CART_KEY ]['unit_id'] === $uid ) {
			wc_add_notice( 'That plate is already in your cart.', 'error' );
			return false;
		}
	}
	$units = j5as_available( $var->get_sku(), true );
	if ( null === $units ) {
		wc_add_notice( 'We could not check our in-stock plates just now. Please try again in a moment.', 'error' );
		return false;
	}
	$unit = null;
	foreach ( $units as $u ) {
		if ( $u['unit_id'] === $uid ) {
			$unit = $u;
			break;
		}
	}
	if ( ! $unit ) {
		wc_add_notice( 'Sorry, that plate was just sold. Please pick another one.', 'error' );
		return false;
	}
	if ( WC()->session && ! WC()->session->has_session() ) {
		WC()->session->set_customer_session_cookie( true ); // stable wc_ref for the hold
	}
	$hold = j5as_hold( $uid );
	if ( 'ok' !== $hold ) {
		wc_add_notice( 'error' === $hold ? 'We could not reserve that plate just now. Please try again.' : 'Sorry, that plate was just taken. Please pick another one.', 'error' );
		return false;
	}
	$unit['sku']    = $var->get_sku();
	$unit['wc_ref'] = j5as_wc_ref();
	$GLOBALS['j5as_pending'] = $unit;
	return true;
}, 30, 4 );

/** A unit is one item. Normalized before WC's form handler (wp_loaded 20) reads it, so the "added" notice says 1. */
add_action( 'wp_loaded', function () {
	if ( '' !== j5as_posted_unit() ) {
		$_REQUEST['quantity'] = $_POST['quantity'] = 1; // phpcs:ignore WordPress.Security.NonceVerification
	}
}, 5 );
add_filter( 'woocommerce_add_to_cart_quantity', function ( $qty ) {
	return '' !== j5as_posted_unit() ? 1 : $qty;
}, 20 );

add_filter( 'woocommerce_add_cart_item_data', function ( $data ) {
	if ( ! empty( $GLOBALS['j5as_pending'] ) ) {
		$data[ J5AS_CART_KEY ] = $GLOBALS['j5as_pending'];
		unset( $GLOBALS['j5as_pending'] );
	}
	return $data;
}, 20 );

add_filter( 'woocommerce_get_cart_item_from_session', function ( $item, $values ) {
	if ( ! empty( $values[ J5AS_CART_KEY ] ) ) {
		$item[ J5AS_CART_KEY ] = $values[ J5AS_CART_KEY ];
	}
	return $item;
}, 10, 2 );

/** Quantity is fixed at 1 in the cart. */
add_filter( 'woocommerce_cart_item_quantity', function ( $html, $key, $item ) {
	return ! empty( $item[ J5AS_CART_KEY ] ) ? '<span class="j5-as-qty">1</span>' : $html;
}, 20, 3 );
add_filter( 'woocommerce_update_cart_validation', function ( $passed, $key, $values, $qty ) {
	if ( ! empty( $values[ J5AS_CART_KEY ] ) && (int) $qty > 1 ) {
		wc_add_notice( 'Each in-stock plate is a single item. Add another plate to buy more than one.', 'error' );
		return false;
	}
	return $passed;
}, 20, 4 );

/** Removed from the cart: let the DP list it again right away. */
add_action( 'woocommerce_remove_cart_item', function ( $key, $cart ) {
	$item = $cart->cart_contents[ $key ] ?? null;
	if ( ! empty( $item[ J5AS_CART_KEY ]['unit_id'] ) ) {
		j5as_release( $item[ J5AS_CART_KEY ]['unit_id'], (string) ( $item[ J5AS_CART_KEY ]['wc_ref'] ?? '' ) );
		delete_transient( 'j5as_' . md5( (string) ( $item[ J5AS_CART_KEY ]['sku'] ?? '' ) ) );
	}
}, 10, 2 );

/** "Plate 1 SN: XXXX1847 | Plate 2 SN: XXXX1848" (one plate: "SN: XXXX1847"). */
function j5as_serial_text( $serials ) {
	$serials = array_values( (array) $serials );
	if ( count( $serials ) < 2 ) {
		return $serials ? 'SN: ' . $serials[0] : '';
	}
	$out = array();
	foreach ( $serials as $i => $s ) {
		$out[] = 'Plate ' . ( $i + 1 ) . ' SN: ' . $s;
	}
	return implode( ' | ', $out );
}

/** "Plate: In stock · Plate 1 SN: XXXX1847 | Plate 2 SN: XXXX1848 · MFG Date: Mar 2025" on cart, checkout and order lines. */
function j5as_line_text( $u ) {
	$made = j5as_month( $u['mfg_date'] ?? '' );
	$bits = array();
	if ( ! empty( $u['serials'] ) ) {
		$bits[] = j5as_serial_text( $u['serials'] );
	}
	if ( $made ) {
		$bits[] = 'MFG Date: ' . $made;
	}
	return $bits ? implode( ' · ', $bits ) : 'Yes';
}
add_filter( 'woocommerce_get_item_data', function ( $item_data, $item ) {
	if ( ! empty( $item[ J5AS_CART_KEY ] ) ) {
		$item_data[] = array( 'key' => 'In-stock plate', 'value' => j5as_line_text( $item[ J5AS_CART_KEY ] ) );
	}
	return $item_data;
}, 25, 2 );

/* =========================================================================
 * 5. DISCOUNT: virtual auto coupon, unit lines only
 * ====================================================================== */

function j5as_is_code( $coupon_or_code ) {
	$code = $coupon_or_code instanceof WC_Coupon ? $coupon_or_code->get_code() : (string) $coupon_or_code;
	return wc_format_coupon_code( $code ) === J5AS_CODE;
}

function j5as_item_pct( $item ) {
	return is_array( $item ) && ! empty( $item[ J5AS_CART_KEY ] ) ? (int) $item[ J5AS_CART_KEY ]['discount_pct'] : 0;
}

add_filter( 'woocommerce_get_shop_coupon_data', function ( $data, $code ) {
	if ( ! j5as_is_code( $code ) ) {
		return $data;
	}
	return array( 'discount_type' => 'percent', 'amount' => 1, 'individual_use' => false, 'usage_limit' => 0, 'description' => J5AS_LABEL );
}, 10, 2 );

add_filter( 'woocommerce_coupon_is_valid_for_product', function ( $valid, $product, $coupon, $values ) {
	return j5as_is_code( $coupon ) ? j5as_item_pct( $values ) > 0 : $valid;
}, 20, 4 );

add_filter( 'woocommerce_coupon_get_discount_amount', function ( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
	if ( ! j5as_is_code( $coupon ) ) {
		return $discount;
	}
	$pct = j5as_item_pct( $cart_item );
	return $pct > 0 ? (float) $discounting_amount * $pct / 100 : 0;
}, 20, 5 );

/** Auto apply / remove without notices (same pattern as factory order savings). */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	static $busy = false;
	if ( $busy || ! $cart instanceof WC_Cart || ( is_admin() && ! wp_doing_ajax() ) ) {
		return;
	}
	$busy = true;
	$want = false;
	foreach ( $cart->get_cart() as $item ) {
		if ( j5as_item_pct( $item ) > 0 ) {
			$want = true;
			break;
		}
	}
	$applied = $cart->get_applied_coupons();
	if ( in_array( J5AS_OLD_CODE, $applied, true ) ) {
		$applied = array_values( array_diff( $applied, array( J5AS_OLD_CODE ) ) );
		$cart->set_applied_coupons( $applied );
	}
	$has     = in_array( J5AS_CODE, $applied, true );
	if ( $want && ! $has ) {
		$applied[] = J5AS_CODE;
		$cart->set_applied_coupons( $applied );
	} elseif ( ! $want && $has ) {
		$cart->set_applied_coupons( array_values( array_diff( $applied, array( J5AS_CODE ) ) ) );
	}
	$busy = false;
}, 6 );

add_filter( 'woocommerce_cart_totals_coupon_label', function ( $label, $coupon ) {
	return j5as_is_code( $coupon ) ? J5AS_LABEL : $label;
}, 20, 2 );
add_filter( 'woocommerce_cart_totals_coupon_html', function ( $html, $coupon, $amount_html ) {
	return j5as_is_code( $coupon ) ? $amount_html : $html;
}, 20, 3 );

/* =========================================================================
 * 6. CHECKOUT: hold again (extends it); stop if the plate is gone
 * ====================================================================== */

add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	foreach ( j5as_cart_items() as $item ) {
		$u    = $item[ J5AS_CART_KEY ];
		$hold = j5as_hold( $u['unit_id'], (string) ( $u['wc_ref'] ?? '' ) );
		$name = isset( $item['data'] ) && $item['data'] instanceof WC_Product ? $item['data']->get_name() : 'your plate';
		if ( 'ok' === $hold ) {
			continue;
		}
		if ( 'error' === $hold ) {
			$errors->add( 'j5as', sprintf( 'We could not confirm the in-stock plate for %s just now. Please try again in a moment.', esc_html( $name ) ) );
		} else {
			$errors->add( 'j5as', sprintf( 'Sorry, the in-stock plate you picked for %s (%s) was just sold. Please remove it from your cart and choose another.', esc_html( $name ), esc_html( j5as_serial_text( $u['serials'] ) ) ) );
		}
	}
}, 20, 2 );

/* =========================================================================
 * 7. ORDER LINE META (the DP reads these on import)
 * ====================================================================== */

add_action( 'woocommerce_checkout_create_order_line_item', function ( $line_item, $cart_item_key, $values ) {
	if ( empty( $values[ J5AS_CART_KEY ] ) ) {
		return;
	}
	$u = $values[ J5AS_CART_KEY ];
	$line_item->add_meta_data( '_j5_as_unit_id', $u['unit_id'], true );
	$line_item->add_meta_data( '_j5_as_serials', j5as_serial_text( $u['serials'] ), true );
	$line_item->add_meta_data( '_j5_as_mfg', (string) $u['mfg_date'], true );
	$line_item->add_meta_data( '_j5_as_discount_pct', (string) (int) $u['discount_pct'], true );
	if ( ! empty( $u['acquisitions'] ) ) {
		// Contract format: "BA-00058" when shared, else "BA-00058, BA-00059" in Plate 1, Plate 2 order. Staff only.
		$acqs = array_values( array_filter( (array) $u['acquisitions'], 'strlen' ) );
		$line_item->add_meta_data( '_j5_as_acquisition', 1 === count( array_unique( $acqs ) ) ? $acqs[0] : implode( ', ', $acqs ), true );
	}
	$line_item->add_meta_data( 'In-stock plate', j5as_line_text( $u ), true );
}, 20, 3 );

/* =========================================================================
 * 8. ACQUISITION # (staff only): wp-admin order screen + admin emails,
 *    printed just above the item's meta (so above the serials).
 * ====================================================================== */

/** Display text from the stored contract value: "BA-00058" or "Plate 1: BA-00058 | Plate 2: BA-00059". */
function j5as_acq_line( $item ) {
	$raw = $item instanceof WC_Order_Item_Product ? trim( (string) $item->get_meta( '_j5_as_acquisition' ) ) : '';
	return '' === $raw ? '' : j5as_acq_text( array_map( 'trim', explode( ',', $raw ) ) );
}

add_action( 'woocommerce_before_order_itemmeta', function ( $item_id, $item ) {
	$a = j5as_acq_line( $item );
	if ( '' !== $a ) {
		echo '<div class="j5-as-acq" style="margin:4px 0 2px"><strong>Acquisition #:</strong> ' . esc_html( $a ) . '</div>';
	}
}, 10, 2 );

/* Emails: only when the email is going to the store (New order, Cancelled, Failed). */
add_action( 'woocommerce_email_before_order_table', function ( $order, $sent_to_admin ) {
	$GLOBALS['j5as_email_admin'] = (bool) $sent_to_admin;
}, 1, 2 );
add_action( 'woocommerce_email_after_order_table', function () {
	$GLOBALS['j5as_email_admin'] = false;
}, 99 );
add_action( 'woocommerce_order_item_meta_start', function ( $item_id, $item, $order, $plain_text = false ) {
	if ( empty( $GLOBALS['j5as_email_admin'] ) ) {
		return;
	}
	$a = j5as_acq_line( $item );
	if ( '' === $a ) {
		return;
	}
	if ( $plain_text ) {
		echo "\nAcquisition #: " . esc_html( $a );
	} else {
		echo '<div style="margin:6px 0 0;font-size:small"><strong>Acquisition #:</strong> ' . esc_html( $a ) . '</div>';
	}
}, 10, 4 );
