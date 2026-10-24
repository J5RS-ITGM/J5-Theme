#!/usr/bin/env bash
# HESCO plates guide: apply the new body, slug, title and Rank Math meta to post 11262.
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com
ID=11262
W="sudo -u j5rescue -i -- bash -c"

cat > /tmp/hesco-final-$ID.html <<'EOF'
<!-- wp:paragraph -->
<p>HESCO is one of the most popular hard armor brands in law enforcement, and for good reason. The plates are made in Aberdeen, Washington, the lineup covers everything from thin special threat inserts to full Level IV, and the threat data is published plate by plate instead of hidden behind a vague rating. The catch is that the lineup is big and the model numbers don't explain themselves.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>This guide covers every HESCO plate we currently carry, what each one actually stops, what it weighs, and how to pick the right one for the way you work. It's written by a dealer run by first responders. We sell these plates to agencies every week, and we would rather you buy the right one than the most expensive one.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Updated October 2026</strong> with HESCO's January 2026 datasheets, the newer 3612, 3802C, 4403MC, L211 and 4800 models, and the new NIJ 0101.07 rating levels.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>HESCO plates at a glance</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Weights below are for one Large Shooter Cut plate (10" x 12"), the most common size we sell. Every model is stand alone, meaning it does not need soft armor behind it to reach its rating.</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table>
<thead>
<tr><th>Model</th><th>Protection</th><th>Curve</th><th>Thickness</th><th>Weight (Large SH)</th><th>Best for</th></tr>
</thead>
<tbody>
<tr><td><a href="/hesco-u210-special-threat-plate-set/">U210</a></td><td>Special threat</td><td>Multi</td><td>0.56"</td><td>4.0 lb</td><td>Thinnest plate, all day wear</td></tr>
<tr><td><a href="/hesco-m210-special-threat-plate-set/">M210</a></td><td>Special threat</td><td>Multi</td><td>0.61"</td><td>4.9 lb</td><td>Adds 7.62x54R and M855A1, still thin</td></tr>
<tr><td><a href="/hesco-l211-special-threat-plate-set/">L211</a></td><td>Special threat</td><td>Single</td><td>0.69"</td><td>4.9 lb</td><td>Budget special threat, L210 successor</td></tr>
<tr><td><a href="/hesco-3802c-level-iii-plate-set/">3802C</a></td><td>NIJ Level III</td><td>Multi</td><td>0.95"</td><td>2.4 lb</td><td>Lightest plate in the lineup</td></tr>
<tr><td><a href="/hesco-armor-3612-ballistic-plate/">3612</a></td><td>Level III+ (NIJ III certified)</td><td>Multi</td><td>1.00"</td><td>3.8 lb</td><td>Light rifle plate that also stops M855</td></tr>
<tr><td><a href="/hesco-3612c-level-iii-plate-set/">3612C</a></td><td>Level III+</td><td>Multi</td><td colspan="2">See product page</td><td>3612 platform variant</td></tr>
<tr><td><a href="/hesco-3810-level-iii/">3810</a></td><td>Level III+ (NIJ .06 certified)</td><td>Multi</td><td>0.98"</td><td>3.9 lb</td><td>Proven patrol favorite</td></tr>
<tr><td><a href="/hesco-4403mc-level-iv-plate-set/">4403MC</a></td><td>NIJ Level IV</td><td>Multi</td><td>See product page</td><td>7.7 lb</td><td>Lowest cost Level IV, 4400 successor</td></tr>
<tr><td><a href="/hesco-4601-level-iv-plate-set/">4601</a></td><td>NIJ Level IV</td><td>Multi</td><td>1.18"</td><td>6.4 lb</td><td>Level IV with the deepest threat list</td></tr>
<tr><td><a href="/hesco-armor-4800-ballistic-plate/">4800</a></td><td>NIJ Level IV</td><td>Multi</td><td>1.04"</td><td>4.9 lb</td><td>Lightest Level IV HESCO makes</td></tr>
</tbody>
</table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>Want to shop them side by side? Every model above is on our <a href="/brand/hesco-armor/">HESCO Armor brand page</a>, and the full spec sheet and threat matrix are on each product page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>How HESCO rates its plates</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>HESCO plates carry one of three kinds of rating, and knowing which one you're looking at matters more than the number.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<!-- wp:list-item -->
<li><strong>NIJ certified (Level III or Level IV).</strong> The plate was tested by an NIJ accredited lab against the NIJ test round and is listed on the NIJ Compliant Products List. Level III is tested against 7.62x51mm M80 ball. Level IV is tested against .30-06 M2 armor piercing.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Level III+.</strong> This is a manufacturer label, not an NIJ level. On HESCO plates it means the plate is NIJ certified at Level III and HESCO has tested it against more rounds, most importantly 5.56 M193 and M855, which a basic Level III test does not include.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Special threat.</strong> The 200 series is not built to the NIJ M80 test at all. HESCO rates it against a specific list of rifle rounds (5.56, 7.62x39, and on the M210, 7.62x54R). Read the threat list. If your threat is on it, these are some of the thinnest rifle plates you can buy.</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Whichever plate you're looking at, the threat matrix on the product page tells you the minimum number of hits each size is expected to defeat for each round, under NIJ test methods. Smaller plates are usually rated for fewer hits than Medium, Large and XL.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://www.j5rescue.com/wp-content/uploads/2022/01/HESCO-Threat-Performance-Matrix.png" alt="Example HESCO threat performance matrix showing rounds and minimum hits per plate size"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3} -->
<h3>NIJ 0101.07: the new level names</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>NIJ published a new body armor standard (0101.07) and a companion specification (NIJ Standard 0123.00) in November 2023. The old levels are being renamed:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<!-- wp:list-item -->
<li>Level III becomes <strong>RF1</strong>, and the test now adds 7.62x39 mild steel core and 5.56 M193 to the M80.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>RF2</strong> is new. It is everything in RF1 plus 5.56 M855.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Level IV becomes <strong>RF3</strong> (.30-06 M2 AP).</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Plates certified under 0101.06 are still listed and still protect exactly as tested. You will see both naming systems for a while. When comparing, look at the rounds, not the label. For example, HESCO's own matrix shows the 3612 and 3810 defeating every round on the RF2 list, even though their certification is .06 Level III.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>HESCO 200 series: special threat plates</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The 200 series is for officers who want rifle protection they can wear all shift. These plates are thin enough to fit low profile carriers and are rated against the rounds you are most likely to face on the street.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-u210-special-threat-plate-set/">HESCO U210</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The lightest special threat plate HESCO makes: 0.56" thick and 4.0 lb in Large Shooter Cut. Multi curve, in Shooter and SAPI cuts, Small through XL. Rated to defeat 5.56 M193, 7.62x39 M67, 5.56 M855/SS109 and 7.62x39 M43 six times per plate, plus 5.56 M855A1 and 7.62x39 API.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-m210-special-threat-plate-set/">HESCO M210</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The M210 trades a little weight for a broader threat list. It adds 7.62x54R LPS and doubles the 7.62x39 API rating (two hits vs. one), though it is rated for fewer hits of 7.62x39 M43 than the U210 (three vs. six). It weighs 4.9 lb in Large Shooter Cut and is only 0.05" thicker. Multi curve, Shooter and SAPI cuts. If your area sees older Eastern Bloc rifles, this is the special threat plate to look at.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-l211-special-threat-plate-set/">HESCO L211</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The single curve, lower cost option in the 200 series and the successor to the L210. 0.69" thick, 4.9 lb in Large. Rated against M193, M67, M855, M43, 7.62x54R LPS, M855A1 and 7.62x39 API. Shooter Cut only, in Small and Large.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>About the L210:</strong> HESCO has discontinued the L210. We still list it while remaining stock lasts. For new orders we recommend the <a href="/hesco-l211-special-threat-plate-set/">L211</a> for a direct replacement, or the <a href="/hesco-u210-special-threat-plate-set/">U210</a> if you want a lighter multi curve plate.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>HESCO Level III and Level III+ plates</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This group is where most patrol officers land. These plates are much lighter than the 200 series but run about 1" thick, so check that your carrier has room.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-3802c-level-iii-plate-set/">HESCO 3802C</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The lightest plate in the lineup: 2.4 lb in Large Shooter Cut and 3.3 lb in SAPI XL. NIJ Level III certified against M80, and rated by HESCO against 5.56 M193, 7.62x39 M67, 7.62x63 JSP and 7.62x39 M43. It is <em>not</em> rated against 5.56 M855 green tip. If that matters to you, step up to the 3612 or 3810.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-armor-3612-ballistic-plate/">HESCO 3612</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Certified NIJ Level III, rated Level III+ by HESCO. 1.00" thick, 3.8 lb in Large Shooter Cut. It defeats M80, M193, M67, M855/SS109 and M43, with six hits on Medium and larger plates (three on Small). That combination of low weight and M855 coverage is why the 3612 is one of our best sellers for patrol.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-3612c-level-iii-plate-set/">HESCO 3612C</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>A variant on the 3612 platform. Check the 3612C spec sheet on the product page for its exact weights and threat matrix before choosing between the two.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-3810-level-iii/">HESCO 3810</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The 3810 has been a patrol favorite for years. NIJ .06 certified Level III+, 0.98" thick, 3.9 lb in Large Shooter Cut. Its threat list matches the 3612: M80, M193, M67, 7.62x63 JSP, M855 and M43. The two are close enough that availability and price usually decide it.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>HESCO Level IV plates</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Level IV is tested against armor piercing .30-06, the hardest test in the NIJ standard. The gap between the heaviest and lightest HESCO Level IV plate is almost three pounds per plate, so weight is the main thing you're choosing.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-4403mc-level-iv-plate-set/">HESCO 4403MC</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The most affordable way into a HESCO Level IV plate, and the replacement for the 4400. Multi curve, Shooter and SAPI cuts, Small through XL. It is also the heaviest at 7.7 lb for a Large Shooter Cut, so it suits vehicle and entry use better than all day wear.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>About the 4400:</strong> The 4400 is discontinued and we are selling through our final stock. The 4403MC is its replacement.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-4601-level-iv-plate-set/">HESCO 4601</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>NIJ 0101.06 Level IV certified, 1.18" thick, 6.4 lb in Large Shooter Cut. The 4601 has the deepest threat list we carry. On top of M2 AP, HESCO rates it against M193, M67, M80, JSP, M855, M43, 7.62x54R LPS, M855A1, M80A1, 7.62x39 API, 7.62x54R B-32 API and 7.62x51 M61 AP. It's a good choice for SWAT and anyone whose threat assessment includes armor piercing rifle ammunition.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3><a href="/hesco-armor-4800-ballistic-plate/">HESCO 4800</a></h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The lightest Level IV plate HESCO makes. 1.04" thick and 4.9 lb in Large Shooter Cut, the same weight as the M210 special threat plate. It carries the same long threat list as the 4601 and is NIJ 0101.06 Level IV certified. It costs the most, but if you want Level IV protection without Level IV weight, this is the plate.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Choosing a cut</h2>
<!-- /wp:heading -->

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://www.j5rescue.com/wp-content/uploads/2022/01/HESCO-Cut.jpg" alt="HESCO Shooter Cut and SAPI Cut plate shapes"/></figure>
<!-- /wp:image -->

<!-- wp:list -->
<ul>
<!-- wp:list-item -->
<li><strong>Shooter Cut (SH):</strong> Corners are cut back so a rifle stock seats against your shoulder and your arms move freely. Most current HESCO plates come in Shooter Cut, and it is what most officers choose.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>SAPI Cut (SA):</strong> Matches military SAPI sizing, with slightly more coverage at the top corners. A good choice if your carrier is cut for SAPI plates.</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Both cuts protect the same vital organs. Pick the cut your carrier is built for, then size the plate.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Single curve vs. multi curve</h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://www.j5rescue.com/wp-content/uploads/2022/01/HESCO-Armor.Single-Curve-768x1024.jpg" alt="HESCO single curve plate, side view"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://www.j5rescue.com/wp-content/uploads/2022/01/HESCO-Armor.Multi-Curve-768x1024.jpg" alt="HESCO multi curve plate, side view"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:paragraph -->
<p>Single curve plates bend side to side only. Multi curve plates also curve top to bottom, so they sit closer to the chest and wear more comfortably over a long shift. Almost every current HESCO plate is multi curve. The L211 is the main single curve option, which is part of why it costs less.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Sizing your plates</h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://www.j5rescue.com/wp-content/uploads/2021/11/HESCO-Sizing.png" alt="How to measure for a HESCO plate"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://www.j5rescue.com/wp-content/uploads/2021/11/HESCO-Sizing-Coverage.png" alt="Plate coverage area on the torso"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:paragraph -->
<p>Size the plate first, then the carrier. A plate is there to cover the heart, lungs and major vessels, not your whole torso. The top edge should sit about an inch below the sternal notch (the dip at the base of your throat), and the plate should be roughly as wide as the distance between your nipples.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Most adults fit a Medium SAPI (9.5" x 12.5") or a Large Shooter Cut (10" x 12"). Our <a href="/hesco-armor-sizing-guide/">HESCO Armor sizing guide</a> walks through measuring step by step. When you're ready for a carrier, see our <a href="/plate-carriers/">plate carriers</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Common questions about HESCO plates</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>Is ceramic armor fragile?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>No more than you'd expect from a life safety product. NIJ testing includes drop and conditioning tests before the plates are shot. Don't throw them around, inspect them regularly, and they will hold up to normal duty use.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Do HESCO plates expire?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The date on the label is the warranty period, not an expiration date for the protection. Most agencies replace plates on a fixed schedule anyway. Check the warranty on the product page and our <a href="/life-safety-replacement-guarantee/">Life Safety Replacement Guarantee</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Can a plate only take one hit?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>It depends on the plate, the size and the round. HESCO's threat matrix lists the minimum hits each size is rated to defeat. A Large 3612, for example, is rated for six hits of M855, while a Small is rated for three.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>What does "stand alone" mean?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The plate reaches its full rating by itself, without soft armor behind it. Every HESCO plate in this guide is stand alone. ICW (in conjunction with) plates need a specific soft armor panel to meet their rating.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Is body armor legal to buy?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>For most civilians in most states, yes, with some exceptions and restrictions. Read our <a href="/body-armor-laws-to-know-before-your-big-purchase/">body armor laws guide</a> before you order.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Buying HESCO plates for your agency</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We supply HESCO plates to law enforcement, corrections, fire and EMS, and private security. Agencies get net terms automatically. Need pricing on a fleet order or a formal quote for a bid? Use <a href="/request-a-quote/">Request a Quote</a>, and for issued gear tracking and reorders see our <a href="/client-portal/">Client Portal and QuarterMaster</a>. Individual officers and first responders can also apply for our <a href="/military-first-responder-discount/">military and first responder discount</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Not sure which plate fits your threat? Call us at <a href="tel:+16304424938">(630) 442-4938</a>. We'll talk it through and tell you if a cheaper plate will do the job.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Specifications are from HESCO Armor datasheets (rev. January 2026) and are the manufacturer's ratings. Always confirm ratings and sizing for your application. NIJ level information is from the <a href="https://nij.ojp.gov/topics/equipment-and-technology/specification-nij-ballistic-protection-levels-and-associated-test-threats-nij-standard-012300" target="_blank" rel="noopener">National Institute of Justice</a>.</em></p>
<!-- /wp:paragraph -->
EOF
chmod 644 /tmp/hesco-final-$ID.html

[ -s /tmp/hesco-old-$ID.html ] || $W "cd $SITE && wp post get $ID --field=post_content 2>/dev/null" > /tmp/hesco-old-$ID.html
echo "backup: /tmp/hesco-old-$ID.html ($(wc -c < /tmp/hesco-old-$ID.html) bytes)"

$W "cd $SITE && wp post update $ID /tmp/hesco-final-$ID.html --post_name=hesco-plates-guide --post_title='HESCO Plates Guide: Every Model Compared' 2>/dev/null"
$W "cd $SITE && wp post meta update $ID rank_math_title 'HESCO Plates Guide: Every Model Compared (2026) | J5 Rescue Supply' 2>/dev/null"
$W "cd $SITE && wp post meta update $ID rank_math_description 'Every HESCO plate we carry, what each one stops, weight, thickness, and how to pick between the 200 series, Level III+ and Level IV. Updated with the 2026 datasheets.' 2>/dev/null"
$W "cd $SITE && wp post meta update $ID rank_math_focus_keyword 'hesco plates' 2>/dev/null"
echo -n "old slug kept for 301: "; $W "cd $SITE && wp post meta get $ID _wp_old_slug 2>/dev/null"
echo -n "new url: "; $W "cd $SITE && wp post url $ID 2>/dev/null"
$W "cd $SITE && wp cache flush 2>/dev/null"
