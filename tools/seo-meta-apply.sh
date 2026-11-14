#!/usr/bin/env bash
# Writes Rank Math SEO titles and meta descriptions for product categories and brands
# from tools/seo-meta-2026-11-14.json (the approved rows of the
# "J5 SEO Titles & Descriptions" Sheet, 2026-11-14).
#
#   bash seo-meta-apply.sh           dry run: shows what would change, writes nothing
#   bash seo-meta-apply.sh --apply   backs up current values, then writes
#
# Safety:
#   - Each row is checked first: the term ID must exist in the right taxonomy
#     (product_cat or product_brand) and its name must match the Sheet. Any mismatch
#     stops the whole run before anything is written.
#   - An empty description in the file means "keep the current one" (left untouched).
#   - Before writing, every current rank_math_title / rank_math_description for these
#     terms goes to /tmp/j5-seo-meta-backup-<time>.json. To undo, run with
#     --restore /tmp/j5-seo-meta-backup-<time>.json
# PHP runs from a /tmp file through `wp eval-file` (the sudo hop expands $variables).
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com
DIR="$(cd "$(dirname "$0")" && pwd)"
MODE="${1:-dry}"
ARG="${2:-}"

cp "$DIR/seo-meta-2026-11-14.json" /tmp/j5-seo-meta-data.json
chmod 644 /tmp/j5-seo-meta-data.json

cat > /tmp/j5-seo-meta-apply.php <<'PHP'
<?php
$mode = getenv( 'J5_MODE' ) ?: 'dry';
$tax  = array( 'category' => 'product_cat', 'brand' => 'product_brand' );

if ( '--restore' === $mode ) {
	$file = getenv( 'J5_ARG' );
	$bak  = json_decode( (string) @file_get_contents( $file ), true );
	if ( ! is_array( $bak ) ) { echo "STOP: can't read backup $file\n"; return; }
	foreach ( $bak as $b ) {
		foreach ( array( 'rank_math_title' => 'title', 'rank_math_description' => 'description' ) as $key => $f ) {
			if ( '' === $b[ $f ] ) delete_term_meta( $b['id'], $key ); else update_term_meta( $b['id'], $key, $b[ $f ] );
		}
	}
	echo 'restored ' . count( $bak ) . " terms from $file\n";
	return;
}

$rows = json_decode( (string) file_get_contents( '/tmp/j5-seo-meta-data.json' ), true );
if ( ! is_array( $rows ) ) { echo "STOP: can't read the data file\n"; return; }

// 1. Check every row before touching anything.
$bad = array();
foreach ( $rows as $r ) {
	$t = get_term( (int) $r['id'], $tax[ $r['type'] ] ?? '' );
	if ( ! $t || is_wp_error( $t ) ) { $bad[] = "{$r['type']} {$r['id']} ({$r['name']}): not found"; continue; }
	if ( html_entity_decode( $t->name, ENT_QUOTES ) !== $r['name'] ) $bad[] = "{$r['type']} {$r['id']}: name is '" . html_entity_decode( $t->name, ENT_QUOTES ) . "', Sheet says '{$r['name']}'";
}
if ( $bad ) { echo "STOP: nothing written. These rows don't match the site:\n  " . implode( "\n  ", $bad ) . "\n"; return; }

// 2. Show / back up / write.
$backup = array(); $nt = 0; $nd = 0;
foreach ( $rows as $r ) {
	$id  = (int) $r['id'];
	$cur = array( 'id' => $id, 'title' => (string) get_term_meta( $id, 'rank_math_title', true ), 'description' => (string) get_term_meta( $id, 'rank_math_description', true ) );
	$backup[] = $cur;
	if ( $cur['title'] !== $r['title'] ) $nt++;
	if ( '' !== $r['description'] && $cur['description'] !== $r['description'] ) $nd++;
	if ( 'dry' === $mode ) {
		printf( "%-8s %5d %-34s %s\n", $r['type'], $id, mb_substr( $r['name'], 0, 34 ), $r['title'] );
	}
}
if ( 'dry' === $mode ) { echo "\nDRY RUN: would set $nt titles and $nd descriptions on " . count( $rows ) . " terms. Run with --apply to write.\n"; return; }
if ( '--apply' !== $mode ) { echo "STOP: unknown option $mode\n"; return; }

$bf = '/tmp/j5-seo-meta-backup-' . gmdate( 'Ymd-His' ) . '.json';
if ( false === file_put_contents( $bf, wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) { echo "STOP: couldn't write the backup $bf; nothing written\n"; return; }
echo "backup: $bf\n";
foreach ( $rows as $r ) {
	update_term_meta( (int) $r['id'], 'rank_math_title', $r['title'] );
	if ( '' !== $r['description'] ) update_term_meta( (int) $r['id'], 'rank_math_description', $r['description'] );
}
echo "wrote $nt titles and $nd descriptions on " . count( $rows ) . " terms\n";
PHP
chmod 644 /tmp/j5-seo-meta-apply.php

sudo -u j5rescue -i -- env J5_MODE="$MODE" J5_ARG="$ARG" wp --path=$SITE eval-file /tmp/j5-seo-meta-apply.php 2>/dev/null
[ "$MODE" != "dry" ] && sudo -u j5rescue -i -- wp --path=$SITE cache flush >/dev/null 2>&1
exit 0
