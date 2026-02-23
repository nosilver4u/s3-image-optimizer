<?php
/**
 * S3 Image Optimizer plugin.
 *
 * @link https://ewww.io
 * @package S3_Image_Optimizer
 */

/*
Plugin Name: S3 Image Optimizer
Plugin URI: https://wordpress.org/plugins/s3-image-optimizer/
Description: Reduce file sizes for images in S3 buckets using lossless and lossy optimization methods via the EWWW Image Optimizer.
Author: Exactly WWW
Version: 2.6.1.3
Requires at least: 6.6
Requires PHP: 8.1
Requires Plugins: ewww-image-optimizer
Author URI: https://ewww.io/
License: GPLv3
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\S3IO\Plugin' ) ) {
	define( 'S3IO_VERSION', 261.32 );
	define( 'S3IO_PLUGIN_FILE', __FILE__ );
	define( 'S3IO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

	require_once S3IO_PLUGIN_DIR . 'classes/trait-utils.php';
	require_once S3IO_PLUGIN_DIR . 'classes/class-base.php';
	require_once S3IO_PLUGIN_DIR . 'classes/class-plugin.php';

	/**
	 * The main function that returns an object of class \S3IO\Plugin.
	 *
	 * @return object|S3IO\Plugin The one true S3IO\Plugin instance.
	 */
	function s3io() {
		return S3IO\Plugin::instance();
	}
	s3io();
}
