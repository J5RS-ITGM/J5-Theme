#!/usr/bin/env bash
# Life Safety Replacement Guarantee page, rebuilt as block content. Page 83814 (URL kept).
# Backs up the old content and Elementor meta to /tmp first, then strips the Elementor meta.
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com
ID=83814
W="sudo -u j5rescue -i -- bash -c"
SEAL=/wp-content/themes/j5-theme/assets/img/badges/life-safety-guarantee.jpg
cat > /tmp/lsg-$ID.html <<EOF
<!-- wp:image {"width":"180px","sizeSlug":"full","align":"left","className":"j5-lsg-seal"} -->
<figure class="wp-block-image alignleft size-full is-resized j5-lsg-seal"><img src="$SEAL" alt="Life Safety Replacement Guarantee seal" style="width:180px"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"className":"j5-lsg-lead"} -->
<p class="j5-lsg-lead"><strong>If gear you bought from us is damaged while saving a life, we replace it free.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The Life Safety Replacement Guarantee covers the equipment people count on when it matters most: plate carriers, body armor, tourniquets, pressure dressings and chest seals. If one of these items does its job and is damaged or used up saving a life, send us the details below and we'll replace it at no cost to you.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Eligible products show the <strong>Life Safety Guarantee</strong> badge on their product page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">What you'll need</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>Proof of purchase</strong> from J5 Rescue Supply LLC, such as your receipt or a paid invoice.</li>
<li><strong>A police report</strong> from a U.S. police agency, or an <strong>incident report</strong> if the event happened in the line of duty.</li>
<li><strong>The documentation for your product</strong>, listed in the coverage table below.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Who qualifies</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list">
<li>Purchasers located in the United States.</li>
<li>Civilians and bystanders who bought the equipment and used it to save a life.</li>
<li>First responders who bought the equipment for their own duty use.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Who doesn't qualify</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Organizations that bill for their services or have a budget for this equipment are not covered. That includes:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<li>Local government and federal agencies</li>
<li>Fire protection districts</li>
<li>EMS providers, public or private</li>
<li>Hospitals and clinics</li>
<li>Nonprofits and NGOs providing emergency medical or surgical support in war zones</li>
<li>Businesses required to provide the listed equipment</li>
<li>Equipment bought to treat combat casualties</li>
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Other businesses are reviewed case by case. Small businesses are usually covered; large businesses and corporations usually are not.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Coverage by product</h2>
<!-- /wp:heading -->

<!-- wp:table {"className":"j5-lsg-table"} -->
<figure class="wp-block-table j5-lsg-table"><table>
<thead><tr><th>Product</th><th>Replacement</th><th>What to send</th><th>Claim window</th></tr></thead>
<tbody>
<tr><td><strong>Plate carriers</strong></td><td>A carrier of equal or greater value</td><td>Photos of the front and back of the carrier</td><td>5 years from the purchase date on your receipt</td></tr>
<tr><td><strong>Body armor</strong></td><td>The same model when it's available. If it isn't, we'll work with you on the replacement.</td><td>Photos of the front and back of each plate, plus each plate's label and serial number</td><td>5 years from the purchase date on your receipt</td></tr>
<tr><td><strong>Tourniquets</strong></td><td>Any CoTCCC recommended tourniquet we carry</td><td>A written statement about the event, including how easy the tourniquet was to apply and how well it worked</td><td>No time limit</td></tr>
<tr><td><strong>Pressure dressings</strong></td><td>The same item</td><td>A written statement about the event and how the dressing performed</td><td>5 years from the purchase date on your receipt</td></tr>
<tr><td><strong>Chest seals</strong></td><td>The same item</td><td>A written statement about the event and how the chest seal performed</td><td>5 years from the purchase date on your receipt</td></tr>
</tbody>
</table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p><em>We may share written statements with the manufacturer for product quality and marketing.</em></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How to file a claim</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list">
<li><strong>Contact us</strong> by phone at <a href="tel:+16304424938">(630) 442-4938</a>, by email at <a href="mailto:info@j5rescue.com">info@j5rescue.com</a>, or through our <a href="/contact-us/">contact page</a>. Tell us what happened and which item you're claiming.</li>
<li><strong>Send your documents:</strong> proof of purchase, the police or incident report, and the photos or statement for your product.</li>
<li><strong>We review your claim</strong> and contact you about your replacement.</li>
</ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Questions about whether your gear or your situation qualifies? Call us before you file. We'd rather talk it through than have you guess.</p>
<!-- /wp:paragraph -->
EOF
chmod 644 /tmp/lsg-$ID.html
STAMP=$(date +%s)
$W "cd $SITE && wp post get $ID --field=post_content 2>/dev/null" > /tmp/lsg-prev-$ID-$STAMP.html
$W "cd $SITE && wp post meta list $ID --format=json 2>/dev/null" > /tmp/lsg-prev-meta-$ID-$STAMP.json
echo "backup: /tmp/lsg-prev-$ID-$STAMP.html and /tmp/lsg-prev-meta-$ID-$STAMP.json"
$W "cd $SITE && wp post update $ID /tmp/lsg-$ID.html --post_title='Life Safety Replacement Guarantee' 2>/dev/null"
for K in _elementor_data _elementor_edit_mode _elementor_page_settings _elementor_css _elementor_template_type _elementor_version _elementor_pro_version _elementor_page_assets _elementor_controls_usage; do
  $W "cd $SITE && wp post meta delete $ID $K 2>/dev/null" >/dev/null
done
$W "cd $SITE && wp post meta update $ID _wp_page_template default 2>/dev/null" >/dev/null
$W "cd $SITE && wp post meta update $ID rank_math_title 'Life Safety Replacement Guarantee | J5 Rescue Supply' 2>/dev/null"
$W "cd $SITE && wp post meta update $ID rank_math_description 'If body armor, a plate carrier, tourniquet, pressure dressing or chest seal you bought from J5 Rescue Supply is damaged saving a life, we replace it free. See who qualifies and how to file.' 2>/dev/null"
$W "cd $SITE && wp post meta update $ID rank_math_focus_keyword 'life safety replacement guarantee' 2>/dev/null"
echo -n "url: "; $W "cd $SITE && wp post url $ID 2>/dev/null"
$W "cd $SITE && wp cache flush 2>/dev/null"
