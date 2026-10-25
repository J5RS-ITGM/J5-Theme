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
	$categories = array( 'shield' => 'Shields' ); // only shields have a type step today

	if ( isset( $_POST['j5_armor_ref_save'] ) && check_admin_referer( 'j5_armor_ref_settings' ) ) {
		$all = get_option( J5_ARMOR_REF_SETTINGS, array() );
		if ( ! is_array( $all ) ) $all = array();
		foreach ( $categories as $cat => $label ) {
			$rows = isset( $_POST['types'][ $cat ] ) && is_array( $_POST['types'][ $cat ] ) ? $_POST['types'][ $cat ] : array();
			$order = array(); $hidden = array();
			// rows: [ name => [ 'sort' => n, 'show' => 1|absent ] ]
			$sorted = array();
			foreach ( $rows as $name => $r ) { $name = sanitize_text_field( wp_unslash( $name ) ); $sorted[ $name ] = (int) ( $r['sort'] ?? 999 ); if ( empty( $r['show'] ) ) $hidden[] = $name; }
			asort( $sorted );
			$order = array_keys( $sorted );
			$all[ $cat ] = array( 'order' => $order, 'hidden' => $hidden );
		}
		update_option( J5_ARMOR_REF_SETTINGS, $all, true );
		if ( function_exists( 'j5_armor_ref_after_push' ) ) j5_armor_ref_after_push( 'settings' );
		echo '<div class="notice notice-success is-dismissible"><p>Saved. The comparison page cache was cleared.</p></div>';
	}
	?>
	<div class="wrap">
		<h1>Armor Comparison</h1>
		<p>Choose which types the walkthrough offers and the order they appear in. Hidden types are removed from the tool and its search markup entirely. Types come from the dealer portal feed; edit them there.</p>
		<form method="post">
			<?php wp_nonce_field( 'j5_armor_ref_settings' ); ?>
			<?php foreach ( $categories as $cat => $label ) :
				$set   = j5_armor_ref_type_settings( $cat );
				$known = j5_armor_ref_known_types( $cat );
				$count = j5_armor_ref_type_counts( $cat );
				// present in saved order, then anything new
				$list = array_values( array_unique( array_merge( array_intersect( $set['order'], $known ), array_diff( $known, $set['order'] ) ) ) );
				?>
				<h2><?php echo esc_html( $label ); ?>: types</h2>
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
			<?php endforeach; ?>
			<p class="submit"><button type="submit" name="j5_armor_ref_save" value="1" class="button button-primary">Save</button></p>
		</form>
		<p class="description">Shields walk through threat, type, then size. Plates and helmets have no type step.</p>
	</div>
	<?php
}
