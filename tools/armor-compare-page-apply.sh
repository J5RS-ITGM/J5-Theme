#!/usr/bin/env bash
# Armor Comparison Tool page (97935): intro above the tool, how-to, spec guide, disclaimer and FAQ below it.
# Also switches Helmets to "Coming soon" (Shields already is). Backs up the old content to /tmp first.
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com
ID=97935
W="sudo -u j5rescue -i -- bash -c"
cat > /tmp/compare-$ID.html <<'EOF'
<!-- wp:paragraph {"className":"j5ac-lead"} -->
<p class="j5ac-lead">Compare body armor plates side by side using the specs each manufacturer publishes. Filter by protection level, NIJ standard, cut, weight and manufacturer, then pick up to six plates to see them next to each other.</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[j5_armor_compare]
<!-- /wp:shortcode -->

<!-- wp:group {"className":"j5ac-seo"} -->
<div class="wp-block-group j5ac-seo">

<!-- wp:heading -->
<h2 class="wp-block-heading">How to use the armor comparison tool</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list">
<li><strong>Pick a category.</strong> Plates are live now. Helmets, soft armor and ballistic shields are coming soon.</li>
<li><strong>Not sure where to start? Press Help me choose.</strong> It asks what the plate needs to stop, whether you need NIJ certification, and whether you want full-size or side plates, then sets the filters for you. Change them any time.</li>
<li><strong>Narrow the list with filters.</strong> Filter by protection level, test standard, standalone or ICW, cut, curve, size, maximum weight and manufacturer. On a phone, tap Filters.</li>
<li><strong>Switch views.</strong> Rows shows every key spec, Cards are quicker to scan, and Chart lines up weight, ounces per square inch or coverage on one scale. Group the results by protection level, cut or manufacturer.</li>
<li><strong>Open a model for the details.</strong> Click any model to see every size it comes in, the weight and coverage of each, its special threats, the threat matrix when the manufacturer publishes one, and a link to the spec sheet.</li>
<li><strong>Compare up to six.</strong> Press Compare on a listing or on a single size. Models that differ from your first pick in size, protection level, test standard or weight are marked so you know the comparison isn't equal. You can still add them with Compare anyway.</li>
</ol>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Reading the specs</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Protection level</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>NIJ Standard 0101.06 rates armor Level IIA, II and IIIA for handgun threats and Level III and IV for rifle threats. The newer NIJ Standard 0101.07 renames them: HG1 and HG2 for handgun, and RF1, RF2 and RF3 for rifle. "Level III+" is not an NIJ level. Manufacturers use it for plates rated beyond the Level III test rounds, so check the special threats to see exactly what a III+ plate is rated to stop.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">NIJ certified vs. tested to a standard</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>"NIJ certified" means the model is on the NIJ Compliant Products List. A plate can be tested to an NIJ standard without being on that list, so the tool shows the standard a plate was tested to and, separately, whether it is certified. "Manufacturer tested" means the rating comes from the manufacturer's own testing rather than an NIJ standard.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Standalone vs. ICW</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>A standalone plate reaches its rating on its own. An ICW (in conjunction with) plate reaches its rating only when worn over the specific soft armor listed on its spec sheet. Compare ICW plates with that soft armor in mind.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Ounces per square inch</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Weight divided by coverage area. It lets you compare plates of different sizes fairly: the lower the number, the lighter the plate is for the area it covers. The tool calculates it from each plate's listed size and weight.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Cuts and sizes</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Shooter's cut plates have the top corners cut so a rifle stock sits against your shoulder. SAPI cut plates follow the military sizes: Small 8.75 x 11.75 in, Medium 9.5 x 12.5 in, Large 10.25 x 13.25 in and XL 11 x 14 in. A 10 x 12 in shooter's cut and a SAPI Medium cover about the same area, so the tool shows one of those by default. Side plates (usually 6 x 6 or 6 x 8 in) are listed separately from full-size plates.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Special threats</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Rounds beyond the standard test rounds that the manufacturer says the plate stops, such as M855 or 7.62x39 mild steel core. The tool groups them by caliber and marks armor piercing rounds in red.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Where the data comes from</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Every spec comes from the manufacturer's own spec sheet, and each model links to the sheet with the date we pulled it. NIJ certification is checked against the <a href="https://cpl.nij.ojp.gov/" target="_blank" rel="noopener">NIJ Compliant Products List</a>. The tool includes plates we don't sell, so you can compare the whole market, not just our catalog. Models J5 sells carry the J5 mark next to the name.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">For informational use only</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"j5ac-disclaimer"} -->
<p class="j5ac-disclaimer">This tool is for informational use only. Specifications are as published by each manufacturer and can change without notice, and errors can happen when they are entered. Nothing here guarantees ballistic performance or recommends a plate for a specific threat. Confirm ratings, sizing and certification with the manufacturer's current spec sheet or with us before you buy, and follow your agency's requirements.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Think something is wrong?</strong> Use <em>Report incorrect information</em> on any model, or contact us at <a href="tel:+16304424938">(630) 442-4938</a>, <a href="mailto:info@j5rescue.com">info@j5rescue.com</a> or through our <a href="/contact-us/">contact page</a>. We review every report.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Common questions about the armor comparison tool</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Does J5 Rescue Supply sell every plate in the tool?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>No. The tool covers plates from across the market so you can compare fairly. Models we sell carry the J5 mark next to the name. Hover or tap it to see whether it is in the store or available by quote.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Why is a model marked "Not an equal comparison"?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>After you pick your first plate, the tool marks any model whose coverage differs by more than 20%, that has a different protection level or test standard, or whose weight differs by more than 30%. Those differences make side by side numbers misleading, but you can still compare them.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">When will helmets, soft armor and shields be added?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We're still loading and checking the spec sheets for those categories. They'll appear here as soon as the data is complete.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">How do I order a plate I found here?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Open a plate with the J5 mark to find its product page or a quote link. For quoted plates or for agency and bulk orders, use <a href="/request-a-quote/">Request a Quote</a> or call us at <a href="tel:+16304424938">(630) 442-4938</a>.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
EOF
chmod 644 /tmp/compare-$ID.html
STAMP=$(date +%s)
$W "cd $SITE && wp post get $ID --field=post_content 2>/dev/null" > /tmp/compare-prev-$ID-$STAMP.html
echo "backup: /tmp/compare-prev-$ID-$STAMP.html"
$W "cd $SITE && wp post update $ID /tmp/compare-$ID.html --post_title='Armor Comparison Tool' 2>/dev/null"
$W "cd $SITE && wp post meta update $ID rank_math_title 'Body Armor Plate Comparison Tool: Compare Specs Side by Side | J5 Rescue Supply' 2>/dev/null"
$W "cd $SITE && wp post meta update $ID rank_math_description 'Compare body armor plates side by side: protection level, NIJ certification, weight, coverage, sizes and special threats, straight from manufacturer spec sheets.' 2>/dev/null"
$W "cd $SITE && wp post meta update $ID rank_math_focus_keyword 'body armor comparison' 2>/dev/null"
# Helmets to "Coming soon" (keeps Shields and anything else already set)
$W "cd $SITE && wp eval '\$a = get_option( \"j5_armor_ref_settings\", array() ); if ( ! is_array( \$a ) ) \$a = array(); \$cs = isset( \$a[\"coming_soon\"] ) && is_array( \$a[\"coming_soon\"] ) ? \$a[\"coming_soon\"] : array( \"shield\" ); foreach ( array( \"shield\", \"helmet\" ) as \$c ) if ( ! in_array( \$c, \$cs, true ) ) \$cs[] = \$c; \$a[\"coming_soon\"] = \$cs; update_option( \"j5_armor_ref_settings\", \$a, true ); if ( function_exists( \"j5_armor_ref_after_push\" ) ) j5_armor_ref_after_push( \"settings\" ); echo \"Coming soon: \" . implode( \", \", \$cs ) . \"\n\";' 2>/dev/null"
echo -n "url: "; $W "cd $SITE && wp post url $ID 2>/dev/null"
$W "cd $SITE && wp cache flush 2>/dev/null"
