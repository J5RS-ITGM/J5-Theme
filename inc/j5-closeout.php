<?php
/**
 * J5 "retired product" page treatment (Closeout / Discontinued).
 *
 * A product is retired when its fulfillment status (inc/j5-fulfillment.php)
 * is NOT buyable and is either the muted "discontinued" state or one of the
 * slugs below. Instead of a struck-through price, a "select options" banner
 * and a dead "Currently unavailable" button, the page shows:
 *
 *   - a clear "No longer carried" panel with the status label, a short
 *     explanation and two actions (shop the category / ask for a recommendation)
 *   - a full-width "Shop current <category>" row of in-stock alternatives
 *
 * Alternatives: the product's _j5_fulfillment_replaces IDs first, then the
 * best sellers in the same category (falling back to the parent category):
 * published, visible, purchasable, in stock, not retired themselves, and not
 * the same brand (we're steering away from it).
 *
 * The page stays indexable and its content/tabs are unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Status slugs treated as retired even if their visual state isn't "discontinued". */
function j5_retired_slugs() {
	return apply_filters( 'j5_retired_slugs', array( 'discontinued', 'closeout', 'close_out', 'retired' ) );
}

/** @param array|null $f Result of j5_get_fulfillment(). */
function j5_is_retired( $f ) {
	if ( ! is_array( $f ) || ! empty( $f['buyable'] ) ) return false;
	return 'discontinued' === $f['state'] || in_array( $f['type'], j5_retired_slugs(), true );
}

/** Deepest product category of a product (the one shown in the breadcrumb is usually this). */
function j5_closeout_category( $product ) {
	$terms = get_the_terms( $product->get_id(), 'product_cat' );
	if ( ! $terms || is_wp_error( $terms ) ) return null;
	$deepest = null; $depth = -1;
	foreach ( $terms as $t ) {
		if ( 'uncategorized' === $t->slug ) continue;
		$d = count( get_ancestors( $t->term_id, 'product_cat' ) );
		if ( $d > $depth ) { $deepest = $t; $depth = $d; }
	}
	return $deepest;
}

function j5_closeout_brand_ids( $product_id ) {
	$ids = wp_get_post_terms( $product_id, 'product_brand', array( 'fields' => 'ids' ) );
	return is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
}

/** Up to $limit in-stock alternatives. Cached 6 hours per product. */
function j5_closeout_recommendations( $product, $limit = 4 ) {
	$key = 'j5_closeout_recs_' . $product->get_id();
	$ids = get_transient( $key );
	if ( ! is_array( $ids ) ) {
		$ids      = array();
		$self     = $product->get_id();
		$brands   = j5_closeout_brand_ids( $self );
		$ok = function ( $p ) use ( $self, $brands ) {
			if ( ! $p || $p->get_id() === $self || 'publish' !== $p->get_status() || ! $p->is_visible() ) return false;
			if ( ! $p->is_purchasable() || ! $p->is_in_stock() ) return false;
			if ( function_exists( 'j5_get_fulfillment' ) && j5_is_retired( j5_get_fulfillment( $p ) ) ) return false;
			if ( $brands && array_intersect( $brands, j5_closeout_brand_ids( $p->get_id() ) ) ) return false;
			return true;
		};
		// 1) Explicit successors from the DP.
		if ( function_exists( 'j5_get_fulfillment' ) && ( $f = j5_get_fulfillment( $product ) ) && ! empty( $f['replaces'] ) ) {
			foreach ( $f['replaces'] as $rid ) {
				if ( count( $ids ) >= $limit ) break;
				if ( $ok( wc_get_product( $rid ) ) ) $ids[] = (int) $rid;
			}
		}
		// 2) Best sellers in the same category, then the parent category.
		$cat  = j5_closeout_category( $product );
		$cats = array();
		if ( $cat ) {
			$cats[] = $cat->slug;
			if ( $cat->parent && ( $parent = get_term( $cat->parent, 'product_cat' ) ) && ! is_wp_error( $parent ) ) $cats[] = $parent->slug;
		}
		foreach ( $cats as $slug ) {
			if ( count( $ids ) >= $limit ) break;
			$cands = wc_get_products( array(
				'category' => array( $slug ), 'status' => 'publish', 'stock_status' => 'instock',
				'limit' => 30, 'orderby' => 'popularity', 'order' => 'DESC', 'return' => 'objects',
			) );
			foreach ( $cands as $p ) {
				if ( count( $ids ) >= $limit ) break;
				if ( ! in_array( $p->get_id(), $ids, true ) && $ok( $p ) ) $ids[] = $p->get_id();
			}
		}
		set_transient( $key, $ids, 6 * HOUR_IN_SECONDS );
	}
	return array_values( array_filter( array_map( 'wc_get_product', $ids ) ) );
}

// Refresh recommendations when products change.
add_action( 'woocommerce_update_product', function () {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_j5\\_closeout\\_recs\\_%' OR option_name LIKE '\\_transient\\_timeout\\_j5\\_closeout\\_recs\\_%'" );
} );

/** Body class so the CSS can hide the buy box parts that don't apply. */
add_filter( 'body_class', function ( $classes ) {
	if ( function_exists( 'is_product' ) && is_product() && function_exists( 'j5_get_fulfillment' ) ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p && j5_is_retired( j5_get_fulfillment( $p ) ) ) $classes[] = 'j5-retired';
	}
	return $classes;
} );

/** Summary-column panel, rendered in place of price / stock / cart. */
function j5_render_closeout_panel( $product, $f ) {
	$cat   = j5_closeout_category( $product );
	$recs  = j5_closeout_recommendations( $product );
	$label = $f['label'] ? $f['label'] : 'No longer carried';
	?>
	<div class="j5-retired">
		<span class="j5-retired__badge"><?php echo esc_html( $label ); ?></span>
		<h2 class="j5-retired__title">We no longer carry this item</h2>
		<p class="j5-retired__text">This product has been retired from our lineup and can't be ordered.
			<?php if ( $recs ) : ?>We've picked current <?php echo $cat ? esc_html( strtolower( wp_specialchars_decode( $cat->name, ENT_QUOTES ) ) ) : 'options'; ?> we stock and stand behind below.<?php else : ?>Tell us what you need it for and we'll point you to a current option.<?php endif; ?>
		</p>
		<div class="j5-retired__actions">
			<?php if ( $recs ) : ?>
				<a class="j5-retired__btn is-primary" href="#j5-alternatives">See recommended alternatives</a>
			<?php elseif ( $cat ) : ?>
				<a class="j5-retired__btn is-primary" href="<?php echo esc_url( get_term_link( $cat ) ); ?>">Shop <?php echo esc_html( wp_specialchars_decode( $cat->name, ENT_QUOTES ) ); ?></a>
			<?php endif; ?>
			<a class="j5-retired__btn" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Ask for a recommendation</a>
		</div>
	</div>
	<?php
}

/** Full-width alternatives row, rendered between the product grid and the tabs. */
function j5_render_closeout_recs( $product ) {
	$recs = j5_closeout_recommendations( $product );
	if ( ! $recs ) return;
	$cat = j5_closeout_category( $product );
	$cat_name = $cat ? wp_specialchars_decode( $cat->name, ENT_QUOTES ) : '';
	?>
	<section class="j5-alts" id="j5-alternatives" aria-labelledby="j5-alts-h">
		<div class="j5-alts__head">
			<div>
				<span class="j5-alts__eyebrow">Recommended instead</span>
				<h2 class="j5-alts__h" id="j5-alts-h"><?php echo $cat_name ? 'Current ' . esc_html( $cat_name ) : 'Current alternatives'; ?></h2>
			</div>
			<?php if ( $cat ) : ?><a class="j5-alts__all" href="<?php echo esc_url( get_term_link( $cat ) ); ?>">View all <?php echo esc_html( $cat_name ); ?> &rarr;</a><?php endif; ?>
		</div>
		<div class="j5-alts__grid">
			<?php foreach ( $recs as $p ) :
				$img   = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : wc_placeholder_img_src();
				$brand = function_exists( 'j5_get_product_brand' ) ? j5_get_product_brand( $p ) : '';
				?>
				<a class="j5-alt" href="<?php echo esc_url( $p->get_permalink() ); ?>">
					<span class="j5-alt__img"><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $p->get_name() ); ?>" loading="lazy"></span>
					<span class="j5-alt__body">
						<?php if ( $brand ) : ?><span class="j5-alt__brand"><?php echo esc_html( $brand ); ?></span><?php endif; ?>
						<span class="j5-alt__name"><?php echo esc_html( $p->get_name() ); ?></span>
						<span class="j5-alt__row">
							<span class="j5-alt__price"><?php echo wp_kses_post( $p->get_price_html() ); ?></span>
							<span class="j5-alt__stock">In stock</span>
						</span>
						<span class="j5-alt__cta">View product &rarr;</span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
	?>
	<style>
	/* Hide buy-box parts that don't apply to a retired product. */
	body.j5-retired .j5-product-info > .j5-product-price,
	body.j5-retired .j5-product-info > .j5-product-stock,
	body.j5-retired .j5-product-info > .j5-cart-blocked,
	body.j5-retired .j5-product-info > .j5-product-trust,
	body.j5-retired .j5-product-info > .j5-discontinued-block,
	body.j5-retired .j5-quote-cta,
	body.j5-retired .j5-sticky-cart { display: none !important; }

	.j5-retired{margin:18px 0 22px;padding:22px 24px;background:var(--j5-bg-card,#15181d);border:1px solid var(--j5-border-hi,#333842);border-left:3px solid var(--j5-ink-mute,#6e6e66)}
	.j5-retired__badge{display:inline-block;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:var(--j5-ink-dim,#a4a49a);border:1px solid var(--j5-border-hi,#333842);padding:4px 10px;margin-bottom:12px}
	.j5-retired__title{font-family:'Bebas Neue',Impact,sans-serif;font-weight:400;font-size:30px;line-height:1.05;letter-spacing:.02em;color:var(--j5-ink,#eceae2);margin:0 0 8px;text-transform:uppercase}
	.j5-retired__text{color:var(--j5-ink-dim,#a4a49a);font-size:16px;line-height:1.55;margin:0 0 16px}
	.j5-retired__actions{display:flex;flex-wrap:wrap;gap:10px}
	.j5-retired__btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 20px;border:1px solid var(--j5-gold,#d4a044);color:var(--j5-gold,#d4a044);font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:15px;letter-spacing:.1em;text-transform:uppercase;text-decoration:none}
	.j5-retired__btn:hover{background:var(--j5-gold,#d4a044);color:#0a0b0d}
	.j5-retired__btn.is-primary{background:var(--j5-red,#c8102e);border-color:var(--j5-red,#c8102e);color:#fff}
	.j5-retired__btn.is-primary:hover{background:var(--j5-red-deep,#8a0a1d);border-color:var(--j5-red-deep,#8a0a1d)}

	.j5-alts{margin:48px 0 8px;padding-top:32px;border-top:1px solid var(--j5-border,#23272f);scroll-margin-top:90px}
	.j5-alts__head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:12px;margin-bottom:20px}
	.j5-alts__eyebrow{display:block;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:var(--j5-gold,#d4a044);margin-bottom:4px}
	.j5-alts__h{font-family:'Bebas Neue',Impact,sans-serif;font-weight:400;font-size:38px;line-height:1;color:var(--j5-ink,#eceae2);margin:0;text-transform:uppercase;letter-spacing:.02em}
	.j5-alts__all{color:var(--j5-gold,#d4a044);text-decoration:none;font-family:'Barlow Condensed',sans-serif;font-weight:600;letter-spacing:.08em;text-transform:uppercase;font-size:14px}
	.j5-alts__grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
	.j5-alt{display:flex;flex-direction:column;background:var(--j5-bg-card,#15181d);border:1px solid var(--j5-border,#23272f);text-decoration:none;transition:border-color .2s,transform .2s}
	.j5-alt:hover{border-color:var(--j5-gold,#d4a044);transform:translateY(-2px)}
	.j5-alt__img{display:block;aspect-ratio:1/1;background:var(--j5-bone,#f5f1e8);overflow:hidden}
	.j5-alt__img img{width:100%;height:100%;object-fit:contain;display:block}
	.j5-alt__body{display:flex;flex-direction:column;gap:6px;padding:14px 16px 16px;flex:1}
	.j5-alt__brand{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:var(--j5-gold,#d4a044)}
	.j5-alt__name{color:var(--j5-ink,#eceae2);font-weight:600;line-height:1.3;font-size:15px}
	.j5-alt__row{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:auto;padding-top:6px}
	.j5-alt__price{color:var(--j5-ink,#eceae2);font-weight:700}
	.j5-alt__price del{color:var(--j5-ink-mute,#6e6e66);font-weight:400;margin-right:4px}
	.j5-alt__stock{font-size:12px;color:#7fd17f;white-space:nowrap}
	.j5-alt__stock::before{content:'';display:inline-block;width:7px;height:7px;border-radius:50%;background:#4a8f5f;margin-right:6px;vertical-align:middle}
	.j5-alt__cta{color:var(--j5-gold,#d4a044);font-size:13px;font-family:'Barlow Condensed',sans-serif;font-weight:600;letter-spacing:.08em;text-transform:uppercase;margin-top:6px}
	@media (max-width:1000px){.j5-alts__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
	@media (max-width:520px){.j5-alts__h{font-size:30px}.j5-alt__name{font-size:14px}}
	</style>
	<?php
}, 30 );
