<?php
/**
 * Minimal autoloader for the bundled dompdf 3.1.0 and its dependencies
 * (php-font-lib 1.0.1, php-svg-lib 1.0.0, masterminds/html5 2.9.0,
 * sabberworm/php-css-parser 8.8.0). Replaces Composer's.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
spl_autoload_register( function ( $class ) {
	static $map = null;
	$base = __DIR__ . '/';
	if ( null === $map ) {
		$map = array(
			'Dompdf\\'         => $base . 'Dompdf/',
			'FontLib\\'        => $base . 'FontLib/',
			'Svg\\'            => $base . 'Svg/',
			'Masterminds\\'    => $base . 'Masterminds/',
			'Sabberworm\\CSS\\' => $base . 'Sabberworm/',
		);
	}
	if ( 'Dompdf\\Cpdf' === $class ) {
		require_once $base . 'lib/Cpdf.php';
		return;
	}
	foreach ( $map as $prefix => $dir ) {
		if ( 0 === strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			$file = $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
			return;
		}
	}
} );
