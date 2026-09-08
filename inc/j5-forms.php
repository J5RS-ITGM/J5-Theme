<?php
/**
 * J5 Forms — Bootstrap
 *
 * Native WordPress contact + quote form system. Replaces Formidable.
 *
 * Provides:
 *   - [j5_contact_form] shortcode
 *   - [j5_quote_form] shortcode
 *   - j5_submission custom post type for stored entries
 *   - Settings → J5 Forms admin page
 *   - Email notifications + autoresponder
 *   - Spam: honeypot + time-trap + rate limit
 *   - Optional Odoo crm.lead push
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'J5_FORMS_VERSION', '1.0.1' );
define( 'J5_FORMS_OPTION', 'j5_forms_settings' );
define( 'J5_FORMS_CPT', 'j5_submission' );

// Load modules.
require_once get_stylesheet_directory() . '/inc/j5-forms-render.php';
require_once get_stylesheet_directory() . '/inc/j5-forms-handler.php';
require_once get_stylesheet_directory() . '/inc/j5-forms-odoo.php';
require_once get_stylesheet_directory() . '/inc/j5-forms-admin.php';

/**
 * Default settings — used on first activation and as fallback.
 */
function j5_forms_default_settings() {
	return array(
		'notification_email'   => get_option( 'admin_email' ),
		'autoresponder_enable' => 1,
		'autoresponder_subject' => 'We received your message — J5 Rescue Supply',
		'autoresponder_body'   => "Hi {{name}},\n\nThanks for reaching out to J5 Rescue Supply. We received your message and will get back to you within one business day.\n\nIf your matter is urgent, give us a call at (630) 442-4938.\n\n— The J5 Rescue Supply Team",
		'contact_topics' => array(
			'General Inquiry',
			'Order or Shipping Question',
			'Department / Agency Pricing',
			'FFL Services',
			'Design & Embroidery',
			'Returns & Warranty',
			'Other',
		),
		'quote_services' => array(
			'FFL Transfer',
			'Embroidery',
			'Patches (Custom)',
			'Engraving',
			'Body Armor (Bulk)',
			'Loadout / Kit Build',
			'Multiple Services',
			'Other',
		),
		'time_trap_seconds' => 3,
		'rate_limit_per_hour' => 8,
		'odoo_enabled'  => 0,
		'odoo_url'      => 'https://ops.j5rescue.com',
		'odoo_db'       => '',
		'odoo_username' => '',
		'odoo_api_key'  => '',
	);
}

/**
 * Get a setting with default fallback.
 */
function j5_forms_get_setting( $key, $fallback = null ) {
	$opts = get_option( J5_FORMS_OPTION, array() );
	if ( ! is_array( $opts ) ) {
		$opts = array();
	}
	$defaults = j5_forms_default_settings();
	if ( isset( $opts[ $key ] ) ) {
		return $opts[ $key ];
	}
	if ( isset( $defaults[ $key ] ) ) {
		return $defaults[ $key ];
	}
	return $fallback;
}

/**
 * Initialize default settings on first run.
 */
add_action( 'init', 'j5_forms_init_defaults', 5 );
function j5_forms_init_defaults() {
	if ( false === get_option( J5_FORMS_OPTION ) ) {
		add_option( J5_FORMS_OPTION, j5_forms_default_settings() );
	}
}

/**
 * Register the j5_submission CPT — stores every form entry.
 */
add_action( 'init', 'j5_forms_register_cpt' );
function j5_forms_register_cpt() {
	register_post_type(
		J5_FORMS_CPT,
		array(
			'label'           => __( 'Form Submissions', 'astra-child' ),
			'labels'          => array(
				'name'          => __( 'Submissions', 'astra-child' ),
				'singular_name' => __( 'Submission', 'astra-child' ),
				'menu_name'     => __( 'J5 Submissions', 'astra-child' ),
				'all_items'     => __( 'All Submissions', 'astra-child' ),
				'view_item'     => __( 'View Submission', 'astra-child' ),
				'search_items'  => __( 'Search Submissions', 'astra-child' ),
				'not_found'     => __( 'No submissions found.', 'astra-child' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-email-alt',
			'menu_position'       => 26,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'custom-fields' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
		)
	);
}

/**
 * Status taxonomy — new / reviewed / archived.
 */
add_action( 'init', 'j5_forms_register_status_tax' );
function j5_forms_register_status_tax() {
	register_taxonomy(
		'j5_submission_status',
		J5_FORMS_CPT,
		array(
			'label'        => __( 'Status', 'astra-child' ),
			'public'       => false,
			'hierarchical' => false,
			'show_ui'      => true,
			'show_admin_column' => true,
			'rewrite'      => false,
			'show_in_rest' => false,
		)
	);
}

/**
 * Seed default status terms.
 */
add_action( 'init', 'j5_forms_seed_status_terms', 20 );
function j5_forms_seed_status_terms() {
	$terms = array( 'new', 'reviewed', 'archived' );
	foreach ( $terms as $t ) {
		if ( ! term_exists( $t, 'j5_submission_status' ) ) {
			wp_insert_term( ucfirst( $t ), 'j5_submission_status', array( 'slug' => $t ) );
		}
	}
}

/**
 * Enqueue frontend CSS/JS — only on pages that contain a form shortcode.
 *
 * Inline-injects CSS at wp_head priority 999 to bypass the LiteSpeed UCSS
 * issue (same workaround pattern as J5-MOBILE-INLINE / J5-SERVICE).
 */
add_action( 'wp_head', 'j5_forms_inline_css', 999 );
function j5_forms_inline_css() {
	if ( ! j5_forms_page_has_form() ) {
		return;
	}
	$css_path = get_stylesheet_directory() . '/assets/css/j5-forms.css';
	if ( ! file_exists( $css_path ) ) {
		return;
	}
	$css = file_get_contents( $css_path );
	if ( empty( $css ) ) {
		return;
	}
	echo "\n<style id=\"j5-forms-inline\">\n" . $css . "\n</style>\n";
}

/**
 * Enqueue frontend JS (also gated to form pages).
 */
add_action( 'wp_enqueue_scripts', 'j5_forms_enqueue_js' );
function j5_forms_enqueue_js() {
	if ( ! j5_forms_page_has_form() ) {
		return;
	}
	$js_path = get_stylesheet_directory() . '/assets/js/j5-forms.js';
	$js_url  = get_stylesheet_directory_uri() . '/assets/js/j5-forms.js';
	if ( ! file_exists( $js_path ) ) {
		return;
	}
	wp_enqueue_script( 'j5-forms', $js_url, array(), filemtime( $js_path ), true );
	wp_localize_script(
		'j5-forms',
		'J5Forms',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'j5_forms_submit' ),
		)
	);
}

/**
 * Detect whether the current page contains a J5 form shortcode.
 */
function j5_forms_page_has_form() {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	if ( ! $post ) {
		return false;
	}
	$content = $post->post_content;
	return ( has_shortcode( $content, 'j5_contact_form' ) || has_shortcode( $content, 'j5_quote_form' ) );
}

/**
 * Register shortcodes.
 */
add_action( 'init', 'j5_forms_register_shortcodes' );
function j5_forms_register_shortcodes() {
	add_shortcode( 'j5_contact_form', 'j5_render_contact_form' );
	add_shortcode( 'j5_quote_form', 'j5_render_quote_form' );
}

/**
 * Get client IP for rate limiting.
 */
function j5_forms_client_ip() {
	$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
	foreach ( $keys as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			$ip = trim( explode( ',', $_SERVER[ $k ] )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '0.0.0.0';
}
