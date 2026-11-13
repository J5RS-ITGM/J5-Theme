#!/usr/bin/env bash
# Armor record privacy request page (/armor-record-privacy/). Creates the page, or updates it
# when the slug exists, with template-j5-armor-privacy.php. Content lives in the template.
# Needs J5_ARMOR_OPTOUT_URL in wp-config (see the deploy notes); warns if it's missing.
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com
SLUG=armor-record-privacy
WP="sudo -u j5rescue -i -- wp --path=$SITE"

ID=$($WP db query "SELECT ID FROM wptp_posts WHERE post_type='page' AND post_name='$SLUG' AND post_status IN ('publish','draft','private') ORDER BY ID LIMIT 1" --skip-column-names 2>/dev/null | tail -1)
if [ -z "$ID" ]; then
	ID=$($WP post create --post_type=page --post_status=publish --post_name="$SLUG" --post_title="Armor Record Privacy Request" --porcelain 2>/dev/null | tail -1)
	echo "created page $ID"
else
	echo "updating page $ID"
fi
[ -z "$ID" ] && { echo "FAILED: no page ID"; exit 1; }

$WP post meta update "$ID" _wp_page_template template-j5-armor-privacy.php >/dev/null 2>&1
$WP post meta update "$ID" rank_math_title "Remove My Details From Armor Records | J5 Rescue Supply" >/dev/null 2>&1
$WP post meta update "$ID" rank_math_description "Ask J5 Rescue Supply to remove your personal details from its armor plate records. A person reviews every request." >/dev/null 2>&1
$WP cache flush >/dev/null 2>&1

if $WP config has J5_ARMOR_OPTOUT_URL >/dev/null 2>&1; then echo "wp-config: J5_ARMOR_OPTOUT_URL is set"; else echo "wp-config: J5_ARMOR_OPTOUT_URL is MISSING, the page will show 'unavailable'"; fi
echo "template: $($WP post meta get "$ID" _wp_page_template 2>/dev/null | tail -1)"
echo "url: $($WP post url "$ID" 2>/dev/null | tail -1)"
