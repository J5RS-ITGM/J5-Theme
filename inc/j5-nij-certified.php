<?php
/**
 * J5 NIJ Certified indicator
 * --------------------------
 * Renders the "NIJ Certified" badge from one portal-published product meta key:
 *
 *   _j5_nij_certified   "yes" | "no" | ""   (parent product; variations inherit)
 *
 * The dealer portal is the source of truth and overwrites the key on every push.
 * The theme never writes it and never infers certification from the level
 * text, title or description. Only "yes" shows anything; "no" and "" show
 * nothing (never a negative badge).
 *
 *   j5_is_nij_certified( $product )      bool
 *   j5_render_nij_certified_tag()        product page tag, next to the LE Only line
 *   card badge                           via the j5_product_card_badges filter
 *   shop filter                          ?nij=yes (meta query), rendered in the sidebar
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5_NIJ_META = '_j5_nij_certified';

/**
 * @param WC_Product|int $product
 * @return bool
 */
function j5_is_nij_certified( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) return false;
	$id  = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	$raw = strtolower( trim( (string) get_post_meta( $id, J5_NIJ_META, true ) ) );
	return 'yes' === $raw;
}

/** Product page tag. Called from woocommerce/single-product.php right after the LE Only line. */
function j5_render_nij_certified_tag( $product ) {
	if ( ! j5_is_nij_certified( $product ) ) return;
	?>
	<div class="j5-nij" role="note">
		<span class="j5-nij__tag" title="On the NIJ Compliant Products List for its stated standard">
			<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2.5 4 5.5v6c0 5 3.4 8.6 8 10 4.6-1.4 8-5 8-10v-6l-8-3Z"/><path d="m8.5 12 2.3 2.3L15.5 9.6"/></svg>
			NIJ Certified
		</span>
		<span class="j5-nij__body">On the NIJ Compliant Products List for its stated standard.</span>
	</div>
	<?php
}

/** Card badge (shop grid, category, brand, related). Sits after LE ONLY when both apply. */
add_filter( 'j5_product_card_badges', function ( $badges, $product ) {
	if ( j5_is_nij_certified( $product ) ) {
		$badges[] = array( 'class' => 'nij', 'label' => 'NIJ CERTIFIED' );
	}
	return $badges;
}, 20, 2 );

/** Shop filter: ?nij=yes limits the grid to certified products. */
function j5_nij_filter_active() {
	return isset( $_GET['nij'] ) && 'yes' === sanitize_key( $_GET['nij'] );
}

function j5_nij_filter_meta_query() {
	return array( 'key' => J5_NIJ_META, 'value' => 'yes', 'compare' => '=' );
}

/** Sidebar group: one pill. Uses the same toggle URL helper as the other pills. */
function j5_render_filter_nij() {
	if ( ! function_exists( 'j5_filter_toggle_url' ) ) return;
	$active = j5_nij_filter_active();
	?>
	<div class="j5-filter-group">
		<div class="j5-filter-head"><h4>Certification</h4><span class="toggle">−</span></div>
		<div class="j5-pill-row">
			<a href="<?php echo esc_url( j5_filter_toggle_url( 'nij', 'yes', false ) ); ?>" class="j5-pill <?php echo $active ? 'active' : ''; ?>">NIJ Certified</a>
		</div>
	</div>
	<?php
}
