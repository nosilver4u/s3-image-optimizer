=== S3 Image Optimizer ===
Contributors: nosilver4u
Tags: amazon, s3, cloudfront, image, optimize, optimization, photo, picture, seo, compression, wp-cli
Requires at least: 4.4
Tested up to: 4.8
Stable tag: 1.5
License: GPLv3

Reduce file sizes for images in S3 buckets using lossless and lossy optimization methods via the EWWW Image Optimizer.

== Description ==

The S3 Image Optimizer is a WordPress plugin that will allow you to retrieve all the images within one or more Amazon S3 buckets and optimize them using the EWWW Image Optimizer. It also requires the Amazon Web Services plugin to work with Amazon S3.

It currently uses a web-based optimization process, but a wp-cli interface is on the roadmap, as well as a hosted version dedicated exclusively to optimizing S3 buckets.

== Installation ==

1. Install and configure the EWWW Image Optimizer plugin.
1. Install the [Amazon Web Services plugin](http://wordpress.org/extend/plugins/amazon-web-services/) using WordPress' built-in installer.
1. Follow the instructions to setup your AWS access keys.
1. Install this plugin via Wordpress' built-in plugin installer.
1. Enter S3 bucket names under Settings and S3 Image Optimizer.
1. As noted on the settings page, you can also define constants to restrict S3 IO to a specific bucket and/or sub-folder: S3_IMAGE_OPTIMIZER_BUCKET and S3_IMAGE_OPTIMIZER_FOLDER

= Usage =

* Go to Media and S3 Bulk Optimizer to start optimizing your bucket.
* Use Media->S3 URL Optimizer to optimize specific images by their url/address.
* Use WP-CLI to optimize your buckets from the command line, especially useful for large buckets or scheduling bulk optimization:  wp-cli help s3io optimize

== Frequently Asked Questions ==

= Why aren't there any questions here? =

Start asking, and then we'll see what needs answering: https://ewww.io/contact-us/

== Changelog ==

= 1.5 =
* catch error when AWS plugin is not properly configured

= 1.4 =
* catch permissions errors on individual files
* removed undefined constant
* fixed undefined variable during wp-cli operation

= 1.3 =
* fixed error when using empty() on a constant that breaks really old PHP installs (5.4 or lower)

= 1.2 =
* catch fatal errors when S3 permissions are not sufficient
* upgrade plugin updater class
* added S3_IMAGE_OPTIMIZER_REGION to set region manually when permissions are too restrictive

= 1.1 =
* table updates more efficient and robust, searches by id first, and only by path if that fails
* fixed potential issue with images optimized by url not being stored in database

= 1.0 =
* fixed issues with checking that a constant is empty in PHP <5.5
* make sure to remove the leading slash from S3_IMAGE_OPTIMIZER_FOLDER

= .9 =
* added WP-CLI interface: 'wp-cli help s3io optimize' for more information
* added constants to define bucket and sub-folder to optimize: S3_IMAGE_OPTIMIZER_BUCKET and S3_IMAGE_OPTIMIZER_FOLDER
* fixed memory overload when running bulk operation with large s3 buckets
* ported bulk optimizer improvements from core EWWW IO: renewable nonce for longer running operations, show last optimized image on top, collapsible and draggable ui from WP core, less AJAX requests
* added escaping for all html to prevent any code injection from translations or database, and use JS for sleeping to avoid DOS by sleep timer
* added S3 URL Optimizer to optimize individual images by their URL

= .8 =
* fixed fatal error when bucket/account requires v4 authentication
* prevent debug information from displaying on settings page improperly

= .7 =
* fixed fatal error when bucket location is empty (us-east) in accounts with mixed regions

= .6 =
* fixed fatal error when bucket region is not set properly

= .5 =
* fixed fatal error when other plugins are using EDD SL Updates class
* fixed acl not set when updating images

= .4 =
* ported table schema fixes from EWWW I.O.
* added option for Frankfurt S3 authentication method

= .3 =
* automatic update checking and license activation

= .2 =
* bugfixes

= .1 =
* First release
