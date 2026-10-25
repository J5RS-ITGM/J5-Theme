<?php
/**
 * J5 threat grid: [j5_threat_grid]
 *
 * One table for a whole plate lineup: rounds down the side, models across the
 * top, the minimum number of hits each model is rated to stop in each cell.
 * Built from the same DP-published `_j5_armor_spec` meta the product pages
 * use (inc/j5-threat-matrix.php), so it never drifts from the product data.
 *
 *   [j5_threat_grid brand="hesco-armor"]                     every product in the brand with a spec
 *   [j5_threat_grid products="hesco-armor-3612-ballistic-plate,hesco-4601-level-iv-plate-set"]
 *   [j5_threat_grid brand="hesco-armor" exclude="hesco-l210-special-threat-plate-set"]
 *
 * Cells show the best-case rating across sizes (usually Large / XL). The
 * caption says so; smaller sizes are often rated for fewer hits and the
 * product page has the per-size matrix.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'j5_threat_grid', function ( $atts ) {
	$atts = shortcode_atts( array( 'brand' => '', 'products' => '', 'exclude' => '' ), $atts, 'j5_threat_grid' );
	if ( ! function_exists( 'j5tm_spec' ) ) return '';

	$ids = array();
	if ( $atts['products'] ) {
		foreach ( array_filter( array_map( 'trim', explode( ',', $atts['products'] ) ) ) as $slug ) {
			$p = get_page_by_path( sanitize_title( $slug ), OBJECT, 'product' );
			if ( $p && 'publish' === $p->post_status ) $ids[] = $p->ID;
		}
	} elseif ( $atts['brand'] ) {
		$ids = get_posts( array(
			'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 50,
			'tax_query' => array( array( 'taxonomy' => 'product_brand', 'field' => 'slug', 'terms' => sanitize_title( $atts['brand'] ) ) ),
			'orderby' => 'title', 'order' => 'ASC',
		) );
	}
	$exclude = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $atts['exclude'] ) ) ) );

	$cols = array();   // model columns
	$rows = array();   // round => [ label, core, by model => hits ]
	$order = array();  // round keys in first-seen order, base rounds first
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( ! $post || in_array( $post->post_name, $exclude, true ) ) continue;
		$spec = j5tm_spec( $id );
		if ( ! $spec || empty( $spec['threats'] ) ) continue;

		$model = j5_threat_grid_model( $post->post_title, $spec );
		$level = isset( $spec['certification']['level'] ) ? (string) $spec['certification']['level'] : '';
		$cols[ $id ] = array( 'model' => $model, 'url' => get_permalink( $id ), 'level' => $level, 'maker' => isset( $spec['certification']['maker_label'] ) ? (string) $spec['certification']['maker_label'] : '' );

		foreach ( $spec['threats'] as $t ) {
			if ( ! is_array( $t ) || empty( $t['round'] ) ) continue;
			$key = strtolower( preg_replace( '/[^a-z0-9]+/i', '', (string) $t['round'] ) );
			if ( ! isset( $rows[ $key ] ) ) {
				$rows[ $key ] = array( 'round' => (string) $t['round'], 'core' => isset( $t['core'] ) ? (string) $t['core'] : '', 'base' => ! empty( $t['base'] ), 'hits' => array() );
				$order[] = $key;
			}
			$best = null;
			foreach ( (array) ( $t['shots'] ?? array() ) as $part => $n ) {
				if ( is_numeric( $n ) && ( null === $best || (float) $n > $best ) ) $best = (float) $n;
			}
			$rows[ $key ]['hits'][ $id ] = null === $best ? '•' : rtrim( rtrim( number_format( $best, 1, '.', '' ), '0' ), '.' );
		}
	}
	if ( ! $cols || ! $rows ) return '';

	// Base (certification) rounds first, then the rest in order seen.
	usort( $order, function ( $a, $b ) use ( $rows ) { return ( $rows[ $b ]['base'] ? 1 : 0 ) - ( $rows[ $a ]['base'] ? 1 : 0 ); } );

	j5_threat_grid_css();
	ob_start();
	?>
	<div class="j5tg">
		<div class="j5tg__scroll">
		<table class="j5tg__t">
			<caption class="j5tg__cap">Minimum hits each plate is rated to stop, best size per the manufacturer's datasheet. Smaller sizes are often rated for fewer; the product page has the per-size matrix.</caption>
			<thead><tr>
				<th scope="col" class="j5tg__round">Round</th>
				<?php foreach ( $cols as $c ) : ?>
					<th scope="col"><a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['model'] ); ?></a><?php if ( $c['level'] ) : ?><small><?php echo esc_html( ( $c['maker'] && 0 !== strcasecmp( $c['maker'], 'Level ' . $c['level'] ) ) ? $c['maker'] : 'Level ' . $c['level'] ); ?></small><?php endif; ?></th>
				<?php endforeach; ?>
			</tr></thead>
			<tbody>
			<?php foreach ( $order as $key ) : $r = $rows[ $key ]; ?>
				<tr<?php echo $r['base'] ? ' class="is-base"' : ''; ?>>
					<th scope="row" class="j5tg__round"><?php echo esc_html( $r['round'] ); ?><?php if ( $r['core'] ) : ?><small><?php echo esc_html( $r['core'] ); ?></small><?php endif; ?></th>
					<?php foreach ( $cols as $id => $c ) : $h = isset( $r['hits'][ $id ] ) ? $r['hits'][ $id ] : ''; ?>
						<td class="<?php echo '' === $h ? 'is-no' : 'is-yes'; ?>" data-label="<?php echo esc_attr( $c['model'] ); ?>"><?php echo '' === $h ? '<span aria-label="Not rated">—</span>' : esc_html( $h ); ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<div class="j5tg__m" aria-hidden="true">
			<?php foreach ( $cols as $id => $c ) : ?>
				<section class="j5tg__card">
					<h4><a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['model'] ); ?></a><?php if ( $c['level'] ) : ?><small><?php echo esc_html( ( $c['maker'] && 0 !== strcasecmp( $c['maker'], 'Level ' . $c['level'] ) ) ? $c['maker'] : 'Level ' . $c['level'] ); ?></small><?php endif; ?></h4>
					<ul>
					<?php $none = array(); foreach ( $order as $key ) : $r = $rows[ $key ];
						if ( ! isset( $r['hits'][ $id ] ) ) { $none[] = $r['round']; continue; } ?>
						<li<?php echo $r['base'] ? ' class="is-base"' : ''; ?>><span><?php echo esc_html( $r['round'] ); ?></span><b><?php echo esc_html( $r['hits'][ $id ] ); ?></b></li>
					<?php endforeach; ?>
					</ul>
					<?php if ( $none ) : ?><p class="j5tg__card-none">Not rated for <?php echo esc_html( implode( ', ', $none ) ); ?></p><?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
		<p class="j5tg__key">Numbers are hits stopped. A dot means rated, hit count not published. A dash means the manufacturer does not rate the plate against that round.</p>
	</div>
	<?php
	return ob_get_clean();
} );

/** Short model name for a column: spec model if present, else the model number from the title. */
function j5_threat_grid_model( $title, array $spec ) {
	if ( ! empty( $spec['model'] ) && is_string( $spec['model'] ) ) return $spec['model'];
	if ( preg_match( '/\b([A-Z]?\d{3,4}[A-Z]{0,2})\b/', $title, $m ) ) return $m[1];
	return wp_trim_words( $title, 3, '' );
}

function j5_threat_grid_css() {
	static $done = false;
	if ( $done ) return;
	$done = true;
	?>
	<style>
	.j5tg{margin:1.8em 0}
	.j5tg__scroll{overflow-x:auto}
	.j5tg__t{width:100%;border-collapse:separate;border-spacing:0;font-size:14px;font-family:'Barlow',sans-serif;color:var(--j5-ink,#eceae2)}
	.j5tg__cap{caption-side:top;text-align:left;padding:0 0 10px;font-size:13px;color:var(--j5-ink-dim,#a4a49a);line-height:1.45}
	.j5tg__t th,.j5tg__t td{padding:10px 12px;border-top:1px solid var(--j5-border,#23272f);text-align:center;vertical-align:middle;white-space:nowrap}
	.j5tg__t thead th{font-family:'Barlow Condensed',sans-serif;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:var(--j5-bone,#f5f1e8);font-weight:600;border-top:0;border-bottom:2px solid var(--j5-gold,#d4a044)}
	.j5tg__t thead th a{color:inherit;text-decoration:none;border-bottom:1px solid var(--j5-gold,#d4a044)}
	.j5tg__t thead th a:hover{color:var(--j5-gold,#d4a044)}
	.j5tg__t th small{display:block;font-size:11px;letter-spacing:.06em;color:var(--j5-ink-mute,#6e6e66);margin-top:3px;font-weight:500}
	.j5tg__round{text-align:left!important;position:sticky;left:0;background:var(--j5-bg,#0a0b0d);font-weight:600;color:var(--j5-ink,#eceae2);min-width:150px;box-shadow:1px 0 0 var(--j5-border,#23272f)}
	.j5tg__round small{text-transform:none;letter-spacing:0;font-family:'Barlow',sans-serif;font-weight:400}
	.j5tg__t td.is-yes{font-family:'Bebas Neue','Barlow Condensed',Impact,sans-serif;font-size:20px;letter-spacing:.03em;color:var(--j5-gold,#d4a044)}
	.j5tg__t td.is-no{color:var(--j5-ink-mute,#6e6e66)}
	.j5tg__t tbody tr:nth-child(even) td,.j5tg__t tbody tr:nth-child(even) th{background:rgba(255,255,255,.03)}
	.j5tg__t tr.is-base th.j5tg__round{color:var(--j5-gold,#d4a044)}
	.j5tg__t tbody tr:hover td,.j5tg__t tbody tr:hover th{background:rgba(212,160,68,.06)}
	.j5tg__key{margin:8px 0 0;font-size:12px;color:var(--j5-ink-mute,#6e6e66)}
	.j5tg__m{display:none}
	@media(max-width:640px){
	.j5tg__scroll{display:none}
	.j5tg__m{display:grid;gap:12px}
	.j5tg__card{background:var(--j5-bg-card,#15181d);border:1px solid var(--j5-border,#23272f);border-radius:8px;padding:0 0 10px;text-align:center}
	.j5tg__card h4{margin:0 0 6px;padding:12px 14px 10px;border-bottom:2px solid var(--j5-gold,#d4a044);font-family:'Bebas Neue','Barlow Condensed',Impact,sans-serif;font-weight:400;font-size:22px;letter-spacing:.03em}
	.j5tg__card h4 a{color:var(--j5-bone,#f5f1e8);text-decoration:none}
	.j5tg__card h4 small{display:block;font-family:'Barlow Condensed',sans-serif;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--j5-ink-mute,#6e6e66);margin-top:3px}
	.j5tg__card ul{list-style:none;margin:0;padding:0 14px}
	.j5tg__card li{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:7px 0;border-bottom:1px solid var(--j5-border,#23272f);font-size:14px;text-align:left}
	.j5tg__card li:last-child{border-bottom:0}
	.j5tg__card li.is-base span{color:var(--j5-gold,#d4a044)}
	.j5tg__card li b{font-family:'Bebas Neue','Barlow Condensed',Impact,sans-serif;font-weight:400;font-size:20px;letter-spacing:.03em;color:var(--j5-gold,#d4a044)}
	.j5tg__card-none{margin:8px 14px 0;font-size:12px;color:var(--j5-ink-mute,#6e6e66)}
	.j5tg__key{text-align:center}
	}
	</style>
	<?php
}
