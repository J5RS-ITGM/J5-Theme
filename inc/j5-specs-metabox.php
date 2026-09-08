<?php
/**
 * J5 Specifications Tab — admin metabox.
 *
 * Adds a metabox to the WooCommerce product edit screen for authoring the
 * structured Specifications tab content.
 *
 * Stored as native post meta (no ACF dependency):
 *   _j5_spec_content
 *
 * Rendered on the front end by inc/j5-specs-shortcodes.php via
 * j5_render_product_specs( $product ).
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the metabox on the product edit screen.
 */
add_action( 'add_meta_boxes_product', 'j5_specs_register_metabox' );
function j5_specs_register_metabox( $post ) {
	add_meta_box(
		'j5_specs_content',
		__( 'J5 Specifications Tab — Structured Content', 'astra-child' ),
		'j5_specs_render_metabox',
		'product',
		'normal',
		'high'
	);
}

/**
 * Render the metabox UI.
 */
function j5_specs_render_metabox( $post ) {
	wp_nonce_field( 'j5_specs_meta_save', 'j5_specs_meta_nonce' );

	$content = (string) get_post_meta( $post->ID, '_j5_spec_content', true );
	?>
	<p style="margin-top:0;font-size:12px;color:#555;">
		<?php esc_html_e( 'Content for the Specifications tab on the front end. Each shortcode below is optional — leave the box empty to fall back to the WooCommerce attribute table only.', 'astra-child' ); ?>
	</p>

	<p style="margin:8px 0 6px;font-size:12px;color:#555;">
		<strong><?php esc_html_e( 'Available shortcodes:', 'astra-child' ); ?></strong>
		<code>[j5_techspecs]</code>,
		<code>[j5_materials]</code>,
		<code>[j5_fitment]</code>,
		<code>[j5_in_the_box]</code>,
		<code>[j5_care]</code>,
		<code>[j5_spec_notes]</code>
	</p>

	<textarea
		id="j5_spec_content"
		name="j5_spec_content"
		rows="22"
		style="width:100%;font-family:Menlo,Consolas,Monaco,monospace;font-size:13px;line-height:1.5;"
		placeholder="<?php echo esc_attr__( 'Click "Show shortcode template" below for a starter skeleton.', 'astra-child' ); ?>"
	><?php echo esc_textarea( $content ); ?></textarea>

	<details style="margin-top:10px;">
		<summary style="cursor:pointer;font-weight:600;font-size:12px;color:#2271b1;">
			<?php esc_html_e( 'Show shortcode template', 'astra-child' ); ?>
		</summary>
		<p style="margin:8px 0 4px;font-size:11px;color:#666;">
			<?php esc_html_e( 'Copy any subset into the box above and adapt to the product. [j5_techspecs] splits each line on the first colon. [j5_in_the_box] splits on newlines. The other four are free-form prose.', 'astra-child' ); ?>
		</p>
<pre style="background:#f6f7f7;border:1px solid #dcdcde;padding:12px;margin:8px 0 0;font-size:12px;line-height:1.5;overflow-x:auto;">[j5_techspecs]
Deployable Width: 1.5 in
Stowed Length: 6.5 in
Stowed Weight: 3.4 oz
[/j5_techspecs]

[j5_materials]
Materials and construction prose goes here.
[/j5_materials]

[j5_fitment]
Compatibility and fitment notes go here.
[/j5_fitment]

[j5_in_the_box]
Item one
Item two
Item three
[/j5_in_the_box]

[j5_care]
Care and maintenance instructions go here.
[/j5_care]

[j5_spec_notes]
Freeform notes go here.
[/j5_spec_notes]</pre>
	</details>
	<?php
}

/**
 * Save the metabox value on product save.
 *
 * Note: shortcode markup contains square brackets that wp_kses_post() would
 * leave intact but other sanitizers (e.g. sanitize_textarea_field) would
 * mangle. We store the raw user input after wp_unslash(); output sanitization
 * happens at render time in j5_render_product_specs() via wp_kses_post() on
 * the already-rendered HTML.
 */
add_action( 'save_post_product', 'j5_specs_save_metabox', 10, 2 );
function j5_specs_save_metabox( $post_id, $post ) {
	// Skip autosaves and revisions.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Nonce check — also implicitly guards against quick-edit / bulk-edit
	// requests that don't include the metabox at all.
	if ( ! isset( $_POST['j5_specs_meta_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['j5_specs_meta_nonce'] ) ), 'j5_specs_meta_save' ) ) {
		return;
	}

	// Capability check.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Only act on products (defensive — the action hook already scopes us).
	if ( get_post_type( $post_id ) !== 'product' ) {
		return;
	}

	// Pull, unslash, normalize whitespace at the edges only. Preserve interior
	// formatting (newlines matter to [j5_in_the_box] and [j5_techspecs]).
	$raw = isset( $_POST['j5_spec_content'] ) ? wp_unslash( $_POST['j5_spec_content'] ) : '';
	$raw = is_string( $raw ) ? $raw : '';
	$raw = trim( $raw );

	if ( $raw === '' ) {
		delete_post_meta( $post_id, '_j5_spec_content' );
	} else {
		update_post_meta( $post_id, '_j5_spec_content', $raw );
	}
}
