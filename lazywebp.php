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
if ( !defined( 'EASYLAZY_ANIMATED' ) ) {
	define( 'EASYLAZY_ANIMATED', true );
}

// Define support for webp images on plugin load
if( !empty($_SERVER['HTTP_ACCEPT']) && strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false ) {
    define( 'EASYLAZY_WEBP_SUPPORT', true );
}



/*
* WP performance tweaks
*/
add_filter( 'script_loader_src', 'easylazy_remove_script_version', 20 );
add_filter( 'style_loader_src', 'easylazy_remove_script_version', 20 );
add_action( 'pre_ping', 'no_self_ping' );

add_filter('max_srcset_image_width', function() { return 700; });

/**
 * Remove queries from static resources
*/
function easylazy_remove_script_version( $src ) {
    $parts = explode( '?ver', $src );
    return $parts[0];
}

/**
 * Disable wordpress self ping
 */
function no_self_ping( &$links ) {
    $home = get_option( 'home' );
    foreach ( $links as $l => $link )
        if ( 0 === strpos( $link, $home ) )
            unset($links[$l]);
}




// LAZYLOAD INIT
add_action("wp_footer" , 'easylazy_lazyload', 1);

function easylazy_init() {

	// require_once __DIR__ . '/settings.php';

    require_once __DIR__ . '/minify-html.php';

	require_once __DIR__ . '/webp-converter.php';
	require_once __DIR__ . '/webp-lazyload.php';
}
add_action( 'plugins_loaded', 'easylazy_init' );