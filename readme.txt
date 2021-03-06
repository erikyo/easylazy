=== EasyLazy ⚡⚡⚡ ===
Contributors: Erik
Tags: lazyload, webp, pagespeed
Requires at least: 3.0
Requires PHP: 5.6
Stable tag: 0.0.1
Tested up to: 5.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optimization tool that convert and serve webp images without any configuration and without breaking your website!

== Description ==
Without making any effort you can easily convert and serve automatically webp images with lazyload to speedup your website consistently and with zero configuration.
* Convert images to webp on upload. uploaded files will be duplicated in the same folder with the .webp suffix (so image.jpg will become ALSO image.jpg.webp)
* Automatically serve generated webp images with lazy-loading, but if the image is missing or the browser doesn't support this feature will fallback with the original image.
* Is designed so you don't have to change any code or configurations or other ⚠ danger stuff, just install the plugin and your site will be amazingly ⚡ faster ⚡

== Setup ==
* Install the plugin.
* To generate the webp copy you need to generate (or regenerate) the thumbnails of that image. This is triggered automatically since the plugin is installed but for all the images that were already in media you may want to use a plugin to do so (indeed, suggested).
* When you are logged into Wordpress you will see a red /orange border around images without webp version. This is useful for finding images that needs to be regenerated, and will be shown only for logged users.

== Support ==
Community support via the [support forums](https://wordpress.org/support/plugin/eazylazy/) on wordpress.org
Open an issue on [GitHub](https://github.com/erikyo/eazylazy)

= Contribute =
We love your input! We want to make contributing to this project as easy and transparent as possible, whether it's:
* Reporting a bug
* Testing the plugin with different user agent and report fingerprinting failures
* Discussing the current state, features, improvements
* Submitting a fix or a new feature

We use github to host code, to track issues and feature requests, as well as accept pull requests.
By contributing, you agree that your contributions will be licensed under its GPLv2 License.

Open an issue on [GitHub](https://github.com/erikyo/eazylazy/issues)
Contribute with a pull requests on [GitHub](https://github.com/erikyo/eazylazy/pulls)

== Constants ==
LAZYWEBP_ENABLED_EXTENSIONS = array()
The listedformat will be converted into webp. if you need for example to convert only png file you can use 'define( 'LAZYWEBP_ENABLED_EXTENSIONS', array( 'png' ) );'

LAZYWEBP_ANIMATED = true|false
Display the lazyloaded images with a short but fancy fade-in animation

EASYLAZY_LARGE_IMAGE_LIMIT = 1920
the images after this width or height are larger than a mdpi monitor (a full-hd display for example).
Assuming this image is an hdpi image we can adjust the image quality to the half without noticing any artifacts

EASYLAZY_LARGE_IMAGE_QUALITY_PERCENTUAL = .5 (.5 means 50%)
this is the ratio with which the hdpi image is compressed, if the original image was at 80% quality with a ratio of ".5" the hdpi quality will be 40%.

EASYLAZY_DEFAULT_JPG_COMPRESSION = 82
the quality that is used by default to compress images. This is valid for png and gif while jpgs are compressed if possible with the same quality level that is used for jpgs

EASYLAZY_FEATURED_IMAGE_SIZE = 'large'
in order to enable featured image preloading you need to specify the correct size used for the featured image


== Changelog ==
0.0.1 Initial release

== Copyright ==
EasyLazy, Copyright 2021 Codekraft Studio
EasyLazy is distributed under the terms of the GNU GPL

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the LICENSE file for more details.