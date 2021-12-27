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
if ( !defined( 'LAZYWEBP_ENABLED_EXTENSIONS' ) ) {
    define( 'LAZYWEBP_ENABLED_EXTENSIONS', array( 'png', 'jpg', 'jpeg', 'gif' ) );
}

// Display a fancy animation when showing the image (may score worst in CLP)
if ( !defined( 'LAZYWEBP_ANIMATED' ) ) {
	define( 'LAZYWEBP_ANIMATED', true );
}

// Define support for webp images on plugin load
if( !empty($_SERVER['HTTP_ACCEPT']) && strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false ) {
    define( 'LAZYWEBP_WEBP_SUPPORT', true );
}

// IMAGES OPTIMIZATIONS - WOOCOMMERCE
if ( class_exists( 'WooCommerce' ) ) {
	add_filter( 'woocommerce_product_get_image', 'lazywebp_webp_by_post', 10, 2 ); // $html, $post
	add_filter( 'woocommerce_single_product_image_thumbnail_html', 'lazywebp_webp_by_post_image_id', 10, 2 ); // $html, $attachment_id
}
add_filter( 'wp_get_attachment_image', 'lazywebp_webp_by_post_image_id', 10, 2 ); // $html, $attachment_id

// hijack original image to webp using a filter (also remove loading="lazy")
add_filter( 'post_thumbnail_html', 'lazywebp_webp_by_post_image_id', 10, 2 ); // $html, $post_id

// HIJACK IMAGE SRC and EXTRACT BACKGROUNDS
add_filter('the_content', 'lazywebp_filter'); // $html



/*
* WP performance tweaks
*/
add_filter( 'script_loader_tag', 'lazywebp_defer_js', 20 );
add_filter( 'script_loader_src', 'lazywebp_remove_script_version', 20 );
add_filter( 'style_loader_src', 'lazywebp_remove_script_version', 20 );
add_action( 'pre_ping', 'no_self_ping' );

add_filter('max_srcset_image_width', function() { return 700; });


// LAZYLOAD INIT
add_action("wp_footer" , 'lazywebp_lazyload', 1);


/**
* JS Defer
*/
function lazywebp_defer_js( $url ) {
    if (
        is_user_logged_in() ||
        false === strpos( $url, '.js' ) ||
        strpos( $url, 'jquery.min.js' ) ||
        strpos( $url, 'jquery.js' ) ||
        strpos( $url, 'blockUI.js' ) ||
        strpos( $url, 'underscore' ) ||
        strpos( $url, 'backbone' ) ||
        strpos( $url, 'jquery-migrate.min.js' )
    ) return $url;

    return str_replace( ' src', ' defer src', $url );
}

/**
 * Remove queries from static resources
*/
function lazywebp_remove_script_version( $src ) {
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

function load_webp_resources( &$html, $attached_file ) {
	preg_match('/\.('.implode('|',LAZYWEBP_ENABLED_EXTENSIONS).')/i', $attached_file, $extension );
	$extension = (!empty($extension[1])) ? $extension[1] : false;

	if ($extension && in_array($extension, LAZYWEBP_ENABLED_EXTENSIONS )) {
		if (file_exists($attached_file . ".webp")) {
			$html = str_replace('.'.$extension, ".$extension.webp", $html);
			$html = preg_replace( '/(\s)src=/', ' src="" data-src=', $html );
			$html = preg_replace( '/(\s)srcset=/', ' srcset="" data-srcset=', $html );
		}
	}

	return str_replace('loading="lazy"', "", $html);
}

function lazywebp_webp_by_post( $html, $post ) {

	$post_image_id = $post->get_image_id();

	$attached_file = get_attached_file($post_image_id);

	return load_webp_resources($html, $attached_file);
}

function lazywebp_webp_by_post_image_id( $html, $post_image_id ) {

	$attached_file = get_attached_file($post_image_id);

	return load_webp_resources($html, $attached_file);
}


/**
 * filters the content and replaces src with data-src (and srcset as well) and extracts the background from the inline css
 * @param $content
 *
 * @return string - the string with replaced sources
 */
function lazywebp_filter($content) {
    if (is_admin()) return $content;

    $enabled_extensions = array_merge( LAZYWEBP_ENABLED_EXTENSIONS , array('webp'));

    // replace src with data-src
    $content = preg_replace( '/(?:\s)src=("(?:[^\s]+.(?:'.implode('|',$enabled_extensions).'))")/', ' src=\'\' data-src=\\1', $content );

    // replace srcset with data-srcset
    $content = preg_replace( '/(?:\s)srcset=("(?:[^"]+.(?:'.implode('|',$enabled_extensions).')).+")/', ' srcset=\'\' data-srcset=\\1', $content );

    // replace background-image with data-lazy-bg + style without the background
    $content = preg_replace_callback(
        '/style=(?:"|\')[^<>]*?background\-image:(?: {1,}|)url(?: {1,}|)\([\'|"]?([^"\')]*)[\'|"]?\)/',
        function ($match) use ($content) {
            // the lazy background property
            $lazy_bg = 'data-background="url(\''.$match[1].'\')" ';
            // replace the original background with empty quotes
            return $lazy_bg . str_replace($match[1], '', $match[0]);
        },
        $content
    );

   return $content;
}

function lazywebp_lazyload() {
	$lazyload_style = (LAZYWEBP_ANIMATED) ?
     '.lazyload{filter: opacity(0)}.lazyloaded{animation: lazyFadeIn linear .1s;filter: opacity(1)}@keyframes lazyFadeIn{0%{filter: opacity(0);}100%{filter: opacity(1)}}' :
     '.lazyload{filter: opacity(0)}.lazyloaded{filter: opacity(1)}';
    $admin_style = '.no-webp, .no-webp-background {box-shadow: 0 0 0 6px #f44336, 0 0 0 -4px #f44336;position: relative;z-index: 1;outline: 6px dotted #ff9800;}';
    ?>
    <style><?php
        echo $lazyload_style;
        if (is_user_logged_in()) echo $admin_style;
    ?></style>
    <script async>
        "use strict";

        const page = document.getElementById("page");
        let observer; // will hold the intersection observer
        const hasWebpSupport = <?php echo (defined('LAZYWEBP_WEBP_SUPPORT')) ? 'true' : 'false' ?>;

        // check if the image exists
        async function imageExists(url, suffix = '') {
            if (url) return new Promise(function (resolve, reject) {
                let image = new Image();
                image.src = url + suffix;
                image.alt = "lazyload";
                image.onload = function () {return resolve("complete")};
                image.onloadstart = function () {return resolve("loading")};
                image.onerror = function () {return reject(`unable to locate ${url + suffix}`)};
            });
        }

        // check if the image has been loaded
        async function imageLoaded(image) {
            const imageUrl = image.src;
            if (imageUrl) return new Promise(function (resolve, reject) {
                let proxy = new Image();
                proxy.src = imageUrl;
                proxy.onload = function () {resolve()};
                proxy.onerror = function () {reject()};
            });
        }

        // show the image and remove the temp classes
        function imageUnveil(elem) {
            elem.classList.add('lazyloaded');
            elem.classList.remove('lazyload');
            delete elem.dataset.src;
            delete elem.dataset.srcset;
        }

        async function loadImage(elem) {

            if (!elem.dataset.background && !elem.dataset.src) throw new Error('EazyLazy - Missing source for ' + elem);

            let suffix = hasWebpSupport ? '.webp' : '';

            // load the background image
            if (elem.dataset.background) {

                elem.classList.add('lazyload');

                const fileExt = elem.dataset.background.split('.').pop().split(/"|\'|\)/).shift();

                const backgroundUrlWebp = ( fileExt === 'webp' ) ? fileExt : elem.dataset.background.replace( fileExt,  fileExt + suffix );

                await imageExists(backgroundUrlWebp).then(function () {

                    elem.style.backgroundImage = backgroundUrlWebp;

                }).catch(function (e) {

                    // there is no webp copy
                    elem.classList.add('no-webp-background');
                    elem.style.backgroundImage = elem.dataset.background;
                });

                elem.classList.add('lazyloaded');
                elem.classList.remove('lazyload');

                delete elem.dataset.background;


            } else if (elem.classList.contains('lazyload') || !elem.getAttribute('src')) {

                // load the image src and srcset
                const fileExt = elem.dataset.src.split('.').pop();
                // reset the suffix if the format is already webp
                if (fileExt === 'webp') suffix = '';

                elem.classList.add('lazyload');

                await imageExists(elem.dataset.src, suffix).then(function () {

                    // hijack the request to webp image format if available
                    if ( suffix !== '' ) {
                        elem.src = elem.dataset.src + suffix;
                        elem.srcset = elem.dataset.srcset ? elem.dataset.srcset.replaceAll("." + fileExt, '.' + fileExt + suffix) : '';
                    } else {
                        elem.src = elem.dataset.src;
                        if (elem.dataset.srcset) elem.srcset = elem.dataset.srcset;
                    }

                    // add a class once the image has been fully loaded
                    imageLoaded(elem).then(function () {
                        return imageUnveil(elem);
                    });

                }).catch(function (e) {
                    if (elem.dataset.src === e) return true;

                    elem.classList.add('no-webp'); // there is no webp copy

                    if (elem.dataset.src) elem.src = elem.dataset.src;
                    if (elem.dataset.srcset) elem.srcset = elem.dataset.srcset;

                    imageLoaded(elem).finally(function () {
                        return imageUnveil(elem);
                    });
                });
            }
        }

        // collect images that needs to be loaded
        function initImageObserver(entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    loadImage(entry.target).then(function () {
                        return observer.unobserve(entry.target);
                    });
                }
            });
        }

        const initMutationObserver = function (mutationsList, mutationObserver) {
            //for every mutation
            mutationsList.forEach(function (mutation) {
                //for every added element
                mutation.addedNodes.forEach(function (node) {
                    // Check if we appended a node type that isn't
                    // an element that we can search for images inside,
                    // like a text node.
                    if (typeof node.getElementsByTagName !== 'function') {
                        return;
                    }

                    const imgs = node.querySelectorAll("[data-src], [data-background]");

                    if (imgs.length) {
                        imgs.forEach(function (img) {
                            observer.observe(img);
                        });
                    }
                });
            });
        };

        // loop trough all nodes and fire a callback
        function forEachNode(nodeList, callback) {
            for (let i = 0, n = nodeList.length; i < n; i++) {
                callback(nodeList[i], i, nodeList);
            }
        }

        // the page mutation observer
        const interceptor = function (content) {
            // Create an observer instance linked to the callback function
            const mutationObserver = new MutationObserver(initMutationObserver); // Start observing the target node for configured mutations

            mutationObserver.observe(content, {
                attributes: false,
                childList: true,
                subtree: true
            });
        };

        // the image lazyload initializer
        function lazyload(content, excludedCount = 0) {

            let imgCollection = page.querySelectorAll("[data-src], [data-background]");

            if ('IntersectionObserver' in window) {

                // Let's start the intersection observer
                observer = new IntersectionObserver(initImageObserver,{ rootMargin: '100px' });

                imgCollection.forEach(function (image, index) {
                    if (index < excludedCount) {
                        loadImage(image);
                    } else {
                        // set a proxy while preloading
                        if (!image.getAttribute('src')) {

                            image.classList.add('lazyload');

                            const imageWidth = image.getAttribute('width') || 0;
                            const imageHeight = image.getAttribute('height') || 0;

                            image.src = `data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="${imageWidth}" height="${imageHeight}" viewBox="0 0 ${imageWidth} ${imageHeight}"%3E%3C/svg%3E`;
                        }

                        observer.observe(image);
                    }

                    // watch for new images added to the DOM
                    interceptor(content);

                });

            } else {

                // intersection observer is not supported, just load all the images
                imgCollection.forEach(function (image) {
                    return loadImage(image);
                });
            }
        }

        // on script load trigger immediately the lazyload
        lazyload(page);

    </script>
    <?php
}

// WEBP automatic conversion on upload
function lazywebp_explode_filepath( $filepath, $attachment_id ) {
    $uploads = wp_upload_dir();
    if ( strpos( $filepath, "/" ) == false ) {
        return array( $uploads['basedir'], "", $filepath );
    } else {
        list( $year, $month, $filename ) = explode( '/', $filepath );
        return array( $uploads['basedir'], "$year/$month", $filename );
    }
}

function lazywebp_explode_filename( $filename ) {
    $filename_parts = explode( '.', $filename );
    $fext           = $filename_parts[ count( $filename_parts ) - 1 ];
    unset( $filename_parts[ count( $filename_parts ) - 1 ] );
    $fname = implode( '.', $filename_parts );

    return array( $fname, $fext );
}

function lazywebp_save_webp_copy( $metadata, $attachment_id ) {

    list( $basedir, $path, $filename ) = lazywebp_explode_filepath( $metadata['file'], $attachment_id );
    list( $fname, $fext ) = lazywebp_explode_filename( $filename );

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

add_filter( 'wp_generate_attachment_metadata', 'lazywebp_save_webp_copy', 30, 2 );

function lazywebp_delete_webp_copy( $post_id ) {

    // get the file path for the image being deleted
    $metadata = wp_get_attachment_metadata( $post_id );

    list( $basedir, $path, $filename ) = lazywebp_explode_filepath( $metadata['file'], $post_id );
    list( $fname, $fext ) = lazywebp_explode_filename( $filename );

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
add_filter( 'delete_attachment', 'lazywebp_delete_webp_copy' );

function lazywebp_bulk_convert( $post_id ) {
    $query_images = new WP_Query( array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'fields'         => 'ids',
        'posts_per_page' => - 1,
    ) );

    if ( $query_images->have_posts() ) {
        foreach ( $query_images->posts as $imageID ) {
            lazywebp_save_webp_copy( wp_get_attachment_metadata( $imageID ), $imageID );
        }
    }
}


class lazywebp_compress_HTML {

	// Variables
	protected $html;

	public function __construct( $html ) {
		if ( ! empty( $html ) ) {
			$this->parseHTML( $html );
		}
	}

	public function parseHTML( $html ) {
		$this->html = $this->minifyHTML( $html );

        $this->html .= "\n" . $this->bottomComment( $html, $this->html );
	}

	protected function minifyHTML( $html ) {

		$search = array(
			'/\/\*([\s\S]*?)\*\/|\s+\/\/.*|<!--.*-->/', // Remove JS,HTML comments and spaces
			'/ {2,}/', // double spaces
			'/\r|\n|\t/',    // shorten multiple whitespace sequences
		);

		$replace = array(
			'',
			' ',
			'',
		);

		return preg_replace($search, $replace, $html);
	}

	protected function bottomComment( $raw, $compressed ) {
		$raw        = strlen( $raw );
		$compressed = strlen( $compressed );

		$savings = round( ( $raw - $compressed ) / $raw * 100, 2 );

		return '<!-- Compressed HTML size ' . $this->toKB($compressed) . 'KB - Original ' . $this->toKB($raw) . 'KB (saved ' . $savings . '%)-->';
	}

	protected function toKB( $bytes ) {
	    return number_format($bytes / 1024, 2);
	}

	public function __toString() {
		return $this->html;
	}
}


add_action( 'get_header', 'lazywebp_html_compression_start' );
function lazywebp_html_compression_start() {
    ob_start( 'lazywebp_html_compression_finish' );
}
function lazywebp_html_compression_finish( $html ) {
	if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
		header( "Content-Encoding: gzip" );
		return gzencode(new lazywebp_compress_HTML( $html ));
	} else {
		return new lazywebp_compress_HTML( $html );
	}
}
