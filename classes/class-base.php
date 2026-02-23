<?php
/**
 * Implements basic and common utility functions for all sub-classes.
 *
 * @package S3_Image_Optimizer
 */

namespace S3IO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common utility functions for child classes.
 */
class Base {

	/**
	 * Content directory (URL) for the plugin to use.
	 *
	 * @access protected
	 * @var string $content_url
	 */
	protected $content_url = WP_CONTENT_URL . 's3io/';

	/**
	 * Content directory (path) for the plugin to use.
	 *
	 * @access protected
	 * @var string $content_dir
	 */
	protected $content_dir = WP_CONTENT_DIR . '/s3io/';

	/**
	 * Plugin version (placeholder) for the plugin.
	 *
	 * @access protected
	 * @var float $version
	 */
	protected $version = 1.1;

	/**
	 * Prefix to be used by plugin in option and hook names.
	 *
	 * @access protected
	 * @var string $prefix
	 */
	protected $prefix = 's3io_';

	/**
	 * Set class properties for children.
	 */
	public function __construct() {
		$this->version = S3IO_VERSION;
	}

	/**
	 * Adds information to the in-memory debug log.
	 *
	 * @param string $message Debug information to add to the log.
	 */
	public function debug_message( $message ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::debug( $message );
			return;
		}
		if ( function_exists( 'ewwwio' ) ) {
			\ewwwio()->debug_message( $message );
		}
	}
}
