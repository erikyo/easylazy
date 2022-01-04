<?php

// IMAGES OPTIMIZATIONS - WOOCOMMERCE
if ( class_exists( 'WooCommerce' ) ) {
	add_filter( 'woocommerce_product_get_image', 'easylazy_webp_by_post_image_id', 10, 2 ); // $html, $post
	add_filter( 'woocommerce_single_product_image_thumbnail_html', 'easylazy_webp_by_post_id', 10, 2 ); // $html, $product_id
}

if (!is_admin()) {

    // hijack attachment image function in replace src with data-src (same for background and srcset)
    // in order to enable lazy-load
    add_filter( 'wp_get_attachment_image', 'easylazy_webp_by_post_image_id', 10, 2 ); // $html, $post

    // hijack original image to webp using a filter (also remove loading="lazy")
    add_filter( 'post_thumbnail_html', 'easylazy_webp_by_post_image_id', 10, 2 ); // $html, $post_id

    // HIJACK IMAGE SRC and EXTRACT BACKGROUNDS
    add_filter('the_content', 'easylazy_filter'); // $html

	// Preload featured image
	add_action('wp_head', 'easylazy_featured_image_preload', 1);

    // LAZYLOAD INIT
    add_action("wp_footer" , 'easylazy_lazyload', 1);
}

function easylazy_featured_image_preload() {
	if (has_post_thumbnail() && EASYLAZY_FEATURED_IMAGE_SIZE) {
		$thumb_id = get_post_thumbnail_id( get_the_ID() );
		$featured_img_src = wp_get_attachment_image_src( $thumb_id, EASYLAZY_FEATURED_IMAGE_SIZE )[0];
		$featured_img_srcset = wp_get_attachment_image_srcset( $thumb_id );
        $link = sprintf('<link rel="preload" as="image" href="%s" srcset="%s" />', $featured_img_src, $featured_img_srcset );

		echo load_webp_resources($link, get_attached_file($thumb_id) );
	}
}

function load_webp_resources( &$html, $attached_file ) {
	preg_match('/\.('.implode('|',EASYLAZY_ENABLED_EXTENSIONS).')/i', $attached_file, $extension );
	$extension = (!empty($extension[1])) ? $extension[1] : false;

	if ($extension && in_array($extension, EASYLAZY_ENABLED_EXTENSIONS )) {
		if ( file_exists($attached_file . ".webp") ) {
			$html = str_replace('.'.$extension, ".$extension.webp", $html);
			$html = preg_replace( '/(\s)src=/', ' src="" data-src=', $html );
			$html = preg_replace( '/(\s)srcset=/', ' srcset="" data-srcset=', $html );
		}
	}

	return str_replace('loading="lazy"', "", $html);
}

function easylazy_webp_by_post_id( $html, $product ) {

	$post_image_id = get_post_thumbnail_id($product);

	return  easylazy_webp_by_post_image_id($html, $post_image_id);
}

function easylazy_webp_by_post_image_id( $html, $post_image_id ) {

	$attached_file = get_attached_file($post_image_id);

	return load_webp_resources($html, $attached_file);
}


/**
 * filters the content and replaces src with data-src (and srcset as well) and extracts the background from the inline css
 * @param $content
 *
 * @return string - the string with replaced sources
 */
function easylazy_filter($content) {
	if (is_admin()) return $content;

	$enabled_extensions = array_merge( EASYLAZY_ENABLED_EXTENSIONS , array('webp'));

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

function easylazy_lazyload() {
	$lazyload_style = (EASYLAZY_ANIMATED) ?
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
        const hasWebpSupport = <?php echo (defined('EASYLAZY_WEBP_SUPPORT')) ? 'true' : 'false' ?>;

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