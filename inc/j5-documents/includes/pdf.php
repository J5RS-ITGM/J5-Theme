<?php
/**
 * J5 Documents: HTML -> PDF with the bundled dompdf.
 *
 * Remote fetching is off; only files under the allowed roots (this module's
 * fonts, the uploads folder for product images) can be read. dompdf caches
 * font metrics in a writable folder (uploads/j5-documents/font-cache).
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * @param string $html
 * @param array  $args { cache: writable dir, roots: readable dirs }
 * @return string PDF bytes
 */
function j5doc_pdf( $html, $args = array() ) {
	require_once J5DOC_DIR . 'lib/dompdf/autoload.php';

	$cache = isset( $args['cache'] ) ? $args['cache'] : J5DOC_DIR . 'cache';
	if ( ! is_dir( $cache ) ) {
		wp_mkdir_p( $cache );
	}
	$roots = isset( $args['roots'] ) ? (array) $args['roots'] : array( J5DOC_DIR );

	$options = new \Dompdf\Options();
	$options->set( 'isRemoteEnabled', false );
	$options->set( 'isPhpEnabled', false );
	$options->set( 'isHtml5ParserEnabled', true );
	$options->set( 'isFontSubsettingEnabled', true );
	$options->set( 'defaultFont', 'DejaVu Sans' );
	$options->set( 'defaultPaperSize', 'letter' );
	$options->set( 'fontDir', $cache );
	$options->set( 'fontCache', $cache );
	$options->set( 'tempDir', $cache );
	$options->set( 'chroot', array_values( array_unique( array_merge( $roots, array( J5DOC_DIR ) ) ) ) );
	$options->set( 'dpi', 96 );

	$dompdf = new \Dompdf\Dompdf( $options );
	$dompdf->loadHtml( $html, 'UTF-8' );
	$dompdf->setPaper( 'letter', 'portrait' );
	$dompdf->render();

	// Page numbers on multi-page documents.
	$canvas = $dompdf->getCanvas();
	if ( $canvas->get_page_count() > 1 ) {
		$font = $dompdf->getFontMetrics()->getFont( 'DejaVu Sans' );
		$canvas->page_text( $canvas->get_width() - 100, $canvas->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, array( 0.55, 0.55, 0.57 ) );
	}
	return $dompdf->output();
}
