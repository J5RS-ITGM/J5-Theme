<?php
/**
 * J5 Fulfillment — Admin Settings
 * -------------------------------
 * Settings → J5 Fulfillment. Lets Eric edit the label, default note, visual
 * state, and buyability of each fulfillment status, and add/remove custom
 * statuses — without needing code changes.
 *
 * These are GLOBAL label definitions (how each status looks), not per-product
 * data (which status a product has). The hub remains the source of truth for
 * the latter: it publishes a status slug per product; this screen controls how
 * that slug renders. Editing here therefore does not conflict with the
 * hub-owns-product-data rule.
 *
 * Storage: option J5_FULFILLMENT_OPTION (see inc/j5-fulfillment.php).
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Built-in status slugs. Editable but not deletable (the hub may publish them).
 *
 * @return array
 */
function j5_fulfillment_locked_slugs() {
	return array( 'in_stock', 'special', 'made_to_order', 'out', 'discontinued' );
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
		'J5 Fulfillment',
		'J5 Fulfillment',
		'manage_options',
		'j5-fulfillment',
		'j5_fulfillment_settings_page'
	);
} );

/**
 * Handle form submission (save all rows / add / delete).
 */
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['j5_fulfillment_save'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'j5_fulfillment_save', 'j5_fulfillment_nonce' );

	$valid_states = array_keys( j5_fulfillment_states() );
	$locked       = j5_fulfillment_locked_slugs();
	$new          = array();

	// Existing rows (posted as parallel arrays keyed by original slug).
	$rows = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array();
	foreach ( $rows as $orig_slug => $row ) {
		$orig_slug = sanitize_key( $orig_slug );

		// Deletion (custom rows only).
		if ( ! empty( $row['_delete'] ) && ! in_array( $orig_slug, $locked, true ) ) {
			continue;
		}

		// Slug: locked rows keep their slug; custom rows may be renamed.
		$slug = in_array( $orig_slug, $locked, true )
			? $orig_slug
			: sanitize_key( isset( $row['slug'] ) ? $row['slug'] : $orig_slug );
		if ( '' === $slug ) {
			continue;
		}

		$state = isset( $row['state'] ) && in_array( $row['state'], $valid_states, true ) ? $row['state'] : 'in';

		$new[ $slug ] = array(
			'state'   => $state,
			'label'   => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : strtoupper( str_replace( '_', ' ', $slug ) ),
			'note'    => isset( $row['note'] ) ? sanitize_text_field( $row['note'] ) : '',
			'buyable'      => ! empty( $row['buyable'] ),
			'replacements' => ! empty( $row['replacements'] ),
			'banner_lock'  => ! empty( $row['banner_lock'] ),
			'ack'          => isset( $row['ack'] ) ? sanitize_key( $row['ack'] ) : '',
		);
	}

	// New custom row (optional).
	if ( ! empty( $_POST['new_slug'] ) ) {
		$slug = sanitize_key( wp_unslash( $_POST['new_slug'] ) );
		if ( '' !== $slug && ! isset( $new[ $slug ] ) ) {
			$state = isset( $_POST['new_state'] ) && in_array( $_POST['new_state'], $valid_states, true ) ? sanitize_key( $_POST['new_state'] ) : 'in';
			$new[ $slug ] = array(
				'state'   => $state,
				'label'   => isset( $_POST['new_label'] ) && '' !== $_POST['new_label'] ? sanitize_text_field( wp_unslash( $_POST['new_label'] ) ) : strtoupper( str_replace( '_', ' ', $slug ) ),
				'note'    => isset( $_POST['new_note'] ) ? sanitize_text_field( wp_unslash( $_POST['new_note'] ) ) : '',
				'buyable'      => ! empty( $_POST['new_buyable'] ),
				'replacements' => ! empty( $_POST['new_replacements'] ),
				'banner_lock'  => ! empty( $_POST['new_banner_lock'] ),
				'ack'          => isset( $_POST['new_ack'] ) ? sanitize_key( wp_unslash( $_POST['new_ack'] ) ) : '',
			);
		}
	}

	// Safety: never let the option end up empty (would fall back to defaults).
	if ( empty( $new ) ) {
		$new = j5_fulfillment_default_types();
	}

	update_option( J5_FULFILLMENT_OPTION, $new );

	add_settings_error( 'j5_fulfillment', 'saved', 'Fulfillment statuses saved.', 'updated' );
	set_transient( 'j5_fulfillment_notice', get_settings_errors( 'j5_fulfillment' ), 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=j5-fulfillment' ) );
	exit;
} );

/**
 * Render the settings page.
 */
function j5_fulfillment_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$types  = j5_fulfillment_types();
	$states = j5_fulfillment_states();
	$locked = j5_fulfillment_locked_slugs();

	// Acknowledgment definitions populate the per-status dropdown. The
	// acknowledgments module is loaded independently, so guard rather than
	// assume — an empty list simply yields a dropdown with only "none".
	$ack_defs = function_exists( 'j5_ack_definitions' ) ? j5_ack_definitions() : array();

	$notice = get_transient( 'j5_fulfillment_notice' );
	if ( $notice ) {
		delete_transient( 'j5_fulfillment_notice' );
		foreach ( (array) $notice as $n ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $n['message'] ) );
		}
	}
	?>
	<div class="wrap">
		<h1>J5 Fulfillment Statuses</h1>
		<p class="description">
			These control how each fulfillment status appears on product pages. The hub publishes a status <strong>slug</strong> per product; the label, note, color, and buyability below control how that slug renders. Built-in statuses can be edited but not deleted (the hub may still publish them).
		</p>

		<form method="post" action="">
			<?php wp_nonce_field( 'j5_fulfillment_save', 'j5_fulfillment_nonce' ); ?>

			<table class="widefat striped" style="margin-top:16px;">
				<thead>
					<tr>
						<th style="width:14%;">Slug <span title="What the hub publishes to _j5_fulfillment" style="cursor:help;">&#9432;</span></th>
						<th style="width:20%;">Label (headline)</th>
						<th style="width:24%;">Default note (sublabel)</th>
						<th style="width:15%;">Appearance</th>
						<th style="width:9%;">Buyable</th>
						<th style="width:10%;">Shows replacements</th>
						<th style="width:10%;">Pins banner <span title="Keep this headline on variable products even after a variation is selected. Leave OFF for statuses that only restate availability." style="cursor:help;">&#9432;</span></th>
						<th style="width:14%;">Requires acknowledgment <span title="Buyers must affirm this acknowledgment at checkout for any product carrying this status. The affirmation is recorded on the order." style="cursor:help;">&#9432;</span></th>
						<th style="width:6%;">Delete</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $types as $slug => $def ) :
						$is_locked = in_array( $slug, $locked, true );
					?>
						<tr>
							<td>
								<?php if ( $is_locked ) : ?>
									<code><?php echo esc_html( $slug ); ?></code>
									<input type="hidden" name="rows[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" />
								<?php else : ?>
									<input type="text" name="rows[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" pattern="[a-z0-9_\-]+" style="width:100%;" />
								<?php endif; ?>
							</td>
							<td><input type="text" name="rows[<?php echo esc_attr( $slug ); ?>][label]" value="<?php echo esc_attr( $def['label'] ); ?>" style="width:100%;" /></td>
							<td><input type="text" name="rows[<?php echo esc_attr( $slug ); ?>][note]" value="<?php echo esc_attr( $def['note'] ); ?>" style="width:100%;" /></td>
							<td>
								<select name="rows[<?php echo esc_attr( $slug ); ?>][state]">
									<?php foreach ( $states as $sval => $slabel ) : ?>
										<option value="<?php echo esc_attr( $sval ); ?>" <?php selected( $def['state'], $sval ); ?>><?php echo esc_html( $slabel ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td style="text-align:center;">
								<input type="checkbox" name="rows[<?php echo esc_attr( $slug ); ?>][buyable]" value="1" <?php checked( $def['buyable'] ); ?> />
							</td>
							<td style="text-align:center;">
								<input type="checkbox" name="rows[<?php echo esc_attr( $slug ); ?>][replacements]" value="1" <?php checked( ! empty( $def['replacements'] ) ); ?> title="Show the 'see these instead' block from _j5_fulfillment_replaces" />
							</td>
							<td style="text-align:center;">
								<input type="checkbox" name="rows[<?php echo esc_attr( $slug ); ?>][banner_lock]" value="1" <?php checked( ! empty( $def['banner_lock'] ) ); ?> title="Pin this headline on variable products. Off = variation selection updates the banner." />
							</td>
							<td>
								<?php $row_ack = isset( $def['ack'] ) ? (string) $def['ack'] : ''; ?>
								<select name="rows[<?php echo esc_attr( $slug ); ?>][ack]" style="width:100%;">
									<option value="">&#8212; none &#8212;</option>
									<?php foreach ( $ack_defs as $aslug => $adef ) : ?>
										<option value="<?php echo esc_attr( $aslug ); ?>" <?php selected( $row_ack, $aslug ); ?>><?php echo esc_html( $adef['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( '' !== $row_ack && ! isset( $ack_defs[ $row_ack ] ) ) : ?>
									<p style="margin:4px 0 0;color:#b32d2e;font-size:11px;">
										Missing acknowledgment <code><?php echo esc_html( $row_ack ); ?></code> &mdash; no gate will show. Create it under J5 Apps &rarr; Acknowledgments.
									</p>
								<?php endif; ?>
							</td>
							<td style="text-align:center;">
								<?php if ( $is_locked ) : ?>
									<span title="Built-in — cannot delete" style="color:#999;">&#8212;</span>
								<?php else : ?>
									<input type="checkbox" name="rows[<?php echo esc_attr( $slug ); ?>][_delete]" value="1" title="Delete this status on save" />
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;">Add a custom status</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="new_slug">Slug</label></th>
					<td>
						<input type="text" id="new_slug" name="new_slug" value="" pattern="[a-z0-9_\-]+" placeholder="e.g. preorder" class="regular-text" />
						<p class="description">Lowercase letters, numbers, hyphens, underscores. This is what the hub publishes for this status.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="new_label">Label</label></th>
					<td><input type="text" id="new_label" name="new_label" value="" placeholder="e.g. PRE-ORDER" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="new_note">Default note</label></th>
					<td><input type="text" id="new_note" name="new_note" value="" placeholder="e.g. reserve now · ships next month" class="large-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="new_state">Appearance</label></th>
					<td>
						<select id="new_state" name="new_state">
							<?php foreach ( $states as $sval => $slabel ) : ?>
								<option value="<?php echo esc_attr( $sval ); ?>"><?php echo esc_html( $slabel ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Buyable</th>
					<td>
						<label><input type="checkbox" name="new_buyable" value="1" checked /> Allow Add to Cart for this status</label><br />
						<label><input type="checkbox" name="new_replacements" value="1" /> Show replacement products block</label><br />
						<label><input type="checkbox" name="new_banner_lock" value="1" /> Pin the banner on variable products (ignore variation availability)</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="new_ack">Requires acknowledgment</label></th>
					<td>
						<select id="new_ack" name="new_ack">
							<option value="">&#8212; none &#8212;</option>
							<?php foreach ( $ack_defs as $aslug => $adef ) : ?>
								<option value="<?php echo esc_attr( $aslug ); ?>"><?php echo esc_html( $adef['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Buyers must affirm this at checkout for any product carrying this status.</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="j5_fulfillment_save" value="1" class="button button-primary">Save Statuses</button>
			</p>
		</form>

		<hr />
		<h2>How to apply a status to a product</h2>
		<p class="description">
			Normally the hub sets this per product and republishes. To set one manually for testing, via WP-CLI:<br />
			<code>wp post meta update &lt;PRODUCT_ID&gt; _j5_fulfillment &lt;slug&gt; --path=/home/j5rescue/htdocs/j5rescue.com</code><br />
			Custom per-product note override: <code>_j5_fulfillment_note</code>. Discontinued replacements: <code>_j5_fulfillment_replaces</code> (comma-separated product IDs).
		</p>
	</div>
	<?php
}
