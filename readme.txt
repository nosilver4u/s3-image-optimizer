=== S3 Image Optimizer ===
Contributors: nosilver4u
Tags: s3, image, optimize, compression, wp-cli
Requires at least: 6.3
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 2.5.1
License: GPLv3

Compress images in Amazon S3 buckets using lossless and lossy optimization methods via the EWWW Image Optimizer.

== Description ==

The S3 Image Optimizer will optimize all your images in 1-1,000+ Amazon S3 buckets using the EWWW Image Optimizer. Since EWWW IO integrates directly with plugins like WP Offload Media, S3 IO is generally for folks who use a solution other than WP Offload Media to put their images on S3. But, if you have 20 sites sharing an S3 bucket, or have lots of buckets, and you would want to optimize them all from one place instead of 20 different sites, this is the plugin for you.

S3 IO features a web-based bulk optimization process, and a WP-CLI interface for massive buckets. S3 IO works with any S3-compatible storage service including Linode, Backblaze B2 and Digital Ocean Spaces.

You may report security issues through our Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/s3-image-optimizer)

== Installation ==

First, it is worth noting that S3 IO is "site agnostic". For example, if you have images for http://www.example.com in your S3 bucket, you do NOT have to run S3 IO on the WordPress install for example.com. You could install it at test.com, or myfuzzybunnies.com, or any site you manage. In fact, you may even create a dedicated WordPress install just for running S3 Image Optimizer, with no other plugins needed except EWWW IO and S3 IO.

= Setup =

Now that we have that cleared up, let's get down to business. You need 2 plugins to make this work:  S3 Image Optimizer, and the [EWWW Image Optimizer](https://wordpress.org/plugins/ewww-image-optimizer/). Then...

* Make sure you have configured EWWW IO with the settings you want to use.
* [Setup your AWS access keys](https://docs.ewww.io/article/61-creating-an-amazon-web-services-aws-user), and then enter your access keys on the S3 IO settings page and save to confirm them.
* Enter the buckets you wish to optimize in the appropriate text area. Leave it empty to have the plugin optimize all your buckets.
* You may also define constants to restrict S3 IO to a specific bucket and/or sub-folder: S3_IMAGE_OPTIMIZER_BUCKET and S3_IMAGE_OPTIMIZER_FOLDER. These override the bucket list on the settings page, and will look like this (note the lack of leading/trailing slashes on the folder setting):
`
define( 'S3_IMAGE_OPTIMIZER_BUCKET', 'my-amazing-bucket-name' );
define( 'S3_IMAGE_OPTIMIZER_FOLDER', 'wp-content/uploads' );
`

* If your IAM user does not have access to list all buckets, you will generally also need to configure the region, something like this:
`
define( 'S3_IMAGE_OPTIMIZER_REGION', 'eu-west-1' );
`

[View the full list of region names.](https://docs.aws.amazon.com/general/latest/gr/rande.html#regional-endpoints)

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

= 2.6.0 =
* added: support for buckets with object ownership enforced
* changed: improved error handling for optimize by URL
* changed: bumped minimum WP and PHP versions
* fixed: malformed image size information in optimization table

= 2.5.1 =
* changed: use updated WP coding standards
* changed: cleanup AWS SDK folder

= 2.5.0 =
* added: compatibility with EWWW Image Optimizer 7+ and better future-proofing to detect compatibility errors
* updated: AWS SDK to latest version
* updated: improved PHP 8.2 compatibility, though there are still (non-critical) deprecation notices from the AWS SDK

= 2.4.3 =
* changed: display the values of any constants defined for endpoint, region, or folder restriction
* fixed: check if the bucket list is an array before sanitizing

= 2.4.2 =
* added: define custom endpoint for any S3-compatible storage via S3_IMAGE_OPTIMIZER_ENDPOINT
* fixed: cleanup of WebP copies using incorrect path

= 2.4.1 =
* fixed: PHP notice when updating db records
* removed EDD_SL_Updater file/class

= 2.4.0 =
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
