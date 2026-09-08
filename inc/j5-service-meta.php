<?php
/**
 * J5 Service Page meta — eyebrow text and optional hero CTA.
 *
 * Adds a metabox to pages using template-j5-service.php with three fields:
 *   - Eyebrow Text   (small uppercase line above the page title in the hero)
 *   - CTA Label      (button text under the intro paragraph)
 *   - CTA URL        (button destination)
 *
 * Stored as native post meta (no ACF dependency):
 *   _j5_service_eyebrow
 *   _j5_service_cta_label
 *   _j5_service_cta_url
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the metabox on all page edit screens.
 *
 * We don't gate visibility to "only pages using the service template" because:
 *   1. New pages haven't picked a template yet — editors need to fill the
 *      eyebrow/CTA fields in the same flow as setting the template.
 *   2. The values are only consumed by template-j5-service.php on the front
 *      end. On other templates, the meta sits unused — harmless.
 */
add_action( 'add_meta_boxes_page', 'j5_service_register_metabox' );
function j5_service_register_metabox( $post ) {
	add_meta_box(
		'j5_service_meta',
		__( 'J5 Service Page — Hero', 'astra-child' ),
		'j5_service_render_metabox',
		'page',
		'side',
		'high'
	);
}

/**
 * Render the metabox UI.
 */
function j5_service_render_metabox( $post ) {
	wp_nonce_field( 'j5_service_meta_save', 'j5_service_meta_nonce' );

	$eyebrow   = get_post_meta( $post->ID, '_j5_service_eyebrow', true );
	$cta_label = get_post_meta( $post->ID, '_j5_service_cta_label', true );
	$cta_url   = get_post_meta( $post->ID, '_j5_service_cta_url', true );
	?>
	<p style="margin-top:0;font-size:11px;color:#666;">
		<?php esc_html_e( 'These fields show in the hero band of pages using the "J5 Service Page" template. The page Title, Featured Image, and Excerpt are also used in the hero.', 'astra-child' ); ?>
	</p>

	<p>
		<label for="j5_service_eyebrow" style="font-weight:600;display:block;margin-bottom:4px;">
			<?php esc_html_e( 'Eyebrow Text', 'astra-child' ); ?>
		</label>
		<input
			type="text"
			id="j5_service_eyebrow"
			name="j5_service_eyebrow"
			value="<?php echo esc_attr( $eyebrow ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. OUR SERVICES', 'astra-child' ); ?>"
			class="widefat"
		/>
		<span style="font-size:11px;color:#888;"><?php esc_html_e( 'Small uppercase label above the title. Optional.', 'astra-child' ); ?></span>
	</p>

	<p>
		<label for="j5_service_cta_label" style="font-weight:600;display:block;margin-bottom:4px;">
			<?php esc_html_e( 'Hero CTA Label', 'astra-child' ); ?>
		</label>
		<input
			type="text"
			id="j5_service_cta_label"
			name="j5_service_cta_label"
			value="<?php echo esc_attr( $cta_label ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. Request a Quote', 'astra-child' ); ?>"
			class="widefat"
		/>
	</p>

	<p>
		<label for="j5_service_cta_url" style="font-weight:600;display:block;margin-bottom:4px;">
			<?php esc_html_e( 'Hero CTA URL', 'astra-child' ); ?>
		</label>
		<input
			type="url"
			id="j5_service_cta_url"
			name="j5_service_cta_url"
			value="<?php echo esc_attr( $cta_url ); ?>"
			placeholder="https://"
			class="widefat"
		/>
		<span style="font-size:11px;color:#888;"><?php esc_html_e( 'Both label and URL must be filled for the button to appear.', 'astra-child' ); ?></span>
	</p>
	<?php
}

/**
 * Save metabox values.
 */
add_action( 'save_post_page', 'j5_service_save_metabox', 10, 2 );
function j5_service_save_metabox( $post_id, $post ) {
	// Nonce + permission + autosave guards.
	if ( ! isset( $_POST['j5_service_meta_nonce'] )
		|| ! wp_verify_nonce( $_POST['j5_service_meta_nonce'], 'j5_service_meta_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_page', $post_id ) ) {
		return;
	}

	$fields = array(
		'_j5_service_eyebrow'   => array( 'key' => 'j5_service_eyebrow',   'sanitize' => 'sanitize_text_field' ),
		'_j5_service_cta_label' => array( 'key' => 'j5_service_cta_label', 'sanitize' => 'sanitize_text_field' ),
		'_j5_service_cta_url'   => array( 'key' => 'j5_service_cta_url',   'sanitize' => 'esc_url_raw' ),
	);

	foreach ( $fields as $meta_key => $cfg ) {
		$raw = isset( $_POST[ $cfg['key'] ] ) ? wp_unslash( $_POST[ $cfg['key'] ] ) : '';
		$val = call_user_func( $cfg['sanitize'], $raw );

		if ( '' === $val ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $val );
		}
	}
}
