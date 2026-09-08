<?php
/**
 * J5 Variation Display — multi-style variation selectors + cascade support.
 *
 * Renders the attribute groups inside the single-product variations form.
 * Each attribute resolves to a display type (pills / radio-cards / dropdown)
 * via j5_attribute_display_type(): explicit map first, then heuristics.
 *
 * Every display type drives the same hidden <select> WooCommerce's
 * wc-add-to-cart-variation.js expects, so variation matching, stock, and
 * price behavior are identical regardless of style. The cascade
 * (progressive disclosure, hiding non-existent combinations, marking
 * out-of-stock options) is handled client-side in j5-shop.js using the
 * data-product_variations JSON.
 *
 * Added 2026-07-13. @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }


/**
 * Inject a reliable three-state stock flag into the variations JSON the
 * frontend consumes: 'in' | 'special' (backorder / made-to-order) | 'out'.
 * WC's is_in_stock alone can't distinguish backorderable-but-unstocked
 * (armor special orders) from on-the-shelf inventory.
 */
add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {
	if ( $variation->is_on_backorder() ) {
		$data['j5_stock_state'] = 'special';
	} elseif ( $variation->is_in_stock() ) {
		$data['j5_stock_state'] = 'in';
	} else {
		$data['j5_stock_state'] = 'out';
	}
	return $data;
}, 10, 3 );

/**
 * Resolve the display type for an attribute.
 *
 * Order: explicit map (filterable) → >7 options: dropdown →
 * price varies across options: radio-cards → pills.
 *
 * @param string $attribute_name Raw attribute name/taxonomy (e.g. pa_mount).
 * @param array  $options        Option slugs/values for this attribute.
 * @param array  $option_prices  Map of option => array( 'min' => f, 'max' => f ).
 * @return string pills|radio-cards|dropdown
 */
function j5_attribute_display_type( $attribute_name, $options, $option_prices ) {
	$slug = sanitize_title( $attribute_name );

	/**
	 * Explicit per-attribute display map. Keys are sanitized attribute
	 * slugs WITHOUT the attribute_ prefix (e.g. 'pa_mount', 'pa_color').
	 * Values: 'pills' | 'radio-cards' | 'dropdown'.
	 *
	 * Phase 2: the hub's attribute registry can publish a display_type
	 * hint down to WC; hook it in through this filter.
	 */
	$map = apply_filters( 'j5_attr_display_map', array() );
	if ( isset( $map[ $slug ] ) ) {
		return $map[ $slug ];
	}

	// A swatch image directory for this attribute → image swatches.
	if ( is_dir( get_stylesheet_directory() . '/assets/img/swatches/' . $slug ) ) {
		return 'image-swatches';
	}

	if ( count( $options ) > 7 ) {
		return 'dropdown';
	}

	// Price variance across options → radio cards (price becomes part of the choice).
	$mins = array();
	foreach ( $option_prices as $p ) {
		if ( $p['min'] !== null ) {
			$mins[ (string) $p['min'] ] = true;
		}
	}
	if ( count( $mins ) > 1 ) {
		return 'radio-cards';
	}

	return 'pills';
}


/**
 * Path/URL for a swatch image following the theme convention:
 *   assets/img/swatches/{attribute_slug}/{option_slug}.png
 * Returns URL string or '' if no file exists.
 */
function j5_swatch_image_url( $attribute_slug, $option ) {
	$rel = '/assets/img/swatches/' . $attribute_slug . '/' . sanitize_title( $option ) . '.png';
	if ( file_exists( get_stylesheet_directory() . $rel ) ) {
		return get_stylesheet_directory_uri() . $rel;
	}
	return '';
}

/**
 * Per-option price ranges for one attribute, computed across the
 * available-variations array (the same data the frontend JSON carries).
 *
 * @return array option => array( 'min' => float|null, 'max' => float|null )
 */
function j5_variation_option_prices( $field_name, $options, $available_variations ) {
	$out = array();
	foreach ( $options as $option ) {
		$min = null;
		$max = null;
		foreach ( $available_variations as $v ) {
			$val = isset( $v['attributes'][ $field_name ] ) ? $v['attributes'][ $field_name ] : '';
			if ( '' !== $val && $val !== $option ) {
				continue;
			}
			if ( ! isset( $v['display_price'] ) || '' === $v['display_price'] ) {
				continue;
			}
			$price = (float) $v['display_price'];
			$min   = ( null === $min ) ? $price : min( $min, $price );
			$max   = ( null === $max ) ? $price : max( $max, $price );
		}
		$out[ $option ] = array( 'min' => $min, 'max' => $max );
	}
	return $out;
}

/**
 * Option slug/value => display label pairs, honoring term names for
 * taxonomy attributes and raw values otherwise.
 */
function j5_variation_option_labels( $product, $attribute_name, $options ) {
	$labels = array();
	if ( taxonomy_is_product_attribute( $attribute_name ) ) {
		$terms = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'all' ) );
		foreach ( $terms as $term ) {
			if ( in_array( $term->slug, $options, true ) ) {
				$labels[ $term->slug ] = $term->name;
			}
		}
	} else {
		foreach ( $options as $option ) {
			$labels[ $option ] = $option;
		}
	}
	return $labels;
}

/**
 * Render all attribute groups for a variable product.
 *
 * Groups after the first render with .j5-vg-hidden; j5-shop.js reveals
 * them progressively (and immediately un-hides everything if the
 * variations JSON is unavailable, e.g. above the AJAX threshold).
 */
function j5_render_variation_groups( $product, $attributes, $available_variations ) {
	$index        = 0;
	$total_groups = count( $attributes );
	foreach ( $attributes as $attribute_name => $options ) {
		$field_name    = 'attribute_' . sanitize_title( $attribute_name );
		$current_value = isset( $_REQUEST[ $field_name ] )
			? wc_clean( wp_unslash( $_REQUEST[ $field_name ] ) )
			: $product->get_variation_default_attribute( $attribute_name );

		$labels        = j5_variation_option_labels( $product, $attribute_name, $options );
		/*
		 * Allow reordering of option labels before render (J5-VAR-ORDER-1).
		 * Term order is really the hub's job, but sizes in particular arrive
		 * unsorted (XL, Small, Medium, Large). See j5_sort_size_options().
		 */
		$labels        = apply_filters( 'j5_variation_option_labels', $labels, $attribute_name, $product );
		$option_prices = j5_variation_option_prices( $field_name, array_keys( $labels ), $available_variations );
		$display       = j5_attribute_display_type( $attribute_name, array_keys( $labels ), $option_prices );

		$group_classes = 'j5-variant-group variations';
		if ( $index > 0 ) {
			$group_classes .= ' j5-vg-hidden';
		}
		/*
		 * Stock state (dot + "In stock" / "Special order" / "Out of stock") is
		 * only meaningful once the full combination is chosen — an individual
		 * cut is neither in nor out of stock, only cut+size is. Mark the final
		 * group so CSS can suppress state indicators on all earlier ones
		 * (J5-VAR-LASTSTATE-1). Single-attribute products still show state,
		 * because group 0 is also the last group.
		 */
		if ( $index === $total_groups - 1 ) {
			$group_classes .= ' j5-vg-last';
		}
		?>
		<div class="<?php echo esc_attr( $group_classes ); ?>"
		     data-attribute="<?php echo esc_attr( $field_name ); ?>"
		     data-display="<?php echo esc_attr( $display ); ?>">

			<label class="j5-variant-label"><?php echo esc_html( wc_attribute_label( $attribute_name ) ); ?></label>

			<?php if ( 'dropdown' === $display ) : ?>
				<select class="j5-variant-select"
				        name="<?php echo esc_attr( $field_name ); ?>"
				        data-attribute_name="<?php echo esc_attr( $field_name ); ?>">
					<option value=""><?php esc_html_e( 'Choose an option', 'woocommerce' ); ?></option>
					<?php foreach ( $labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( sanitize_title( $current_value ), sanitize_title( $value ) ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<?php // Hidden select — Woo's variation JS reads/writes this. ?>
				<select name="<?php echo esc_attr( $field_name ); ?>"
				        data-attribute_name="<?php echo esc_attr( $field_name ); ?>"
				        style="display:none">
					<option value=""><?php esc_html_e( 'Choose', 'woocommerce' ); ?></option>
					<?php foreach ( $labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( sanitize_title( $current_value ), sanitize_title( $value ) ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<?php if ( 'image-swatches' === $display ) : ?>
					<div class="j5-variant-swatches" data-attribute="<?php echo esc_attr( $field_name ); ?>">
						<?php foreach ( $labels as $value => $label ) :
							$is_selected = sanitize_title( $current_value ) === sanitize_title( $value );
							$img         = j5_swatch_image_url( sanitize_title( $attribute_name ), $value );
						?>
							<button type="button"
							        class="j5-variant-swatch <?php echo $is_selected ? 'active' : ''; ?>"
							        data-value="<?php echo esc_attr( $value ); ?>"
							        title="<?php echo esc_attr( $label ); ?>">
								<?php if ( $img ) : ?>
									<span class="j5-vs-img"><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $label ); ?>" loading="lazy"></span>
								<?php endif; ?>
								<span class="j5-vs-label"><?php echo esc_html( $label ); ?></span>
								<span class="j5-vs-status"></span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php elseif ( 'radio-cards' === $display ) : ?>
					<div class="j5-variant-cards" data-attribute="<?php echo esc_attr( $field_name ); ?>">
						<?php foreach ( $labels as $value => $label ) :
							$is_selected = sanitize_title( $current_value ) === sanitize_title( $value );
							$p           = $option_prices[ $value ];
							/*
							 * Per-option pricing is OFF by default (J5-VAR-PRICE-1).
							 * Showing a price on every option alongside the resolved
							 * "Your price" produced several competing numbers that
							 * contradicted each other (a cut's "From" floor vs. the
							 * size prices beneath it). One authoritative price now
							 * resolves below the selectors instead.
							 *
							 * The .j5-vc-price element and its data-min attribute are
							 * still emitted — j5-shop.js reads data-min to drive live
							 * price updates, so removing it would break the engine.
							 * Only the visible text is suppressed.
							 *
							 * Re-enable globally or per-attribute:
							 *   add_filter( 'j5_show_option_prices', '__return_true' );
							 *   add_filter( 'j5_show_option_prices', function ( $show, $attr ) {
							 *       return 'pa_size' === $attr;
							 *   }, 10, 2 );
							 */
							$show_option_price = apply_filters( 'j5_show_option_prices', false, $attribute_name );
							$price_html        = '';
							if ( $show_option_price && null !== $p['min'] ) {
								$price_html = ( $p['min'] === $p['max'] )
									? wc_price( $p['min'] )
									: '<span class="from_">From</span> ' . wc_price( $p['min'] );
							}
						?>
							<button type="button"
							        class="j5-variant-card <?php echo $is_selected ? 'active' : ''; ?>"
							        data-value="<?php echo esc_attr( $value ); ?>">
								<span class="j5-vc-main">
									<span class="j5-vc-name"><?php echo esc_html( $label ); ?></span>
									<span class="j5-vc-in">In stock</span>
									<span class="j5-vc-oos">Out of stock</span>
									<span class="j5-vc-special">Special order</span>
								</span>
								<span class="j5-vc-price" data-min="<?php echo esc_attr( (string) $p['min'] ); ?>"><?php echo wp_kses_post( $price_html ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="j5-variant-pills" data-attribute="<?php echo esc_attr( $field_name ); ?>">
						<?php foreach ( $labels as $value => $label ) :
							$is_selected = sanitize_title( $current_value ) === sanitize_title( $value );
						?>
							<button type="button"
							        class="j5-variant-pill <?php echo $is_selected ? 'active' : ''; ?>"
							        data-value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

		</div>
		<?php
		$index++;
	}
}
