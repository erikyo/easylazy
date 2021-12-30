<?php

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