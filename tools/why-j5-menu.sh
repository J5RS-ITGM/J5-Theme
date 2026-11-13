#!/usr/bin/env bash
# Puts the "Why Choose J5" page in the footer menu (theme location footer_menu), directly
# below the "Military & First Responder Discount" item, under the same parent (Support).
# Adds the item if it's missing, moves it if it's already there, then renumbers the whole
# menu so the order is exact. Safe to rerun.
#   bash why-j5-menu.sh            finds the page by slug
#   bash why-j5-menu.sh <page ID>
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com
SLUG=why-choose-j5-armor-dealer
LABEL="Why Choose J5"
WP="sudo -u j5rescue -i -- wp --path=$SITE"
Q() { $WP db query "$1" --skip-column-names 2>/dev/null | grep -v '^$'; }

ID="${1:-}"
[ -z "$ID" ] && ID=$(Q "SELECT ID FROM wptp_posts WHERE post_type='page' AND post_name='$SLUG' AND post_status='publish' ORDER BY ID LIMIT 1" | tail -1)
[ -z "$ID" ] && { echo "menu: page $SLUG not found"; exit 1; }

# Menu assigned to the footer_menu location.
MENU=$($WP eval 'echo (int) ( get_nav_menu_locations()["footer_menu"] ?? 0 );' 2>/dev/null | tail -1)
[ -z "$MENU" ] || [ "$MENU" = "0" ] && { echo "menu: no menu assigned to footer_menu"; exit 1; }

# Anchor item: title contains "first responder" (stored title, or the linked page's title).
ANCHOR=$($WP eval '
$items = wp_get_nav_menu_items( '"$MENU"' );
foreach ( (array) $items as $it ) {
	if ( false !== stripos( html_entity_decode( $it->title, ENT_QUOTES ), "first responder" ) ) { echo $it->ID . " " . (int) $it->menu_item_parent; break; }
}' 2>/dev/null | tail -1)
[ -z "$ANCHOR" ] && { echo "menu: no First Responder item in menu $MENU"; exit 1; }
ANCHOR_ID=${ANCHOR% *}; PARENT=${ANCHOR#* }

# Existing item for this page in this menu, else add one.
ITEM=$($WP eval '
foreach ( (array) wp_get_nav_menu_items( '"$MENU"' ) as $it ) { if ( "page" === $it->object && (int) $it->object_id === '"$ID"' ) { echo $it->ID; break; } }' 2>/dev/null | tail -1)
if [ -z "$ITEM" ]; then
	ITEM=$($WP menu item add-post "$MENU" "$ID" --title="$LABEL" --parent-id="$PARENT" --porcelain 2>/dev/null | tail -1)
	echo "menu: added item $ITEM"
else
	$WP menu item update "$ITEM" --title="$LABEL" --parent-id="$PARENT" >/dev/null 2>&1
	echo "menu: found item $ITEM, moving it"
fi

# Renumber every item in the menu: current order, with ours placed right after the anchor.
$WP eval '
$menu = '"$MENU"'; $mine = '"$ITEM"'; $anchor = '"$ANCHOR_ID"';
$items = wp_get_nav_menu_items( $menu, array( "post_status" => "any" ) );
usort( $items, function ( $a, $b ) { return $a->menu_order - $b->menu_order; } );
$order = array();
foreach ( $items as $it ) {
	if ( (int) $it->ID === $mine ) continue;
	$order[] = (int) $it->ID;
	if ( (int) $it->ID === $anchor ) $order[] = $mine;
}
foreach ( $order as $i => $id ) wp_update_post( array( "ID" => $id, "menu_order" => $i + 1 ) );
echo "menu: renumbered " . count( $order ) . " items\n";
' 2>/dev/null | tail -1
$WP cache flush >/dev/null 2>&1

# Show the Support column as it now stands.
$WP eval '
$items = wp_get_nav_menu_items( '"$MENU"' );
foreach ( (array) $items as $it ) if ( (int) $it->menu_item_parent === '"$PARENT"' ) echo "  " . $it->menu_order . "  " . html_entity_decode( $it->title, ENT_QUOTES ) . "\n";
' 2>/dev/null
