<?php
/**
 * J5 plate sizing chart: [j5_plate_sizing]
 *
 * Real-text version of the HESCO two-step sizing chart (chest measurement to
 * SAPI size, plus the Shooter / full cut equivalents). Readable on dark
 * backgrounds, indexable, and it stacks on phones.
 *
 *   [j5_plate_sizing]                 full chart
 *   [j5_plate_sizing brand="HESCO"]   label the chart with a brand name
 *
 * Dimensions are the standard SAPI / ESAPI cuts and HESCO's published
 * Shooter / full cut sizes. Styles are inlined once per page, same pattern as
 * inc/j5-threat-matrix.php.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'j5_plate_sizing', function ( $atts ) {
	$atts  = shortcode_atts( array( 'brand' => '' ), $atts, 'j5_plate_sizing' );
	$brand = sanitize_text_field( $atts['brand'] );
	j5_plate_sizing_css();

	$sizes = array(
		array( 'XS', '7.25 x 11.5 in',   '184 x 292 mm', '32 to 35 in', '81 to 89 cm' ),
		array( 'S',  '8.75 x 11.75 in',  '222 x 298 mm', '36 to 39 in', '91 to 99 cm' ),
		array( 'M',  '9.5 x 12.5 in',    '241 x 318 mm', '40 to 43 in', '102 to 109 cm' ),
		array( 'L',  '10.25 x 13.25 in', '260 x 337 mm', '44 to 47 in', '112 to 119 cm' ),
		array( 'XL', '11 x 14 in',       '279 x 356 mm', '48 in and up', '122 cm and up' ),
	);

	ob_start();
	?>
	<div class="j5-sizing">
		<div class="j5-sizing__step">
			<span class="j5-sizing__n">Step 1</span>
			<span class="j5-sizing__t">Measure your chest and find your size</span>
		</div>
		<div class="j5-sizing__scroll">
		<table class="j5-sizing__t1">
			<thead><tr><th scope="row">Chest</th><?php foreach ( $sizes as $s ) : ?><th scope="col"><?php echo esc_html( $s[3] ); ?><small><?php echo esc_html( $s[4] ); ?></small></th><?php endforeach; ?></tr></thead>
			<tbody>
				<tr><th scope="row">SAPI size</th><?php foreach ( $sizes as $s ) : ?><td data-chest="<?php echo esc_attr( $s[3] . ' (' . $s[4] . ')' ); ?>"><b><?php echo esc_html( $s[0] ); ?></b></td><?php endforeach; ?></tr>
			</tbody>
		</table>
		</div>
		<p class="j5-sizing__note">Plate length: standing upright, the top edge of the plate should sit at the suprasternal notch (the dip at the base of your throat). The bottom edge should end about 4.5 inches above your navel. That covers the heart, lungs and major vessels without the plate riding into your belt when you sit or crouch.</p>

		<div class="j5-sizing__step">
			<span class="j5-sizing__n">Step 2</span>
			<span class="j5-sizing__t">Pick the cut, then the matching size</span>
		</div>
		<ul class="j5-sizing__m" aria-hidden="true">
			<?php foreach ( $sizes as $s ) : ?>
				<li><b><?php echo esc_html( $s[0] ); ?></b><span><?php echo esc_html( $s[1] ); ?><small><?php echo esc_html( $s[2] ); ?></small></span></li>
			<?php endforeach; ?>
			<li class="j5-sizing__m-cut"><b>Shooter and full cut</b><span>8 x 10 in <small>XS and S, 203 x 254 mm</small></span><span>10 x 12 in <small>M, L and XL, 254 x 305 mm</small></span></li>
		</ul>
		<div class="j5-sizing__scroll j5-sizing__scroll--t2">
		<table class="j5-sizing__t2">
			<thead><tr><th scope="row">Cut</th><?php foreach ( $sizes as $s ) : ?><th scope="col"><?php echo esc_html( $s[0] ); ?></th><?php endforeach; ?></tr></thead>
			<tbody>
				<tr><th scope="row">SAPI cut</th><?php foreach ( $sizes as $s ) : ?><td><?php echo esc_html( $s[1] ); ?><small><?php echo esc_html( $s[2] ); ?></small></td><?php endforeach; ?></tr>
				<tr><th scope="row">Shooter and full cut</th><td colspan="2">8 x 10 in<small>203 x 254 mm</small></td><td colspan="3">10 x 12 in<small>254 x 305 mm</small></td></tr>
			</tbody>
		</table>
		</div>
		<p class="j5-sizing__src"><?php echo $brand ? esc_html( $brand ) . ' sizing' : 'Sizing'; ?> per the manufacturer's fit guide. Between sizes, size down for mobility or up for coverage, and check that your carrier is cut for the plate you choose.</p>
	</div>
	<?php
	return ob_get_clean();
} );

function j5_plate_sizing_css() {
	static $done = false;
	if ( $done ) return;
	$done = true;
	?>
	<style>
	.j5-sizing{margin:1.8em 0;font-family:'Barlow',sans-serif;color:var(--j5-ink,#eceae2)}
	.j5-sizing__step{display:flex;align-items:baseline;gap:12px;margin:0 0 12px}
	.j5-sizing__step+.j5-sizing__scroll{margin-top:0}
	.j5-sizing__n{font-family:'Barlow Condensed',sans-serif;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--j5-gold,#d4a044);border:1px solid var(--j5-gold,#d4a044);border-radius:3px;padding:3px 8px}
	.j5-sizing__t{font-family:'Bebas Neue','Barlow Condensed',Impact,sans-serif;font-size:22px;letter-spacing:.03em;color:var(--j5-bone,#f5f1e8)}
	.j5-sizing__scroll{overflow-x:auto;margin:0 0 14px}
	.j5-sizing table{width:100%;border-collapse:collapse;font-size:14px;table-layout:fixed}
	.j5-sizing th,.j5-sizing td{padding:10px 8px;text-align:center;color:var(--j5-ink,#eceae2);border-bottom:1px solid var(--j5-border,#23272f);vertical-align:top;line-height:1.3}
	.j5-sizing th[scope=row]{text-align:left;font-family:'Barlow Condensed',sans-serif;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--j5-ink-mute,#6e6e66);font-weight:600;width:22%}
	.j5-sizing thead th{border-bottom:2px solid var(--j5-gold,#d4a044)}.j5-sizing thead th[scope=col]{font-weight:600;color:var(--j5-bone,#f5f1e8)}
	.j5-sizing tbody tr:nth-child(even) td,.j5-sizing tbody tr:nth-child(even) th{background:rgba(255,255,255,.03)}
	.j5-sizing small{display:block;font-size:11px;color:var(--j5-ink-mute,#6e6e66);margin-top:2px;letter-spacing:0;text-transform:none}
	.j5-sizing td b{font-family:'Bebas Neue','Barlow Condensed',Impact,sans-serif;font-weight:400;font-size:26px;letter-spacing:.03em;color:var(--j5-gold,#d4a044)}
	.j5-sizing__t2 td[colspan]{background:rgba(255,255,255,.025)}
	.j5-sizing__note{margin:0 0 22px;font-size:14px;line-height:1.55;color:var(--j5-ink-dim,#a4a49a)}
	.j5-sizing__src{margin:0;font-size:12px;color:var(--j5-ink-mute,#6e6e66)}
	.j5-sizing__m{display:none}
	@media(max-width:640px){
	.j5-sizing__step{justify-content:center;text-align:center;flex-direction:column;gap:8px}
	.j5-sizing__scroll--t2{display:none}
	.j5-sizing__m{display:block;list-style:none;margin:0 0 14px;padding:0}
	.j5-sizing__m li{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 12px;border-bottom:1px solid var(--j5-border,#23272f)}
	.j5-sizing__m li b{font-family:'Bebas Neue','Barlow Condensed',Impact,sans-serif;font-weight:400;font-size:24px;letter-spacing:.03em;color:var(--j5-gold,#d4a044)}
	.j5-sizing__m li span{text-align:right}
	.j5-sizing__m li small{display:block;font-size:11px;color:var(--j5-ink-mute,#6e6e66)}
	.j5-sizing__m-cut{flex-direction:column;align-items:center!important;text-align:center;gap:6px!important;background:rgba(255,255,255,.03)}
	.j5-sizing__m-cut b{font-family:'Barlow Condensed',sans-serif!important;font-size:12px!important;letter-spacing:.12em;text-transform:uppercase;color:var(--j5-ink-mute,#6e6e66)!important}
	.j5-sizing__m-cut span{text-align:center!important}
	.j5-sizing__t1 tr{border:1px solid var(--j5-border,#23272f);border-radius:8px;padding:6px 0 4px;background:var(--j5-bg-card,#15181d)}
	.j5-sizing__t1 th[scope=row]{text-align:center!important;padding:6px 12px 8px;border-bottom:2px solid var(--j5-gold,#d4a044)!important;margin:0 0 4px}
	.j5-sizing__t1 td{padding:7px 12px}
	.j5-sizing__note,.j5-sizing__src{text-align:center}
	
	.j5-sizing__t1 thead{display:none}
	.j5-sizing__t1,.j5-sizing__t1 tbody,.j5-sizing__t1 tr,.j5-sizing__t1 th,.j5-sizing__t1 td{display:block;width:auto;text-align:left}
	.j5-sizing__t1 tr{display:grid;grid-template-columns:1fr}
	.j5-sizing__t1 th[scope=row]{width:auto;border:0;padding-bottom:4px}
	.j5-sizing__t1 td{display:flex;justify-content:space-between;align-items:center}
	.j5-sizing__t1 td::before{content:attr(data-chest);font-size:13px;color:var(--j5-ink-dim,#a4a49a)}
	.j5-sizing__t1 td b{font-size:22px}
	.j5-sizing__t2{font-size:12px}
	}
	</style>
	<?php
}
