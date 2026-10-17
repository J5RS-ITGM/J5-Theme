<?php
/**
 * Template Name: J5 Client Portal & QuarterMaster
 * Template Post Type: page
 *
 * Agency landing page for the J5 Client Portal and the QuarterMaster
 * uniform & equipment workflow. Content lives in this file (like the RFQ
 * page); styles in assets/css/j5-portal.css. Feature names, roles and order
 * stages mirror the DP client portal (frontend-client) so the page describes
 * what agencies actually get.
 *
 * Links are filterable: j5_portal_links.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$j5p = apply_filters( 'j5_portal_links', array(
	'signin'  => 'https://portal.j5rescue.com/',
	'setup'   => home_url( '/contact-us/' ),
	'quote'   => home_url( '/request-a-quote/' ),
	'phone'   => '(630) 442-4938',
	'tel'     => '+16304424938',
	'email'   => 'info@j5rescue.com',
) );

if ( ! function_exists( 'j5p_icon' ) ) {
	function j5p_icon( $name ) {
		$p = array(
			'roles'     => '<circle cx="9" cy="8" r="3.2"/><path d="M3 19c.6-3.2 3-5 6-5s5.4 1.8 6 5"/><path d="M16 5.5a3 3 0 0 1 0 5.6M18 14.4c1.6.7 2.7 2.3 3 4.6"/>',
			'history'   => '<path d="M4 12a8 8 0 1 0 2.4-5.7"/><path d="M4 4v4h4"/><path d="M12 8v4.5l3 1.8"/>',
			'docs'      => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h6"/>',
			'tracking'  => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
			'equipment' => '<path d="M4 7l8-4 8 4v10l-8 4-8-4z"/><path d="M4 7l8 4 8-4M12 11v10"/>',
			'issue'     => '<path d="M12 3l7 3v5c0 4.6-3 8.3-7 10-4-1.7-7-5.4-7-10V6z"/><path d="M9 12l2.2 2.2L15.5 10"/>',
			'armor'     => '<path d="M8 3h8l1 4 3 1v12H4V8l3-1z"/><path d="M9 3c0 2 1.3 3 3 3s3-1 3-3"/>',
			'ruler'     => '<path d="M3 16L16 3l5 5L8 21z"/><path d="M7 12l2 2M10 9l2 2M13 6l2 2"/>',
			'bolt'      => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
		);
		return '<svg class="j5qm-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}
}

add_action( 'wp_enqueue_scripts', function () {
	$f = get_stylesheet_directory() . '/assets/css/j5-portal.css';
	wp_enqueue_style( 'j5-portal', get_stylesheet_directory_uri() . '/assets/css/j5-portal.css', array(), file_exists( $f ) ? (string) filemtime( $f ) : null );
} );

get_header();
?>
<main id="primary" class="site-main j5qm">

	<!-- HERO -->
	<section class="j5qm-hero">
		<div class="j5qm-wrap j5qm-hero__grid">
			<div class="j5qm-hero__copy">
				<p class="j5qm-eyebrow">For agencies &amp; organizations &middot; Client Portal + QuarterMaster</p>
				<h1 class="j5qm-h1">Your agency's gear.<span>One portal.</span></h1>
				<p class="j5qm-lead">The J5 Client Portal gives your agency or organization one place to order, track and issue uniforms and equipment. QuarterMaster builds the workflow around the way your agency already runs, from sizing day to the person who signs for it.</p>
				<div class="j5qm-ctas">
					<a class="j5qm-btn j5qm-btn--red" href="<?php echo esc_url( $j5p['setup'] ); ?>">Set up your agency</a>
					<a class="j5qm-btn j5qm-btn--ghost" href="<?php echo esc_url( $j5p['signin'] ); ?>">Portal sign in &rarr;</a>
				</div>
				<ul class="j5qm-ticks">
					<li>No software to install</li>
					<li>Works on phones and tablets</li>
					<li>Built for public safety and private teams</li>
				</ul>
			</div>

			<div class="j5qm-mock" aria-hidden="true">
				<div class="j5qm-mock__bar"><i></i><i></i><i></i><span>portal.j5rescue.com &rsaquo; QuarterMaster</span></div>
				<div class="j5qm-mock__body">
					<div class="j5qm-mock__side">
						<b>Dashboard</b><b class="is-on">QuarterMaster</b><b>Orders</b><b>Tracker</b><b>Equipment</b><b>ArmorSync</b><b>Documents</b>
					</div>
					<div class="j5qm-mock__main">
						<div class="j5qm-mock__head"><span>Open tracker &middot; Patrol vests 2026</span><em>14 officers</em></div>
						<div class="j5qm-mock__row"><span>Ofc. Smith</span><span class="j5qm-pill is-ok">Delivered</span></div>
						<div class="j5qm-mock__row"><span>Ofc. Johnson</span><span class="j5qm-pill is-go">Shipped</span></div>
						<div class="j5qm-mock__row"><span>Sgt. Williams</span><span class="j5qm-pill is-go">In production</span></div>
						<div class="j5qm-mock__row"><span>Ofc. Brown</span><span class="j5qm-pill is-warn">Sizing</span></div>
						<div class="j5qm-mock__row"><span>Ofc. Davis</span><span class="j5qm-pill">Intent</span></div>
						<div class="j5qm-mock__meter"><span style="width:64%"></span></div>
						<div class="j5qm-mock__foot"><span>9 of 14 sized</span><span>Payee: Department</span></div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- WHO IT'S FOR -->
	<section class="j5qm-for" aria-label="Who it's for">
		<div class="j5qm-wrap j5qm-for__row">
			<span class="j5qm-for__label">Built for</span>
			<span class="j5qm-for__chip">Law Enforcement</span>
			<span class="j5qm-for__chip">Fire &amp; EMS</span>
			<span class="j5qm-for__chip">Corrections</span>
			<span class="j5qm-for__chip">Private Security</span>
			<span class="j5qm-for__chip">Businesses</span>
		</div>
	</section>

	<!-- GUARANTEE -->
	<section class="j5qm-promise">
		<div class="j5qm-wrap">
			<div class="j5qm-promise__head">
				<?php echo j5p_icon( 'bolt' ); ?>
				<div>
					<h2 class="j5qm-h2">Our fastest service. Guaranteed.</h2>
					<p>QuarterMaster agencies go to the front of the line on the three things that slow most departments down.</p>
				</div>
			</div>
			<div class="j5qm-promise__grid">
				<div class="j5qm-promise__item"><?php echo j5p_icon( 'armor' ); ?><b>Body armor</b><span>Your carrier configurations, placards and panels are already on file, so every order starts from your agency's spec instead of a blank form.</span></div>
				<div class="j5qm-promise__item"><?php echo j5p_icon( 'ruler' ); ?><b>Sizing</b><span>Send sizing forms to the whole roster. Officers size themselves or your quartermaster does it, and the results land straight in the order.</span></div>
				<div class="j5qm-promise__item"><?php echo j5p_icon( 'bolt' ); ?><b>Order processing</b><span>Approved orders come to us already sized, configured and assigned to an officer. Nothing to chase down, nothing to re-key.</span></div>
			</div>
		</div>
	</section>

	<!-- QUARTERMASTER WORKFLOW -->
	<section class="j5qm-sec">
		<div class="j5qm-wrap">
			<p class="j5qm-eyebrow">QuarterMaster</p>
			<h2 class="j5qm-h2">Uniform and equipment workflows, built around your agency.</h2>
			<p class="j5qm-sub">QuarterMaster connects your roster, your gear specs and your purchasing rules to our ordering. Every department runs differently, so we set it up to match yours: who orders, who approves and who pays.</p>

			<ol class="j5qm-flow">
				<li><span class="j5qm-flow__n">01</span><b>Set up your agency</b><span>We load your roster, carrier configurations and placards, and set who pays: the department, the officer, or a split.</span></li>
				<li><span class="j5qm-flow__n">02</span><b>Size your people</b><span>Open a tracker and send sizing forms. You see who's done and who's still outstanding, right on the page.</span></li>
				<li><span class="j5qm-flow__n">03</span><b>Order &amp; approve</b><span>Officer requests can route to your quartermaster for approval first, or go straight through. Your call.</span></li>
				<li><span class="j5qm-flow__n">04</span><b>Track every unit</b><span>Each officer moves through the same stages, from intent and sizing through production, shipping and delivery.</span></li>
				<li><span class="j5qm-flow__n">05</span><b>Issue &amp; keep it current</b><span>Armor gets assigned to the officer who wears it, with serials and warranty dates on record for the next replacement cycle.</span></li>
			</ol>

			<div class="j5qm-stages" aria-label="Order stages">
				<span>Intent</span><span>Sizing</span><span>Ordered</span><span>In production</span><span>Shipped</span><span>Delivered</span><span class="is-last">Issued</span>
			</div>
		</div>
	</section>

	<!-- PORTAL FEATURES -->
	<section class="j5qm-sec j5qm-sec--alt">
		<div class="j5qm-wrap">
			<p class="j5qm-eyebrow">Client Portal</p>
			<h2 class="j5qm-h2">Everything your department buys from us, in one place.</h2>
			<div class="j5qm-feat">
				<div class="j5qm-card"><?php echo j5p_icon( 'roles' ); ?><h3>Roles &amp; permissions</h3><p>Admins, quartermasters, purchasing and officers each see what they need and nothing they don't. Approvals are on or off per agency.</p></div>
				<div class="j5qm-card"><?php echo j5p_icon( 'history' ); ?><h3>Order history</h3><p>Every order your agency has placed, searchable, with line items and who it was for. Reordering is quick.</p></div>
				<div class="j5qm-card"><?php echo j5p_icon( 'docs' ); ?><h3>Documents</h3><p>Quotes, invoices, policies and the rest of your paperwork, kept in one place. No more digging through email.</p></div>
				<div class="j5qm-card"><?php echo j5p_icon( 'tracking' ); ?><h3>Order tracking</h3><p>Live status from the moment an order is placed, with partial shipments and tracking numbers as they happen.</p></div>
				<div class="j5qm-card"><?php echo j5p_icon( 'equipment' ); ?><h3>Equipment tracking</h3><p>Everything your agency holds, with lot, serial and expiration dates. Scan a barcode with a handheld scanner or a phone camera to pull it up.</p></div>
				<div class="j5qm-card"><?php echo j5p_icon( 'issue' ); ?><h3>Issuance</h3><p>Assign gear to the officer who carries it, reassign it, take it back. Expiring items are flagged at 90 and 30 days.</p></div>
			</div>
		</div>
	</section>

	<!-- ROLES -->
	<section class="j5qm-sec">
		<div class="j5qm-wrap">
			<p class="j5qm-eyebrow">Who uses it</p>
			<h2 class="j5qm-h2">One login for everyone in the chain.</h2>
			<div class="j5qm-roles">
				<div class="j5qm-role"><span class="j5qm-role__tag">Admin</span><p>Runs the account. Invites users, sets roles and approval rules, and sees every order and document.</p></div>
				<div class="j5qm-role"><span class="j5qm-role__tag">Quartermaster</span><p>Manages trackers, sizing, the roster, armor and equipment. Can approve orders when your agency turns that on.</p></div>
				<div class="j5qm-role"><span class="j5qm-role__tag">Purchasing</span><p>Places orders that go straight through, with order history, invoices and documents at hand.</p></div>
				<div class="j5qm-role"><span class="j5qm-role__tag">Officer</span><p>Sizes themselves, sees their own armor and gear, and requests what they need. Requests can require approval.</p></div>
			</div>
			<p class="j5qm-note">Titles match your world. Fire and EMS see Chief / Admin, Quartermaster / Logistics, General Purchasing and Line Personnel. Businesses and private security teams get Admin and Purchaser accounts.</p>
		</div>
	</section>

	<!-- CTA -->
	<section class="j5qm-cta">
		<div class="j5qm-wrap j5qm-cta__inner">
			<div>
				<h2 class="j5qm-h2">Bring your agency onto QuarterMaster.</h2>
				<p>Tell us how your department orders today. We'll set up the portal around it and get your people sized.</p>
			</div>
			<div class="j5qm-ctas">
				<a class="j5qm-btn j5qm-btn--red" href="<?php echo esc_url( $j5p['setup'] ); ?>">Set up your agency</a>
				<a class="j5qm-btn j5qm-btn--ghost" href="tel:<?php echo esc_attr( $j5p['tel'] ); ?>"><?php echo esc_html( $j5p['phone'] ); ?></a>
			</div>
			<p class="j5qm-cta__fine">Already set up? <a href="<?php echo esc_url( $j5p['signin'] ); ?>">Sign in to the portal</a> &middot; Need a one-off price? <a href="<?php echo esc_url( $j5p['quote'] ); ?>">Request a quote</a></p>
		</div>
	</section>

</main>
<?php
get_footer();
