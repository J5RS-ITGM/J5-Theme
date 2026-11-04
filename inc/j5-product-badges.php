<?php
/**
 * J5 product badges (under the product name)
 * ------------------------------------------
 * Three boxed badges, each shown only when the product earns it:
 *
 *   NIJ Certified           portal meta _j5_nij_certified = "yes" (inc/j5-nij-certified.php)
 *   Made in the USA         product_tag slug  made-in-usa
 *   Life Safety Guarantee   product_tag slug  life-safety-guarantee
 *
 * The dealer portal stores the two flags and pushes them as WooCommerce product tags
 * on the parent product (variations inherit). The theme checks the slug only, so the
 * tag's display name can change freely. Order is fixed: NIJ, USA, Guarantee.
 * Each badge shows a detail line on hover, keyboard focus, or tap.
 *
 *   j5_product_badges( $product )         list of badge arrays
 *   j5_render_product_badges( $product )  the badge row (woocommerce/single-product.php)
 *
 * @package j5-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5_BADGE_TAG_USA       = 'made-in-usa';
const J5_BADGE_TAG_GUARANTEE = 'life-safety-guarantee';

/** True when the parent product carries the product_tag with this slug. */
function j5_product_has_tag_slug( $product, $slug ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) return false;
	$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	return has_term( $slug, 'product_tag', $id );
}

/**
 * @param WC_Product|int $product
 * @return array[] each: key, label (HTML-safe text with optional line break), detail, icon (HTML)
 */
function j5_product_badges( $product ) {
	$out = array();
	if ( function_exists( 'j5_is_nij_certified' ) && j5_is_nij_certified( $product ) ) {
		$out[] = array(
			'key'    => 'nij',
			'label'  => 'NIJ Certified',
			'detail' => 'On the NIJ Compliant Products List for its stated standard.',
			'icon'   => '<span class="j5-badge__ico j5-badge__ico--nij" aria-hidden="true">NIJ</span>',
		);
	}
	if ( j5_product_has_tag_slug( $product, J5_BADGE_TAG_USA ) ) {
		$out[] = array(
			'key'    => 'usa',
			'label'  => 'Made in<br>the USA',
			'detail' => 'Made in the United States.',
			'icon'   => '<span class="j5-badge__ico j5-badge__ico--usa" aria-hidden="true"><svg width="40" height="27" viewBox="-1 -4 62 48" aria-hidden="true"><path d="M0 0.00 Q15 -3.20 30 0.00 T60 0.00 L60 40.00 Q45 43.20 30 40.00 T0 40.00 Z" fill="#f5f5f2"/><path d="M0 0.00 Q15 -3.20 30 0.00 T60 0.00 L60 3.08 Q45 6.28 30 3.08 T0 3.08 Z" fill="#c8102e"/><path d="M0 6.15 Q15 2.95 30 6.15 T60 6.15 L60 9.23 Q45 12.43 30 9.23 T0 9.23 Z" fill="#c8102e"/><path d="M0 12.31 Q15 9.11 30 12.31 T60 12.31 L60 15.38 Q45 18.58 30 15.38 T0 15.38 Z" fill="#c8102e"/><path d="M0 18.46 Q15 15.26 30 18.46 T60 18.46 L60 21.54 Q45 24.74 30 21.54 T0 21.54 Z" fill="#c8102e"/><path d="M0 24.62 Q15 21.42 30 24.62 T60 24.62 L60 27.69 Q45 30.89 30 27.69 T0 27.69 Z" fill="#c8102e"/><path d="M0 30.77 Q15 27.57 30 30.77 T60 30.77 L60 33.85 Q45 37.05 30 33.85 T0 33.85 Z" fill="#c8102e"/><path d="M0 36.92 Q15 33.72 30 36.92 T60 36.92 L60 40.00 Q45 43.20 30 40.00 T0 40.00 Z" fill="#c8102e"/><path d="M0 0 Q12 -2.6 24 -0.6 L24 20.94 Q12 18.94 0 21.54 Z" fill="#2a2f6b"/><circle cx="2.40" cy="1.12" r="0.75" fill="#ffffff"/><circle cx="6.30" cy="1.48" r="0.75" fill="#ffffff"/><circle cx="10.20" cy="1.83" r="0.75" fill="#ffffff"/><circle cx="14.10" cy="1.81" r="0.75" fill="#ffffff"/><circle cx="18.00" cy="1.45" r="0.75" fill="#ffffff"/><circle cx="21.90" cy="1.09" r="0.75" fill="#ffffff"/><circle cx="4.35" cy="5.00" r="0.75" fill="#ffffff"/><circle cx="8.25" cy="5.36" r="0.75" fill="#ffffff"/><circle cx="12.15" cy="5.69" r="0.75" fill="#ffffff"/><circle cx="16.05" cy="5.33" r="0.75" fill="#ffffff"/><circle cx="19.95" cy="4.97" r="0.75" fill="#ffffff"/><circle cx="2.40" cy="8.52" r="0.75" fill="#ffffff"/><circle cx="6.30" cy="8.88" r="0.75" fill="#ffffff"/><circle cx="10.20" cy="9.23" r="0.75" fill="#ffffff"/><circle cx="14.10" cy="9.21" r="0.75" fill="#ffffff"/><circle cx="18.00" cy="8.85" r="0.75" fill="#ffffff"/><circle cx="21.90" cy="8.49" r="0.75" fill="#ffffff"/><circle cx="4.35" cy="12.40" r="0.75" fill="#ffffff"/><circle cx="8.25" cy="12.76" r="0.75" fill="#ffffff"/><circle cx="12.15" cy="13.09" r="0.75" fill="#ffffff"/><circle cx="16.05" cy="12.73" r="0.75" fill="#ffffff"/><circle cx="19.95" cy="12.37" r="0.75" fill="#ffffff"/><circle cx="2.40" cy="15.92" r="0.75" fill="#ffffff"/><circle cx="6.30" cy="16.28" r="0.75" fill="#ffffff"/><circle cx="10.20" cy="16.64" r="0.75" fill="#ffffff"/><circle cx="14.10" cy="16.61" r="0.75" fill="#ffffff"/><circle cx="18.00" cy="16.25" r="0.75" fill="#ffffff"/><circle cx="21.90" cy="15.89" r="0.75" fill="#ffffff"/><path d="M30 -3 Q40 20 30 43 L36 43 Q46 20 36 -3 Z" fill="#000" opacity=".10"/></svg></span>',
		);
	}
	if ( j5_product_has_tag_slug( $product, J5_BADGE_TAG_GUARANTEE ) ) {
		$out[] = array(
			'key'    => 'guarantee',
			'label'  => 'Life Safety<br>Guarantee',
			'detail' => 'Eligible for the Life Safety Replacement Guarantee.',
			'icon'   => '<span class="j5-badge__seal" aria-hidden="true"><img src="' . esc_url( get_stylesheet_directory_uri() . '/assets/img/badges/life-safety-guarantee.jpg' ) . '" alt="" width="62" height="62" loading="lazy" decoding="async"></span>',
		);
	}
	return apply_filters( 'j5_product_badges', $out, $product );
}

/** Badge row under the product name. Prints nothing when the product earns no badge. */
function j5_render_product_badges( $product ) {
	$badges = j5_product_badges( $product );
	if ( ! $badges ) return;
	?>
	<ul class="j5-badges" aria-label="Product credentials">
		<?php foreach ( $badges as $i => $b ) :
			$id = 'j5-badge-' . sanitize_key( $b['key'] ) . '-' . (int) $i; ?>
			<li class="j5-badge j5-badge--<?php echo esc_attr( $b['key'] ); ?>" tabindex="0" aria-describedby="<?php echo esc_attr( $id ); ?>">
				<?php echo $b['icon']; // built above from fixed markup ?>
				<span class="j5-badge__label"><?php echo wp_kses( $b['label'], array( 'br' => array() ) ); ?></span>
				<span class="j5-badge__tip" id="<?php echo esc_attr( $id ); ?>" role="tooltip"><?php echo esc_html( $b['detail'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}
