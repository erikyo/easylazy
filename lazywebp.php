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
    <style>
      .lazyload{filter: opacity(0)}
      img.lazyloaded{animation: lazyFadeIn linear .2s;filter: opacity(1)}
      @keyframes lazyFadeIn{0%{filter: opacity(0);}100%{filter: opacity(1)}}
    </style>
    <script>

      hasWebpSupport = e => document.createElement('canvas').toDataURL('image/webp').indexOf('data:image/webp') === 0;

      // check if the image exists
      async function imageExists(url, suffix = '') {
        if (url) return new Promise((resolve, reject) => {
          const image = new Image();
          image.src = url + suffix;
          image.onload = () => resolve("complete");
          image.onloadstart = () => resolve("loading");
          image.onerror = () => reject(`unable to locate ${url+suffix}`)
        });
      }

      // check if the image has been loaded
      async function imageLoaded(image) {

        const imageUrl = image.dataset.src;

        if (imageUrl) return new Promise((resolve, reject) => {

          const proxy = new Image();
          proxy.src = imageUrl;

          proxy.onload = () => {
            resolve()
          }

          proxy.onerror = () => {
            reject(`unable to locate ${imageUrl}`)
          }

        });
      }

      async function imageSet(elem, suffix) {

        elem.classList.add('lazyload');

        await imageExists(elem.dataset.src, suffix)
          .then(() => {
            // hijack the request to webp image format if available
            if (suffix) {
              elem.src = elem.dataset.src + suffix;
              elem.srcset = elem.dataset.srcset ? elem.dataset.srcset.replaceAll( "." + fileExt, '.'+fileExt+suffix ) : '';
            } else {
              elem.src = elem.dataset.src;
              elem.srcset = elem.dataset.srcset;
            }
            // add a class once the image has been fully loaded
            imageLoaded(elem).then(() => imageUnveil(elem))
          })
          .catch(() => {
            // there is no webp copy
            elem.classList.add('no-webp');

            elem.src = elem.dataset.src;
            elem.srcset = elem.dataset.srcset;

            imageLoaded(elem).then(() => imageUnveil(elem))
          })
      }


      function imageUnveil(elem) {
        elem.classList.add('lazyloaded');
        elem.classList.remove('lazyload');
        delete elem.dataset.src;
        delete elem.dataset.srcset;
      }



      // loop trough all nodes and fire a callback
      function forEachNode(nodeList, callback) {
        for ( let i = 0, n = nodeList.length; i < n; i++ ) {
          callback(nodeList[i], i, nodeList);
        }
      }



      async function loadImage(elem) {

        if (!elem.dataset.src) return true;

        // store the file extension
        const fileExt = elem.dataset.src.split('.').pop();
        const suffix = hasWebpSupport ? '.webp' : '';

        if ( elem.classList.contains('lazyload') || !elem.getAttribute('src') || !elem.complete ) {

          await imageSet(elem, suffix)
        }
      }

      // collect images that needs to be loaded
      function initImageObserver(entries, observer) {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            loadImage(entry.target).then( () => observer.unobserve(entry.target) );
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

            var imgs = node.querySelectorAll("[data-src]");
            if (imgs.length) {
              imgCollection = page.querySelectorAll("[data-src]")
              imgs.forEach(img => loadImage(img))
            }
          })
        })
      };


      const pageWatcher = page => {
        // Create an observer instance linked to the callback function
        const mutationObserver = new MutationObserver(initMutationObserver);
        // Start observing the target node for configured mutations
        mutationObserver.observe(page, {attributes: false, childList: true, subtree: true});
      };


      function lazyload(page) {

        const imgCollection = page.querySelectorAll("[data-src]");

        // start the intersection observer
        if ('IntersectionObserver' in window) {

          const observer = new IntersectionObserver(initImageObserver);

          imgCollection.forEach(image => {

            // set a proxy while preloading
            if (!image.getAttribute('src') && !image.complete) {
              image.src = `data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${image.naturalWidth} ${image.naturalHeight}"%3E%3C/svg%3E`
              image.classList.add('lazyload');
            }

            observer.observe(image);

            // Callback function to execute when mutations are observed
            pageWatcher(page);
          });


        } else {
          // intersection observer is not supported, just load all the images
          imgCollection.forEach(image => loadImage(image));
        }

      }

      // init lazyload
      document.addEventListener('DOMContentLoaded', () => {
        const pageContainer = document.getElementById("page");
        lazyload(pageContainer)
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
