<?php
/**
 * J5 Acknowledgments — Admin Settings
 * -----------------------------------
 * Settings → J5 Acknowledgments. Define reusable purchase acknowledgments
 * (title, body text, required flag) and manage the list. These are GLOBAL
 * definitions; which products they attach to is per-product data owned by the
 * hub (meta _j5_acknowledgments).
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the settings page — only if the consolidated J5 Apps menu isn't
 * present (it registers this page under itself instead).
 */
add_action( 'admin_menu', function () {
	if ( defined( 'J5_APPS_MENU' ) && J5_APPS_MENU ) {
		return;
	}
	add_options_page(
		'J5 Acknowledgments',
		'J5 Acknowledgments',
		'manage_options',
		'j5-acknowledgments',
		'j5_ack_settings_page'
	);
} );

/**
 * Handle save.
 */
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['j5_ack_save'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'j5_ack_save', 'j5_ack_nonce' );

	$new  = array();
	$rows = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array();

	foreach ( $rows as $orig_slug => $row ) {
		if ( ! empty( $row['_delete'] ) ) {
			continue;
		}
		$slug = sanitize_key( isset( $row['slug'] ) ? $row['slug'] : $orig_slug );
		if ( '' === $slug ) {
			continue;
		}
		$new[ $slug ] = array(
			'title'    => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : ucfirst( str_replace( array( '_', '-' ), ' ', $slug ) ),
			'body'     => isset( $row['body'] ) ? sanitize_textarea_field( $row['body'] ) : '',
			'required' => ! empty( $row['required'] ),
			'placement' => isset( $row['placement'] ) && 'product' === $row['placement'] ? 'product' : 'checkout',
		);
	}

	// New row.
	if ( ! empty( $_POST['new_slug'] ) ) {
		$slug = sanitize_key( wp_unslash( $_POST['new_slug'] ) );
		if ( '' !== $slug && ! isset( $new[ $slug ] ) ) {
			$new[ $slug ] = array(
				'title'    => isset( $_POST['new_title'] ) && '' !== $_POST['new_title'] ? sanitize_text_field( wp_unslash( $_POST['new_title'] ) ) : ucfirst( str_replace( array( '_', '-' ), ' ', $slug ) ),
				'body'     => isset( $_POST['new_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['new_body'] ) ) : '',
				'required' => ! empty( $_POST['new_required'] ),
				'placement' => isset( $_POST['new_placement'] ) && 'product' === $_POST['new_placement'] ? 'product' : 'checkout',
			);
		}
	}

	update_option( J5_ACK_OPTION, $new );

	set_transient( 'j5_ack_notice', 'Acknowledgments saved.', 30 );
	wp_safe_redirect( admin_url( 'admin.php?page=j5-acknowledgments' ) );
	exit;
} );

/**
 * Render the page.
 */
function j5_ack_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$defs = j5_ack_definitions();

	$notice = get_transient( 'j5_ack_notice' );
	if ( $notice ) {
		delete_transient( 'j5_ack_notice' );
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notice ) );
	}
	?>
	<div class="wrap">
		<h1>J5 Product Acknowledgments</h1>
		<p class="description">
			Define reusable acknowledgments (ITAR, age 18+, medical device, etc.). Attach them to products by publishing the acknowledgment <strong>slug</strong> to product meta <code>_j5_acknowledgments</code> (the hub does this; see the bottom of this page for the manual command). Required acknowledgments block checkout until the buyer ticks them, and the affirmation is recorded on the order.
		</p>

		<form method="post" action="">
			<?php wp_nonce_field( 'j5_ack_save', 'j5_ack_nonce' ); ?>

			<table class="widefat striped" style="margin-top:16px;">
				<thead>
					<tr>
						<th style="width:14%;">Slug</th>
						<th style="width:22%;">Title</th>
						<th style="width:34%;">Body (shown to buyer)</th>
						<th style="width:14%;">Affirmed at <span title="Checkout = one checkbox per order at payment. Product page = ticked before Add to Cart, recorded per item; checkout still asks for items that reached the cart another way." style="cursor:help;">&#9432;</span></th>
						<th style="width:8%;">Required</th>
						<th style="width:8%;">Delete</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $defs ) ) : ?>
						<tr><td colspan="6"><em>No acknowledgments defined yet. Add one below.</em></td></tr>
					<?php endif; ?>
					<?php foreach ( $defs as $slug => $d ) : ?>
						<tr>
							<td>
								<input type="text" name="rows[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" pattern="[a-z0-9_\-]+" style="width:100%;" />
							</td>
							<td><input type="text" name="rows[<?php echo esc_attr( $slug ); ?>][title]" value="<?php echo esc_attr( $d['title'] ); ?>" style="width:100%;" /></td>
							<td><textarea name="rows[<?php echo esc_attr( $slug ); ?>][body]" rows="3" style="width:100%;"><?php echo esc_textarea( $d['body'] ); ?></textarea></td>
							<td>
								<select name="rows[<?php echo esc_attr( $slug ); ?>][placement]" style="width:100%;">
									<option value="checkout" <?php selected( $d['placement'], 'checkout' ); ?>>Checkout</option>
									<option value="product" <?php selected( $d['placement'], 'product' ); ?>>Product page</option>
								</select>
							</td>
							<td style="text-align:center;"><input type="checkbox" name="rows[<?php echo esc_attr( $slug ); ?>][required]" value="1" <?php checked( $d['required'] ); ?> /></td>
							<td style="text-align:center;"><input type="checkbox" name="rows[<?php echo esc_attr( $slug ); ?>][_delete]" value="1" title="Delete on save" /></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;">Add an acknowledgment</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="new_slug">Slug</label></th>
					<td>
						<input type="text" id="new_slug" name="new_slug" value="" pattern="[a-z0-9_\-]+" placeholder="e.g. medical_device" class="regular-text" />
						<p class="description">Lowercase letters, numbers, hyphens, underscores. This is what the hub publishes to attach it.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="new_title">Title</label></th>
					<td><input type="text" id="new_title" name="new_title" value="" placeholder="e.g. Medical Device — Licensed Professionals Only" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="new_body">Body</label></th>
					<td><textarea id="new_body" name="new_body" rows="3" class="large-text" placeholder="The statement the buyer must acknowledge."></textarea></td>
				</tr>
				<tr>
					<th scope="row">Required</th>
					<td><label><input type="checkbox" name="new_required" value="1" checked /> Block purchase until acknowledged</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="new_placement">Affirmed at</label></th>
					<td>
						<select id="new_placement" name="new_placement">
							<option value="checkout">Checkout</option>
							<option value="product">Product page</option>
						</select>
						<p class="description">Product page captures the affirmation per item, at the moment of the decision. Checkout still asks for items that reached the cart without a product page visit.</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="j5_ack_save" value="1" class="button button-primary">Save Acknowledgments</button>
			</p>
		</form>

		<hr />
		<h2>How to attach to a product</h2>
		<p class="description">
			Normally the hub publishes this. To attach manually (comma-separated slugs) via WP-CLI:<br />
			<code>wp post meta update &lt;PRODUCT_ID&gt; _j5_acknowledgments "itar,age18" --path=/home/j5rescue/htdocs/j5rescue.com</code><br />
			To remove: <code>wp post meta delete &lt;PRODUCT_ID&gt; _j5_acknowledgments --path=/home/j5rescue/htdocs/j5rescue.com</code><br />
			Variations inherit their parent product's acknowledgments automatically.
		</p>
	</div>
	<?php
}
