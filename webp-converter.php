<?php

// WEBP automatic conversion on upload
function easylazy_explode_filepath( $filepath ) {
	$uploads = wp_upload_dir();
	if ( strpos( $filepath, "/" ) == false ) {
		return array( $uploads['basedir'], "", $filepath );
	} else {
		list( $year, $month, $filename ) = explode( '/', $filepath );
		return array( $uploads['basedir'], "$year/$month", $filename );
	}
}

function easylazy_is_large_image( $width, $height ) {
	$large_image_size = apply_filters('easylazy_large_image_size', EASYLAZY_LARGE_IMAGE_LIMIT);
	return max(array($width, $height)) > $large_image_size;
}

function easylazy_save_webp_copy( $metadata, $attachment_id ) {

	// PHP GD is required
	if ( ! extension_loaded( 'gd' ) ) return $metadata;

	// if the attachment doesn't contain resizes it isn't an image or document with previews (like pdf)
	if (empty($metadata['sizes'])) return $metadata;

	// get the image mime
	$mime = get_post_mime_type($attachment_id);


	// if not available add the full image to the file collection (is available only if the attachment is an image)
	if ( ! empty( $metadata['file'] ) ) {
		$image_path = $metadata['file'];
	} else {
		$image_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
	}

	list( $basedir, $path, $filename ) = easylazy_explode_filepath(  $image_path );

	// initialize the file sub-sizes collection
	$file_collection = $metadata['sizes'];

	// the full size image (that is already set for attachment like pdf)
	if (!isset($file_collection['full'])) {

		$main_image = array(
			'file'      => $filename,
			'width'     => $metadata['width'],
			'height'    => $metadata['height'],
			'mime-type' => $mime
		);

		if (isset($metadata['original_image'])) {
			$file_collection['scaled'] = $main_image;

			$fullsize_image = wp_get_attachment_image_src($attachment_id);

			$file_collection['full'] = array(
				'file'      => $metadata['original_image'],
				'width'     => $fullsize_image[1],
				'height'    => $fullsize_image[2],
				'mime-type' => $mime
			);
		} else {
			$file_collection['full'] = $main_image;
		}
	}


	// determine/set the quality to be used in the case of a jpg image
	$compressionQuality = EASYLAZY_DEFAULT_IMG_COMPRESSION;
	if ($mime == 'image/jpeg') {
		if ( extension_loaded('imagick') && class_exists('Imagick') ) {
			// the quality of a jpg can be compared with the webp compression with a ratio of 102.5 (82/80)
			// source:  https://www.industrialempathy.com/posts/avif-webp-quality-settings/#quality-settings-for-a-range-of-jpeg-qualities
			// getImageCompressionQuality need the exif data to work as intended otherwise will return 82
			$img = new Imagick($basedir . '/' . $path . '/' . $filename);
			$compressionQuality =  round($img->getImageCompressionQuality() * 1.025);
		}
	}

	foreach ( $file_collection as $resize => $file ) {

		if ( ! file_exists( $basedir . '/' . $path . '/' . $file['file'] ) ) {
			error_log( 'EasyLazy: image resize was missing: ' . $file['file'] );
			break;
		}

		switch ( $file['mime-type'] ) {

			case 'image/jpeg':

				$image = imagecreatefromjpeg( $basedir . '/' . $path . '/' . $file['file'] );

				$compressionQuality = easylazy_is_large_image( $file['width'], $file['height'] ) ? $compressionQuality * EASYLAZY_LARGE_IMAGE_QUALITY_PERCENTUAL : $compressionQuality;

				break;

			case 'image/png':

				$image = imagecreatefrompng( $basedir . '/' . $path . '/' . $file['file'] );
				imagepalettetotruecolor( $image );
				imagealphablending( $image, true );
				imagesavealpha( $image, true );

				break;

			case 'image/gif':

				$image = imagecreatefromgif( $basedir . '/' . $path . '/' . $file['file'] );
				imagepalettetotruecolor( $image );
				imagealphablending( $image, true );
				imagesavealpha( $image, true );

				break;

			default:

				return false;
		}

		// if the webp resize has been created, store it into wordpress metadata array
		if ( imagewebp( $image, $basedir . '/' . $path . '/' . $file['file'] . '.webp', $compressionQuality ) ) {
			// TODO: maybe an option that replace the original image with the webp version (currently a copy is created)
			$metadata['sizes'][ $resize . '_webp' ] = array(
				'file'      => $file['file'] . '.webp',
				'width'     => $file['width'],
				'height'    => $file['height'],
				'mime-type' => 'image/webp'
			);
		}

		// the destroy the image to free some server ram
		imagedestroy( $image );

	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'easylazy_save_webp_copy', 99, 2 );












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
