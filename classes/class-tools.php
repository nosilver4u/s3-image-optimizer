<?php
/**
 * Class file for S3 IO Tools
 *
 * Implements WebP utilities.
 *
 * @package S3_Image_optimizer
 */

namespace S3IO;

use S3IO\Aws3\Aws\Exception\AwsException;
use S3IO\Aws3\Aws\S3\Exception\S3Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets up a tools page with WebP utilities. Could be used for other S3 related tasks, but all WebP for now.
 */
class Tools extends Base {

	use Utils;

	/**
	 * Setup the class and get things rolling.
	 */
	public function __construct() {
		parent::__construct();
		$this->register_hooks();
	}

	/**
	 * Setup hooks for tools page.
	 */
	public function register_hooks() {
		\add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		// The JS for all tools.
		\add_action( 'admin_enqueue_scripts', array( $this, 'tools_script' ) );
		// Scanning action to pull a list of all images on a bucket.
		\add_action( 'wp_ajax_scan_loop', array( $this, 'scan_loop' ) );
		// AJAX actions for the WebP renaming tool.
		\add_action( 'wp_ajax_webp_rename_init', array( $this, 'webp_rename_init' ) );
		\add_action( 'wp_ajax_webp_rename_loop', array( $this, 'webp_rename_loop' ) );
		\add_action( 'wp_ajax_webp_rename_cleanup', array( $this, 'webp_rename_cleanup' ) );
		// AJAX actions for the WebP cleanup/deletion tool.
		// TODO: we may be able to re-use the init/cleanup actions, or we might not even need them?
		\add_action( 'wp_ajax_webp_delete_loop', array( $this, 'webp_delete_loop' ) );
	}

	/**
	 * Setup the admin menu items for the bulk pages.
	 */
	public function admin_menu() {
		if ( ! function_exists( 'ewww_image_optimizer' ) ) {
			return;
		}
		\add_management_page(
			esc_html__( 'S3 Image Optimizer', 's3-image-optimizer' ),
			esc_html__( 'S3 Image Optimizer', 's3-image-optimizer' ),
			'activate_plugins',
			's3-image-optimizer-tools',
			array( $this, 'render_tools_page' )
		);
	}

	/**
	 * Displays the bulk migration form.
	 */
	public function render_tools_page() {
		$naming_mode = \ewwwio()->get_option( 'ewww_image_optimizer_webp_naming_mode', 'append' );
		// TODO: if anything indicates a bulk operation is in progress, bail and tell them to finish or cancel the bulk operation.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'S3 Image Optimizer', 's3-image-optimizer' ); ?></h1>
			<div id="s3io-tools-loading"></div>
			<div id="s3io-tools-progressbar"></div>
			<div id="s3io-tools-counter"></div>
			<div id="s3io-tools-status"></div>
			<h2><?php esc_html_e( 'Rename WebP Images', 's3-image-optimizer' ); ?></h2>
			<p>
				<?php if ( 'replace' === $naming_mode ) : ?>
					<?php esc_html_e( 'This tool will search all buckets for images with a .webp extension appended and convert them to replacement naming.', 's3-image-optimizer' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'This tool will search all buckets for images with a .webp extension in place of the original, and append the extension instead.', 's3-image-optimizer' ); ?>
				<?php endif; ?>
			</p>
			<div id="bulk-forms">
				<form id="webp-start" class="webp-form" method="post" action="">
					<input id="webp-first" type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Start Renaming', 's3-image-optimizer' ); ?>" />
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Scan buckets for webp images using the old naming scheme and update/rename them to the new naming scheme.
	 *
	 * @param bool $verbose Enable (true) to output WP_CLI logging. Default false.
	 */
	public function rename_loop( $verbose = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$wpcli = false;
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$wpcli = true;
		}
		$permissions = \apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
		if ( ! $wpcli && ( empty( $_REQUEST['s3io_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! \current_user_can( $permissions ) ) ) {
			die( \wp_json_encode( array( 'error' => \esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
		}
		$start               = \microtime( true );
		$file_counter        = 0;
		$naming_mode         = \ewwwio()->get_option( 'ewww_image_optimizer_webp_naming_mode', 'append' );
		$original_extensions = array( 'png', 'jpg', 'jpeg', 'gif' );
		if ( $this->stl_check() ) {
			\set_time_limit( 0 );
		}
		global $wpdb;
		s3io()->errors = array();

		try {
			$client = s3io()->amazon_web_services->get_client();
		} catch ( AwsException | S3Exception | Exception $e ) {
			s3io()->errors[] = $this->format_aws_exception( $e->getMessage() );
			if ( $wpcli ) {
				return 0;
			}
			die( \wp_json_encode( array( 'error' => s3io()->errors[0] ) ) );
		}

		$bucket_list = \s3io_get_selected_buckets();
		if ( ! empty( s3io()->errors ) ) {
			if ( $wpcli ) {
				return 0;
			}
			die( \wp_json_encode( array( 'error' => s3io()->errors[0] ) ) );
		}

		$buckets_scanned   = \get_option( 's3io_buckets_scanned' );
		$completed_buckets = $this->is_iterable( $buckets_scanned ) ? $buckets_scanned : array();
		$paginator         = \get_option( 's3io_bucket_paginator' );
		foreach ( $bucket_list as $bucket ) {
			$this->debug_message( "scanning $bucket" );
			if ( $verbose && $wpcli ) {
				/* translators: %s: S3 bucket name */
				WP_CLI::line( \sprintf( __( 'Scanning bucket %s...', 's3-image-optimizer' ), $bucket ) );
			}
			if ( in_array( $bucket, $completed_buckets, true ) ) {
				$this->debug_message( "skipping $bucket, already done" );
				continue;
			}
			try {
				$location = $client->getBucketLocation( array( 'Bucket' => $bucket ) );
			} catch ( AwsException | S3Exception | Exception $e ) {
				$location = new \WP_Error( 's3io_exception', $this->format_aws_exception( $e->getMessage() ) );
			}
			if ( \is_wp_error( $location ) && ( ! defined( 'S3_IMAGE_OPTIMIZER_REGION' ) || ! S3_IMAGE_OPTIMIZER_REGION ) ) {
				/* translators: 1: bucket name 2: AWS error message */
				s3io()->errors[] = \sprintf( \esc_html__( 'Could not get bucket location for %1$s, error: %2$s. Will assume us-east-1 region for all buckets. You may set the region manually using the S3_IMAGE_OPTIMIZER_REGION constant in wp-config.php.', 's3-image-optimizer' ), $bucket, wp_kses_post( $location->get_error_message() ) );
				if ( ! $wpcli ) {
					die( \wp_json_encode( array( 'error' => s3io()->errors[0] ) ) );
				}
			}
			$paginator_args = array( 'Bucket' => $bucket );
			if ( defined( 'S3_IMAGE_OPTIMIZER_FOLDER' ) && S3_IMAGE_OPTIMIZER_FOLDER ) {
				$paginator_args['Prefix'] = \ltrim( S3_IMAGE_OPTIMIZER_FOLDER, '/' );
			}
			if ( $paginator && is_string( $paginator ) ) {
				$this->debug_message( "starting from $paginator" );
				$paginator_args['Marker'] = $paginator;
			}

			// In case you need to modify the arguments to the $client->getPaginator() call before they are used.
			$paginator_args = apply_filters( 's3io_scan_iterator_args', $paginator_args );

			$results = $client->getPaginator( 'ListObjects', $paginator_args );

			$already_optimized = $wpdb->get_results( $wpdb->prepare( "SELECT path,image_size FROM $wpdb->s3io_images WHERE bucket LIKE %s", $bucket ), ARRAY_A );
			$optimized_list    = array();
			foreach ( $already_optimized as $optimized ) {
				$optimized_path                    = $optimized['path'];
				$optimized_list[ $optimized_path ] = (int) $optimized['image_size'];
			}
			if ( function_exists( 'ewww_image_optimizer_stl_check' ) && ewww_image_optimizer_stl_check() ) {
				set_time_limit( 0 );
			}
			try {
				foreach ( $results as $result ) {
					foreach ( $result['Contents'] as $object ) {
						++$file_counter;
						$path = $object['Key'];
						$this->debug_message( "$file_counter: checking $path" );

						if ( ! \str_ends_with( $path, '.webp' ) ) {
							continue;
						}

						$original_path = $this->remove_from_end( $path, '.webp' );
						$info          = \pathinfo( $original_path );
						$ext           = \strtolower( $info['extension'] ?? '' );
						$is_real_ext   = \in_array( $ext, $original_extensions, true );
						if ( 'append' === $naming_mode ) {
							if ( $is_real_ext ) {
								continue;
							}
							foreach ( $original_extensions as $ext ) {
								if ( $this->is_file( $original_path . '.' . $ext ) || $this->is_file( $original_path . '.' . strtoupper( $ext ) ) ) {
									ewwwio_debug_message( "queued $path" );
									$list[] = $path;
									break;
								}
							}
						} elseif ( 'replace' === $naming_mode ) {
							if ( ! $is_real_ext ) {
								continue;
							}
							if ( ewwwio_is_file( $original_path ) ) {
								ewwwio_debug_message( "queued $path" );
								$list[] = $path;
							}
						}
					}
				}
			} catch ( AwsException | S3Exception | Exception $e ) {
				/* translators: 1: bucket name 2: AWS error message */
				s3io()->errors[] = sprintf( esc_html__( 'Incorrect region for %1$s, please set the region using the S3_IMAGE_OPTIMIZER_REGION constant in wp-config.php. Error: %2$s.', 's3-image-optimizer' ), $bucket, wp_kses_post( $this->format_aws_exception( $e->getMessage() ) ) );
				if ( ! $wpcli ) {
					die( wp_json_encode( array( 'error' => s3io()->errors[0] ) ) );
				}
			}
			$paginator = '';
			update_option( 's3io_bucket_paginator', $paginator, false );
			$completed_buckets[] = $bucket;
			$this->debug_message( "adding $bucket to the completed list" );
			update_option( 's3io_buckets_scanned', $completed_buckets, false );
		}

		$elapsed = microtime( true ) - $start;
		$this->debug_message( "query time for $file_counter files (seconds): $elapsed" );
		return $list;
	}

	/**
	 * Prepares the migration and includes the javascript functions.
	 *
	 * @param string $hook The hook identifier for the current page.
	 */
	public function tools_script( $hook ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Make sure we are being called from the migration page.
		if ( 'admin_page_s3-image-optimizer-tools' !== $hook ) {
			return;
		}
		s3io_make_upload_dir();
		// TODO: are there options that need reset every time, like when someone refreshes the page?
		// Remove the images array from the db if it currently exists, and then store the new list in the database.
		if ( get_option( 'ewww_image_optimizer_webp_images' ) ) {
			delete_option( 'ewww_image_optimizer_webp_images' );
		}
		wp_enqueue_script( 'ewwwwebpscript', plugins_url( '/s3io.js', S3IO_PLUGIN_FILE ), array( 'jquery' ), S3IO_VERSION );
		// Submit a couple variables to the javascript to work with.
		wp_localize_script(
			'ewwwwebpscript',
			'ewww_vars',
			array(
				's3io_wpnonce'     => wp_create_nonce( 's3io-bulk' ),
				'interrupted'      => esc_html__( 'Operation Interrupted', 's3-image-optimizer' ),
				'retrying'         => esc_html__( 'Temporary failure, attempts remaining:', 's3-image-optimizer' ),
				'invalid_response' => esc_html__( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 's3-image-optimizer' ),
			)
		);
		wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( 'jquery-ui-1.10.1.custom.css', S3IO_PLUGIN_FILE ), array(), S3IO_VERSION );
		$this->progressbar_style();
	}

	/**
	 * Called by JS/AJAX to initialize some output.
	 */
	public function webp_rename_init() {
		// Verify that an authorized user has started the migration.
		$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
		if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-webp' ) || ! current_user_can( $permissions ) ) {
			ewwwio_ob_clean();
			die( esc_html__( 'Access denied.', 's3-image-optimizer' ) );
		}
		// Generate the WP spinner image for display.
		$loading_image = plugins_url( '/images/wpspin.gif', S3IO_PLUGIN_FILE );
		// Let the user know that we are beginning.
		ewwwio_ob_clean();
		die( '<p>' . esc_html__( 'Scanning', 's3-image-optimizer' ) . '&nbsp;<img src="' . esc_url( $loading_image ) . '" /></p>' );
	}

	/**
	 * Called by javascript to process each image in the queue.
	 */
	public function webp_rename_loop() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
		if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-webp' ) || ! current_user_can( $permissions ) ) {
			ewwwio_ob_clean();
			die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
		}
		if ( empty( $_REQUEST['webp_images'] ) || ! is_array( $_REQUEST['webp_images'] ) ) {
			ewwwio_ob_clean();
			die( wp_json_encode( array( 'error' => esc_html__( 'No images to migrate.', 'ewww-image-optimizer' ) ) ) );
		}
		// Retrieve the time when the migration starts.
		$started = microtime( true );
		if ( ewww_image_optimizer_stl_check() ) {
			set_time_limit( 0 );
		}
		ewwwio_debug_message( 'renaming images now' );
		$images_processed = 0;
		$images_renamed   = 0;
		$output           = '';
		$images           = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['webp_images'] ) );
		while ( $images ) {
			++$images_processed;
			ewwwio_debug_message( "processed $images_processed images so far" );
			$image = array_pop( $images );
			if ( ! ewwwio_is_file( $image ) ) {
				ewwwio_debug_message( "skipping $image because it is not a file, or not in a permitted folder" );
				continue;
			}
			$replace_base        = '';
			$skip                = true;
			$naming_mode         = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_naming_mode', 'append' );
			$extensionless       = ewwwio()->remove_from_end( $image, '.webp' );
			$info                = pathinfo( $extensionless );
			$ext                 = strtolower( $info['extension'] ?? '' );
			$original_extensions = array( 'png', 'jpg', 'jpeg', 'gif' );
			$is_real_ext         = in_array( $ext, $original_extensions, true );
			if ( 'replace' === $naming_mode ) {
				if ( $is_real_ext && ewwwio_is_file( $extensionless ) ) {
					$replace_base = $extensionless;
					$skip         = false;
				}
			} elseif ( 'append' === $naming_mode ) {
				if ( $is_real_ext ) {
					continue;
				}
				foreach ( $original_extensions as $img_ext ) {
					if ( ewwwio_is_file( $extensionless . '.' . $img_ext ) || ewwwio_is_file( $extensionless . '.' . strtoupper( $img_ext ) ) ) {
						if ( ! empty( $replace_base ) ) {
							$skip = true;
							break;
						}
						$replace_base = $extensionless . '.' . $img_ext;
						$skip         = false;
					}
				}
			}
			if ( $skip ) {
				if ( $replace_base ) {
					ewwwio_debug_message( "multiple replacement options for $image, not renaming" );
				} else {
					ewwwio_debug_message( "no match found for $image, strange..." );
				}
				/* translators: %s: a webp file */
				$output .= sprintf( esc_html__( 'Skipped %s, could not determine original image path', 'ewww-image-optimizer' ), esc_html( $image ) ) . '<br>';
			} else {
				$new_webp_path = ewww_image_optimizer_get_webp_path( $replace_base );
				if ( is_file( $new_webp_path ) ) {
					ewwwio_debug_message( "$new_webp_path already exists, deleting $image" );
					ewwwio_delete_file( $image );
					/* translators: 1: a webp file 2: another webp file */
					$output .= sprintf( esc_html__( '%1$s already exists, removed %2$s', 'ewww-image-optimizer' ), esc_html( $new_webp_path ), esc_html( $image ) ) . '<br>';
					continue;
				}
				++$images_renamed;
				ewwwio_debug_message( "renaming $image with match of $replace_base to $new_webp_path" );
				rename( $image, $new_webp_path );
			}
		} // End while().
		if ( $images_renamed ) {
			/* translators: %d: number of images */
			$output .= sprintf( esc_html__( 'Renamed %d WebP images', 'ewww-image-optimizer' ), (int) $images_renamed ) . '<br>';
		}

		// Calculate how much time has elapsed since we started.
		$elapsed = microtime( true ) - $started;
		ewwwio_debug_message( "took $elapsed seconds this time around" );
		// Store the updated list of images back in the database.
		echo wp_json_encode(
			array(
				'output' => $output,
			)
		);
		die();
	}

	/**
	 * Called by JS/AJAX to cleanup after ourselves.
	 */
	public function webp_rename_cleanup() {
		$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
		if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-webp' ) || ! current_user_can( $permissions ) ) {
			ewwwio_ob_clean();
			die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
		}
		ewwwio_ob_clean();
		// and let the user know we are done.
		die( '<p><b>' . esc_html__( 'Finished', 'ewww-image-optimizer' ) . '</b></p>' );
	}
}
