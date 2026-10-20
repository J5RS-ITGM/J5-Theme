<?php
/**
 * J5 armor threat matrix: [j5_threat_matrix]
 *
 * Renders the DP-verified armor spec pushed as parent product meta
 * `_j5_armor_spec` (JSON string). Empty / missing / bad JSON renders nothing.
 * Values are shown exactly as given: nothing is computed or converted.
 *
 *   [j5_threat_matrix]            current product
 *   [j5_threat_matrix id="123"]   a specific product
 *
 * On product pages the spec is split across two tabs (woocommerce/single-product.php):
 *   Ballistics tab      j5tm_render_ballistics(): protection tiles + "What Does
 *                       the {model} Stop?" threat table. Tab only exists when the
 *                       product has certification or threat data.
 *   Specifications tab  j5tm_render_sizes(): "What Sizes Does the {model} Come
 *                       In?" table, above the general specs.
 * The shortcode still renders everything in one block.
 *
 * Variable products: the column whose part number matches the selected
 * variation SKU is highlighted (case-insensitive, dashes/spaces ignored; the
 * SKU may end with the part number).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Decoded spec array, or null. */
function j5tm_spec( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) return null;
	$pid = $product->get_parent_id() ?: $product->get_id();
	$raw = get_post_meta( $pid, '_j5_armor_spec', true );
	if ( ! is_string( $raw ) || '' === trim( $raw ) ) return null;
	$spec = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $spec ) ) return null;
	if ( empty( $spec['sizes'] ) && empty( $spec['threats'] ) && empty( $spec['certification'] ) ) return null;
	return $spec;
}

/** Brand name as written (not uppercased), for "Special threats (per Hesco)". */
function j5tm_brand( $product ) {
	$pid = $product->get_parent_id() ?: $product->get_id();
	foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $tax ) {
		if ( taxonomy_exists( $tax ) ) {
			$t = get_the_terms( $pid, $tax );
			if ( $t && ! is_wp_error( $t ) ) return $t[0]->name;
		}
	}
	return '';
}

function j5tm_v( $v ) {
	return ( null === $v || '' === $v || is_array( $v ) ) ? '' : (string) $v;
}

function j5tm_norm( $s ) {
	return strtolower( preg_replace( '/[\s\-_]+/', '', (string) $s ) );
}

function j5tm_render( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) return '';
	$spec = j5tm_spec( $product );
	if ( ! $spec ) return '';

	$cert    = is_array( $spec['certification'] ?? null ) ? $spec['certification'] : array();
	$level   = j5tm_v( $cert['level'] ?? null );
	$std     = trim( preg_replace( '/^\s*NIJ\s*/i', '', j5tm_v( $cert['standard'] ?? null ) ) );
	$maker   = j5tm_v( $cert['maker_label'] ?? null );
	$sizes   = is_array( $spec['sizes'] ?? null ) ? array_values( array_filter( $spec['sizes'], 'is_array' ) ) : array();
	$threats = is_array( $spec['threats'] ?? null ) ? array_values( array_filter( $spec['threats'], 'is_array' ) ) : array();

	// Columns: parts in size-table order, then any part only listed under a threat.
	$parts = array();
	foreach ( $sizes as $s ) { $p = j5tm_v( $s['part'] ?? null ); if ( '' !== $p && ! in_array( $p, $parts, true ) ) $parts[] = $p; }
	foreach ( $threats as $t ) {
		foreach ( (array) ( $t['shots'] ?? array() ) as $p => $n ) { $p = (string) $p; if ( '' !== $p && ! in_array( $p, $parts, true ) ) $parts[] = $p; }
	}

	$base    = array_values( array_filter( $threats, function ( $t ) { return ! empty( $t['base'] ); } ) );
	$special = array_values( array_filter( $threats, function ( $t ) { return empty( $t['base'] ); } ) );
	$brand   = j5tm_brand( $product );
	$cols    = 3 + count( $parts );

	ob_start();
	?>
	<section class="j5tm" data-j5tm>
		<h3 class="j5tm__h">Ballistic protection</h3>
		<?php if ( '' !== $level ) : ?>
			<p class="j5tm__cert">
				<strong><?php echo esc_html( trim( 'NIJ ' . $std ) . ' certified Level ' . $level ); ?></strong>
				<?php if ( '' !== $maker && 0 !== strcasecmp( $maker, 'Level ' . $level ) && 0 !== strcasecmp( $maker, $level ) ) : ?>
					<span class="j5tm__maker"><?php echo esc_html( $maker . ' (manufacturer rating)' ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<?php
		$facts = array();
		if ( '' !== j5tm_v( $spec['thickness_in'] ?? null ) ) $facts[] = 'Thickness: ' . j5tm_v( $spec['thickness_in'] ) . ' in';
		if ( '' !== j5tm_v( $spec['areal_density_lb_ft2'] ?? null ) ) $facts[] = 'Areal density: ' . j5tm_v( $spec['areal_density_lb_ft2'] ) . ' lb/ft²';
		if ( $facts ) : ?>
			<p class="j5tm__facts"><?php echo esc_html( implode( ' · ', $facts ) ); ?></p>
		<?php endif; ?>

		<?php if ( $sizes ) : ?>
			<div class="j5tm__scroll">
				<table class="j5tm__t j5tm__sizes">
					<thead><tr><th scope="col">Part number</th><th scope="col">Cut</th><th scope="col">Curve</th><th scope="col">W × L (in)</th><th scope="col">Weight (lb)</th></tr></thead>
					<tbody>
					<?php foreach ( $sizes as $s ) :
						$w = j5tm_v( $s['w_in'] ?? null ); $l = j5tm_v( $s['l_in'] ?? null );
						$wl = ( '' !== $w && '' !== $l ) ? $w . ' × ' . $l : ( $w . $l );
						?>
						<tr data-part="<?php echo esc_attr( j5tm_norm( $s['part'] ?? '' ) ); ?>">
							<th scope="row" data-label="Part number"><?php echo esc_html( j5tm_v( $s['part'] ?? null ) ); ?></th>
							<td data-label="Cut"><?php echo esc_html( j5tm_v( $s['cut'] ?? null ) ); ?></td>
							<td data-label="Curve"><?php echo esc_html( j5tm_v( $s['curve'] ?? null ) ); ?></td>
							<td data-label="W × L (in)"><?php echo esc_html( $wl ); ?></td>
							<td data-label="Weight (lb)"><?php echo esc_html( j5tm_v( $s['weight_lb'] ?? null ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( $threats ) : ?>
			<div class="j5tm__scroll">
				<table class="j5tm__t j5tm__matrix">
					<caption class="j5tm__cap">Rounds tested and shots stopped per plate</caption>
					<thead><tr>
						<th scope="col">Round</th><th scope="col">Core</th><th scope="col">ft/s</th>
						<?php foreach ( $parts as $i => $p ) : ?><th scope="col" class="j5tm__part" data-col="<?php echo (int) $i; ?>" data-part="<?php echo esc_attr( j5tm_norm( $p ) ); ?>"><?php echo esc_html( $p ); ?></th><?php endforeach; ?>
					</tr></thead>
					<?php
					$groups = array();
					if ( $base ) $groups[] = array( '' !== $level ? 'NIJ Level ' . $level . ' test round' : 'Certification test round', $base );
					if ( $special ) $groups[] = array( 'Special threats (per ' . ( $brand ? $brand : 'manufacturer' ) . ')', $special );
					foreach ( $groups as $g ) : ?>
						<tbody>
							<tr class="j5tm__grp"><th scope="rowgroup" colspan="<?php echo (int) $cols; ?>"><?php echo esc_html( $g[0] ); ?></th></tr>
							<?php foreach ( $g[1] as $t ) : $shots = (array) ( $t['shots'] ?? array() ); ?>
								<tr>
									<th scope="row" data-label="Round"><?php echo esc_html( j5tm_v( $t['round'] ?? null ) ); ?></th>
									<?php foreach ( array( 'core' => 'Core', 'fps' => 'ft/s' ) as $k => $lab ) : $val = j5tm_v( $t[ $k ] ?? null ); ?>
										<td data-label="<?php echo esc_attr( $lab ); ?>"<?php echo '' === $val ? ' class="is-empty"' : ''; ?>><?php echo esc_html( $val ); ?></td>
									<?php endforeach; ?>
									<?php foreach ( $parts as $i => $p ) :
										$n = array_key_exists( $p, $shots ) ? j5tm_v( $shots[ $p ] ) : ''; ?>
										<td class="j5tm__shots<?php echo '' === $n ? ' is-empty' : ''; ?>" data-col="<?php echo (int) $i; ?>" data-label="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( $n ); ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endif; ?>

		<?php
		$src = j5tm_v( $spec['source'] ?? null ); $rev = j5tm_v( $spec['revision'] ?? null );
		if ( '' !== $src || '' !== $rev ) : ?>
			<p class="j5tm__src">Source: <?php echo esc_html( $src ); ?><?php echo '' !== $rev ? esc_html( ( '' !== $src ? ', ' : '' ) . 'rev. ' . $rev ) : ''; ?></p>
		<?php endif; ?>
	</section>
	<?php
	j5tm_assets();
	return ob_get_clean();
}


/* -------------------------------------------------------------------------
 * Tab split: Ballistics tab + sizes block for the Specifications tab
 * ----------------------------------------------------------------------- */

/** True when the product gets a Ballistics tab (certification or threats). */
function j5tm_has_ballistics( $product ) {
	$spec = j5tm_spec( $product );
	if ( ! $spec ) return false;
	return ! empty( $spec['threats'] ) || ! empty( $spec['certification']['level'] );
}

/**
 * Model name for headings, e.g. "HESCO 3612". Uses spec['model'] when the DP
 * sends it; otherwise the product title cut before the rating words
 * ("Hesco 3612 Level III+ Plate Set" -> "HESCO 3612"). Filter: j5tm_model_name.
 */
function j5tm_model_name( $product, $spec = null ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) return '';
	$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	$spec   = $spec ?: j5tm_spec( $parent );
	$name   = j5tm_v( $spec['model'] ?? null );
	if ( '' === $name ) {
		$name = wp_specialchars_decode( $parent->get_name(), ENT_QUOTES );
		$name = preg_split( '/\s*\|\s*|\s+(?:NIJ\b|Level\b|Special Threat\b|Multi[\s-]?Curve\b|Single[\s-]?Curve\b|Stand[\s-]?Alone\b|ICW\b|Ballistic\b|Plate\b|Plates\b|Body Armor\b|Rifle\b|Hard Armor\b)/i', $name )[0];
		$name = trim( $name );
	}
	$caps = apply_filters( 'j5tm_brand_caps', array( 'hesco' => 'HESCO', 'highcom' => 'HighCom', 'usi' => 'USI' ) );
	foreach ( $caps as $k => $v ) {
		$name = preg_replace( '/^' . preg_quote( $k, '/' ) . '\b/i', $v, $name );
	}
	return apply_filters( 'j5tm_model_name', $name, $parent, $spec );
}

/**
 * Round names for sentences.
 *   short: "5.56 × 45 mm – M193" -> "M193"; generic loads keep the caliber:
 *          "7.62 × 63 mm – JSP" -> "7.62 × 63 JSP"
 *   full:  "7.62 × 51 mm – M80" -> "7.62 × 51 M80"
 */
function j5tm_round_short( $round, $full = false ) {
	$round = j5tm_v( $round );
	$parts = preg_split( '/\s+[–—-]\s+/u', $round );
	if ( count( $parts ) < 2 ) return trim( $round );
	$name = trim( end( $parts ) );
	$cal  = trim( preg_replace( '/\s*mm\b/i', '', $parts[0] ) );
	$generic = preg_match( '/^(JSP|FMJ|Ball|SP|HP|JHP|SJHP|SJSP|AP|MSC|LRN|FMJ\s*RN|Lead\s*Core|Steel\s*Core)$/i', $name );
	return ( $full || $generic ) ? $cal . ' ' . $name : $name;
}

/** "a, b and c" */
function j5tm_join( $items ) {
	$items = array_values( array_filter( array_map( 'trim', $items ), 'strlen' ) );
	if ( count( $items ) < 2 ) return implode( '', $items );
	$last = array_pop( $items );
	return implode( ', ', $items ) . ' and ' . $last;
}

/** Thickness shown to two decimals with inch mark: 1 -> 1.00" */
function j5tm_inches( $v ) {
	$v = j5tm_v( $v );
	if ( '' === $v ) return '';
	return is_numeric( $v ) ? number_format( (float) $v, 2 ) . '"' : $v;
}

/** Ballistics tab content. Empty string when there is nothing to show. */
function j5tm_render_ballistics( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product || ! j5tm_has_ballistics( $product ) ) return '';
	$spec    = j5tm_spec( $product );
	$model   = j5tm_model_name( $product, $spec );
	$brand   = j5tm_brand( $product );
	$brand_s = preg_replace( '/\s+Armor$/i', '', $brand );
	$brand_s = strcasecmp( $brand_s, 'hesco' ) === 0 ? 'HESCO' : $brand_s;

	$cert  = is_array( $spec['certification'] ?? null ) ? $spec['certification'] : array();
	$level = j5tm_v( $cert['level'] ?? null );
	$std   = trim( preg_replace( '/^\s*NIJ\s*/i', '', j5tm_v( $cert['standard'] ?? null ) ) );
	$maker = j5tm_v( $cert['maker_label'] ?? null );
	$thick = j5tm_inches( $spec['thickness_in'] ?? null );
	$ad    = j5tm_v( $spec['areal_density_lb_ft2'] ?? null );

	$threats = is_array( $spec['threats'] ?? null ) ? array_values( array_filter( $spec['threats'], 'is_array' ) ) : array();
	$base    = array_values( array_filter( $threats, function ( $t ) { return ! empty( $t['base'] ); } ) );
	$special = array_values( array_filter( $threats, function ( $t ) { return empty( $t['base'] ); } ) );

	// Answer sentence, built only from the data.
	$bits = array();
	if ( '' !== $level ) {
		$b = 'Certified NIJ ' . ( '' !== $std ? $std . ' ' : '' ) . 'Level ' . $level;
		if ( $base ) $b .= ' against the ' . j5tm_join( array_map( function ( $t ) { return j5tm_round_short( $t['round'] ?? null, true ); }, $base ) );
		$bits[] = $b;
	}
	if ( $special ) {
		$list   = j5tm_join( array_map( function ( $t ) { return j5tm_round_short( $t['round'] ?? null ); }, $special ) );
		$bits[] = ( $bits ? 'and rated by ' : 'Rated by ' ) . ( $brand_s ? $brand_s : 'the manufacturer' ) . ' to stop ' . $list;
	}
	$answer = $bits ? implode( ', ', $bits ) . '.' : '';

	ob_start();
	?>
	<div class="j5tm j5tm--ballistics" data-j5tm>
		<div class="j5tm__tiles">
			<?php if ( '' !== $level ) : ?>
				<div class="j5tm__tile j5tm__tile--wide">
					<span class="j5tm__tile-k">Protection level</span>
					<span class="j5tm__tile-v"><?php echo esc_html( trim( 'NIJ ' . $std ) . ' Level ' . $level ); ?></span>
					<?php if ( '' !== $maker && 0 !== strcasecmp( $maker, 'Level ' . $level ) && 0 !== strcasecmp( $maker, $level ) ) : ?>
						<span class="j5tm__tile-n">Certified. Rated <?php echo esc_html( $maker ); ?> by the manufacturer.</span>
					<?php else : ?>
						<span class="j5tm__tile-n">Certified.</span>
					<?php endif; ?>
				</div>
			<?php elseif ( '' !== $maker ) : ?>
				<div class="j5tm__tile j5tm__tile--wide">
					<span class="j5tm__tile-k">Protection level</span>
					<span class="j5tm__tile-v"><?php echo esc_html( $maker ); ?></span>
					<span class="j5tm__tile-n">Manufacturer rating.</span>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $thick ) : ?>
				<div class="j5tm__tile"><span class="j5tm__tile-k">Thickness</span><span class="j5tm__tile-v"><?php echo esc_html( $thick ); ?></span></div>
			<?php endif; ?>
			<?php if ( '' !== $ad ) : ?>
				<div class="j5tm__tile"><span class="j5tm__tile-k">Areal density</span><span class="j5tm__tile-v"><?php echo esc_html( $ad ); ?> lb/ft²</span></div>
			<?php endif; ?>
		</div>

		<?php if ( $threats ) : ?>
			<h2 class="j5tm__q">What Does the <?php echo esc_html( $model ); ?> Stop?</h2>
			<?php if ( '' !== $answer ) : ?><p class="j5tm__a"><?php echo esc_html( $answer ); ?></p><?php endif; ?>
			<?php echo j5tm_matrix_html( $product, $spec, $model ); ?>
		<?php endif; ?>
	</div>
	<?php
	j5tm_assets();
	return ob_get_clean();
}

/** Sizes block for the top of the Specifications tab. */
function j5tm_render_sizes( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if ( ! $product ) return '';
	$spec = j5tm_spec( $product );
	if ( ! $spec ) return '';
	$sizes = is_array( $spec['sizes'] ?? null ) ? array_values( array_filter( $spec['sizes'], 'is_array' ) ) : array();
	if ( ! $sizes ) return '';
	$model = j5tm_model_name( $product, $spec );

	$cuts = array(); $wts = array();
	foreach ( $sizes as $s ) {
		$c = j5tm_v( $s['cut'] ?? null ); if ( '' !== $c && ! in_array( $c, $cuts, true ) ) $cuts[] = $c;
		$w = j5tm_v( $s['weight_lb'] ?? null ); if ( is_numeric( $w ) ) $wts[] = (float) $w;
	}
	$n      = count( $sizes );
	$answer = $n . ( 1 === $n ? ' size' : ' sizes' );
	if ( $cuts ) $answer .= ' in ' . j5tm_join( $cuts ) . ( count( $cuts ) > 1 ? ' cuts' : ' cut' );
	if ( $wts ) {
		$lo = min( $wts ); $hi = max( $wts );
		$fmt = function ( $x ) { return rtrim( rtrim( number_format( $x, 2, '.', '' ), '0' ), '.' ); };
		$answer .= $lo === $hi ? ', ' . $fmt( $lo ) . ' lb per plate' : ', from ' . $fmt( $lo ) . ' to ' . $fmt( $hi ) . ' lb per plate';
	}
	$answer .= '.';

	ob_start();
	?>
	<div class="j5tm j5tm--sizes" data-j5tm>
		<h2 class="j5tm__q">What Sizes Does the <?php echo esc_html( $model ); ?> Come In?</h2>
		<p class="j5tm__a"><?php echo esc_html( $answer ); ?></p>
		<?php echo j5tm_sizes_html( $sizes, $model ); ?>
	</div>
	<?php
	j5tm_assets();
	return ob_get_clean();
}

/** Sizes table markup (shared). */
function j5tm_sizes_html( $sizes, $model = '' ) {
	ob_start();
	?>
	<div class="j5tm__scroll">
		<table class="j5tm__t j5tm__sizes">
			<?php if ( '' !== $model ) : ?><caption class="j5tm__cap"><?php echo esc_html( $model ); ?> part numbers by cut, curve, dimensions and weight per plate</caption><?php endif; ?>
			<thead><tr><th scope="col">Part number</th><th scope="col">Cut</th><th scope="col">Curve</th><th scope="col">W × L (in)</th><th scope="col">Weight (lb)</th></tr></thead>
			<tbody>
			<?php foreach ( $sizes as $s ) :
				$w = j5tm_v( $s['w_in'] ?? null ); $l = j5tm_v( $s['l_in'] ?? null );
				$wl = ( '' !== $w && '' !== $l ) ? $w . ' × ' . $l : ( $w . $l );
				?>
				<tr data-part="<?php echo esc_attr( j5tm_norm( $s['part'] ?? '' ) ); ?>">
					<th scope="row" data-label="Part number"><?php echo esc_html( j5tm_v( $s['part'] ?? null ) ); ?></th>
					<td data-label="Cut"><?php echo esc_html( j5tm_v( $s['cut'] ?? null ) ); ?></td>
					<td data-label="Curve"><?php echo esc_html( j5tm_v( $s['curve'] ?? null ) ); ?></td>
					<td data-label="W × L (in)"><?php echo esc_html( $wl ); ?></td>
					<td data-label="Weight (lb)"><?php echo esc_html( j5tm_v( $s['weight_lb'] ?? null ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
	return ob_get_clean();
}

/** Threat matrix table markup + source line (shared). */
function j5tm_matrix_html( $product, $spec, $model = '' ) {
	$cert    = is_array( $spec['certification'] ?? null ) ? $spec['certification'] : array();
	$level   = j5tm_v( $cert['level'] ?? null );
	$sizes   = is_array( $spec['sizes'] ?? null ) ? array_values( array_filter( $spec['sizes'], 'is_array' ) ) : array();
	$threats = is_array( $spec['threats'] ?? null ) ? array_values( array_filter( $spec['threats'], 'is_array' ) ) : array();
	if ( ! $threats ) return '';
	$parts = array();
	foreach ( $sizes as $s ) { $p = j5tm_v( $s['part'] ?? null ); if ( '' !== $p && ! in_array( $p, $parts, true ) ) $parts[] = $p; }
	foreach ( $threats as $t ) {
		foreach ( (array) ( $t['shots'] ?? array() ) as $p => $n ) { $p = (string) $p; if ( '' !== $p && ! in_array( $p, $parts, true ) ) $parts[] = $p; }
	}
	$base    = array_values( array_filter( $threats, function ( $t ) { return ! empty( $t['base'] ); } ) );
	$special = array_values( array_filter( $threats, function ( $t ) { return empty( $t['base'] ); } ) );
	$brand   = j5tm_brand( $product );
	$cols    = 3 + count( $parts );
	ob_start();
	?>
	<div class="j5tm__scroll">
		<table class="j5tm__t j5tm__matrix">
			<caption class="j5tm__cap"><?php echo esc_html( ( '' !== $model ? $model . ': r' : 'R' ) . 'ounds tested and shots stopped per plate' ); ?></caption>
			<thead><tr>
				<th scope="col">Round</th><th scope="col">Core</th><th scope="col">ft/s</th>
				<?php foreach ( $parts as $i => $p ) : ?><th scope="col" class="j5tm__part" data-col="<?php echo (int) $i; ?>" data-part="<?php echo esc_attr( j5tm_norm( $p ) ); ?>"><?php echo esc_html( $p ); ?></th><?php endforeach; ?>
			</tr></thead>
			<?php
			$groups = array();
			if ( $base ) $groups[] = array( '' !== $level ? 'NIJ Level ' . $level . ' test round' : 'Certification test round', $base );
			if ( $special ) $groups[] = array( 'Special threats (per ' . ( $brand ? $brand : 'manufacturer' ) . ')', $special );
			foreach ( $groups as $g ) : ?>
				<tbody>
					<tr class="j5tm__grp"><th scope="rowgroup" colspan="<?php echo (int) $cols; ?>"><?php echo esc_html( $g[0] ); ?></th></tr>
					<?php foreach ( $g[1] as $t ) : $shots = (array) ( $t['shots'] ?? array() ); ?>
						<tr>
							<th scope="row" data-label="Round"><?php echo esc_html( j5tm_v( $t['round'] ?? null ) ); ?></th>
							<?php foreach ( array( 'core' => 'Core', 'fps' => 'ft/s' ) as $k => $lab ) : $val = j5tm_v( $t[ $k ] ?? null ); ?>
								<td data-label="<?php echo esc_attr( $lab ); ?>"<?php echo '' === $val ? ' class="is-empty"' : ''; ?>><?php echo esc_html( $val ); ?></td>
							<?php endforeach; ?>
							<?php foreach ( $parts as $i => $p ) :
								$n = array_key_exists( $p, $shots ) ? j5tm_v( $shots[ $p ] ) : ''; ?>
								<td class="j5tm__shots<?php echo '' === $n ? ' is-empty' : ''; ?>" data-col="<?php echo (int) $i; ?>" data-label="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( $n ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			<?php endforeach; ?>
		</table>
	</div>
	<?php
	$src = j5tm_v( $spec['source'] ?? null ); $rev = j5tm_v( $spec['revision'] ?? null );
	if ( '' !== $src || '' !== $rev ) : ?>
		<p class="j5tm__src">Source: <?php echo esc_html( $src ); ?><?php echo '' !== $rev ? esc_html( ( '' !== $src ? ', ' : '' ) . 'rev. ' . $rev ) : ''; ?></p>
	<?php endif;
	return ob_get_clean();
}

add_shortcode( 'j5_threat_matrix', function ( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'j5_threat_matrix' );
	$id   = absint( $atts['id'] ) ?: get_the_ID();
	return $id ? j5tm_render( $id ) : '';
} );

/** CSS + variation highlight script, printed once per page. */
function j5tm_assets() {
	static $done = false;
	if ( $done ) return;
	$done = true;
	?>
	<style>
	.j5tm{margin:0 0 28px}
	.j5tm__h{font-family:'Bebas Neue',Impact,sans-serif;font-weight:400;font-size:26px;letter-spacing:.03em;margin:0 0 6px;color:var(--j5-ink)}
	.j5tm__cert{margin:0 0 4px;color:var(--j5-ink)}.j5tm__maker{margin-left:8px;color:var(--j5-ink-dim);font-size:.9em}
	.j5tm__facts,.j5tm__src{color:var(--j5-ink-dim);font-size:13px;margin:0 0 14px}
	.j5tm__scroll{overflow-x:auto;margin:0 0 18px}
	.j5tm__t{width:100%;border-collapse:collapse;font-size:14px}
	.j5tm__t th,.j5tm__t td{padding:9px 12px;border-bottom:1px solid var(--j5-border);text-align:left;white-space:nowrap}
	.j5tm__t thead th{font-family:'Barlow Condensed',sans-serif;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--j5-ink-mute)}
	.j5tm__t tbody th{color:var(--j5-ink);font-weight:600}.j5tm__t td{color:var(--j5-ink-dim)}
	.j5tm__shots{text-align:center!important;font-family:'JetBrains Mono',monospace;color:var(--j5-ink)!important}
	.j5tm__part{text-align:center!important}
	.j5tm__grp th{background:var(--j5-bg-card);color:var(--j5-gold)!important;font-family:'Barlow Condensed',sans-serif;letter-spacing:.1em;text-transform:uppercase;font-size:12px}
	.j5tm .is-sel{background:rgba(212,160,68,.12);color:var(--j5-gold)!important}
	.j5tm__cap{caption-side:top;text-align:left;color:var(--j5-ink-mute);font-size:12px;padding-bottom:6px}
	.j5tm--ballistics{display:flex;flex-direction:column;gap:6px}
	.j5tm__tiles{display:flex;flex-wrap:wrap;gap:14px;margin:0 0 30px}
	.j5tm__tile{flex:1 1 150px;display:flex;flex-direction:column;gap:6px;padding:18px 22px;background:var(--j5-bg-card);border:1px solid var(--j5-border);border-radius:6px}
	.j5tm__tile--wide{flex:2 1 300px}
	.j5tm__tile-k{font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--j5-ink-mute)}
	.j5tm__tile-v{font-family:'Bebas Neue',Impact,sans-serif;font-size:36px;line-height:1;color:var(--j5-bone)}
	.j5tm__tile-n{color:var(--j5-ink-dim);font-size:14px}
	.j5tm__q{font-family:'Bebas Neue',Impact,sans-serif;font-weight:400;font-size:32px;line-height:1.05;letter-spacing:.02em;margin:0 0 8px;color:var(--j5-bone)}
	.j5tm__a{margin:0 0 16px;max-width:780px;color:var(--j5-ink-dim);font-size:15px;line-height:1.55}
	.j5tm--sizes{margin:0 0 32px}
	@media(max-width:639px){.j5tm__q{font-size:26px}.j5tm__tile-v{font-size:30px}}
	@media(max-width:639px){
	.j5tm__scroll:has(.j5tm__matrix){overflow:visible}.j5tm__matrix thead{display:none}.j5tm__matrix,.j5tm__matrix caption,.j5tm__matrix tbody,.j5tm__matrix tr,.j5tm__matrix th,.j5tm__matrix td{display:block;width:auto;box-sizing:border-box}
	.j5tm__matrix tr:not(.j5tm__grp){border:1px solid var(--j5-border);margin:0 0 10px;padding:6px 0}
	.j5tm__matrix td,.j5tm__matrix tbody th{border:0;padding:4px 12px;display:flex;justify-content:space-between;white-space:normal}
	.j5tm__matrix td::before{content:attr(data-label);color:var(--j5-ink-mute)}.j5tm__matrix td.is-empty{display:none}
	}
	</style>
	<script>
	(function(){
	  function norm(s){return String(s||'').toLowerCase().replace(/[\s\-_]+/g,'');}
	  function mark(sku){
	    var k=norm(sku);
	    document.querySelectorAll('[data-j5tm]').forEach(function(box){
	      box.querySelectorAll('.is-sel').forEach(function(el){el.classList.remove('is-sel');});
	      if(!k) return;
	      box.querySelectorAll('.j5tm__part').forEach(function(th){
	        var p=th.getAttribute('data-part'); if(!p||!(k===p||k.slice(-p.length)===p)) return;
	        var c=th.getAttribute('data-col');
	        box.querySelectorAll('[data-col="'+c+'"]').forEach(function(el){el.classList.add('is-sel');});
	      });
	      box.querySelectorAll('.j5tm__sizes tr[data-part]').forEach(function(tr){
	        var p=tr.getAttribute('data-part'); if(p&&(k===p||k.slice(-p.length)===p)) tr.classList.add('is-sel');
	      });
	    });
	  }
	  if(window.jQuery){jQuery(function($){$('form.variations_form').on('found_variation',function(e,v){mark(v&&v.sku);}).on('reset_data',function(){mark('');});});}
	})();
	</script>
	<?php
}
