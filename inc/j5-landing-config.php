<?php
/**
 * ============================================================================
 *  J5 Rescue Supply — Landing Page Config Registry (v1.1)
 * ============================================================================
 *
 *  CHANGELOG
 *  ---------
 *  v1.1 (patch-2):
 *    - Armor expanded from stub to full config (3 tiles, Agency Portal angle)
 *    - New field: 'secondary_callout' (object) replaces 'show_kit_builder_callout'
 *      Lets each page show a DIFFERENT second callout. The 'show_life_safety_callout'
 *      boolean stays — it's the only truly global reusable one.
 *    - New field: 'show_agency_banner' — defaults to TRUE on all landing pages.
 *      Set to false to hide the inline Agency & Department Buyers banner.
 *    - New field: 'section_heading' — overrides the default "{category name}"
 *      heading on the subcategory grid. MARCH uses this for "M.A.R.C.H.",
 *      Armor uses "CATEGORIES".
 *    - hero.title is now optional. If omitted, no big typographic hero renders
 *      and the eyebrow + phrase + body carry the visual weight.
 *
 *  FIELD REFERENCE (full)
 *  ----------------------
 *  hero.eyebrow                : Pill text above title. ''|string
 *  hero.eyebrow_color          : 'red' (default) | 'gold' | 'blue' — drives pill/dot color
 *  hero.title                  : Array of chars for big headline. Multiple = per-letter
 *                                accent (MARCH style). Single = full-width single-color
 *                                underline (Armor style). Omit for no big hero title.
 *  hero.colors                 : Array matching hero.title length. Per-letter accent hex.
 *  hero.phrase                 : Array of words between title and body. Omit for none.
 *  hero.subtitle               : Alternative to hero.phrase — simpler "A · B · C"
 *                                subtitle for non-acronym pages. Array of words.
 *  hero.body                   : Descriptive paragraph. Supports <strong>.
 *  hero.stats                  : Array of [value, label] pairs.
 *  hero.stats_variant          : 'default' (big number) | 'small' (text-list)
 *
 *  section_heading             : The big ALL-CAPS heading above the subcategory grid.
 *                                Default: the category name upper-cased.
 *  section_subheading          : Muted subtitle below section_heading.
 *
 *  subcats                     : Array of tile structs (unchanged from v1.0)
 *
 *  show_agency_banner          : bool, default TRUE. Global default.
 *  show_life_safety_callout    : bool, default TRUE.
 *  secondary_callout           : array|null — the second callout after Life Safety.
 *      kicker                  : Small label above title
 *      title_html              : Headline (HTML OK, <br> useful)
 *      body_html               : Paragraph (HTML OK for <strong>)
 *      link_url                : Target URL
 *      link_text               : Button text
 *
 *  featured_product_ids        : array of WP post IDs
 *  related_category_slugs      : array of slugs
 *  related_heading             : Override for "Related in {parent}" section header
 *
 *  BRAND TOKENS
 *  ------------
 *    red    = #c8102e  |  gold   = #d4a044  |  blue   = #4a90c2
 *    green  = #5aa572  |  amber  = #c97b2b  |  purple = #8b6fb8
 * ============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function j5_landing_config() {
	$config = array(

		// ====================================================================
		// M.A.R.C.H. — TCCC protocol sequence
		// ====================================================================
		'm-a-r-c-h' => array(

			'hero' => array(
				'eyebrow'       => 'Tactical Combat Casualty Care · TCCC Aligned',
				'eyebrow_color' => 'red',
				'title'         => array( 'M', 'A', 'R', 'C', 'H' ),
				'phrase'        => array(
					'Massive Hemorrhage', 'Airway', 'Respiration', 'Circulation', 'Hypothermia',
				),
				'colors'        => array(
					'#c8102e', // M — red
					'#4a90c2', // A — blue
					'#5aa572', // R — green
					'#c97b2b', // C — amber
					'#8b6fb8', // H — purple
				),
				'body'          => 'The <strong>MARCH sequence</strong> is the TCCC priority framework for trauma intervention — the order point of care medical providers work through preventable causes of death in the field. Every kit, tool, and consumable here is organized around that sequence: the things you reach for first, in the order you\'ll need them.',
				'stats'         => array(
					array( '57',   'Products Stocked' ),
					array( 'TCCC', 'Recommended Devices & Much More' ),
				),
				'stats_variant' => 'default',
			),

			'section_heading'    => 'M.A.R.C.H.',
			'section_subheading' => 'Enter by stage · Treat in order',

			'subcats' => array(
				array(
					'slug'      => 'massive-hemorrhage',
					'letter'    => 'M',
					'eyebrow'   => 'M — Massive Bleeding',
					'name_html' => 'Hemorrhage<br>Control',
					'desc'      => 'Stop life-threatening extremity and junctional bleeding. Tourniquets, hemostatic gauze, and junctional devices.',
					'chips'     => array( 'Tourniquets', 'Hemostatic Agents', 'Junctional TQs', 'Gauze', 'Pressure Dressings' ),
					'icon'      => 'blood-drop',
					'cta'       => 'Shop Hemorrhage Control',
				),
				array(
					'slug'      => 'airway',
					'letter'    => 'A',
					'eyebrow'   => 'A — Airway',
					'name_html' => 'Airway<br>Management',
					'desc'      => 'Establish and maintain a patent airway. Nasopharyngeal airways, recovery positions, and adjuncts.',
					'chips'     => array( 'NPAs', 'Cric Kits', 'BVMs', 'Adjuncts' ),
					'icon'      => 'airway-head',
					'cta'       => 'Shop Airway',
				),
				array(
					'slug'      => 'respiration',
					'letter'    => 'R',
					'eyebrow'   => 'R — Respiration',
					'name_html' => 'Breathing &amp;<br>Chest Trauma',
					'desc'      => 'Manage tension pneumothorax and open chest wounds. Vented chest seals and decompression needles.',
					'chips'     => array( 'Chest Seals', 'Decompression', 'Occlusive' ),
					'icon'      => 'lungs',
					'cta'       => 'Shop Respiration',
				),
				array(
					'slug'      => 'circulation',
					'letter'    => 'C',
					'eyebrow'   => 'C — Circulation',
					'name_html' => 'Circulation &amp;<br>IV/IO Access',
					'desc'      => 'Manage shock and maintain perfusion. Pressure dressings, wound packing gauze, and IV/IO fluid resuscitation.',
					'chips'     => array( 'Pressure Dressings', 'Gauze', 'IV/IO Access' ),
					'icon'      => 'iv-bag-heart',
					'cta'       => 'Shop Circulation',
				),
				array(
					'slug'      => 'hypothermia-prevention',
					'letter'    => 'H',
					'eyebrow'   => 'H — Hypothermia',
					'name_html' => 'Hypothermia<br>Prevention',
					'desc'      => 'Prevent heat loss in trauma casualties. Emergency blankets, hypothermia wraps, and casualty evacuation gear.',
					'chips'     => array( 'Blankets', 'Casualty Wraps', 'Heat Packs' ),
					'icon'      => 'thermometer',
					'cta'       => 'Shop Hypothermia',
				),
			),

			'featured_product_ids'    => array(),
			'show_agency_banner'      => true,
			'show_life_safety_callout' => true,
			'secondary_callout' => array(
				'kicker'    => 'Build Your Kit',
				'title_html' => 'IFAK &amp; Aid Bag<br>Customization',
				'body_html' => 'Spec a custom IFAK or aid bag for your agency, squad, or vehicle loadout — pick the pouch, pick the contents, lock the price. Volume pricing on 10+ kits. Quotes returned same business day.',
				'link_url'  => '/bleeding-control-kit-builder/',
				'link_text' => 'Launch the builder',
			),

			'related_category_slugs' => array(
				'first-aid', 'bleeding-control-kit', 'stocked-ifaks',
				'burn-care', 'automated-external-defibrillator-aed', 'loaded-jump-bags',
			),
			'related_heading' => 'Related in <span class="j5-accent">Medical</span>',
		),

		// ====================================================================
		// ARMOR — Ballistic protection
		// ====================================================================
		'armor' => array(

			'hero' => array(
				'eyebrow'       => 'Ballistic Protection · NIJ Rated',
				'eyebrow_color' => 'gold',
				'title'         => array( 'ARMOR' ),   // Single element = single-word hero treatment
				'colors'        => array( '#d4a044' ), // Gold underline
				'subtitle'      => array( 'Ballistic Plates', 'Shields', 'Helmets' ),
				'body'          => 'Hard armor plates, soft armor panels, ballistic shields, and helmets — rated to NIJ standards, selected for what law enforcement and tactical teams actually encounter in the field. From <strong>HESCO, HighCom, and United Shield International</strong>, with threat-specific products across <strong>Level III &amp; III+, Level IV, and Special Threat</strong>. Agency quotes returned same day.',
				'stats'         => array(
					array( 'HESCO · HIGHCOM · UNITED SHIELD INTERNATIONAL', 'Brand Partners' ),
					array( 'III · III+ · IV · ST', 'NIJ Threat Levels Covered' ),
				),
				'stats_variant' => 'small',
			),

			'section_heading'    => 'CATEGORIES',
			'section_subheading' => 'Shop by armor type',

			'subcats' => array(
				array(
					'slug'      => 'body-armor',
					'letter'    => 'BA',
					'eyebrow'   => '01 — Hard Armor Plates & Soft Armor Panels',
					'name_html' => 'Body<br>Armor',
					'desc'      => 'Hard armor plate sets and soft armor panels across NIJ III, III+, IV, and Special Threat ratings. HESCO, HighCom, and United Shield International primary lines, in stock and special order.',
					'chips'     => array( 'Plate Sets', 'Level III & III+', 'Level IV', 'Special Threat', 'QuickShip' ),
					'icon'      => 'plate',
					'cta'       => 'Shop Body Armor',
					'color'     => '#d4a044',
				),
				array(
					'slug'      => 'shields',
					'letter'    => 'SH',
					'eyebrow'   => '02 — Pistol & Rifle Rated Ballistic Shields',
					'name_html' => 'Ballistic<br>Shields',
					'desc'      => 'Pistol and rifle rated ballistic shields for entry, rescue, and patrol. Lightweight construction with integrated viewports and mounting systems.',
					'chips'     => array( 'Pistol Rated', 'Rifle Rated', 'Shield Accessories', 'Mounts' ),
					'icon'      => 'shield',
					'cta'       => 'Shop Shields',
					'color'     => '#d4a044',
				),
				array(
					'slug'      => 'helmets',
					'letter'    => 'HL',
					'eyebrow'   => '03 — Head Protection',
					'name_html' => 'Helmets',
					'desc'      => 'Ballistic helmets and accessories for patrol, tactical, and specialized operations. MICH, ATE, and full-cut profiles with rails and accessory mounts.',
					'chips'     => array( 'Ballistic Helmets', 'Rails & Mounts', 'Covers' ),
					'icon'      => 'helmet',
					'cta'       => 'Shop Helmets',
					'color'     => '#d4a044',
				),
			),

			'featured_product_ids'    => array(),
			'show_agency_banner'      => true,
			'show_life_safety_callout' => true,
			'secondary_callout' => array(
				'kicker'    => 'Agency Portal',
				'title_html' => 'Built for How<br>Agencies Buy',
				'body_html' => 'Net 30 terms · PO uploads · departmental pricebooks · dedicated point of contact per agency · CJIS-aware portal. Quote out of ops, receive in your inbox, pay by PO, ship to the department.',
				'link_url'  => 'https://ops.j5rescue.com',
				'link_text' => 'Request portal access',
				'link_target' => '_blank',
			),

			'related_category_slugs' => array(
				'plate-carriers', 'tactical-outer-carriers', 'plate-carrier-accessories',
				'helmet-rails-mounts', 'loaded-jump-bags', 'm-a-r-c-h',
			),
			'related_heading' => 'Pairs Well With',
		),

		// ====================================================================
		// Stubs — fill these in one by one
		// ====================================================================
		'medical'                   => array( '_stub' => true ),
		'nylon-gear'                => array( '_stub' => true ),
		'bags-cases'                => array( '_stub' => true ),
		'less-lethal'               => array( '_stub' => true ),
		'lights'                    => array( '_stub' => true ),
		'tools-knives'              => array( '_stub' => true ),
		'optics-weapon-accessories' => array( '_stub' => true ),
		'specialty'                 => array( '_stub' => true ),

	);

	return apply_filters( 'j5_landing_config', $config );
}

function j5_landing_config_for( $slug ) {
	$config = j5_landing_config();
	return isset( $config[ $slug ] ) ? $config[ $slug ] : null;
}

function j5_has_full_landing( $slug ) {
	$entry = j5_landing_config_for( $slug );
	if ( empty( $entry ) ) { return false; }
	return empty( $entry['_stub'] );
}
