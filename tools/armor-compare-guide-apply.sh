#!/usr/bin/env bash
# Armor Comparison Tool page (97935), layout B (2026-11-08):
#   intro -> quick start band (3 steps + common questions, hideable) -> tool -> guide on the tool's grid
#   (On this page | Reading the specs cards, Where the data comes from, disclaimer).
# Needs the theme with J5-AC-GUIDE in assets/css/j5-armor-compare.css and assets/js/j5-armor-compare.js.
# Backs up the current page content to /tmp first. Run as root: bash <this file>
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com
ID=97935
W="sudo -u j5rescue -i -- bash -c"
cat > /tmp/compare-$ID.html <<'EOF'
<!-- wp:paragraph {"className":"j5ac-lead"} -->
<p class="j5ac-lead">Compare body armor plates side by side using the specs each manufacturer publishes. Filter by protection level, NIJ standard, cut, weight and manufacturer, then pick up to six plates to see them next to each other.</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<section class="j5ac-qs" data-j5ac-qs aria-label="Quick start">
<div class="j5ac-qs-head"><p>New here? Quick start</p><button type="button" class="j5ac-qs-toggle" data-j5ac-qs-toggle aria-expanded="true" hidden>Hide</button></div>
<div class="j5ac-qs-body">
<div class="j5ac-qs-how">
<h2 class="j5ac-qs-h">How to use the armor comparison tool</h2>
<ol class="j5ac-qs-steps">
<li><span><strong>Pick a category</strong>Plates are live now. Helmets, soft armor and ballistic shields are coming soon.</span></li>
<li><span><strong>Narrow it down</strong>Press Help me choose and answer three quick questions, or set the filters yourself. On a phone, tap Filters.</span></li>
<li><span><strong>Compare up to six</strong>Click a model for every size and its spec sheet. Press Compare on up to six. Unequal plates are flagged, and you can compare anyway.</span></li>
</ol>
<p class="j5ac-qs-tip">Switch between Rows, Cards and Chart, and group results by protection level, cut or manufacturer. New to plate specs? <a href="#j5ac-specs">Reading the specs</a> is below the tool.</p>
</div>
<div class="j5ac-qs-faq">
<h2 class="j5ac-qs-h">Common questions</h2>
<details><summary>Does J5 Rescue Supply sell every plate in the tool?</summary><p>No. The tool covers plates from across the market so you can compare fairly. Models we sell carry the J5 mark next to the name. Hover or tap it to see whether it is in the store or available by quote.</p></details>
<details><summary>Why is a model marked "Not an equal comparison"?</summary><p>After you pick your first plate, the tool marks any model whose coverage differs by more than 20%, that has a different protection level or test standard, or whose weight differs by more than 30%. Those differences make side by side numbers misleading, but you can still compare them.</p></details>
<details><summary>When will helmets, soft armor and shields be added?</summary><p>We're still loading and checking the spec sheets for those categories. They'll appear here as soon as the data is complete.</p></details>
<details><summary>How do I order a plate I found here?</summary><p>Open a plate with the J5 mark to find its product page or a quote link. For quoted plates or for agency and bulk orders, use Request a Quote or call us at <a href="tel:+16304424938">(630) 442-4938</a>.</p></details>
</div>
</div>
</section>
<!-- /wp:html -->

<!-- wp:shortcode -->
[j5_armor_compare]
<!-- /wp:shortcode -->

<!-- wp:html -->
<div class="j5ac-gd" id="j5ac-guide" data-j5ac-gd>
<nav class="j5ac-gd-nav" aria-label="On this page"><p>On this page</p><a href="#j5ac-specs" class="is-on">Reading the specs</a><a href="#j5ac-data">Where the data comes from</a><a href="#j5ac-info">For informational use only</a></nav>
<div class="j5ac-gd-body">
<section id="j5ac-specs">
<h2>Reading the specs</h2>
<div class="j5ac-gd-cards">
<div class="j5ac-gd-card"><h3>Protection level</h3><p>NIJ Standard 0101.06 rates armor Level IIA, II and IIIA for handgun threats and Level III and IV for rifle threats. The newer NIJ Standard 0101.07 renames them: HG1 and HG2 for handgun, and RF1, RF2 and RF3 for rifle. "Level III+" is not an NIJ level. Manufacturers use it for plates rated beyond the Level III test rounds, so check the special threats to see exactly what a III+ plate is rated to stop.</p></div>
<div class="j5ac-gd-card"><h3>NIJ certified vs. tested to a standard</h3><p>"NIJ certified" means the model is on the NIJ Compliant Products List. A plate can be tested to an NIJ standard without being on that list, so the tool shows the standard a plate was tested to and, separately, whether it is certified. "Manufacturer tested" means the rating comes from the manufacturer's own testing rather than an NIJ standard.</p></div>
<div class="j5ac-gd-card"><h3>Standalone vs. ICW</h3><p>A standalone plate reaches its rating on its own. An ICW (in conjunction with) plate reaches its rating only when worn over the specific soft armor listed on its spec sheet. Compare ICW plates with that soft armor in mind.</p></div>
<div class="j5ac-gd-card"><h3>Ounces per square inch</h3><p>Weight divided by coverage area. It lets you compare plates of different sizes fairly: the lower the number, the lighter the plate is for the area it covers. The tool calculates it from each plate's listed size and weight.</p></div>
<div class="j5ac-gd-card"><h3>Cuts and sizes</h3><p>Shooter's cut plates have the top corners cut so a rifle stock sits against your shoulder. SAPI cut plates follow the military sizes: Small 8.75 x 11.75 in, Medium 9.5 x 12.5 in, Large 10.25 x 13.25 in and XL 11 x 14 in. A 10 x 12 in shooter's cut and a SAPI Medium cover about the same area, so the tool shows one of those by default. Side plates (usually 6 x 6 or 6 x 8 in) are listed separately from full-size plates.</p></div>
<div class="j5ac-gd-card"><h3>Special threats</h3><p>Rounds beyond the standard test rounds that the manufacturer says the plate stops, such as M855 or 7.62x39 mild steel core. The tool groups them by caliber and marks armor piercing rounds in red.</p></div>
</div>
</section>
<section id="j5ac-data">
<h2>Where the data comes from</h2>
<p>Every spec comes from the manufacturer's own spec sheet, and each model links to the sheet with the date we pulled it. NIJ certification is checked against the NIJ Compliant Products List. The tool includes plates we don't sell, so you can compare the whole market, not just our catalog. Models J5 sells carry the J5 mark next to the name.</p>
</section>
<section id="j5ac-info">
<div class="j5ac-disclaimer">
<p><strong>For informational use only.</strong> Specifications are as published by each manufacturer and can change without notice, and errors can happen when they are entered. Nothing here guarantees ballistic performance or recommends a plate for a specific threat. Confirm ratings, sizing and certification with the manufacturer's current spec sheet or with us before you buy, and follow your agency's requirements.</p>
<p><strong>Think something is wrong?</strong> Use <em>Report incorrect information</em> on any model, or contact us at <a href="tel:+16304424938">(630) 442-4938</a>, <a href="mailto:info@j5rescue.com">info@j5rescue.com</a> or through our <a href="/contact-us/">contact page</a>. We review every report.</p>
</div>
</section>
</div>
</div>
<!-- /wp:html -->
EOF
chmod 644 /tmp/compare-$ID.html
STAMP=$(date +%s)
$W "cd $SITE && wp post get $ID --field=post_content 2>/dev/null" > /tmp/compare-prev-$ID-$STAMP.html
echo "backup: /tmp/compare-prev-$ID-$STAMP.html ($(wc -c < /tmp/compare-prev-$ID-$STAMP.html) bytes)"
$W "cd $SITE && wp post update $ID /tmp/compare-$ID.html 2>/dev/null"
echo -n "url: "; $W "cd $SITE && wp post url $ID 2>/dev/null"
$W "cd $SITE && wp cache flush 2>/dev/null"
echo "To undo: sudo -u j5rescue -i -- bash -c \"cd $SITE && wp post update $ID /tmp/compare-prev-$ID-$STAMP.html\""
