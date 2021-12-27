<?php

// WEBP automatic conversion on upload
function easylazy_explode_filepath( $filepath, $attachment_id ) {
	$uploads = wp_upload_dir();
	if ( strpos( $filepath, "/" ) == false ) {
		return array( $uploads['basedir'], "", $filepath );
	} else {
		list( $year, $month, $filename ) = explode( '/', $filepath );
		return array( $uploads['basedir'], "$year/$month", $filename );
	}
}

function easylazy_explode_filename( $filename ) {
	$filename_parts = explode( '.', $filename );
	$fext           = $filename_parts[ count( $filename_parts ) - 1 ];
	unset( $filename_parts[ count( $filename_parts ) - 1 ] );
	$fname = implode( '.', $filename_parts );

	return array( $fname, $fext );
}

function easylazy_save_webp_copy( $metadata, $attachment_id ) {

	list( $basedir, $path, $filename ) = easylazy_explode_filepath( $metadata['file'], $attachment_id );
	list( $fname, $fext ) = easylazy_explode_filename( $filename );

	if ( isset( $metadata['mime-type'] ) ) {
		if ( $metadata['mime-type'] == 'pdf' ) {
			$file_collection = array_column( $metadata['sizes'], 'file' );
		} else {
			return true;
		}
	} else {
		$file_collection = array_merge( array( $fname . "." . $fext ), array_column( $metadata['sizes'], 'file' ) );
	}

	switch ( $fext ) {
		case 'jpg':
			foreach ( $file_collection as $value ) {
				$image = imagecreatefromjpeg( $basedir . '/' . $path . '/' . $value );

				imagewebp( $image, $basedir . '/' . $path . '/' . $value . '.webp', 95 );

				imagedestroy( $image );
			}
			break;

		case 'png':
			foreach ( $file_collection as $value ) {

				$image = imagecreatefrompng( $basedir . '/' . $path . '/' . $value );
				imagepalettetotruecolor( $image );
				imagealphablending( $image, true );
				imagesavealpha( $image, true );

				imagewebp( $image, $basedir . '/' . $path . '/' . $value . '.webp', 90 );

				imagedestroy( $image );
			}
			break;

		case 'gif':
			foreach ( $file_collection as $value ) {

				$image = imagecreatefromgif( $basedir . '/' . $path . '/' . $value );
				imagepalettetotruecolor( $image );
				imagealphablending( $image, true );
				imagesavealpha( $image, true );

				imagewebp( $image, $basedir . '/' . $path . '/' . $value . '.webp', 90 );

				imagedestroy( $image );
			}
			break;

		default:
			return false;
	}

	return $metadata;
}

add_filter( 'wp_generate_attachment_metadata', 'easylazy_save_webp_copy', 30, 2 );

function easylazy_delete_webp_copy( $post_id ) {

	// get the file path for the image being deleted
	$metadata = wp_get_attachment_metadata( $post_id );

	list( $basedir, $path, $filename ) = easylazy_explode_filepath( $metadata['file'], $post_id );
	list( $fname, $fext ) = easylazy_explode_filename( $filename );

	// create a fake metadata/size to add the main image to remove list
	$metadata["sizes"]["full"]["file"] = $fname . "." . $fext;

	// remove the webp copy from using the metadata sizes as iterator
	foreach ( $metadata['sizes'] as $file ) {
		if ( isset( $file['file'] ) && file_exists( $basedir . '/' . $path . '/' . $file['file'] . '.webp' ) ) {
			wp_delete_file( $basedir . '/' . $path . '/' . $file['file'] . '.webp' );
		}
	}

	return $post_id;
}
add_filter( 'delete_attachment', 'easylazy_delete_webp_copy' );










function easylazy_bulk_convert( $post_id ) {
	$query_images = new WP_Query( array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'fields'         => 'ids',
		'posts_per_page' => - 1,
	) );

	if ( $query_images->have_posts() ) {
		foreach ( $query_images->posts as $imageID ) {
			easylazy_save_webp_copy( wp_get_attachment_metadata( $imageID ), $imageID );
		}
	}
}
