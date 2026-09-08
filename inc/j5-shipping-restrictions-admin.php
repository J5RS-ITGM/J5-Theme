<?php
/**
 * J5 Shipping Restrictions — Admin
 * --------------------------------
 * J5 Apps → Shipping Restrictions. Edit WP-local rules; view hub-published
 * rules read-only. Both sets are enforced together (union, fail-closed).
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * US state codes for the picker.
 *
 * @return array code => name
 */
function j5_ship_states() {
	return array(
		'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
		'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
		'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
		'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
		'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
		'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
		'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
		'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
		'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
		'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
		'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
		'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
		'WI' => 'Wisconsin', 'WY' => 'Wyoming',
	);
}

/**
 * Save local rules.
 */
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['j5_ship_save'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'j5_ship_save', 'j5_ship_nonce' );

	$new = array();

	// Existing local rows.
	$rows = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array();
	foreach ( $rows as $row ) {
		if ( ! empty( $row['_delete'] ) ) {
			continue;
		}
		$rule = j5_ship_admin_row_to_rule( $row );
		if ( $rule ) {
			$new[] = $rule;
		}
	}

	// New rule — only if the add-form was actually filled in (prevents
	// creating a duplicate every time you save existing rows).
	if ( isset( $_POST['new'] ) && is_array( $_POST['new'] ) ) {
		$n         = wp_unslash( $_POST['new'] );
		$has_input = ! empty( $n['label'] )
			|| ! empty( $n['categories'] )
			|| ! empty( $n['tags'] )
			|| ( isset( $n['products'] ) && '' !== trim( (string) $n['products'] ) );
		if ( $has_input ) {
			$rule = j5_ship_admin_row_to_rule( $n );
			if ( $rule ) {
				$new[] = $rule;
			}
		}
	}

	update_option( J5_SHIP_LOCAL_OPTION, $new );
	set_transient( 'j5_ship_notice', 'Shipping restrictions saved.', 30 );
	wp_safe_redirect( admin_url( 'admin.php?page=j5-shipping-restrictions' ) );
	exit;
} );

/**
 * Convert a posted admin row into a normalized rule (or null).
 *
 * @param array $row
 * @return array|null
 */
function j5_ship_admin_row_to_rule( $row ) {
	$states = array();
	if ( ! empty( $row['states'] ) && is_array( $row['states'] ) ) {
		$states = $row['states'];
	}
	$cities = array();
	if ( ! empty( $row['cities'] ) ) {
		foreach ( explode( ',', (string) $row['cities'] ) as $c ) {
			$c = trim( $c );
			if ( '' !== $c ) {
				$cities[] = $c;
			}
		}
	}
	return j5_ship_normalize_rule( array(
		'label'      => isset( $row['label'] ) ? $row['label'] : '',
		'categories' => isset( $row['categories'] ) ? $row['categories'] : array(),
		'tags'       => isset( $row['tags'] ) ? $row['tags'] : array(),
		'products'   => isset( $row['products'] ) ? $row['products'] : '',
		'block_type' => isset( $row['block_type'] ) ? $row['block_type'] : '',
		'states'     => $states,
		'cities'     => $cities,
	) );
}

/**
 * Load WooCommerce's bundled Select2 (wc-enhanced-select) on this page only,
 * so category/tag pickers become searchable chip inputs. Also wires the
 * per-row delete buttons.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! isset( $_GET['page'] ) || 'j5-shipping-restrictions' !== $_GET['page'] ) {
		return;
	}

	// WooCommerce ships select2 + the enhanced-select wrapper.
	wp_enqueue_style( 'woocommerce_admin_styles' );
	wp_enqueue_script( 'wc-enhanced-select' );
	wp_enqueue_script( 'jquery' );

	$js = <<<'JS'
jQuery( function ( $ ) {
	function initPickers( $scope ) {
		$scope.find( 'select.j5-ship-select2' ).each( function () {
			var $el = $( this );
			if ( $el.data( 'select2' ) ) { return; }
			if ( $.fn.selectWoo ) {
				$el.selectWoo( { width: '100%', placeholder: $el.data( 'placeholder' ) || 'Search…', allowClear: true } );
			} else if ( $.fn.select2 ) {
				$el.select2( { width: '100%', placeholder: $el.data( 'placeholder' ) || 'Search…', allowClear: true } );
			}
		} );
	}
	initPickers( $( document ) );

	// Per-row delete: mark for removal and hide immediately.
	$( document ).on( 'click', '.j5-ship-delete', function ( e ) {
		e.preventDefault();
		var $row = $( this ).closest( 'tr' );
		if ( ! window.confirm( 'Delete this rule? This takes effect when you click Save.' ) ) { return; }
		$row.find( 'input.j5-ship-delete-flag' ).val( '1' );
		$row.addClass( 'j5-ship-row-deleted' ).hide();
		$( '.j5-ship-deleted-note' ).show();
	} );
} );
JS;
	wp_add_inline_script( 'wc-enhanced-select', $js );

	wp_add_inline_style( 'woocommerce_admin_styles', '.j5-ship-deleted-note{display:none;margin:10px 0;padding:8px 12px;background:#fcf9e8;border-left:4px solid #dba617;} .j5-ship-delete{color:#b32d2e;text-decoration:none;} .j5-ship-delete:hover{color:#8a2424;}' );
} );

/**
 * Render the page.
 */
function j5_ship_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$local  = j5_ship_read_rules( J5_SHIP_LOCAL_OPTION );
	$hub    = j5_ship_read_rules( J5_SHIP_HUB_OPTION );
	$states = j5_ship_states();
	$cats   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	$tags   = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) );

	$notice = get_transient( 'j5_ship_notice' );
	if ( $notice ) {
		delete_transient( 'j5_ship_notice' );
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notice ) );
	}

	// Helper to summarize a rule's targets (multi-target aware).
	$target_name = function ( $rule ) {
		$parts = array();
		if ( ! empty( $rule['categories'] ) ) {
			$names = array();
			foreach ( $rule['categories'] as $id ) {
				$t       = get_term( $id, 'product_cat' );
				$names[] = ( $t && ! is_wp_error( $t ) ) ? $t->name : ( '#' . $id );
			}
			$parts[] = 'Categories: ' . implode( ', ', $names );
		}
		if ( ! empty( $rule['tags'] ) ) {
			$names = array();
			foreach ( $rule['tags'] as $id ) {
				$t       = get_term( $id, 'product_tag' );
				$names[] = ( $t && ! is_wp_error( $t ) ) ? $t->name : ( '#' . $id );
			}
			$parts[] = 'Tags: ' . implode( ', ', $names );
		}
		if ( ! empty( $rule['products'] ) ) {
			$names = array();
			foreach ( $rule['products'] as $id ) {
				$p       = wc_get_product( $id );
				$names[] = $p ? $p->get_name() : ( '#' . $id );
			}
			$parts[] = 'Products: ' . implode( ', ', $names );
		}
		return implode( ' | ', $parts );
	};
	?>
	<div class="wrap">
		<h1>J5 Shipping Restrictions</h1>
		<p class="description">Block shipments of certain products to certain destinations (e.g. no body armor to Connecticut). Rules from the hub and rules you add here are <strong>both enforced</strong> — a destination is blocked if either matches. Enforcement: a cart warning plus a hard block at checkout.</p>

		<?php if ( ! empty( $hub ) ) : ?>
			<h2 style="margin-top:22px;">From the hub <span style="font-weight:400;color:#888;font-size:13px;">(read-only — edit in the hub)</span></h2>
			<table class="widefat striped">
				<thead><tr><th>Label</th><th>Applies to</th><th>Blocked destination</th></tr></thead>
				<tbody>
					<?php foreach ( $hub as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r['label'] ? $r['label'] : '—' ); ?></td>
							<td><?php echo esc_html( $target_name( $r ) ); ?></td>
							<td><?php echo esc_html( ucfirst( $r['block_type'] ) . ': ' . j5_ship_rule_dest_text( $r ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'j5_ship_save', 'j5_ship_nonce' ); ?>

			<h2 style="margin-top:26px;">Local rules <span style="font-weight:400;color:#888;font-size:13px;">(editable here)</span></h2>
			<p class="description">A rule matches a product if it is in <strong>any</strong> selected category, <strong>or</strong> carries <strong>any</strong> selected tag, <strong>or</strong> is one of the listed product IDs.</p>
			<div class="j5-ship-deleted-note">Rule(s) marked for deletion. Click <strong>Save Restrictions</strong> to apply.</div>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:15%;">Label</th>
						<th style="width:21%;">Categories</th>
						<th style="width:19%;">Tags</th>
						<th style="width:12%;">Product IDs</th>
						<th style="width:9%;">Block by</th>
						<th style="width:18%;">States / Cities</th>
						<th style="width:6%;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $local ) ) : ?>
						<tr><td colspan="7"><em>No local rules yet. Add one below.</em></td></tr>
					<?php endif; ?>
					<?php foreach ( $local as $i => $r ) : ?>
						<tr>
							<td>
								<input type="text" name="rows[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $r['label'] ); ?>" style="width:100%;" />
								<input type="hidden" class="j5-ship-delete-flag" name="rows[<?php echo (int) $i; ?>][_delete]" value="" />
							</td>
							<td>
								<select class="j5-ship-select2" data-placeholder="Search categories…" name="rows[<?php echo (int) $i; ?>][categories][]" multiple style="width:100%;">
									<?php foreach ( $cats as $c ) : ?>
										<option value="<?php echo esc_attr( $c->term_id ); ?>" <?php echo in_array( (int) $c->term_id, array_map( 'intval', $r['categories'] ), true ) ? 'selected' : ''; ?>><?php echo esc_html( $c->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select class="j5-ship-select2" data-placeholder="Search tags…" name="rows[<?php echo (int) $i; ?>][tags][]" multiple style="width:100%;">
									<?php foreach ( $tags as $t ) : ?>
										<option value="<?php echo esc_attr( $t->term_id ); ?>" <?php echo in_array( (int) $t->term_id, array_map( 'intval', $r['tags'] ), true ) ? 'selected' : ''; ?>><?php echo esc_html( $t->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<input type="text" name="rows[<?php echo (int) $i; ?>][products]" value="<?php echo esc_attr( implode( ', ', $r['products'] ) ); ?>" placeholder="95776, 88240" style="width:100%;" />
								<br><small>Comma-separated IDs</small>
							</td>
							<td>
								<select name="rows[<?php echo (int) $i; ?>][block_type]">
									<option value="state" <?php selected( $r['block_type'], 'state' ); ?>>State</option>
									<option value="city" <?php selected( $r['block_type'], 'city' ); ?>>City</option>
								</select>
							</td>
							<td>
								<?php if ( 'city' === $r['block_type'] ) : ?>
									<input type="text" name="rows[<?php echo (int) $i; ?>][cities]" value="<?php echo esc_attr( implode( ', ', $r['cities'] ) ); ?>" placeholder="chicago, aurora" style="width:100%;" />
									<br><small>Comma-separated city names</small>
								<?php else : ?>
									<select class="j5-ship-select2" data-placeholder="Search states…" name="rows[<?php echo (int) $i; ?>][states][]" multiple style="width:100%;">
										<?php foreach ( $states as $sc => $sn ) : ?>
											<option value="<?php echo esc_attr( $sc ); ?>" <?php echo in_array( $sc, $r['states'], true ) ? 'selected' : ''; ?>><?php echo esc_html( $sc . ' - ' . $sn ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
							</td>
							<td style="text-align:center;">
								<a href="#" class="j5-ship-delete" title="Delete this rule">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:26px;">Add a local rule</h2>
			<p class="description">Leave the Label blank if you don't want to add a rule when saving.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="new_label">Label</label></th>
					<td><input type="text" id="new_label" name="new[label]" value="" class="regular-text" placeholder="e.g. No armor or shields to CT" /></td>
				</tr>
				<tr>
					<th scope="row">Categories</th>
					<td>
						<select class="j5-ship-select2" data-placeholder="Search categories…" name="new[categories][]" multiple style="min-width:420px;">
							<?php foreach ( $cats as $c ) : ?><option value="<?php echo esc_attr( $c->term_id ); ?>"><?php echo esc_html( $c->name ); ?></option><?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Tags</th>
					<td>
						<select class="j5-ship-select2" data-placeholder="Search tags…" name="new[tags][]" multiple style="min-width:420px;">
							<?php foreach ( $tags as $t ) : ?><option value="<?php echo esc_attr( $t->term_id ); ?>"><?php echo esc_html( $t->name ); ?></option><?php endforeach; ?>
						</select>
						<p class="description">Optional. A product matches if it has any selected tag.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Product IDs</th>
					<td>
						<input type="text" name="new[products]" value="" class="regular-text" placeholder="95776, 88240" />
						<p class="description">Optional. Comma-separated product IDs.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Block by</th>
					<td>
						<select name="new[block_type]">
							<option value="state">State</option>
							<option value="city">City</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">States</th>
					<td>
						<select class="j5-ship-select2" data-placeholder="Search states…" name="new[states][]" multiple style="min-width:420px;">
							<?php foreach ( $states as $sc => $sn ) : ?><option value="<?php echo esc_attr( $sc ); ?>"><?php echo esc_html( $sc . ' - ' . $sn ); ?></option><?php endforeach; ?>
						</select>
						<p class="description">Used when Block by = State.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Cities</th>
					<td>
						<input type="text" name="new[cities]" value="" class="regular-text" placeholder="chicago, aurora" />
						<p class="description">Comma-separated. Used when Block by = City.</p>
					</td>
				</tr>
			</table>

			<p class="submit"><button type="submit" name="j5_ship_save" value="1" class="button button-primary">Save Restrictions</button></p>
		</form>

		<hr />
		<h2>Hub contract</h2>
		<p class="description">The hub publishes its rule set to option <code>j5_shipping_rules_hub</code> (array of rules; each: <code>label, categories[], tags[], products[], block_type [state|city], states[], cities[]</code>). The legacy single-target shape (<code>match_type</code> + <code>match</code>) is still accepted and upconverted automatically. WP-local rules live in <code>j5_shipping_rules_local</code>. Both are enforced together; neither overwrites the other.</p>
	</div>
	<?php
}
