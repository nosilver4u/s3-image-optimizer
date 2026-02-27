<?php
/**
 * Implements common utility functions for all classes.
 *
 * @package S3_Image_Optimizer
 */

namespace S3IO;

use Exception;
use S3IO\Aws3\Aws\Exception\AwsException;
use S3IO\Aws3\Aws\S3\Exception\S3Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common utility functions for child classes.
 */
trait Utils {

	/**
	 * The primary color, used for progress bars.
	 *
	 * @access protected
	 * @var string $admin_color
	 */
	protected $admin_color = '';

	/**
	 * A WP_Filesystem_Direct object for various file operations.
	 *
	 * @access protected
	 * @var object $filesystem
	 */
	protected $filesystem = false;

	/**
	 * Generates css include for progress bars to match admin style.
	 */
	public function progressbar_style() {
		if ( \function_exists( 'wp_add_inline_style' ) ) {
			\wp_add_inline_style( 'jquery-ui-progressbar', '.ui-widget-header { background-color: ' . $this->admin_background() . '; }' );
		}
	}

	/**
	 * Determines the background color to use based on the selected admin theme.
	 *
	 * @return string The background color in hex notation.
	 */
	protected function admin_background() {
		if ( ! empty( $this->admin_color ) && \preg_match( '/^\#([0-9a-fA-F]){3,6}$/', $this->admin_color ) ) {
			return $this->admin_color;
		}
		if ( \function_exists( 'wp_add_inline_style' ) ) {
			$user_info = \wp_get_current_user();
			global $_wp_admin_css_colors;
			if (
				\is_array( $_wp_admin_css_colors ) &&
				! empty( $user_info->admin_color ) &&
				isset( $_wp_admin_css_colors[ $user_info->admin_color ] ) &&
				\is_object( $_wp_admin_css_colors[ $user_info->admin_color ] ) &&
				\is_array( $_wp_admin_css_colors[ $user_info->admin_color ]->colors ) &&
				! empty( $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2] ) &&
				\preg_match( '/^\#([0-9a-fA-F]){3,6}$/', $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2] )
			) {
				$this->admin_color = $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2];
				return $this->admin_color;
			}
			switch ( $user_info->admin_color ) {
				case 'midnight':
					return '#e14d43';
				case 'blue':
					return '#096484';
				case 'light':
					return '#04a4cc';
				case 'ectoplasm':
					return '#a3b745';
				case 'coffee':
					return '#c7a589';
				case 'ocean':
					return '#9ebaa0';
				case 'sunrise':
					return '#dd823b';
				default:
					return '#0073aa';
			}
		}
		return '#0073aa';
	}

	/**
	 * Make sure an array/object can be parsed by a foreach().
	 *
	 * @param mixed $value A variable to test for iteration ability.
	 * @return bool True if the variable is iterable and not empty.
	 */
	protected function is_iterable( $value ) {
		return ! empty( $value ) && \is_iterable( $value );
	}

	/**
	 * Checks if a function is disabled or does not exist.
	 *
	 * @param string $function_name The name of a function to test.
	 * @param bool   $debug Whether to output debugging.
	 * @return bool True if the function is available, False if not.
	 */
	public function function_exists( $function_name, $debug = false ) {
		if ( \function_exists( '\ini_get' ) ) {
			$disabled = @\ini_get( 'disable_functions' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $debug ) {
				$this->debug_message( "disable_functions: $disabled" );
			}
		}
		if ( \extension_loaded( 'suhosin' ) && \function_exists( '\ini_get' ) ) {
			$suhosin_disabled = @\ini_get( 'suhosin.executor.func.blacklist' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $debug ) {
				$this->debug_message( "suhosin_blacklist: $suhosin_disabled" );
			}
			if ( ! empty( $suhosin_disabled ) ) {
				$suhosin_disabled = \explode( ',', $suhosin_disabled );
				$suhosin_disabled = \array_map( 'trim', $suhosin_disabled );
				$suhosin_disabled = \array_map( 'strtolower', $suhosin_disabled );
				if ( \function_exists( $function_name ) && ! \in_array( \trim( $function_name, '\\' ), $suhosin_disabled, true ) ) {
					return true;
				}
				return false;
			}
		}
		return \function_exists( $function_name );
	}

	/**
	 * Alert the user if the s3io folder could not be created within the uploads folder.
	 */
	public function make_upload_dir_remote_error() {
		echo "<div id='s3io-error-mkdir' class='error'><p>" . \esc_html__( 'Unable to create the /s3io/ working directory: could not determine local upload directory path.', 's3-image-optimizer' ) . '</p></div>';
	}

	/**
	 * Alert the user if the s3io folder could not be created within the uploads folder.
	 */
	public function make_upload_dir_failed() {
		echo "<div id='s3io-error-mkdir' class='error'><p>" . \esc_html__( 'Could not create the /s3io/ folder within the WordPress uploads folder, please adjust the permissions and try again.', 's3-image-optimizer' ) . '</p></div>';
	}

	/**
	 * Alert the user if the s3io folder is not writable.
	 */
	public function make_upload_dir_write_error() {
		echo "<div id='s3io-error-mkdir' class='error'><p>" . \esc_html__( 'The /s3io/ working directory is not writable, please check permissions for the WordPress uploads folder and /s3io/ sub-folder.', 's3-image-optimizer' ) . '</p></div>';
	}

	/**
	 * Attempt to create the s3io/ folder within the uploads directory.
	 *
	 * @return string The absolute filesystem path to the s3io/ directory.
	 */
	public function make_upload_dir() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Unlook S3 Uploads from upload_dir.
		if ( \class_exists( '\S3_Uploads' ) || \class_exists( '\S3_Uploads\Plugin' ) ) {
			$this->debug_message( 'S3_Uploads detected, removing upload_dir filters' );
			\remove_all_filters( 'upload_dir' );
		}
		$upload_dir = \wp_upload_dir( null, false, true );
		if ( false !== \strpos( $upload_dir['basedir'], 's3://' ) ) {
			$this->debug_message( "upload_dir has an s3 prefix: {$upload_dir['basedir']}" );
			\add_action( 'admin_notices', array( $this, 'make_upload_dir_remote_error' ) );
			return \trailingslashit( $upload_dir['basedir'] ) . 's3io/';
		}
		if ( ! \is_writable( $upload_dir['basedir'] ) ) {
			$this->debug_message( 'upload_dir is not writable' );
			\add_action( 'admin_notices', array( $this, 'make_upload_dir_failed' ) );
			return false;
		}
		$upload_dir = \trailingslashit( $upload_dir['basedir'] ) . 's3io/';
		if ( ! \is_dir( $upload_dir ) ) {
			$mkdir = \wp_mkdir_p( $upload_dir );
			if ( ! $mkdir ) {
				$this->debug_message( 'could not create /s3io/ working dir' );
				\add_action( 'admin_notices', array( $this, 'make_upload_dir_failed' ) );
				return false;
			}
		}
		if ( ! is_writable( $upload_dir ) ) {
			$this->debug_message( "$upload_dir is not writable" );
			\add_action( 'admin_notices', array( $this, 'make_upload_dir_write_error' ) );
			return false;
		}
		$this->debug_message( "using $upload_dir as working dir" );
		return $upload_dir;
	}

	/**
	 * Get list of buckets.
	 *
	 * @return array A list of bucket names.
	 */
	protected function get_selected_buckets() {
		if ( \defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) && \S3_IMAGE_OPTIMIZER_BUCKET ) {
			$bucket_list = array( \S3_IMAGE_OPTIMIZER_BUCKET );
		} else {
			$bucket_list = \get_option( 's3io_bucketlist' );
		}
		if ( empty( $bucket_list ) ) {
			$bucket_list = array();
			try {
				$client = \s3io()->amazon_web_services->get_client();
			} catch ( AwsException | S3Exception | Exception $e ) {
				\s3io()->errors[] = $this->format_aws_exception( $e->getMessage() );
				return $bucket_list;
			}
			try {
				$buckets = $client->listBuckets();
			} catch ( AwsException | S3Exception | Exception $e ) {
				$buckets = new \WP_Error( 's3io_exception', $this->format_aws_exception( $e->getMessage() ) );
			}
			if ( is_wp_error( $buckets ) ) {
				/* translators: %s: AWS error message */
				\s3io()->errors[] = \sprintf( \esc_html__( 'Could not list buckets: %s', 's3-image-optimizer' ), \wp_kses_post( $buckets->get_error_message() ) );
			} else {
				foreach ( $buckets['Buckets'] as $aws_bucket ) {
					$bucket_list[] = $aws_bucket['Name'];
				}
			}
		}
		return $bucket_list;
	}

	/**
	 * Check if object ownership is enforced. If so, ACLs are disabled!
	 *
	 * @param string $bucket The name of the bucket to check.
	 * @return bool True if object ownership is enforced, false otherwise or if unknown.
	 */
	public function object_ownership_enforced( $bucket ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$enforced = false;
		if ( \defined( 'S3IO_OBJECT_OWNERSHIP_ENFORCED' ) ) {
			return \S3IO_OBJECT_OWNERSHIP_ENFORCED;
		}
		if ( empty( $bucket ) ) {
			$this->debug_message( 'no bucket?' );
		}
		$this->debug_message( "checking object ownership policy for $bucket" );
		try {
			$client = \s3io()->amazon_web_services->get_client();
		} catch ( AwsException | S3Exception | Exception $e ) {
			$this->debug_message( 'unable to initialize AWS client lib: ' . $e->getMessage() );
			$client = false;
		}
		if ( $client && \is_object( $client ) ) {
			try {
				$s3result       = $client->getBucketOwnershipControls( array( 'Bucket' => $bucket ) );
				$owner_controls = $s3result->get( 'OwnershipControls' );
				if ( ! empty( $owner_controls ) && ! empty( $owner_controls['Rules'][0]['ObjectOwnership'] ) && 'BucketOwnerEnforced' === $owner_controls['Rules'][0]['ObjectOwnership'] ) {
					$enforced = true;
				}
			} catch ( AwsException | S3Exception | Exception $e ) {
				$this->debug_message( "unable to get ownership controls for $bucket: " . $e->getMessage() );
			}
		}
		return \apply_filters( 's3io_object_ownership_enforced', $enforced );
	}

	/**
	 * Check if an object exists within the given bucket.
	 *
	 * @param string $bucket The bucket on S3/Spaces.
	 * @param string $key The object key to check on S3/Spaces.
	 * @param object $client An AWS object with an established connection. Optional.
	 * @return bool True if it exists, false otherwise.
	 */
	protected function object_exists( $bucket, $key, $client = false ) {
		if ( isset( $this->images_found ) && $this->is_iterable( $this->images_found ) ) {
			foreach ( $this->images_found as $image ) {
				if ( $image['bucket'] === $bucket && $image['data']['Key'] === $key ) {
					return true;
				}
			}
		}
		if ( empty( $client ) || ! \is_object( $client ) ) {
			try {
				$client = \s3io()->amazon_web_services->get_client();
			} catch ( AwsException | S3Exception | Exception $e ) {
				\s3io()->errors[] = $this->format_aws_exception( $e->getMessage() );
				$this->debug_message( 'unable to initialize AWS client lib' );
				return false;
			}
		}
		try {
			$exists = $client->headObject(
				array(
					'Bucket' => $bucket,
					'Key'    => $key,
				)
			);
			if ( $exists ) {
				return true;
			}
		} catch ( AwsException | S3Exception | Exception $e ) {
			$s3_error = $e->getMessage();
			if ( ! \str_contains( $s3_error, '404 Not Found' ) ) {
				\s3io()->errors[] = $this->format_aws_exception( $s3_error );
				$this->debug_message( "failed to get info for $bucket / $key: $s3_error" );
			}
		}
		return false;
	}

	/**
	 * Formats an AWS exception for readability.
	 *
	 * @param string $aws_exception An error message/exception generated by the AWS SDK.
	 * @return string The error message with linebreaks added for clarity.
	 */
	protected function format_aws_exception( $aws_exception ) {
		if ( ! \defined( 'WP_CLI' ) || ! \WP_CLI ) {
			$aws_exception = str_replace( "\n", '<br>', $aws_exception );
		}
		return $aws_exception;
	}

	/**
	 * When WP CLI is in use, format, send, and reset output.
	 *
	 * @param string $output The current output buffer, usually one line at a time, but could be more.
	 * @param string $level Whether this is informational (info), a warning, or an error. Optional, defaults to 'info'.
	 * @return string An empty string if WP CLI is in use, unaltered $output otherwise.
	 */
	protected function flush_output_to_cli( $output, $level = 'info' ) {
		if ( ! empty( $output ) && \defined( 'WP_CLI' ) && \WP_CLI ) {
			$cli_output = array();
			if ( \is_string( $output ) ) {
				$cli_output = \explode( '<br>', $output );
				$output     = '';
			} elseif ( \is_array( $output ) ) {
				$cli_output = $output;
				$output     = array();
			}
			if ( ! empty( $cli_output ) ) {
				foreach ( $cli_output as $cli_message ) {
					$cli_message = \htmlspecialchars_decode( $cli_message );
					if ( 'error' === $level ) {
						\WP_CLI::error( $cli_message );
					} elseif ( 'warning' === $level ) {
						\WP_CLI::warning( $cli_message );
					} else {
						\WP_CLI::line( $cli_message );
					}
				}
			}
		}
		return $output;
	}

	/**
	 * Creates a human-readable message based on the original and optimized sizes.
	 *
	 * @param int $orig_size The original size of the image.
	 * @param int $opt_size The new size of the image.
	 * @return string A message with the percentage and size savings.
	 */
	protected function get_results_msg( $orig_size, $opt_size ) {
		if ( $opt_size >= $orig_size ) {
			$results_msg = \__( 'No savings', 's3-image-optimizer' );
		} else {
			// Calculate how much space was saved.
			$savings     = \intval( $orig_size ) - \intval( $opt_size );
			$savings_str = $this->size_format( $savings );
			// Determine the percentage savings.
			$percent = \number_format_i18n( 100 - ( 100 * ( $opt_size / $orig_size ) ), 1 ) . '%';
			// Use the percentage and the savings size to output a nice message to the user.
			$results_msg = \sprintf(
				/* translators: 1: Size of savings in bytes, kb, mb 2: Percentage savings */
				\__( 'Reduced by %1$s (%2$s)', 's3-image-optimizer' ),
				$percent,
				$savings_str
			);
		}
		return $results_msg;
	}

	/**
	 * Get a list of un-optimized images in the s3io_images table.
	 *
	 * @param string $bucket Optional bucket name to filter by.
	 * @return array List of un-optimized images, with id, path, and bucket fields.
	 */
	protected function table_get_pending( $bucket = '' ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		if ( ! empty( $bucket ) ) {
			$pending = $wpdb->get_results( $wpdb->prepare( "SELECT id,path,bucket FROM $wpdb->s3io_images WHERE bucket LIKE %s AND pending = 1", $bucket ), \ARRAY_A );
		} else {
			$pending = $wpdb->get_results( "SELECT id,path,bucket FROM $wpdb->s3io_images WHERE pending = 1", \ARRAY_A );
		}
		if ( \is_array( $pending ) && \count( $pending ) > 0 ) {
			return $pending;
		}
		return array();
	}

	/**
	 * Find the number of optimized images in the s3io_images table.
	 *
	 * @return int Number of optimized images.
	 */
	protected function table_count_optimized() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->s3io_images WHERE image_size IS NOT NULL" );
		return $count;
	}

	/**
	 * Find the number of un-optimized images in the s3io_images table.
	 *
	 * @return int Number of pending/un-optimized images.
	 */
	public function table_count_pending() {
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->s3io_images WHERE pending = 1" );
		return $count;
	}

	/**
	 * Remove all un-optimized images from the s3io_images table.
	 *
	 * @param int $id ID of image record to set to pending.
	 */
	protected function table_set_pending( $id ) {
		if ( empty( $id ) ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->s3io_images SET pending = 1 WHERE id = %d", (int) $id ) );
	}

	/**
	 * Remove all un-optimized images from the s3io_images table and unset pending flags on previously optimized images.
	 */
	public function table_clear_pending() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->s3io_images WHERE image_size IS NULL" );
		$wpdb->query( "UPDATE $wpdb->s3io_images SET pending = 0 WHERE pending = 1" );
	}

	/**
	 * Remove webp_size and webp_error data from table when a .webp image is deleted.
	 *
	 * @param string $bucket The bucket on S3/Spaces.
	 * @param string $key The object key to check on S3/Spaces.
	 */
	protected function delete_webp_info( $bucket, $key ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->s3io_images,
			array(
				'webp_size'  => 0,
				'webp_error' => 0,
			),
			array(
				'bucket' => $bucket,
				'path'   => $key,
			),
			array(
				'%d',
				'%d',
			),
			array(
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Removes an image from the s3io_images table.
	 *
	 * @param int $image_id The ID of the image to remove from the table.
	 * @return bool True if the image record was successfully removed, false otherwise.
	 */
	protected function table_delete_image( $image_id ) {
		if ( empty( $image_id ) ) {
			return false;
		}
		global $wpdb;
		return $wpdb->delete( $wpdb->s3io_images, array( 'id' => (int) $image_id ) );
	}

	/**
	 * Wipes out the s3io_images table to allow re-optimization.
	 */
	public function table_truncate() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE $wpdb->s3io_images" );
	}

	/**
	 * Check if file exists, and that it is local rather than using a protocol like http:// or phar://
	 *
	 * @param string $file The path of the file to check.
	 * @return bool True if the file exists and is local, false otherwise.
	 */
	public function is_file( $file ) {
		if ( empty( $file ) ) {
			return false;
		}
		if ( \str_contains( $file, '://' ) ) {
			return false;
		}
		if ( \str_contains( $file, 'phar://' ) ) {
			return false;
		}
		return \is_file( $file );
	}

	/**
	 * Check if a file/directory is readable.
	 *
	 * @param string $file The path to check.
	 * @return bool True if it is, false if it ain't.
	 */
	public function is_readable( $file ) {
		$this->get_filesystem();
		return $this->filesystem->is_readable( $file );
	}

	/**
	 * Check filesize, and prevent errors by ensuring file exists, and that the cache has been cleared.
	 *
	 * @param string $file The name of the file.
	 * @return int The size of the file or zero.
	 */
	public function filesize( $file ) {
		$file = \realpath( $file );
		if ( $this->is_file( $file ) ) {
			$this->get_filesystem();
			// Flush the cache for filesize.
			\clearstatcache();
			// Find out the size of the new PNG file.
			return $this->filesystem->size( $file );
		} else {
			return 0;
		}
	}

	/**
	 * Check if file is in an approved location and remove it.
	 *
	 * @param string $file The path of the file to check.
	 * @param string $dir The path of the folder constraint. Optional.
	 * @return bool True if the file was removed, false otherwise.
	 */
	public function delete_file( $file, $dir = '' ) {
		$file = \realpath( $file );
		if ( ! empty( $dir ) ) {
			return \wp_delete_file_from_directory( $file, $dir );
		}

		$wp_dir      = \realpath( ABSPATH );
		$upload_dir  = \wp_get_upload_dir();
		$upload_dir  = \realpath( $upload_dir['basedir'] );
		$content_dir = \realpath( WP_CONTENT_DIR );

		if ( false !== \strpos( $file, $upload_dir ) ) {
			return \wp_delete_file_from_directory( $file, $upload_dir );
		}
		if ( false !== \strpos( $file, $content_dir ) ) {
			return \wp_delete_file_from_directory( $file, $content_dir );
		}
		if ( false !== \strpos( $file, $wp_dir ) ) {
			return \wp_delete_file_from_directory( $file, $wp_dir );
		}
		return false;
	}

	/**
	 * Setup the filesystem class.
	 */
	public function get_filesystem() {
		require_once \ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once \ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		if ( ! \defined( 'FS_CHMOD_DIR' ) ) {
			\define( 'FS_CHMOD_DIR', ( \fileperms( \ABSPATH ) & 0777 | 0755 ) );
		}
		if ( ! \defined( 'FS_CHMOD_FILE' ) ) {
			\define( 'FS_CHMOD_FILE', ( \fileperms( \ABSPATH . 'index.php' ) & 0777 | 0644 ) );
		}
		if ( ! \is_object( $this->filesystem ) ) {
			$this->filesystem = new \WP_Filesystem_Direct( '' );
		}
	}

	/**
	 * Check the mimetype of the given file with magic mime strings/patterns.
	 *
	 * @param string $path The absolute path to the file.
	 * @return bool|string A valid mime-type or false.
	 */
	public function mimetype( $path ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( "testing mimetype: $path" );
		$type = false;
		// For S3 images/files, don't attempt to read the file, just use the quick (filename) mime check.
		if ( $this->stream_wrapped( $path ) ) {
			return $this->quick_mimetype( $path );
		}
		$path = \realpath( $path );
		if ( ! $this->is_file( $path ) ) {
			$this->debug_message( "$path is not a file, or out of bounds" );
			return $type;
		}
		if ( ! \is_readable( $path ) ) {
			$this->debug_message( "$path is not readable" );
			return $type;
		}
		$file_handle   = \fopen( $path, 'rb' );
		$file_contents = \fread( $file_handle, 4096 );
		if ( $file_contents ) {
			// Read first 12 bytes, which equates to 24 hex characters.
			$magic = \bin2hex( \substr( $file_contents, 0, 12 ) );
			$this->debug_message( $magic );
			if ( '424d' === \substr( $magic, 0, 4 ) ) {
				$type = 'image/bmp';
				$this->debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( 0 === \strpos( $magic, '52494646' ) && 16 === \strpos( $magic, '57454250' ) ) {
				$type = 'image/webp';
				$this->debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( 'ffd8ff' === \substr( $magic, 0, 6 ) ) {
				$type = 'image/jpeg';
				$this->debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( '89504e470d0a1a0a' === \substr( $magic, 0, 16 ) ) {
				$type = 'image/png';
				$this->debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( '474946383761' === \substr( $magic, 0, 12 ) || '474946383961' === \substr( $magic, 0, 12 ) ) {
				$type = 'image/gif';
				$this->debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( '25504446' === \substr( $magic, 0, 8 ) ) {
				$type = 'application/pdf';
				$this->debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( \preg_match( '/<svg/', $file_contents ) ) {
				$type = 'image/svg+xml';
				$this->debug_message( "ewwwio type: $type" );
				return $type;
			}
			$this->debug_message( "match not found for image: $magic" );
		} else {
			$this->debug_message( 'could not open for reading' );
		}
		return false;
	}

	/**
	 * Get mimetype based on file extension instead of file contents when speed outweighs accuracy.
	 *
	 * @param string $path The name of the file.
	 * @return string|bool The mime type based on the extension or false.
	 */
	public function quick_mimetype( $path ) {
		$pathextension = \strtolower( \pathinfo( $path, \PATHINFO_EXTENSION ) );
		switch ( $pathextension ) {
			case 'bmp':
				return 'image/bmp';
			case 'jpg':
			case 'jpeg':
			case 'jpe':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			case 'gif':
				return 'image/gif';
			case 'webp':
				return 'image/webp';
			case 'pdf':
				return 'application/pdf';
			case 'svg':
				return 'image/svg+xml';
			default:
				if ( empty( $pathextension ) && ! $this->stream_wrapped( $path ) && $this->is_file( $path ) ) {
					return $this->mimetype( $path, 'i' );
				}
				return false;
		}
	}

	/**
	 * Checks if there is enough memory still available.
	 *
	 * Looks to see if the current usage + padding will fit within the memory_limit defined by PHP.
	 *
	 * @param int $padding Optional. The amount of memory needed to continue. Default 1050000.
	 * @return True to proceed, false if there is not enough memory.
	 */
	public function check_memory_available( $padding = 1050000 ) {
		$memory_limit = $this->memory_limit();

		$current_memory = \memory_get_usage( true ) + $padding;
		if ( $current_memory >= $memory_limit ) {
			$this->debug_message( "detected memory limit is not enough: $memory_limit" );
			return false;
		}
		$this->debug_message( "detected memory limit is: $memory_limit" );
		return true;
	}

	/**
	 * Finds the current PHP memory limit or a reasonable default.
	 *
	 * @return int The memory limit in bytes.
	 */
	public function memory_limit() {
		if ( \defined( 'EIO_MEMORY_LIMIT' ) && \EIO_MEMORY_LIMIT ) {
			$memory_limit = \EIO_MEMORY_LIMIT;
		} elseif ( \function_exists( '\ini_get' ) ) {
			$memory_limit = \ini_get( 'memory_limit' );
		} else {
			if ( ! \defined( 'EIO_MEMORY_LIMIT' ) ) {
				// Conservative default, current usage + 16M.
				$current_memory = \memory_get_usage( true );
				$memory_limit   = \round( $current_memory / ( 1024 * 1024 ) ) + 16;
				define( 'EIO_MEMORY_LIMIT', $memory_limit );
			}
		}
		if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::debug( "memory limit is set at $memory_limit" );
		}
		if ( ! $memory_limit || -1 === \intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}
		if ( \stripos( $memory_limit, 'g' ) ) {
			$memory_limit = \intval( $memory_limit ) * 1024 * 1024 * 1024;
		} else {
			$memory_limit = \intval( $memory_limit ) * 1024 * 1024;
		}
		return $memory_limit;
	}

	/**
	 * Find out if set_time_limit() is allowed.
	 */
	public function stl_check() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( \defined( 'S3IO_DISABLE_STL' ) && \S3IO_DISABLE_STL ) {
			$this->debug_message( 'stl disabled by user' );
			return false;
		}
		if ( \function_exists( 'wp_is_ini_value_changeable' ) && ! \wp_is_ini_value_changeable( 'max_execution_time' ) ) {
			$this->debug_message( 'max_execution_time not configurable' );
			return false;
		}
		return $this->function_exists( '\set_time_limit' );
	}

	/**
	 * Clear output buffers without throwing a fit.
	 */
	public function ob_clean() {
		if ( \ob_get_length() ) {
			\ob_end_clean();
		}
	}

	/**
	 * Wrapper around size_format to remove the decimal from sizes in bytes.
	 *
	 * @param int $size A filesize in bytes.
	 * @param int $precision Number of places after the decimal separator.
	 * @return string Human-readable filesize.
	 */
	public function size_format( $size, $precision = 1 ) {
			// Convert it to human readable format.
			$size_str = \size_format( $size, $precision );
			// Remove spaces and extra decimals when measurement is in bytes.
			return \preg_replace( '/\.0+ B ?/', ' B', $size_str );
	}

	/**
	 * Trims the given 'needle' from the end of the 'haystack'.
	 *
	 * @param string $haystack The string to be modified if it contains needle.
	 * @param string $needle The string to remove if it is at the end of the haystack.
	 * @return string The haystack with needle removed from the end.
	 */
	public function remove_from_end( $haystack, $needle ) {
		$needle_length = \strlen( $needle );
		if ( \substr( $haystack, -$needle_length ) === $needle ) {
			return \substr( $haystack, 0, -$needle_length );
		}
		return $haystack;
	}

	/**
	 * Checks the filename for an S3 or GCS stream wrapper.
	 *
	 * @param string $filename The filename to be searched.
	 * @return bool True if a stream wrapper is found, false otherwise.
	 */
	public function stream_wrapped( $filename ) {
		if ( false !== \strpos( $filename, '://' ) ) {
			if ( \strpos( $filename, 's3' ) === 0 ) {
				return true;
			}
			if ( \strpos( $filename, 'gs' ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A wrapper for PHP's parse_url, prepending assumed scheme for network path
	 * URLs. PHP versions 5.4.6 and earlier do not correctly parse without scheme.
	 *
	 * @param string  $url The URL to parse.
	 * @param integer $component Retrieve specific URL component.
	 * @return mixed Result of parse_url.
	 */
	public function parse_url( $url, $component = -1 ) {
		if ( empty( $url ) ) {
			return false;
		}
		if ( \str_starts_with( $url, '//' ) ) {
			$url = ( \is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		if ( ! \str_starts_with( $url, 'http' ) && ! \str_starts_with( $url, '/' ) && ! \str_starts_with( $url, '.' ) ) {
			$url = ( \is_ssl() ? 'https://' : 'http://' ) . $url;
		}
		// Because encoded ampersands in the filename break things.
		$url = \html_entity_decode( $url );
		return \parse_url( $url, $component );
	}
}
