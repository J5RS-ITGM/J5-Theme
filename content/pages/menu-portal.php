<?php
/**
 * Adds the Client Portal & QuarterMaster page (/client-portal/) to the header menu.
 *   - "For Agencies" top-level item: its empty link now opens the page, and the
 *     page is added as the first item in its dropdown.
 *   - No "For Agencies" item: adds a top-level "Client Portal" item instead.
 * Re-runnable: skips anything that already links to the page.
 * Run: wp eval-file wp-content/themes/j5-theme/content/pages/menu-portal.php
 */
$page = get_page_by_path( 'client-portal' );
if ( ! $page ) { echo "Page /client-portal/ not found. Run create-portal-page.php first.\n"; return; }
$locs = get_nav_menu_locations();
$menu = $locs['primary'] ?? 0;
if ( ! $menu ) { echo "No menu assigned to the primary location. Locations: " . implode( ', ', array_keys( $locs ) ) . "\n"; return; }

$tree = function () use ( $menu ) {
	foreach ( (array) wp_get_nav_menu_items( $menu ) as $i ) {
		echo ( $i->menu_item_parent ? '    - ' : '  ' ) . $i->title . '  ->  ' . ( $i->url ?: '(no link)' ) . "\n";
	}
};
echo "Header menu BEFORE:\n"; $tree();

$items  = (array) wp_get_nav_menu_items( $menu );
$agency = null; $linked = false;
foreach ( $items as $i ) {
	if ( ! $i->menu_item_parent && preg_match( '/^for\s+agencies$/i', trim( $i->title ) ) ) $agency = $i;
	if ( 'post_type' === $i->type && (int) $i->object_id === $page->ID && $i->menu_item_parent ) $linked = true;
}

if ( $agency ) {
	// Parent: point the empty "For Agencies" link at the page (keeps title and position).
	if ( ! ( 'post_type' === $agency->type && (int) $agency->object_id === $page->ID ) ) {
		wp_update_nav_menu_item( $menu, $agency->ID, array(
			'menu-item-title'     => $agency->title,
			'menu-item-object-id' => $page->ID,
			'menu-item-object'    => 'page',
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => 0,
			'menu-item-position'  => $agency->menu_order,
		) );
		echo "\"{$agency->title}\" now links to /client-portal/\n";
	}
	// Child: first item in the dropdown.
	if ( ! $linked ) {
		$kids = array_filter( $items, function ( $i ) use ( $agency ) { return (int) $i->menu_item_parent === (int) $agency->ID; } );
		wp_update_nav_menu_item( $menu, 0, array(
			'menu-item-title'     => 'Client Portal & QuarterMaster',
			'menu-item-object-id' => $page->ID,
			'menu-item-object'    => 'page',
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $agency->ID,
			'menu-item-position'  => $kids ? min( wp_list_pluck( $kids, 'menu_order' ) ) : $agency->menu_order + 1,
		) );
		echo "Added \"Client Portal & QuarterMaster\" under \"{$agency->title}\"\n";
	}
} else {
	$top = false;
	foreach ( $items as $i ) if ( 'post_type' === $i->type && (int) $i->object_id === $page->ID ) $top = true;
	if ( ! $top ) {
		wp_update_nav_menu_item( $menu, 0, array(
			'menu-item-title'     => 'Client Portal',
			'menu-item-object-id' => $page->ID,
			'menu-item-object'    => 'page',
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		) );
		echo "No \"For Agencies\" item found; added top-level \"Client Portal\"\n";
	}
}
wp_cache_delete( $menu, 'nav_menu' );
echo "\nHeader menu AFTER:\n"; $tree();
