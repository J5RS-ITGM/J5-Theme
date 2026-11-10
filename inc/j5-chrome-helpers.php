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

/* =========================================================================
 * J5-MEGA-CATS-1 (2026-11-09; departments layout J5-MEGA-DEPTS-1): the mega menu, the phone drawer's Shop list and the
 * footer Shop column are built from the product category tree (the DP pushes it),
 * not from a hand-kept WP menu. Only categories with products show. Order: the
 * category "order" set in WC (Products > Categories drag order), then name.
 * Set add_filter( 'j5_mega_menu_source', fn() => 'menu' ) to go back to the old
 * Shop Menu (ID 17) source.
 * ====================================================================== */

/** Products in a category including its subcategories (WC keeps this count). */
function j5_cat_product_count( $term ) {
	$n = get_term_meta( $term->term_id, 'product_count_product_cat', true );
	return '' === $n ? (int) $term->count : (int) $n;
}

/**
 * [ [ 'term' => WP_Term, 'url' => string, 'children' => [ WP_Term, ... ] ], ... ]
 * Top-level categories with products, each with its subcategories that have products.
 */
function j5_shop_category_tree() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$key = 'j5_shop_cat_tree_' . md5( wp_cache_get_last_changed( 'terms' ) );
	$hit = get_transient( $key );
	if ( is_array( $hit ) ) {
		return $memo = $hit;
	}
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		return $memo = array();
	}
	$skip   = (int) get_option( 'default_product_cat' ); // Uncategorized
	$by_par = array();
	foreach ( $terms as $t ) {
		if ( (int) $t->term_id === $skip || 'uncategorized' === $t->slug || j5_cat_product_count( $t ) < 1 ) {
			continue;
		}
		$by_par[ (int) $t->parent ][] = $t;
	}
	$sort = function ( &$list ) {
		usort( $list, function ( $a, $b ) {
			$oa = (int) get_term_meta( $a->term_id, 'order', true );
			$ob = (int) get_term_meta( $b->term_id, 'order', true );
			return $oa === $ob ? strnatcasecmp( $a->name, $b->name ) : $oa - $ob;
		} );
	};
	$out = array();
	$top = $by_par[0] ?? array();
	$sort( $top );
	foreach ( $top as $t ) {
		$kids = $by_par[ (int) $t->term_id ] ?? array();
		$sort( $kids );
		$link = get_term_link( $t );
		$out[] = array( 'term' => $t, 'url' => is_wp_error( $link ) ? home_url( '/shop/' ) : $link, 'children' => $kids );
	}
	set_transient( $key, $out, DAY_IN_SECONDS );
	return $memo = $out;
}

function j5_mega_from_categories() {
	return 'menu' !== apply_filters( 'j5_mega_menu_source', 'categories' );
}

/**
 * Departments for the mega menu (J5-MEGA-DEPTS-1, 2026-11-09, layout "C").
 * Each: [ name, one-line hint, [ top-level category slugs, in display order ] ].
 * A populated top-level category not listed here lands in "More" automatically.
 * Change with add_filter( 'j5_mega_departments', function ( $d ) { ...; return $d; } ).
 */
function j5_mega_departments() {
	return apply_filters( 'j5_mega_departments', array(
		array( 'Armor & Protection', 'Plates, shields, carriers, helmets', array( 'body-armor', 'shields', 'carriers', 'helmets' ) ),
		array( 'Medical & Rescue', 'EMS, fire, water rescue, training', array( 'medical', 'fire-rescue-equipment', 'water-rescue', 'training' ) ),
		array( 'Less Lethal & Weapon', 'Launchers, optics, lights, accessories', array( 'less-lethal', 'lights', 'optics', 'weapon-accessories' ) ),
		array( 'Duty Gear & Apparel', 'Pouches, holsters, bags, PPE, tops', array( 'pouches', 'holsters', 'duty-belt-gear', 'bags-cases', 'personal-protection-equipment', 'gloves', 'tops', 'patches-id-placards', 'cleaning' ) ),
		array( 'Tools & Tech', 'Knives, multi-tools, drones', array( 'tools-knives', 'electronics-communication' ) ),
	) );
}

/** Departments filled from the live tree: [ [ 'name', 'hint', 'rows' => tree rows ], ... ], empty ones dropped. */
function j5_mega_department_rows() {
	$by_slug = array();
	foreach ( j5_shop_category_tree() as $row ) {
		$by_slug[ $row['term']->slug ] = $row;
	}
	$out = array();
	foreach ( j5_mega_departments() as $d ) {
		$rows = array();
		foreach ( (array) $d[2] as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$rows[] = $by_slug[ $slug ];
				unset( $by_slug[ $slug ] );
			}
		}
		if ( $rows ) {
			$out[] = array( 'name' => $d[0], 'hint' => $d[1], 'rows' => $rows );
		}
	}
	if ( $by_slug ) {
		$out[] = array( 'name' => 'More', 'hint' => 'Everything else', 'rows' => array_values( $by_slug ) );
	}
	return $out;
}

/** Mega menu, layout C: departments on the left, that department's categories in columns. */
function j5_render_mega_menu_categories() {
	$depts = j5_mega_department_rows();
	if ( ! $depts ) {
		return;
	}
	$name = function ( $t ) { return html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ); };
	?>
	<div id="j5-mega-menu" class="j5-mega-menu j5-mega-menu--depts" aria-hidden="true" role="region" aria-label="Shop categories">
		<div class="j5-mega-menu__inner">
			<div class="j5-mega-menu__cats" role="tablist">
				<?php foreach ( $depts as $i => $d ) : ?>
					<button type="button"
						class="j5-mega-menu__cat<?php echo 0 === $i ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
						aria-controls="j5-mega-panel-d<?php echo (int) $i; ?>"
						data-j5-mega-cat="d<?php echo (int) $i; ?>"
						data-j5-mega-url="">
						<span class="j5-mega-menu__dept">
							<span class="j5-mega-menu__dept-name"><?php echo esc_html( $d['name'] ); ?></span>
							<span class="j5-mega-menu__dept-hint"><?php echo esc_html( $d['hint'] ); ?></span>
						</span>
						<svg class="j5-mega-menu__cat-arrow" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
					</button>
				<?php endforeach; ?>
				<a class="j5-mega-menu__all" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>">View all products</a>
			</div>
			<div class="j5-mega-menu__content">
				<?php foreach ( $depts as $i => $d ) : ?>
					<div id="j5-mega-panel-d<?php echo (int) $i; ?>"
						class="j5-mega-menu__panel<?php echo 0 === $i ? ' is-active' : ''; ?>"
						role="tabpanel"<?php echo 0 === $i ? '' : ' hidden'; ?>>
						<div class="j5-mega-menu__groups">
							<?php foreach ( $d['rows'] as $row ) : $t = $row['term']; ?>
								<div class="j5-mega-menu__group">
									<a class="j5-mega-menu__group-title" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $name( $t ) ); ?></a>
									<?php if ( $row['children'] ) : ?>
									<ul>
										<?php foreach ( $row['children'] as $c ) : $cl = get_term_link( $c ); if ( is_wp_error( $cl ) ) { continue; } ?>
											<li><a href="<?php echo esc_url( $cl ); ?>"><?php echo esc_html( $name( $c ) ); ?></a></li>
										<?php endforeach; ?>
									</ul>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="j5-mega-menu__foot">
							<span>Shop live stock. If we don’t have it, a factory order saves you money.</span>
							<?php $faq = (int) apply_filters( 'j5_faq_page_id', 9041 ); if ( $faq && 'publish' === get_post_status( $faq ) ) : ?>
								<a href="<?php echo esc_url( get_permalink( $faq ) . '#factory-orders' ); ?>">How factory orders work</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}

/** Phone drawer: departments, each opening to its categories (and their subcategories). */
function j5_render_mobile_shop_categories() {
	if ( ! j5_mega_from_categories() ) {
		return;
	}
	$depts = j5_mega_department_rows();
	if ( ! $depts ) {
		return;
	}
	$nm = function ( $t ) { return esc_html( html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ) ); };
	echo '<div class="j5-mobile-cats"><p class="j5-mobile-cats__title">Shop</p>';
	foreach ( $depts as $d ) {
		echo '<details class="j5-mobile-cats__group"><summary>' . esc_html( $d['name'] ) . '</summary><div class="j5-mobile-cats__body">';
		foreach ( $d['rows'] as $row ) {
			$t = $row['term'];
			if ( ! $row['children'] ) {
				echo '<a class="j5-mobile-cats__cat" href="' . esc_url( $row['url'] ) . '">' . $nm( $t ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				continue;
			}
			echo '<details class="j5-mobile-cats__sub"><summary>' . $nm( $t ) . '</summary><ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<li><a class="j5-mobile-cats__all" href="' . esc_url( $row['url'] ) . '">Shop all ' . $nm( $t ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			foreach ( $row['children'] as $c ) {
				$cl = get_term_link( $c );
				if ( ! is_wp_error( $cl ) ) {
					echo '<li><a href="' . esc_url( $cl ) . '">' . $nm( $c ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			echo '</ul></details>';
		}
		echo '</div></details>';
	}
	echo '<a class="j5-mobile-cats__cat j5-mobile-cats__viewall" href="' . esc_url( home_url( '/shop/' ) ) . '">View all products</a></div>';
}

function j5_render_mega_menu() {
	if ( j5_mega_from_categories() ) {
		j5_render_mega_menu_categories();
		return;
	}
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
	if ( j5_mega_from_categories() && j5_shop_category_tree() ) {
		echo '<ul class="j5-footer-links">';
		foreach ( array_slice( j5_shop_category_tree(), 0, 6 ) as $row ) {
			echo '<li><a href="' . esc_url( $row['url'] ) . '">' . esc_html( html_entity_decode( $row['term']->name, ENT_QUOTES, 'UTF-8' ) ) . '</a></li>';
		}
		echo '<li class="j5-footer-links__all"><a href="' . esc_url( home_url( '/shop/' ) ) . '">View all categories &rarr;</a></li></ul>';
		return;
	}
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
