<?php
/**
 * J5 Archive Config — per-category archive page configuration
 *
 * Slug-keyed registry of per-category overrides for the WC product archive.
 * Reads at archive render time. If no entry exists for the current category
 * slug, the archive falls back to the default toolbar+sidebar+grid layout
 * with no hero band, no brand strip, no special copy.
 *
 * Schema (all keys optional except 'enabled'):
 *
 *   'enabled' => bool
 *       If false (or key missing), category renders the default archive.
 *
 *   'parent_landing' => string|null
 *       Slug of parent landing page (used for accent color inheritance).
 *       Currently 'armor' = gold, 'medical'/'march' = red, null = neutral gold.
 *
 *   'hero' => [
 *       'breadcrumb' => array of [label, url] tuples (last item is current page, no url)
 *       'eyebrow' => string,
 *       'eyebrow_color' => 'gold' | 'red' | 'blue' (default: 'gold')
 *       'title_main' => string,
 *       'title_accent' => string,           // colored portion of title
 *       'title_accent_color' => 'gold' | 'red' (default matches parent_landing)
 *       'body' => string,                   // descriptive copy. <strong> allowed.
 *       'meta_strip' => array of [label, value] pairs
 *       'side' => [
 *           'type' => 'stat' | 'march',
 *           // For 'stat':
 *           'label' => string,
 *           'num' => string,
 *           'sub' => string,
 *           // For 'march':
 *           'letter' => 'M'|'A'|'R'|'C'|'H',
 *           'word' => string,
 *           'sub' => string,
 *       ]
 *   ]
 *
 *   'brand_strip' => [
 *       'label' => string,                  // default: "Trusted Lines"
 *       'brands' => array of brand names
 *   ]
 *
 *   'subcategory_strip' => [                // optional chip strip above toolbar
 *       'label' => string,                  // default: "Quick Filter"
 *       'chips' => array of [label, slug, active]
 *   ]
 *
 *   'layout' => 'grid' | 'brand-grouped'    // default 'grid'
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Returns the full archive config registry.
 */
function j5_get_archive_config_registry() {
	return array(

		/*===================================================================
		 *  BODY ARMOR
		 *===================================================================*/
		'body-armor' => array(
			'enabled'        => true,
			'parent_landing' => 'armor',
			'hero' => array(
				'breadcrumb' => array(
					array( 'label' => 'Shop',  'url' => '/shop/' ),
					array( 'label' => 'Armor', 'url' => '/armor/' ),
					array( 'label' => 'Body Armor' ),
				),
				'eyebrow'             => 'Hard Plates · Soft Panels · NIJ Rated',
				'eyebrow_color'       => 'gold',
				'title_main'          => 'BODY',
				'title_accent'        => 'ARMOR',
				'title_accent_color'  => 'gold',
				'body'                => 'Hard armor plate sets and soft armor panels across <strong>NIJ III, III+, IV, and Special Threat</strong>. HESCO, HighCom, and United Shield International primary lines, in stock and special order.',
				'meta_strip' => array(
					array( 'label' => 'NIJ',       'value' => 'III · III+ · IV · ST' ),
					array( 'label' => 'CUTS',      'value' => "SAPI · Shooter's · Swimmer" ),
					array( 'label' => 'QUICKSHIP', 'value' => 'Yes' ),
				),
				'side' => array(
					'type'  => 'stat',
					'label' => 'Plates Stocked',
					'num'   => '', // auto-fills from product count if blank
					'sub'   => 'Across 5 Threat Lines',
				),
			),
			'brand_strip' => array(
				'label'  => 'Trusted Lines',
				'brands' => array( 'HESCO', 'HighCom', 'United Shield International', 'PROTECH' ),
			),
			'layout' => 'grid',
		),

		/*===================================================================
		 *  MASSIVE HEMORRHAGE  (M.A.R.C.H. — M)
		 *===================================================================*/
		'massive-hemorrhage' => array(
			'enabled'        => true,
			'parent_landing' => 'medical',
			'hero' => array(
				'breadcrumb' => array(
					array( 'label' => 'Shop',     'url' => '/shop/' ),
					array( 'label' => 'Medical',  'url' => '/medical/' ),
					array( 'label' => 'M.A.R.C.H.', 'url' => '/m-a-r-c-h/' ),
					array( 'label' => 'Massive Hemorrhage' ),
				),
				'eyebrow'             => 'M — Massive Bleeding · Stage 1 of 5',
				'eyebrow_color'       => 'red',
				'title_main'          => 'HEMORRHAGE',
				'title_accent'        => 'CONTROL',
				'title_accent_color'  => 'red',
				'body'                => 'Stop life-threatening extremity and junctional bleeding. <strong>Tourniquets, hemostatic gauze, junctional devices, and pressure dressings</strong> — the first products you reach for under TCCC protocol. Every consumable lot-traceable. Life Safety Replacement Guarantee on qualifying items.',
				'meta_strip' => array(
					array( 'label' => 'TCCC',        'value' => 'Recommended' ),
					array( 'label' => 'STAGE',       'value' => 'M (1 of 5)' ),
					array( 'label' => 'LIFE SAFETY', 'value' => 'Eligible items' ),
				),
				'side' => array(
					'type'   => 'march',
					'letter' => 'M',
					'word'   => 'Massive',
					'sub'    => 'Hemorrhage',
				),
			),
			'subcategory_strip' => array(
				'label' => 'Quick Filter',
				'chips' => array(
					array( 'label' => 'All Hemorrhage',     'slug' => '',                  'active' => true ),
					array( 'label' => 'Tourniquets',        'slug' => 'tourniquets',       'active' => false ),
					array( 'label' => 'Hemostatic Agents',  'slug' => 'hemostatic-agents', 'active' => false ),
					array( 'label' => 'Junctional TQs',     'slug' => 'junctional-tqs',    'active' => false ),
					array( 'label' => 'Pressure Dressings', 'slug' => 'pressure-dressings','active' => false ),
					array( 'label' => 'Gauze',              'slug' => 'gauze',             'active' => false ),
				),
			),
			'layout' => 'grid',
		),

		/*===================================================================
		 *  LIGHTS  (top-level category, brand-grouped layout)
		 *===================================================================*/
		'lights' => array(
			'enabled'        => true,
			'parent_landing' => null,
			'hero' => array(
				'breadcrumb' => array(
					array( 'label' => 'Shop',   'url' => '/shop/' ),
					array( 'label' => 'Lights' ),
				),
				'eyebrow'             => 'Handheld · WML · Helmet · Scene',
				'eyebrow_color'       => 'gold',
				'title_main'          => 'LIGHTS',
				'title_accent'        => '',
				'body'                => 'Tactical and rescue illumination across <strong>handheld, weapon-mounted, helmet, and scene</strong> applications. Streamlight, SureFire, FoxFury, and ASP — the brands officers and operators actually run. Lumen output, beam pattern, and battery life clearly spec\'d.',
				'meta_strip' => array(
					array( 'label' => 'USE CASES', 'value' => 'Patrol · Tactical · K9 · Search' ),
					array( 'label' => 'BATTERY',   'value' => 'Rechargeable · CR123 · AA' ),
				),
				'side' => array(
					'type'  => 'stat',
					'label' => 'SKUs in Stock',
					'num'   => '',
					'sub'   => 'Across multiple brands',
				),
			),
			'layout' => 'brand-grouped',
		),

	);
}

/**
 * Returns the config for a single category slug, or null if no config exists.
 *
 * @param string $slug
 * @return array|null
 */
function j5_get_archive_config( $slug ) {
	$registry = j5_get_archive_config_registry();
	if ( ! is_string( $slug ) || $slug === '' ) {
		return null;
	}
	if ( ! isset( $registry[ $slug ] ) ) {
		return null;
	}
	$config = $registry[ $slug ];
	if ( empty( $config['enabled'] ) ) {
		return null;
	}
	return $config;
}

/**
 * Returns config for the currently-rendering product category, if any.
 *
 * @return array|null
 */
function j5_get_current_archive_config() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return null;
	}
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return null;
	}
	return j5_get_archive_config( $term->slug );
}
