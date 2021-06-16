=== S3 Image Optimizer ===
Contributors: nosilver4u
Tags: amazon, s3, image, optimize, optimization, photo, picture, seo, compression, wp-cli
Requires at least: 5.5
Tested up to: 5.7
Requires PHP: 7.1
Stable tag: 2.4
License: GPLv3

Compress images in Amazon S3 buckets using lossless and lossy optimization methods via the EWWW Image Optimizer.

== Description ==

The S3 Image Optimizer will optimize all your images in 1-1,000+ Amazon S3 buckets and optimize them using the EWWW Image Optimizer. Since EWWW IO integrates directly with plugins like WP Offload Media, S3 IO is generally for folks who use a solution other than WP Offload Media to put their images on S3. But, if you have 20 sites sharing an S3 bucket, or have lots of buckets, and you would want to optimize them all from one place instead of 20 different sites, this is the plugin for you.

S3 IO features a web-based bulk optimization process, and a WP-CLI interface for massive buckets. S3 IO is also compatible with Digital Ocean Spaces.

== Installation ==

First, it is worth noting that S3 IO is "site agnostic". For example, if you have images for http://www.example.com in your S3 bucket, you do NOT have to run S3 IO on the WordPress install for example.com. You could install it at test.com, or myfuzzybunnies.com, or any site you manage. In fact, you may even create a dedicated WordPress install just for running S3 Image Optimizer, with no other plugins needed except EWWW IO and S3 IO.

= Setup =

Now that we have that cleared up, let's get down to business. You need 2 plugins to make this work:  S3 Image Optimizer, and the [EWWW Image Optimizer](https://wordpress.org/plugins/ewww-image-optimizer/). Then...

* Make sure you have configured EWWW I.O. with the settings you want to use.
* [Setup your AWS access keys](https://docs.ewww.io/article/61-creating-an-amazon-web-services-aws-user), and then enter your access keys on the S3 IO settings page and save to confirm them.
* Enter the buckets you wish to optimize in the appropriate text area. Leave it empty to have the plugin optimize all your buckets.
* You may also define constants to restrict S3 IO to a specific bucket and/or sub-folder: S3_IMAGE_OPTIMIZER_BUCKET and S3_IMAGE_OPTIMIZER_FOLDER. These override the bucket list on the settings page, and will look like this (note the lack of leading/trailing slashes on the folder setting):

`define( 'S3_IMAGE_OPTIMIZER_BUCKET', 'my-amazing-bucket-name' );
define( 'S3_IMAGE_OPTIMIZER_FOLDER', 'wp-content/uploads' );`

* If your IAM user does not have access to list all buckets, you will generally also need to configure the region, something like this:

`define( 'S3_IMAGE_OPTIMIZER_REGION', 'eu-west-1' );`

[View the full list of region names.](http://docs.aws.amazon.com/general/latest/gr/rande.html#s3_region)

= Usage =
* Go to Media->S3 Bulk Optimizer to start optimizing your bucket(s).
* Use Media->S3 URL Optimizer to optimize specific images by their url/address.
* Use WP-CLI to optimize your buckets from the command line, especially useful for large buckets or scheduling bulk optimization: `wp-cli help s3io optimize`

== Frequently Asked Questions ==

= What happens if I have so many images that the S3 Bulk Optimizer keeps timing out? =

If you have configured S3 IO to optimize all your buckets, try a single bucket to see if that will work. If that still doesn't work, use the S3_IMAGE_OPTIMIZER_FOLDER setting above to restrict optimization to a specific folder. This way you can optimize a single bucket by configuring each folder within the bucket, running the S3 Bulk Optimizer, and then moving to the next folder.

If the last option has you groaning, see if your web host supports WP-CLI. Using WP-CLI allows you to avoid any timeouts, and solves a whole host of issues with long-running processes. The `wp-cli help s3io optimize` command should get you going.

If you've tried everything, and WP-CLI isn't an option with your web host, find a web host that DOES support WP-CLI. It's pretty easy to find a cheap host that supports WP-CLI, like GoDaddy. While I wouldn't recommend GoDaddy for hosting, if you want a cheap solution to run WP-CLI, it works. Remember that S3 IO is site agnostic, so you can run it from a site completely separate from the site(s) that your S3 images belong to. You could also fire up a Digital Ocean droplet with WordPress pre-installed for $5 and put WP-CLI on there. When you're done, you can make a backup image of the droplet and destroy it so that you aren't paying for usage all the time.

= What about X, or Y, or maybe even Z? =

Most problems we've seen are either permissions-related, or covered by the timeout stuff above. If you have a question, [shoot us an email](https://ewww.io/contact-us/)!

== Changelog ==

= 2.4 =
* fixed: failure in creating s3io/ working directory silently breaks bulk tools
* fixed: sanitation for error messages was too aggressive
* fixed: listBuckets error displayed even when S3_IMAGE_OPTIMIZER_BUCKET is defined

= 2.3 =
* fixed: conflict getting local uploads directory when S3 Uploads plugin is active

= 2.2 =
* added: generate and upload WebP version of your images in accordance with EWWW IO settings (WebP Conversion and Force WebP)
* fixed bulk toggle-arrow styling
* additional sanitizing and escaping for better security

= 2.1 =
* updated AWS SDK to v3, let us know if you encounter errors
* catch errors when defined region is incorrect
* added ability to remove license key (e.g. if you entered it wrong)
* rewrote bucket scanning to use AJAX in order to avoid timeouts
* fixed delay not working for WP-CLI
* display configuration errors when run from WP-CLI
* fixed S3IO_DOSPACES constant not working
* fixed scanner broken on objects with apostrophes
* fixed URL optimizer with url-encoded characters (like spaces = %20)

= 2.0 =
* added compatibility with Digital Ocean Spaces
* lots of code cleanup and sanitation

= 1.9 =
* prevent s3io_resume option from autoloading
* reset s3io_resume after completion

= 1.8 =
* fixed error with redeclaring ewwwio_debug_message() in some edge cases
* fixed bulk optimizer UI bugs

= 1.7 =
* problem with AWS object context (not global when it needs to be)
* updated plugin updater class
* updated AWS SDK

= 1.6 =
* integrate AWS SDK and remove external AWS plugin dependency
* catch errors better when AWS keys are not configured

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
