<?php
/*
	Plugin Name: EasyLazy ⚡
	Plugin URI: https://github.com/erikyo/EasyLazy
	Description: Convert and serve automatically webp images with lazyload to speedup your website consistently with zero configuration
	Author: Erik
	Version: 0.0.1
	Author URI: https://codekraft.it/
*/

// If this file is called directly, abort.
if ( !defined( 'ABSPATH' ) ) {
    die( 'Sorry, this file cannot be accessed directly.' );
}

// Define support for webp images
if ( !defined( 'EASYLAZY_ENABLED_EXTENSIONS' ) ) {
    define( 'EASYLAZY_ENABLED_EXTENSIONS', array( 'png', 'jpg', 'jpeg', 'gif' ) );
}

// Display a fancy animation when showing the image (may score worst in CLP)
if ( !defined( 'EASYLAZY_ANIMATED' ) ) define( 'EASYLAZY_ANIMATED', true );

// Halve the quality of the images automatically if the width or the height is bigger than this value (because they are likely to be hdpi)
if ( !defined( 'EASYLAZY_SKIP_IMAGES' ) ) define( 'EASYLAZY_SKIP_IMAGES', 1 );
if ( !defined( 'EASYLAZY_LARGE_IMAGE_LIMIT' ) ) define( 'EASYLAZY_LARGE_IMAGE_LIMIT', 1920 );
if ( !defined( 'EASYLAZY_LARGE_IMAGE_QUALITY_PERCENTUAL' ) ) define( 'EASYLAZY_LARGE_IMAGE_QUALITY_PERCENTUAL', .5 ); // .5 means 50%
if ( !defined( 'EASYLAZY_DEFAULT_IMG_COMPRESSION' ) ) define( 'EASYLAZY_DEFAULT_IMG_COMPRESSION', 82 );
if ( !defined( 'EASYLAZY_FEATURED_IMAGE_SIZE' ) ) define( 'EASYLAZY_FEATURED_IMAGE_SIZE', false );


// Define support for webp images on plugin load
if( !empty($_SERVER['HTTP_ACCEPT']) && strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false ) {
    define( 'EASYLAZY_WEBP_SUPPORT', true );
}




function easylazy_init() {

	// require_once __DIR__ . '/settings.php';

    require_once __DIR__ . '/minify-html.php';

	require_once __DIR__ . '/performance-tweaks.php';

	require_once __DIR__ . '/webp-converter.php';
	require_once __DIR__ . '/webp-lazyload.php';
}
add_action( 'plugins_loaded', 'easylazy_init' );