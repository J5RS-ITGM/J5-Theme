<?php
/**
 * Links to the Armor Comparison Tool from the rest of the site
 * -----------------------------------------------------------
 *   j5_armor_compare_url( $hash )        tool URL ('' while the page isn't published)
 *   j5_home_compare_feature()            home page section (template-j5-home.php, after Shop by Category)
 *   j5_compare_product_link( $product )  "Compare this plate" callout at the end of a plate's Ballistics tab
 *   the_content filter                   callout in the HESCO plates guide and the NIJ levels article,
 *                                        placed before their "Common questions" section
 *
 * Page 97935 = the tool page (slug armor-comparison). Post 11262 = HESCO plates guide.
 *
 * @package j5-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'J5_COMPARE_PAGE_ID' ) ) define( 'J5_COMPARE_PAGE_ID', 97935 );
if ( ! defined( 'J5_HESCO_GUIDE_ID' ) )  define( 'J5_HESCO_GUIDE_ID', 11262 );

function j5_armor_compare_url( $hash = '' ) {
	$id = (int) apply_filters( 'j5_compare_page_id', J5_COMPARE_PAGE_ID );
	if ( ! $id || 'publish' !== get_post_status( $id ) ) return '';
	return get_permalink( $id ) . ( $hash ? '#' . ltrim( $hash, '#' ) : '' );
}

/** Published URL of the NIJ levels article (newest published post with "NIJ" in its title), cached for a day. */
function j5_nij_article_url() {
	$url = get_transient( 'j5_nij_article_url' );
	if ( false === $url ) {
		$url = '';
		$q = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 's' => 'NIJ', 'posts_per_page' => 10, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
		foreach ( $q as $p ) if ( false !== stripos( $p->post_title, 'NIJ' ) ) { $url = get_permalink( $p ); break; }
		set_transient( 'j5_nij_article_url', $url, DAY_IN_SECONDS );
	}
	return (string) apply_filters( 'j5_nij_article_url', $url );
}

/** Up to four Level IV plate listings for the home preview: featured makers first, one row per listing. */
function j5_home_compare_preview_rows() {
	if ( ! function_exists( 'j5_armor_ref_feed' ) ) return array();
	$feed = j5_armor_ref_feed( 'plate' );
	if ( ! $feed || empty( $feed['models'] ) ) return array();
	$featured = function_exists( 'j5_armor_ref_featured_makers' ) ? j5_armor_ref_featured_makers( 'plate' ) : array();
	$fams = array();
	foreach ( $feed['models'] as $m ) {
		$s = isset( $m['specs'] ) && is_array( $m['specs'] ) ? $m['specs'] : array();
		if ( 'IV' !== strtoupper( trim( (string) ( $s['level'] ?? '' ) ) ) ) continue;
		$k = function_exists( 'j5_armor_ref_fam_key' ) ? j5_armor_ref_fam_key( $m ) : $m['slug'];
		if ( ! isset( $fams[ $k ] ) ) $fams[ $k ] = array( 'name' => ! empty( $m['family'] ) ? $m['family'] : $m['model'], 'maker' => $m['manufacturer'], 'std' => (string) ( $s['standard'] ?? '' ), 'cert' => false, 'w' => array() );
		if ( ! empty( $s['nij_certified'] ) && ( true === $s['nij_certified'] || preg_match( '/^(yes|true|1)$/i', (string) $s['nij_certified'] ) ) ) $fams[ $k ]['cert'] = true;
		if ( isset( $s['weight_lb'] ) && is_numeric( $s['weight_lb'] ) ) $fams[ $k ]['w'][] = (float) $s['weight_lb'];
	}
	$rank = function ( $maker ) use ( $featured ) { foreach ( $featured as $i => $f ) if ( '' !== $f && false !== stripos( $maker, $f ) ) return $i; return 99; };
	uasort( $fams, function ( $a, $b ) use ( $rank ) { $r = $rank( $a['maker'] ) - $rank( $b['maker'] ); return $r ? $r : ( $b['cert'] - $a['cert'] ); } );
	$out = array();
	foreach ( $fams as $f ) {
		if ( ! $f['w'] ) continue;
		$lo = min( $f['w'] ); $hi = max( $f['w'] );
		$fmt = function ( $n ) { return rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' ); };
		$out[] = array( 'name' => $f['name'], 'maker' => $f['maker'], 'std' => trim( $f['std'] . ( $f['cert'] ? ' · Certified' : '' ), ' ·' ), 'w' => ( $lo === $hi ? $fmt( $lo ) : $fmt( $lo ) . '–' . $fmt( $hi ) ) . ' lb' );
		if ( count( $out ) >= 4 ) break;
	}
	return $out;
}

function j5_home_compare_feature() {
	$url = j5_armor_compare_url();
	if ( '' === $url ) return;
	$rows  = j5_home_compare_preview_rows();
	$guide = 'publish' === get_post_status( J5_HESCO_GUIDE_ID ) ? get_permalink( J5_HESCO_GUIDE_ID ) : '';
	$nij   = j5_nij_article_url();
	$check = '<span class="j5-hc-chk" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-10"/></svg></span>';
	?>
	<!-- === J5-HOME-COMPARE-START === -->
	<section class="j5-block j5-block-alt j5-home-compare" aria-labelledby="j5-home-compare-t">
		<div class="j5-container j5-home-compare__grid">
			<div class="j5-home-compare__copy">
				<h2 id="j5-home-compare-t" class="j5-block-title">Compare armor, <span class="accent">side by side.</span></h2>
				<p class="j5-home-compare__desc">A resource for anyone buying armor, not a sales pitch. We compiled the specs straight from each manufacturer's spec sheet, including many brands we don't sell. Filter by threat level, NIJ certification, cut and weight, open any plate to see its spec sheet, then line them up and compare up to six at a time.</p>
				<ul class="j5-home-compare__list">
					<li><?php echo $check; ?>Hesco, HighCom, RMA, LTC, USI, Safariland and many more</li>
					<li><?php echo $check; ?>NIJ certification checked against the NIJ Compliant Products List (CPL)</li>
					<li><?php echo $check; ?>Flags comparisons of unequal products, but allows comparison regardless</li>
					<li><?php echo $check; ?>See each manufacturer's spec sheet. If we don't have the sheet, the plate isn't in this tool</li>
				</ul>
				<div class="j5-home-compare__ctas">
					<a href="<?php echo esc_url( $url ); ?>" class="j5-btn j5-btn-primary">Compare plates <span class="arrow">&rarr;</span></a>
					<?php if ( $guide ) : ?><a href="<?php echo esc_url( $guide ); ?>" class="j5-btn j5-btn-ghost">HESCO plates guide</a><?php endif; ?>
				</div>
				<p class="j5-home-compare__note">Plates are live. Helmets, soft armor and ballistic shields are coming soon.<?php if ( $nij ) : ?> <a href="<?php echo esc_url( $nij ); ?>">What NIJ levels mean</a><?php endif; ?></p>
			</div>
			<?php if ( $rows ) : ?>
			<a class="j5-home-compare__preview" href="<?php echo esc_url( $url ); ?>" aria-label="Open the armor comparison tool">
				<span class="j5-home-compare__bar" aria-hidden="true"><i></i><i></i><i></i><span><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $url ) ) ); ?></span></span>
				<span class="j5-home-compare__body" aria-hidden="true">
					<span class="j5-home-compare__lv">Level IV <small>Full-size plates</small></span>
					<?php foreach ( $rows as $i => $r ) : ?>
						<span class="j5-home-compare__row">
							<span class="j5-home-compare__m"><b><?php echo esc_html( $r['name'] ); ?></b><em><?php echo esc_html( $r['maker'] ); ?></em></span>
							<span class="j5-home-compare__kv"><em>Standard</em><?php echo esc_html( $r['std'] ); ?></span>
							<span class="j5-home-compare__kv"><em>Weight</em><b><?php echo esc_html( $r['w'] ); ?></b></span>
							<span class="j5-home-compare__pill<?php echo 0 === $i ? ' is-on' : ''; ?>"><?php echo 0 === $i ? '&#10003; Comparing' : '+ Compare'; ?></span>
						</span>
					<?php endforeach; ?>
				</span>
			</a>
			<?php endif; ?>
		</div>
	</section>
	<!-- === J5-HOME-COMPARE-END === -->
	<?php
}

/** True when the product is a plate (any product category with "plate" in its name or slug). */
function j5_product_is_plate( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) return false;
	$id    = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	$terms = get_the_terms( $id, 'product_cat' );
	if ( ! $terms || is_wp_error( $terms ) ) return false;
	foreach ( $terms as $t ) if ( false !== stripos( $t->slug . ' ' . $t->name, 'plate' ) ) return true;
	return false;
}

function j5_compare_callout( $title, $text, $url, $cta ) {
	return '<aside class="j5-cmp-callout"><div class="j5-cmp-callout__t">' . esc_html( $title ) . '</div><p>' . esc_html( $text ) . '</p><a class="j5-cmp-callout__a" href="' . esc_url( $url ) . '">' . esc_html( $cta ) . ' <span aria-hidden="true">&rarr;</span></a></aside>';
}

/** End of a plate's Ballistics tab. */
function j5_compare_product_link( $product ) {
	if ( ! j5_product_is_plate( $product ) ) return '';
	$url = j5_armor_compare_url( 'j5ac-plate' );
	if ( '' === $url ) return '';
	return j5_compare_callout( 'Compare this plate with others', 'See how it stacks up against plates from other manufacturers: weight, coverage, sizes, certification and special threats, all from their spec sheets.', $url, 'Open the armor comparison tool' );
}

/** HESCO guide and NIJ article: callout before the "Common questions" H2 (or at the end). */
add_filter( 'the_content', function ( $html ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) return $html;
	$url = j5_armor_compare_url( 'j5ac-plate' );
	if ( '' === $url ) return $html;
	$id  = get_the_ID();
	$nij = j5_nij_article_url();
	$is_nij = $nij && untrailingslashit( get_permalink( $id ) ) === untrailingslashit( $nij );
	if ( J5_HESCO_GUIDE_ID !== (int) $id && ! $is_nij ) return $html;
	$box = $is_nij
		? j5_compare_callout( 'See NIJ levels plate by plate', 'Our armor comparison tool lists plates by NIJ level and standard, shows which are on the NIJ Compliant Products List, and lines them up side by side.', $url, 'Compare plates by NIJ level' )
		: j5_compare_callout( 'Compare HESCO with other brands', 'Line up HESCO plates against HighCom, RMA, LTC, USI, Safariland and more, using each manufacturer\'s own spec sheet.', $url, 'Open the armor comparison tool' );
	if ( preg_match( '#<h2[^>]*>[^<]*question#i', $html, $m, PREG_OFFSET_CAPTURE ) ) return substr( $html, 0, $m[0][1] ) . $box . substr( $html, $m[0][1] );
	return $html . $box;
}, 20 );

/** Callout styles: posts use j5-single.css, products j5-shop.css; this small block covers both. */
add_action( 'wp_head', function () {
	if ( ! ( is_singular( 'post' ) || ( function_exists( 'is_product' ) && is_product() ) ) ) return;
	echo '<style id="j5-cmp-callout">.j5-cmp-callout{margin:28px 0;padding:18px 20px;border:1px solid #4a3e22;border-left:3px solid #d4a044;border-radius:6px;background:#17140c;color:#e6e3da;overflow-wrap:anywhere}.j5-cmp-callout__t{font-family:"Barlow Condensed",sans-serif;font-weight:700;font-size:17px;letter-spacing:.08em;text-transform:uppercase;color:#f0bc5a;margin:0 0 6px}.j5-cmp-callout p{margin:0 0 10px;font-size:15px;line-height:1.5;color:#e6e3da}.j5-cmp-callout__a,.j5-cmp-callout__a:visited{font-family:"Barlow Condensed",sans-serif;font-weight:700;font-size:14px;letter-spacing:.12em;text-transform:uppercase;color:#f0bc5a;text-decoration:underline;text-underline-offset:3px}.j5-cmp-callout__a:hover{color:#fff}</style>' . "\n";
}, 30 );
