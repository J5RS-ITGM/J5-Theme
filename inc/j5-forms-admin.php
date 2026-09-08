<?php
/**
 * J5 Forms — Admin
 *
 * Settings → J5 Forms page (tabbed):
 *   - General: notification email, autoresponder body
 *   - Contact Topics: editable list
 *   - Quote Services: editable list
 *   - Spam: time-trap + rate limit
 *   - Odoo: connection settings + test
 *
 * Plus admin columns and detail view for j5_submission CPT.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================================
 * SETTINGS PAGE
 * ==========================================================================*/

add_action( 'admin_menu', 'j5_forms_admin_menu' );
function j5_forms_admin_menu() {
	add_options_page(
		__( 'J5 Forms', 'astra-child' ),
		__( 'J5 Forms', 'astra-child' ),
		'manage_options',
		'j5-forms',
		'j5_forms_settings_page'
	);
}

add_action( 'admin_init', 'j5_forms_register_settings' );
function j5_forms_register_settings() {
	register_setting(
		'j5_forms_settings_group',
		J5_FORMS_OPTION,
		array( 'sanitize_callback' => 'j5_forms_sanitize_settings' )
	);
}

function j5_forms_sanitize_settings( $input ) {
	$current = get_option( J5_FORMS_OPTION, j5_forms_default_settings() );
	if ( ! is_array( $current ) ) { $current = j5_forms_default_settings(); }
	if ( ! is_array( $input ) )   { return $current; }

	$out = $current;

	// Tab-specific merging — only update the keys present in the submitted form.
	if ( isset( $input['notification_email'] ) ) {
		$out['notification_email']   = sanitize_email( $input['notification_email'] );
	}
	if ( isset( $input['autoresponder_subject'] ) ) {
		$out['autoresponder_subject'] = sanitize_text_field( $input['autoresponder_subject'] );
	}
	if ( isset( $input['autoresponder_body'] ) ) {
		$out['autoresponder_body']    = sanitize_textarea_field( $input['autoresponder_body'] );
	}
	if ( isset( $input['_tab_general'] ) ) {
		$out['autoresponder_enable'] = ! empty( $input['autoresponder_enable'] ) ? 1 : 0;
	}

	if ( isset( $input['contact_topics'] ) ) {
		$lines = preg_split( '/\r\n|\r|\n/', $input['contact_topics'] );
		$lines = array_filter( array_map( 'trim', array_map( 'sanitize_text_field', $lines ) ) );
		$out['contact_topics'] = array_values( $lines );
	}
	if ( isset( $input['quote_services'] ) ) {
		$lines = preg_split( '/\r\n|\r|\n/', $input['quote_services'] );
		$lines = array_filter( array_map( 'trim', array_map( 'sanitize_text_field', $lines ) ) );
		$out['quote_services'] = array_values( $lines );
	}

	if ( isset( $input['time_trap_seconds'] ) ) {
		$out['time_trap_seconds'] = max( 0, min( 60, (int) $input['time_trap_seconds'] ) );
	}
	if ( isset( $input['rate_limit_per_hour'] ) ) {
		$out['rate_limit_per_hour'] = max( 1, min( 100, (int) $input['rate_limit_per_hour'] ) );
	}

	if ( isset( $input['_tab_odoo'] ) ) {
		$out['odoo_enabled']  = ! empty( $input['odoo_enabled'] ) ? 1 : 0;
		$out['odoo_url']      = esc_url_raw( $input['odoo_url'] ?? '' );
		$out['odoo_db']       = sanitize_text_field( $input['odoo_db'] ?? '' );
		$out['odoo_username'] = sanitize_text_field( $input['odoo_username'] ?? '' );
		// Only update API key if a new value was submitted (avoid clobbering with empty).
		if ( ! empty( $input['odoo_api_key'] ) ) {
			$out['odoo_api_key'] = sanitize_text_field( $input['odoo_api_key'] );
		}
	}

	return $out;
}

function j5_forms_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

	// Handle test connection action.
	$test_msg = '';
	if ( 'odoo' === $tab && isset( $_POST['j5f_test_odoo'] ) && check_admin_referer( 'j5_forms_test_odoo' ) ) {
		$result = j5_forms_odoo_test_connection();
		if ( is_wp_error( $result ) ) {
			$test_msg = '<div class="notice notice-error"><p><strong>Connection failed:</strong> ' . esc_html( $result->get_error_message() ) . '</p></div>';
		} else {
			$test_msg = '<div class="notice notice-success"><p><strong>Connected successfully.</strong> Authenticated as Odoo uid #' . (int) $result . '.</p></div>';
		}
	}

	$opts = get_option( J5_FORMS_OPTION, j5_forms_default_settings() );
	if ( ! is_array( $opts ) ) { $opts = j5_forms_default_settings(); }
	$d = j5_forms_default_settings();

	// Helper to fetch with default fallback.
	$g = function( $k ) use ( $opts, $d ) {
		return $opts[ $k ] ?? ( $d[ $k ] ?? '' );
	};
	?>
	<div class="wrap">
		<h1>J5 Forms</h1>
		<?php echo $test_msg; ?>

		<h2 class="nav-tab-wrapper">
			<?php
			$tabs = array(
				'general'  => 'General',
				'contact'  => 'Contact Topics',
				'quote'    => 'Quote Services',
				'spam'     => 'Spam',
				'odoo'     => 'Odoo CRM',
			);
			foreach ( $tabs as $slug => $label ) {
				$active = ( $tab === $slug ) ? ' nav-tab-active' : '';
				printf(
					'<a href="%s" class="nav-tab%s">%s</a>',
					esc_url( admin_url( 'options-general.php?page=j5-forms&tab=' . $slug ) ),
					esc_attr( $active ),
					esc_html( $label )
				);
			}
			?>
		</h2>

		<form method="post" action="options.php">
			<?php settings_fields( 'j5_forms_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[_tab_<?php echo esc_attr( $tab ); ?>]" value="1">

			<?php if ( 'general' === $tab ) : ?>
				<table class="form-table">
					<tr>
						<th><label for="notification_email">Notification Email</label></th>
						<td>
							<input type="email" id="notification_email" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[notification_email]" value="<?php echo esc_attr( $g('notification_email') ); ?>" class="regular-text">
							<p class="description">Where new submissions are emailed. Comma-separated for multiple recipients.</p>
						</td>
					</tr>
					<tr>
						<th><label>Autoresponder</label></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[autoresponder_enable]" value="1" <?php checked( $g('autoresponder_enable'), 1 ); ?>>
								Send a confirmation email to the customer after they submit
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="autoresponder_subject">Autoresponder Subject</label></th>
						<td>
							<input type="text" id="autoresponder_subject" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[autoresponder_subject]" value="<?php echo esc_attr( $g('autoresponder_subject') ); ?>" class="large-text">
						</td>
					</tr>
					<tr>
						<th><label for="autoresponder_body">Autoresponder Body</label></th>
						<td>
							<textarea id="autoresponder_body" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[autoresponder_body]" rows="8" class="large-text code"><?php echo esc_textarea( $g('autoresponder_body') ); ?></textarea>
							<p class="description">Tokens: <code>{{name}}</code>, <code>{{form}}</code></p>
						</td>
					</tr>
				</table>

			<?php elseif ( 'contact' === $tab ) : ?>
				<h3>Contact Form — Topic Options</h3>
				<p>One topic per line. Order here = order in the dropdown.</p>
				<textarea name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[contact_topics]" rows="12" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $g('contact_topics') ) ); ?></textarea>

			<?php elseif ( 'quote' === $tab ) : ?>
				<h3>Quote Form — Service Options</h3>
				<p>One service per line. Order here = order in the dropdown.</p>
				<textarea name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[quote_services]" rows="12" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $g('quote_services') ) ); ?></textarea>

			<?php elseif ( 'spam' === $tab ) : ?>
				<table class="form-table">
					<tr>
						<th><label for="time_trap_seconds">Time-Trap Threshold</label></th>
						<td>
							<input type="number" min="0" max="60" id="time_trap_seconds" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[time_trap_seconds]" value="<?php echo esc_attr( $g('time_trap_seconds') ); ?>" class="small-text"> seconds
							<p class="description">Reject submissions made faster than this. Bots fill instantly; humans take a few seconds. Default: 3.</p>
						</td>
					</tr>
					<tr>
						<th><label for="rate_limit_per_hour">Rate Limit per IP</label></th>
						<td>
							<input type="number" min="1" max="100" id="rate_limit_per_hour" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[rate_limit_per_hour]" value="<?php echo esc_attr( $g('rate_limit_per_hour') ); ?>" class="small-text"> per hour
							<p class="description">Max submissions per IP per hour. Default: 8.</p>
						</td>
					</tr>
				</table>
				<p><strong>Honeypot</strong> is always active — a hidden field that bots fill but humans never see. No setting needed.</p>

			<?php elseif ( 'odoo' === $tab ) : ?>
				<table class="form-table">
					<tr>
						<th>Enabled</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[odoo_enabled]" value="1" <?php checked( $g('odoo_enabled'), 1 ); ?>> Push submissions to Odoo as crm.lead</label></td>
					</tr>
					<tr>
						<th><label for="odoo_url">Odoo URL</label></th>
						<td><input type="url" id="odoo_url" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[odoo_url]" value="<?php echo esc_attr( $g('odoo_url') ); ?>" class="regular-text"> <span class="description">e.g. https://ops.j5rescue.com</span></td>
					</tr>
					<tr>
						<th><label for="odoo_db">Database</label></th>
						<td><input type="text" id="odoo_db" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[odoo_db]" value="<?php echo esc_attr( $g('odoo_db') ); ?>" class="regular-text"> <span class="description">e.g. hkykvp36h2s.cloudpepper.site</span></td>
					</tr>
					<tr>
						<th><label for="odoo_username">Username</label></th>
						<td><input type="text" id="odoo_username" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[odoo_username]" value="<?php echo esc_attr( $g('odoo_username') ); ?>" class="regular-text"> <span class="description">Odoo login. Use a service account.</span></td>
					</tr>
					<tr>
						<th><label for="odoo_api_key">API Key</label></th>
						<td>
							<input type="password" id="odoo_api_key" name="<?php echo esc_attr( J5_FORMS_OPTION ); ?>[odoo_api_key]" value="" placeholder="<?php echo ! empty( $g('odoo_api_key') ) ? '••••••••(saved — leave blank to keep)' : ''; ?>" class="regular-text" autocomplete="new-password">
							<p class="description">Generate in Odoo: <em>Preferences → Account Security → New API Key</em>. NOT your password.</p>
						</td>
					</tr>
				</table>

			<?php endif; ?>

			<?php submit_button( 'Save Settings' ); ?>
		</form>

		<?php if ( 'odoo' === $tab ) : ?>
			<form method="post" style="margin-top:24px;padding:16px;background:#f6f7f7;border:1px solid #c3c4c7;">
				<?php wp_nonce_field( 'j5_forms_test_odoo' ); ?>
				<button type="submit" name="j5f_test_odoo" class="button button-secondary">Test Odoo Connection</button>
				<span class="description">&nbsp;Tests authentication with the saved settings. Save first if you've changed anything.</span>
			</form>
		<?php endif; ?>

		<?php if ( 'general' === $tab ) : ?>
			<hr>
			<h3>Shortcodes</h3>
			<p>Drop these into any page (block editor → Shortcode block):</p>
			<p><code>[j5_contact_form]</code> — renders the contact form (with sidebar info card)</p>
			<p><code>[j5_quote_form]</code> — renders the quote form (with the agency banner above it)</p>
		<?php endif; ?>
	</div>
	<?php
}

/* ============================================================================
 * SUBMISSION CPT — admin columns + detail view
 * ==========================================================================*/

add_filter( 'manage_' . J5_FORMS_CPT . '_posts_columns', 'j5_forms_admin_columns' );
function j5_forms_admin_columns( $columns ) {
	$new = array();
	$new['cb']    = $columns['cb'] ?? '<input type="checkbox" />';
	$new['title'] = 'Submission';
	$new['j5_form'] = 'Form';
	$new['j5_email'] = 'Email';
	$new['j5_topic'] = 'Topic / Service';
	$new['taxonomy-j5_submission_status'] = 'Status';
	$new['date']  = 'Submitted';
	return $new;
}

add_action( 'manage_' . J5_FORMS_CPT . '_posts_custom_column', 'j5_forms_admin_column_content', 10, 2 );
function j5_forms_admin_column_content( $col, $post_id ) {
	switch ( $col ) {
		case 'j5_form':
			echo esc_html( ucfirst( get_post_meta( $post_id, '_j5_form_id', true ) ) );
			break;
		case 'j5_email':
			$email = get_post_meta( $post_id, '_j5_field_email', true );
			if ( $email ) {
				printf( '<a href="mailto:%s">%s</a>', esc_attr( $email ), esc_html( $email ) );
			}
			break;
		case 'j5_topic':
			$t = get_post_meta( $post_id, '_j5_field_topic', true );
			if ( ! $t ) {
				$t = get_post_meta( $post_id, '_j5_field_service', true );
			}
			echo esc_html( $t );
			break;
	}
}

/**
 * Detail metabox on the submission edit screen.
 */
add_action( 'add_meta_boxes_' . J5_FORMS_CPT, 'j5_forms_add_detail_metabox' );
function j5_forms_add_detail_metabox() {
	add_meta_box( 'j5_form_detail', 'Submission Detail', 'j5_forms_render_detail_metabox', J5_FORMS_CPT, 'normal', 'high' );
	add_meta_box( 'j5_form_meta', 'Metadata', 'j5_forms_render_meta_metabox', J5_FORMS_CPT, 'side', 'default' );
}

function j5_forms_render_detail_metabox( $post ) {
	$all_meta = get_post_meta( $post->ID );
	echo '<table class="widefat striped" style="border:0;"><tbody>';
	foreach ( $all_meta as $key => $values ) {
		if ( strpos( $key, '_j5_field_' ) !== 0 ) {
			continue;
		}
		$label = ucwords( str_replace( '_', ' ', substr( $key, strlen( '_j5_field_' ) ) ) );
		$val   = maybe_unserialize( $values[0] );
		if ( is_int( $val ) || is_bool( $val ) ) {
			$val = $val ? 'Yes' : 'No';
		}
		printf(
			'<tr><th style="width:200px;text-align:left;">%s</th><td>%s</td></tr>',
			esc_html( $label ),
			nl2br( esc_html( (string) $val ) )
		);
	}
	echo '</tbody></table>';

	// Attachments
	$attached = get_post_meta( $post->ID, '_j5_attachments', true );
	if ( ! empty( $attached ) && is_array( $attached ) ) {
		echo '<h3 style="margin-top:24px;">Attachments</h3><ul>';
		foreach ( $attached as $att_id ) {
			$url  = wp_get_attachment_url( $att_id );
			$name = basename( get_attached_file( $att_id ) );
			if ( $url ) {
				printf( '<li><a href="%s" target="_blank">%s</a></li>', esc_url( $url ), esc_html( $name ) );
			}
		}
		echo '</ul>';
	}
}

function j5_forms_render_meta_metabox( $post ) {
	$form_id   = get_post_meta( $post->ID, '_j5_form_id', true );
	$ip        = get_post_meta( $post->ID, '_j5_ip', true );
	$ua        = get_post_meta( $post->ID, '_j5_user_agent', true );
	$ref       = get_post_meta( $post->ID, '_j5_referrer', true );
	$odoo_id   = get_post_meta( $post->ID, '_j5_odoo_lead_id', true );
	$odoo_err  = get_post_meta( $post->ID, '_j5_odoo_error', true );

	echo '<p><strong>Form:</strong> ' . esc_html( $form_id ) . '</p>';
	echo '<p><strong>IP:</strong> ' . esc_html( $ip ) . '</p>';
	if ( $ref )    { echo '<p><strong>Referrer:</strong><br>' . esc_html( $ref ) . '</p>'; }
	if ( $ua )     { echo '<p style="font-size:11px;color:#666;"><strong>UA:</strong> ' . esc_html( $ua ) . '</p>'; }
	if ( $odoo_id ){ echo '<p><strong>Odoo Lead ID:</strong> #' . (int) $odoo_id . '</p>'; }
	if ( $odoo_err ){ echo '<p style="color:#a00;"><strong>Odoo Error:</strong><br>' . esc_html( $odoo_err ) . '</p>'; }
}

/**
 * Disable the block editor for submissions — the metabox UI is enough.
 */
add_filter( 'use_block_editor_for_post_type', 'j5_forms_disable_block_editor', 10, 2 );
function j5_forms_disable_block_editor( $use, $post_type ) {
	return ( J5_FORMS_CPT === $post_type ) ? false : $use;
}
