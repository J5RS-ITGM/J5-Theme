<?php
/**
 * J5 Chrome — Helpers (mega menu + footer menu renderers)
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function j5_get_shop_menu_id() {
	return (int) apply_filters( 'j5_shop_menu_id', 17 );
}

function j5_get_shop_menu_tree() {
	static $cache = null;
	if ( null !== $cache ) return $cache;
	$items = wp_get_nav_menu_items( j5_get_shop_menu_id() );
	if ( ! $items ) { $cache = array(); return $cache; }
	$tree = array();
	foreach ( $items as $item ) {
		$parent = (int) $item->menu_item_parent;
		if ( ! isset( $tree[ $parent ] ) ) $tree[ $parent ] = array();
		$tree[ $parent ][] = $item;
	}
	$cache = $tree;
	return $cache;
}

function j5_flatten_children( $parent_id, $tree ) {
	$out = array();
	if ( empty( $tree[ $parent_id ] ) ) return $out;
	foreach ( $tree[ $parent_id ] as $child ) {
		if ( '#' !== $child->url && ! empty( $child->url ) ) $out[] = $child;
		if ( ! empty( $tree[ $child->ID ] ) ) $out = array_merge( $out, j5_flatten_children( $child->ID, $tree ) );
	}
	return $out;
}

function j5_render_mega_menu() {
	$tree = j5_get_shop_menu_tree();
	if ( empty( $tree[0] ) ) return;
	$top_level = $tree[0];
	$shop_root = esc_url( home_url( '/shop/' ) );
	?>
	<div id="j5-mega-menu" class="j5-mega-menu" aria-hidden="true" role="region" aria-label="Shop categories">
		<div class="j5-mega-menu__inner">
			<div class="j5-mega-menu__cats" role="tablist">
				<?php foreach ( $top_level as $i => $cat ) :
					$label   = html_entity_decode( $cat->title, ENT_QUOTES, 'UTF-8' );
					$url     = ( '#' === $cat->url || empty( $cat->url ) ) ? $shop_root : esc_url( $cat->url );
					$is_deal = ( false !== stripos( $cat->title, 'deal' ) || false !== stripos( $cat->title, 'clearance' ) );
					?>
					<button type="button"
						class="j5-mega-menu__cat<?php echo $i === 0 ? ' is-active' : ''; ?><?php echo $is_deal ? ' is-deal' : ''; ?>"
						role="tab"
						aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						aria-controls="j5-mega-panel-<?php echo (int) $cat->ID; ?>"
						data-j5-mega-cat="<?php echo (int) $cat->ID; ?>"
						data-j5-mega-url="<?php echo esc_attr( $url ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
						<span class="j5-mega-menu__cat-arrow" aria-hidden="true">&rsaquo;</span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="j5-mega-menu__content">
				<?php foreach ( $top_level as $i => $cat ) :
					$label    = html_entity_decode( $cat->title, ENT_QUOTES, 'UTF-8' );
					$url      = ( '#' === $cat->url || empty( $cat->url ) ) ? $shop_root : esc_url( $cat->url );
					$children = j5_flatten_children( $cat->ID, $tree );
					?>
					<div id="j5-mega-panel-<?php echo (int) $cat->ID; ?>"
						class="j5-mega-menu__panel<?php echo $i === 0 ? ' is-active' : ''; ?>"
						role="tabpanel"<?php if ( $i !== 0 ) echo ' hidden'; ?>>
						<div class="j5-mega-menu__panel-head">
							<h3 class="j5-mega-menu__panel-title"><?php echo esc_html( $label ); ?></h3>
							<a href="<?php echo esc_url( $url ); ?>" class="j5-mega-menu__panel-viewall">View all &rarr;</a>
						</div>
						<?php if ( ! empty( $children ) ) : ?>
						<ul class="j5-mega-menu__items">
							<?php foreach ( $children as $child ) : ?>
								<li><a href="<?php echo esc_url( $child->url ); ?>"><?php echo esc_html( html_entity_decode( $child->title, ENT_QUOTES, 'UTF-8' ) ); ?></a></li>
							<?php endforeach; ?>
						</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}

function j5_render_footer_shop_menu() {
	$tree = j5_get_shop_menu_tree();
	if ( empty( $tree[0] ) ) {
		echo '<p class="j5-footer-links__empty">Shop Menu not configured.</p>';
		return;
	}
	$items = array_slice( $tree[0], 0, 6 );
	?>
	<ul class="j5-footer-links">
		<?php foreach ( $items as $item ) :
			$label = html_entity_decode( $item->title, ENT_QUOTES, 'UTF-8' );
			$url   = ( '#' === $item->url || empty( $item->url ) ) ? home_url( '/shop/' ) : $item->url;
			?>
			<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></li>
		<?php endforeach; ?>
		<li class="j5-footer-links__all"><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>">View all categories &rarr;</a></li>
	</ul>
	<?php
}

function j5_render_footer_menu_groups() {
	$locations = get_nav_menu_locations();
	if ( empty( $locations['footer_menu'] ) ) {
		echo '<div class="j5-footer-col"><h2 class="j5-footer-col__title">Support</h2><p class="j5-footer-links__empty">Footer menu not configured.</p></div>';
		echo '<div class="j5-footer-col"><h2 class="j5-footer-col__title">Legal</h2><p class="j5-footer-links__empty">Footer menu not configured.</p></div>';
		return;
	}
	$items = wp_get_nav_menu_items( (int) $locations['footer_menu'] );
	if ( ! $items ) return;
	$groups = array();
	foreach ( $items as $item ) {
		$parent = (int) $item->menu_item_parent;
		if ( 0 === $parent ) {
			$groups[ $item->ID ] = array( 'parent' => $item, 'children' => array() );
		} elseif ( isset( $groups[ $parent ] ) ) {
			$groups[ $parent ]['children'][] = $item;
		}
	}
	foreach ( $groups as $group ) {
		$title    = html_entity_decode( $group['parent']->title, ENT_QUOTES, 'UTF-8' );
		$is_legal = ( false !== stripos( $title, 'legal' ) );
		if ( $is_legal ) continue;
		$slug = sanitize_html_class( strtolower( $title ) );
		?>
		<div class="j5-footer-col j5-footer-col--<?php echo esc_attr( $slug ); ?>">
			<h2 class="j5-footer-col__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( ! empty( $group['children'] ) ) : ?>
			<ul class="j5-footer-links">
				<?php foreach ( $group['children'] as $child ) : ?>
					<li><a href="<?php echo esc_url( $child->url ); ?>"><?php echo esc_html( html_entity_decode( $child->title, ENT_QUOTES, 'UTF-8' ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>
		<?php
	}
	foreach ( $groups as $group ) {
		$title    = html_entity_decode( $group['parent']->title, ENT_QUOTES, 'UTF-8' );
		$is_legal = ( false !== stripos( $title, 'legal' ) );
		if ( ! $is_legal ) continue;
		?>
		<div class="j5-footer-col j5-footer-col--legal">
			<h2 class="j5-footer-col__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( ! empty( $group['children'] ) ) : ?>
			<ul class="j5-footer-links">
				<?php foreach ( $group['children'] as $child ) : ?>
					<li><a href="<?php echo esc_url( $child->url ); ?>"><?php echo esc_html( html_entity_decode( $child->title, ENT_QUOTES, 'UTF-8' ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}

function j5_render_footer_legal_mirror() {
	$locations = get_nav_menu_locations();
	if ( empty( $locations['footer_menu'] ) ) return;
	$items = wp_get_nav_menu_items( (int) $locations['footer_menu'] );
	if ( ! $items ) return;
	$legal_parent_id = null;
	foreach ( $items as $item ) {
		if ( 0 === (int) $item->menu_item_parent && false !== stripos( $item->title, 'legal' ) ) {
			$legal_parent_id = $item->ID;
			break;
		}
	}
	if ( ! $legal_parent_id ) return;
	$children = array();
	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent === $legal_parent_id ) $children[] = $item;
	}
	if ( empty( $children ) ) return;
	?>
	<ul class="j5-footer-bottom__legal-list">
		<?php foreach ( $children as $child ) : ?>
			<li><a href="<?php echo esc_url( $child->url ); ?>"><?php echo esc_html( html_entity_decode( $child->title, ENT_QUOTES, 'UTF-8' ) ); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<?php
}

function j5_is_external_url( $url ) {
	if ( empty( $url ) || '#' === $url ) return false;
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$url_host  = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $url_host ) return false;
	return ( strtolower( $site_host ) !== strtolower( $url_host ) );
}

add_filter( 'nav_menu_link_attributes', function ( $atts, $item, $args, $depth ) {
	if ( empty( $atts['href'] ) ) return $atts;
	if ( j5_is_external_url( $atts['href'] ) ) {
		$atts['class']  = ( isset( $atts['class'] ) ? $atts['class'] . ' ' : '' ) . 'j5-nav-external';
		$atts['target'] = '_blank';
		$atts['rel']    = trim( ( isset( $atts['rel'] ) ? $atts['rel'] . ' ' : '' ) . 'noopener' );
	}
	return $atts;
}, 10, 4 );

add_filter( 'nav_menu_item_title', function ( $title ) {
	return html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
}, 10, 1 );
