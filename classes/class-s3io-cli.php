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
	 * [<delay>]
	 * : optional, number of seconds to pause between images
	 *
	 * [--force]
	 * : optional, should the plugin re-optimize images that have already been processed
	 *
	 * [--reset]
	 * : optional, scan buckets again instead of resuming from last position
	 *
	 * [--webp-only]
	 * : optional, only do WebP Conversion, skip all other operations
	 *
	 * [--noprompt]
	 * : do not prompt, just start optimizing
	 *
	 * [--verbose]
	 * : be extra noisy, including details during the scan phase
	 *
	 * ## EXAMPLES
	 *
	 *     # Optimize right away with no prompt and extra output/information.
	 *     wp-cli s3io optimize --noprompt --verbose
	 *
	 *     # Optimize with 5 seconds between images, ignoring previous results and re-optimizing everything.
	 *     # Also, if a previous operation was interrupted, start over and rescan all buckets.
	 *     wp-cli s3io optimize 5 --force --reset
	 *
	 *     # Skip normal optimization and only do WebP conversion with 3 seconds between images.
	 *     wp-cli s3io optimize 3 --webp-only
	 *
	 * @synopsis [<delay>] [--force] [--reset] [--webp-only] [--noprompt] [--verbose]
	 *
	 * @param array $args A numbered array of arguments provided via WP-CLI without option names.
	 * @param array $assoc_args An array of named arguments provided via WP-CLI.
	 */
	public function optimize( $args, $assoc_args ) {

		// because NextGEN hasn't flushed it's buffers...
		while ( @ob_end_flush() ); // phpcs:ignore

		$delay = 0;
		if ( ! empty( $args[0] ) ) {
			$delay = (int) $args[0];
		}

		if ( ! empty( $assoc_args['reset'] ) ) {
			update_option( 's3io_resume', '' );
			WP_CLI::line( __( 'Bulk status has been reset, starting from the beginning.', 's3-image-optimizer' ) );
		}

		// check to see if the user has asked to reset (empty) the optimized images table.
		if ( ! empty( $assoc_args['force'] ) ) {
			WP_CLI::line( __( 'Forcing re-optimization of previously processed images.', 's3-image-optimizer' ) );
			s3io()->bulk->force = true;
		}

		if ( ! empty( $assoc_args['webp-only'] ) ) {
			if ( empty( ewwwio()->get_option( 'ewww_image_optimizer_webp' ) ) ) {
				WP_CLI::error( __( 'WebP Conversion is not enabled.', 's3-image-optimizer' ) );
			}
			WP_CLI::line( __( 'Running WebP conversion only.', 's3-image-optimizer' ) );
			s3io()->bulk->webp_only = true;
			\ewwwio()->webp_only    = true;
		}

		/* translators: %d: number of seconds */
		WP_CLI::line( sprintf( __( 'Optimizing with a %d second pause between images.', 's3-image-optimizer' ), $delay ) );

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
			s3io()->table_clear_pending();
			WP_CLI::line( __( 'Scanning, this could take a while', 's3-image-optimizer' ) );
			s3io()->bulk->image_scan( $verbose );
		}
		if ( ! empty( s3io()->errors ) ) {
			foreach ( s3io()->errors as $error_message ) {
				WP_CLI::error( $error_message, false );
			}
			WP_CLI::halt( 1 );
		}

		$image_count = s3io()->table_count_pending();
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
			s3io()->bulk->bulk_loop( $verbose );
			--$image_count;
			++$images_finished;
			WP_CLI::line( __( 'Optimized:', 's3-image-optimizer' ) . " $images_finished / $image_total" );
			sleep( $delay );
		}

		// Just to make sure we cleared them all.
		$image_count  = s3io()->table_count_pending();
		$image_total += $image_count;
		if ( $image_count > 0 ) {
			while ( $image_count > 0 ) {
				s3io()->bulk->bulk_loop( $verbose );
				--$image_count;
				++$images_finished;
				WP_CLI::line( __( 'Optimized:', 's3-image-optimizer' ) . " $images_finished / $image_total" );
			}
		}
		update_option( 's3io_resume', '', false );

		if ( $image_count ) {
			// and let the user know we are done.
			WP_CLI::success( __( 'Finished Optimization!', 's3-image-optimizer' ) );
		}
	}

	/**
	 * Rename WebP Images in S3 buckets
	 *
	 * Changes .webp image naming to conform with current naming mode in EWWW Image Optimizer.
	 * This setting may either append the .webp extension, like 'image.jpg.webp' or replace
	 * the original extension, like 'image.webp'.
	 *
	 * ## OPTIONS
	 *
	 * <reset>
	 * : optional, start over instead of resuming from last position
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli s3io webp_rename --reset
	 *
	 * @synopsis [--reset]
	 *
	 * @param array $args A numbered array of arguments provided via WP-CLI without option names.
	 * @param array $assoc_args An array of named arguments provided via WP-CLI.
	 */
	public function webp_rename( $args, $assoc_args ) {

		// because NextGEN hasn't flushed it's buffers...
		while ( @ob_end_flush() ); // phpcs:ignore

		if ( ! empty( $assoc_args['reset'] ) ) {
			\update_option( 's3io_webp_rename_resume', '', false );
			\update_option( 's3io_webp_delete_resume', '', false );
			\update_option( 's3io_bucket_paginator', '', false );
			\update_option( 's3io_buckets_scanned', '', false );
			WP_CLI::line( __( 'Renaming process has been reset, starting from the beginning.', 's3-image-optimizer' ) );
		}

		$naming_mode = \ewwwio()->get_option( 'ewww_image_optimizer_webp_naming_mode', 'append' );
		if ( 'append' === $naming_mode ) {
			WP_CLI::line( __( 'Renaming WebP images, from replace mode to append mode.', 's3-image-optimizer' ) );
		} else {
			WP_CLI::line( __( 'Renaming WebP images, from append mode to replace mode.', 's3-image-optimizer' ) );
		}
		WP_CLI::confirm( __( 'Do you wish to continue?', 's3-image-optimizer' ) );

		s3io()->tools->webp_rename_loop();
	}

	/**
	 * Delete WebP Images in S3 buckets
	 *
	 * Checks for .webp images that are copies of an original JPG, PNG, or GIF and removes them.
	 * There is no undo, proceed with extreme caution.
	 *
	 * ## OPTIONS
	 *
	 * <reset>
	 * : optional, start over instead of resuming from last position
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli s3io webp_delete --reset
	 *
	 * @synopsis [--reset]
	 *
	 * @param array $args A numbered array of arguments provided via WP-CLI without option names.
	 * @param array $assoc_args An array of named arguments provided via WP-CLI.
	 */
	public function webp_delete( $args, $assoc_args ) {

		// because NextGEN hasn't flushed it's buffers...
		while ( @ob_end_flush() ); // phpcs:ignore

		if ( ! empty( $assoc_args['reset'] ) ) {
			\update_option( 's3io_webp_rename_resume', '', false );
			\update_option( 's3io_webp_delete_resume', '', false );
			\update_option( 's3io_bucket_paginator', '', false );
			\update_option( 's3io_buckets_scanned', '', false );
			WP_CLI::line( __( 'Renaming process has been reset, starting from the beginning.', 's3-image-optimizer' ) );
		}

		WP_CLI::confirm( __( 'You are about to remove all WebP copies from all of your S3 buckets. Do you wish to continue?', 's3-image-optimizer' ) );

		s3io()->tools->webp_delete_loop();
	}
}

WP_CLI::add_command( 's3io', 'S3IO_CLI' );
