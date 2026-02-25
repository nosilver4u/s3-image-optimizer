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
	 * A list of images processed in a given loop/iteration.
	 *
	 * @var array $images_found
	 */
	protected $images_found = array();

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
		// AJAX action for the WebP renaming tool.
		\add_action( 'wp_ajax_s3io_webp_rename_loop', array( $this, 'webp_rename_loop' ) );
		// AJAX action for the WebP cleanup/deletion tool.
		\add_action( 'wp_ajax_s3io_webp_delete_loop', array( $this, 'webp_delete_loop' ) );
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
	 * Prepares the migration and includes the javascript functions.
	 *
	 * @param string $hook The hook identifier for the current page.
	 */
	public function tools_script( $hook ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Make sure we are being called from the migration page.
		if ( 'tools_page_s3-image-optimizer-tools' !== $hook ) {
			return;
		}
		$this->make_upload_dir();
		// Check to see if we are supposed to reset the bulk operation and verify we are authorized to do so.
		if ( ! empty( $_REQUEST['s3io_reset_bulk'] ) && ! empty( $_REQUEST['s3io_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk-reset' ) ) {
			\update_option( 's3io_webp_rename_resume', '', false );
			\update_option( 's3io_webp_delete_resume', '', false );
			\update_option( 's3io_bucket_paginator', '', false );
			\update_option( 's3io_buckets_scanned', '', false );
		}
		wp_enqueue_script( 's3iobulkscript', plugins_url( '/s3io.js', S3IO_PLUGIN_FILE ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar', 'postbox', 'dashboard' ), S3IO_VERSION );
		// Submit a couple variables to the javascript to work with.
		wp_localize_script(
			's3iobulkscript',
			's3io_vars',
			array(
				'_wpnonce'              => wp_create_nonce( 's3io-bulk' ),
				'operation_interrupted' => esc_html__( 'Operation Interrupted', 's3-image-optimizer' ),
				'temporary_failure'     => esc_html__( 'Temporary failure, seconds left to retry:', 's3-image-optimizer' ),
				'finished'              => esc_html__( 'Finished', 's3-image-optimizer' ),
				'invalid_response'      => esc_html__( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 's3-image-optimizer' ),
			)
		);
		wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( 'jquery-ui-1.10.1.custom.css', S3IO_PLUGIN_FILE ), array(), S3IO_VERSION );
		$this->progressbar_style();
	}

	/**
	 * Displays the bulk migration form.
	 */
	public function render_tools_page() {
		$naming_mode      = \ewwwio()->get_option( 'ewww_image_optimizer_webp_naming_mode', 'append' );
		$s3io_bulk_resume = \get_option( 's3io_resume' );
		$rename_resume    = \get_option( 's3io_webp_rename_resume' );
		$delete_resume    = \get_option( 's3io_webp_delete_resume' );
		$buckets_scanned  = \get_option( 's3io_buckets_scanned' );
		$paginator        = \get_option( 's3io_bucket_paginator' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'S3 Image Optimizer', 's3-image-optimizer' ); ?></h1>
		<?php if ( ! empty( $s3io_bulk_resume ) ) : ?>
			<p>
				<?php esc_html_e( 'A bulk operation appears to be in progress. Please wait for it to complete or reset the process on the S3 Bulk Optimizer page.', 's3-image-optimizer' ); ?>
			</p>
		<?php endif; ?>
			<div id="s3io-tools-loading" style="display: none;">
				<?php esc_html_e( 'Scanning', 's3-image-optimizer' ); ?>
				<img src="<?php echo esc_url( plugins_url( '/wpspin.gif', S3IO_PLUGIN_FILE ) ); ?>" />
			</div>
			<div id="s3io-tools-counter"></div>
			<div id="s3io-tools-status"></div>
			<div class="s3io-tool-info">
				<h2><?php esc_html_e( 'Rename WebP Images', 's3-image-optimizer' ); ?></h2>
				<p>
					<?php if ( 'replace' === $naming_mode ) : ?>
						<?php esc_html_e( 'This tool will search all buckets for images with a .webp extension appended and convert them to replacement naming.', 's3-image-optimizer' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'This tool will search all buckets for images with a .webp extension in place of the original, and append the extension instead.', 's3-image-optimizer' ); ?>
					<?php endif; ?>
				</p>
			</div>
			<div class="s3io-tool-form">
				<form id="s3io-webp-rename" class="s3io-tool-form" method="post" action="">
					<input type="submit" class="button-primary action" value="<?php esc_attr_e( 'Start Renaming', 's3-image-optimizer' ); ?>" />
				</form>
			</div>
		<?php if ( ! empty( $rename_resume ) || ! empty( $buckets_scanned ) || ! empty( $paginator ) ) : ?>
			<p class="s3io-tool-info">
				<?php esc_html_e( 'A previous operation was not completed, will resume from last image processed.', 's3-image-optimizer' ); ?>
			</p>
			<form id="s3io-tool-reset" class="s3io-tool-form" method="post" action="">
				<?php wp_nonce_field( 's3io-bulk-reset', 's3io_wpnonce' ); ?>
				<input type="hidden" name="s3io_reset_bulk" value="1">
				<button type="submit" class="button-secondary action"><?php esc_html_e( 'Reset progress and start over', 's3-image-optimizer' ); ?></button>
			</form>
		<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Scan buckets for webp images using the old naming scheme and update/rename them to the new naming scheme.
	 *
	 * @param bool $verbose Enable (true) to output WP_CLI logging. Default false.
	 */
	public function webp_rename_loop( $verbose = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$wpcli = false;
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$wpcli = true;
		}
		$permissions = \apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
		if ( ! $wpcli && ( empty( $_REQUEST['s3io_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! \current_user_can( $permissions ) ) ) {
			die( \wp_json_encode( array( 'error' => \esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
		}

		global $wpdb;
		$start_time          = \microtime( true );
		$already_done        = ! empty( $_POST['completed'] ) ? (int) $_POST['completed'] : 0;
		$images_processed    = 0;
		$images_renamed      = 0;
		$output              = '';
		$naming_mode         = \ewwwio()->get_option( 'ewww_image_optimizer_webp_naming_mode', 'append' );
		$original_extensions = array( 'png', 'jpg', 'jpeg', 'gif' );

		if ( $this->stl_check() ) {
			\set_time_limit( 0 );
		}
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

		$bucket_list = $this->get_selected_buckets();
		if ( ! empty( s3io()->errors ) ) {
			if ( $wpcli ) {
				return 0;
			}
			die( \wp_json_encode( array( 'error' => s3io()->errors[0] ) ) );
		}

		\update_option( 's3io_webp_rename_resume', true, false );

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
				$s3_error = \sprintf( \esc_html__( 'Could not get bucket location for %1$s, error: %2$s. You may set the region manually using the S3_IMAGE_OPTIMIZER_REGION constant in wp-config.php.', 's3-image-optimizer' ), $bucket, wp_kses_post( $location->get_error_message() ) );
				if ( $wpcli ) {
					WP_CLI::error( $s3_error );
				}
				die( \wp_json_encode( array( 'error' => $s3_error ) ) );
			}

			$ownership_control_enforced = $this->object_ownership_enforced( $bucket );

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
						$elapsed = microtime( true ) - $start_time;
						if ( ! $wpcli && $elapsed > 20 ) {
							$this->debug_message( "query time for $images_processed files (seconds): $elapsed" );
							// Find out if our nonce is on it's last leg/tick.
							$tick = wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' );
							/* translators: %d: number of images */
							$counter_msg = sprintf( esc_html__( 'Checked %d images', 's3-image-optimizer' ), intval( $images_processed + $already_done ) );
							if ( ! empty( $images_renamed ) ) {
								/* translators: %d: number of images */
								$output .= sprintf( esc_html__( 'Renamed %d WebP images', 's3-image-optimizer' ), (int) $images_renamed ) . '<br>';
							}
							wp_die(
								wp_json_encode(
									array(
										'output'      => $output,
										'counter_msg' => $counter_msg,
										'completed'   => $images_processed, // Number of images scanned in current iteration/loop.
										'new_nonce'   => 2 === (int) $tick ? wp_create_nonce( 's3io-bulk' ) : false,
									)
								)
							);
						}
						$this->images_found[] = array(
							'bucket' => $bucket,
							'data'   => $object,
						);
						++$images_processed;
						$path = $object['Key'];
						$this->debug_message( "$images_processed: checking $path" );

						if ( ! \str_ends_with( $path, '.webp' ) ) {
							continue;
						}

						$replace_base  = '';
						$original_path = $this->remove_from_end( $path, '.webp' );
						$info          = \pathinfo( $original_path );
						$ext           = \strtolower( $info['extension'] ?? '' );
						$is_real_ext   = \in_array( $ext, $original_extensions, true );
						if ( 'append' === $naming_mode ) {
							if ( $is_real_ext ) {
								continue;
							}
							foreach ( $original_extensions as $ext ) {
								$original_lpath = $original_path . '.' . $ext;
								$original_upath = $original_path . '.' . strtoupper( $ext );
								// This works without checking the bucket name because the query for the $optimized_list is refreshed after each bucket.
								if ( isset( $optimized_list[ $original_lpath ] ) ) {
									if ( ! empty( $replace_base ) ) {
										/* translators: 1: S3 bucket name 2: a webp file */
										$output .= sprintf( esc_html__( '%1$s: Skipped %2$s, could not determine original image path', 's3-image-optimizer' ), esc_html( $bucket ), esc_html( $path ) ) . '<br>';
										$output  = $this->flush_output_to_cli( $output );
										continue 2;
									}
									$replace_base = $original_lpath;
								} elseif ( isset( $optimized_list[ $original_upath ] ) ) {
									if ( ! empty( $replace_base ) ) {
										/* translators: 1: S3 bucket name 2: a webp file */
										$output .= sprintf( esc_html__( '%1$s: Skipped %2$s, could not determine original image path', 's3-image-optimizer' ), esc_html( $bucket ), esc_html( $path ) ) . '<br>';
										$output  = $this->flush_output_to_cli( $output );
										continue 2;
									}
									$replace_base = $original_upath;
								} elseif ( $this->object_exists( $bucket, $original_lpath, $client ) ) {
									if ( ! empty( $replace_base ) ) {
										/* translators: 1: S3 bucket name 2: a webp file */
										$output .= sprintf( esc_html__( '%1$s: Skipped %2$s, could not determine original image path', 's3-image-optimizer' ), esc_html( $bucket ), esc_html( $path ) ) . '<br>';
										$output  = $this->flush_output_to_cli( $output );
										continue 2;
									}
									$replace_base = $original_lpath;
								} elseif ( $this->object_exists( $bucket, $original_upath, $client ) ) {
									if ( ! empty( $replace_base ) ) {
										/* translators: 1: S3 bucket name 2: a webp file */
										$output .= sprintf( esc_html__( '%1$s: Skipped %2$s, could not determine original image path', 's3-image-optimizer' ), esc_html( $bucket ), esc_html( $path ) ) . '<br>';
										$output  = $this->flush_output_to_cli( $output );
										continue 2;
									}
									$replace_base = $original_upath;
								}
							}
						} elseif ( 'replace' === $naming_mode ) {
							if ( ! $is_real_ext ) {
								continue;
							}
							if ( $this->object_exists( $bucket, $original_path, $client ) ) {
								$replace_base = $original_path;
							}
						}
						try {
							$new_webp_path = ewww_image_optimizer_get_webp_path( $replace_base );
							if ( $new_webp_path && $this->object_exists( $bucket, $new_webp_path ) ) {
								$this->debug_message( "$new_webp_path already exists, deleting $path" );
								$client->deleteObject(
									array(
										'Bucket' => $bucket,
										'Key'    => $path,
									)
								);
								/* translators: 1: a webp file 2: an S3 bucket name 3: another webp file */
								$output .= sprintf( esc_html__( '%1$s: %2$s already exists, removed %3$s', 's3-image-optimizer' ), esc_html( $bucket ), esc_html( $new_webp_path ), esc_html( $path ) ) . '<br>';
								$output  = $this->flush_output_to_cli( $output );
							} elseif ( $new_webp_path ) {
								++$images_renamed;
								$this->debug_message( "renaming $path with match of $replace_base to $new_webp_path" );
								$client->copyObject(
									array(
										'Bucket'      => $bucket,
										'CopySource'  => \trailingslashit( $bucket ) . $path,
										'Key'         => $new_webp_path,
										'ContentType' => 'image/webp',
									)
								);
								if ( ! $ownership_control_enforced ) {
									$client->putObjectAcl(
										array(
											'Bucket' => $bucket,
											'Key'    => $new_webp_path,
											'ACL'    => 'public-read',
										)
									);
								}
								$client->deleteObject(
									array(
										'Bucket' => $bucket,
										'Key'    => $path,
									)
								);
								/* translators: 1: a webp file 2: another webp file 3: bucket name */
								$output .= sprintf( esc_html__( '%1$s: %2$s renamed to %3$s', 's3-image-optimizer' ), esc_html( $bucket ), esc_html( $path ), esc_html( $new_webp_path ) ) . '<br>';
								$output  = $this->flush_output_to_cli( $output );
							}
						} catch ( AwsException | S3Exception | Exception $e ) {
							/* translators: 1: file name 2: bucket name 3: AWS error message */
							$s3_error = sprintf( esc_html__( 'Error encountered while renaming %1$s in %2$s. Error: %3$s.', 's3-image-optimizer' ), esc_html( $path ), esc_html( $bucket ), wp_kses_post( $this->format_aws_exception( $e->getMessage() ) ) );
							$s3_error = $this->flush_output_to_cli( $s3_error, 'error' );
							die( wp_json_encode( array( 'error' => $s3_error ) ) );
						}
					} // End foreach object.
				} // End foreach result (page or something of that nature).
			} catch ( AwsException | S3Exception | Exception $e ) {
				/* translators: 1: bucket name 2: AWS error message */
				$s3_error = sprintf( esc_html__( 'Error encountered while scanning %1$s. You may need to set the region using the S3_IMAGE_OPTIMIZER_REGION constant in wp-config.php. Error: %2$s.', 's3-image-optimizer' ), $bucket, wp_kses_post( $this->format_aws_exception( $e->getMessage() ) ) );
				$s3_error = $this->flush_output_to_cli( $s3_error, 'error' );
				die( \wp_json_encode( array( 'error' => $s3_error ) ) );
			}
			$paginator = '';
			\update_option( 's3io_bucket_paginator', $paginator, false );
			$completed_buckets[] = $bucket;
			$this->debug_message( "adding $bucket to the completed list" );
			\update_option( 's3io_buckets_scanned', $completed_buckets, false );
		} // End foreach bucket.

		$elapsed = \microtime( true ) - $start_time;
		$this->debug_message( "query time for $images_processed files (seconds): $elapsed" );
		\update_option( 's3io_webp_rename_resume', '', false );
		\update_option( 's3io_buckets_scanned', '', false );
		if ( ! $wpcli ) {
			/* translators: %d: number of images */
			$counter_msg = sprintf( esc_html__( 'Checked %d images', 's3-image-optimizer' ), intval( $images_processed + $already_done ) );
			if ( ! empty( $images_renamed ) ) {
				/* translators: %d: number of images */
				$output .= sprintf( esc_html__( 'Renamed %d WebP images', 's3-image-optimizer' ), (int) $images_renamed ) . '<br>';
			}
			wp_die(
				wp_json_encode(
					array(
						'output'      => $output,
						'counter_msg' => $counter_msg,
						'completed'   => $images_processed, // Number of images scanned in this iteration/loop.
						'done'        => 1,
					)
				)
			);
		}
		/* translators: %d: number of images */
		WP_CLI::line( \sprintf( __( 'Checked %d images', 's3-image-optimizer' ), $images_processed ) );
		/* translators: %d: number of images */
		WP_CLI::line( \sprintf( __( 'Renamed %d WebP images', 's3-image-optimizer' ), $images_renamed ) );
	}

	/**
	 * Scan buckets for webp image copies and remove them.
	 *
	 * @param bool $verbose Enable (true) to output WP_CLI logging. Default false.
	 */
	public function webp_delete_loop( $verbose = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$wpcli = false;
	}
}
