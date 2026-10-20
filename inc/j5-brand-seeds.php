<?php
/**
 * J5 Brand Seeds
 *
 * Starter content for brand pages (Products -> Brands -> (brand) -> "Brand
 * Page"). Keys are the brand SLUGS exactly as they exist in Products ->
 * Brands. Loaded by inc/j5-brands.php.
 *
 * Load with WP-CLI (see j5_brands_seed_all() in inc/j5-brands.php):
 *     wp eval 'j5_brands_seed_all();'          preview
 *     wp eval 'j5_brands_seed_all( true );'    apply (skips brands that already have content)
 *
 * Every tile URL below was checked against the live store on 2026-10-20.
 * Copy sticks to what the product titles state; exact ratings, weights and
 * certifications stay on the product pages.
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Shared agency quote band. */
function j5_brand_seed_agency( $title, $text ) {
	return array(
		'agency_on'    => 1,
		'agency_title' => $title,
		'agency_text'  => $text,
		'agency_btn'   => 'Request a quote',
		'agency_url'   => '/contact-us/',
	);
}

add_filter( 'j5rc_brand_seeds', function ( $seeds ) {

	/* ---------------------------------------------------------------------
	 * ARMOR & SHIELDS
	 * ------------------------------------------------------------------- */

	$seeds['hesco-armor'] = array_merge( array(
		'enabled'      => 1,
		'title'        => 'HESCO',
		'title_accent' => 'Armor',
		'eyebrow'      => 'Hard Armor Plates · Special Threat, Level III+ & Level IV',
		'chips'        => 'Special Threat, Level III+, Level IV, Plate Sets',
		'_description' => 'HESCO hard armor plate sets for patrol, tactical teams, and active threat response. We carry the <strong>L210, L211, M210 and U210</strong> special threat plates, the <strong>3612, 3612C, 3810 and 3802C</strong> Level III and III+ plates, and the <strong>4403MC, 4601 and 4800</strong> Level IV plates. New to plate ratings? Our <a href="/just-another-useful-guide-to-body-armor-hesco-plates/">guide to body armor and HESCO plates</a> explains the differences.',

		'tiles_label'  => 'Popular HESCO plates',
		'tiles_title'  => 'Shop by plate line',
		'tiles'        => array(
			array( 'url' => '/hesco-l210-special-threat-plate-set-2/', 'kicker' => '01 · Special Threat', 'title' => 'HESCO L210', 'text' => 'Our most popular HESCO plate set, available as QuickShip.', 'chips' => 'Special Threat, QuickShip', 'cta' => 'View the L210' ),
			array( 'url' => '/hesco-l211-special-threat-plate-set/', 'kicker' => '02 · Special Threat', 'title' => 'HESCO L211', 'text' => 'The current special threat plate in the L-series.', 'chips' => 'Special Threat', 'cta' => 'View the L211' ),
			array( 'url' => '/hesco-m210-special-threat-plate-set/', 'kicker' => '03 · Special Threat', 'title' => 'HESCO M210', 'text' => 'Special threat plate set in the 210 family.', 'chips' => 'Special Threat', 'cta' => 'View the M210' ),
			array( 'url' => '/hesco-4601-level-iv-plate-set/', 'kicker' => '04 · Level IV', 'title' => 'HESCO 4601', 'text' => 'Level IV plate set for armor-piercing rifle threats.', 'chips' => 'Level IV', 'cta' => 'View the 4601' ),
		),

		'cmp_label'    => 'Special Threat vs Level IV',
		'cmp_title'    => 'Which HESCO plate do you need?',
		'cmp_intro'    => 'HESCO builds plates for different threats. Match the plate to what you expect to face rather than defaulting to the heaviest option.',
		'cmp_col_a'    => 'Special Threat (L210, L211, M210, U210)',
		'cmp_col_b'    => 'Level IV (4403MC, 4601, 4800)',
		'cmp_rows'     => array(
			array( 'label' => 'Built for', 'a' => 'Common patrol rifle threats, tested against the specific rounds listed on each product page', 'b' => 'Armor-piercing rifle rounds under the NIJ Level IV rating' ),
			array( 'label' => 'Weight', 'a' => 'Lighter, easier to wear for a full shift', 'b' => 'Heavier, plan for longer-wear comfort' ),
			array( 'label' => 'Best for', 'a' => 'Patrol officers and daily-wear rifle protection', 'b' => 'Tactical teams and high-threat callouts' ),
			array( 'label' => 'Check', 'a' => 'The threat list on the product page', 'b' => 'Plate size and cut for your carrier' ),
		),

		'faq_title'    => 'HESCO plate questions',
		'faqs'         => array(
			array( 'q' => 'What is the difference between HESCO Special Threat and Level IV plates?', 'a' => 'Special Threat plates are tested against specific rifle rounds and are usually lighter. Level IV plates are rated to stop armor-piercing rifle rounds and weigh more. Each product page lists the exact threats.' ),
			array( 'q' => 'Where do Level III and III+ plates fit in?', 'a' => 'HESCO Level III and III+ plates, such as the 3612, 3810 and 3802C, cover common rifle threats at a lower weight than Level IV. Check the threat list on each product page.' ),
			array( 'q' => 'Are HESCO plates NIJ certified?', 'a' => 'Many HESCO plates carry NIJ certification. Certification status is listed on each product page, so check the model you are buying.' ),
			array( 'q' => 'Will HESCO plates fit my plate carrier?', 'a' => 'Match the plate size and cut to your carrier\'s plate pocket. Sizes and cuts are listed on each product page, and you can <a href="/contact-us/">contact us</a> if you are unsure.' ),
			array( 'q' => 'What replaced the HESCO L210?', 'a' => 'The <a href="/hesco-l211-special-threat-plate-set/">L211</a> is the current L-series special threat plate. Remaining L210 stock is listed on the <a href="/hesco-l210-special-threat-plate-set-2/">L210 page</a>.' ),
			array( 'q' => 'Do you sell HESCO plates to agencies?', 'a' => 'Yes. We accept agency purchase orders and quote multi-unit plate orders. <a href="/contact-us/">Contact us</a> for a quote.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'HESCO and body armor guides',
		'art_pinned'   => 'just-another-useful-guide-to-body-armor-hesco-plates',
		'art_tag'      => 'hesco',
		'art_count'    => 3,

		'related_title' => 'Shop related',
		'related_slugs' => array( 'body-armor', 'plate-carriers', 'shields', 'helmets' ),
	), j5_brand_seed_agency( 'Outfitting a department?', 'Purchase orders accepted and quotes on multi-unit HESCO plate orders.' ) );

	$seeds['highcom-armor'] = array_merge( array(
		'enabled'      => 1,
		'title'        => 'HighCom',
		'title_accent' => 'Armor',
		'eyebrow'      => 'Ballistic Shields · Hard Armor Plates · Helmets',
		'chips'        => 'Bellfire Shields, Multi-Curve Plates, Striker ACH',
		'_description' => 'HighCom ballistic shields, hard armor plates and helmets. The <strong>Bellfire</strong> shield family (B3, B3A, MTS and the lightweight LTS) in handgun and rifle rated versions, multi-curve plate sets including the <strong>3S9M, 4SAS7, 4S16M and 4S17M</strong>, and the <strong>Striker ACH</strong> helmet. Compare shield ratings on our <a href="/shields/">ballistic shields</a> page.',

		'tiles_label'  => 'Shop HighCom',
		'tiles_title'  => 'Shields and plates',
		'tiles'        => array(
			array( 'url' => '/highcom-bellfire-b3-ballistic-shield-rifle-rated/', 'kicker' => '01 · Rifle Rated', 'title' => 'Bellfire B3', 'text' => 'Rifle rated Bellfire shield.', 'chips' => 'Rifle Rated', 'cta' => 'View the B3' ),
			array( 'url' => '/highcom-bellfire-b3a-ballistic-shield-handgun-rated/', 'kicker' => '02 · Handgun Rated', 'title' => 'Bellfire B3A', 'text' => 'Handgun rated Bellfire, lighter for patrol carry.', 'chips' => 'Handgun Rated, Patrol', 'cta' => 'View the B3A' ),
			array( 'url' => '/highcom-bellfire-lts-lightweight-ballistic-shield-rifle-rated/', 'kicker' => '03 · Lightweight', 'title' => 'Bellfire LTS', 'text' => 'Lightweight Bellfire, in rifle and handgun rated versions.', 'chips' => 'Lightweight, Rifle Rated', 'cta' => 'View the LTS' ),
			array( 'url' => '/highcom-4sas7-multi-curve-level-iv-plate-set/', 'kicker' => '04 · Plates', 'title' => '4SAS7 Plate Set', 'text' => 'Multi-curve Level IV hard armor plate set.', 'chips' => 'Level IV, Multi-Curve', 'cta' => 'View the 4SAS7' ),
		),

		'faq_title'    => 'HighCom questions',
		'faqs'         => array(
			array( 'q' => 'What is the difference between the HighCom Bellfire B3 and B3A?', 'a' => 'The B3 is rifle rated and the B3A is handgun rated. The B3A is lighter; the B3 stops common rifle threats. Sizes and weights are on each product page.' ),
			array( 'q' => 'Which HighCom shield is the lightest?', 'a' => 'Handgun rated shields and the Bellfire LTS lightweight line are the lightest. Exact weights for each size are on the product pages.' ),
			array( 'q' => 'Does HighCom make body armor plates?', 'a' => 'Yes. We carry HighCom multi-curve plate sets in Level III+ (3S9M) and Level IV (4SAS7, 4S16M, 4S17M).' ),
			array( 'q' => 'Do you sell HighCom to agencies?', 'a' => 'Yes. We accept agency purchase orders and quote multi-unit shield and plate orders. <a href="/contact-us/">Contact us</a> for a quote.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'Shield and armor guides',
		'art_pinned'   => 'ballistic-shields-the-complete-buyers-guide',
		'art_tag'      => 'highcom',
		'art_count'    => 3,

		'related_title' => 'Shop related',
		'related_slugs' => array( 'shields', 'rifle-rated-shields', 'body-armor', 'helmets' ),
	), j5_brand_seed_agency( 'Outfitting a team?', 'Purchase orders accepted and quotes on multi-unit HighCom shield and plate orders.' ) );

	$seeds['united-shield-international'] = array_merge( array(
		'enabled'      => 1,
		'title'        => 'United Shield',
		'title_accent' => 'International',
		'eyebrow'      => 'Ballistic Shields · Training Shields · Helmets',
		'chips'        => 'Phantom EVO, Lightweight Level III+, Training Shields, Spec Ops Delta',
		'_description' => 'United Shield International (USI) ballistic shields and helmets. The <strong>Phantom EVO</strong> shield and its matching training shield, the rifle rated <strong>Lightweight Level III+</strong> shields, and the <strong>Spec Ops Delta Gen II</strong> ballistic helmet. Compare shield ratings on our <a href="/shields/">ballistic shields</a> page.',

		'tiles_label'  => 'Shop USI',
		'tiles_title'  => 'Shields and helmets',
		'tiles'        => array(
			array( 'url' => '/usi-phantom-evo-ballistic-shield/', 'kicker' => '01 · Ballistic Shield', 'title' => 'Phantom EVO Shield', 'text' => 'USI\'s Phantom EVO ballistic shield.', 'chips' => 'Shield', 'cta' => 'View the Phantom EVO' ),
			array( 'url' => '/usi-lightweight-level-iii-rrs-rifle-rated/', 'kicker' => '02 · Rifle Rated', 'title' => 'Lightweight Level III+', 'text' => 'Rifle rated lightweight shield.', 'chips' => 'Level III+, Rifle Rated', 'cta' => 'View the Level III+' ),
			array( 'url' => '/usi-phantom-evo-training-shield/', 'kicker' => '03 · Training', 'title' => 'Phantom EVO Training Shield', 'text' => 'Non-rated trainer so teams can practice without wearing out duty shields.', 'chips' => 'Training', 'cta' => 'View the trainer' ),
			array( 'url' => '/usi-spec-ops-delta-gen-ii/', 'kicker' => '04 · Helmet', 'title' => 'Spec Ops Delta Gen II', 'text' => 'USI ballistic helmet.', 'chips' => 'Ballistic Helmet', 'cta' => 'View the Delta' ),
		),

		'faq_title'    => 'United Shield International questions',
		'faqs'         => array(
			array( 'q' => 'What is the difference between handgun rated and rifle rated shields?', 'a' => 'Handgun rated (Level IIIA) shields are lighter and built for handgun threats. Rifle rated shields stop common rifle rounds and weigh more. See our <a href="/shields/">ballistic shields</a> page for a side-by-side comparison.' ),
			array( 'q' => 'Do you carry USI training shields?', 'a' => 'Yes. The Phantom EVO training shield matches the duty shield, so teams can train without wearing out rated equipment.' ),
			array( 'q' => 'Do you sell USI products to agencies?', 'a' => 'Yes. We accept agency purchase orders and quote multi-unit orders. <a href="/contact-us/">Contact us</a> for a quote.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'United Shield articles',
		'art_pinned'   => 'united-shield-international-the-modern-officers-armor-carrier,ballistic-shields-the-complete-buyers-guide',
		'art_tag'      => 'united-shield',
		'art_count'    => 3,

		'related_title' => 'Shop related',
		'related_slugs' => array( 'shields', 'rifle-rated-shields', 'shield-accessories', 'helmets' ),
	), j5_brand_seed_agency( 'Outfitting a team?', 'Purchase orders accepted and quotes on multi-unit USI shield orders.' ) );

	$seeds['ops-core'] = array(
		'enabled'      => 1,
		'title'        => 'Ops-Core',
		'title_accent' => '',
		'eyebrow'      => 'Helmets',
		'chips'        => 'FAST Bump, High Cut',
		'_description' => 'Ops-Core helmet systems, including the <strong>FAST Bump High Cut</strong> helmet for training, rescue and non-ballistic missions.',
		'faq_title'    => 'Ops-Core questions',
		'faqs'         => array(
			array( 'q' => 'Is the Ops-Core FAST Bump helmet bulletproof?', 'a' => 'No. A bump helmet protects against impacts, not gunfire. For ballistic protection, see our <a href="/helmets/">helmets</a>.' ),
		),
		'related_title' => 'Shop related',
		'related_slugs' => array( 'helmets', 'body-armor' ),
	);

	/* ---------------------------------------------------------------------
	 * LESS LETHAL
	 * ------------------------------------------------------------------- */

	$seeds['byrna'] = array_merge( array(
		'enabled'      => 1,
		'title'        => 'Byrna',
		'title_accent' => '',
		'eyebrow'      => 'Less Lethal · .68 Cal Launchers',
		'chips'        => 'CL, MLR, Max Projectiles, CO2, Holsters',
		'_description' => 'Byrna less lethal launchers, projectiles and accessories. The <strong>Byrna CL</strong> and <strong>MLR</strong> launchers in semi and fully automatic versions, <strong>Byrna Max</strong> .68 cal pepper projectiles, 8 gram CO2 cartridges, and Level II holsters.',

		'tiles_label'  => 'Shop Byrna',
		'tiles_title'  => 'Launchers and supplies',
		'tiles'        => array(
			array( 'url' => '/byrna-cl-universal-kit/', 'kicker' => '01 · Launcher', 'title' => 'Byrna CL Universal Kit', 'text' => 'Byrna CL launcher kit.', 'chips' => 'Launcher, Kit', 'cta' => 'View the CL' ),
			array( 'url' => '/byrna-mlr-less-lethal-launcher-semi-automatic/', 'kicker' => '02 · Launcher', 'title' => 'Byrna MLR', 'text' => 'Rifle style launcher, semi and fully automatic versions.', 'chips' => 'Semi Auto, Full Auto', 'cta' => 'View the MLR' ),
			array( 'url' => '/byrna-max-projectile-less-lethal-chemical-agent/', 'kicker' => '03 · Projectiles', 'title' => 'Byrna Max Projectiles', 'text' => '.68 cal pepper projectiles.', 'chips' => '.68 Cal, Pepper', 'cta' => 'View projectiles' ),
			array( 'url' => '/byrna-8-gram-co2-cartridge-oiler-cartridge-sd-ep-hd/', 'kicker' => '04 · CO2', 'title' => '8 Gram CO2 Cartridges', 'text' => 'CO2 and oiler cartridges for the SD, EP and HD.', 'chips' => 'CO2, Reloads', 'cta' => 'View CO2' ),
		),

		'cmp_label'    => 'Byrna vs Pavashot',
		'cmp_title'    => 'Byrna or Pavashot?',
		'cmp_intro'    => 'We carry both. Each launches .68 cal less lethal projectiles; the platforms differ.',
		'cmp_col_a'    => 'Byrna',
		'cmp_col_b'    => 'Pavashot',
		'cmp_rows'     => array(
			array( 'label' => 'Launchers', 'a' => 'CL handheld and MLR rifle style', 'b' => 'EMC 100 and EMC 200' ),
			array( 'label' => 'Shop', 'a' => '<a href="/brand/byrna/">Byrna &rarr;</a>', 'b' => '<a href="/brand/pavashot/">Pavashot &rarr;</a>' ),
		),

		'faq_title'    => 'Byrna questions',
		'faqs'         => array(
			array( 'q' => 'What does a Byrna launcher fire?', 'a' => 'Byrna launchers fire .68 caliber projectiles, including Byrna Max pepper projectiles, using CO2 as the propellant.' ),
			array( 'q' => 'Which CO2 cartridge does my Byrna use?', 'a' => 'The 8 gram cartridges we stock fit the Byrna SD, EP and HD. Check the product page for the launcher you own.' ),
			array( 'q' => 'Is Byrna legal where I live?', 'a' => 'Laws on less lethal launchers and pepper projectiles vary by state and city. Check your local laws before ordering. Checkout will tell you if we cannot ship a product to your address.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'Less lethal guides',
		'art_tag'      => 'byrna',
		'art_count'    => 3,

		'related_title' => 'Shop related',
		'related_slugs' => array( 'less-lethal' ),
	), j5_brand_seed_agency( 'Equipping a department?', 'Purchase orders accepted and quotes on multi-unit Byrna orders.' ) );

	$seeds['pavashot'] = array(
		'enabled'      => 1,
		'title'        => 'Pavashot',
		'title_accent' => '',
		'eyebrow'      => 'Less Lethal · .68 Cal Launchers',
		'chips'        => 'EMC 100, EMC 200, PAVA',
		'_description' => 'Pavashot less lethal launchers. The <strong>EMC 100</strong> and <strong>EMC 200</strong> fire .68 caliber projectiles to stop a threat at a distance without a firearm.',

		'tiles_label'  => 'Shop Pavashot',
		'tiles_title'  => 'Launchers',
		'tiles'        => array(
			array( 'url' => '/pavashot-emc-100-less-lethal-launcher/', 'kicker' => '01 · Launcher', 'title' => 'Pavashot EMC 100', 'text' => '.68 cal less lethal launcher.', 'chips' => '.68 Cal', 'cta' => 'View the EMC 100' ),
			array( 'url' => '/pavashot-emc-200-less-lethal-launcher/', 'kicker' => '02 · Launcher', 'title' => 'Pavashot EMC 200', 'text' => '.68 cal less lethal launcher.', 'chips' => '.68 Cal', 'cta' => 'View the EMC 200' ),
		),

		'cmp_label'    => 'Pavashot vs Byrna',
		'cmp_title'    => 'Pavashot or Byrna?',
		'cmp_intro'    => 'We carry both. Each launches .68 cal less lethal projectiles; the platforms differ.',
		'cmp_col_a'    => 'Pavashot',
		'cmp_col_b'    => 'Byrna',
		'cmp_rows'     => array(
			array( 'label' => 'Launchers', 'a' => 'EMC 100 and EMC 200', 'b' => 'CL handheld and MLR rifle style' ),
			array( 'label' => 'Shop', 'a' => '<a href="/brand/pavashot/">Pavashot &rarr;</a>', 'b' => '<a href="/brand/byrna/">Byrna &rarr;</a>' ),
		),

		'faq_title'    => 'Pavashot questions',
		'faqs'         => array(
			array( 'q' => 'What is PAVA?', 'a' => 'PAVA (pelargonic acid vanillylamide) is a synthetic form of capsaicin, the compound that makes pepper spray work. It causes intense eye and skin irritation that stops a threat without lasting injury in most cases.' ),
			array( 'q' => 'What is the difference between the EMC 100 and EMC 200?', 'a' => 'Both are .68 cal Pavashot launchers. Compare features and capacity on the <a href="/pavashot-emc-100-less-lethal-launcher/">EMC 100</a> and <a href="/pavashot-emc-200-less-lethal-launcher/">EMC 200</a> product pages.' ),
			array( 'q' => 'Is Pavashot legal where I live?', 'a' => 'Laws on pepper-based devices vary by state and city. Check your local laws before ordering. Checkout will tell you if we cannot ship a product to your address.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'Less lethal guides',
		'art_tag'      => 'pavashot',
		'art_count'    => 3,

		'agency_on'    => 0,

		'related_title' => 'Shop related',
		'related_slugs' => array( 'less-lethal' ),
	);

	/* ---------------------------------------------------------------------
	 * LIGHTS & OPTICS
	 * ------------------------------------------------------------------- */

	$seeds['surefire'] = array_merge( array(
		'enabled'      => 1,
		'title'        => 'SureFire',
		'title_accent' => '',
		'eyebrow'      => 'Weapon Lights · Switches · Batteries',
		'chips'        => 'X300, Scout Light Pro, SR07 Switches, LED Heads, CR123A',
		'_description' => 'SureFire weapon lights and parts. The <strong>X300 Ultra</strong> and <strong>X300 Turbo</strong> pistol lights, the <strong>M640V Scout Light Pro</strong> with IR output, <strong>SR07</strong> and compact remote switches, KM1 and KM2 LED upgrade heads, rear caps, and CR123A and rechargeable batteries.',

		'tiles_label'  => 'Shop SureFire',
		'tiles_title'  => 'Lights and switches',
		'tiles'        => array(
			array( 'url' => '/surefire-x300-ultra/', 'kicker' => '01 · Pistol Light', 'title' => 'X300 Ultra', 'text' => 'Broad, bright beam for room-clearing and duty carry.', 'chips' => 'Pistol, Rail Mount', 'cta' => 'View the X300 Ultra' ),
			array( 'url' => '/surefire-x300-turbo-weapon-light/', 'kicker' => '02 · Pistol Light', 'title' => 'X300 Turbo', 'text' => 'Tight, long-reaching beam for distance.', 'chips' => 'Pistol, Long Throw', 'cta' => 'View the X300 Turbo' ),
			array( 'url' => '/surefire-m640v-scout-light-pro/', 'kicker' => '03 · Rifle Light', 'title' => 'M640V Scout Light Pro', 'text' => 'Scout Light Pro with IR output.', 'chips' => 'Rifle, IR', 'cta' => 'View the M640V' ),
			array( 'url' => '/surefire-sr07-remote-dual-switch-for-weapon-lights/', 'kicker' => '04 · Switch', 'title' => 'SR07 Remote Switch', 'text' => 'Remote dual switch for SureFire weapon lights.', 'chips' => 'Switch, Remote', 'cta' => 'View the SR07' ),
		),

		'cmp_label'    => 'X300 Ultra vs X300 Turbo',
		'cmp_title'    => 'Which X300?',
		'cmp_col_a'    => 'X300 Ultra',
		'cmp_col_b'    => 'X300 Turbo',
		'cmp_rows'     => array(
			array( 'label' => 'Beam', 'a' => 'Broad beam that lights up a whole room', 'b' => 'Focused beam that reaches farther' ),
			array( 'label' => 'Best for', 'a' => 'Indoor and close-range use', 'b' => 'Outdoor and longer-distance use' ),
			array( 'label' => 'Shop', 'a' => '<a href="/surefire-x300-ultra/">X300 Ultra &rarr;</a>', 'b' => '<a href="/surefire-x300-turbo-weapon-light/">X300 Turbo &rarr;</a>' ),
		),

		'faq_title'    => 'SureFire questions',
		'faqs'         => array(
			array( 'q' => 'What is the difference between the SureFire X300 Ultra and X300 Turbo?', 'a' => 'The Ultra puts out a broad beam for indoor and close-range use. The Turbo focuses its output into a tight beam that reaches farther. Both mount to pistol rails.' ),
			array( 'q' => 'Can I use rechargeable batteries in my SureFire light?', 'a' => 'Many SureFire lights accept the SFLFP123 rechargeable battery. Check compatibility on the <a href="/surefire-sflfp123-rechargeable-lfp123a-batteries/">SFLFP123 page</a> before switching from CR123A lithium cells.' ),
			array( 'q' => 'Which switch fits my Scout Light?', 'a' => 'Tail caps and remote switches are model-specific. Each switch and rear cap page lists the lights it fits, or <a href="/contact-us/">ask us</a>.' ),
			array( 'q' => 'Do you sell SureFire to agencies?', 'a' => 'Yes. We accept agency purchase orders and quote multi-unit weapon light orders. <a href="/contact-us/">Contact us</a> for a quote.' ),
		),

		'art_label'    => 'From the J5 blog',
		'art_title'    => 'Weapon light articles',
		'art_tag'      => 'surefire',
		'art_count'    => 3,

		'related_title' => 'Shop related',
		'related_slugs' => array( 'lights', 'optics-weapon-accessories', 'firearms-parts' ),
	), j5_brand_seed_agency( 'Equipping a department?', 'Purchase orders accepted and quotes on multi-unit SureFire light orders.' ) );

	$seeds['eotech'] = array_merge( array(
		'enabled'      => 1,
		'title'        => 'EOTECH',
		'title_accent' => '',
		'eyebrow'      => 'Holographic Sights · Magnifiers',
		'chips'        => 'EXPS3, G43, G45, QD Mounts',
		'_description' => 'EOTECH holographic weapon sights and magnifiers. The <strong>EXPS3</strong> holographic sight with QD mount, the <strong>G43</strong> compact 3X magnifier, and the <strong>G45</strong> 5X magnifier with switch-to-side QD mount option.',

		'tiles_label'  => 'Shop EOTECH',
		'tiles_title'  => 'Sights and magnifiers',
		'tiles'        => array(
			array( 'url' => '/eotech-exps3-holographic-weapon-sight-with-qd-mount/', 'kicker' => '01 · Holographic Sight', 'title' => 'EXPS3', 'text' => 'Holographic weapon sight with QD mount.', 'chips' => 'Holographic, QD Mount', 'cta' => 'View the EXPS3' ),
			array( 'url' => '/eotech-g43-compact-3x-magnifier/', 'kicker' => '02 · Magnifier', 'title' => 'G43 3X Magnifier', 'text' => 'Compact 3X magnifier.', 'chips' => '3X, Compact', 'cta' => 'View the G43' ),
			array( 'url' => '/eotech-g45-5x-magnifier-switch-to-side-qd-mount-option/', 'kicker' => '03 · Magnifier', 'title' => 'G45 5X Magnifier', 'text' => '5X magnifier with switch-to-side QD mount option.', 'chips' => '5X, Switch-to-Side', 'cta' => 'View the G45' ),
		),

		'faq_title'    => 'EOTECH questions',
		'faqs'         => array(
			array( 'q' => 'What is a holographic sight?', 'a' => 'A holographic sight projects the reticle as a hologram inside the window rather than reflecting an LED dot. EOTECH is the best-known maker of holographic weapon sights.' ),
			array( 'q' => 'Do I need a magnifier?', 'a' => 'A magnifier sits behind the sight and adds magnification for longer shots. Switch-to-side mounts let you flip it out of the way for close range.' ),
			array( 'q' => 'Do you sell EOTECH to agencies?', 'a' => 'Yes. We accept agency purchase orders and quote multi-unit optic orders. <a href="/contact-us/">Contact us</a> for a quote.' ),
		),

		'related_title' => 'Shop related',
		'related_slugs' => array( 'optics-weapon-accessories', 'lights' ),
	), j5_brand_seed_agency( 'Equipping a department?', 'Purchase orders accepted and quotes on multi-unit EOTECH orders.' ) );

	$seeds['leupold'] = array(
		'enabled'      => 1,
		'title'        => 'Leupold',
		'title_accent' => '',
		'eyebrow'      => 'Red Dot Sights',
		'chips'        => 'DeltaPoint Pro',
		'_description' => 'Leupold optics, including the <strong><a href="/deltapoint-pro/">DeltaPoint Pro</a></strong> reflex red dot sight for pistols and rifles.',
		'related_title' => 'Shop related',
		'related_slugs' => array( 'optics-weapon-accessories' ),
	);

	$seeds['scalarworks'] = array(
		'enabled'      => 1,
		'title'        => 'Scalarworks',
		'title_accent' => '',
		'eyebrow'      => 'Optic Mounts',
		'chips'        => 'Optic Mounts',
		'_description' => 'Scalarworks optic mounts.',
		'related_title' => 'Shop related',
		'related_slugs' => array( 'optics-weapon-accessories' ),
	);

	$seeds['magpul'] = array(
		'enabled'      => 1,
		'title'        => 'Magpul',
		'title_accent' => '',
		'eyebrow'      => 'Slings · Sling Adapters · Gloves',
		'chips'        => 'MS1, MS1 Padded, MS1 Lite, QD Adapters, Breach Gloves',
		'_description' => 'Magpul slings and gear. The <strong>MS1</strong> two-point sling in standard, padded and Lite versions, MS1 adapters for single-point and QD use, and <strong>Breach Gloves 2.0</strong>.',

		'tiles_label'  => 'Shop Magpul',
		'tiles_title'  => 'Slings and gloves',
		'tiles'        => array(
			array( 'url' => '/magpul-ms1-rifle-sling/', 'kicker' => '01 · Sling', 'title' => 'MS1 Rifle Sling', 'text' => 'Magpul\'s two-point rifle sling.', 'chips' => 'Two-Point', 'cta' => 'View the MS1' ),
			array( 'url' => '/magpul-mag545-ms1-padded-sling/', 'kicker' => '02 · Sling', 'title' => 'MS1 Padded Sling', 'text' => 'MS1 with a padded shoulder section.', 'chips' => 'Two-Point, Padded', 'cta' => 'View the MS1 Padded' ),
			array( 'url' => '/magpul-mag1312-ms1-lite-rifle-sling/', 'kicker' => '03 · Sling', 'title' => 'MS1 Lite Sling', 'text' => 'Lighter version of the MS1.', 'chips' => 'Two-Point, Lightweight', 'cta' => 'View the MS1 Lite' ),
			array( 'url' => '/magpul-breach-gloves-2-0/', 'kicker' => '04 · Gloves', 'title' => 'Breach Gloves 2.0', 'text' => 'Magpul duty gloves.', 'chips' => 'Gloves', 'cta' => 'View the gloves' ),
		),

		'faq_title'    => 'Magpul questions',
		'faqs'         => array(
			array( 'q' => 'Can the Magpul MS1 be used as a single-point sling?', 'a' => 'Yes, with an adapter. The <a href="/magpul-mag519-ms1-single-point-dual-qd-sling-adapter/">MS1 MS4 QD adapter</a> and <a href="/magpul-ms1-ms3-adapter/">MS1 MS3 adapter</a> convert the MS1 for other setups.' ),
		),

		'related_title' => 'Shop related',
		'related_slugs' => array( 'optics-weapon-accessories', 'firearms-parts', 'nylon-gear' ),
	);

	/* ---------------------------------------------------------------------
	 * MEDICAL
	 * ------------------------------------------------------------------- */

	$seeds['north-american-rescue'] = array_merge( array(
		'enabled'      => 1,
		'title'        => 'North American',
		'title_accent' => 'Rescue',
		'eyebrow'      => 'Tourniquets · Chest Seals · Hemorrhage Control',
		'chips'        => 'C-A-T Tourniquet, HyFin Chest Seals, Gauze, ETD, Kits',
		'_description' => 'North American Rescue (NAR) trauma and hemorrhage control gear. The <strong>Combat Application Tourniquet (C-A-T)</strong>, <strong>HyFin</strong> vented and non-vented chest seals, compressed and wound packing gauze, emergency trauma dressings, the ARS decompression needle, hypothermia prevention kits, and bleeding control kits. Building kits for a department? Try our <a href="/bleeding-control-kit-builder/">bleeding control kit builder</a>.',

		'tiles_label'  => 'Shop NAR',
		'tiles_title'  => 'Trauma essentials',
		'tiles'        => array(
			array( 'url' => '/combat-application-tourniquet/', 'kicker' => '01 · Tourniquet', 'title' => 'C-A-T Tourniquet', 'text' => 'NAR Combat Application Tourniquet.', 'chips' => 'Tourniquet, CoTCCC', 'cta' => 'View the C-A-T' ),
			array( 'url' => '/hyfin-vent-chest-seal-twin-pack/', 'kicker' => '02 · Chest Seal', 'title' => 'HyFin Vent Chest Seal', 'text' => 'Vented chest seal, twin pack.', 'chips' => 'Vented, Twin Pack', 'cta' => 'View HyFin Vent' ),
			array( 'url' => '/nar-compressed-gauze/', 'kicker' => '03 · Gauze', 'title' => 'Compressed Gauze', 'text' => 'Compressed gauze for wound packing.', 'chips' => 'Gauze', 'cta' => 'View the gauze' ),
			array( 'url' => '/nar-bleeding-control-kit-twin-pack/', 'kicker' => '04 · Kit', 'title' => 'Bleeding Control Kit', 'text' => 'NAR bleeding control kit, twin pack.', 'chips' => 'Kit, Twin Pack', 'cta' => 'View the kit' ),
		),

		'faq_title'    => 'North American Rescue questions',
		'faqs'         => array(
			array( 'q' => 'Is the C-A-T tourniquet CoTCCC recommended?', 'a' => 'Yes. The Combat Application Tourniquet is on the Committee on Tactical Combat Casualty Care (CoTCCC) recommended device list.' ),
			array( 'q' => 'Vented or non-vented chest seal?', 'a' => 'Vented chest seals, like the <a href="/hyfin-vent-chest-seal-twin-pack/">HyFin Vent</a>, let air escape from the chest while blocking air from entering. Follow your protocols and training when choosing.' ),
			array( 'q' => 'Do you sell NAR to agencies?', 'a' => 'Yes. We accept agency purchase orders and quote multi-unit medical orders and custom kits. <a href="/contact-us/">Contact us</a> for a quote.' ),
		),

		'related_title' => 'Shop related',
		'related_slugs' => array( 'medical' ),
	), j5_brand_seed_agency( 'Stocking a department?', 'Purchase orders accepted and quotes on multi-unit NAR orders and custom kits.' ) );

	$seeds['sam-medical'] = array_merge( array(
		'enabled'      => 1,
		'title'        => 'SAM',
		'title_accent' => 'Medical',
		'eyebrow'      => 'Junctional Tourniquets · Hemostatics · Trainers',
		'chips'        => 'SJT, ChitoSAM 100, SAM XT Trainer',
		'_description' => 'SAM Medical hemorrhage control. The <strong>SAM Junctional Tourniquet (SJT)</strong>, <strong>ChitoSAM 100</strong> hemostatic gauze, and the <strong>SAM XT</strong> training tourniquet.',

		'tiles_label'  => 'Shop SAM Medical',
		'tiles_title'  => 'Hemorrhage control',
		'tiles'        => array(
			array( 'url' => '/sam-junctional-tourniquet/', 'kicker' => '01 · Tourniquet', 'title' => 'SAM Junctional Tourniquet', 'text' => 'For bleeding at the groin and armpit, where limb tourniquets cannot reach.', 'chips' => 'Junctional', 'cta' => 'View the SJT' ),
			array( 'url' => '/chitosam-100/', 'kicker' => '02 · Hemostatic', 'title' => 'ChitoSAM 100', 'text' => 'Chitosan hemostatic gauze.', 'chips' => 'Hemostatic, Gauze', 'cta' => 'View ChitoSAM' ),
			array( 'url' => '/sam-xt-tourniquet-training-blue/', 'kicker' => '03 · Trainer', 'title' => 'SAM XT Trainer', 'text' => 'Blue training tourniquet. Not for patient use.', 'chips' => 'Training', 'cta' => 'View the trainer' ),
		),

		'faq_title'    => 'SAM Medical questions',
		'faqs'         => array(
			array( 'q' => 'What is a junctional tourniquet?', 'a' => 'A junctional tourniquet controls bleeding where the limbs meet the body, such as the groin and armpit, where a standard limb tourniquet cannot be applied.' ),
			array( 'q' => 'Can the blue SAM XT trainer be used on a patient?', 'a' => 'No. Blue tourniquets are training devices only. Keep them out of duty kits.' ),
		),

		'related_title' => 'Shop related',
		'related_slugs' => array( 'medical' ),
	), j5_brand_seed_agency( 'Stocking a department?', 'Purchase orders accepted and quotes on multi-unit SAM Medical orders.' ) );

	$seeds['celox'] = array(
		'enabled'      => 1,
		'title'        => 'Celox',
		'title_accent' => '',
		'eyebrow'      => 'Hemostatics',
		'chips'        => 'Rapid Ribbon, Applicator',
		'_description' => 'Celox hemostatics, including the <strong><a href="/march-celox-rapid-ribbon/">Celox Rapid Ribbon</a></strong> and the <strong><a href="/celox-applicator/">Celox Applicator</a></strong>.',
		'related_title' => 'Shop related',
		'related_slugs' => array( 'medical' ),
	);

	$seeds['tacmed-solutions'] = array(
		'enabled'      => 1,
		'title'        => 'TacMed',
		'title_accent' => 'Solutions',
		'eyebrow'      => 'Tourniquets · Bandages',
		'chips'        => 'SOF Tourniquet, OLAES Bandage',
		'_description' => 'TacMed Solutions trauma gear, including the <strong><a href="/sof-tourniquet/">SOF Tourniquet</a></strong> and the <strong><a href="/olaes-modular-bandage/">OLAES Modular Bandage</a></strong>.',
		'related_title' => 'Shop related',
		'related_slugs' => array( 'medical' ),
	);

	$seeds['safeguard-medical'] = array(
		'enabled'      => 1,
		'title'        => 'SafeGuard',
		'title_accent' => 'Medical',
		'eyebrow'      => 'Trauma Bandages',
		'chips'        => 'Emergency Trauma Bandage',
		'_description' => 'SafeGuard Medical trauma bandages, including the <strong><a href="/emergency-trauma-bandage-etb-6/">4" Emergency Trauma Bandage (ETB)</a></strong> and the <a href="/emergency-trauma-bandage-etb-civilian-version/">civilian version</a>.',
		'related_title' => 'Shop related',
		'related_slugs' => array( 'medical' ),
	);

	$seeds['quikclot'] = array(
		'enabled'      => 1,
		'title'        => 'QuikClot',
		'title_accent' => '',
		'eyebrow'      => 'Hemostatics',
		'chips'        => 'Hemostatic Dressings',
		'_description' => 'QuikClot hemostatic products for hemorrhage control.',
		'related_title' => 'Shop related',
		'related_slugs' => array( 'medical' ),
	);

	$seeds['tasmanian-tiger'] = array(
		'enabled'      => 1,
		'title'        => 'Tasmanian',
		'title_accent' => 'Tiger',
		'eyebrow'      => 'Medic Packs · Aid Bags · Pouches',
		'chips'        => 'MASCAL Pack, Medic Assault Pack MKII, Move On MK II, Tac Pouch Medic',
		'_description' => 'Tasmanian Tiger (TT) medic packs and pouches. The <strong>Medic MASCAL Pack</strong> aid bag, the <strong>Medic Assault Pack MKII</strong> in S, standard and L, the <strong>First Responder Move On MK II</strong>, the Medic Transporter Panel, and the Tac Pouch Medic.',

		'tiles_label'  => 'Shop Tasmanian Tiger',
		'tiles_title'  => 'Medic packs',
		'tiles'        => array(
			array( 'url' => '/tt-medic-mascal-pack/', 'kicker' => '01 · Aid Bag', 'title' => 'Medic MASCAL Pack', 'text' => 'Aid bag built for mass casualty response.', 'chips' => 'Aid Bag, MASCAL', 'cta' => 'View the MASCAL Pack' ),
			array( 'url' => '/tt-medic-assault-pack-mkii/', 'kicker' => '02 · Backpack', 'title' => 'Medic Assault Pack MKII', 'text' => 'Medic backpack in S, standard and L sizes.', 'chips' => 'Backpack, 3 Sizes', 'cta' => 'View the Assault Pack' ),
			array( 'url' => '/tt-first-responder-move-on-mk-ii/', 'kicker' => '03 · Bag', 'title' => 'First Responder Move On MK II', 'text' => 'First responder bag.', 'chips' => 'First Responder', 'cta' => 'View the Move On' ),
			array( 'url' => '/tt-tac-pouch-medic/', 'kicker' => '04 · Pouch', 'title' => 'Tac Pouch Medic', 'text' => 'Medic pouch for belts and carriers.', 'chips' => 'Pouch', 'cta' => 'View the pouch' ),
		),

		'related_title' => 'Shop related',
		'related_slugs' => array( 'medical', 'bags-cases', 'nylon-gear' ),
	);

	$seeds['dynarex'] = array(
		'enabled'      => 1,
		'title'        => 'Dynarex',
		'title_accent' => '',
		'eyebrow'      => 'Medical Supplies',
		'chips'        => 'First Aid',
		'_description' => 'Dynarex medical and first aid supplies.',
		'related_title' => 'Shop related',
		'related_slugs' => array( 'medical' ),
	);

	$seeds['teleflex-medical'] = array(
		'enabled'      => 1,
		'title'        => 'Teleflex',
		'title_accent' => 'Medical',
		'eyebrow'      => 'Medical Devices',
		'chips'        => 'Medical',
		'_description' => 'Teleflex medical devices.',
		'related_title' => 'Shop related',
		'related_slugs' => array( 'medical' ),
	);

	/* ---------------------------------------------------------------------
	 * TOOLS, EYEWEAR, RESCUE
	 * ------------------------------------------------------------------- */

	$seeds['tekto'] = array(
		'enabled'      => 1,
		'title'        => 'Tekto',
		'title_accent' => '',
		'eyebrow'      => 'Knives',
		'chips'        => 'A2 Badger, A3 Delta, A4 Humvee, A5 Spry OTF, F1, F2, F3',
		'_description' => 'Tekto knives. The A-series, including the <strong>A2 Badger</strong>, <strong>A3 Delta</strong> automatic, <strong>A4 Humvee</strong> and <strong>A5 Spry</strong> OTF automatic, and the F-series <strong>F1 Alpha</strong>, <strong>F2 Bravo</strong> and <strong>F3 Charlie</strong>.',

		'tiles_label'  => 'Shop Tekto',
		'tiles_title'  => 'Knives',
		'tiles'        => array(
			array( 'url' => '/tekto-a2-badger/', 'kicker' => '01 · A-Series', 'title' => 'A2 Badger', 'text' => 'Tekto A2 Badger.', 'chips' => 'A-Series', 'cta' => 'View the A2' ),
			array( 'url' => '/tekto-a5-spry/', 'kicker' => '02 · OTF Automatic', 'title' => 'A5 Spry', 'text' => 'Out-the-front automatic knife.', 'chips' => 'OTF, Automatic', 'cta' => 'View the A5' ),
			array( 'url' => '/tekto-a3-delta/', 'kicker' => '03 · Automatic', 'title' => 'A3 Delta', 'text' => 'Tekto automatic knife.', 'chips' => 'Automatic', 'cta' => 'View the A3' ),
			array( 'url' => '/tekto-f1-alpha/', 'kicker' => '04 · F-Series', 'title' => 'F1 Alpha', 'text' => 'Tekto F-series knife.', 'chips' => 'F-Series', 'cta' => 'View the F1' ),
		),

		'faq_title'    => 'Tekto questions',
		'faqs'         => array(
			array( 'q' => 'Are automatic knives legal where I live?', 'a' => 'Automatic and OTF knife laws vary by state and city. Check your local laws before ordering. Checkout will tell you if we cannot ship a product to your address.' ),
		),

		'related_title' => 'Shop related',
		'related_slugs' => array( 'tools-knives' ),
	);

	$seeds['ess'] = array(
		'enabled'      => 1,
		'title'        => 'ESS',
		'title_accent' => '',
		'eyebrow'      => 'Protective Eyewear',
		'chips'        => 'Crossbow Response',
		'_description' => 'ESS protective eyewear, including the <strong><a href="/crossbow-response/">Crossbow Response</a></strong>, also available <a href="/crossbow-response-with-gasket/">with gasket</a>.',
		'related_title' => 'Shop related',
		'related_slugs' => array( 'nylon-gear' ),
	);

	$seeds['kent-safety-products'] = array(
		'enabled'      => 1,
		'title'        => 'Kent',
		'title_accent' => 'Safety Products',
		'eyebrow'      => 'Water Rescue',
		'chips'        => 'Swift Water Rescue Vest, A/M 33 Life Jacket, Throw Bags',
		'_description' => 'Kent Safety Products water rescue gear. The <strong>Swift Water Rescue Vest (SWRV)</strong>, the <strong>A/M 33 All Clear</strong> inflatable life jacket and rearming kit, and rescue throw bags in 50, 75 and 100 foot lengths.',

		'tiles_label'  => 'Shop Kent',
		'tiles_title'  => 'Water rescue',
		'tiles'        => array(
			array( 'url' => '/swift-water-rescue-vest-swrv/', 'kicker' => '01 · Vest', 'title' => 'Swift Water Rescue Vest', 'text' => 'ANSI yellow, adult universal.', 'chips' => 'Swift Water', 'cta' => 'View the SWRV' ),
			array( 'url' => '/a-m-33-all-clear-automatic-manual-inflatable-life-jacket/', 'kicker' => '02 · Life Jacket', 'title' => 'A/M 33 All Clear', 'text' => 'Automatic/manual inflatable life jacket.', 'chips' => 'Inflatable', 'cta' => 'View the A/M 33' ),
			array( 'url' => '/rescue-rope-throw-bag-75-feet/', 'kicker' => '03 · Throw Bag', 'title' => 'Rescue Throw Bags', 'text' => 'Yellow throw bags in 50, 75 and 100 foot lengths.', 'chips' => '50 ft, 75 ft, 100 ft', 'cta' => 'View throw bags' ),
			array( 'url' => '/a-m-33-all-clear-rearming-kit/', 'kicker' => '04 · Rearming Kit', 'title' => 'A/M 33 Rearming Kit', 'text' => '33g rearming kit for the A/M 33.', 'chips' => 'Rearming, 33g', 'cta' => 'View the kit' ),
		),

		'faq_title'    => 'Kent questions',
		'faqs'         => array(
			array( 'q' => 'Which rearming kit fits my inflatable life jacket?', 'a' => 'Rearming kits are model-specific. The <a href="/a-m-33-all-clear-rearming-kit/">A/M 33 rearming kit</a> fits the A/M 33 All Clear.' ),
		),

		'related_title' => 'Shop related',
		'related_slugs' => array( 'bags-cases' ),
	);

	return $seeds;
} );
