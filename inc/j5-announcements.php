<?php
/**
 * J5 Announcements
 * ----------------
 * Configurable announcement banners/notices in three scopes:
 *   - site   : full-width strip at the top of every page (wp_body_open)
 *   - category : accented card on a product category archive
 *   - product  : accented card on a single product page
 *
 * Each announcement has: header, body, a preset color, an optional icon,
 * active flag, dismissible flag, and a scope target (category term id or
 * product id where applicable).
 *
 * These are marketing copy owned entirely by WordPress admin (Settings live
 * under the J5 Apps menu). They do NOT ride the hub publish cycle — that's a
 * deliberate choice: announcements change fast and are not catalog truth.
 *
 * Storage: option J5_ANNOUNCE_OPTION = array of announcements keyed by id.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const J5_ANNOUNCE_OPTION = 'j5_announcements';

/**
 * Preset colors. Slug => [ label, hex ]. Text auto-contrasts per scope.
 *
 * @return array
 */
function j5_announce_colors() {
	return array(
		'red'   => array( 'label' => 'Red',   'hex' => '#c8102e' ),
		'gold'  => array( 'label' => 'Gold',  'hex' => '#d4a044' ),
		'blue'  => array( 'label' => 'Blue',  'hex' => '#378add' ),
		'green' => array( 'label' => 'Green', 'hex' => '#4a8f5f' ),
		'gray'  => array( 'label' => 'Gray',  'hex' => '#5f5e5a' ),
	);
}

/**
 * Curated icon set (Tabler-style names rendered as a small inline glyph via
 * the theme's icon font/CSS). Slug => label. '' = no icon.
 *
 * @return array
 */
function j5_announce_icons() {
	return array(
		''               => 'None',
		'info-circle'    => 'Info',
		'truck'          => 'Shipping',
		'shield-check'   => 'Shield',
		'alert-triangle' => 'Warning',
		'tag'            => 'Tag / Sale',
		'bolt'           => 'New / Fast',
		'calendar'       => 'Date',
		'discount'       => 'Discount',
	);
}

/**
 * All stored announcements (raw), validated.
 *
 * @return array id => announcement
 */
function j5_announce_all() {
	$stored = get_option( J5_ANNOUNCE_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		return array();
	}

	$colors = j5_announce_colors();
	$icons  = j5_announce_icons();
	$clean  = array();

	foreach ( $stored as $id => $a ) {
		$id = sanitize_key( $id );
		if ( '' === $id || ! is_array( $a ) ) {
			continue;
		}
		$scope = isset( $a['scope'] ) && in_array( $a['scope'], array( 'site', 'category', 'product' ), true ) ? $a['scope'] : 'site';
		$color = isset( $a['color'] ) && isset( $colors[ $a['color'] ] ) ? $a['color'] : 'gold';
		$icon  = isset( $a['icon'] ) && isset( $icons[ $a['icon'] ] ) ? $a['icon'] : '';

		$clean[ $id ] = array(
			'scope'       => $scope,
			'target'      => isset( $a['target'] ) ? absint( $a['target'] ) : 0,
			'header'      => isset( $a['header'] ) ? (string) $a['header'] : '',
			'body'        => isset( $a['body'] ) ? (string) $a['body'] : '',
			'color'       => $color,
			'icon'        => $icon,
			'active'      => ! empty( $a['active'] ),
			'dismissible' => ! empty( $a['dismissible'] ),
		);
	}
	return $clean;
}

/**
 * Active announcements for a given scope (optionally filtered by target id).
 *
 * @param string $scope  'site' | 'category' | 'product'
 * @param int    $target term id or product id (0 = ignore target)
 * @return array
 */
function j5_announce_for( $scope, $target = 0 ) {
	$out = array();
	foreach ( j5_announce_all() as $id => $a ) {
		if ( ! $a['active'] || $a['scope'] !== $scope ) {
			continue;
		}
		if ( 'site' !== $scope && $target && (int) $a['target'] !== (int) $target ) {
			continue;
		}
		$a['id'] = $id;
		$out[]   = $a;
	}
	return $out;
}

/**
 * Inline icon markup for a slug (small glyph). Uses a simple <span> the theme
 * CSS styles; kept dependency-free so it renders even without an icon font.
 *
 * @param string $icon
 * @return string
 */
function j5_announce_icon_html( $icon ) {
	if ( '' === $icon ) {
		return '';
	}
	return '<span class="j5-announce-icon j5-ico-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
}

/**
 * Render a single announcement card (category/product scopes).
 *
 * @param array $a
 */
function j5_announce_render_card( $a ) {
	$colors = j5_announce_colors();
	$hex    = $colors[ $a['color'] ]['hex'];
	$dismiss = $a['dismissible'] ? '<button type="button" class="j5-announce-x" aria-label="Dismiss">&times;</button>' : '';

	printf(
		'<div class="j5-announce-card j5-announce-%1$s" style="border-left-color:%2$s;" data-announce-id="%3$s">%4$s<div class="j5-announce-text">%5$s%6$s</div>%7$s</div>',
		esc_attr( $a['color'] ),
		esc_attr( $hex ),
		esc_attr( $a['id'] ),
		j5_announce_icon_html( $a['icon'] ) ? '<span class="j5-announce-ico-wrap" style="color:' . esc_attr( $hex ) . ';">' . j5_announce_icon_html( $a['icon'] ) . '</span>' : '',
		$a['header'] ? '<strong>' . esc_html( $a['header'] ) . '</strong>' : '',
		$a['body'] ? '<span>' . esc_html( $a['body'] ) . '</span>' : '',
		$dismiss
	);
}

/* =========================================================================
 * Site-wide strip — top of every page
 * ====================================================================== */
add_action( 'wp_body_open', function () {
	$anns = j5_announce_for( 'site' );
	if ( empty( $anns ) ) {
		return;
	}
	$colors = j5_announce_colors();
	foreach ( $anns as $a ) {
		$hex = $colors[ $a['color'] ]['hex'];
		printf(
			'<div class="j5-announce-strip j5-announce-%1$s" style="background:%2$s;" data-announce-id="%3$s">%4$s<span class="j5-announce-strip-text">%5$s%6$s</span>%7$s</div>',
			esc_attr( $a['color'] ),
			esc_attr( $hex ),
			esc_attr( $a['id'] ),
			j5_announce_icon_html( $a['icon'] ),
			$a['header'] ? '<strong>' . esc_html( $a['header'] ) . '</strong> ' : '',
			$a['body'] ? esc_html( $a['body'] ) : '',
			$a['dismissible'] ? '<button type="button" class="j5-announce-x" aria-label="Dismiss">&times;</button>' : ''
		);
	}
}, 5 );

/* =========================================================================
 * Category archive — accented card
 * ====================================================================== */
add_action( 'woocommerce_before_main_content', function () {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term || empty( $term->term_id ) ) {
		return;
	}
	$anns = j5_announce_for( 'category', $term->term_id );
	if ( empty( $anns ) ) {
		return;
	}
	echo '<div class="j5-announce-cards j5-announce-category">';
	foreach ( $anns as $a ) {
		j5_announce_render_card( $a );
	}
	echo '</div>';
}, 6 );

/* =========================================================================
 * Single product — accented card (called from single-product.php)
 * ====================================================================== */
function j5_render_product_announcements( $product ) {
	if ( is_numeric( $product ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$anns = j5_announce_for( 'product', $product->get_id() );
	if ( empty( $anns ) ) {
		return;
	}
	echo '<div class="j5-announce-cards j5-announce-product">';
	foreach ( $anns as $a ) {
		j5_announce_render_card( $a );
	}
	echo '</div>';
}

/* =========================================================================
 * Dismiss behavior (session-only, no storage): hide via localStorage-free JS.
 * ====================================================================== */
add_action( 'wp_footer', function () {
	?>
	<script>
	( function () {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest && e.target.closest( '.j5-announce-x' );
			if ( ! btn ) { return; }
			var box = btn.closest( '[data-announce-id]' );
			if ( box ) { box.style.display = 'none'; }
		} );
	} )();
	</script>
	<?php
}, 100 );
