<?php
/**
 * J5 Blog Index (Posts page)
 *
 * Thin loader: the posts index uses the same layout as all other
 * archive views (hero + filter pills + paginated grid). WordPress
 * does NOT fall back from home.php to archive.php on its own —
 * without this file the parent theme's index.php takes over.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require get_stylesheet_directory() . '/archive.php';
