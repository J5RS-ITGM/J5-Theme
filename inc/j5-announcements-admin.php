<?php
/**
 * J5 Announcements — Admin
 * ------------------------
 * CRUD editor under J5 Apps → Announcements. Manages site/category/product
 * announcements with header, body, preset color, optional icon, active and
 * dismissible flags.
 *
 * The menu registration lives in j5-apps-menu.php; this file only registers
 * the page callback (j5_announce_settings_page) and the save handler.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save / add / delete announcements.
 */
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['j5_announce_save'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'j5_announce_save', 'j5_announce_nonce' );

	$colors = array_keys( j5_announce_colors() );
	$icons  = array_keys( j5_announce_icons() );
	$new    = array();

	// Existing rows.
	$rows = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array();
	foreach ( $rows as $id => $row ) {
		$id = sanitize_key( $id );
		if ( '' === $id || ! empty( $row['_delete'] ) ) {
			continue;
		}
		$new[ $id ] = j5_announce_sanitize_row( $row, $colors, $icons );
	}

	// New row.
	$new_row = isset( $_POST['new'] ) && is_array( $_POST['new'] ) ? wp_unslash( $_POST['new'] ) : array();
	if ( ! empty( $new_row['header'] ) || ! empty( $new_row['body'] ) ) {
		// Product scope: a typed product ID overrides the category dropdown.
		if ( isset( $new_row['scope'] ) && 'product' === $new_row['scope'] && ! empty( $new_row['target_product'] ) ) {
			$new_row['target'] = absint( $new_row['target_product'] );
		}
		$id         = 'a' . substr( md5( uniqid( '', true ) ), 0, 10 );
		$new[ $id ] = j5_announce_sanitize_row( $new_row, $colors, $icons );
	}

	update_option( J5_ANNOUNCE_OPTION, $new );
	set_transient( 'j5_announce_notice', 'Announcements saved.', 30 );
	wp_safe_redirect( admin_url( 'admin.php?page=j5-announcements' ) );
	exit;
} );

/**
 * Sanitize one announcement row.
 *
 * @param array $row
 * @param array $colors valid color slugs
 * @param array $icons  valid icon slugs
 * @return array
 */
function j5_announce_sanitize_row( $row, $colors, $icons ) {
	$scope = isset( $row['scope'] ) && in_array( $row['scope'], array( 'site', 'category', 'product' ), true ) ? $row['scope'] : 'site';
	return array(
		'scope'       => $scope,
		'target'      => isset( $row['target'] ) ? absint( $row['target'] ) : 0,
		'header'      => isset( $row['header'] ) ? sanitize_text_field( $row['header'] ) : '',
		'body'        => isset( $row['body'] ) ? sanitize_textarea_field( $row['body'] ) : '',
		'color'       => isset( $row['color'] ) && in_array( $row['color'], $colors, true ) ? $row['color'] : 'gold',
		'icon'        => isset( $row['icon'] ) && in_array( $row['icon'], $icons, true ) ? $row['icon'] : '',
		'active'      => ! empty( $row['active'] ),
		'dismissible' => ! empty( $row['dismissible'] ),
	);
}

/**
 * Render the page.
 */
function j5_announce_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$all    = j5_announce_all();
	$colors = j5_announce_colors();
	$icons  = j5_announce_icons();

	// Build target option lists once.
	$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );

	$notice = get_transient( 'j5_announce_notice' );
	if ( $notice ) {
		delete_transient( 'j5_announce_notice' );
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notice ) );
	}
	?>
	<div class="wrap">
		<h1>J5 Announcements</h1>
		<p class="description">Banners and notices shown on the storefront. Site-wide shows a strip on every page; category and product notices show an accented card. These are WordPress-managed and independent of the hub.</p>

		<form method="post" action="">
			<?php wp_nonce_field( 'j5_announce_save', 'j5_announce_nonce' ); ?>

			<h2 style="margin-top:20px;">Existing announcements</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:10%;">Scope</th>
						<th style="width:20%;">Applies to</th>
						<th style="width:18%;">Header</th>
						<th style="width:24%;">Body</th>
						<th style="width:9%;">Color</th>
						<th style="width:9%;">Icon</th>
						<th style="width:5%;">On</th>
						<th style="width:5%;">Del</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $all ) ) : ?>
						<tr><td colspan="8"><em>No announcements yet. Add one below.</em></td></tr>
					<?php endif; ?>
					<?php foreach ( $all as $id => $a ) : ?>
						<tr>
							<td>
								<select name="rows[<?php echo esc_attr( $id ); ?>][scope]">
									<?php foreach ( array( 'site' => 'Site-wide', 'category' => 'Category', 'product' => 'Product' ) as $sv => $sl ) : ?>
										<option value="<?php echo esc_attr( $sv ); ?>" <?php selected( $a['scope'], $sv ); ?>><?php echo esc_html( $sl ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<?php if ( 'category' === $a['scope'] ) : ?>
									<select name="rows[<?php echo esc_attr( $id ); ?>][target]">
										<option value="0">— select —</option>
										<?php foreach ( $cats as $c ) : ?>
											<option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( $a['target'], $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ( 'product' === $a['scope'] ) : ?>
									<input type="number" name="rows[<?php echo esc_attr( $id ); ?>][target]" value="<?php echo esc_attr( $a['target'] ); ?>" placeholder="Product ID" style="width:100%;" />
									<?php if ( $a['target'] ) { $p = wc_get_product( $a['target'] ); if ( $p ) { echo '<br><small>' . esc_html( $p->get_name() ) . '</small>'; } } ?>
								<?php else : ?>
									<span style="color:#888;">All pages</span>
									<input type="hidden" name="rows[<?php echo esc_attr( $id ); ?>][target]" value="0" />
								<?php endif; ?>
							</td>
							<td><input type="text" name="rows[<?php echo esc_attr( $id ); ?>][header]" value="<?php echo esc_attr( $a['header'] ); ?>" style="width:100%;" /></td>
							<td><textarea name="rows[<?php echo esc_attr( $id ); ?>][body]" rows="2" style="width:100%;"><?php echo esc_textarea( $a['body'] ); ?></textarea></td>
							<td>
								<select name="rows[<?php echo esc_attr( $id ); ?>][color]">
									<?php foreach ( $colors as $cv => $c ) : ?>
										<option value="<?php echo esc_attr( $cv ); ?>" <?php selected( $a['color'], $cv ); ?>><?php echo esc_html( $c['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="rows[<?php echo esc_attr( $id ); ?>][icon]">
									<?php foreach ( $icons as $iv => $il ) : ?>
										<option value="<?php echo esc_attr( $iv ); ?>" <?php selected( $a['icon'], $iv ); ?>><?php echo esc_html( $il ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td style="text-align:center;"><input type="checkbox" name="rows[<?php echo esc_attr( $id ); ?>][active]" value="1" <?php checked( $a['active'] ); ?> /></td>
							<td style="text-align:center;"><input type="checkbox" name="rows[<?php echo esc_attr( $id ); ?>][_delete]" value="1" title="Delete on save" /></td>
						</tr>
						<tr>
							<td colspan="8" style="padding-top:0;border-top:none;">
								<label style="font-size:12px;color:#666;"><input type="checkbox" name="rows[<?php echo esc_attr( $id ); ?>][dismissible]" value="1" <?php checked( $a['dismissible'] ); ?> /> Dismissible by visitor</label>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;">Add announcement</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Scope</th>
					<td>
						<select name="new[scope]">
							<option value="site">Site-wide (every page)</option>
							<option value="category">Category</option>
							<option value="product">Product</option>
						</select>
						<p class="description">For Category, enter the category in "Applies to" after saving switches the row to a picker. For Product, enter the product ID.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label>Applies to</label></th>
					<td>
						<select name="new[target]">
							<option value="0">— site-wide / choose category —</option>
							<?php foreach ( $cats as $c ) : ?>
								<option value="<?php echo esc_attr( $c->term_id ); ?>"><?php echo esc_html( $c->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Category scope: pick above. Product scope: ignore this and put the product ID here instead → <input type="number" name="new[target_product]" placeholder="Product ID" style="width:120px;" /></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="new_header">Header</label></th>
					<td><input type="text" id="new_header" name="new[header]" value="" class="regular-text" placeholder="e.g. Holiday shipping cutoff Dec 18" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="new_body">Body</label></th>
					<td><textarea id="new_body" name="new[body]" rows="2" class="large-text" placeholder="Supporting text."></textarea></td>
				</tr>
				<tr>
					<th scope="row">Color</th>
					<td>
						<select name="new[color]">
							<?php foreach ( $colors as $cv => $c ) : ?>
								<option value="<?php echo esc_attr( $cv ); ?>"><?php echo esc_html( $c['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Icon</th>
					<td>
						<select name="new[icon]">
							<?php foreach ( $icons as $iv => $il ) : ?>
								<option value="<?php echo esc_attr( $iv ); ?>"><?php echo esc_html( $il ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Options</th>
					<td>
						<label><input type="checkbox" name="new[active]" value="1" checked /> Active</label>
						&nbsp;&nbsp;
						<label><input type="checkbox" name="new[dismissible]" value="1" /> Dismissible by visitor</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="j5_announce_save" value="1" class="button button-primary">Save Announcements</button>
			</p>
		</form>
	</div>
	<?php
}
