<?php
/**
 * J5 Forms — Submission Handler
 *
 * Processes form submissions: spam check → validate → sanitize → save →
 * notify → autoresponder → Odoo push. All wrapped in error handling so a
 * single failure (e.g. Odoo unreachable) doesn't lose the submission.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_j5_form_submit', 'j5_forms_handle_submit' );
add_action( 'wp_ajax_nopriv_j5_form_submit', 'j5_forms_handle_submit' );

function j5_forms_handle_submit() {
	// 1. Nonce.
	if ( ! check_ajax_referer( 'j5_forms_submit', 'j5f_nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed. Please refresh and try again.' ), 403 );
	}

	$form_id = isset( $_POST['j5f_form'] ) ? sanitize_key( $_POST['j5f_form'] ) : '';
	if ( ! in_array( $form_id, array( 'contact', 'quote' ), true ) ) {
		wp_send_json_error( array( 'message' => 'Unknown form.' ), 400 );
	}

	// 2. Honeypot — bots fill all fields, including hidden ones.
	if ( ! empty( $_POST['j5f_hp'] ) ) {
		// Quietly succeed — don't tip off the bot.
		wp_send_json_success( array( 'message' => 'Submitted.' ) );
	}

	// 3. Time trap — reject if form was submitted faster than humans can fill it.
	$ts        = isset( $_POST['j5f_ts'] ) ? (int) $_POST['j5f_ts'] : 0;
	$threshold = (int) j5_forms_get_setting( 'time_trap_seconds', 3 );
	if ( $ts < 1 || ( time() - $ts ) < $threshold ) {
		wp_send_json_error( array( 'message' => 'Form submitted too quickly. Please try again.' ), 400 );
	}

	// 4. Rate limit per IP.
	$ip       = j5_forms_client_ip();
	$key      = 'j5f_rl_' . md5( $ip );
	$count    = (int) get_transient( $key );
	$max_hour = (int) j5_forms_get_setting( 'rate_limit_per_hour', 8 );
	if ( $count >= $max_hour ) {
		wp_send_json_error( array( 'message' => 'Too many submissions from your network. Try again in an hour.' ), 429 );
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );

	// 5. Validate + sanitize.
	$data = j5_forms_validate( $form_id, $_POST );
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( array( 'message' => $data->get_error_message(), 'errors' => $data->get_error_data() ), 400 );
	}

	// 6. Save submission as a CPT post.
	$post_id = j5_forms_save_submission( $form_id, $data );
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Could not save submission. Please try again or call us.' ), 500 );
	}

	// 7. Handle file uploads (quote form only).
	if ( 'quote' === $form_id && ! empty( $_FILES['attachments'] ) ) {
		j5_forms_attach_files( $post_id, $_FILES['attachments'] );
	}

	// 8. Email notification + autoresponder. Failure here doesn't fail the request.
	j5_forms_send_notification( $form_id, $post_id, $data );
	j5_forms_send_autoresponder( $form_id, $data );

	// 9. Odoo push (optional, isolated). Failure logged, doesn't fail the request.
	if ( (int) j5_forms_get_setting( 'odoo_enabled' ) === 1 ) {
		try {
			j5_forms_push_to_odoo( $form_id, $post_id, $data );
		} catch ( Exception $e ) {
			error_log( '[j5-forms] Odoo push failed: ' . $e->getMessage() );
			update_post_meta( $post_id, '_j5_odoo_error', $e->getMessage() );
		}
	}

	wp_send_json_success( array( 'message' => 'Submission received.' ) );
}

/**
 * Validate + sanitize. Returns sanitized array or WP_Error.
 */
function j5_forms_validate( $form_id, $raw ) {
	$errors = array();
	$data   = array();

	// Common helpers
	$sanitize_text = function( $key ) use ( $raw ) {
		return isset( $raw[ $key ] ) ? sanitize_text_field( wp_unslash( $raw[ $key ] ) ) : '';
	};
	$sanitize_email = function( $key ) use ( $raw ) {
		return isset( $raw[ $key ] ) ? sanitize_email( wp_unslash( $raw[ $key ] ) ) : '';
	};
	$sanitize_textarea = function( $key ) use ( $raw ) {
		return isset( $raw[ $key ] ) ? sanitize_textarea_field( wp_unslash( $raw[ $key ] ) ) : '';
	};

	if ( 'contact' === $form_id ) {
		$data['first_name'] = $sanitize_text( 'first_name' );
		$data['last_name']  = $sanitize_text( 'last_name' );
		$data['email']      = $sanitize_email( 'email' );
		$data['phone']      = $sanitize_text( 'phone' );
		$data['agency_flag'] = ! empty( $raw['agency_flag'] ) ? 1 : 0;
		$data['organization'] = $sanitize_text( 'organization' );
		$data['topic']      = $sanitize_text( 'topic' );
		$data['message']    = $sanitize_textarea( 'message' );

		if ( '' === $data['first_name'] ) { $errors['first_name'] = 'First name required.'; }
		if ( '' === $data['last_name'] )  { $errors['last_name']  = 'Last name required.'; }
		if ( '' === $data['email'] || ! is_email( $data['email'] ) ) { $errors['email'] = 'Valid email required.'; }
		if ( '' === $data['topic'] )      { $errors['topic']      = 'Please select a topic.'; }
		if ( '' === $data['message'] )    { $errors['message']    = 'Message required.'; }
		// Conditionally required: only when agency_flag is set.
		if ( 1 === $data['agency_flag'] && '' === $data['organization'] ) {
			$errors['organization'] = 'Department / agency name required.';
		}

		// Composite display name for CPT title.
		$data['_display_name'] = trim( $data['first_name'] . ' ' . $data['last_name'] );
	} elseif ( 'quote' === $form_id ) {
		$data['full_name']    = $sanitize_text( 'full_name' );
		$data['title']        = $sanitize_text( 'title' );
		$data['email']        = $sanitize_email( 'email' );
		$data['phone']        = $sanitize_text( 'phone' );
		$data['organization'] = $sanitize_text( 'organization' );
		$data['service']      = $sanitize_text( 'service' );
		$data['quantity']     = $sanitize_text( 'quantity' );
		$data['timeline']     = $sanitize_text( 'timeline' );
		$data['budget']       = $sanitize_text( 'budget' );
		$data['description']  = $sanitize_textarea( 'description' );
		$data['discuss_call'] = ! empty( $raw['discuss_call'] ) ? 1 : 0;

		if ( '' === $data['full_name'] )   { $errors['full_name']   = 'Full name required.'; }
		if ( '' === $data['email'] || ! is_email( $data['email'] ) ) { $errors['email'] = 'Valid email required.'; }
		if ( '' === $data['phone'] )       { $errors['phone']       = 'Phone required.'; }
		if ( '' === $data['service'] )     { $errors['service']     = 'Please select a service.'; }
		if ( '' === $data['quantity'] )    { $errors['quantity']    = 'Quantity required.'; }
		if ( '' === $data['description'] ) { $errors['description'] = 'Description required.'; }

		$data['_display_name'] = $data['full_name'];
	}

	if ( ! empty( $errors ) ) {
		return new WP_Error( 'j5_forms_validation', 'Please fix the highlighted fields.', $errors );
	}

	return $data;
}

/**
 * Save validated data as a j5_submission post.
 */
function j5_forms_save_submission( $form_id, $data ) {
	$display = ! empty( $data['_display_name'] ) ? $data['_display_name'] : 'Anonymous';
	$title   = sprintf(
		'%s: %s — %s',
		ucfirst( $form_id ),
		$display,
		date_i18n( 'Y-m-d H:i' )
	);

	$post_id = wp_insert_post(
		array(
			'post_type'   => J5_FORMS_CPT,
			'post_title'  => $title,
			'post_status' => 'publish',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	// Default status = "new"
	wp_set_object_terms( $post_id, 'new', 'j5_submission_status' );

	// Form ID + IP + UA + timestamp meta.
	update_post_meta( $post_id, '_j5_form_id',    $form_id );
	update_post_meta( $post_id, '_j5_ip',         j5_forms_client_ip() );
	update_post_meta( $post_id, '_j5_user_agent', isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '' );
	update_post_meta( $post_id, '_j5_referrer',   isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '' );

	// Save each data field as meta. Skip internal keys (prefixed with _).
	foreach ( $data as $k => $v ) {
		if ( '_' === substr( $k, 0, 1 ) ) {
			continue;
		}
		update_post_meta( $post_id, '_j5_field_' . $k, $v );
	}

	return $post_id;
}

/**
 * Handle quote form file uploads.
 */
function j5_forms_attach_files( $post_id, $files ) {
	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$allowed_mime = array(
		'pdf'  => 'application/pdf',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'ai'   => 'application/postscript',
		'eps'  => 'application/postscript',
		'svg'  => 'image/svg+xml',
	);

	$max_bytes = 10 * 1024 * 1024; // 10 MB

	$count = is_array( $files['name'] ) ? count( $files['name'] ) : 0;
	$attached = array();

	for ( $i = 0; $i < min( $count, 5 ); $i++ ) {
		if ( empty( $files['name'][ $i ] ) ) {
			continue;
		}
		if ( ! empty( $files['error'][ $i ] ) ) {
			continue;
		}
		if ( $files['size'][ $i ] > $max_bytes ) {
			continue;
		}

		$file = array(
			'name'     => $files['name'][ $i ],
			'type'     => $files['type'][ $i ],
			'tmp_name' => $files['tmp_name'][ $i ],
			'error'    => $files['error'][ $i ],
			'size'     => $files['size'][ $i ],
		);

		$_FILES['j5f_single'] = $file;
		$attach_id = media_handle_upload( 'j5f_single', $post_id, array(), array(
			'test_form' => false,
			'mimes'     => $allowed_mime,
		) );
		unset( $_FILES['j5f_single'] );

		if ( ! is_wp_error( $attach_id ) ) {
			$attached[] = $attach_id;
		}
	}

	if ( ! empty( $attached ) ) {
		update_post_meta( $post_id, '_j5_attachments', $attached );
	}
}

/**
 * Email notification to admin/owner.
 *
 * J5-HTML-EMAILS-V1 — styled HTML, light theme, brand colors,
 * email-client-safe (table-based layout, inline CSS, no images).
 */
function j5_forms_send_notification( $form_id, $post_id, $data ) {
        $to = j5_forms_get_setting( 'notification_email', get_option( 'admin_email' ) );
        if ( empty( $to ) ) {
                return;
        }

        $subject       = sprintf( '[J5 %s] %s', ucfirst( $form_id ), $data['_display_name'] );
        $form_label    = strtoupper( str_replace( '_', ' ', $form_id ) );
        $submitted_at  = current_time( 'F j, Y \a\t g:i a' );
        $admin_url     = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        $client_ip     = j5_forms_client_ip();

        // Build the data rows (skip internal _ keys)
        $rows = '';
        $stripe = false;
        foreach ( $data as $k => $v ) {
                if ( '_' === substr( $k, 0, 1 ) ) {
                        continue;
                }
                $label = strtoupper( str_replace( '_', ' ', $k ) );
                if ( is_int( $v ) || is_bool( $v ) ) {
                        $v = $v ? 'Yes' : 'No';
                } elseif ( is_array( $v ) ) {
                        $v = implode( ', ', array_map( 'strval', $v ) );
                } else {
                        $v = (string) $v;
                }
                $bg     = $stripe ? '#F7F5F0' : '#FFFFFF';
                $stripe = ! $stripe;
                $rows  .= '<tr>'
                        . '<td style="padding:14px 18px;background:' . $bg . ';border-bottom:1px solid #E8E4D8;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1px;color:#8A1015;width:35%;vertical-align:top;">'
                        . esc_html( $label )
                        . '</td>'
                        . '<td style="padding:14px 18px;background:' . $bg . ';border-bottom:1px solid #E8E4D8;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1A1A1A;line-height:1.55;">'
                        . nl2br( esc_html( $v ) )
                        . '</td>'
                        . '</tr>';
        }

        $body = ''
                . '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . esc_html( $subject ) . '</title></head>'
                . '<body style="margin:0;padding:0;background:#EFEAE0;">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#EFEAE0;padding:32px 12px;">'
                . '<tr><td align="center">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#FFFFFF;border:1px solid #E0DBC8;">'

                // Red top stripe
                . '<tr><td style="background:#C41E24;height:6px;line-height:6px;font-size:0;">&nbsp;</td></tr>'

                // Brand header
                . '<tr><td style="background:#0B0B0B;padding:24px 32px;">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">'
                . '<tr>'
                . '<td style="font-family:Arial Black,Arial,Helvetica,sans-serif;font-size:20px;font-weight:900;letter-spacing:3px;color:#C8A84E;text-transform:uppercase;">J5 RESCUE SUPPLY</td>'
                . '<td align="right" style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:2px;color:#A0A0A0;text-transform:uppercase;">' . esc_html( $form_label ) . ' Submission</td>'
                . '</tr></table>'
                . '</td></tr>'

                // Hero / submitter
                . '<tr><td style="padding:32px 32px 8px 32px;">'
                . '<div style="font-family:Arial Black,Arial,Helvetica,sans-serif;font-size:24px;font-weight:900;letter-spacing:1px;color:#1A1A1A;text-transform:uppercase;line-height:1.2;">New ' . esc_html( strtolower( $form_label ) ) . '</div>'
                . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#5A5A5A;margin-top:8px;">From <strong style="color:#1A1A1A;">' . esc_html( $data['_display_name'] ) . '</strong> &middot; ' . esc_html( $submitted_at ) . '</div>'
                . '</td></tr>'

                // Submission table
                . '<tr><td style="padding:24px 32px 16px 32px;">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid #E0DBC8;border-collapse:collapse;">'
                . $rows
                . '</table>'
                . '</td></tr>'

                // Action button
                . '<tr><td style="padding:8px 32px 32px 32px;" align="left">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0">'
                . '<tr><td style="background:#C41E24;">'
                . '<a href="' . esc_url( $admin_url ) . '" style="display:inline-block;padding:14px 28px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;letter-spacing:1.5px;color:#FFFFFF;text-decoration:none;text-transform:uppercase;">View in WP Admin &rarr;</a>'
                . '</td></tr></table>'
                . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#9A9A9A;margin-top:18px;">Submitted IP: ' . esc_html( $client_ip ) . '</div>'
                . '</td></tr>'

                // Footer
                . '<tr><td style="background:#0B0B0B;padding:20px 32px;">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">'
                . '<tr>'
                . '<td style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#7A7A7A;line-height:1.6;">'
                . 'J5 Rescue Supply LLC<br>'
                . '<a href="tel:6304424938" style="color:#C8A84E;text-decoration:none;">(630) 442-4938</a> &middot; '
                . '<a href="https://www.j5rescue.com" style="color:#C8A84E;text-decoration:none;">j5rescue.com</a>'
                . '</td>'
                . '</tr></table>'
                . '</td></tr>'

                . '</table>'
                . '</td></tr></table>'
                . '</body></html>';

        $headers   = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        if ( ! empty( $data['email'] ) ) {
                $headers[] = 'Reply-To: ' . sanitize_email( $data['email'] );
        }

        wp_mail( $to, $subject, $body, $headers );
}

/**
 * Autoresponder to the customer.
 *
 * J5-HTML-EMAILS-V1 — styled HTML wrapper around the configurable
 * autoresponder body. Body text comes from the j5_forms_settings option
 * and may contain {{name}} / {{form}} tokens.
 */
function j5_forms_send_autoresponder( $form_id, $data ) {
        if ( (int) j5_forms_get_setting( 'autoresponder_enable' ) !== 1 ) {
                return;
        }
        $to = ! empty( $data['email'] ) ? $data['email'] : '';
        if ( empty( $to ) || ! is_email( $to ) ) {
                return;
        }

        $subject  = j5_forms_get_setting( 'autoresponder_subject', 'We received your message' );
        $body_tpl = j5_forms_get_setting( 'autoresponder_body', '' );
        if ( empty( $body_tpl ) ) {
                return;
        }

        // Token replacement
        $body_text = str_replace(
                array( '{{name}}', '{{form}}' ),
                array( $data['_display_name'], ucfirst( $form_id ) ),
                $body_tpl
        );

        // Convert plain-text body to HTML paragraphs (preserves line breaks)
        $paragraphs = preg_split( '/\n\s*\n/', trim( $body_text ) );
        $body_html  = '';
        foreach ( $paragraphs as $p ) {
                $p = trim( $p );
                if ( $p === '' ) {
                        continue;
                }
                $body_html .= '<p style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.65;color:#2A2A2A;margin:0 0 18px 0;">'
                        . nl2br( esc_html( $p ) )
                        . '</p>';
        }

        $body = ''
                . '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . esc_html( $subject ) . '</title></head>'
                . '<body style="margin:0;padding:0;background:#EFEAE0;">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#EFEAE0;padding:32px 12px;">'
                . '<tr><td align="center">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#FFFFFF;border:1px solid #E0DBC8;">'

                // Red top stripe
                . '<tr><td style="background:#C41E24;height:6px;line-height:6px;font-size:0;">&nbsp;</td></tr>'

                // Brand header
                . '<tr><td style="background:#0B0B0B;padding:24px 32px;text-align:center;">'
                . '<div style="font-family:Arial Black,Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:3px;color:#C8A84E;text-transform:uppercase;">J5 RESCUE SUPPLY</div>'
                . '</td></tr>'

                // Headline
                . '<tr><td style="padding:36px 32px 12px 32px;text-align:center;">'
                . '<div style="font-family:Arial Black,Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:2px;color:#1A1A1A;text-transform:uppercase;line-height:1.2;">Thanks for reaching out</div>'
                . '<div style="display:inline-block;width:60px;height:2px;background:#C8A84E;margin-top:18px;line-height:2px;font-size:0;">&nbsp;</div>'
                . '</td></tr>'

                // Body content
                . '<tr><td style="padding:24px 40px 8px 40px;">'
                . $body_html
                . '</td></tr>'

                // Phone CTA
                . '<tr><td style="padding:16px 32px 36px 32px;text-align:center;">'
                . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:2px;color:#8A1015;text-transform:uppercase;margin-bottom:8px;">Need it sooner?</div>'
                . '<a href="tel:6304424938" style="font-family:Arial Black,Arial,Helvetica,sans-serif;font-size:24px;font-weight:900;letter-spacing:1px;color:#C41E24;text-decoration:none;">(630) 442-4938</a>'
                . '</td></tr>'

                // Footer
                . '<tr><td style="background:#0B0B0B;padding:24px 32px;text-align:center;">'
                . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#7A7A7A;line-height:1.6;">'
                . 'J5 Rescue Supply LLC<br>'
                . '<a href="https://www.j5rescue.com" style="color:#C8A84E;text-decoration:none;">j5rescue.com</a>'
                . '</div>'
                . '</td></tr>'

                . '</table>'
                . '</td></tr></table>'
                . '</body></html>';

        $headers   = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        wp_mail( $to, $subject, $body, $headers );
}
