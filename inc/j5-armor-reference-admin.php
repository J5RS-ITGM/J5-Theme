<?php
/**
 * J5 Armor Comparison: admin settings (J5 Apps > Armor Comparison)
 *
 * Per category, which `type` values the tool shows and in what order. Types
 * come from the DP feed (the registry pick list plus whatever models carry);
 * nothing here edits the feed. Hidden types are dropped from the tool and its
 * schema on render, the same way Panel always is.
 *
 * Option: j5_armor_ref_settings = [ 'shield' => [ 'order' => [...], 'hidden' => [...] ], ... ]
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const J5_ARMOR_REF_SETTINGS = 'j5_armor_ref_settings';

/** Default type order per category; anything not listed sorts after, alphabetically. */
function j5_armor_ref_default_order( $category ) {
	$d = array( 'shield' => array( 'Patrol', 'Entry', 'Flexible', 'Ladder', 'Rolling', 'Panel' ) );
	return isset( $d[ $category ] ) ? $d[ $category ] : array();
}

/** Size groups the plate size step uses (ids are fixed in the JS; labels and order are editable). */
function j5_armor_ref_size_groups() {
	return array( 'side' => 'Side plates', 'shooter' => "Shooter's cut", 'sapi' => 'SAPI cut', 'other' => 'Other sizes' );
}

/** Manufacturers pinned to the front of every list, per category. Matched case-insensitively as a substring. */
function j5_armor_ref_featured_makers( $category ) {
	$all = get_option( J5_ARMOR_REF_SETTINGS, array() );
	$s   = isset( $all[ $category ]['featured'] ) && is_array( $all[ $category ]['featured'] ) ? $all[ $category ]['featured'] : null;
	if ( null === $s ) $s = array( 'United Shield', 'HESCO', 'HighCom' );
	return array_values( array_filter( array_map( 'trim', $s ) ) );
}

/** Plate size-group settings: [ 'order' => [ids], 'hidden' => [ids] ]. */
function j5_armor_ref_size_group_settings() {
	$all = get_option( J5_ARMOR_REF_SETTINGS, array() );
	$s   = isset( $all['plate']['size_groups'] ) && is_array( $all['plate']['size_groups'] ) ? $all['plate']['size_groups'] : array();
	$ids = array_keys( j5_armor_ref_size_groups() );
	$order  = isset( $s['order'] ) && is_array( $s['order'] ) ? array_values( array_intersect( $s['order'], $ids ) ) : $ids;
	$order  = array_values( array_unique( array_merge( $order, $ids ) ) );
	$hidden = isset( $s['hidden'] ) && is_array( $s['hidden'] ) ? array_values( array_intersect( $s['hidden'], $ids ) ) : array();
	return array( 'order' => $order, 'hidden' => $hidden );
}

/** Settings for one category: [ 'order' => [...], 'hidden' => [...] ]. Panel is always hidden. */
function j5_armor_ref_type_settings( $category ) {
	$all = get_option( J5_ARMOR_REF_SETTINGS, array() );
	$s   = isset( $all[ $category ] ) && is_array( $all[ $category ] ) ? $all[ $category ] : array();
	$order  = isset( $s['order'] ) && is_array( $s['order'] ) ? $s['order'] : j5_armor_ref_default_order( $category );
	$hidden = isset( $s['hidden'] ) && is_array( $s['hidden'] ) ? $s['hidden'] : array();
	if ( ! in_array( 'Panel', $hidden, true ) ) $hidden[] = 'Panel';
	return array( 'order' => array_values( $order ), 'hidden' => array_values( $hidden ) );
}

/** Every type value a category's feed knows about: registry options plus values on models. */
function j5_armor_ref_known_types( $category ) {
	$feed  = function_exists( 'j5_armor_ref_feed' ) ? j5_armor_ref_feed( $category ) : null;
	$types = array();
	if ( $feed ) {
		foreach ( $feed['fields'] as $f ) if ( 'type' === $f['key'] && ! empty( $f['options'] ) ) $types = array_merge( $types, $f['options'] );
		foreach ( $feed['models'] as $m ) if ( ! empty( $m['specs']['type'] ) ) $types[] = (string) $m['specs']['type'];
	}
	$types = array_merge( $types, j5_armor_ref_default_order( $category ) );
	return array_values( array_unique( array_filter( array_map( 'trim', $types ) ) ) );
}

/** Model counts per type, for the admin table. */
function j5_armor_ref_type_counts( $category ) {
	$feed = function_exists( 'j5_armor_ref_feed' ) ? j5_armor_ref_feed( $category ) : null;
	$c = array();
	if ( $feed ) foreach ( $feed['models'] as $m ) { $t = isset( $m['specs']['type'] ) ? (string) $m['specs']['type'] : ''; if ( $t ) $c[ $t ] = ( $c[ $t ] ?? 0 ) + 1; }
	return $c;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j5-apps', 'Armor Comparison', 'Armor Comparison', 'manage_options', 'j5-armor-compare', 'j5_armor_ref_settings_page' );
}, 20 );

function j5_armor_ref_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$categories = array( 'shield' => 'Shields', 'plate' => 'Plates' );

	if ( isset( $_POST['j5_armor_ref_save'] ) && check_admin_referer( 'j5_armor_ref_settings' ) ) {
		$all = get_option( J5_ARMOR_REF_SETTINGS, array() );
		if ( ! is_array( $all ) ) $all = array();
		foreach ( $categories as $cat => $label ) {
			if ( ! isset( $all[ $cat ] ) || ! is_array( $all[ $cat ] ) ) $all[ $cat ] = array();
			// Featured manufacturers: one per line.
			$raw = isset( $_POST['featured'][ $cat ] ) ? (string) wp_unslash( $_POST['featured'][ $cat ] ) : '';
			$all[ $cat ]['featured'] = array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r?\n/', $raw ) ) ) );
			// Types (shields).
			if ( isset( $_POST['types'][ $cat ] ) && is_array( $_POST['types'][ $cat ] ) ) {
				$sorted = array(); $hidden = array();
				foreach ( $_POST['types'][ $cat ] as $name => $r ) { $name = sanitize_text_field( wp_unslash( $name ) ); $sorted[ $name ] = (int) ( $r['sort'] ?? 999 ); if ( empty( $r['show'] ) ) $hidden[] = $name; }
				asort( $sorted );
				$all[ $cat ]['order'] = array_keys( $sorted ); $all[ $cat ]['hidden'] = $hidden;
			}
			// Size groups (plates).
			if ( 'plate' === $cat && isset( $_POST['sizegroups'] ) && is_array( $_POST['sizegroups'] ) ) {
				$sorted = array(); $hidden = array();
				foreach ( $_POST['sizegroups'] as $id => $r ) { $id = sanitize_key( $id ); $sorted[ $id ] = (int) ( $r['sort'] ?? 999 ); if ( empty( $r['show'] ) ) $hidden[] = $id; }
				asort( $sorted );
				$all['plate']['size_groups'] = array( 'order' => array_keys( $sorted ), 'hidden' => $hidden );
			}
		}
		update_option( J5_ARMOR_REF_SETTINGS, $all, true );
		if ( function_exists( 'j5_armor_ref_after_push' ) ) j5_armor_ref_after_push( 'settings' );
		echo '<div class="notice notice-success is-dismissible"><p>Saved. The comparison page cache was cleared.</p></div>';
	}
	$groups = j5_armor_ref_size_groups();
	?>
	<div class="wrap">
		<h1>Armor Comparison</h1>
		<p>What the walkthrough offers and in what order. Hidden items are removed from the tool and its search markup entirely. Model data itself comes from the dealer portal; edit it there.</p>
		<form method="post">
			<?php wp_nonce_field( 'j5_armor_ref_settings' ); ?>
			<?php foreach ( $categories as $cat => $label ) : ?>
				<h2><?php echo esc_html( $label ); ?></h2>

				<h3>Featured manufacturers</h3>
				<p class="description">Listed first in every result list, in this order. One per line; partial names match (HESCO matches "Hesco Armor").</p>
				<textarea name="featured[<?php echo esc_attr( $cat ); ?>]" rows="4" cols="40"><?php echo esc_textarea( implode( "\n", j5_armor_ref_featured_makers( $cat ) ) ); ?></textarea>

				<?php if ( 'shield' === $cat ) :
					$set = j5_armor_ref_type_settings( $cat ); $known = j5_armor_ref_known_types( $cat ); $count = j5_armor_ref_type_counts( $cat );
					$list = array_values( array_unique( array_merge( array_intersect( $set['order'], $known ), array_diff( $known, $set['order'] ) ) ) ); ?>
					<h3>Types</h3>
					<table class="widefat striped" style="max-width:640px">
						<thead><tr><th style="width:70px">Show</th><th>Type</th><th style="width:90px">Models</th><th style="width:90px">Order</th></tr></thead>
						<tbody>
						<?php foreach ( $list as $i => $t ) : $is_panel = ( 'Panel' === $t ); ?>
							<tr>
								<td><input type="checkbox" name="types[<?php echo esc_attr( $cat ); ?>][<?php echo esc_attr( $t ); ?>][show]" value="1" <?php checked( ! in_array( $t, $set['hidden'], true ) ); disabled( $is_panel ); ?>></td>
								<td><?php echo esc_html( $t ); ?><?php if ( $is_panel ) echo ' <span class="description">(accessories, always hidden)</span>'; ?></td>
								<td><?php echo (int) ( $count[ $t ] ?? 0 ); ?></td>
								<td><input type="number" class="small-text" name="types[<?php echo esc_attr( $cat ); ?>][<?php echo esc_attr( $t ); ?>][sort]" value="<?php echo (int) ( $i + 1 ); ?>" min="1" step="1"></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( 'plate' === $cat ) : $sg = j5_armor_ref_size_group_settings(); ?>
					<h3>Size groups</h3>
					<p class="description">Side plates are 8 in tall or shorter. SAPI cut is the five standard SAPI/ESAPI sizes. Shooter's cut is every other rectangle.</p>
					<table class="widefat striped" style="max-width:640px">
						<thead><tr><th style="width:70px">Show</th><th>Group</th><th style="width:90px">Order</th></tr></thead>
						<tbody>
						<?php foreach ( $sg['order'] as $i => $id ) : ?>
							<tr>
								<td><input type="checkbox" name="sizegroups[<?php echo esc_attr( $id ); ?>][show]" value="1" <?php checked( ! in_array( $id, $sg['hidden'], true ) ); ?>></td>
								<td><?php echo esc_html( $groups[ $id ] ); ?></td>
								<td><input type="number" class="small-text" name="sizegroups[<?php echo esc_attr( $id ); ?>][sort]" value="<?php echo (int) ( $i + 1 ); ?>" min="1" step="1"></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endforeach; ?>
			<p class="submit"><button type="submit" name="j5_armor_ref_save" value="1" class="button button-primary">Save</button></p>
		</form>
	</div>
	<?php
}
