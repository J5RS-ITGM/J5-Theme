<?php
/**
 * J5 Product Options (add-ons) from the Dealer Portal.
 *
 * The DP pushes one JSON meta key on the PARENT product, `_j5_options`
 * (see the DP brief). The theme only renders it, validates the buyer's picks
 * against it, prices them, and writes the picks to the order line for the DP
 * to read back. No WC attributes, variations or SKUs are created, and stock
 * on the base product is untouched.
 *
 *   Product page   j5_render_product_options() inside the add-to-cart form,
 *                  after the variation selectors and before the ack gate.
 *                  Posts j5_options[<group code>][] = <choice code>.
 *   Live price     assets/js/j5-product-options.js: base (variation price for
 *                  variable products) + sum of chosen price_adjust.
 *   Cart           validated server-side; price adjust added per unit;
 *                  picks shown as "Group: Choice (+$6.00)".
 *   Order line     visible meta per choice (key = group name), plus hidden
 *                  _j5_options, _j5_base_sku, _j5_sku, _j5_base_price,
 *                  _j5_options_total.
 *
 * Products with an empty / missing `_j5_options` behave exactly as before.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5_OPTS_META     = '_j5_options';
const J5_OPTS_CART_KEY = 'j5_options';

/* =========================================================================
 * 1. DATA
 * ====================================================================== */

/**
 * Option groups for a product (parent meta), normalized. Empty array = none.
 *
 * @param int|WC_Product $product Product, variation, or ID.
 * @return array
 */
function j5_opts_groups( $product ) {
	static $cache = array();
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) {
		return array();
	}
	$pid = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	if ( isset( $cache[ $pid ] ) ) {
		return $cache[ $pid ];
	}
	$raw = get_post_meta( $pid, J5_OPTS_META, true );
	$out = array();
	// JSON string per the brief; an array/object is also accepted (the WC REST
	// API stores a JSON object sent as meta_data value as a serialized array).
	if ( is_string( $raw ) && '' !== trim( $raw ) ) {
		$decoded = json_decode( $raw, true );
		if ( is_string( $decoded ) ) { // double-encoded
			$decoded = json_decode( $decoded, true );
		}
	} elseif ( is_array( $raw ) || is_object( $raw ) ) {
		$decoded = json_decode( wp_json_encode( $raw ), true );
	} else {
		$decoded = null;
	}
	if ( isset( $decoded['groups'] ) && is_array( $decoded['groups'] ) ) { // tolerate a { "groups": [...] } wrapper
		$decoded = $decoded['groups'];
	}
	if ( is_array( $decoded ) ) {
		foreach ( $decoded as $g ) {
			if ( ! is_array( $g ) || ! isset( $g['code'] ) || '' === (string) $g['code'] || empty( $g['choices'] ) || ! is_array( $g['choices'] ) ) {
				continue;
			}
			$choices = array();
			foreach ( $g['choices'] as $c ) {
				if ( ! is_array( $c ) || ! isset( $c['code'] ) || '' === (string) $c['code'] ) {
					continue;
				}
				$choices[ (string) $c['code'] ] = array(
					'code'         => (string) $c['code'],
					'label'        => isset( $c['label'] ) && '' !== (string) $c['label'] ? (string) $c['label'] : (string) $c['code'],
					'description'  => isset( $c['description'] ) ? (string) $c['description'] : '',
					'sku_code'     => isset( $c['sku_code'] ) && null !== $c['sku_code'] ? trim( (string) $c['sku_code'] ) : '',
					'price_adjust' => j5_opts_dec( isset( $c['price_adjust'] ) ? $c['price_adjust'] : 0 ),
					'image_url'    => isset( $c['image_url'] ) && is_string( $c['image_url'] ) ? $c['image_url'] : '',
					'is_default'   => ! empty( $c['is_default'] ),
				);
			}
			if ( ! $choices ) {
				continue;
			}
			$mode = isset( $g['sku_mode'] ) ? (string) $g['sku_mode'] : 'none';
			$out[ (string) $g['code'] ] = array(
				'code'        => (string) $g['code'],
				'name'        => isset( $g['name'] ) && '' !== (string) $g['name'] ? (string) $g['name'] : (string) $g['code'],
				'description' => isset( $g['description'] ) ? (string) $g['description'] : '',
				'selection'   => ( isset( $g['selection'] ) && 'multi' === $g['selection'] ) ? 'multi' : 'single',
				'required'    => ! empty( $g['required'] ),
				'sku_mode'    => in_array( $mode, array( 'none', 'suffix', 'yoke' ), true ) ? $mode : 'none',
				'choices'     => $choices,
			);
		}
	}
	$cache[ $pid ] = $out;
	return $out;
}

/** True when the product has at least one option group. */
function j5_opts_has( $product ) {
	return ! empty( j5_opts_groups( $product ) );
}

/** Decimal as a 2-place float. */
function j5_opts_dec( $v ) {
	return round( (float) ( is_numeric( $v ) ? $v : 0 ), 2 );
}

/** Decimal as a "6.00" string (what the DP expects). */
function j5_opts_dec_str( $v ) {
	return number_format( (float) $v, 2, '.', '' );
}

/** "+$6.00" / "-$2.50" in plain text; '' for zero. */
function j5_opts_adjust_label( $amount ) {
	$amount = (float) $amount;
	if ( abs( $amount ) < 0.005 ) {
		return '';
	}
	$sym = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
	$num = number_format( abs( $amount ), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
	return ( $amount < 0 ? '-' : '+' ) . $sym . $num;
}

/**
 * Validate posted picks against the product's groups.
 *
 * @param array $groups j5_opts_groups() result.
 * @param mixed $posted $_POST['j5_options'] (unslashed).
 * @return array [ 'picks' => [ group_code => [ choice_code, ... ] ], 'missing' => [ group names ] ]
 */
function j5_opts_validate( $groups, $posted ) {
	$posted  = is_array( $posted ) ? $posted : array();
	$picks   = array();
	$missing = array();
	foreach ( $groups as $gcode => $g ) {
		$vals = isset( $posted[ $gcode ] ) ? (array) $posted[ $gcode ] : array();
		$ok   = array();
		foreach ( $vals as $v ) {
			$v = is_scalar( $v ) ? (string) $v : '';
			if ( '' !== $v && isset( $g['choices'][ $v ] ) && ! in_array( $v, $ok, true ) ) {
				$ok[] = $v; // unknown choices are ignored
			}
		}
		if ( 'single' === $g['selection'] && count( $ok ) > 1 ) {
			$ok = array( $ok[0] );
		}
		// Keep the DP's choice order, not the order the browser posted.
		$ok = array_values( array_intersect( array_keys( $g['choices'] ), $ok ) );
		if ( $ok ) {
			$picks[ $gcode ] = $ok;
		} elseif ( $g['required'] ) {
			$missing[] = $g['name'];
		}
	}
	return array( 'picks' => $picks, 'missing' => $missing );
}

/**
 * Picks expanded to the order-line record, in group order.
 *
 * @return array list of [ group_code, group_name, choice_code, choice_label, price_adjust, sku_code, sku_mode ]
 */
function j5_opts_records( $groups, $picks ) {
	$rows = array();
	foreach ( $groups as $gcode => $g ) {
		if ( empty( $picks[ $gcode ] ) ) {
			continue;
		}
		foreach ( $picks[ $gcode ] as $ccode ) {
			if ( ! isset( $g['choices'][ $ccode ] ) ) {
				continue;
			}
			$c      = $g['choices'][ $ccode ];
			$rows[] = array(
				'group_code'   => $gcode,
				'group_name'   => $g['name'],
				'choice_code'  => $ccode,
				'choice_label' => $c['label'],
				'price_adjust' => j5_opts_dec_str( $c['price_adjust'] ),
				'sku_code'     => '' !== $c['sku_code'] ? $c['sku_code'] : null,
				'sku_mode'     => $g['sku_mode'],
			);
		}
	}
	return $rows;
}

/** Sum of price_adjust across records, per unit. */
function j5_opts_total( $records ) {
	$t = 0.0;
	foreach ( (array) $records as $r ) {
		$t += (float) $r['price_adjust'];
	}
	return round( $t, 2 );
}

/**
 * Composed SKU.
 *   none    base unchanged
 *   suffix  BASE-<code> per choice, in group order
 *   yoke    BRAND-CC-REST -> BRAND-<code>CC-REST (codes in group order);
 *           base not shaped BRAND-CC- -> falls back to suffix
 * Choices with no sku_code never change the SKU.
 */
function j5_opts_compose_sku( $base, $records ) {
	$base = (string) $base;
	if ( '' === $base ) {
		return '';
	}
	$yoke   = '';
	$suffix = '';
	$shaped = (bool) preg_match( '/^([^-]+)-([A-Za-z]{2})-(.+)$/', $base, $m );
	foreach ( (array) $records as $r ) {
		$code = isset( $r['sku_code'] ) ? (string) $r['sku_code'] : '';
		if ( '' === $code || 'none' === $r['sku_mode'] ) {
			continue;
		}
		if ( 'yoke' === $r['sku_mode'] && $shaped ) {
			$yoke .= $code;
		} else {
			$suffix .= '-' . $code;
		}
	}
	$sku = ( '' !== $yoke ) ? $m[1] . '-' . $yoke . $m[2] . '-' . $m[3] : $base;
	return $sku . $suffix;
}

/* =========================================================================
 * 2. PRODUCT PAGE
 * ====================================================================== */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product || ! j5_opts_has( $product ) ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	wp_enqueue_style( 'j5-product-options', $uri . '/assets/css/j5-product-options.css', array(), (string) filemtime( $dir . '/assets/css/j5-product-options.css' ) );
	wp_enqueue_script( 'j5-product-options', $uri . '/assets/js/j5-product-options.js', array( 'jquery' ), (string) filemtime( $dir . '/assets/js/j5-product-options.js' ), true );
}, 30 );

/**
 * Option groups inside the add-to-cart form. Renders nothing without options.
 *
 * @param WC_Product $product
 */
function j5_render_product_options( $product ) {
	$groups = j5_opts_groups( $product );
	if ( ! $groups ) {
		return;
	}
	$base = $product->is_type( 'variable' ) ? '' : (string) wc_get_price_to_display( $product );
	printf(
		'<div class="j5-opts" data-j5-opts data-base="%s" data-currency="%s" data-decimals="%d" data-dec-sep="%s" data-thou-sep="%s">',
		esc_attr( $base ),
		esc_attr( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ),
		(int) wc_get_price_decimals(),
		esc_attr( wc_get_price_decimal_separator() ),
		esc_attr( wc_get_price_thousand_separator() )
	);
	foreach ( $groups as $gcode => $g ) {
		$name     = 'j5_options[' . $gcode . '][]';
		$id_base  = 'j5o-' . sanitize_html_class( $gcode );
		$use_sel  = 'single' === $g['selection'] && count( $g['choices'] ) > 6;
		$has_def  = false;
		foreach ( $g['choices'] as $c ) {
			if ( $c['is_default'] ) { $has_def = true; break; }
		}
		?>
		<fieldset class="j5-opts__group" data-group="<?php echo esc_attr( $gcode ); ?>" data-required="<?php echo $g['required'] ? '1' : '0'; ?>" data-selection="<?php echo esc_attr( $g['selection'] ); ?>">
			<legend class="j5-opts__name"><?php echo esc_html( $g['name'] ); ?><?php if ( $g['required'] ) : ?> <span class="j5-opts__req" aria-hidden="true">*</span><span class="screen-reader-text"> (required)</span><?php endif; ?></legend>
			<?php if ( '' !== $g['description'] ) : ?><p class="j5-opts__help"><?php echo esc_html( $g['description'] ); ?></p><?php endif; ?>

			<?php if ( $use_sel ) : ?>
				<select class="j5-opts__select" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id_base ); ?>" aria-label="<?php echo esc_attr( $g['name'] ); ?>">
					<option value="" data-adjust="0"<?php selected( ! $has_def ); ?>><?php echo $g['required'] ? 'Choose an option' : 'None'; ?></option>
					<?php foreach ( $g['choices'] as $ccode => $c ) :
						$adj = j5_opts_adjust_label( $c['price_adjust'] ); ?>
						<option value="<?php echo esc_attr( $ccode ); ?>" data-adjust="<?php echo esc_attr( j5_opts_dec_str( $c['price_adjust'] ) ); ?>"<?php selected( $c['is_default'] ); ?>><?php echo esc_html( $c['label'] . ( '' !== $adj ? ' (' . $adj . ')' : '' ) ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<div class="j5-opts__choices">
					<?php
					$i = 0;
					foreach ( $g['choices'] as $ccode => $c ) :
						$i++;
						$cid  = $id_base . '-' . $i;
						$adj  = j5_opts_adjust_label( $c['price_adjust'] );
						$type = 'multi' === $g['selection'] ? 'checkbox' : 'radio';
						?>
						<label class="j5-opts__choice" for="<?php echo esc_attr( $cid ); ?>">
							<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $cid ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $ccode ); ?>" data-adjust="<?php echo esc_attr( j5_opts_dec_str( $c['price_adjust'] ) ); ?>"<?php checked( $c['is_default'] ); ?> />
							<?php if ( '' !== $c['image_url'] ) : ?>
								<img class="j5-opts__img" src="<?php echo esc_url( $c['image_url'] ); ?>" alt="" width="40" height="40" loading="lazy" />
							<?php endif; ?>
							<span class="j5-opts__text">
								<span class="j5-opts__label"><?php echo esc_html( $c['label'] ); ?></span>
								<?php if ( '' !== $c['description'] ) : ?><span class="j5-opts__desc"><?php echo esc_html( $c['description'] ); ?></span><?php endif; ?>
							</span>
							<?php if ( '' !== $adj ) : ?><span class="j5-opts__adj"><?php echo esc_html( $adj ); ?></span><?php endif; ?>
						</label>
					<?php endforeach; ?>
					<?php if ( 'single' === $g['selection'] && ! $g['required'] ) : ?>
						<label class="j5-opts__choice j5-opts__choice--none" for="<?php echo esc_attr( $id_base ); ?>-none">
							<input type="radio" id="<?php echo esc_attr( $id_base ); ?>-none" name="<?php echo esc_attr( $name ); ?>" value="" data-adjust="0"<?php checked( ! $has_def ); ?> />
							<span class="j5-opts__text"><span class="j5-opts__label">None</span></span>
						</label>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<p class="j5-opts__err" role="alert" hidden>Please choose an option for <?php echo esc_html( $g['name'] ); ?>.</p>
		</fieldset>
		<?php
	}
	echo '</div>';
}

/* Shop cards / home cards: products with options go to the product page instead of adding from the grid. */
add_filter( 'woocommerce_product_add_to_cart_url', function ( $url, $product ) {
	return ( $product instanceof WC_Product && j5_opts_has( $product ) ) ? $product->get_permalink() : $url;
}, 20, 2 );
add_filter( 'woocommerce_product_add_to_cart_text', function ( $text, $product ) {
	return ( $product instanceof WC_Product && $product->is_type( 'simple' ) && j5_opts_has( $product ) ) ? __( 'Select options', 'woocommerce' ) : $text;
}, 20, 2 );
add_filter( 'woocommerce_product_supports', function ( $supports, $feature, $product ) {
	return ( 'ajax_add_to_cart' === $feature && $product instanceof WC_Product && j5_opts_has( $product ) ) ? false : $supports;
}, 20, 3 );

/* =========================================================================
 * 3. CART
 * ====================================================================== */

/** Posted picks, unslashed. */
function j5_opts_posted() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- add-to-cart is not nonce-protected by WC.
	return isset( $_POST['j5_options'] ) && is_array( $_POST['j5_options'] ) ? wp_unslash( $_POST['j5_options'] ) : array();
}

/** Server-side gate: required groups must be picked. */
add_filter( 'woocommerce_add_to_cart_validation', function ( $passed, $product_id, $quantity = 1, $variation_id = 0 ) {
	$groups = j5_opts_groups( $product_id );
	if ( ! $groups ) {
		return $passed;
	}
	$res = j5_opts_validate( $groups, j5_opts_posted() );
	foreach ( $res['missing'] as $gname ) {
		wc_add_notice(
			sprintf(
				/* translators: 1: option group name, 2: product title */
				__( 'Please choose %1$s for %2$s.', 'woocommerce' ),
				esc_html( $gname ),
				esc_html( get_the_title( $product_id ) )
			),
			'error'
		);
		$passed = false;
	}
	return $passed;
}, 15, 4 );

/** Attach the validated picks; different picks become separate cart lines. */
add_filter( 'woocommerce_add_cart_item_data', function ( $data, $product_id, $variation_id = 0 ) {
	$groups = j5_opts_groups( $product_id );
	if ( ! $groups ) {
		return $data;
	}
	$res     = j5_opts_validate( $groups, j5_opts_posted() );
	$records = j5_opts_records( $groups, $res['picks'] );
	if ( $records ) {
		$data[ J5_OPTS_CART_KEY ] = $records;
	}
	return $data;
}, 10, 3 );

add_filter( 'woocommerce_get_cart_item_from_session', function ( $item, $values ) {
	if ( ! empty( $values[ J5_OPTS_CART_KEY ] ) ) {
		$item[ J5_OPTS_CART_KEY ] = $values[ J5_OPTS_CART_KEY ];
	}
	return $item;
}, 10, 2 );

/**
 * Add the per-unit adjust to the line price. Runs once per product object
 * (calculate_totals can fire several times in one request).
 */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	static $done = array();
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	foreach ( $cart->get_cart() as $key => $item ) {
		if ( empty( $item[ J5_OPTS_CART_KEY ] ) || empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
			continue;
		}
		$p   = $item['data'];
		$oid = spl_object_id( $p );
		if ( isset( $done[ $oid ] ) ) {
			continue;
		}
		$base  = (float) $p->get_price(); // view context: keeps any pricing plugin's adjusted price as the base
		$total = j5_opts_total( $item[ J5_OPTS_CART_KEY ] );
		$done[ $oid ] = $base;
		$cart->cart_contents[ $key ]['j5_base_price'] = $base;
		if ( abs( $total ) >= 0.005 ) {
			$p->set_price( max( 0, $base + $total ) );
		}
	}
}, 20 );

/** "Group: Choice (+$6.00)" on cart and checkout lines. */
add_filter( 'woocommerce_get_item_data', function ( $item_data, $item ) {
	if ( empty( $item[ J5_OPTS_CART_KEY ] ) ) {
		return $item_data;
	}
	foreach ( $item[ J5_OPTS_CART_KEY ] as $r ) {
		$adj         = j5_opts_adjust_label( $r['price_adjust'] );
		$item_data[] = array(
			'key'   => $r['group_name'],
			'value' => $r['choice_label'] . ( '' !== $adj ? ' (' . $adj . ')' : '' ),
		);
	}
	return $item_data;
}, 20, 2 );

/* =========================================================================
 * 4. ORDER LINE META (read back by the DP)
 * ====================================================================== */

add_action( 'woocommerce_checkout_create_order_line_item', function ( $line_item, $cart_item_key, $values ) {
	if ( empty( $values[ J5_OPTS_CART_KEY ] ) ) {
		return;
	}
	$records = $values[ J5_OPTS_CART_KEY ];
	$product = isset( $values['data'] ) && $values['data'] instanceof WC_Product ? $values['data'] : null;

	$base_sku = '';
	if ( $product ) {
		$base_sku = (string) $product->get_sku();
	}
	$total = j5_opts_total( $records );
	if ( isset( $values['j5_base_price'] ) ) {
		$base_price = (float) $values['j5_base_price'];
	} elseif ( $product ) {
		$base_price = (float) $product->get_price() - $total;
	} else {
		$base_price = 0.0;
	}

	// Visible: one display meta per choice (order emails, admin).
	foreach ( $records as $r ) {
		$adj = j5_opts_adjust_label( $r['price_adjust'] );
		$line_item->add_meta_data( $r['group_name'], $r['choice_label'] . ( '' !== $adj ? ' (' . $adj . ')' : '' ), false );
	}

	// Hidden: what the DP reads.
	$out = array();
	foreach ( $records as $r ) {
		$out[] = array(
			'group_code'   => $r['group_code'],
			'group_name'   => $r['group_name'],
			'choice_code'  => $r['choice_code'],
			'choice_label' => $r['choice_label'],
			'price_adjust' => $r['price_adjust'],
			'sku_code'     => $r['sku_code'],
		);
	}
	$line_item->add_meta_data( '_j5_options', wp_json_encode( $out ), true );
	$line_item->add_meta_data( '_j5_base_sku', $base_sku, true );
	$line_item->add_meta_data( '_j5_sku', j5_opts_compose_sku( $base_sku, $records ), true );
	$line_item->add_meta_data( '_j5_base_price', j5_opts_dec_str( $base_price ), true );
	$line_item->add_meta_data( '_j5_options_total', j5_opts_dec_str( $total ), true );
}, 20, 3 );
