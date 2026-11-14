#!/usr/bin/env bash
# READ-ONLY. Which blog posts, product categories and brand pages have their own
# Rank Math SEO title and meta description, and which fall back to the site defaults.
# Also prints those defaults (Titles & Meta templates), since the fallback is what
# Google sees for anything missing. Writes the full list to /tmp/j5-seo-meta-audit.csv.
# Changes nothing. PHP runs from a /tmp file through `wp eval-file` (the sudo hop
# expands $variables in inline PHP).
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com

cat > /tmp/j5-seo-meta-audit.php <<'PHP'
<?php
$o = get_option( 'rank-math-options-titles', array() );
echo "== Rank Math defaults (used when an item has no title/description of its own)\n";
foreach ( array( 'pt_post' => 'Blog posts', 'tax_product_cat' => 'Product categories', 'tax_product_brand' => 'Brands' ) as $k => $label ) {
	echo "$label\n  title:       " . ( $o[ $k . '_title' ] ?? '(none)' ) . "\n  description: " . ( $o[ $k . '_description' ] ?? '(none)' ) . "\n";
}

$rows = array();
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
	$rows[] = array( 'post', $p->ID, $p->post_title, get_permalink( $p ), '', (string) get_post_meta( $p->ID, 'rank_math_title', true ), (string) get_post_meta( $p->ID, 'rank_math_description', true ), has_excerpt( $p ) ? 'yes' : 'no' );
}
foreach ( array( 'product_cat' => 'category', 'product_brand' => 'brand' ) as $tax => $type ) {
	if ( ! taxonomy_exists( $tax ) ) { echo "\n(taxonomy $tax not found)\n"; continue; }
	foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
		$rows[] = array( $type, $t->term_id, html_entity_decode( $t->name ), get_term_link( $t ), $t->count, (string) get_term_meta( $t->term_id, 'rank_math_title', true ), (string) get_term_meta( $t->term_id, 'rank_math_description', true ), '' !== trim( wp_strip_all_tags( $t->description ) ) ? 'yes' : 'no' );
	}
}

$f = fopen( '/tmp/j5-seo-meta-audit.csv', 'w' );
fputcsv( $f, array( 'type', 'id', 'name', 'url', 'products', 'seo_title', 'seo_description', 'has_excerpt_or_description', 'title_len', 'desc_len' ) );
$sum = array();
$missing = array();
foreach ( $rows as $r ) {
	list( $type, $id, $name, $url, $count, $title, $desc ) = $r;
	fputcsv( $f, array_merge( $r, array( mb_strlen( $title ), mb_strlen( $desc ) ) ) );
	$sum[ $type ]['total'] = ( $sum[ $type ]['total'] ?? 0 ) + 1;
	$need = array();
	if ( '' === trim( $title ) ) $need[] = 'title';
	if ( '' === trim( $desc ) )  $need[] = 'description';
	if ( stripos( $title, 'archive' ) !== false ) $need[] = 'title says Archive';
	if ( $need ) {
		$sum[ $type ]['missing'] = ( $sum[ $type ]['missing'] ?? 0 ) + 1;
		$missing[ $type ][] = sprintf( '%-8s %6s  %-48s %s%s', $type, $id, mb_substr( $name, 0, 48 ), implode( ', ', $need ), ( '' !== (string) $count && 0 === (int) $count ) ? '  (0 products)' : '' );
	}
}
fclose( $f );

echo "\n== Summary\n";
foreach ( $sum as $type => $s ) printf( "%-9s %4d total, %4d need work\n", $type, $s['total'], $s['missing'] ?? 0 );
foreach ( $missing as $type => $list ) { echo "\n== $type: missing\n" . implode( "\n", $list ) . "\n"; }
echo "\nFull list: /tmp/j5-seo-meta-audit.csv\n";
PHP
chmod 644 /tmp/j5-seo-meta-audit.php
sudo -u j5rescue -i -- wp --path=$SITE eval-file /tmp/j5-seo-meta-audit.php 2>/dev/null
