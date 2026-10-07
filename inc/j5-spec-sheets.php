<?php
/**
 * J5 Spec Sheets
 * --------------
 * "Spec Sheets" tab on the product page, listing PDF links. The files live in
 * the B2 Media bucket (spec-sheets/ folder); WooCommerce stores only the URLs,
 * so nothing is uploaded to or served from this server.
 *
 * Meta contract (published by the dealer portal; editable in wp-admin):
 *
 *   _j5_spec_sheets  Preferred. Either a JSON array:
 *                      [ { "label": "Spec Sheet", "url": "https://...pdf",
 *                          "note": "Rev 2026-03" }, ... ]
 *                    or plain text, one sheet per line:
 *                      Spec Sheet | https://media.j5rescue.com/spec-sheets/x.pdf
 *                      Sizing Chart | https://.../x-sizing.pdf | Rev B
 *
 *   _j5_spec_sheet_url / _j5_spec_sheet_label
 *                    Single-sheet fallback, used only when _j5_spec_sheets is
 *                    empty.
 *
 * Only https URLs are shown. To restrict to your own hosts:
 *   add_filter( 'j5_spec_sheet_allowed_hosts', fn() => array( 'media.j5rescue.com' ) );
 *
 * The tab only appears on products that have at least one valid sheet.
 * Sheets are product-level (variations use the parent's).
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const J5_SPEC_SHEETS_META = '_j5_spec_sheets';

/**
 * Parse whatever is stored in _j5_spec_sheets (JSON or "Label | URL" lines).
 *
 * @param string $raw
 * @return array list of [ label, url, note ]
 */
function j5_spec_sheets_parse( $raw ) {
	$raw  = trim( (string) $raw );
	$rows = array();
	if ( '' === $raw ) {
		return $rows;
	}

	$json = json_decode( $raw, true );
	if ( is_array( $json ) ) {
		foreach ( $json as $item ) {
			if ( is_string( $item ) ) {
				$rows[] = array( 'label' => '', 'url' => $item, 'note' => '' );
			} elseif ( is_array( $item ) ) {
				$rows[] = array(
					'label' => isset( $item['label'] ) ? (string) $item['label'] : '',
					'url'   => isset( $item['url'] ) ? (string) $item['url'] : '',
					'note'  => isset( $item['note'] ) ? (string) $item['note'] : '',
				);
			}
		}
		return $rows;
	}

	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		if ( 1 === count( $parts ) ) {
			$rows[] = array( 'label' => '', 'url' => $parts[0], 'note' => '' );
		} else {
			$rows[] = array(
				'label' => $parts[0],
				'url'   => $parts[1],
				'note'  => isset( $parts[2] ) ? $parts[2] : '',
			);
		}
	}
	return $rows;
}

/** Whether a URL is https and (if a host list is set) on an allowed host. */
function j5_spec_sheet_url_ok( $url ) {
	$url = trim( (string) $url );
	if ( 0 !== stripos( $url, 'https://' ) ) {
		return false;
	}
	$hosts = array_map( 'strtolower', (array) apply_filters( 'j5_spec_sheet_allowed_hosts', array() ) );
	if ( $hosts ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return in_array( $host, $hosts, true );
	}
	return true;
}

/**
 * Valid spec sheets for a product.
 *
 * @param WC_Product|int $product
 * @return array list of [ label, url, note ]
 */
function j5_get_spec_sheets( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) {
		return array();
	}
	$id   = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	$rows = j5_spec_sheets_parse( get_post_meta( $id, J5_SPEC_SHEETS_META, true ) );

	if ( ! $rows ) {
		$single = trim( (string) get_post_meta( $id, '_j5_spec_sheet_url', true ) );
		if ( '' !== $single ) {
			$rows[] = array(
				'label' => (string) get_post_meta( $id, '_j5_spec_sheet_label', true ),
				'url'   => $single,
				'note'  => '',
			);
		}
	}

	$out  = array();
	$seen = array();
	foreach ( $rows as $i => $r ) {
		$url = esc_url_raw( trim( $r['url'] ) );
		if ( ! $url || ! j5_spec_sheet_url_ok( $url ) || isset( $seen[ $url ] ) ) {
			continue;
		}
		$seen[ $url ] = true;
		$label        = trim( wp_strip_all_tags( $r['label'] ) );
		if ( '' === $label ) {
			$label = 0 === count( $out ) ? 'Spec Sheet' : 'Spec Sheet ' . ( count( $out ) + 1 );
		}
		$out[] = array(
			'label' => $label,
			'url'   => $url,
			'note'  => trim( wp_strip_all_tags( $r['note'] ) ),
		);
	}
	return apply_filters( 'j5_spec_sheets', $out, $product );
}

/** Tab button. Called from woocommerce/single-product.php. */
function j5_render_spec_sheets_tab_button( $product ) {
	$sheets = j5_get_spec_sheets( $product );
	if ( ! $sheets ) {
		return;
	}
	$label = count( $sheets ) > 1 ? 'Spec Sheets' : 'Spec Sheet';
	printf(
		'<button class="j5-tab-btn" data-tab="spec-sheets">%s%s</button>',
		esc_html( $label ),
		count( $sheets ) > 1 ? ' <span class="count">(' . (int) count( $sheets ) . ')</span>' : ''
	);
}

/** Tab panel. Called from woocommerce/single-product.php. */
function j5_render_spec_sheets_panel( $product ) {
	$sheets = j5_get_spec_sheets( $product );
	if ( ! $sheets ) {
		return;
	}
	?>
	<div class="j5-tab-panel" data-panel="spec-sheets">
		<ul class="j5-spec-sheets">
			<?php foreach ( $sheets as $s ) :
				$is_pdf = (bool) preg_match( '/\.pdf(\?|#|$)/i', $s['url'] );
				$host   = (string) wp_parse_url( $s['url'], PHP_URL_HOST );
			?>
				<li class="j5-spec-sheet">
					<span class="j5-spec-sheet__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 2.5H6.5a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V8Z"/><path d="M14 2.5V8h5.5"/></svg>
						<span><?php echo $is_pdf ? 'PDF' : 'DOC'; ?></span>
					</span>
					<span class="j5-spec-sheet__text">
						<span class="j5-spec-sheet__label"><?php echo esc_html( $s['label'] ); ?></span>
						<?php if ( '' !== $s['note'] ) : ?>
							<span class="j5-spec-sheet__note"><?php echo esc_html( $s['note'] ); ?></span>
						<?php endif; ?>
					</span>
					<a class="j5-spec-sheet__open" href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( 'Open ' . $s['label'] . ' (opens in a new tab)' ); ?>">
						Open <?php echo $is_pdf ? 'PDF' : 'file'; ?> <span aria-hidden="true">&#8599;</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="j5-spec-sheet__foot">Manufacturer specifications. Always confirm ratings and sizing for your application.</p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * wp-admin: edit the same meta on the product screen (DP pushes overwrite it)
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'j5_spec_sheets', 'Spec Sheets', 'j5_spec_sheets_metabox', 'product', 'side', 'default' );
} );

function j5_spec_sheets_metabox( $post ) {
	wp_nonce_field( 'j5_spec_sheets_save', 'j5_spec_sheets_nonce' );
	$rows  = j5_spec_sheets_parse( get_post_meta( $post->ID, J5_SPEC_SHEETS_META, true ) );
	$lines = array();
	foreach ( $rows as $r ) {
		$lines[] = trim( $r['label'] . ' | ' . $r['url'] . ( '' !== $r['note'] ? ' | ' . $r['note'] : '' ), ' |' );
	}
	?>
	<p style="margin-top:0">One per line: <code>Label | https://url.pdf | note</code></p>
	<textarea name="j5_spec_sheets" rows="5" style="width:100%;font-family:monospace;font-size:11px"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
	<p class="description">Files live in the B2 Media bucket (spec-sheets/). The dealer portal overwrites this when it publishes the product. https links only.</p>
	<?php
}

add_action( 'save_post_product', function ( $post_id ) {
	if ( ! isset( $_POST['j5_spec_sheets_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['j5_spec_sheets_nonce'] ) ), 'j5_spec_sheets_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$raw  = isset( $_POST['j5_spec_sheets'] ) ? (string) wp_unslash( $_POST['j5_spec_sheets'] ) : '';
	$rows = array();
	foreach ( j5_spec_sheets_parse( $raw ) as $r ) {
		$url = esc_url_raw( trim( $r['url'] ) );
		if ( $url && 0 === stripos( $url, 'https://' ) ) {
			$rows[] = array(
				'label' => sanitize_text_field( $r['label'] ),
				'url'   => $url,
				'note'  => sanitize_text_field( $r['note'] ),
			);
		}
	}
	if ( $rows ) {
		update_post_meta( $post_id, J5_SPEC_SHEETS_META, wp_slash( wp_json_encode( $rows, JSON_UNESCAPED_SLASHES ) ) );
	} else {
		delete_post_meta( $post_id, J5_SPEC_SHEETS_META );
	}
} );
