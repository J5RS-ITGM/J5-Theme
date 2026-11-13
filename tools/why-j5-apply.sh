#!/usr/bin/env bash
# "Why Choose J5" armor dealer page. Creates the page (or updates it when the slug exists)
# with template-j5-why-j5.php. The content lives in the template, so the page body stays empty.
# Rank Math title + description are set here. Prints the page ID and URL at the end.
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com
SLUG=why-choose-j5-armor-dealer
TITLE="Why Choose J5 Rescue Supply as Your Body Armor Dealer"
DESC="Authorized distributor, serial-matched plate sets, live stock you can see, and every plate tracked so recall notices reach you. Here's how J5 sells armor."
WP="sudo -u j5rescue -i -- wp --path=$SITE"

ID=$($WP db query "SELECT ID FROM wptp_posts WHERE post_type='page' AND post_name='$SLUG' AND post_status IN ('publish','draft','private') ORDER BY ID LIMIT 1" --skip-column-names 2>/dev/null | tail -1)
if [ -z "$ID" ]; then
	ID=$($WP post create --post_type=page --post_status=publish --post_name="$SLUG" --post_title="Why Choose J5" --porcelain 2>/dev/null | tail -1)
	echo "created page $ID"
else
	echo "updating page $ID"
fi
[ -z "$ID" ] && { echo "FAILED: no page ID"; exit 1; }

$WP post meta update "$ID" _wp_page_template template-j5-why-j5.php >/dev/null 2>&1
$WP post meta update "$ID" rank_math_title "$TITLE" >/dev/null 2>&1
$WP post meta update "$ID" rank_math_description "$DESC" >/dev/null 2>&1
$WP post meta update "$ID" rank_math_focus_keyword "body armor dealer" >/dev/null 2>&1
$WP post meta update "$ID" _j5_faq_schema off >/dev/null 2>&1   # the template prints its own FAQPage schema
$WP cache flush >/dev/null 2>&1

echo "template: $($WP post meta get "$ID" _wp_page_template 2>/dev/null | tail -1)"
echo "url: $($WP post url "$ID" 2>/dev/null | tail -1)"

# Footer menu: add this page right below "First Responder Discount" (same menu, same parent).
# Skips if a menu item for this page already exists.
HAVE=$($WP db query "SELECT COUNT(*) FROM wptp_postmeta WHERE meta_key='_menu_item_object_id' AND meta_value='$ID'" --skip-column-names 2>/dev/null | tail -1)
if [ "${HAVE:-0}" != "0" ]; then
	echo "menu: already linked ($HAVE item)"
else
	FOUND=""
	for MENU in $($WP menu list --fields=term_id --format=ids 2>/dev/null | tail -1); do
		ROW=$($WP menu item list "$MENU" --fields=db_id,title,position,menu_item_parent --format=csv 2>/dev/null | grep -i ',first responder discount,' | head -1)
		if [ -n "$ROW" ]; then
			POS=$(echo "$ROW" | cut -d, -f3); PARENT=$(echo "$ROW" | cut -d, -f4)
			$WP menu item add-post "$MENU" "$ID" --title="Why Choose J5" --position=$((POS + 1)) --parent-id="$PARENT" --porcelain >/dev/null 2>&1 \
				&& echo "menu: added to menu $MENU at position $((POS + 1)) (parent $PARENT)" \
				|| echo "menu: FAILED to add to menu $MENU"
			FOUND=1; break
		fi
	done
	[ -z "$FOUND" ] && echo "menu: no 'First Responder Discount' item found; add it by hand in Appearance > Menus"
	$WP cache flush >/dev/null 2>&1
fi
