<?php
/**
 * J5 Forms — Odoo Integration
 *
 * Pushes form submissions to Odoo as crm.lead records via JSON-RPC.
 *
 * Settings (in J5 Forms → Odoo):
 *   - Enabled toggle
 *   - URL (https://ops.j5rescue.com)
 *   - Database name (e.g. hkykvp36h2s.cloudpepper.site)
 *   - Username (an Odoo user, ideally a dedicated service account)
 *   - API Key (NOT password — generate one in Odoo: Preferences → Account Security → New API Key)
 *
 * Failure here doesn't fail the WordPress submission. The handler catches
 * exceptions and logs them. The CPT entry stays intact, the email goes out,
 * only the CRM push is lost — easy to retry manually if needed.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Push a submission to Odoo as a crm.lead.
 *
 * @param string $form_id  'contact' or 'quote'
 * @param int    $post_id  WP CPT post ID (for back-reference)
 * @param array  $data     Validated submission data
 *
 * @throws Exception on any failure.
 */
function j5_forms_push_to_odoo( $form_id, $post_id, $data ) {
	$url      = trim( j5_forms_get_setting( 'odoo_url', '' ) );
	$db       = trim( j5_forms_get_setting( 'odoo_db', '' ) );
	$username = trim( j5_forms_get_setting( 'odoo_username', '' ) );
	$api_key  = trim( j5_forms_get_setting( 'odoo_api_key', '' ) );

	if ( '' === $url || '' === $db || '' === $username || '' === $api_key ) {
		throw new Exception( 'Odoo connection not fully configured.' );
	}

	$url = rtrim( $url, '/' );

	// Step 1: authenticate to get a uid.
	$uid = j5_forms_odoo_jsonrpc( $url, 'common', 'authenticate', array( $db, $username, $api_key, array() ) );
	if ( ! is_int( $uid ) || $uid <= 0 ) {
		throw new Exception( 'Odoo authentication failed (no uid returned).' );
	}

	// Step 2: build the lead payload.
	$lead = j5_forms_build_lead_payload( $form_id, $post_id, $data );

	// Step 3: create crm.lead.
	$lead_id = j5_forms_odoo_jsonrpc(
		$url,
		'object',
		'execute_kw',
		array( $db, $uid, $api_key, 'crm.lead', 'create', array( $lead ) )
	);

	if ( ! is_int( $lead_id ) || $lead_id <= 0 ) {
		throw new Exception( 'Odoo lead creation returned no id.' );
	}

	update_post_meta( $post_id, '_j5_odoo_lead_id', $lead_id );
	delete_post_meta( $post_id, '_j5_odoo_error' );

	return $lead_id;
}

/**
 * Build a crm.lead payload from form data.
 */
function j5_forms_build_lead_payload( $form_id, $post_id, $data ) {
	$is_contact = ( 'contact' === $form_id );

	$display = $data['_display_name'];
	$topic   = $is_contact ? ( $data['topic'] ?? '' ) : ( $data['service'] ?? '' );
	$prefix  = $is_contact ? '[Contact]' : '[Quote]';

	$lead_name = $prefix . ' ' . $display;
	if ( ! empty( $topic ) ) {
		$lead_name .= ' — ' . $topic;
	}

	// Build a description block with all the submitted fields.
	$desc_lines = array();
	$desc_lines[] = '<p><strong>Source:</strong> j5rescue.com ' . ucfirst( $form_id ) . ' Form</p>';
	$desc_lines[] = '<p><strong>WP Submission ID:</strong> ' . (int) $post_id . '</p>';
	$desc_lines[] = '<hr/>';
	foreach ( $data as $k => $v ) {
		if ( '_' === substr( $k, 0, 1 ) ) {
			continue;
		}
		if ( in_array( $k, array( 'first_name', 'last_name', 'full_name', 'email', 'phone' ), true ) ) {
			continue; // Already mapped to dedicated CRM fields below.
		}
		if ( is_int( $v ) || is_bool( $v ) ) {
			$v = $v ? 'Yes' : 'No';
		}
		if ( '' === trim( (string) $v ) ) {
			continue;
		}
		$label = ucwords( str_replace( '_', ' ', $k ) );
		$desc_lines[] = '<p><strong>' . esc_html( $label ) . ':</strong> ' . nl2br( esc_html( $v ) ) . '</p>';
	}
	$description = implode( "\n", $desc_lines );

	$lead = array(
		'name'         => $lead_name,
		'contact_name' => $display,
		'email_from'   => $data['email'] ?? '',
		'phone'        => $data['phone'] ?? '',
		'description'  => $description,
		'type'         => 'lead',
	);

	// Map organization → partner_name on the CRM lead. Available on both
	// forms: quote form has a dedicated field, contact form populates it
	// when agency_flag is checked.
	if ( ! empty( $data['organization'] ) ) {
		$lead['partner_name'] = $data['organization'];
	}

	// Quote-form-only extras
	if ( ! $is_contact ) {
		if ( ! empty( $data['title'] ) ) {
			$lead['function'] = $data['title'];
		}
	}

	// Allow filtering for project-specific tweaks (tags, team_id, user_id, etc).
	$lead = apply_filters( 'j5_forms_odoo_lead', $lead, $form_id, $post_id, $data );

	return $lead;
}

/**
 * Make a JSON-RPC call to an Odoo endpoint.
 *
 * @param string $url      Base Odoo URL (no trailing slash).
 * @param string $service  'common' or 'object'
 * @param string $method   'authenticate' / 'execute_kw'
 * @param array  $args     Positional arguments passed to the service method.
 *
 * @return mixed The 'result' field of the response.
 * @throws Exception on transport or RPC error.
 */
function j5_forms_odoo_jsonrpc( $url, $service, $method, $args ) {
	$endpoint = $url . '/jsonrpc';

	$payload = array(
		'jsonrpc' => '2.0',
		'method'  => 'call',
		'id'      => wp_rand( 1, 999999 ),
		'params'  => array(
			'service' => $service,
			'method'  => $method,
			'args'    => $args,
		),
	);

	$response = wp_remote_post(
		$endpoint,
		array(
			'method'  => 'POST',
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
		)
	);

	if ( is_wp_error( $response ) ) {
		throw new Exception( 'Transport error: ' . $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		throw new Exception( 'Odoo HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) ) {
		throw new Exception( 'Odoo returned non-JSON response.' );
	}

	if ( isset( $body['error'] ) ) {
		$msg = isset( $body['error']['data']['message'] )
			? $body['error']['data']['message']
			: ( isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown Odoo error' );
		throw new Exception( 'Odoo RPC error: ' . $msg );
	}

	return $body['result'] ?? null;
}

/**
 * Test the Odoo connection — used by the settings page "Test connection" button.
 */
function j5_forms_odoo_test_connection() {
	$url      = trim( j5_forms_get_setting( 'odoo_url', '' ) );
	$db       = trim( j5_forms_get_setting( 'odoo_db', '' ) );
	$username = trim( j5_forms_get_setting( 'odoo_username', '' ) );
	$api_key  = trim( j5_forms_get_setting( 'odoo_api_key', '' ) );

	if ( '' === $url || '' === $db || '' === $username || '' === $api_key ) {
		return new WP_Error( 'incomplete', 'Fill in URL, database, username, and API key first.' );
	}

	try {
		$uid = j5_forms_odoo_jsonrpc(
			rtrim( $url, '/' ),
			'common',
			'authenticate',
			array( $db, $username, $api_key, array() )
		);
		if ( ! is_int( $uid ) || $uid <= 0 ) {
			return new WP_Error( 'auth', 'Authentication returned no uid. Check username/API key.' );
		}
		return $uid;
	} catch ( Exception $e ) {
		return new WP_Error( 'rpc', $e->getMessage() );
	}
}
