<?php
/**
 * Class file for S3 IO bulk operations.
 *
 * @package S3_Image_optimizer
 */

namespace S3IO;

use Exception;
use S3IO\Aws3\Aws\Exception\AwsException;
use S3IO\Aws3\Aws\S3\Exception\S3Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets up a tools page with WebP utilities. Could be used for other S3 related tasks, but all WebP for now.
 */
class Bulk extends Base {

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
		\add_action( 'admin_enqueue_scripts', array( $this, 'bulk_script' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'url_script' ) );
		\add_action( 'wp_ajax_s3io_image_scan', array( $this, 'image_scan' ) );
		\add_action( 'wp_ajax_s3io_bulk_init', array( $this, 'bulk_init' ) );
		\add_action( 'wp_ajax_s3io_bulk_loop', array( $this, 'bulk_loop' ) );
		\add_action( 'wp_ajax_s3io_bulk_cleanup', array( $this, 'bulk_cleanup' ) );
		\add_action( 'wp_ajax_s3io_url_images_loop', array( $this, 'url_loop' ) );
		\add_action( 'wp_ajax_s3io_query_table', array( $this, 'show_table' ) );
		\add_action( 'wp_ajax_s3io_table_remove', array( $this, 'remove_from_table' ) );
	}

	/**
	 * Setup the admin menu items for the bulk pages.
	 */
	public function admin_menu() {
		if ( ! \function_exists( 'ewww_image_optimizer' ) ) {
			return;
		}
		// Register the menu items for the bulk optimizers.
		\add_media_page( \esc_html__( 'S3 Bulk Image Optimizer', 's3-image-optimizer' ), \esc_html__( 'S3 Bulk Optimizer', 's3-image-optimizer' ), 'activate_plugins', 's3io-bulk-display', array( $this, 'bulk_display' ) );
		\add_media_page( \esc_html__( 'S3 Bulk URL Optimizer', 's3-image-optimizer' ), \esc_html__( 'S3 URL Optimizer', 's3-image-optimizer' ), 'activate_plugins', 's3io-url-display', array( $this, 'url_display' ) );
	}

	/**
	 * Prepares the bulk operation and includes the javascript functions.
	 *
	 * @param string $hook The hook/suffix for the current page.
	 */
	public function bulk_script( $hook ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Make sure we are being called from the proper page.
		if ( 's3io-auto' !== $hook && 'media_page_s3io-bulk-display' !== $hook ) {
			return;
		}
		$this->make_upload_dir();
		// Check to see if the user has asked to reset (empty) the optimized images table.
		if ( ! empty( $_REQUEST['s3io_force_empty'] ) && ! empty( $_REQUEST['s3io_wpnonce'] ) && \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk-empty' ) ) {
			$this->table_truncate();
		}
		// Check to see if we are supposed to reset the bulk operation and verify we are authorized to do so.
		if ( ! empty( $_REQUEST['s3io_reset_bulk'] ) && ! empty( $_REQUEST['s3io_wpnonce'] ) && \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk-reset' ) ) {
			\update_option( 's3io_resume', '', false );
		}
		// Check the 'bulk resume' option.
		$resume = \get_option( 's3io_resume' );

		\delete_option( 's3io_last_run' );
		\update_option( 's3io_bucket_paginator', '', false );
		\update_option( 's3io_buckets_scanned', '', false );
		if ( empty( $resume ) ) {
			$this->table_clear_pending();
		}
		if ( 'media_page_s3io-bulk-display' === $hook ) {
			// Submit a couple variables to the javascript to work with.
			\wp_enqueue_script( 's3iobulkscript', \plugins_url( '/s3io.js', \S3IO_PLUGIN_FILE ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar', 'postbox', 'dashboard' ), \S3IO_VERSION );
			$image_count = $this->table_count_optimized();
			\wp_localize_script(
				's3iobulkscript',
				's3io_vars',
				array(
					'_wpnonce'              => \wp_create_nonce( 's3io-bulk' ),
					'image_count'           => $image_count, // Number of images completed.
					'attachments'           => $this->table_count_pending(), // Number of pending images, will be 0 unless resuming.
					/* translators: %s: number of items completed (includes HTML markup) */
					'completed_string'      => \sprintf( \esc_html__( 'Checked %s files so far', 's3-image-optimizer' ), '<span id="s3io-completed-count"></span>' ),
					/* translators: %d: number of images */
					'count_string'          => \sprintf( \esc_html__( '%d images', 's3-image-optimizer' ), $image_count ),
					'starting_scan'         => \esc_html__( 'Scanning buckets...', 's3-image-optimizer' ),
					'operation_stopped'     => \esc_html__( 'Optimization stopped, reload page to resume.', 's3-image-optimizer' ),
					'operation_interrupted' => \esc_html__( 'Operation Interrupted', 's3-image-optimizer' ),
					'temporary_failure'     => \esc_html__( 'Temporary failure, seconds left to retry:', 's3-image-optimizer' ),
					'remove_failed'         => \esc_html__( 'Could not remove image from table.', 's3-image-optimizer' ),
					'optimized'             => \esc_html__( 'Optimized', 's3-image-optimizer' ),
				)
			);
			\wp_enqueue_style( 'jquery-ui-progressbar', \plugins_url( 'jquery-ui-1.10.1.custom.css', \S3IO_PLUGIN_FILE ), array(), \S3IO_VERSION );
			$this->progressbar_style();
		}
	}

	/**
	 * Prepares the bulk URL operation and includes the javascript functions.
	 *
	 * @param string $hook The hook/suffix for the current page.
	 */
	public function url_script( $hook ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Make sure we are being called from the proper page.
		if ( 'media_page_s3io-url-display' !== $hook ) {
			return;
		}
		$this->make_upload_dir();
		$loading_image = \plugins_url( '/wpspin.gif', \S3IO_PLUGIN_FILE );
		// Submit a couple variables to the javascript to work with.
		\wp_enqueue_script( 's3iobulkscript', \plugins_url( '/s3io.js', \S3IO_PLUGIN_FILE ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar', 'postbox', 'dashboard' ), \S3IO_VERSION );
		\wp_localize_script(
			's3iobulkscript',
			's3io_vars',
			array(
				'_wpnonce'              => \wp_create_nonce( 's3io-url' ),
				'operation_stopped'     => \esc_html__( 'Optimization stopped, reload page to optimize more images by url.', 's3-image-optimizer' ),
				'operation_interrupted' => \esc_html__( 'Operation Interrupted', 's3-image-optimizer' ),
				'temporary_failure'     => \esc_html__( 'Temporary failure, seconds left to retry:', 's3-image-optimizer' ),
				'optimized'             => \esc_html__( 'Optimized', 's3-image-optimizer' ),
				'finished'              => \esc_html__( 'Finished', 's3-image-optimizer' ),
				'optimizing'            => \esc_html__( 'Optimizing', 's3-image-optimizer' ),
				'spinner'               => '<img src="' . \esc_url( $loading_image ) . '" alt="loading"/>',
			)
		);
		\wp_enqueue_style( 'jquery-ui-progressbar', \plugins_url( 'jquery-ui-1.10.1.custom.css', \S3IO_PLUGIN_FILE ), array(), \S3IO_VERSION );
	}

	/**
	 * Display the bulk S3 optimization page.
	 */
	public function bulk_display() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Retrieve the value of the 'aux resume' option and set the button text for the form to use.
		$s3io_resume = \get_option( 's3io_resume' );
		$start_text  = \__( 'Start optimizing', 's3-image-optimizer' );
		if ( empty( $s3io_resume ) ) {
			$button_text = \__( 'Scan for unoptimized images', 's3-image-optimizer' );
		} else {
			$button_text = \__( 'Resume where you left off', 's3-image-optimizer' );
		}
		// find out if the auxiliary image table has anything in it.
		$already_optimized = $this->table_count_optimized();
		// generate the WP spinner image for display.
		$loading_image = \plugins_url( '/wpspin.gif', \S3IO_PLUGIN_FILE );
		echo "\n";
		?>
		<div class="wrap">
			<h1><?php \esc_html_e( 'S3 Bulk Optimizer', 's3-image-optimizer' ); ?></h1>
		<?php
		if ( ! empty( \s3io()->errors ) && \is_array( \s3io()->errors ) ) {
			foreach ( \s3io()->errors as $s3io_error ) {
				echo '<p style="color: red"><strong>' . \esc_html( $s3io_error ) . '</strong></p>';
			}
		}
		\s3io()->errors = array();
		?>
			<div id="s3io-bulk-loading">
				<p id="s3io-loading" class="s3io-bulk-info" style="display:none">&nbsp;<img src="<?php echo \esc_url( $loading_image ); ?>" /></p>
			</div>
			<div id="s3io-bulk-progressbar"></div>
			<div id="s3io-bulk-counter"></div>
			<form id="s3io-bulk-stop" style="display:none;" method="post" action="">
				<br><input type="submit" class="button-secondary action" value="<?php \esc_attr_e( 'Stop Optimizing', 's3-image-optimizer' ); ?>" />
			</form>
			<div id="s3io-bulk-widgets" class="metabox-holder" style="display:none">
				<div class="meta-box-sortables">
					<div id="s3io-bulk-last" class="postbox">
						<button type="button" class="s3io-handlediv button-link" aria-expanded="true">
							<span class="screen-reader-text"><?php \esc_html_e( 'Click to toggle', 's3-image-optimizer' ); ?></span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
						<h2 class="s3io-hndle"><span><?php \esc_html_e( 'Last Image Optimized', 's3-image-optimizer' ); ?></span></h2>
						<div class="inside"></div>
					</div>
				</div>
				<div class="meta-box-sortables">
					<div id="s3io-bulk-status" class="postbox">
						<button type="button" class="s3io-handlediv button-link" aria-expanded="true">
							<span class="screen-reader-text"><?php \esc_html_e( 'Click to toggle', 's3-image-optimizer' ); ?></span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
						<h2 class="s3io-hndle"><span><?php \esc_html_e( 'Optimization Log', 's3-image-optimizer' ); ?></span></h2>
						<div class="inside"></div>
					</div>
				</div>
			</div>
			<form id="s3io-delay-slider-form" class="s3io-bulk-form">
				<p><label for="s3io-delay" style="font-weight: bold"><?php \esc_html_e( 'Choose how long to pause between images (in seconds, 0 = disabled)', 's3-image-optimizer' ); ?></label>&emsp;<input type="text" id="s3io-delay" name="s3io-delay" value="0"></p>
				<div id="s3io-delay-slider" style="width:50%"></div>
			</form>
			<div id="s3io-bulk-forms">
				<p class="s3io-media-info s3io-bulk-info"><strong><?php	\esc_html_e( 'Currently selected buckets:', 's3-image-optimizer' ); ?></strong>
					<?php
					$bucket_list = $this->get_selected_buckets();
					if ( ! empty( \s3io()->errors ) ) {
						echo '<span style="color: red"><strong>' . \esc_html( \s3io()->errors[0] ) . '</strong></p>';
					} elseif ( empty( $bucket_list ) ) {
						echo '<strong>' . \esc_html__( 'Unable to find any buckets to scan.', 's3-image-optimizer' ) . '</strong>';
					} else {
						foreach ( $bucket_list as $bucket ) {
							echo '<br>' . \esc_html( $bucket );
						}
					}
					?>
				</p>
		<?php if ( empty( $s3io_resume ) && ! empty( $bucket_list ) ) : ?>
				<form id="s3io-scan" class="s3io-bulk-form" method="post" action="">
					<input id="s3io-scan-button" type="submit" class="button-primary" value="<?php echo \esc_attr( $button_text ); ?>" />
				</form>
				<p id="s3io-found-images" class="s3io-bulk-info" style="display:none;"></p>
				<form id="s3io-start" class="s3io-bulk-form" style="display:none;" method="post" action="">
					<input id="s3io-start-button" type="submit" class="button-primary" value="<?php echo \esc_attr( $start_text ); ?>" />
				</form>
		<?php endif; ?>
		<?php if ( ! empty( $s3io_resume ) ) : ?>
				<p id="s3io-found-images" class="s3io-bulk-info">
					<?php
					$pending = $this->table_count_pending();
					/* translators: %d: number of images */
					\printf( \esc_html__( 'There are %d images to be optimized.', 's3-image-optimizer' ), (int) $pending );
					?>
				</p>
				<form id="s3io-start" class="s3io-bulk-form" method="post" action="">
					<input id="s3io-start-button" type="submit" class="button-primary action" value="<?php echo \esc_attr( $button_text ); ?>" />
				</form>
				<p class="s3io-bulk-info">
					<?php \esc_html_e( 'Would you like to clear the queue and rescan for images?', 's3-image-optimizer' ); ?>
				</p>
				<form id="s3io-bulk-reset" class="s3io-bulk-form" method="post" action="">
					<?php \wp_nonce_field( 's3io-bulk-reset', 's3io_wpnonce' ); ?>
					<input type="hidden" name="s3io_reset_bulk" value="1">
					<button type="submit" class="button-secondary action"><?php \esc_html_e( 'Clear Queue', 's3-image-optimizer' ); ?></button>
				</form>
		<?php endif; ?>
		<?php if ( ! empty( $already_optimized ) ) : ?>
				<p class="s3io-bulk-info" style="margin-top: 2.5em">
					<?php \esc_html_e( 'Force a re-optimization of all images by erasing the optimization history. This cannot be undone, as it will remove all optimization records from the database.', 's3-image-optimizer' ); ?>
				</p>
				<form id="s3io-force-empty" class="s3io-bulk-form" style="margin-bottom: 2.5em" method="post" action="">
					<?php \wp_nonce_field( 's3io-bulk-empty', 's3io_wpnonce' ); ?>
					<input type="hidden" name="s3io_force_empty" value="1">
					<button type="submit" class="button-secondary action"><?php \esc_html_e( 'Erase Optimization History', 's3-image-optimizer' ); ?></button>
				</form>
				<p id="s3io-table-info" class="s3io-bulk-info">
					<?php
					/* translators: %d: number of images */
					\printf( \esc_html__( 'The optimizer keeps track of already optimized images to prevent re-optimization. There are %d images that have been optimized so far.', 's3-image-optimizer' ), (int) $already_optimized );
					?>
				</p>
				<form id="s3io-show-table" class="s3io-bulk-form" method="post" action="">
					<button type="submit" class="button-secondary action"><?php \esc_html_e( 'Show Optimized Images', 's3-image-optimizer' ); ?></button>
				</form>
				<div class="tablenav s3io-aux-table" style="display:none">
					<div class="tablenav-pages s3io-table">
						<span class="displaying-num s3io-table"></span>
						<span id="paginator" class="pagination-links s3io-table">
							<a id="first-images" class="tablenav-pages-navspan button first-page" style="display:none">&laquo;</a>
							<a id="prev-images" class="tablenav-pages-navspan button prev-page" style="display:none">&lsaquo;</a>
							<?php \esc_html_e( 'page', 's3-image-optimizer' ); ?> <span class="current-page"></span> <?php \esc_html_e( 'of', 's3-image-optimizer' ); ?>
							<span class="total-pages"></span>
							<a id="next-images" class="tablenav-pages-navspan button next-page" style="display:none">&rsaquo;</a>
							<a id="last-images" class="tablenav-pages-navspan button last-page" style="display:none">&raquo;</a>
						</span>
					</div>
				</div>
				<div id="s3io-bulk-table" class="s3io-table"></div>
				<span id="s3io-pointer" style="display:none">0</span>
		<?php endif; ?>
			</div><!-- end #s3io-bulk-forms -->
		</div><!-- end .wrap -->
		<?php
	}

	/**
	 * Display the bulk S3 optimization page for URLs.
	 */
	public function url_display() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$loading_image = \plugins_url( '/wpspin.gif', \S3IO_PLUGIN_FILE );
		?>
		<div class="wrap">
		<h1><?php \esc_html_e( 'S3 URL Optimizer', 's3-image-optimizer' ); ?></h1>
			<div id="s3io-bulk-loading">
				<p id="s3io-loading" class="s3io-bulk-info" style="display:none">&nbsp;<img src="<?php echo \esc_url( $loading_image ); ?>" /></p>
			</div>
			<div id="s3io-bulk-progressbar"></div>
			<div id="s3io-bulk-counter"></div>
			<form id="s3io-bulk-stop" style="display:none;" method="post" action="">
				<br /><input type="submit" class="button-secondary action" value="<?php \esc_attr_e( 'Stop Optimizing', 's3-image-optimizer' ); ?>" />
			</form>
			<div id="s3io-bulk-widgets" class="metabox-holder" style="display:none">
				<div class="meta-box-sortables">
					<div id="s3io-bulk-status" class="postbox">
						<button type="button" class="s3io-handlediv button-link" aria-expanded="true">
							<span class="screen-reader-text"><?php \esc_html_e( 'Click to toggle', 's3-image-optimizer' ); ?></span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
						<h2 class="s3io-hndle"><span><?php \esc_html_e( 'Optimization Log', 's3-image-optimizer' ); ?></span></h2>
						<div class="inside"></div>
					</div>
				</div>
			</div>
			<form class="s3io-bulk-form">
				<p><label for="s3io-delay" style="font-weight: bold"><?php \esc_html_e( 'Choose how long to pause between images (in seconds, 0 = disabled)', 's3-image-optimizer' ); ?></label>&emsp;<input type="text" id="s3io-delay" name="s3io-delay" value="0"></p>
				<div id="s3io-delay-slider" style="width:50%"></div>
			</form>
			<div id="s3io-bulk-forms"><p class="s3io-bulk-info">
				<p class="s3io-media-info s3io-bulk-info">
					<?php \esc_html_e( 'Previously optimized images will not be skipped.', 's3-image-optimizer' ); ?>
					<?php echo ( \function_exists( 'ewww_image_optimizer_get_option' ) && \ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? ' *' . \esc_html__( 'WebP versions will be generated and uploaded in accordance with EWWW IO settings.', 's3-image-optimizer' ) : '' ); ?>
				</p>
				<form id="s3io-url-start" class="s3io-bulk-form" method="post" action="">
					<p>
						<label><strong><?php \esc_html_e( 'Enter URLs of images to optimize:', 's3-image-optimizer' ); ?></strong></label>
					</p>
					<textarea id="s3io-url-image-queue" name="s3io-url-image-queue" style="resize:both; height: 300px; width: 60%;"></textarea>
					<p class="description">
						<?php /* translators: %s: example image URL */ ?>
						<?php \printf( \esc_html__( 'One per line, example: %s', 's3-image-optimizer' ), \esc_url( 'https://bucket-name.s3.amazonaws.com/uploads/' . \gmdate( 'Y' ) . '/' . \gmdate( 'm' ) . '/test-image.jpg' ) ); ?>
					</p>
					<p>
						<input id="s3io-first" type="submit" class="button-primary action" value="<?php \esc_attr_e( 'Start optimizing', 's3-image-optimizer' ); ?>" />
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Check for .webp copies of optimized images and remove them from the queue.
	 *
	 * If a .webp image was previously optimized, we'll leave it alone, whether it is a copy or not.
	 */
	public function check_webp_copies() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! \ewwwio()->get_option( 'ewww_image_optimizer_webp' ) || empty( \ewwwio()->get_option( 'ewww_image_optimizer_webp_level' ) ) ) {
			return;
		}
		global $wpdb;
		$this->debug_message( 'WebP Conversion is enabled, and so is WebP Optimization, checking for WebP copies.' );
		$original_extensions = array( 'png', 'jpg', 'jpeg', 'gif' );
		$full_list           = array();
		$all_images          = $wpdb->get_results( "SELECT path,bucket FROM $wpdb->s3io_images", \ARRAY_A );
		if ( empty( $all_images ) ) {
			$this->debug_message( 'no images in database, skipping check for .webp copies' );
			return;
		}
		foreach ( $all_images as $image_record ) {
			$image_path               = $image_record['path'];
			$full_list[ $image_path ] = $image_record;
		}
		$pending_images = $this->table_get_pending();
		$this->debug_message( 'checking ' . \count( $pending_images ) . ' against full list of ' . \count( $full_list ) . ' images for .webp copies' );
		// Now we will loop through the pending images and check if any of them are a .webp copy of another image in the bucket.
		// We are specifically looking for WebP images where the .webp extension has replaced the original extension,
		// since we already check for appended .webp extensions earlier in $this->image_scan().
		// If a Webp image is a copy of another JPG/PNG/GIF image, we will remove them from the table, since we don't further optimize .webp image copies.
		foreach ( $pending_images as $pending_image ) {
			if ( empty( $pending_image['path'] ) || empty( $pending_image['id'] ) || empty( $pending_image['bucket'] ) ) {
				continue;
			}
			$pending_path = $pending_image['path'];
			if ( ! \str_ends_with( $pending_path, '.webp' ) ) {
				continue;
			}
			$webp_copy     = false;
			$original_path = $this->remove_from_end( $pending_path, '.webp' );
			$info          = \pathinfo( $original_path );
			$ext           = \strtolower( $info['extension'] ?? '' );
			$this->debug_message( "checking $pending_path if it is a .webp copy, possible original path is $original_path, ext: $ext" );
			if ( empty( $ext ) || ! \in_array( $ext, $original_extensions, true ) ) {
				$this->debug_message( 'this is not an appended .webp copy, so checking for original image with extension replaced' );
				// This is not an appended .webp copy, now to find if there is a matching original image with the same path but a different extension.
				foreach ( $original_extensions as $ext ) {
					if ( isset( $full_list[ $original_path . '.' . $ext ] ) && $pending_image['bucket'] === $full_list[ $original_path . '.' . $ext ]['bucket'] ) {
						$webp_copy = true;
					} elseif ( isset( $full_list[ $original_path . '.' . \strtoupper( $ext ) ] ) && $pending_image['bucket'] === $full_list[ $original_path . '.' . \strtoupper( $ext ) ]['bucket'] ) {
						$webp_copy = true;
					}
					// If we found a matching original image, this .webp is a copy and we can remove it from the pending table.
					if ( $webp_copy ) {
						$this->debug_message( "removing $pending_path from image table since it is a .webp copy of $original_path.$ext" );
						$this->table_delete_image( $pending_image['id'] );
						break;
					}
				}
			}
		}
	}

	/**
	 * Scan buckets for images and store in database.
	 *
	 * @param bool $verbose Enable (true) to output WP_CLI logging. Default false.
	 */
	public function image_scan( $verbose = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$wpcli = false;
		if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
			$wpcli = true;
		}
		$permissions = \apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
		if ( ! $wpcli && ( empty( $_REQUEST['s3io_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! \current_user_can( $permissions ) ) ) {
			\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
		}
		global $wpdb;
		\s3io()->errors = array();
		$images         = array();
		$image_count    = 0;
		$scan_count     = 0;
		$field_formats  = array(
			'%s', // bucket.
			'%s', // path.
			'%d', // image_size.
			'%d', // pending.
		);

		$original_extensions = array( 'png', 'jpg', 'jpeg', 'gif' );

		/* $start = microtime( true ); */
		try {
			$client = \s3io()->amazon_web_services->get_client();
		} catch ( AwsException | S3Exception | Exception $e ) {
			$s3io_error = $this->format_aws_exception( $e->getMessage() );
			$this->flush_output_to_cli( $s3io_error, 'error' );
			\wp_die( \wp_json_encode( array( 'error' => $s3io_error ) ) );
		}

		$bucket_list = $this->get_selected_buckets();
		if ( ! empty( s3io()->errors ) ) {
			\s3io()->errors = $this->flush_output_to_cli( \s3io()->errors, 'error' );
			\wp_die( wp_json_encode( array( 'error' => \s3io()->errors[0] ) ) );
		}

		$buckets_scanned   = \get_option( 's3io_buckets_scanned' );
		$completed_buckets = $this->is_iterable( $buckets_scanned ) ? $buckets_scanned : array();
		$completed_buckets = \get_option( 's3io_buckets_scanned' ) ? \get_option( 's3io_buckets_scanned' ) : array();
		$paginator         = \get_option( 's3io_bucket_paginator' );
		foreach ( $bucket_list as $bucket ) {
			$this->debug_message( "scanning $bucket" );
			if ( $verbose && $wpcli ) {
				/* translators: %s: S3 bucket name */
				\WP_CLI::line( \sprintf( \__( 'Scanning bucket %s...', 's3-image-optimizer' ), $bucket ) );
			}
			foreach ( $completed_buckets as $completed_bucket ) {
				if ( $bucket === $completed_bucket ) {
					$this->debug_message( "skipping $bucket, already done" );
					continue 2;
				}
			}
			try {
				$location = $client->getBucketLocation( array( 'Bucket' => $bucket ) );
			} catch ( AwsException | S3Exception | Exception $e ) {
				$location = new \WP_Error( 's3io_exception', $this->format_aws_exception( $e->getMessage() ) );
			}
			if ( \is_wp_error( $location ) && ( ! defined( 'S3_IMAGE_OPTIMIZER_REGION' ) || ! \S3_IMAGE_OPTIMIZER_REGION ) ) {
				/* translators: 1: bucket name 2: AWS error message */
				$s3_error = \sprintf( \esc_html__( 'Could not get bucket location for %1$s, error: %2$s. You may set the region manually using the S3_IMAGE_OPTIMIZER_REGION constant in wp-config.php.', 's3-image-optimizer' ), $bucket, \wp_kses_post( $location->get_error_message() ) );
				$this->flush_output_to_cli( $s3_error, 'error' );
				\wp_die( \wp_json_encode( array( 'error' => $s3_error ) ) );
			}
			$paginator_args = array( 'Bucket' => $bucket );
			if ( \defined( 'S3_IMAGE_OPTIMIZER_FOLDER' ) && \S3_IMAGE_OPTIMIZER_FOLDER ) {
				$paginator_args['Prefix'] = \ltrim( \S3_IMAGE_OPTIMIZER_FOLDER, '/' );
			}
			if ( $paginator ) {
				$this->debug_message( "starting from $paginator" );
				$paginator_args['Marker'] = $paginator;
			}

			// In case you need to modify the arguments to the $client->getPaginator() call before they are used.
			$paginator_args = \apply_filters( 's3io_scan_iterator_args', $paginator_args );

			$results = $client->getPaginator( 'ListObjects', $paginator_args );

			$already_optimized = $wpdb->get_results( $wpdb->prepare( "SELECT id,path,image_size FROM $wpdb->s3io_images WHERE bucket LIKE %s", $bucket ), \ARRAY_A );
			$optimized_list    = array();
			foreach ( $already_optimized as $optimized ) {
				$optimized_path                            = $optimized['path'];
				$optimized_list[ $optimized_path ]['size'] = (int) $optimized['image_size'];
				$optimized_list[ $optimized_path ]['id']   = (int) $optimized['id'];
			}
			if ( $this->stl_check() ) {
				\set_time_limit( 0 );
			}
			try {
				foreach ( $results as $result ) {
					foreach ( $result['Contents'] as $object ) {
						++$scan_count;
						$skip_optimized = false; // Image already optimized, skip it.
						$reset_pending  = false; // Image optimized, but size has changed, reset to pending.
						$path           = $object['Key'];
						$this->debug_message( "$scan_count: checking $path" );
						if ( \preg_match( '/\.(jpe?g|png|gif|webp)$/i', $path ) ) {
							// If the naming mode is 'replace', we'll deal with those later, after all images have been scanned.
							// At that point, we'll have a full list of all the files in the selected buckets, and can check the table for a matching original.
							if ( \str_ends_with( $path, '.webp' ) ) {
								if ( empty( \ewwwio()->get_option( 'ewww_image_optimizer_webp_level' ) ) ) {
									// If webp optimization is disabled, we should skip it.
									continue;
								}
								// If we have a .webp file, we need to see if the .webp extension was appended.
								$stripped_path     = $this->remove_from_end( $path, '.webp' );
								$stripped_info     = \pathinfo( $stripped_path );
								$stripped_ext      = \strtolower( $stripped_info['extension'] ?? '' );
								$is_real_extension = \in_array( $stripped_ext, $original_extensions, true );
								if ( $is_real_extension ) {
									// This is a .webp file that was created by appending .webp to an original filename, so we should skip it.
									continue;
								}
							}
							$image_size = (int) $object['Size'];
							if ( isset( $optimized_list[ $path ] ) && $optimized_list[ $path ]['size'] === $image_size ) {
								$this->debug_message( 'size matches db, skipping' );
								$skip_optimized = true;
							} elseif ( isset( $optimized_list[ $path ] ) ) {
								$this->debug_message( 'size does not match, set to pending' );
								$reset_pending = $optimized_list[ $path ]['id'];
							}
						} else {
							$this->debug_message( 'not an image, skipping' );
							continue;
						}
						// We don't actually have a force option at this point.
						// They have to remove records to force a re-opt.
						if ( ! $skip_optimized || ! empty( $_REQUEST['s3io_force'] ) ) {
							if ( $reset_pending ) {
								$this->table_set_pending( $reset_pending );
							} else {
								$images[ $path ] = array(
									'bucket'    => $bucket,
									'path'      => $path,
									'orig_size' => $image_size,
									'pending'   => 1,
								);
							}
							if ( $verbose && $wpcli ) {
								/* translators: 1: image name 2: S3 bucket name */
								\WP_CLI::line( \sprintf( \__( 'Queueing %1$s in %2$s.', 's3-image-optimizer' ), $path, $bucket ) );
							}
							$this->debug_message( "queuing $path in $bucket" );
							++$image_count;
						}
						if ( $scan_count >= 4000 && \count( $images ) ) {
							// let's dump what we have so far to the db.
							if ( ! \function_exists( 'ewww_image_optimizer_mass_insert' ) ) {
								\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'Please update EWWW Image Optimizer to the latest version.', 's3-image-optimizer' ) ) ) );
							}
							\ewww_image_optimizer_mass_insert( $wpdb->s3io_images, $images, $field_formats );
							$this->debug_message( "saved queue to db after checking $scan_count and finding $image_count" );
							if ( $verbose && $wpcli ) {
								\WP_CLI::line( \__( 'Saved queue to database.', 's3-image-optimizer' ) );
							}
							if ( ! $wpcli ) {
								$this->debug_message( "stashing $path as last marker" );
								\update_option( 's3io_bucket_paginator', $path, false );
								\wp_die(
									\wp_json_encode(
										array(
											/* translators: %s: S3 bucket name */
											'current'   => \sprintf( \esc_html__( 'Scanning bucket %s', 's3-image-optimizer' ), "<strong>$bucket</strong>" ),
											'completed' => $scan_count, // Number of images scanned in this pass.
										)
									)
								);
							}
							$image_count = 0;
							$images      = array();
						} elseif ( $scan_count >= 4000 && ! $wpcli ) {
							$this->debug_message( "stashing $path as last marker" );
							\update_option( 's3io_bucket_paginator', $path, false );
							\wp_die(
								\wp_json_encode(
									array(
										/* translators: %s: S3 bucket name */
										'current'   => \sprintf( \esc_html__( 'Scanning bucket %s', 's3-image-optimizer' ), "<strong>$bucket</strong>" ),
										'completed' => $scan_count, // Number of images scanned in this pass.
									)
								)
							);
						}
					}
				}
			} catch ( AwsException | S3Exception | Exception $e ) {
				/* translators: 1: bucket name 2: AWS error message */
				$s3_error = \sprintf( \esc_html__( 'Error encountered while scanning %1$s. You may need to set the region using the S3_IMAGE_OPTIMIZER_REGION constant in wp-config.php. Error: %2$s.', 's3-image-optimizer' ), $bucket, \wp_kses_post( $this->format_aws_exception( $e->getMessage() ) ) );
				$this->flush_output_to_cli( $s3_error, 'error' );
				\wp_die( \wp_json_encode( array( 'error' => $s3_error ) ) );
			}
			$paginator = '';
			\update_option( 's3io_bucket_paginator', $paginator, false );
			$completed_buckets[] = $bucket;
			$this->debug_message( "adding $bucket to the completed list" );
			\update_option( 's3io_buckets_scanned', $completed_buckets, false );
		}
		if ( ! empty( $images ) ) {
			if ( ! \function_exists( 'ewww_image_optimizer_mass_insert' ) ) {
				\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'Please update EWWW Image Optimizer to the latest version.', 's3-image-optimizer' ) ) ) );
			}
			$this->debug_message( 'saving queue to db' );
			\ewww_image_optimizer_mass_insert( $wpdb->s3io_images, $images, $field_formats );
		}
		\update_option( 's3io_buckets_scanned', '', false );
		$this->check_webp_copies();
		if ( ! $wpcli ) {
			$pending = $this->table_count_pending();
			/* translators: %d: number of images */
			$message = $pending ? \sprintf( \esc_html__( 'There are %d images to be optimized.', 's3-image-optimizer' ), $pending ) : \esc_html__( 'There is nothing left to optimize.', 's3-image-optimizer' );
			if ( $pending && \function_exists( 'ewww_image_optimizer_get_option' ) && \ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
				$message .= ' *' . \esc_html__( 'WebP versions will be generated and uploaded in accordance with EWWW IO settings.', 's3-image-optimizer' );
			}
			\wp_die(
				\wp_json_encode(
					array(
						'message'   => $message,
						'pending'   => $pending, // Number of images to do.
						'completed' => $scan_count, // Number of images scanned in this pass.
					)
				)
			);
		}
		return $image_count;
	}

	/**
	 * Called by javascript to initialize the bulk output.
	 */
	public function bulk_init() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$output = array();
		// Verify that an authorized user has started the optimizer.
		$permissions = \apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
		if ( empty( $_REQUEST['s3io_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! \current_user_can( $permissions ) ) {
			\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
		}
		\session_write_close();
		// Store the time and number of images for later display.
		\update_option( 's3io_resume', true, false );
		// Generate the WP spinner image for display.
		$loading_image = \plugins_url( '/wpspin.gif', \S3IO_PLUGIN_FILE );
		global $wpdb;
		$image_record = $wpdb->get_row( "SELECT path FROM $wpdb->s3io_images WHERE pending = 1 LIMIT 1", \ARRAY_A );
		// Let the user know that we are beginning.
		$output['results'] = '<p>' . \esc_html__( 'Optimizing', 's3-image-optimizer' ) . ' <b>' . \esc_html( $image_record['path'] ) . "</b>&nbsp;<img src='" . \esc_url( $loading_image ) . "' alt='loading'/></p>";
		\wp_die( \wp_json_encode( $output ) );
	}

	/**
	 * Called by javascript to process each image in the loop.
	 *
	 * @param bool $verbose True to output extra information. Optional. Default false.
	 */
	public function bulk_loop( $verbose = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$wpcli = false;
		if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
			$wpcli = true;
		}
		$output = array();
		// verify that an authorized user has started the optimizer.
		$permissions = \apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
		if ( ! $wpcli && ( empty( $_REQUEST['s3io_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! \current_user_can( $permissions ) ) ) {
			\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
		}
		// Retrieve the time when the optimizer starts.
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'begin' );
		$started = \microtime( true );
		if ( ! $wpcli ) {
			// Find out if our nonce is on it's last leg/tick.
			$tick = \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' );
			if ( 2 === (int) $tick ) {
				$output['new_nonce'] = \wp_create_nonce( 's3io-bulk' );
			} else {
				$output['new_nonce'] = '';
			}
		}
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'ticked' );
		if (
			$this->stl_check() &&
			$this->function_exists( 'ini_get' ) &&
			\ini_get( 'max_execution_time' ) < 60
		) {
			\set_time_limit( 0 );
		}
		global $ewww_image;
		global $wpdb;
		$image_record = $wpdb->get_row( "SELECT id,bucket,path,orig_size FROM $wpdb->s3io_images WHERE pending = 1 LIMIT 1", \ARRAY_A );
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'image retrieved from db' );

		$upload_dir = $this->make_upload_dir();
		if ( ! $upload_dir ) {
			$s3io_error = \esc_html__( 'Could not create the /s3io/ folder within the WordPress uploads folder, please adjust the permissions and try again.', 's3-image-optimizer' );
			$this->flush_output_to_cli( $s3io_error, 'error' );
			\wp_die(
				\wp_json_encode(
					array(
						'error' => $s3io_error,
					)
				)
			);
		}
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'upload folder created' );
		$upload_dir = \trailingslashit( $upload_dir ) . \sanitize_file_name( $image_record['bucket'] ) . '/';
		$this->debug_message( "stashing files in $upload_dir" );
		if ( false !== strpos( $upload_dir, 's3://' ) ) {
			/* translators: %s: path to uploads directory */
			$s3io_error = \sprintf( \esc_html__( 'Received an unusable working directory: %s', 's3-image-optimizer' ), $upload_dir );
			$this->flush_output_to_cli( $s3io_error, 'error' );
			\wp_die( \wp_json_encode( array( 'error' => $s3io_error ) ) );
		}
		try {
			$client = \s3io()->amazon_web_services->get_client();
		} catch ( AwsException | S3Exception | Exception $e ) {
			$s3io_error = $this->flush_output_to_cli( $e->getMessage(), 'error' );
			\wp_die( \wp_json_encode( array( 'error' => \wp_kses_post( $s3io_error ) ) ) );
		}
		$filename = $upload_dir . $image_record['path'];
		$full_dir = \dirname( $filename );
		if ( ! \is_dir( $full_dir ) ) {
			\wp_mkdir_p( $full_dir );
		}
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'start s3 fetch' );
		try {
			$fetch_result = $client->getObject(
				array(
					'Bucket' => $image_record['bucket'],
					'Key'    => $image_record['path'],
					'SaveAs' => $filename,
				)
			);
		} catch ( AwsException | S3Exception | Exception $e ) {
			$output['error'] = \sprintf(
				/* translators: 1: bucket name, 2: path to file, 3: error message */
				\esc_html__( 'Fetch failed for bucket: %1$s, path: %2$s, message: %3$s', 's3-image-optimizer' ),
				\esc_html( $image_record['bucket'] ),
				\esc_html( $image_record['path'] ),
				\wp_kses_post( $this->format_aws_exception( $e->getMessage() ) )
			);
			$output['error'] = $this->flush_output_to_cli( $output['error'], 'error' );
			\wp_die( \wp_json_encode( $output ) );
		}
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'finish s3 fetch' );
		$orig_size = $image_record['orig_size'];
		if ( $verbose && $wpcli ) {
			/* translators: %s: filename */
			\WP_CLI::line( \sprintf( \__( 'Starting optimization for %s', 's3-image-optimizer' ), $filename ) );
		}
		// Make sure EWWW IO doesn't skip images.
		\ewwwio()->force = true;
		\add_filter( 'ewww_image_optimizer_update_table', '__return_false' );
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'start opt' );
		// Do the optimization for the current image.
		$results = \ewww_image_optimizer( $filename );
		if ( $verbose && $wpcli ) {
			/* translators: %s: filename */
			\WP_CLI::line( \sprintf( \__( 'Optimized %s', 's3-image-optimizer' ), $filename ) );
		}
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'end opt' );
		\ewwwio()->force = false;
		$ewww_status     = \get_transient( 'ewww_image_optimizer_cloud_status' );
		if ( 'exceeded' === $ewww_status ) {
			\unlink( $filename );
			$output['error'] = \esc_html__( 'License Exceeded', 's3-image-optimizer' );
			$output['error'] = $this->flush_output_to_cli( $output['error'], 'error' );
			\wp_die( \wp_json_encode( $output ) );
		}

		$ownership_control_enforced = $this->object_ownership_enforced( $image_record['bucket'] );

		$new_size = $this->filesize( $filename );
		if ( $new_size && $new_size < $fetch_result['ContentLength'] ) {
			if ( $verbose && $wpcli ) {
				/* translators: %s: filename */
				\WP_CLI::line( \sprintf( \__( 'Starting re-upload for %s', 's3-image-optimizer' ), $filename ) );
			}
			$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'start s3 push' );
			// Re-upload to S3.
			try {
				$client->putObject(
					array(
						'Bucket'       => $image_record['bucket'],
						'Key'          => $image_record['path'],
						'SourceFile'   => $filename,
						'ContentType'  => $fetch_result['ContentType'],
						'CacheControl' => 'max-age=31536000',
					)
				);
				if ( ! $ownership_control_enforced ) {
					$client->putObjectAcl(
						array(
							'Bucket' => $image_record['bucket'],
							'Key'    => $image_record['path'],
							'ACL'    => 'public-read',
						)
					);
				}
			} catch ( AwsException | S3Exception | Exception $e ) {
				$output['error'] = \sprintf(
					/* translators: 1: bucket name, 2: path to file, 3: error message */
					\esc_html__( 'Upload failed for bucket: %1$s, path: %2$s, message: %3$s', 's3-image-optimizer' ),
					\esc_html( $image_record['bucket'] ),
					\esc_html( $image_record['path'] ),
					\wp_kses_post( $this->format_aws_exception( $e->getMessage() ) )
				);
				$this->flush_output_to_cli( $output['error'], 'error' );
				\wp_die( \wp_json_encode( $output ) );
			}
			$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'end s3 push' );
			if ( $verbose && $wpcli ) {
				/* translators: %s: filename */
				\WP_CLI::line( \sprintf( \__( 'Finished re-upload of %s', 's3-image-optimizer' ), $filename ) );
			}
		}
		\unlink( $filename );
		$webp_error    = 0;
		$webp_filename = \ewww_image_optimizer_get_webp_path( $filename );
		$webp_key      = \ewww_image_optimizer_get_webp_path( $image_record['path'] );
		$webp_size     = $this->filesize( $webp_filename );
		if ( is_object( $ewww_image ) && ! empty( $ewww_image->webp_error ) && is_int( $ewww_image->webp_error ) ) {
			$webp_error = $ewww_image->webp_error;
		}
		$this->debug_message( print_r( $ewww_image, true ) );
		if ( $webp_size && ! empty( $webp_key ) ) {
			if ( $verbose && $wpcli ) {
				/* translators: %s: filename */
				\WP_CLI::line( \sprintf( \__( 'Uploading %s', 's3-image-optimizer' ), $webp_filename ) );
			}
			$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'start s3 webp push' );
			// Re-upload to S3.
			try {
				$client->putObject(
					array(
						'Bucket'       => $image_record['bucket'],
						'Key'          => $webp_key,
						'SourceFile'   => $webp_filename,
						'ContentType'  => 'image/webp',
						'CacheControl' => 'max-age=31536000',
					)
				);
				if ( ! $ownership_control_enforced ) {
					$client->putObjectAcl(
						array(
							'Bucket' => $image_record['bucket'],
							'Key'    => $webp_key,
							'ACL'    => 'public-read',
						)
					);
				}
			} catch ( AwsException | S3Exception | Exception $e ) {
				$output['error'] = \sprintf(
					/* translators: 1: bucket name, 2: path to file, 3: error message */
					\esc_html__( 'Upload failed for bucket: %1$s, path: %2$s, message: %3$s', 's3-image-optimizer' ),
					\esc_html( $image_record['bucket'] ),
					\esc_html( $webp_key ),
					\wp_kses_post( $this->format_aws_exception( $e->getMessage() ) )
				);
				$this->flush_output_to_cli( $output['error'], 'error' );
				\wp_die( \wp_json_encode( $output ) );
			}
			$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'end s3 push' );
			if ( $verbose && $wpcli ) {
				/* translators: %s: filename */
				\WP_CLI::line( \sprintf( \__( 'Finished upload of %s', 's3-image-optimizer' ), $webp_filename ) );
			}
			\unlink( $webp_filename );
		}
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'stash db record' );
		$this->table_update( $image_record['path'], $new_size, $fetch_result['ContentLength'], $webp_size, $webp_error, $image_record['id'], $image_record['bucket'] );
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'remove db record' );
		// Make sure ewww doesn't keep a record of these files.
		$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => \ewww_image_optimizer_relativize_path( $filename ) ) );
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'updated db records' );
		if ( \function_exists( 'ewww_image_optimizer_debug_log' ) ) {
			\ewww_image_optimizer_debug_log();
		}
		$elapsed = \microtime( true ) - $started;
		if ( $wpcli ) {
			/* translators: %s: path to an image */
			\WP_CLI::line( \sprintf( \__( 'Optimized image: %s', 's3-image-optimizer' ), $image_record['path'] ) );
			\WP_CLI::line( $this->get_results_msg( $orig_size, $new_size ) );
			if ( $webp_size ) {
				/* translators: %s: a result message, like 'Reduced by 66.9% (2.5 MB)', translated elsewhere */
				\WP_CLI::line( \sprintf( \__( 'WebP: %s', 's3-image-optimizer' ), $this->get_results_msg( $orig_size, $webp_size ) ) );
			}
			/* translators: %s: time in seconds */
			\WP_CLI::line( \sprintf( \__( 'Elapsed: %s seconds', 's3-image-optimizer' ), \number_format_i18n( $elapsed, 1 ) ) );
			return;
		}
		// Output the path.
		$output['results'] = '<p>';
		/* translators: %s: path to an image */
		$output['results'] .= \sprintf( \esc_html__( 'Optimized image: %s', 's3-image-optimizer' ), '<strong>' . \esc_html( $image_record['bucket'] . '/' . $image_record['path'] ) . '</strong>' ) . '<br>';
		$output['results'] .= \esc_html( $this->get_results_msg( $orig_size, $new_size ) ) . '<br>';
		if ( $webp_size ) {
			/* translators: %s: a result message, like 'Reduced by 66.9% (2.5 MB)', translated elsewhere */
			$output['results'] .= \sprintf( \esc_html__( 'WebP: %s', 's3-image-optimizer' ), $this->get_results_msg( $orig_size, $webp_size ) ) . '<br>';
		}
		// Output how much time has elapsed since we started.
		/* translators: %s: time in seconds */
		$output['results'] .= \sprintf( \esc_html__( 'Elapsed: %s seconds', 's3-image-optimizer' ), \number_format_i18n( $elapsed, 1 ) ) . '</p>';

		// Find the next image to optimize.
		$image_record = $wpdb->get_row( "SELECT path FROM $wpdb->s3io_images WHERE pending = 1 LIMIT 1", \ARRAY_A );
		$this->debug_message( \gmdate( 'Y-m-d H:i:s' ) . 'retrieved next image from db' );
		if ( ! empty( $image_record ) ) {
			$loading_image = \plugins_url( '/wpspin.gif', \S3IO_PLUGIN_FILE );
			/* translators: %s: path to an image */
			$output['next_file'] = '<p>' . \sprintf( \esc_html__( 'Optimizing %s', 's3-image-optimizer' ), '<b>' . \esc_html( $image_record['bucket'] . '/' . $image_record['path'] ) . '</b>' ) . "&nbsp;<img src='" . \esc_url( $loading_image ) . "' alt='loading'/></p>";
		}
		\wp_die( \wp_json_encode( $output ) );
	}

	/**
	 * Called by bulk process to cleanup after ourselves.
	 */
	public function bulk_cleanup() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Verify that an authorized user has started the optimizer.
		$permissions = \apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
		if ( empty( $_REQUEST['s3io_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! \current_user_can( $permissions ) ) {
			\wp_die( \esc_html__( 'Access denied.', 's3-image-optimizer' ) );
		}
		\update_option( 's3io_resume', '', false );
		echo '<p><b>' . \esc_html__( 'Finished', 's3-image-optimizer' ) . '</b></p>';
		die();
	}

	/**
	 * Run optimization for an image from the bulk URL process.
	 */
	public function url_loop() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Verify that an authorized user has started the optimizer.
		$permissions = \apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
		if ( empty( $_REQUEST['s3io_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-url' ) || ! \current_user_can( $permissions ) ) {
			\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
		}
		\s3io()->errors = array();
		$output         = array();
		$started        = \microtime( true );
		if (
			$this->stl_check() &&
			$this->function_exists( 'ini_get' ) &&
			\ini_get( 'max_execution_time' ) < 60
		) {
			\set_time_limit( 0 );
		}
		if ( empty( $_REQUEST['s3io_url'] ) ) {
			$output['error'] = \esc_html__( 'No URL supplied', 's3-image-optimizer' );
			\wp_die( \wp_json_encode( $output ) );
		}
		$url = \esc_url_raw( \wp_unslash( $_REQUEST['s3io_url'] ) );
		if ( ! empty( $url ) ) {
			$url_args = $this->get_args_from_url( $url );
		}
		if ( ! empty( \s3io()->errors ) ) {
			\wp_die(
				\wp_json_encode(
					array(
						'error' => \sprintf(
							/* translators: %s: AWS/S3 error message(s) */
							\esc_html__( 'Error retrieving path information: %s', 's3-image-optimizer' ),
							\wp_kses_post( \implode( '<br>', \s3io()->errors ) )
						),
					)
				)
			);
		}
		if ( empty( $url ) || empty( $url_args ) ) {
			/* translators: %s: path to an image */
			$output['results'] = '<p>' . \sprintf( \esc_html__( 'Image not found: %s', 's3-image-optimizer' ), '<strong>' . \esc_html( $url ) . '</strong>' ) . '</p>';
			\wp_die( \wp_json_encode( $output ) );
		}
		$url_args['path'] = \ltrim( $url_args['path'], '/' );

		$upload_dir = $this->make_upload_dir();
		if ( ! $upload_dir ) {
			\wp_die(
				\wp_json_encode(
					array(
						'error' => \esc_html__( 'Could not create the /s3io/ folder within the WordPress uploads folder, please adjust the permissions and try again.', 's3-image-optimizer' ),
					)
				)
			);
		}
		$upload_dir = \trailingslashit( $upload_dir ) . \trailingslashit( \sanitize_file_name( $url_args['bucket'] ) );
		$this->debug_message( "stashing files in $upload_dir" );
		if ( false !== \strpos( $upload_dir, 's3://' ) ) {
			\wp_die(
				\wp_json_encode(
					array(
						/* translators: %s: path to uploads directory */
						'error' => \sprintf( \esc_html__( 'Received an unusable working directory: %s', 's3-image-optimizer' ), esc_html( $upload_dir ) ),
					)
				)
			);
		}
		try {
			$client = \s3io()->amazon_web_services->get_client();
		} catch ( AwsException | S3Exception | Exception $e ) {
			\wp_die(
				\wp_json_encode(
					array(
						'error' => \sprintf(
							/* translators: %s: AWS/S3 error message */
							\esc_html__( 'Error connecting to S3: %s', 's3-image-optimizer' ),
							\wp_kses_post( $this->format_aws_exception( $e->getMessage() ) )
						),
					)
				)
			);
		}
		$filename = $upload_dir . $url_args['path'];
		$full_dir = \dirname( $filename );
		if ( ! \is_dir( $full_dir ) ) {
			\wp_mkdir_p( $full_dir );
		}
		try {
			$fetch_result = $client->getObject(
				array(
					'Bucket' => $url_args['bucket'],
					'Key'    => $url_args['path'],
					'SaveAs' => $filename,
				)
			);
		} catch ( AwsException | S3Exception | Exception $e ) {
			$s3_error = $e->getMessage();
			$this->debug_message( "failed to fetch $filename: $s3_error" );
			if ( \str_contains( $s3_error, '404 Not Found' ) ) {
				$output['results'] = '<p>';
				/* translators: %s: path to an image */
				$output['results'] .= \sprintf( \esc_html__( 'Image not found: %s', 's3-image-optimizer' ), '<strong>' . \esc_html( $url_args['bucket'] . '/' . $url_args['path'] ) . '</strong>' ) . '</p>';
				\wp_die( \wp_json_encode( $output ) );
			} else {
				\wp_die(
					\wp_json_encode(
						array(
							'error' => \sprintf(
								/* translators: 1: bucket name, 2: path to file, 3: error message */
								\esc_html__( 'Fetch failed for bucket: %1$s, path: %2$s, message: %3$s', 's3-image-optimizer' ),
								\esc_html( $url_args['bucket'] ),
								\esc_html( $url_args['path'] ),
								\wp_kses_post( $this->format_aws_exception( $s3_error ) )
							),
						)
					)
				);
			}
		}
		global $ewww_image;
		$orig_size = $this->filesize( $filename );
		// Make sure EWWW IO doesn't skip images.
		\ewwwio()->force = true;
		// Do the optimization for the current image.
		$results         = \ewww_image_optimizer( $filename );
		\ewwwio()->force = false;
		$ewww_status     = \get_transient( 'ewww_image_optimizer_cloud_status' );
		if ( 'exceeded' === $ewww_status ) {
			\unlink( $filename );
			$output['error'] = \esc_html__( 'License Exceeded', 's3-image-optimizer' );
			\wp_die( \wp_json_encode( $output ) );
		}

		$ownership_control_enforced = $this->object_ownership_enforced( $url_args['bucket'] );

		$new_size = $this->filesize( $filename );
		if ( $new_size && $new_size < $fetch_result['ContentLength'] ) {
			// Re-upload to S3.
			try {
				$client->putObject(
					array(
						'Bucket'       => $url_args['bucket'],
						'Key'          => $url_args['path'],
						'SourceFile'   => $filename,
						'ContentType'  => $fetch_result['ContentType'],
						'CacheControl' => 'max-age=31536000',
					)
				);
				if ( ! $ownership_control_enforced ) {
					$client->putObjectAcl(
						array(
							'Bucket' => $url_args['bucket'],
							'Key'    => $url_args['path'],
							'ACL'    => 'public-read',
						)
					);
				}
			} catch ( AwsException | S3Exception | Exception $e ) {
				$output['error'] = \sprintf(
					/* translators: 1: bucket name, 2: path to file, 3: error message */
					\esc_html__( 'Upload failed for bucket: %1$s, path: %2$s, message: %3$s', 's3-image-optimizer' ),
					\esc_html( $url_args['bucket'] ),
					\esc_html( $url_args['path'] ),
					\wp_kses_post( $this->format_aws_exception( $e->getMessage() ) )
				);
				\wp_die( \wp_json_encode( $output ) );
			}
		}
		\unlink( $filename );
		$webp_error    = 0;
		$webp_filename = \ewww_image_optimizer_get_webp_path( $filename );
		$webp_key      = \ewww_image_optimizer_get_webp_path( $url_args['path'] );
		$webp_size     = $this->filesize( $webp_filename );
		if ( is_object( $ewww_image ) && ! empty( $ewww_image->webp_error ) && is_int( $ewww_image->webp_error ) ) {
			$webp_error = $ewww_image->webp_error;
		}
		if ( $webp_size && ! empty( $webp_key ) ) {
			// Upload to S3.
			try {
				$client->putObject(
					array(
						'Bucket'       => $url_args['bucket'],
						'Key'          => $webp_key,
						'SourceFile'   => $webp_filename,
						'ContentType'  => 'image/webp',
						'CacheControl' => 'max-age=31536000',
					)
				);
				if ( ! $ownership_control_enforced ) {
					$client->putObjectAcl(
						array(
							'Bucket' => $url_args['bucket'],
							'Key'    => $webp_key,
							'ACL'    => 'public-read',
						)
					);
				}
			} catch ( AwsException | S3Exception | Exception $e ) {
				$output['error'] = \sprintf(
					/* translators: 1: bucket name, 2: path to file, 3: error message */
					\esc_html__( 'Upload failed for bucket: %1$s, path: %2$s, message: %3$s', 's3-image-optimizer' ),
					\esc_html( $url_args['bucket'] ),
					\esc_html( $webp_key ),
					\wp_kses_post( $this->format_aws_exception( $e->getMessage() ) )
				);
				\wp_die( \wp_json_encode( $output ) );
			}
			\unlink( $webp_filename );
		}
		$this->table_update( $url_args['path'], $new_size, $fetch_result['ContentLength'], $webp_size, $webp_error, false, $url_args['bucket'] );
		// Make sure ewww doesn't keep a record of these files.
		global $wpdb;
		$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => \ewww_image_optimizer_relativize_path( $filename ) ) );
		if ( \function_exists( 'ewww_image_optimizer_debug_log' ) ) {
			\ewww_image_optimizer_debug_log();
		}

		$elapsed           = \microtime( true ) - $started;
		$output['results'] = '<p>';
		/* translators: %s: path to an image */
		$output['results'] .= \sprintf( \esc_html__( 'Optimized image: %s', 's3-image-optimizer' ), '<strong>' . \esc_html( $url_args['bucket'] . '/' . $url_args['path'] ) . '</strong>' ) . '<br>';
		$output['results'] .= \esc_html( $this->get_results_msg( $orig_size, $new_size ) ) . '<br>';
		if ( $webp_size ) {
			/* translators: %s: a result message, like 'Reduced by 66.9% (2.5 MB)', translated elsewhere */
			$output['results'] .= \sprintf( \esc_html__( 'WebP: %s', 's3-image-optimizer' ), $this->get_results_msg( $orig_size, $webp_size ) ) . '<br>';
		}
		/* translators: %s: time in seconds */
		$output['results'] .= \sprintf( \esc_html__( 'Elapsed: %s seconds', 's3-image-optimizer' ), \number_format_i18n( $elapsed, 1 ) ) . '</p>';

		\wp_die( \wp_json_encode( $output ) );
	}

	/**
	 * Takes an S3/Spaces URL and gets the bucket and path for an object.
	 *
	 * @param string $url The url of the image on S3/Spaces.
	 * @return array The bucket and path of the object.
	 */
	protected function get_args_from_url( $url ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$url     = \urldecode( $url );
		$urlinfo = \parse_url( $url );
		if ( ! $urlinfo ) {
			$this->debug_message( "failed to parse $url" );
			return false;
		}
		if ( \defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) && \S3_IMAGE_OPTIMIZER_BUCKET ) {
			if ( \strpos( $urlinfo['host'], \S3_IMAGE_OPTIMIZER_BUCKET ) !== false ) {
				$this->debug_message( 'found ' . \S3_IMAGE_OPTIMIZER_BUCKET . ' and ' . $urlinfo['path'] );
				return array(
					'bucket' => \S3_IMAGE_OPTIMIZER_BUCKET,
					'path'   => $urlinfo['path'],
				);
			}
			if ( \strpos( $urlinfo['path'], \S3_IMAGE_OPTIMIZER_BUCKET ) !== false ) {
				$path = \str_replace( '/' . \S3_IMAGE_OPTIMIZER_BUCKET, '', $urlinfo['path'] );
				$this->debug_message( 'found ' . \S3_IMAGE_OPTIMIZER_BUCKET . ' and ' . $path );
				return array(
					'bucket' => \S3_IMAGE_OPTIMIZER_BUCKET,
					'path'   => $path,
				);
			}
		}

		$bucket_list = $this->get_selected_buckets();

		// If we don't have a list of buckets, we can't do much more here.
		if ( empty( $bucket_list ) || ! is_array( $bucket_list ) ) {
			$this->debug_message( 'could not retrieve list of buckets' );
			return false;
		}

		foreach ( $bucket_list as $aws_bucket ) {
			if ( \strpos( $urlinfo['host'], $aws_bucket ) !== false ) {
				$this->debug_message( 'found ' . $aws_bucket . ' and ' . $urlinfo['path'] );
				return array(
					'bucket' => $aws_bucket,
					'path'   => $urlinfo['path'],
				);
			}
			if ( \strpos( $urlinfo['path'], $aws_bucket ) !== false ) {
				$path = \str_replace( '/' . $aws_bucket, '', $urlinfo['path'] );
				$this->debug_message( 'found ' . $aws_bucket . ' and ' . $path );
				return array(
					'bucket' => $aws_bucket,
					'path'   => $path,
				);
			}
		}

		// Otherwise, we must have a custom domain, so lets do a quick search for the attachment in all buckets.
		// Done in a separate foreach, in case there are performance implications of switching the region in accounts with lots of buckets.
		try {
			$client = \s3io()->amazon_web_services->get_client();
		} catch ( AwsException | S3Exception | Exception $e ) {
			\s3io()->errors[] = $this->format_aws_exception( $e->getMessage() );
			$this->debug_message( 'unable to initialize AWS client lib' );
			return false;
		}
		foreach ( $bucket_list as $aws_bucket ) {
			if ( \str_contains( $urlinfo['host'], $aws_bucket ) ) {
				$key = \ltrim( $urlinfo['path'], '/' );
				if ( $this->object_exists( $aws_bucket, $key, $client ) ) {
					$this->debug_message( 'found ' . $aws_bucket . ' and ' . $urlinfo['path'] );
					return array(
						'bucket' => $aws_bucket,
						'path'   => $urlinfo['path'],
					);
				}
			}
			if ( \str_contains( $urlinfo['path'], $aws_bucket ) ) {
				$path = \str_replace( '/' . $aws_bucket, '', $urlinfo['path'] );
				$key  = \ltrim( $path, '/' );
				if ( $this->object_exists( $aws_bucket, $key, $client ) ) {
					$this->debug_message( 'found ' . $aws_bucket . ' and ' . $path );
					return array(
						'bucket' => $aws_bucket,
						'path'   => $path,
					);
				}
			}
		}
		$this->debug_message( "failed to find $url" );
		return false;
	}

	/**
	 * Displays 50 records from the auxiliary images table.
	 */
	public function show_table() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Verify that an authorized user has called function.
		$permissions = \apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
		if ( empty( $_REQUEST['s3io_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! \current_user_can( $permissions ) ) {
			die( \esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) );
		}
		if ( empty( $_POST['s3io_offset'] ) ) {
			$_POST['s3io_offset'] = 0;
		}
		global $wpdb;
		$already_optimized = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->s3io_images WHERE image_size IS NOT NULL ORDER BY id DESC LIMIT %d,50", 50 * (int) $_POST['s3io_offset'] ), \ARRAY_A );
		echo '<br /><table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>' . \esc_html__( 'Bucket', 's3-image-optimizer' ) . '</th><th>' . \esc_html__( 'Filename', 's3-image-optimizer' ) . '</th><th>' . \esc_html__( 'Image Optimizer', 's3-image-optimizer' ) . '</th></tr></thead>';
		$alternate = true;
		foreach ( $already_optimized as $optimized_image ) {
			$file_size = $this->size_format( $optimized_image['image_size'], 1 );
			?>
			<tr<?php echo ( $alternate ? " class='alternate'" : '' ); ?> id="s3io-image-<?php echo (int) $optimized_image['id']; ?>">
				<td class='title'><?php echo \esc_html( $optimized_image['bucket'] ); ?></td>
				<td class='title'><?php echo \esc_html( $optimized_image['path'] ); ?></td>
				<td>
					<?php
					/* translators: %s: size of image, in bytes */
					echo \esc_html( $this->get_results_msg( $optimized_image['orig_size'], $optimized_image['image_size'] ) ) . ' <br>' . \sprintf( \esc_html__( 'Image Size: %s', 's3-image-optimizer' ), \esc_html( $file_size ) );
					?>
					<?php if ( ! empty( $optimized_image['webp_size'] ) ) : ?>
						<br>WebP: <?php echo \esc_html( $this->size_format( $optimized_image['webp_size'] ) ); ?>
					<?php elseif ( ! empty( $optimized_image['webp_error'] ) && 2 !== (int) $optimized_image['webp_error'] ) : ?>
						<br><?php echo \esc_html( \ewww_image_optimizer_webp_error_message( $optimized_image['webp_error'] ) ); ?>
					<?php endif; ?>
					<br><a class="removeimage" onclick="s3ioRemoveImage( <?php echo (int) $optimized_image['id']; ?> )"><?php \esc_html_e( 'Remove from table', 's3-image-optimizer' ); ?></a>
				</td>
			</tr>
			<?php
			$alternate = ! $alternate;
		}
		echo '</table>';
		die;
	}

	/**
	 * Removes an image from the auxiliary images table.
	 */
	public function remove_from_table() {
		// Verify that an authorized user has called function.
		$permissions = \apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
		if ( empty( $_REQUEST['s3io_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! \current_user_can( $permissions ) ) {
			die( \esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) );
		}
		if ( empty( $_POST['s3io_image_id'] ) ) {
			echo '0';
		} elseif ( $this->table_delete_image( (int) $_POST['s3io_image_id'] ) ) {
			echo '1';
		}
		die;
	}

	/**
	 * Update a record in the database after optimization.
	 *
	 * @param string $path The location of the file.
	 * @param int    $opt_size The filesize of the optimized image.
	 * @param int    $orig_size The original filesize of the image.
	 * @param int    $webp_size The filesize of the WebP image (if any). Optional.
	 * @param int    $webp_error An error code for the WebP conversion. Optional.
	 * @param int    $id The ID of the db record which we are about to update. Optional. Default to false.
	 * @param string $bucket The name of the bucket where the image is located. Optional. Default to empty string.
	 */
	protected function table_update( $path, $opt_size, $orig_size, $webp_size = 0, $webp_error = 0, $id = false, $bucket = '' ) {
		global $wpdb;

		$opt_size  = (int) $opt_size;
		$orig_size = (int) $orig_size;
		$id        = $id ? (int) $id : (bool) $id;

		if ( $opt_size >= $orig_size ) {
			$this->debug_message( 's3io: no savings' );
			if ( $id ) {
				$this->debug_message( "s3io: looking for $id" );
				$optimized_query = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->s3io_images WHERE id = %d", $id ), \ARRAY_A );
				if ( $optimized_query && ! empty( $optimized_query['image_size'] ) && (int) $optimized_query['image_size'] === (int) $opt_size ) {
					$this->debug_message( "s3io: no change for $id" );
					return;
				}
				// Store info on the current image for future reference.
				$updated = $wpdb->update(
					$wpdb->s3io_images,
					array(
						'image_size' => $opt_size,
						'webp_size'  => $webp_size,
						'webp_error' => $webp_error,
						'pending'    => 0,
					),
					array(
						'id' => $id,
					),
					array(
						'%d',
						'%d',
						'%d',
						'%d',
					),
					array(
						'%d',
					)
				);
				if ( false === $updated ) {
					$this->debug_message( 'db error: ' . $wpdb->last_error );
				} elseif ( $updated ) {
					$this->debug_message( "s3io: updated $id" );
					$wpdb->flush();
				}
				return;
			}
		} else {
			if ( $id ) {
				$this->debug_message( "s3io: updating $id" );
				// Store info on the current image for future reference.
				$updated = $wpdb->update(
					$wpdb->s3io_images,
					array(
						'image_size' => $opt_size,
						'webp_size'  => $webp_size,
						'webp_error' => $webp_error,
						'pending'    => 0,
					),
					array(
						'id' => $id,
					),
					array(
						'%d',
						'%d',
						'%d',
						'%d',
					),
					array(
						'%d',
					)
				);
				if ( false === $updated ) {
					$this->debug_message( 'db error: ' . $wpdb->last_error );
				} elseif ( $updated ) {
					$this->debug_message( "s3io: updated $id" );
					$wpdb->flush();
				}
				return;
			}
		}
		$this->debug_message( "s3io: falling back to search by $path" );
		$optimized_query = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->s3io_images WHERE path = %s", $path ), \ARRAY_A );
		if ( ! empty( $optimized_query ) ) {
			$this->debug_message( 's3io: found results by path, checking...' );
			foreach ( $optimized_query as $image ) {
				$this->debug_message( $image['path'] );
				if ( $image['path'] === $path && $image['bucket'] === $bucket ) {
					$this->debug_message( 'found a match' );
					$already_optimized = $image;
				}
			}
		}
		if ( ! empty( $already_optimized['image_size'] ) && (int) $opt_size === (int) $already_optimized['image_size'] ) {
			$this->debug_message( 'returning results without update, no change' );
			return;
		}
		if ( ! empty( $already_optimized['id'] ) ) {
			// Store info on the current image for future reference.
			$updated = $wpdb->update(
				$wpdb->s3io_images,
				array(
					'image_size' => $opt_size,
					'webp_size'  => $webp_size,
					'webp_error' => $webp_error,
					'pending'    => 0,
				),
				array(
					'id' => $already_optimized['id'],
				),
				array(
					'%d',
					'%d',
					'%d',
					'%d',
				),
				array(
					'%d',
				)
			);
			if ( false === $updated ) {
				$this->debug_message( 'db error: ' . $wpdb->last_error );
			} elseif ( $updated ) {
				$this->debug_message( "s3io: updated results for $path ({$already_optimized['id']})" );
				$wpdb->flush();
			}
			return;
		}
		$this->debug_message( 'no existing records found, inserting new one' );
		$inserted = $wpdb->insert(
			$wpdb->s3io_images,
			array(
				'bucket'     => $bucket,
				'path'       => $path,
				'image_size' => $opt_size,
				'orig_size'  => $orig_size,
				'webp_size'  => $webp_size,
				'webp_error' => $webp_error,
			),
			array(
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
			)
		);
		if ( $inserted ) {
			$this->debug_message( 'successful INSERT' );
		}
		$wpdb->flush();
		return;
	}
}
