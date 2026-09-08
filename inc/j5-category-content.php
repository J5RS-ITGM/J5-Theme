<?php
/**
 * J5 Category Content
 *
 * Makes product-category pages editable without a page builder. Two regions:
 *
 *   1. ABOVE-GRID INTRO  — uses WooCommerce's native category "Description"
 *      field (term_description). Rendered below the hero, above the toolbar.
 *      Replaces the old hardcoded blurb; falls back to a generic line only
 *      when a category has no description.
 *
 *   2. BELOW-GRID BUYER'S GUIDE — a new term meta field, `j5_cat_guide`,
 *      added to the category Add/Edit screens in wp-admin. Rendered after the
 *      product grid, before the "Explore more" section. Long-form SEO content.
 *
 * Both are edited at: Products -> Categories -> (category) -> Description /
 * Buyer's Guide. HTML is allowed (wp_kses_post on output).
 *
 * The archive template (woocommerce/archive-product.php) calls the two render
 * helpers below at the appropriate insertion points.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Meta key for the below-grid buyer's guide content. */
const J5_CAT_GUIDE_META = 'j5_cat_guide';

/* ---------------------------------------------------------------------------
 * 1. ADMIN — add the "Buyer's Guide" field to the category edit screens
 * ------------------------------------------------------------------------- */

/**
 * Add-category screen (no term yet): render an empty textarea.
 */
add_action( 'product_cat_add_form_fields', function () {
	?>
	<div class="form-field term-j5-guide-wrap">
		<label for="j5_cat_guide"><?php esc_html_e( "Buyer's Guide (below product grid)", 'astra-child' ); ?></label>
		<?php
		wp_editor(
			'',
			'j5_cat_guide',
			array(
				'textarea_name' => J5_CAT_GUIDE_META,
				'textarea_rows' => 10,
				'media_buttons' => false,
				'teeny'         => true,
			)
		);
		?>
		<p class="description"><?php esc_html_e( 'Long-form content shown at the bottom of this category page (headings, lists, links). Great for SEO buyer guides.', 'astra-child' ); ?></p>
	</div>
	<?php
} );

/**
 * Edit-category screen: render the field pre-filled with the saved value,
 * inside the standard two-column table row Woo uses on edit screens.
 */
add_action( 'product_cat_edit_form_fields', function ( $term ) {
	$val = get_term_meta( $term->term_id, J5_CAT_GUIDE_META, true );
	?>
	<tr class="form-field term-j5-guide-wrap">
		<th scope="row"><label for="j5_cat_guide"><?php esc_html_e( "Buyer's Guide (below product grid)", 'astra-child' ); ?></label></th>
		<td>
			<?php
			wp_editor(
				$val,
				'j5_cat_guide',
				array(
					'textarea_name' => J5_CAT_GUIDE_META,
					'textarea_rows' => 12,
					'media_buttons' => false,
					'teeny'         => true,
				)
			);
			?>
			<p class="description"><?php esc_html_e( 'Long-form content shown at the bottom of this category page (headings, lists, links). Great for SEO buyer guides.', 'astra-child' ); ?></p>
		</td>
	</tr>
	<?php
}, 10, 1 );

/**
 * Save the field on both create and edit. Content is post-kses'd so editors
 * can use standard formatting/links but not unsafe markup.
 */
function j5_save_cat_guide_meta( $term_id ) {
	if ( ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}
	if ( isset( $_POST[ J5_CAT_GUIDE_META ] ) ) {
		$clean = wp_kses_post( wp_unslash( $_POST[ J5_CAT_GUIDE_META ] ) );
		update_term_meta( $term_id, J5_CAT_GUIDE_META, $clean );
	}
}
add_action( 'created_product_cat', 'j5_save_cat_guide_meta' );
add_action( 'edited_product_cat', 'j5_save_cat_guide_meta' );

/* ---------------------------------------------------------------------------
 * 2. FRONT-END — render helpers called by archive-product.php
 * ------------------------------------------------------------------------- */

/**
 * Above-grid intro. Prints the current category's native description when set,
 * otherwise a generic fallback line. Only renders on category/tag archives.
 *
 * @param string $fallback Generic copy shown when the term has no description.
 */
function j5_render_category_intro( $fallback = '' ) {
	if ( ! ( function_exists( 'is_product_category' ) && ( is_product_category() || is_product_tag() ) ) ) {
		// Shop root / other archives: show the fallback only if provided.
		if ( '' === $fallback ) {
			return;
		}
		$html = wpautop( wp_kses_post( $fallback ) );
		printf( '<section class="j5-cat-intro"><div class="j5-container j5-cat-intro-inner">%s</div></section>', $html );
		return;
	}

	$term = get_queried_object();
	$desc = ( $term && ! empty( $term->term_id ) ) ? term_description( $term->term_id ) : '';

	$body = ( '' !== trim( (string) $desc ) )
		? $desc                                   // term_description() is already HTML/wpautop'd
		: wpautop( wp_kses_post( $fallback ) );

	if ( '' === trim( (string) $body ) ) {
		return; // nothing to show and no fallback
	}

	printf(
		'<section class="j5-cat-intro"><div class="j5-container j5-cat-intro-inner">%s</div></section>',
		$body
	);
}

/**
 * Below-grid buyer's guide. Renders the j5_cat_guide term meta for the current
 * category, wrapped in the guide section markup. No output when empty.
 */
function j5_render_category_guide() {
	if ( ! ( function_exists( 'is_product_category' ) && ( is_product_category() || is_product_tag() ) ) ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term || empty( $term->term_id ) ) {
		return;
	}
	$raw = get_term_meta( $term->term_id, J5_CAT_GUIDE_META, true );
	if ( '' === trim( (string) $raw ) ) {
		return;
	}

	$body = wpautop( wp_kses_post( $raw ) );
	?>
	<section class="j5-cat-guide">
		<div class="j5-container j5-cat-guide-inner">
			<div class="j5-cat-guide-eyebrow">// BUYER'S GUIDE</div>
			<div class="j5-cat-guide-body"><?php echo $body; // already kses'd + wpautop'd ?></div>
		</div>
	</section>
	<?php
}
