<?php
/**
 * Template Name: J5 Why Choose J5 (Armor Dealer)
 * Template Post Type: page
 *
 * "Why choose J5 Rescue Supply as your armor dealer" page. Content lives in
 * this file (like template-j5-portal.php); styles in assets/css/j5-why.css.
 * Create / update the page with tools/why-j5-apply.sh.
 *
 * The figures (acquisition record, stock statuses, disposition email, slip,
 * agency batch sheet) are HTML illustrations with EXAMPLE data, not real
 * records. Each is marked "Example". Swap any figure for a real screenshot by
 * replacing its <figure> body with an <img>.
 *
 * FAQ: the same array renders the visible Q&A and the FAQPage JSON-LD, so the
 * schema always mirrors the page.
 *
 * Links are filterable: j5_why_links.
 *
 * @package j5-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$j5w = apply_filters( 'j5_why_links', array(
	'shop'      => home_url( '/body-armor/' ),
	'compare'   => function_exists( 'j5_armor_compare_url' ) && j5_armor_compare_url() ? j5_armor_compare_url( 'j5ac-plate' ) : home_url( '/armor-comparison/' ),
	'guarantee' => 'https://www.j5rescue.com/life-safety-replacement-guarantee/',
	'portal'    => 'https://portal.j5rescue.com/',
	'agency'    => home_url( '/client-portal/' ),
	'contact'   => home_url( '/contact-us/' ),
	'optout'    => home_url( '/armor-record-privacy/' ),
	'phone'     => '(630) 442-4938',
	'tel'       => '+16304424938',
) );

$j5w_faq = array(
	array( 'Are all the plates you sell NIJ certified?', 'No. Each product page says whether a plate is NIJ certified, NIJ compliant or tested to NIJ, matching the manufacturer\'s spec sheet. Only certified plates carry the NIJ Certified badge.' ),
	array( 'What does "Factory order" mean?', 'We don\'t have that size on our shelf, but we can order it from the manufacturer for you. The product page shows the approximate lead time, and factory orders typically come with a 5 to 10% discount.' ),
	array( 'Are your plate sets matched?', 'Yes. We pair plates with consecutive serial numbers and inspect each one before it\'s boxed.' ),
	array( 'How will I know which plates I got?', 'When your order is assigned, we email you the lot number and both serial numbers. A printed disposition slip comes in the box too.' ),
	array( 'Will you contact me if my plates are recalled?', 'Yes. We record which plates went to which buyer, so we can reach you directly if NIJ issues an advisory or safety notice on your model. You can opt out, but then we may not be able to reach you.' ),
	array( 'Do I need to show ID to buy body armor?', 'Only for items restricted to law enforcement and licensed security. Those orders are held until we approve your ID.' ),
	array( 'Does the Life Safety Guarantee cover plates my department bought?', 'No. It covers professionals who buy with their own money, and only for gear shipped directly from J5 or the manufacturer to the end user.' ),
	array( 'Can I compare your plates with brands you don\'t sell?', 'Yes. Our armor comparison tool includes many brands we don\'t carry, all from their own spec sheets.' ),
);

/**
 * A QR-looking square for the example sheet. Not a scannable code: a seeded
 * pattern with the three finder squares, so the illustration reads as a QR.
 */
if ( ! function_exists( 'j5w_fake_qr' ) ) {
	function j5w_fake_qr( $seed, $px = 64 ) {
		$n = 21; $s = crc32( (string) $seed ); $cells = '';
		$finder = function ( $x, $y ) use ( $n ) {
			foreach ( array( array( 0, 0 ), array( $n - 7, 0 ), array( 0, $n - 7 ) ) as $f ) {
				if ( $x >= $f[0] && $x < $f[0] + 8 && $y >= $f[1] && $y < $f[1] + 8 ) return true;
			}
			return false;
		};
		for ( $y = 0; $y < $n; $y++ ) {
			for ( $x = 0; $x < $n; $x++ ) {
				if ( $finder( $x, $y ) ) continue;
				$s = ( $s * 1103515245 + 12345 ) & 0x7fffffff;
				if ( ( $s >> 16 ) % 2 ) $cells .= 'M' . $x . ' ' . $y . 'h1v1h-1z';
			}
		}
		$fp = '';
		foreach ( array( array( 0, 0 ), array( $n - 7, 0 ), array( 0, $n - 7 ) ) as $f ) {
			list( $fx, $fy ) = $f;
			$fp .= 'M' . $fx . ' ' . $fy . 'h7v7h-7zM' . ( $fx + 1 ) . ' ' . ( $fy + 1 ) . 'v5h5v-5zM' . ( $fx + 2 ) . ' ' . ( $fy + 2 ) . 'h3v3h-3z';
		}
		return '<svg class="j5wy-qr" width="' . (int) $px . '" height="' . (int) $px . '" viewBox="-1 -1 23 23" aria-hidden="true"><rect x="-1" y="-1" width="23" height="23" fill="#fff"/><path fill="#111" fill-rule="evenodd" d="' . $fp . $cells . '"/></svg>';
	}
}

add_action( 'wp_enqueue_scripts', function () {
	$f = get_stylesheet_directory() . '/assets/css/j5-why.css';
	wp_enqueue_style( 'j5-why', get_stylesheet_directory_uri() . '/assets/css/j5-why.css', array(), file_exists( $f ) ? (string) filemtime( $f ) : null );
} );

add_action( 'wp_head', function () use ( $j5w_faq ) {
	$items = array();
	foreach ( $j5w_faq as $qa ) {
		$items[] = array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ) );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 40 );

get_header();
?>
<main id="primary" class="site-main j5wy">

	<!-- HERO -->
	<header class="j5wy-hero">
		<div class="j5wy-wrap">
			<p class="j5wy-eyebrow">Why J5 Rescue Supply</p>
			<h1 class="j5wy-h1">Why choose J5 as your <span>body armor dealer</span></h1>
			<p class="j5wy-lead">You can't test a plate before you wear it. You're trusting the maker's spec sheet, and you're trusting the dealer to tell you what that sheet really says, ship you the right plates, and stand behind them. Here's how we handle that second part.</p>
			<div class="j5wy-ctas">
				<a class="j5wy-btn j5wy-btn--red" href="<?php echo esc_url( $j5w['shop'] ); ?>">Shop body armor</a>
				<a class="j5wy-btn j5wy-btn--ghost" href="<?php echo esc_url( $j5w['compare'] ); ?>">Compare plates &rarr;</a>
			</div>
			<ul class="j5wy-points" aria-label="At a glance">
				<li><b>Authorized distributor</b><span>Bought direct from the manufacturer</span></li>
				<li><b>Serial-matched sets</b><span>Consecutive serials, inspected</span></li>
				<li><b>Every plate tracked</b><span>Recall notices reach you directly</span></li>
				<li><b>Spec sheet facts</b><span>No rounding up on certification</span></li>
			</ul>
		</div>
	</header>

	<div class="j5wy-wrap j5wy-body">

		<!-- DIRECT -->
		<section class="j5wy-sec" id="direct">
			<h2 class="j5wy-h2">Bought direct from the manufacturer</h2>
			<p>We're an authorized distributor for every brand of armor we sell. That means we buy straight from the manufacturer, not from another dealer or a middleman. Every plate we ship has one path: from the factory to our shelf to you. We know where it's been, and so do you.</p>
		</section>

		<!-- SPEC SHEET -->
		<section class="j5wy-sec" id="spec-sheet">
			<h2 class="j5wy-h2">We say exactly what the spec sheet says</h2>
			<p>Every spec on our armor pages comes from the manufacturer's own spec sheet: sizes, weights, curve, cut, thickness, and the rounds each plate is rated to stop. We don't copy specs from other retailers, and we don't fill gaps with guesses.</p>
			<p>That matters most for the word "certified." Three claims get mixed up all the time:</p>
			<div class="j5wy-claims">
				<div class="j5wy-claim is-cert"><b>NIJ certified</b><span>The model is on the NIJ Compliant Products List for that standard.</span></div>
				<div class="j5wy-claim"><b>NIJ compliant</b><span>The maker says it meets the standard. It is not on the NIJ list.</span></div>
				<div class="j5wy-claim"><b>Tested to NIJ</b><span>The maker tested it against the standard's rounds.</span></div>
			</div>
			<p>We print the one the sheet supports and nothing stronger. If a plate is compliant, our page says compliant. If a round isn't on the sheet, we say "Not listed," never "fails" and never "stops it."</p>
		</section>

		<!-- HANDLING -->
		<section class="j5wy-sec" id="handling">
			<h2 class="j5wy-h2">How we handle your armor, from our shelf to your door</h2>
			<div class="j5wy-split">
				<div>
					<p>When we order plates, they arrive in bulk, usually by the pallet. Every plate gets scanned into our system when it comes in. We record the manufacturer, model, cut, size, lot or batch number, serial number, manufacture date and the manufacturer's warranty period. All that goes into an acquisition record, so we know exactly where each plate came from.</p>
					<p>Next, we pair plates with consecutive serial numbers, inspect each one, and wrap them individually. Each pair goes into its shipping box with every empty space packed tight, so nothing shifts or gets damaged in transit. The box is labeled and stored on a shelf in climate-controlled storage until it ships to you.</p>
				</div>
				<figure class="j5wy-fig">
					<div class="j5wy-rec">
						<div class="j5wy-rec__top"><span>Acquisition record</span><em>Example</em></div>
						<dl class="j5wy-rec__grid">
							<div><dt>Manufacturer</dt><dd>HESCO</dd></div>
							<div><dt>Model</dt><dd>3810</dd></div>
							<div><dt>Cut</dt><dd>Shooters Cut</dd></div>
							<div><dt>Size</dt><dd>10 × 12</dd></div>
							<div><dt>Lot / batch</dt><dd class="mono">L24-0817</dd></div>
							<div><dt>Serial</dt><dd class="mono">38100412</dd></div>
							<div><dt>Mfg date</dt><dd>Aug 2024</dd></div>
							<div><dt>Mfr warranty</dt><dd>10 years</dd></div>
						</dl>
						<div class="j5wy-rec__pair"><span class="mono">38100412</span><i aria-hidden="true">+</i><span class="mono">38100413</span><b>Paired set</b></div>
					</div>
					<figcaption>Every plate is scanned in on arrival, then paired with the next consecutive serial.</figcaption>
				</figure>
			</div>
		</section>

		<!-- STATUSES -->
		<section class="j5wy-sec" id="stock-labels">
			<h2 class="j5wy-h2">What the stock labels on our site mean</h2>
			<p>When you shop for armor on our site, each size and cut shows one of three statuses:</p>
			<div class="j5wy-split j5wy-split--fig-left">
				<figure class="j5wy-fig">
					<div class="j5wy-pick">
						<div class="j5wy-pick__top"><span>Choose your size</span><em>Example</em></div>
						<div class="j5wy-pick__opt"><span>Medium</span><b class="st st--in"><i></i>In stock</b></div>
						<div class="j5wy-pick__opt is-on"><span>Large</span><b class="st st--prod"><i></i>In production</b><small>Est. ship date Jan 12, 2027</small></div>
						<div class="j5wy-pick__opt"><span>X-Large</span><b class="st st--fac"><i></i>Factory order</b><small>Lead time about 6 to 8 weeks</small></div>
						<div class="j5wy-pick__opt"><span>Large, prorated set</span><b class="st st--in"><i></i>In stock</b><small>Mfg Aug 2024, discounted</small></div>
					</div>
					<figcaption>Each size and cut shows its own status. Older sets show up as prorated.</figcaption>
				</figure>
				<ul class="j5wy-status">
					<li><b class="st st--in"><i></i>In Stock</b><p>The set is on our shelf right now. That count comes straight from the same system that tracks every plate we receive.</p></li>
					<li><b class="st st--fac"><i></i>Factory Order</b><p>We don't have that model, cut or size on hand, but we can order it from the manufacturer for you. Lead times vary by maker, and we work hard to keep them current. The product page shows the approximate wait. Factory orders often come with a discount, typically 5 to 10%.</p><span class="j5wy-ex"><em>Example</em>Lead time about 6 to 8 weeks</span></li>
					<li><b class="st st--prod"><i></i>In Production</b><p>These plates are on an open purchase order that the factory has confirmed. The arrival date comes from the manufacturer, and it's the date we expect to ship to you. If you don't want to wait out a full factory lead time, this is a good way to get in line early.</p><span class="j5wy-ex"><em>Example</em>Est. ship date Jan 12, 2027</span></li>
				</ul>
			</div>
		</section>

		<!-- PRORATED -->
		<section class="j5wy-sec" id="prorated">
			<h2 class="j5wy-h2">Older sets cost less</h2>
			<p>When you get to the last step of picking your plates, you may see a discounted set. We prorate armor that's more than a year past its manufacture date. The longer a set has been with us, the bigger the discount. These sets got the same care as every other set we stock. They've just been sitting on a shelf longer.</p>
			<div class="j5wy-split">
				<div>
					<p>Here's how picking one works. Once you choose your cut and size, a "Choose your set" step shows each set we have on the shelf, with its manufacture date and how many are left. Older sets show their discount right on the card.</p>
					<p>Want a specific pair? Pick it from the serial pair list. You'll see the last digits of both serials and the month they were made, so you know exactly which plates you're getting before you check out. The age discount comes off in your cart.</p>
				</div>
				<figure class="j5wy-fig">
					<div class="j5wy-sets">
						<div class="j5wy-pick__top"><span>Choose your set</span><em>Example</em></div>
						<div class="j5wy-sets__body">
							<div class="j5wy-set">
								<span class="j5wy-set__radio" aria-hidden="true"></span>
								<div><b>Newest set</b><small>Made Sep 2026 · 3 left</small></div>
							</div>
							<div class="j5wy-set is-on">
								<span class="j5wy-set__save">Save 14%</span>
								<span class="j5wy-set__radio" aria-hidden="true"></span>
								<div><b>Shelf set</b><small>Made Feb 2024 · 1 left</small></div>
							</div>
							<label class="j5wy-sets__lbl">Serial pair</label>
							<div class="j5wy-sets__select"><span>…7425 / …7426 · Feb 2024</span><i aria-hidden="true"></i></div>
							<p class="j5wy-sets__note">The Plate Age Discount comes off in your cart.</p>
						</div>
					</div>
					<figcaption>Pick the set, or the exact serial pair. Older sets show their discount up front.</figcaption>
				</figure>
			</div>
		</section>

		<!-- AFTER ORDER -->
		<section class="j5wy-sec" id="after-you-order">
			<h2 class="j5wy-h2">What happens after you order</h2>
			<p>If your armor is in stock, your plate details come to you fast. As soon as your order comes in, our system picks a set from inventory and assigns it to you. You'll get an email with the lot number and both serial numbers. That's us creating a disposition, which officially signs the armor over to you. You'll find a printed disposition slip inside the box too.</p>

			<ol class="j5wy-flow" aria-label="Order process">
				<li><span class="n">1</span><b>You order</b><span>Pick your plates and check out.</span></li>
				<li><span class="n">2</span><b>Set assigned</b><span>Our system picks a matched set from inventory.</span></li>
				<li><span class="n">3</span><b>Disposition email</b><span>Lot number and both serials, sent to you.</span></li>
				<li><span class="n">4</span><b>Shipped with slip</b><span>A printed disposition slip rides in the box.</span></li>
				<li><span class="n">5</span><b>Tracked for you</b><span>If a notice hits your model, we contact you.</span></li>
			</ol>

			<div class="j5wy-docs">
				<figure class="j5wy-fig">
					<div class="j5wy-mail">
						<div class="j5wy-mail__bar"><span>Inbox</span><em>Example</em></div>
						<div class="j5wy-mail__head">
							<b>Your plates are assigned: Order #10482</b>
							<span>J5 Rescue Supply &lt;orders@j5rescue.com&gt;</span>
						</div>
						<div class="j5wy-mail__body">
							<p>Hi Chris, your plate set has been assigned and signed over to you. Keep this email with your records.</p>
							<table class="j5wy-mini">
								<tbody>
									<tr><th scope="row">Model</th><td>HESCO 3810, Shooters Cut, Large</td></tr>
									<tr><th scope="row">Lot</th><td class="mono">L24-0817</td></tr>
									<tr><th scope="row">Front plate</th><td class="mono">38100412</td></tr>
									<tr><th scope="row">Back plate</th><td class="mono">38100413</td></tr>
									<tr><th scope="row">Mfg date</th><td>Aug 2024</td></tr>
								</tbody>
							</table>
							<p class="j5wy-mail__fine">If NIJ or the manufacturer issues a notice on these plates, we'll contact you here.</p>
						</div>
					</div>
					<figcaption>The disposition email, sent as soon as a set is assigned.</figcaption>
				</figure>

				<figure class="j5wy-fig">
					<div class="j5wy-slip">
						<div class="j5wy-slip__head">
							<div><b>J5 Rescue Supply</b><span>Armor disposition record</span></div>
							<em>Example</em>
						</div>
						<div class="j5wy-slip__meta">
							<span>Disposition <b class="mono">D-2026-04417</b></span>
							<span>Order <b class="mono">#10482</b></span>
							<span>Date <b>Nov 13, 2026</b></span>
						</div>
						<table class="j5wy-slip__t">
							<thead><tr><th scope="col">Plate</th><th scope="col">Serial</th><th scope="col">Lot</th><th scope="col">Mfg</th><th scope="col">Warranty to</th></tr></thead>
							<tbody>
								<tr><td data-label="Plate">Front</td><td data-label="Serial" class="mono">38100412</td><td data-label="Lot" class="mono">L24-0817</td><td data-label="Mfg">08/2024</td><td data-label="Warranty to">08/2034</td></tr>
								<tr><td data-label="Plate">Back</td><td data-label="Serial" class="mono">38100413</td><td data-label="Lot" class="mono">L24-0817</td><td data-label="Mfg">08/2024</td><td data-label="Warranty to">08/2034</td></tr>
							</tbody>
						</table>
						<div class="j5wy-slip__foot"><span>Model: HESCO 3810 · Shooters Cut · Multi Curve · Large</span><span>Inspected and packed by: J5</span></div>
					</div>
					<figcaption>The printed disposition slip packed with every set.</figcaption>
				</figure>
			</div>
		</section>

		<!-- TRACKING -->
		<section class="j5wy-sec j5wy-sec--callout" id="tracking">
			<h2 class="j5wy-h2">Why we track every plate</h2>
			<p>Whatever comes of the ShotStop Ballistics case, one thing is already clear: the people wearing that armor were left in the dark. NIJ pulled the company from its compliant list and told agencies to stop using its armor, and the story made the news. But officers and civilians who bought those plates through a dealer or reseller often never heard which models were affected, or that they should stop wearing them. Plenty are likely still wearing them today.</p>
			<p>That's the gap we aimed to close. We record which plates went to which person, so when NIJ issues an advisory or safety notice, or a manufacturer turns out to be selling armor that doesn't meet the standard, we can contact you directly within minutes. You don't have to catch it in the news, and you don't have to wonder whether your plates are the ones affected.</p>
		</section>

		<!-- OPT OUT -->
		<section class="j5wy-sec" id="opt-out">
			<h2 class="j5wy-h2">You can opt out</h2>
			<p>This tracking is a courtesy, and you can turn it off. We take privacy as seriously as anyone. <a href="<?php echo esc_url( $j5w['optout'] ); ?>">Fill out a short form</a> and accept the disclaimer: if you opt out, we won't be able to reach you during an NIJ advisory or product recall. We'll then remove your contact info from the plate's disposition record. Once we remove it, it's gone for good.</p>
		</section>

		<!-- GUARANTEE -->
		<section class="j5wy-sec" id="guarantee">
			<h2 class="j5wy-h2">Our Life Safety Replacement Guarantee</h2>
			<p>Life safety gear gets our <a href="<?php echo esc_url( $j5w['guarantee'] ); ?>">Life Safety Replacement Guarantee</a>. Products that qualify carry the guarantee badge on their page.</p>
			<p>It covers gear we know the history of. That means items shipped directly from J5 or from the manufacturer to you, the end user. Gear bought through a reseller is outside our chain of custody, so it isn't covered.</p>
			<p>If you're a professional (law enforcement, security, military, first responder) buying with your own money, you're covered. Purchases made by a department or agency are not covered. Read the <a href="<?php echo esc_url( $j5w['guarantee'] ); ?>">full terms</a> before you buy.</p>
		</section>

		<!-- BUYING FOR YOURSELF -->
		<section class="j5wy-sec" id="individuals">
			<h2 class="j5wy-h2">Buying for yourself</h2>
			<p>Most of our customers buy online for themselves, and checkout works like any other store. Some armor, shields and helmets are restricted to law enforcement and licensed security. For those, we verify your ID once and hold the order until it's approved.</p>
			<p>We ship armor to the lower 48 states, except Connecticut and New York.</p>
		</section>

		<!-- AGENCY -->
		<section class="j5wy-sec" id="agency-sales">
			<h2 class="j5wy-h2">Agency sales</h2>
			<p>Everything above works the same for agencies and businesses, just at a bigger scale.</p>
			<p>When you order for a team, we can disposition the whole order to your agency at once. You'll get a sheet listing every plate's record, with a QR code on each line so you can scan the details straight into your own system instead of typing them in.</p>

			<figure class="j5wy-fig j5wy-fig--wide">
				<div class="j5wy-sheet">
					<div class="j5wy-sheet__head">
						<div><b>Batch disposition</b><span>Example Police Department · PO 26-0193</span></div>
						<em>Example</em>
					</div>
					<table class="j5wy-sheet__t">
						<thead><tr><th scope="col">Scan</th><th scope="col">Model</th><th scope="col">Size</th><th scope="col">Serial</th><th scope="col">Lot</th><th scope="col">Mfg</th></tr></thead>
						<tbody>
						<?php
						$rows = array(
							array( 'HESCO 3810 SH MC', 'Large', '38100412', 'L24-0817', '08/2024' ),
							array( 'HESCO 3810 SH MC', 'Large', '38100413', 'L24-0817', '08/2024' ),
							array( 'HESCO 3810 SH MC', 'Medium', '38100288', 'L24-0817', '08/2024' ),
							array( 'HESCO 3810 SH MC', 'Medium', '38100289', 'L24-0817', '08/2024' ),
						);
						foreach ( $rows as $r ) : ?>
							<tr>
								<td data-label="Scan" class="qr"><?php echo j5w_fake_qr( $r[2], 52 ); ?></td>
								<td data-label="Model"><?php echo esc_html( $r[0] ); ?></td>
								<td data-label="Size"><?php echo esc_html( $r[1] ); ?></td>
								<td data-label="Serial" class="mono"><?php echo esc_html( $r[2] ); ?></td>
								<td data-label="Lot" class="mono"><?php echo esc_html( $r[3] ); ?></td>
								<td data-label="Mfg"><?php echo esc_html( $r[4] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<figcaption>A batch disposition sheet. Each line's QR code carries that plate's record. (Illustration; these codes don't scan.)</figcaption>
			</figure>

			<p>You also get access to our <a href="<?php echo esc_url( $j5w['portal'] ); ?>">Client Portal</a>. As soon as we disposition plates to you, every record shows up there. You can export them to a CSV file anytime.</p>
			<p>Think of us as your quartermaster. Through <a href="<?php echo esc_url( $j5w['agency'] ); ?>">QuarterMaster</a>, built into the portal, you can assign plates to individual officers or to units like squad cars, medic units or security staff. You always know which plates are where and who's wearing them. That takes much of the logistics off your plate, so your people can stay focused on the job.</p>
			<p>Agencies get payment terms automatically, with no credit application, and ACH or wire payments carry no fee.</p>
		</section>

		<!-- COMPARE -->
		<section class="j5wy-sec" id="compare">
			<h2 class="j5wy-h2">Compare before you buy, even brands we don't sell</h2>
			<p>Our <a href="<?php echo esc_url( $j5w['compare'] ); ?>">armor comparison tool</a> lines up plates side by side: weight, sizes, cut, NIJ standard, certification and special threats. It covers Hesco, HighCom, RMA, LTC, USI, Safariland and more, including many brands we don't carry.</p>
			<p>Every plate in the tool comes from its maker's spec sheet. Certification is checked against the NIJ Compliant Products List. If we don't have the sheet, the plate isn't in the tool.</p>
			<p>The tool also flags comparisons that aren't equal, such as different threat levels, different NIJ standards, or big gaps in size or weight. You can still compare them; we just tell you why the numbers don't line up. It's a resource for anyone buying armor, not a sales pitch. If another plate fits you better, we'd rather you know.</p>
		</section>

		<!-- FAQ -->
		<section class="j5wy-sec" id="questions">
			<h2 class="j5wy-h2">Common questions</h2>
			<div class="j5wy-faq">
				<?php foreach ( $j5w_faq as $qa ) : ?>
					<details class="j5wy-faq__item">
						<summary><h3><?php echo esc_html( $qa[0] ); ?></h3></summary>
						<p><?php echo esc_html( $qa[1] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</section>
	</div>

	<!-- CTA -->
	<section class="j5wy-cta">
		<div class="j5wy-wrap j5wy-cta__inner">
			<div>
				<h2 class="j5wy-h2">Ready to pick your plates?</h2>
				<p>Shop live stock, or let us factory order the size you need.</p>
			</div>
			<div class="j5wy-ctas">
				<a class="j5wy-btn j5wy-btn--red" href="<?php echo esc_url( $j5w['shop'] ); ?>">Shop body armor</a>
				<a class="j5wy-btn j5wy-btn--ghost" href="tel:<?php echo esc_attr( $j5w['tel'] ); ?>"><?php echo esc_html( $j5w['phone'] ); ?></a>
			</div>
		</div>
	</section>

</main>
<?php
get_footer();
