<?php
/*
	Plugin Name: EasyLazy
	Plugin URI: https://github.com/erikyo/EasyLazy
	Description: Convert and serve automatically webp images with lazyload to speedup your website consistently and with zero-config.
	Author: Erik
	Version: 0.0.1
	Author URI: https://codekraft.it/
*/

// If this file is called directly, abort.
if ( !defined( 'ABSPATH' ) ) {
    die( 'Sorry, this file cannot be accessed directly.' );
}

add_filter('the_content', 'lazywebp_filter');
add_filter('wp_footer', 'lazywebp_filter');
add_filter('post_thumbnail_html', 'lazywebp_filter');
add_action("wp_body_open" , 'lazywebp_lazyload', 1);

function lazywebp_filter($content) {
    if (is_admin()) return $content;
    $content = preg_replace( '/(\s)src=/', ' src=\'\' data-src=', $content );
    $content = preg_replace( '/(\s)srcset=/', ' srcset=\'\' data-srcset=', $content );
    return $content;
}

function lazywebp_lazyload() {
    ?>
    <style>.lazyloading,.lazyload{filter: opacity(0)}img.lazyloaded{animation: lazyFadeIn linear .2s;filter: opacity(1)}@keyframes lazyFadeIn{0%{filter: opacity(0);}100%{filter: opacity(1)}}</style>
    <script>

      hasWebpSupport = e => document.createElement('canvas').toDataURL('image/webp').indexOf('data:image/webp') === 0;

      // check if the image exists
      async function imageExists(url, suffix = '') {
        if (url) return new Promise((resolve, reject) => {
          var image = new Image();
          image.src = url + suffix;
          if (image.complete) resolve("complete");
          image.onload = () => resolve("loading");
          image.onerror = () => reject(`unable to locate ${url+suffix}`)
        });
      }

      // check if the image has been loaded
      async function imageLoaded(url) {
        if (url) return new Promise((resolve, reject) => {
          var image = new Image();
          if (image.complete) resolve();
          image.onerror = () => reject(`unable to locate ${url}`)
        });
      }

      function forEachNode(nodeList, func) {
        for ( let i = 0, n = nodeList.length; i < n; i++ ) {
          func(nodeList[i], i, nodeList);
        }
      }

      async function loadImage(elem, hasWebpSupport) {

        const suffix = hasWebpSupport ? '.webp' : '';

        if ( elem.classList.contains('lazyload') || !elem.getAttribute('src') || !elem.complete ) {
          await imageExists(elem.dataset.src, suffix)
            .then((res) => {

              elem.classList.add('lazyload');

              // store the file extension
              const fileExt = elem.dataset.src.split('.').pop();

              // hijack the request to webp image format if available
              if (suffix) {
                elem.src = elem.dataset.src + suffix;
                elem.srcset = elem.dataset.srcset ? elem.dataset.srcset.replaceAll("." + fileExt, `.${fileExt + suffix}`) : '';
              } else {
                elem.src = elem.dataset.src;
                elem.srcset = elem.dataset.srcset;
              }

              // add a class once the image has been fully loaded
              imageLoaded(elem.dataset.src)
                .then(() => {elem.classList.add('lazyloaded')})
                .catch(() => {elem.classList.add('lazyload-error')})
            })
            .catch(() => {
              // there is no webp copy
              elem.classList.add('no-webp');

              if (elem.dataset.src) {
                elem.src = elem.dataset.src;
                elem.srcset = elem.dataset.srcset;

                imageLoaded(elem.dataset.src)
                  .then(() => {elem.classList.add('lazyloaded')})
                  .catch(() => {elem.classList.add('lazyload-error')})
              }
            })
            .then(() => {
              // clean
              elem.classList.remove('lazyload');
              delete elem.dataset.src;
              delete elem.dataset.srcset;
            })
        }
      }

      // collect images that needs to be loaded
      function initImageObserver(entries, observer) {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            loadImage(entry.target, hasWebpSupport);
          }
        });
      }

      function lazyload() {
        const content = document.getElementById('page');
        const imgCollection = content.querySelectorAll('img, picture');

        // start the intersection observer
        if ('IntersectionObserver' in window) {
          const observer = new IntersectionObserver(initImageObserver);
          imgCollection.forEach(image => {
            if (!image.getAttribute('src')) {
              image.onload = () => {
                image.src = `data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${image.naturalWidth} ${image.naturalHeight}"%3E%3C/svg%3E`
              }
            }
            image.classList.add('lazyload');
            observer.observe(image)
          });
        } else {
          imgCollection.forEach( image => loadImage(image) );
        }
      }

      // init lazyload
      document.addEventListener('DOMContentLoaded', () => {
        lazyload();
      });

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