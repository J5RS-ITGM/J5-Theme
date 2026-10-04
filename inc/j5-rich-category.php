<?php
/**
 * J5 Rich Category Layout
 *
 * A reusable, wp-admin-editable layout for product category pages. Turn it on
 * for any category at Products -> Categories -> (category) -> "Rich Category
 * Page", then fill in the sections. Empty sections don't render.
 *
 * What it adds to the normal J5 archive (woocommerce/archive-product.php):
 *
 *   Hero      — H1 override (title + accent word), eyebrow, chips. The hero
 *               intro paragraph is the category's native Description field.
 *   Tiles     — "Shop by" tiles linking to subcategories or any URL.
 *   (product toolbar, filters and grid are unchanged)
 *   Compare   — two-column comparison table.
 *   Checklist — numbered "how to choose" cards.
 *   Articles  — pinned posts + posts carrying a chosen tag.
 *   FAQ       — accordion + FAQPage JSON-LD schema.
 *   Agency    — optional quote band.
 *   Related   — chosen related categories (replaces "Explore more").
 *
 * The existing below-grid Buyer's Guide field (j5-category-content.php) still
 * renders if filled in.
 *
 * Storage: one term meta key, `j5rc`, holding an array. Starter content for
 * a category can be loaded once with WP-CLI:
 *
 *     wp eval 'j5rc_seed_category( "shields" );'
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5RC_META   = 'j5rc';
const J5RC_NONCE  = 'j5rc_nonce';
const J5RC_TILES  = 4;
const J5RC_ROWS   = 8;
const J5RC_STEPS  = 8;
const J5RC_FAQS   = 10;

/**
 * Taxonomies that get the rich layout: product categories and brands
 * (WooCommerce's built-in Products -> Brands, see inc/j5-brands.php).
 */
function j5rc_taxonomies() {
	return apply_filters( 'j5rc_taxonomies', array( 'product_cat', 'product_brand' ) );
}

/** True on a product category or brand archive. */
function j5rc_is_rich_archive() {
	foreach ( j5rc_taxonomies() as $tax ) {
		if ( 'product_cat' === $tax ? ( function_exists( 'is_product_category' ) && is_product_category() ) : is_tax( $tax ) ) {
			return true;
		}
	}
	return false;
}

/* =========================================================================
 * 1. DATA
 * ========================================================================= */

/**
 * Default (empty) shape for a category's rich-layout data.
 */
function j5rc_defaults() {
	return array(
		'enabled'        => 0,
		'title'          => '',
		'title_accent'   => '',
		'eyebrow'        => '',
		'chips'          => '',
		'tiles_label'    => '',
		'tiles_title'    => '',
		'tiles'          => array(),
		'cmp_label'      => '',
		'cmp_title'      => '',
		'cmp_intro'      => '',
		'cmp_col_a'      => '',
		'cmp_col_b'      => '',
		'cmp_rows'       => array(),
		'steps_label'    => '',
		'steps_title'    => '',
		'steps_intro'    => '',
		'steps'          => array(),
		'art_label'      => '',
		'art_title'      => '',
		'art_intro'      => '',
		'art_pinned'     => '',
		'art_tag'        => '',
		'art_count'      => 3,
		'faq_title'      => '',
		'faqs'           => array(),
		'agency_on'      => 0,
		'agency_title'   => '',
		'agency_text'    => '',
		'agency_btn'     => '',
		'agency_url'     => '',
		'related_title'  => '',
		'related'        => array(),
	);
}

/**
 * Saved data for a term, merged over defaults.
 *
 * @param int $term_id
 * @return array
 */
function j5rc_get( $term_id ) {
	$saved = get_term_meta( (int) $term_id, J5RC_META, true );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( j5rc_defaults(), $saved );
}

/**
 * Data for the category currently being viewed, or null when the rich layout
 * is off (or we're not on a product category).
 *
 * @return array|null
 */
function j5rc_current() {
	static $cache = false;
	if ( false !== $cache ) {
		return $cache;
	}
	$cache = null;
	if ( ! j5rc_is_rich_archive() ) {
		return $cache;
	}
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return $cache;
	}
	$data = j5rc_get( $term->term_id );
	if ( ! empty( $data['enabled'] ) ) {
		$data['_term'] = $term;
		$cache         = $data;
	}
	return $cache;
}

/** Split a comma-separated field into trimmed, non-empty items. */
function j5rc_list( $str ) {
	return array_values( array_filter( array_map( 'trim', explode( ',', (string) $str ) ), 'strlen' ) );
}

/** Rows with at least one non-empty value in the given keys. */
function j5rc_filled( $rows, $keys ) {
	$out = array();
	foreach ( (array) $rows as $row ) {
		foreach ( $keys as $k ) {
			if ( isset( $row[ $k ] ) && '' !== trim( (string) $row[ $k ] ) ) {
				$out[] = $row;
				break;
			}
		}
	}
	return $out;
}

/* =========================================================================
 * 2. ADMIN — fields on the product category edit screen
 * ========================================================================= */

add_action( 'init', function () {
	foreach ( j5rc_taxonomies() as $tax ) {
		add_action( $tax . '_edit_form_fields', 'j5rc_admin_fields', 20, 1 );
		add_action( 'edited_' . $tax, 'j5rc_save', 10, 1 );
	}
}, 100 );

function j5rc_admin_fields( $term ) {
	$d    = j5rc_get( $term->term_id );
	$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( is_wp_error( $cats ) ) {
		$cats = array();
	}
	wp_nonce_field( 'j5rc_save', J5RC_NONCE );

	$text = function ( $name, $value, $placeholder = '', $wide = true ) {
		printf(
			'<input type="text" name="j5rc[%1$s]" value="%2$s" placeholder="%3$s" class="%4$s" />',
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $placeholder ),
			$wide ? 'large-text' : 'regular-text'
		);
	};
	$area = function ( $name, $value, $rows = 3 ) {
		printf(
			'<textarea name="j5rc[%1$s]" rows="%3$d" class="large-text">%2$s</textarea>',
			esc_attr( $name ),
			esc_textarea( $value ),
			(int) $rows
		);
	};
	$cat_select = function ( $name, $selected ) use ( $cats ) {
		echo '<select name="j5rc[' . esc_attr( $name ) . ']"><option value="">— None (use URL) —</option>';
		foreach ( $cats as $c ) {
			printf( '<option value="%d"%s>%s</option>', (int) $c->term_id, selected( (int) $selected, (int) $c->term_id, false ), esc_html( wp_specialchars_decode( $c->name ) ) );
		}
		echo '</select>';
	};
	?>
	<tr class="form-field j5rc-wrap">
		<th scope="row"><label><?php echo 'product_brand' === $term->taxonomy ? esc_html__( 'Brand Page', 'astra-child' ) : esc_html__( 'Rich Category Page', 'astra-child' ); ?></label></th>
		<td>
			<style>
				.j5rc-wrap details{background:#fff;border:1px solid #dcdcde;margin:0 0 10px;padding:0 14px}
				.j5rc-wrap summary{cursor:pointer;font-weight:600;padding:12px 0}
				.j5rc-wrap .j5rc-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin:0 0 12px;padding:10px;background:#f6f7f7}
				.j5rc-wrap label.j5rc-l{display:block;font-weight:600;margin:10px 0 4px}
				.j5rc-wrap .description{margin:4px 0 10px}
				.j5rc-wrap select[multiple]{min-height:160px;width:100%}
			</style>

			<p><label><input type="checkbox" name="j5rc[enabled]" value="1" <?php checked( ! empty( $d['enabled'] ) ); ?> /> <strong><?php esc_html_e( 'Use the rich layout for this page', 'astra-child' ); ?></strong></label></p>
			<p class="description"><?php esc_html_e( 'Empty sections are hidden. The hero intro paragraph is the Description field above. HTML links are allowed in paragraph and answer fields.', 'astra-child' ); ?></p>

			<details open>
				<summary>Hero</summary>
				<label class="j5rc-l">Page title (H1)</label><?php $text( 'title', $d['title'], 'Ballistic' ); ?>
				<label class="j5rc-l">Title accent (colored word, follows title)</label><?php $text( 'title_accent', $d['title_accent'], 'Shields' ); ?>
				<p class="description">Leave both empty to use the category name.</p>
				<label class="j5rc-l">Eyebrow</label><?php $text( 'eyebrow', $d['eyebrow'], 'Handgun & Rifle Rated · NIJ 0108.01' ); ?>
				<label class="j5rc-l">Chips (comma separated)</label><?php $text( 'chips', $d['chips'], 'HighCom, Level IIIA, Viewport Options' ); ?>
			</details>

			<details>
				<summary>Shop-by tiles (up to <?php echo (int) J5RC_TILES; ?>)</summary>
				<label class="j5rc-l">Section label</label><?php $text( 'tiles_label', $d['tiles_label'], 'Shop by rating' ); ?>
				<label class="j5rc-l">Section title</label><?php $text( 'tiles_title', $d['tiles_title'], 'Choose your protection level' ); ?>
				<?php for ( $i = 0; $i < J5RC_TILES; $i++ ) :
					$t = isset( $d['tiles'][ $i ] ) ? $d['tiles'][ $i ] : array();
					$t = array_merge( array( 'cat' => '', 'url' => '', 'kicker' => '', 'title' => '', 'text' => '', 'chips' => '', 'cta' => '' ), $t );
				?>
					<div class="j5rc-row">
						<div><label class="j5rc-l">Tile <?php echo (int) ( $i + 1 ); ?> category</label><?php $cat_select( "tiles][$i][cat", $t['cat'] ); ?></div>
						<div><label class="j5rc-l">…or URL</label><?php $text( "tiles][$i][url", $t['url'], '/some-page/' ); ?></div>
						<div><label class="j5rc-l">Kicker</label><?php $text( "tiles][$i][kicker", $t['kicker'], '01 — Level IIIA' ); ?></div>
						<div><label class="j5rc-l">Title</label><?php $text( "tiles][$i][title", $t['title'], 'Handgun Rated Shields' ); ?></div>
						<div><label class="j5rc-l">Text</label><?php $text( "tiles][$i][text", $t['text'] ); ?></div>
						<div><label class="j5rc-l">Chips (comma separated)</label><?php $text( "tiles][$i][chips", $t['chips'] ); ?></div>
						<div><label class="j5rc-l">Button text</label><?php $text( "tiles][$i][cta", $t['cta'], 'Shop Handgun Rated' ); ?></div>
					</div>
				<?php endfor; ?>
				<p class="description">A tile pointing at a category that doesn't exist or has no products is hidden.</p>
			</details>

			<details>
				<summary>Comparison table (up to <?php echo (int) J5RC_ROWS; ?> rows)</summary>
				<label class="j5rc-l">Section label</label><?php $text( 'cmp_label', $d['cmp_label'] ); ?>
				<label class="j5rc-l">Section title</label><?php $text( 'cmp_title', $d['cmp_title'] ); ?>
				<label class="j5rc-l">Intro</label><?php $area( 'cmp_intro', $d['cmp_intro'], 2 ); ?>
				<div class="j5rc-row">
					<div><label class="j5rc-l">Column A heading</label><?php $text( 'cmp_col_a', $d['cmp_col_a'] ); ?></div>
					<div><label class="j5rc-l">Column B heading</label><?php $text( 'cmp_col_b', $d['cmp_col_b'] ); ?></div>
				</div>
				<?php for ( $i = 0; $i < J5RC_ROWS; $i++ ) :
					$r = isset( $d['cmp_rows'][ $i ] ) ? $d['cmp_rows'][ $i ] : array();
					$r = array_merge( array( 'label' => '', 'a' => '', 'b' => '' ), $r );
				?>
					<div class="j5rc-row">
						<div><label class="j5rc-l">Row <?php echo (int) ( $i + 1 ); ?> label</label><?php $text( "cmp_rows][$i][label", $r['label'] ); ?></div>
						<div><label class="j5rc-l">Column A</label><?php $text( "cmp_rows][$i][a", $r['a'] ); ?></div>
						<div><label class="j5rc-l">Column B</label><?php $text( "cmp_rows][$i][b", $r['b'] ); ?></div>
					</div>
				<?php endfor; ?>
			</details>

			<details>
				<summary>How-to-choose checklist (up to <?php echo (int) J5RC_STEPS; ?>)</summary>
				<label class="j5rc-l">Section label</label><?php $text( 'steps_label', $d['steps_label'] ); ?>
				<label class="j5rc-l">Section title</label><?php $text( 'steps_title', $d['steps_title'] ); ?>
				<label class="j5rc-l">Intro</label><?php $area( 'steps_intro', $d['steps_intro'], 2 ); ?>
				<?php for ( $i = 0; $i < J5RC_STEPS; $i++ ) :
					$s = isset( $d['steps'][ $i ] ) ? $d['steps'][ $i ] : array();
					$s = array_merge( array( 'title' => '', 'text' => '' ), $s );
				?>
					<div class="j5rc-row">
						<div><label class="j5rc-l">Item <?php echo (int) ( $i + 1 ); ?> title</label><?php $text( "steps][$i][title", $s['title'] ); ?></div>
						<div><label class="j5rc-l">Text</label><?php $area( "steps][$i][text", $s['text'], 2 ); ?></div>
					</div>
				<?php endfor; ?>
			</details>

			<details>
				<summary>Related articles</summary>
				<label class="j5rc-l">Section label</label><?php $text( 'art_label', $d['art_label'], 'From the J5 blog' ); ?>
				<label class="j5rc-l">Section title</label><?php $text( 'art_title', $d['art_title'], 'Ballistic shield guides' ); ?>
				<label class="j5rc-l">Intro</label><?php $area( 'art_intro', $d['art_intro'], 2 ); ?>
				<label class="j5rc-l">Pinned posts (post IDs or slugs, comma separated, shown first)</label><?php $text( 'art_pinned', $d['art_pinned'], 'ballistic-shields-the-complete-buyers-guide' ); ?>
				<label class="j5rc-l">Fill remaining slots with posts tagged (tag slug)</label><?php $text( 'art_tag', $d['art_tag'], 'ballistic-shields', false ); ?>
				<label class="j5rc-l">Number of articles</label>
				<input type="number" min="1" max="9" name="j5rc[art_count]" value="<?php echo (int) $d['art_count']; ?>" class="small-text" />
			</details>

			<details>
				<summary>FAQ (up to <?php echo (int) J5RC_FAQS; ?>) — also output as FAQ schema</summary>
				<label class="j5rc-l">Section title</label><?php $text( 'faq_title', $d['faq_title'], 'Ballistic shield questions' ); ?>
				<?php for ( $i = 0; $i < J5RC_FAQS; $i++ ) :
					$f = isset( $d['faqs'][ $i ] ) ? $d['faqs'][ $i ] : array();
					$f = array_merge( array( 'q' => '', 'a' => '' ), $f );
				?>
					<div class="j5rc-row">
						<div><label class="j5rc-l">Question <?php echo (int) ( $i + 1 ); ?></label><?php $text( "faqs][$i][q", $f['q'] ); ?></div>
						<div><label class="j5rc-l">Answer</label><?php $area( "faqs][$i][a", $f['a'], 3 ); ?></div>
					</div>
				<?php endfor; ?>
			</details>

			<details>
				<summary>Agency band</summary>
				<p><label><input type="checkbox" name="j5rc[agency_on]" value="1" <?php checked( ! empty( $d['agency_on'] ) ); ?> /> Show agency band</label></p>
				<label class="j5rc-l">Title</label><?php $text( 'agency_title', $d['agency_title'], 'Outfitting a team?' ); ?>
				<label class="j5rc-l">Text</label><?php $area( 'agency_text', $d['agency_text'], 2 ); ?>
				<div class="j5rc-row">
					<div><label class="j5rc-l">Button text</label><?php $text( 'agency_btn', $d['agency_btn'], 'Request a quote' ); ?></div>
					<div><label class="j5rc-l">Button URL</label><?php $text( 'agency_url', $d['agency_url'], '/contact-us/' ); ?></div>
				</div>
			</details>

			<details>
				<summary>Related categories</summary>
				<label class="j5rc-l">Section title</label><?php $text( 'related_title', $d['related_title'], 'Related categories' ); ?>
				<label class="j5rc-l">Categories (Ctrl/Cmd-click to pick several)</label>
				<select name="j5rc[related][]" multiple>
					<?php foreach ( $cats as $c ) : ?>
						<option value="<?php echo (int) $c->term_id; ?>" <?php selected( in_array( (int) $c->term_id, array_map( 'intval', (array) $d['related'] ), true ) ); ?>><?php echo esc_html( wp_specialchars_decode( $c->name ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">Empty = the standard "Explore more" categories strip.</p>
			</details>
		</td>
	</tr>
	<?php
}


function j5rc_save( $term_id ) {
	if ( ! isset( $_POST[ J5RC_NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ J5RC_NONCE ] ) ), 'j5rc_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}
	$in = isset( $_POST['j5rc'] ) && is_array( $_POST['j5rc'] ) ? wp_unslash( $_POST['j5rc'] ) : array();
	update_term_meta( $term_id, J5RC_META, j5rc_sanitize( $in ) );
}

/**
 * Sanitize raw input into the stored shape. Plain fields are text; intro,
 * paragraph and answer fields allow post HTML (links, strong, em).
 *
 * @param array $in
 * @return array
 */
function j5rc_sanitize( $in ) {
	$t  = function ( $v ) { return sanitize_text_field( (string) $v ); };
	$h  = function ( $v ) { return wp_kses_post( trim( (string) $v ) ); };
	$g  = function ( $k ) use ( $in ) { return isset( $in[ $k ] ) ? $in[ $k ] : ''; };
	$ar = function ( $k ) use ( $in ) { return isset( $in[ $k ] ) && is_array( $in[ $k ] ) ? $in[ $k ] : array(); };

	$out = array(
		'enabled'       => empty( $in['enabled'] ) ? 0 : 1,
		'title'         => $t( $g( 'title' ) ),
		'title_accent'  => $t( $g( 'title_accent' ) ),
		'eyebrow'       => $t( $g( 'eyebrow' ) ),
		'chips'         => $t( $g( 'chips' ) ),
		'tiles_label'   => $t( $g( 'tiles_label' ) ),
		'tiles_title'   => $t( $g( 'tiles_title' ) ),
		'tiles'         => array(),
		'cmp_label'     => $t( $g( 'cmp_label' ) ),
		'cmp_title'     => $t( $g( 'cmp_title' ) ),
		'cmp_intro'     => $h( $g( 'cmp_intro' ) ),
		'cmp_col_a'     => $t( $g( 'cmp_col_a' ) ),
		'cmp_col_b'     => $t( $g( 'cmp_col_b' ) ),
		'cmp_rows'      => array(),
		'steps_label'   => $t( $g( 'steps_label' ) ),
		'steps_title'   => $t( $g( 'steps_title' ) ),
		'steps_intro'   => $h( $g( 'steps_intro' ) ),
		'steps'         => array(),
		'art_label'     => $t( $g( 'art_label' ) ),
		'art_title'     => $t( $g( 'art_title' ) ),
		'art_intro'     => $h( $g( 'art_intro' ) ),
		'art_pinned'    => $t( $g( 'art_pinned' ) ),
		'art_tag'       => sanitize_title( (string) $g( 'art_tag' ) ),
		'art_count'     => max( 1, min( 9, (int) $g( 'art_count' ) ) ),
		'faq_title'     => $t( $g( 'faq_title' ) ),
		'faqs'          => array(),
		'agency_on'     => empty( $in['agency_on'] ) ? 0 : 1,
		'agency_title'  => $t( $g( 'agency_title' ) ),
		'agency_text'   => $h( $g( 'agency_text' ) ),
		'agency_btn'    => $t( $g( 'agency_btn' ) ),
		'agency_url'    => esc_url_raw( (string) $g( 'agency_url' ) ),
		'related_title' => $t( $g( 'related_title' ) ),
		'related'       => array_values( array_filter( array_map( 'absint', $ar( 'related' ) ) ) ),
	);

	foreach ( array_slice( $ar( 'tiles' ), 0, J5RC_TILES ) as $r ) {
		$out['tiles'][] = array(
			'cat'    => isset( $r['cat'] ) ? absint( $r['cat'] ) : 0,
			'url'    => isset( $r['url'] ) ? esc_url_raw( (string) $r['url'] ) : '',
			'kicker' => $t( isset( $r['kicker'] ) ? $r['kicker'] : '' ),
			'title'  => $t( isset( $r['title'] ) ? $r['title'] : '' ),
			'text'   => $t( isset( $r['text'] ) ? $r['text'] : '' ),
			'chips'  => $t( isset( $r['chips'] ) ? $r['chips'] : '' ),
			'cta'    => $t( isset( $r['cta'] ) ? $r['cta'] : '' ),
		);
	}
	foreach ( array_slice( $ar( 'cmp_rows' ), 0, J5RC_ROWS ) as $r ) {
		$out['cmp_rows'][] = array(
			'label' => $t( isset( $r['label'] ) ? $r['label'] : '' ),
			'a'     => $h( isset( $r['a'] ) ? $r['a'] : '' ),
			'b'     => $h( isset( $r['b'] ) ? $r['b'] : '' ),
		);
	}
	foreach ( array_slice( $ar( 'steps' ), 0, J5RC_STEPS ) as $r ) {
		$out['steps'][] = array(
			'title' => $t( isset( $r['title'] ) ? $r['title'] : '' ),
			'text'  => $h( isset( $r['text'] ) ? $r['text'] : '' ),
		);
	}
	foreach ( array_slice( $ar( 'faqs' ), 0, J5RC_FAQS ) as $r ) {
		$out['faqs'][] = array(
			'q' => $t( isset( $r['q'] ) ? $r['q'] : '' ),
			'a' => $h( isset( $r['a'] ) ? $r['a'] : '' ),
		);
	}
	return $out;
}

/* =========================================================================
 * 3. FRONT END — hooks fired by woocommerce/archive-product.php
 * ========================================================================= */

add_filter( 'body_class', function ( $classes ) {
	if ( j5rc_current() ) {
		$classes[] = 'j5-rich-cat';
	}
	return $classes;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! j5rc_current() ) {
		return;
	}
	$rel = 'assets/css/j5-rich-category.css';
	$abs = get_stylesheet_directory() . '/' . $rel;
	if ( file_exists( $abs ) ) {
		wp_enqueue_style( 'j5-rich-category', get_stylesheet_directory_uri() . '/' . $rel, array(), (string) filemtime( $abs ) );
	}
}, 30 );

/** H1: "Title <accent>". Returns HTML. */
add_filter( 'j5_archive_title_html', function ( $html ) {
	$d = j5rc_current();
	if ( ! $d || ( '' === $d['title'] && '' === $d['title_accent'] ) ) {
		return $html;
	}
	$out = esc_html( $d['title'] );
	if ( '' !== $d['title_accent'] ) {
		$out .= ( '' !== $d['title'] ? ' ' : '' ) . '<span class="j5rc-accent">' . esc_html( $d['title_accent'] ) . '</span>';
	}
	return $out;
} );

/** The rich layout puts the Description inside the hero, so skip the separate intro band. */
add_filter( 'j5_archive_show_intro', function ( $show ) {
	return j5rc_current() ? false : $show;
} );

/** The rich layout's Related section replaces "Explore more" when categories are chosen. */
add_filter( 'j5_archive_show_spotlight', function ( $show ) {
	$d = j5rc_current();
	return ( $d && ! empty( $d['related'] ) ) ? false : $show;
} );

/** Eyebrow above the H1. */
add_action( 'j5_archive_hero_before_title', function () {
	$d = j5rc_current();
	if ( ! $d || '' === $d['eyebrow'] ) {
		return;
	}
	echo '<div class="j5rc-eyebrow"><span class="dot" aria-hidden="true"></span>' . esc_html( $d['eyebrow'] ) . '</div>';
} );

/** Description + chips under the H1. */
add_action( 'j5_archive_hero_after_title', function () {
	$d = j5rc_current();
	if ( ! $d ) {
		return;
	}
	$desc = term_description( $d['_term']->term_id );
	if ( '' !== trim( (string) $desc ) ) {
		echo '<div class="j5rc-lead">' . wp_kses_post( $desc ) . '</div>';
	}
	$chips = j5rc_list( $d['chips'] );
	if ( $chips ) {
		echo '<div class="j5rc-chips">';
		foreach ( $chips as $c ) {
			echo '<span class="j5rc-chip">' . esc_html( $c ) . '</span>';
		}
		echo '</div>';
	}
} );

/** Tiles, between hero and product toolbar. */
add_action( 'j5_archive_before_toolbar', 'j5rc_render_tiles' );

function j5rc_render_tiles() {
	$d = j5rc_current();
	if ( ! $d ) {
		return;
	}
	$tiles = array();
	foreach ( j5rc_filled( $d['tiles'], array( 'title', 'cat', 'url' ) ) as $t ) {
		$url = '';
		if ( ! empty( $t['cat'] ) ) {
			$term = get_term( (int) $t['cat'], 'product_cat' );
			if ( ! $term || is_wp_error( $term ) || (int) $term->count < 1 ) {
				continue; // missing or empty category: hide the tile
			}
			$url = get_term_link( $term );
			if ( '' === $t['title'] ) {
				$t['title'] = wp_specialchars_decode( $term->name );
			}
		} elseif ( ! empty( $t['url'] ) ) {
			$url = $t['url'];
		}
		if ( ! $url || is_wp_error( $url ) || '' === $t['title'] ) {
			continue;
		}
		$t['href'] = $url;
		$tiles[]   = $t;
	}
	if ( ! $tiles ) {
		return;
	}
	?>
	<section class="j5rc-section j5rc-tiles">
		<div class="j5-container">
			<?php j5rc_head( $d['tiles_label'], $d['tiles_title'] ); ?>
			<div class="j5rc-tile-grid">
				<?php foreach ( $tiles as $t ) : ?>
					<a class="j5rc-tile" href="<?php echo esc_url( $t['href'] ); ?>">
						<?php if ( $t['kicker'] ) : ?><span class="j5rc-mono j5rc-gold"><?php echo esc_html( $t['kicker'] ); ?></span><?php endif; ?>
						<span class="j5rc-tile-title"><?php echo esc_html( $t['title'] ); ?></span>
						<?php if ( $t['text'] ) : ?><span class="j5rc-tile-text"><?php echo esc_html( $t['text'] ); ?></span><?php endif; ?>
						<?php $tc = j5rc_list( $t['chips'] ); if ( $tc ) : ?>
							<span class="j5rc-chips"><?php foreach ( $tc as $c ) : ?><span class="j5rc-chip"><?php echo esc_html( $c ); ?></span><?php endforeach; ?></span>
						<?php endif; ?>
						<span class="j5rc-tile-cta"><?php echo esc_html( $t['cta'] ? $t['cta'] : 'Shop now' ); ?> &rarr;</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/** Everything below the product grid. */
add_action( 'j5_archive_after_grid', 'j5rc_render_compare', 10 );
add_action( 'j5_archive_after_grid', 'j5rc_render_steps', 20 );
add_action( 'j5_archive_after_grid', 'j5rc_render_articles', 30 );
add_action( 'j5_archive_after_grid', 'j5rc_render_faq', 40 );
add_action( 'j5_archive_after_grid', 'j5rc_render_agency', 50 );
add_action( 'j5_archive_after_grid', 'j5rc_render_related', 60 );

/** Section label + title + optional intro. */
function j5rc_head( $label, $title, $intro = '' ) {
	if ( $label ) {
		echo '<div class="j5rc-label">// ' . esc_html( $label ) . '</div>';
	}
	if ( $title ) {
		echo '<h2 class="j5rc-h2">' . esc_html( $title ) . '</h2>';
	}
	if ( $intro ) {
		echo '<div class="j5rc-sub">' . wp_kses_post( wpautop( $intro ) ) . '</div>';
	}
}

function j5rc_render_compare() {
	$d = j5rc_current();
	if ( ! $d ) {
		return;
	}
	$rows = j5rc_filled( $d['cmp_rows'], array( 'label', 'a', 'b' ) );
	if ( ! $rows ) {
		return;
	}
	?>
	<section class="j5rc-section j5rc-alt">
		<div class="j5-container">
			<?php j5rc_head( $d['cmp_label'], $d['cmp_title'], $d['cmp_intro'] ); ?>
			<div class="j5rc-table-wrap">
				<table class="j5rc-table">
					<?php if ( $d['cmp_col_a'] || $d['cmp_col_b'] ) : ?>
						<thead><tr><td></td><th scope="col"><?php echo esc_html( $d['cmp_col_a'] ); ?></th><th scope="col"><?php echo esc_html( $d['cmp_col_b'] ); ?></th></tr></thead>
					<?php endif; ?>
					<tbody>
						<?php foreach ( $rows as $r ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $r['label'] ); ?></th>
								<td data-label="<?php echo esc_attr( $d['cmp_col_a'] ); ?>"><?php echo wp_kses_post( $r['a'] ); ?></td>
								<td data-label="<?php echo esc_attr( $d['cmp_col_b'] ); ?>"><?php echo wp_kses_post( $r['b'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php
}

function j5rc_render_steps() {
	$d = j5rc_current();
	if ( ! $d ) {
		return;
	}
	$steps = j5rc_filled( $d['steps'], array( 'title', 'text' ) );
	if ( ! $steps ) {
		return;
	}
	?>
	<section class="j5rc-section">
		<div class="j5-container">
			<?php j5rc_head( $d['steps_label'], $d['steps_title'], $d['steps_intro'] ); ?>
			<div class="j5rc-step-grid">
				<?php foreach ( $steps as $i => $s ) : ?>
					<div class="j5rc-step">
						<span class="j5rc-step-num"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<?php if ( $s['title'] ) : ?><h3><?php echo esc_html( $s['title'] ); ?></h3><?php endif; ?>
						<?php if ( $s['text'] ) : ?><div class="j5rc-step-text"><?php echo wp_kses_post( wpautop( $s['text'] ) ); ?></div><?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Pinned posts (IDs or slugs, in order) then newest posts with the chosen tag.
 *
 * @return WP_Post[]
 */
function j5rc_get_articles( $d ) {
	$limit = max( 1, (int) $d['art_count'] );
	$posts = array();
	$ids   = array();

	foreach ( j5rc_list( $d['art_pinned'] ) as $ref ) {
		$p = ctype_digit( $ref ) ? get_post( (int) $ref ) : get_page_by_path( sanitize_title( $ref ), OBJECT, 'post' );
		if ( $p instanceof WP_Post && 'publish' === $p->post_status && 'post' === $p->post_type && ! in_array( $p->ID, $ids, true ) ) {
			$posts[] = $p;
			$ids[]   = $p->ID;
		}
	}

	if ( count( $posts ) < $limit && $d['art_tag'] ) {
		$more = get_posts( array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit - count( $posts ),
			'tag'                 => $d['art_tag'],
			'post__not_in'        => $ids,
			'ignore_sticky_posts' => true,
		) );
		$posts = array_merge( $posts, $more );
	}
	return array_slice( $posts, 0, $limit );
}

function j5rc_render_articles() {
	$d = j5rc_current();
	if ( ! $d ) {
		return;
	}
	$posts = j5rc_get_articles( $d );
	if ( ! $posts ) {
		return;
	}
	$all_url = '';
	if ( $d['art_tag'] ) {
		$tag     = get_term_by( 'slug', $d['art_tag'], 'post_tag' );
		$all_url = ( $tag && ! is_wp_error( $tag ) ) ? get_term_link( $tag ) : '';
	}
	?>
	<section class="j5rc-section j5rc-articles">
		<div class="j5-container">
			<div class="j5rc-head-row">
				<div><?php j5rc_head( $d['art_label'], $d['art_title'], $d['art_intro'] ); ?></div>
				<?php if ( $all_url && ! is_wp_error( $all_url ) ) : ?>
					<a class="j5rc-btn j5rc-btn-ghost" href="<?php echo esc_url( $all_url ); ?>">All articles &rarr;</a>
				<?php endif; ?>
			</div>
			<div class="j5rc-art-grid">
				<?php foreach ( $posts as $p ) :
					$url     = get_permalink( $p );
					$title   = get_the_title( $p );
					$thumb   = get_the_post_thumbnail_url( $p, 'medium_large' );
					$excerpt = has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 22, '&hellip;' );
					$cats    = get_the_category( $p->ID );
				?>
					<article class="j5rc-art">
						<a class="j5rc-art-img" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
							<?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" /><?php else : ?><span>J5</span><?php endif; ?>
						</a>
						<div class="j5rc-art-body">
							<span class="j5rc-mono j5rc-gold"><?php echo esc_html( $cats ? $cats[0]->name : 'Article' ); ?></span>
							<h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
							<?php if ( $excerpt ) : ?><p><?php echo esc_html( wp_strip_all_tags( $excerpt ) ); ?></p><?php endif; ?>
							<a class="j5rc-mono j5rc-gold" href="<?php echo esc_url( $url ); ?>" aria-label="Read <?php echo esc_attr( $title ); ?>">Read &rarr;</a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

function j5rc_render_faq() {
	$d = j5rc_current();
	if ( ! $d ) {
		return;
	}
	$faqs = j5rc_filled( $d['faqs'], array( 'q' ) );
	if ( ! $faqs ) {
		return;
	}
	?>
	<section class="j5rc-section j5rc-faq">
		<div class="j5-container j5rc-narrow">
			<?php j5rc_head( 'FAQ', $d['faq_title'] ); ?>
			<div class="j5rc-faq-list">
				<?php foreach ( $faqs as $i => $f ) : ?>
					<details<?php echo 0 === $i ? ' open' : ''; ?>>
						<summary><span><?php echo esc_html( $f['q'] ); ?></span><span class="j5rc-faq-x" aria-hidden="true">+</span></summary>
						<div class="j5rc-faq-a"><?php echo wp_kses_post( wpautop( $f['a'] ) ); ?></div>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

function j5rc_render_agency() {
	$d = j5rc_current();
	if ( ! $d || empty( $d['agency_on'] ) || '' === $d['agency_title'] ) {
		return;
	}
	?>
	<section class="j5rc-section j5rc-agency-wrap">
		<div class="j5-container">
			<div class="j5rc-agency">
				<div>
					<div class="j5rc-label">// For agencies</div>
					<div class="j5rc-agency-title"><?php echo esc_html( $d['agency_title'] ); ?></div>
					<?php if ( $d['agency_text'] ) : ?><div class="j5rc-agency-text"><?php echo wp_kses_post( wpautop( $d['agency_text'] ) ); ?></div><?php endif; ?>
				</div>
				<?php if ( $d['agency_btn'] && $d['agency_url'] ) : ?>
					<a class="j5rc-btn j5rc-btn-gold" href="<?php echo esc_url( $d['agency_url'] ); ?>"><?php echo esc_html( $d['agency_btn'] ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
}

function j5rc_render_related() {
	$d = j5rc_current();
	if ( ! $d || empty( $d['related'] ) ) {
		return;
	}
	$terms = array();
	foreach ( $d['related'] as $id ) {
		$term = get_term( (int) $id, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) && (int) $term->count > 0 ) {
			$terms[] = $term;
		}
	}
	if ( ! $terms ) {
		return;
	}
	?>
	<section class="j5rc-section j5rc-alt">
		<div class="j5-container">
			<?php j5rc_head( 'Pairs well with', $d['related_title'] ? $d['related_title'] : 'Related categories' ); ?>
			<div class="j5rc-rel-grid">
				<?php foreach ( $terms as $term ) : ?>
					<a class="j5rc-rel" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( wp_specialchars_decode( $term->name ) ); ?> <span aria-hidden="true">&rarr;</span></a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/** FAQPage schema for the questions shown on the page. */
add_action( 'wp_head', function () {
	$d = j5rc_current();
	if ( ! $d ) {
		return;
	}
	$items = array();
	foreach ( j5rc_filled( $d['faqs'], array( 'q' ) ) as $f ) {
		if ( '' === trim( wp_strip_all_tags( $f['a'] ) ) ) {
			continue;
		}
		$items[] = array(
			'@type'          => 'Question',
			'name'           => $f['q'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_kses( $f['a'], array( 'a' => array( 'href' => array() ), 'strong' => array(), 'em' => array() ) ) ),
		);
	}
	if ( ! $items ) {
		return;
	}
	$schema = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items );
	echo "<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
}, 20 );

/* =========================================================================
 * 4. STARTER CONTENT
 * ========================================================================= */

/**
 * Load starter content for a category, by slug. Only fills a category that has
 * no rich-layout data yet, unless $force is true. Run once over SSH:
 *
 *     wp eval 'j5rc_seed_category( "shields" );'
 *
 * @return string Result message.
 */
function j5rc_seed_category( $slug, $force = false, $taxonomy = 'product_cat' ) {
	$seeds = 'product_brand' === $taxonomy ? apply_filters( 'j5rc_brand_seeds', array() ) : j5rc_seeds();
	if ( ! isset( $seeds[ $slug ] ) ) {
		return "No starter content for '$slug'.";
	}
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( ! $term ) {
		return "'$slug' not found in $taxonomy.";
	}
	$existing = get_term_meta( $term->term_id, J5RC_META, true );
	if ( ! empty( $existing ) && ! $force ) {
		return "'$slug' already has rich-layout data; pass true as the second argument to overwrite.";
	}
	$seed = $seeds[ $slug ];

	// Resolve category slugs to IDs (tiles + related).
	$id_for = function ( $s ) {
		$t = get_term_by( 'slug', $s, 'product_cat' );
		return $t ? (int) $t->term_id : 0;
	};
	$seed['tiles'] = isset( $seed['tiles'] ) ? $seed['tiles'] : array();
	foreach ( $seed['tiles'] as &$tile ) {
		if ( ! empty( $tile['cat_slug'] ) ) {
			$tile['cat'] = $id_for( $tile['cat_slug'] );
		}
		unset( $tile['cat_slug'] );
	}
	unset( $tile );
	$seed['related'] = array_values( array_filter( array_map( $id_for, isset( $seed['related_slugs'] ) ? $seed['related_slugs'] : array() ) ) );
	unset( $seed['related_slugs'] );

	$description = isset( $seed['_description'] ) ? $seed['_description'] : '';
	unset( $seed['_description'] );

	update_term_meta( $term->term_id, J5RC_META, j5rc_sanitize( $seed ) );
	if ( $description && ( $force || '' === trim( $term->description ) ) ) {
		wp_update_term( $term->term_id, $taxonomy, array( 'description' => $description ) );
	}
	return "Starter content loaded for '$slug' ($taxonomy).";
}

function j5rc_seeds() {
	return array(
		'shields' => array(
			'enabled'       => 1,
			'title'         => 'Ballistic',
			'title_accent'  => 'Shields',
			'eyebrow'       => 'Handgun & Rifle Rated · NIJ 0108.01',
			'chips'         => 'HighCom, United Shield International, Level IIIA, Level III / III+, Viewport Options',
			'_description'  => 'Handheld ballistic shields for patrol, entry teams, and active threat response. Choose <strong>handgun rated Level IIIA</strong> shields for lighter one-handed carry, or <strong>rifle rated Level III and III+</strong> shields when long guns are the threat. HighCom and United Shield International, with viewport, size, and curve options. Not sure which you need? Start with our <a href="/ballistic-shields-the-complete-buyers-guide/">ballistic shield buyer\'s guide</a>.',

			'tiles_label'   => 'Shop by rating',
			'tiles_title'   => 'Choose your protection level',
			'tiles'         => array(
				array( 'cat_slug' => 'handgun-rated-shields', 'kicker' => '01 — Level IIIA', 'title' => 'Handgun Rated Shields', 'text' => 'Lightest option. Built for one-handed carry with a sidearm on patrol, warrant service, and building searches.', 'chips' => 'Patrol, Lightweight, IIIA', 'cta' => 'Shop Handgun Rated' ),
				array( 'cat_slug' => 'rifle-rated-shields', 'kicker' => '02 — Level III / III+', 'title' => 'Rifle Rated Shields', 'text' => 'Stops common rifle threats for active shooter response and high-risk entry. Heavier, so plan for size and carry.', 'chips' => 'Active Threat, Entry, III / III+', 'cta' => 'Shop Rifle Rated' ),
				array( 'cat_slug' => 'shield-accessories', 'kicker' => '03 — Add-ons', 'title' => 'Shield Accessories', 'text' => 'Shield lights, handles, carry bags, covers, and training shields to set up and maintain your shield.', 'chips' => 'Lights, Covers, Training', 'cta' => 'Shop Accessories' ),
			),

			'cmp_label'     => 'Handgun vs rifle rated',
			'cmp_title'     => 'Which ballistic shield level do you need?',
			'cmp_intro'     => 'The biggest decision is the threat you need to stop. Higher protection always adds weight, so match the rating to the mission rather than defaulting to the heaviest shield.',
			'cmp_col_a'     => 'Handgun Rated (Level IIIA)',
			'cmp_col_b'     => 'Rifle Rated (Level III / III+)',
			'cmp_rows'      => array(
				array( 'label' => 'Stops', 'a' => 'Common handgun rounds, including 9mm and .44 Magnum at NIJ test velocities', 'b' => 'Common rifle rounds such as 7.62x51 M80 ball. III+ models add manufacturer-tested special threats' ),
				array( 'label' => 'Weight', 'a' => 'Lighter. Realistic for one-handed carry with a sidearm', 'b' => 'Heavier at the same size. Plan for shorter carries or a rolling option' ),
				array( 'label' => 'Best for', 'a' => 'Patrol, warrant service, building searches, school resource officers', 'b' => 'Active shooter response, high-risk entry, rifle threat callouts' ),
				array( 'label' => 'Typical sizes', 'a' => '18x24 to 24x48, with or without viewport', 'b' => 'Similar sizes; smaller sizes keep weight manageable' ),
				array( 'label' => 'Shop', 'a' => '<a href="/handgun-rated-shields/">Handgun rated shields &rarr;</a>', 'b' => '<a href="/rifle-rated-shields/">Rifle rated shields &rarr;</a>' ),
			),

			'steps_label'   => 'Buying checklist',
			'steps_title'   => 'How to choose a ballistic shield',
			'steps_intro'   => 'After protection level, these are the choices that change how a shield works in the field. Our <a href="/ballistic-shields-the-complete-buyers-guide/">complete buyer\'s guide</a> covers each one in depth.',
			'steps'         => array(
				array( 'title' => 'Size and coverage', 'text' => 'Larger shields protect more of the body but cost mobility and add weight. 20x34 is a common patrol balance; 24x48 covers more for entry stacks.' ),
				array( 'title' => 'Weight and carry', 'text' => 'Check the spec sheet weight at the exact size and rating. A shield nobody wants to carry stays in the trunk.' ),
				array( 'title' => 'Viewport', 'text' => 'A ballistic viewport lets the carrier see while staying covered. It adds weight and cost, so decide if your team needs it.' ),
				array( 'title' => 'Flat or curved', 'text' => 'Single curve shields wrap the body for better coverage at the edges. Flat shields are simpler to store and stack.' ),
				array( 'title' => 'Handle and light setup', 'text' => 'Handle orientation and shield light mounts affect how the shield is held with a weapon. Try it with your duty setup.' ),
				array( 'title' => 'Training shields', 'text' => 'Non-rated training shields match the size and feel of the real one, so teams can train without wearing out duty equipment.' ),
			),

			'art_label'     => 'From the J5 blog',
			'art_title'     => 'Ballistic shield guides',
			'art_intro'     => 'Field notes and buying guidance on ballistic shields from the J5 team.',
			'art_pinned'    => 'ballistic-shields-the-complete-buyers-guide',
			'art_tag'       => 'ballistic-shields',
			'art_count'     => 3,

			'faq_title'     => 'Ballistic shield questions',
			'faqs'          => array(
				array( 'q' => 'What is a ballistic shield?', 'a' => 'A ballistic shield is a portable, handheld barrier rated to stop bullets. Officers and tactical teams use them to approach, search, and rescue while staying covered. They are tested to NIJ Standard 0108.01 for ballistic resistant materials.' ),
				array( 'q' => 'How much does a ballistic shield weigh?', 'a' => 'It depends on the size and protection level. Handgun rated patrol shields are the lightest, while a rifle rated shield of the same size weighs considerably more. Every product page lists the weight for each size.' ),
				array( 'q' => 'Can a police shield stop rifle rounds?', 'a' => 'Only if it is rifle rated. Level IIIA shields are built for handgun threats. Level III and III+ shields are built to stop common rifle rounds. Check the rating before you buy.' ),
				array( 'q' => 'Are riot shields bulletproof?', 'a' => 'No. Most riot shields are clear polycarbonate built to stop thrown objects and blunt impacts, not bullets. A ballistic shield carries an NIJ protection rating.' ),
				array( 'q' => 'Is there a Level 4 or Level 5 ballistic shield?', 'a' => 'NIJ does not define a Level 5. Level IV protects against armor-piercing rifle rounds but adds significant weight, which is why most handheld shields are Level IIIA or Level III/III+.' ),
				array( 'q' => 'Do you sell to agencies and departments?', 'a' => 'Yes. We accept agency purchase orders, quote multi-unit orders, and can help match shield sizes and ratings to your team. <a href="/contact-us/">Contact us</a> for a quote.' ),
			),

			'agency_on'     => 1,
			'agency_title'  => 'Outfitting a team?',
			'agency_text'   => 'Purchase orders accepted, quotes on multi-unit shield orders, and help choosing sizes and ratings for your team.',
			'agency_btn'    => 'Request a quote',
			'agency_url'    => '/contact-us/',

			'related_title' => 'Related categories',
			'related_slugs' => array( 'body-armor', 'helmets', 'plate-carriers', 'shield-accessories' ),
		),
	);
}
