<?php
/**
 * Class file for S3IO_CLI
 *
 * S3IO_CLI contains an extension for WP-CLI to enable bulk optimization of S3 buckets via command line.
 *
 * @package S3_Image_optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Implements wp-cli extension for bulk optimizing.
 */
class S3IO_CLI extends WP_CLI_Command {
	/**
	 * Bulk Optimize S3 Images
	 *
	 * ## OPTIONS
	 *
	 * <delay>
	 * : optional, number of seconds to pause between images
	 *
	 * <force>
	 * : optional, WARNING: will immediately empty the history of previously
	 * optimized S3 images, there is no undo for this
	 *
	 * <reset>
	 * : optional, scan buckets again instead of resuming from last position
	 *
	 * <noprompt>
	 * : do not prompt, just start optimizing
	 *
	 * <verbose>
	 * : be extra noisy, including details during the scan phase
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli s3io optimize 5 --force --reset --noprompt --verbose
	 *
	 * @synopsis [<delay>] [--force] [--reset] [--noprompt] [--verbose]
	 *
	 * @param array $args A numbered array of arguments provided via WP-CLI without option names.
	 * @param array $assoc_args An array of named arguments provided via WP-CLI.
	 */
	function optimize( $args, $assoc_args ) {

		// because NextGEN hasn't flushed it's buffers...
		while ( @ob_end_flush() ); // phpcs:ignore

		if ( empty( $args[0] ) ) {
			$delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
		} else {
			$delay = $args[0];
		}

		if ( ! empty( $assoc_args['reset'] ) ) {
			update_option( 's3io_resume', '' );
			WP_CLI::line( __( 'Bulk status has been reset, starting from the beginning.', 's3-image-optimizer' ) );
		}

		// check to see if the user has asked to reset (empty) the optimized images table.
		if ( ! empty( $assoc_args['force'] ) ) {
			WP_CLI::line( __( 'Forcing re-optimization of previously processed images.', 's3-image-optimizer' ) );
			s3io_table_truncate();
		}

		/* translators: %d: number of seconds */
		WP_CLI::line( sprintf( __( 'Optimizing with a %d second pause between images.', 's3-image-optimizer' ), $delay ) );

		// Let's get started, shall we?
		ewww_image_optimizer_admin_init();

		$upload_dir = wp_upload_dir();
		$upload_dir = trailingslashit( $upload_dir['basedir'] ) . 's3io/';
		if ( ! is_dir( $upload_dir ) ) {
			$mkdir = mkdir( $upload_dir );
			if ( ! $mkdir ) {
				WP_CLI::error( __( 'Could not create the s3io folder within the WordPress uploads folder, please adjust the permissions and try again.', 's3-image-optimizer' ) );
			}
		}

		// check the 'bulk resume' option.
		$resume  = get_option( 's3io_resume' );
		$verbose = ( empty( $assoc_args['verbose'] ) ? false : true );

		if ( empty( $resume ) ) {
			s3io_table_delete_pending();
			WP_CLI::line( __( 'Scanning, this could take a while', 's3-image-optimizer' ) );
			s3io_image_scan( $verbose );
		}
		global $s3io_errors;
		if ( ! empty( $s3io_errors ) ) {
			foreach ( $s3io_errors as $error_message ) {
				WP_CLI::error( $error_message, false );
			}
			WP_CLI::halt( 1 );
		}

		$image_count = s3io_table_count_pending();
		if ( ! $image_count ) {
			WP_CLI::success( __( 'There is nothing left to optimize.', 's3-image-optimizer' ) );
		} elseif ( empty( $assoc_args['noprompt'] ) ) {
			WP_CLI::confirm(
				/* translators: %d: number of images */
				sprintf( __( 'There are %d images to be optimized.', 's3-image-optimizer' ), $image_count ) .
				' ' . __( 'Continue?', 's3-image-optimizer' )
			);
		} else {
			/* translators: %d: number of images */
			WP_CLI::line( sprintf( __( 'There are %d images to be optimized.', 's3-image-optimizer' ), $image_count ) );
		}

		update_option( 's3io_resume', true, false );

		$images_finished = 0;
		$image_total     = $image_count;
		while ( $image_count > 0 ) {
			s3io_bulk_loop( true, $verbose );
			$image_count--;
			$images_finished++;
			WP_CLI::line( __( 'Optimized:', 's3-image-optimizer' ) . " $images_finished / $image_total" );
			sleep( $delay );
		}

		// Just to make sure we cleared them all.
		$image_count  = s3io_table_count_pending();
		$image_total += $image_count;
		if ( $image_count > 0 ) {
			while ( $image_count > 0 ) {
				s3io_bulk_loop( true, $verbose );
				$image_count--;
				$images_finished++;
				WP_CLI::line( __( 'Optimized:', 's3-image-optimizer' ) . " $images_finished / $image_total" );
			}
		}
		update_option( 's3io_resume', '', false );

		if ( $image_count ) {
			// and let the user know we are done.
			WP_CLI::success( __( 'Finished Optimization!', 's3-image-optimizer' ) );
		}
	}
}

WP_CLI::add_command( 's3io', 'S3IO_CLI' );
