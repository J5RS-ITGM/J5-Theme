#!/usr/bin/env bash
# Footer menu (menu 1048, footer_menu location): Support column order, with the
# Why Choose J5 page added as "Why Buy Armor From J5".
#
#   Support
#     Military & First Responder Discount   item 96879
#     Why Buy Armor From J5                 new (page why-choose-j5-armor-dealer)
#     Armor Comparison Tool                 item 98411
#     Life Safety Guarantee                 item 98406
#     FAQ                                   item 97541
#   Legal (unchanged, after Support)        items 96878, 96897, 96898, 96910, 96899
#
# Item IDs come from the live menu listing of 2026-11-13. The script checks each one
# (same menu, same linked page) and stops without changing anything if one doesn't match.
# Existing labels are not changed. Safe to rerun: an existing link to the page is reused.
#
# PHP runs from a file in /tmp through `wp eval-file`. PHP passed inline through
# `sudo -u j5rescue -i --` breaks, because the login shell expands its $variables.
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com

cat > /tmp/j5-why-menu.php <<'PHP'
<?php
$menu = 1048;
$label = 'Why Buy Armor From J5';
// item ID => linked object ID, in the order they should appear
$support = 96877;
$expect = array( 96879 => 96875, 98411 => 97935, 98406 => 83814, 97541 => 9041 );
$legal  = array( 96878, 96897, 96898, 96910, 96899 );

$page = get_page_by_path( 'why-choose-j5-armor-dealer' );
if ( ! $page || 'publish' !== $page->post_status ) { echo "STOP: the Why Choose J5 page isn't published yet (run why-j5-apply.sh first). Nothing changed.\n"; return; }

$items = array();
foreach ( (array) wp_get_nav_menu_items( $menu, array( 'post_status' => 'any' ) ) as $it ) $items[ (int) $it->ID ] = $it;
foreach ( $expect as $id => $obj ) {
	if ( ! isset( $items[ $id ] ) || (int) $items[ $id ]->object_id !== $obj || (int) $items[ $id ]->menu_item_parent !== $support ) {
		echo "STOP: item $id isn't the expected Support link (page $obj). Nothing changed.\n"; return;
	}
}
foreach ( array_merge( array( $support ), $legal ) as $id ) {
	if ( ! isset( $items[ $id ] ) ) { echo "STOP: item $id is missing from menu $menu. Nothing changed.\n"; return; }
}

// Reuse an existing link to the page in this menu, else create it.
$why = 0;
foreach ( $items as $id => $it ) if ( 'page' === $it->object && (int) $it->object_id === (int) $page->ID ) { $why = $id; break; }
$args = array( 'menu-item-object-id' => $page->ID, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-title' => $label, 'menu-item-parent-id' => $support, 'menu-item-status' => 'publish' );
$res = wp_update_nav_menu_item( $menu, $why, $args );
if ( is_wp_error( $res ) ) { echo 'STOP: could not save the new item: ' . $res->get_error_message() . "\n"; return; }
echo ( $why ? "reused item $why" : "added item $res" ) . "\n";
$why = (int) $res;

$order = array_merge( array( $support, 96879, $why, 98411, 98406, 97541 ), $legal );
$seen  = array_flip( $order );
foreach ( $items as $id => $it ) if ( ! isset( $seen[ $id ] ) ) $order[] = $id; // anything else keeps its place at the end
foreach ( $order as $n => $id ) wp_update_post( array( 'ID' => $id, 'menu_order' => $n + 1 ) );

echo "Footer menu now:\n";
foreach ( (array) wp_get_nav_menu_items( $menu ) as $it ) echo '  ' . $it->menu_order . '  ' . ( $it->menu_item_parent ? '    ' : '' ) . html_entity_decode( $it->title ) . "\n";
PHP
chmod 644 /tmp/j5-why-menu.php
sudo -u j5rescue -i -- wp --path=$SITE eval-file /tmp/j5-why-menu.php
sudo -u j5rescue -i -- wp --path=$SITE cache flush >/dev/null 2>&1
