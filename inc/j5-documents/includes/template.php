<?php
/**
 * J5 Documents: HTML for receipts, invoices and packing slips.
 *
 * Reads only the array from j5doc_build(). Used for the PDF (dompdf) and for
 * the browser preview, so layout is tables + simple CSS that dompdf supports
 * (no flexbox / grid).
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * @param array  $d     j5doc_build() result.
 * @param string $mode  'pdf' (local font/image paths) or 'html' (URLs).
 * @param array  $paths [ 'fonts' => dir or URL base ]
 * @return string
 */
function j5doc_html( $d, $mode = 'pdf', $paths = array() ) {
	$e     = function ( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); };
	$fonts = rtrim( isset( $paths['fonts'] ) ? $paths['fonts'] : '', '/' ) . '/';
	$slip  = 'packing-slip' === $d['type'];
	$tone  = isset( $d['badge']['tone'] ) ? $d['badge']['tone'] : '';
	$tick  = '<span class="sym">&#10004;</span>';

	ob_start();
	?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $e( 'J5 Rescue Supply ' . $d['title'] . ' #' . $d['number'] ); ?></title>
<style>
@font-face { font-family: 'Barlow'; font-weight: normal; font-style: normal; src: url('<?php echo $e( $fonts ); ?>Barlow-Regular.ttf') format('truetype'); }
@font-face { font-family: 'Barlow'; font-weight: 600; font-style: normal; src: url('<?php echo $e( $fonts ); ?>Barlow-SemiBold.ttf') format('truetype'); }
@font-face { font-family: 'Barlow'; font-weight: bold; font-style: normal; src: url('<?php echo $e( $fonts ); ?>Barlow-Bold.ttf') format('truetype'); }
@font-face { font-family: 'Barlow Condensed'; font-weight: bold; font-style: normal; src: url('<?php echo $e( $fonts ); ?>BarlowCondensed-Bold.ttf') format('truetype'); }
@font-face { font-family: 'Bebas Neue'; font-weight: normal; font-style: normal; src: url('<?php echo $e( $fonts ); ?>BebasNeue-Regular.ttf') format('truetype'); }

@page { size: letter portrait; margin: 0.5in 0.5in 0.7in; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Barlow', 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.25; color: #1b1d21; }
<?php if ( 'html' === $mode ) : ?>
html { background: #d9d9d6; }
body { width: 8.5in; min-height: 11in; margin: 24px auto; padding: 0.5in; background: #fff; box-shadow: 0 2px 18px rgba(0,0,0,.18); }
@media print { html { background: #fff; } body { margin: 0; padding: 0; box-shadow: none; width: auto; min-height: 0; } .no-print { display: none; } }
<?php endif; ?>
table { border-collapse: collapse; width: 100%; }
td, th { vertical-align: top; padding: 0; }
.sym { font-family: 'DejaVu Sans', sans-serif; }
.cond { font-family: 'Barlow Condensed', 'Barlow', sans-serif; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2px; }
.muted { color: #6b6d72; }
.red { color: #c41e24; }

/* Header */
.head td { vertical-align: top; }
.mark { width: 46px; height: 46px; border: 2px solid #c8a84e; text-align: center; font-family: 'Bebas Neue', sans-serif; font-size: 26pt; line-height: 44px; color: #1b1d21; }
.brand { padding-left: 12px; }
.brand-name { font-family: 'Bebas Neue', sans-serif; font-size: 22pt; line-height: 1; letter-spacing: 1px; }
.brand-lines { font-size: 8.5pt; color: #55575c; margin-top: 3px; line-height: 1.35; }
.doc-title { font-family: 'Bebas Neue', sans-serif; font-size: 34pt; line-height: 0.9; letter-spacing: 1.5px; text-align: right; }
.badge { display: inline-block; margin-top: 6px; padding: 3px 9px; font-family: 'Barlow Condensed', sans-serif; font-weight: bold; font-size: 9pt; letter-spacing: 1.5px; text-transform: uppercase; color: #fff; }
.badge.good { background: #2e7d4f; }
.badge.due { background: #c41e24; }
.badge.muted { background: #6b6d72; }
.rule { height: 3px; background: #c41e24; margin: 14px 0 0; }
.rule-gold { height: 1px; background: #c8a84e; margin: 0 0 8px; }

/* Facts + parties */
.facts { width: 100%; }
.facts td { padding: 1px 0; font-size: 9pt; }
.facts .k { width: 78px; color: #6b6d72; }
.facts .v { font-weight: 600; }
.party-h { font-family: 'Barlow Condensed', sans-serif; font-weight: bold; font-size: 8.5pt; letter-spacing: 1.5px; text-transform: uppercase; color: #a8862f; margin-bottom: 4px; }
.party { font-size: 9.5pt; line-height: 1.3; }
.parties td { padding-right: 14px; }

/* Items */
.items { margin-top: 12px; }
.items th { font-family: 'Barlow Condensed', sans-serif; font-weight: bold; font-size: 8.5pt; letter-spacing: 1.3px; text-transform: uppercase; color: #fff; background: #1b1d21; padding: 6px 8px; text-align: left; }
.items th.num, .items td.num { text-align: right; }
.items th.ctr, .items td.ctr { text-align: center; }
.items td { padding: 6px 8px; border-bottom: 1px solid #e3e1db; }
.items tr.zebra td { background: #faf9f6; }
.thumb { width: 38px; height: 38px; border: 1px solid #e3e1db; }
.thumb-empty { width: 38px; height: 38px; border: 1px solid #e3e1db; background: #f3f2ee; }
.name { font-weight: 600; font-size: 10pt; }
.sku { font-size: 8.5pt; color: #6b6d72; margin-top: 1px; }
.meta { margin-top: 3px; font-size: 8.5pt; color: #3a3c41; }
.meta b { font-weight: 600; color: #1b1d21; }
.note { margin-top: 3px; font-size: 8.5pt; color: #a8862f; font-weight: 600; }
.ack { margin-top: 3px; font-size: 8pt; color: #2e7d4f; }
.le { display: inline-block; margin-top: 3px; padding: 1px 5px; font-family: 'Barlow Condensed', sans-serif; font-weight: bold; font-size: 7.5pt; letter-spacing: 1px; text-transform: uppercase; color: #c41e24; border: 1px solid #c41e24; }
.box { width: 15px; height: 15px; border: 1.5px solid #1b1d21; margin: 0 auto; }

/* Totals */
.totals-wrap { margin-top: 8px; }
.totals td { padding: 2px 8px; font-size: 9.5pt; }
.totals .lbl { text-align: right; color: #55575c; }
.totals .val { text-align: right; width: 150px; font-weight: 600; }
.totals tr.order_total td { border-top: 2px solid #1b1d21; padding-top: 5px; font-size: 13pt; font-weight: bold; color: #1b1d21; }
.totals tr.order_total .lbl { font-family: 'Bebas Neue', sans-serif; font-weight: normal; font-size: 16pt; letter-spacing: 1px; }

/* Panels */
.panel { margin-top: 10px; padding: 8px 12px; border: 1px solid #e3e1db; border-left: 3px solid #c8a84e; page-break-inside: avoid; }
.panel-h { font-family: 'Barlow Condensed', sans-serif; font-weight: bold; font-size: 9pt; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 4px; }
.panel p { margin: 2px 0; font-size: 9pt; }
.due { border-left-color: #c41e24; }
.due .amt { font-family: 'Bebas Neue', sans-serif; font-size: 22pt; color: #c41e24; line-height: 1; }
.hold { margin-top: 14px; padding: 10px 12px; background: #c41e24; color: #fff; font-family: 'Barlow Condensed', sans-serif; font-weight: bold; font-size: 12pt; letter-spacing: 1px; text-transform: uppercase; }
.ok { margin-top: 14px; padding: 8px 12px; border: 1.5px solid #2e7d4f; color: #2e7d4f; font-family: 'Barlow Condensed', sans-serif; font-weight: bold; font-size: 10pt; letter-spacing: 1px; text-transform: uppercase; }
.sign td { padding-top: 20px; font-size: 8.5pt; color: #55575c; }
.sign .line { border-bottom: 1px solid #1b1d21; height: 14px; }

/* Footer */
.foot { position: fixed; bottom: -0.45in; left: 0; right: 0; font-size: 7.5pt; color: #8a8c91; text-align: center; border-top: 1px solid #e3e1db; padding-top: 5px; }
<?php if ( 'html' === $mode ) : ?>
.foot { position: static; margin-top: 28px; }
<?php endif; ?>
</style>
</head>
<body>

<div class="foot">
	<?php echo $e( $d['seller']['name'] ); ?> · <?php echo $e( $d['seller']['phone'] ); ?> · <?php echo $e( $d['seller']['email'] ); ?> · <?php echo $e( $d['seller']['website'] ); ?>
	<?php if ( ! $slip ) : ?> · Returns: <?php echo $e( $d['seller']['website'] ); ?>/returns-refunds<?php endif; ?>
</div>

<table class="head">
	<tr>
		<td style="width:60%">
			<table><tr>
				<td style="width:50px"><div class="mark">J5</div></td>
				<td class="brand">
					<div class="brand-name">J5 Rescue Supply</div>
					<div class="brand-lines">
						<?php echo $e( implode( ' · ', $d['seller']['lines'] ) ); ?><br>
						<?php echo $e( $d['seller']['phone'] ); ?> · <?php echo $e( $d['seller']['email'] ); ?>
					</div>
				</td>
			</tr></table>
		</td>
		<td style="width:40%; text-align:right">
			<div class="doc-title"><?php echo $e( $d['title'] ); ?></div>
			<?php if ( ! empty( $d['badge']['text'] ) ) : ?>
				<div class="badge <?php echo $e( $tone ); ?>"><?php echo $e( $d['badge']['text'] ); ?></div>
			<?php endif; ?>
		</td>
	</tr>
</table>
<div class="rule"></div>
<div class="rule-gold" style="margin-top:2px"></div>

<?php if ( $slip ) : ?>
	<?php if ( in_array( $d['le_status'], array( 'pending', 'missing' ), true ) ) : ?>
		<div class="hold"><span class="sym">&#9888;</span> Hold: law enforcement ID not yet verified. Do not ship.</div>
	<?php elseif ( 'rejected' === $d['le_status'] ) : ?>
		<div class="hold"><span class="sym">&#9888;</span> LE ID rejected. Do not ship. Cancel and refund.</div>
	<?php elseif ( 'verified' === $d['le_status'] ) : ?>
		<div class="ok"><?php echo $tick; ?> Law enforcement ID verified. OK to ship.</div>
	<?php endif; ?>
<?php endif; ?>

<table class="parties" style="margin-top:12px">
	<tr>
		<?php if ( ! $slip ) : ?>
			<td style="width:33%">
				<div class="party-h">Bill to</div>
				<div class="party"><?php echo $d['parties']['bill_to']; // already wp_kses'd to <br> only ?></div>
				<?php if ( $d['parties']['email'] ) : ?><div class="party muted"><?php echo $e( $d['parties']['email'] ); ?></div><?php endif; ?>
				<?php if ( $d['parties']['phone'] ) : ?><div class="party muted"><?php echo $e( $d['parties']['phone'] ); ?></div><?php endif; ?>
			</td>
		<?php endif; ?>
		<td style="width:<?php echo $slip ? '55' : '33'; ?>%">
			<div class="party-h">Ship to</div>
			<div class="party" style="<?php echo $slip ? 'font-size:12pt; font-weight:600; line-height:1.35' : ''; ?>"><?php echo $d['parties']['ship_to']; ?></div>
			<?php if ( $slip && $d['parties']['phone'] ) : ?><div class="party muted"><?php echo $e( $d['parties']['phone'] ); ?></div><?php endif; ?>
		</td>
		<td style="width:<?php echo $slip ? '45' : '34'; ?>%">
			<table class="facts">
				<?php foreach ( $d['facts'] as $k => $v ) : ?>
					<tr><td class="k"><?php echo $e( $k ); ?></td><td class="v"><?php echo $e( $v ); ?></td></tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
</table>

<table class="items">
	<thead>
		<tr>
			<th style="width:48px"></th>
			<th>Item</th>
			<th class="ctr" style="width:42px">Qty</th>
			<?php if ( $slip ) : ?>
				<th class="ctr" style="width:52px">Packed</th>
			<?php else : ?>
				<th class="num" style="width:76px">Unit</th>
				<th class="num" style="width:84px">Total</th>
			<?php endif; ?>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $d['items'] as $i => $it ) :
		$img = 'pdf' === $mode ? $it['img_path'] : $it['img_url'];
		?>
		<tr class="<?php echo 1 === $i % 2 ? 'zebra' : ''; ?>">
			<td><?php echo $img ? '<img class="thumb" src="' . $e( $img ) . '" alt="">' : '<div class="thumb-empty"></div>'; ?></td>
			<td>
				<div class="name"><?php echo $e( $it['name'] ); ?></div>
				<?php if ( $it['sku'] ) : ?><div class="sku">SKU <?php echo $e( $it['sku'] ); ?></div><?php endif; ?>
				<?php if ( $it['meta'] ) : ?>
					<div class="meta">
						<?php
						$parts = array();
						foreach ( $it['meta'] as $m ) {
							// Packers don't need option prices: "XE strap (+$6.00)" -> "XE strap".
							$val     = $slip ? preg_replace( '/\s*\([+-][^)]*\)\s*$/u', '', $m['value'] ) : $m['value'];
							$parts[] = '<b>' . $e( $m['label'] ) . ':</b> ' . $e( $val );
						}
						echo implode( ' &nbsp;·&nbsp; ', $parts ); // escaped above
						?>
					</div>
				<?php endif; ?>
				<?php if ( $it['note'] ) : ?><div class="note"><?php echo $e( $it['note'] ); ?></div><?php endif; ?>
				<?php if ( $it['le_only'] ) : ?><div class="le">Law enforcement only</div><?php endif; ?>
				<?php foreach ( $it['acks'] as $a ) : ?>
					<div class="ack"><?php echo $tick; ?> Acknowledged: <?php echo $e( $a['title'] ); ?><?php echo $a['when'] ? ' · ' . $e( $a['when'] ) : ''; ?></div>
				<?php endforeach; ?>
			</td>
			<td class="ctr" style="font-weight:600; font-size:<?php echo $slip ? '12pt' : '10pt'; ?>"><?php echo $e( $it['qty'] ); ?></td>
			<?php if ( $slip ) : ?>
				<td class="ctr"><div class="box"></div></td>
			<?php else : ?>
				<td class="num"><?php echo $e( $it['unit'] ); ?></td>
				<td class="num" style="font-weight:600"><?php echo $e( $it['total'] ); ?></td>
			<?php endif; ?>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php if ( ! $slip && $d['totals'] ) : ?>
	<table class="totals-wrap"><tr>
		<td style="width:52%; padding-right:16px">
			<?php if ( 'invoice' === $d['type'] && ! empty( $d['due'] ) ) : ?>
				<div class="panel due">
					<div class="panel-h red">Amount due</div>
					<div class="amt"><?php echo $e( $d['due']['amount'] ); ?></div>
					<?php if ( $d['due']['link'] ) : ?>
						<p style="margin-top:6px">Pay online: <span style="color:#c41e24; font-weight:600"><?php echo $e( $d['due']['link'] ); ?></span></p>
					<?php endif; ?>
					<p class="muted">Questions? <?php echo $e( $d['seller']['phone'] ); ?> · <?php echo $e( $d['seller']['email'] ); ?></p>
				</div>
			<?php else : ?>
				<div class="panel">
					<div class="panel-h">Thank you</div>
					<p>This receipt confirms payment for order #<?php echo $e( $d['number'] ); ?>. Keep it for your records.</p>
				</div>
			<?php endif; ?>
		</td>
		<td style="width:48%">
			<table class="totals">
				<?php foreach ( $d['totals'] as $t ) : ?>
					<tr class="<?php echo $e( $t['key'] ); ?>">
						<td class="lbl"><?php echo $e( $t['label'] ); ?></td>
						<td class="val"><?php echo $e( $t['value'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr></table>
	<?php echo j5doc_html_extras( $d, true, true, $e, $tick ); ?>
<?php endif; ?>

<?php if ( $slip && ( $d['order_acks'] || '' !== $d['note'] ) ) : ?>
	<table><tr>
		<td style="width:50%; padding-right:8px"><?php echo j5doc_html_extras( $d, true, false, $e, $tick ); ?></td>
		<td style="width:50%; padding-left:8px"><?php echo j5doc_html_extras( $d, false, true, $e, $tick ); ?></td>
	</tr></table>
<?php endif; ?>

<?php if ( $slip ) : ?>
	<table class="sign">
		<tr>
			<td style="width:30%">Packed by<div class="line"></div></td>
			<td style="width:5%"></td>
			<td style="width:30%">Checked by<div class="line"></div></td>
			<td style="width:5%"></td>
			<td style="width:30%">Date<div class="line"></div></td>
		</tr>
	</table>
<?php endif; ?>

</body>
</html>
	<?php
	return ob_get_clean();
}

/** Checkout acknowledgments and customer note panels. */
function j5doc_html_extras( $d, $acks, $note, $e, $tick ) {
	$out = '';
	if ( $acks && $d['order_acks'] ) {
		$out .= '<div class="panel"><div class="panel-h">Acknowledgments affirmed at checkout</div>';
		foreach ( $d['order_acks'] as $a ) {
			$out .= '<p>' . $tick . ' ' . $e( $a['title'] ) . ( $a['when'] ? ' <span class="muted">· ' . $e( $a['when'] ) . '</span>' : '' ) . '</p>';
		}
		$out .= '</div>';
	}
	if ( $note && '' !== $d['note'] ) {
		$out .= '<div class="panel"><div class="panel-h">Customer note</div><p>' . nl2br( $e( $d['note'] ) ) . '</p></div>';
	}
	return $out;
}
