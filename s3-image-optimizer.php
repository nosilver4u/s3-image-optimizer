<?php
/*
Plugin Name: S3 Image Optimizer
Description: Reduce file sizes for images in S3 buckets using lossless and lossy optimization methods via the EWWW Image Optimizer.
Author: Shane Bishop
Text Domain: s3-image-optimizer
Version: .2
Author URI: https://ewww.io/
*/

// Constants
define( 'S3IO_VERSION', '.24' );
// this is the full path of the plugin file itself
define( 'S3IO_PLUGIN_FILE', __FILE__ );
// this is the path of the plugin file relative to the plugins/ folder
define( 'S3IO_PLUGIN_FILE_REL', 's3-image-optimizer/s3-image-optimizer.php' );
// the site for auto-update checking
define( 'S3IO_SL_STORE_URL', 'https://ewww.io' );
// product name for update checking
define( 'S3IO_SL_ITEM_NAME', 'S3 Image Optimizer' );

add_action( 'admin_init', 's3io_admin_init' );
add_action( 'admin_menu', 's3io_admin_menu', 60 );
add_action( 'admin_init', 's3io_activate_license' );

global $wpdb;
if ( ! isset( $wpdb->s3io_images ) ) {
	$wpdb->s3io_images = $wpdb->prefix . "s3io_images";
}

function s3io_admin_init() {
	// if we ever have multisite global options some day:
/*	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( S3IO_PLUGIN_FILE_REL ) ) {
		if ( isset( $_POST['s3io_bucketlist'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 's3io_options_page-options' ) ) {
			if ( empty( $_POST['s3io_bucketlist'] ) ) $_POST['s3io_bucketlist'] = '';
			update_site_option( 's3io_bucketlist', s3io_bucketlist_sanitize( $_POST['s3io_bucketlist'] ) );
			add_action('network_admin_notices', 's3io_network_settings_saved');
		}
	}*/

	register_setting( 's3io_options', 's3io_verion' );
	register_setting( 's3io_options', 's3io_bucketlist', 's3io_bucketlist_sanitize' );
	register_setting( 's3io_options', 's3io_resume' );
	register_setting( 's3io_options', 's3io_license_key', 's3io_license_sanitize' );
	if ( get_option( 's3io_version' ) < S3IO_VERSION ) {
		s3io_install_table();
		//s3io_set_defaults();
		update_option( 's3io_version', S3IO_VERSION );
	}
	global $wp_version; 
	if ( substr($wp_version, 0, 3) >= 3.8 ) {  
		add_action('admin_enqueue_scripts', 's3io_progressbar_style'); 
	}
	if ( ! class_exists( 'Amazon_Web_Services' ) ) {
		add_action( 'network_admin_notices', 's3io_missing_aws_plugin');
		add_action( 'admin_notices', 's3io_missing_aws_plugin');
	}
	if ( ! function_exists( 'ewww_image_optimizer' ) ) {
		add_action( 'network_admin_notices', 's3io_missing_ewww_plugin');
		add_action( 'admin_notices', 's3io_missing_ewww_plugin');
	}

	if ( ! class_exists( 'EDD_SL_Plugin' ) ) {
		include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
	}
	$license_key = trim( get_option( 's3io_license_key' ) );
	$edd_updater = new EDD_SL_Plugin_Updater( S3IO_SL_STORE_URL, __FILE__, array(
		'version'	=> '0.2',
		'license'	=> $license_key,
		'item_name'	=> S3IO_SL_ITEM_NAME,
		'author'	=> 'Shane Bishop',
		'url'		=> home_url(),
	) );
}

function s3io_install_table() {
	global $wpdb;
	//TODO: make this as robust as the ewwwio_table stuff to avoid mb4 issues on the index
	$charset_collate = $wpdb->get_charset_collate();
	
	// create a table with 5 columns: an id, the file path, the optimization results, optimized image size, and original image size
	$sql = "CREATE TABLE $wpdb->s3io_images (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		bucket VARCHAR(100),
		path text NOT NULL,
		results VARCHAR(55) NOT NULL,
		image_size int UNSIGNED,
		orig_size int UNSIGNED,
		UNIQUE KEY id (id),
		KEY path_image_size (path(255),image_size)
	) $charset_collate;";

	// include the upgrade library to initialize a table
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
	add_option( 's3io_license_status', '', 'no' );
/*	$s3io_attachments = get_option( 's3io_attachments', '' );
	delete_option( 's3io_attachments' );
	add_option('s3io_attachments', $s3io_attachments, '', 'no');*/
}

function s3io_activate_license() {
	if ( isset( $_POST['s3io_license_activate'] ) ) {
		if ( ! check_admin_referer( 's3io_activation_nonce', 's3io_activation_nonce' ) )
			return;

		$license = trim( $_POST['s3io_license_key'] );

		$api_params = array(
			'edd_action' => 'activate_license',
			'license' => $license,
			'item_name' => urlencode( S3IO_SL_ITEM_NAME ),
			'url' => home_url(),
		);

		$response = wp_remote_post( S3IO_SL_STORE_URL, array(
			'timeout' => 15,
			'sslverify' => true,
			'body' => $api_params,
		) );

		if ( is_wp_error( $response ) )
			return false;

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		update_option( 's3io_license_status', $license_data->license );
	}
}

function s3io_progressbar_style() {
	if ( function_exists( 'wp_add_inline_style' ) ) {
		wp_add_inline_style( 'jquery-ui-progressbar', ".ui-widget-header { background-color: " . s3io_admin_background() . "; }" );
	}
}

// determines the background color to use based on the selected theme
function s3io_admin_background() {
	$user_info = wp_get_current_user();
	switch ( $user_info->admin_color ) {
		case 'midnight':
			return "#e14d43";
		case 'blue':
			return "#096484";
		case 'light':
			return "#04a4cc";
		case 'ectoplasm':
			return "#a3b745";
		case 'coffee':
			return "#c7a589";
		case 'ocean':
			return "#9ebaa0";
		case 'sunrise':
			return "#dd823b";
		default:
			return "#0073aa";
	}
}

function s3io_missing_aws_plugin() {
	echo "<div id='s3io-error-aws' class='error'><p>" . __( 'Could not detect the Amazon Web Services plugin, please install and configure it first.', 's3-image-optimizer' ) . "</p></div>";
}

function s3io_missing_ewww_plugin() {
	echo "<div id='s3io-error-ewww' class='error'><p>" . __( 'Could not detect the EWWW Image Optimizer plugin, please install and configure it first.', 's3-image-optimizer' ) . "</p></div>";
}

function s3io_admin_menu() {
	add_media_page( __( 'S3 Optimizer', 's3-image-optimizer' ), __( 'S3 Image Optimizer', 's3-image-optimizer' ), 'activate_plugins', 's3io-bulk-display', 's3io_bulk_display' );
	// add options page to the settings menu
	add_options_page(
		'S3 Image Optimizer',		//Title
		'S3 Image Optimizer',		//Sub-menu title
		'activate_plugins',		//Security
		S3IO_PLUGIN_FILE,		//File to open
		's3io_options_page'	//Function to call
	);
}

function s3io_options_page() {
	if ( class_exists( 'Amazon_Web_Services' ) ) {
		global $amazon_web_services;
		$aws = $amazon_web_services->get_client();
		$client = $aws->get( 'S3' );
		$buckets = $client->listBuckets();
		$license_status = get_option( 's3io_license_status' );
?>
		<div class='wrap'>
			<h1>S3 Image Optimizer</h1>
			<form method='post' action='options.php'>
<?php				settings_fields( 's3io_options' ); ?>
				<table class='form-table'>
					<tr><th><label for='s3io_license_key'><?php _e( 'License Key', 's3-image-optimizer' ) ?></label></th><td><input type="text" id="s3io_license_key" name="s3io_license_key" value="<?php echo get_option( 's3io_license_key' ) ?>" size="32" /> <?php _e( 'Enter your license key to activate automatic update checking', 's3-image-optimizer' ) ?></td></tr>
<?php		if ( false !== get_option( 's3io_license_key' ) ) { ?>
					<tr valign="top"><th scope="row" valign="top">
						<?php _e( 'Activate License', 's3-image-optimizer' ); ?>
					</th>
					<td>
<?php 			if ( $license_status !== false && $license_status == 'valid' ) { ?>
						<span style="color:green;"><?php _e( 'active', 's3-image-optimizer' ); ?></span>
<?php			} else {
				wp_nonce_field( 's3io_activation_nonce', 's3io_activation_nonce' ) ?>
						<input type="submit" class="button-secondary" name="s3io_license_activate" value="<?php _e( 'Activate License', 's3-image-optimizer' ); ?>"/>
<?php			} ?>
					</td></tr>
<?php		} ?>
					<tr><th><label for='s3io_bucketlist'><?php _e( 'Buckets to optimize', 's3-image-optimizer' ) ?></label></th><td><?php _e( 'One bucket per line, must match one of the buckets listed below. If empty, all available buckets will be optimized.', 's3-image-optimizer' ) ?><br>
					<textarea id='s3io_bucketlist' name='s3io_bucketlist' rows='3' cols='40'>
<?php 						$bucket_list = get_option( 's3io_bucketlist' );
						if ( ! empty( $bucket_list ) ) {
							foreach ( $bucket_list as $bucket ) {
								echo "$bucket\n";
							}
						}
					?></textarea>
					<p class='description'><?php _e( 'These are the buckets that we have access to optimize:', 's3-image-optimizer' ) ?><br>
<?php					foreach ( $buckets['Buckets'] as $bucket ) {
						echo "{$bucket['Name']}<br>\n";
					}?>
					</p>
					</td></tr>
				</table>
				<p class='submit'><input type='submit' class='button-primary' value='<?php _e( 'Save Changes', 's3-image-optimizer' ) ?>' /></p>
			</form>
		</div>
<?php	}
}

function s3io_bucketlist_sanitize( $input ) {
	if ( empty( $input ) ) {
		return '';
	}
	if ( ! class_exists( 'Amazon_Web_Services' ) ) {
		return '';
	}
	global $amazon_web_services;
	$aws = $amazon_web_services->get_client();
	$client = $aws->get( 'S3' );
	$buckets = $client->listBuckets();
	$bucket_array = array();
	$input_buckets = explode("\n", $input);
	foreach ( $input_buckets as $input_bucket) {
		$input_bucket = trim( $input_bucket );
		foreach ( $buckets['Buckets'] as $bucket ) {
			if ( $input_bucket == $bucket['Name'] ) {
				$bucket_array[] = $input_bucket;
			}
		}
	}
	return $bucket_array;
}

function s3io_license_sanitize( $input ) {
	if ( empty( $input ) ) {
		return '';
	}
	$input = trim( $input );
	if ( preg_match( '/^[a-zA-Z0-9]+$/', $input ) ) {
		$old = get_option( 's3io_license_key' );
		if ( $old && $old != $input ) {
			delete_option( 's3io_license_status' );
		}
		return $input;
	} else {
		return '';
	}
}

function s3io_make_upload_dir_failed() {
	echo "<div id='s3io-error-mkdir' class='error'><p>" . __( 'Could not create the s3io folder within the WordPress uploads folder, please adjust the permissions and try again.', 's3-image-optimizer' ) . "</p></div>";
}

// prepares the bulk operation and includes the javascript functions
function s3io_bulk_script( $hook ) {
	// make sure we are being called from the proper page
	if ( 's3io-auto' !== $hook && 'media_page_s3io-bulk-display' != $hook ) {
		return;
	}
	$upload_dir = wp_upload_dir();
	$upload_dir = trailingslashit( $upload_dir['basedir'] ) . 's3io/';
	if ( ! is_dir( $upload_dir ) ) {
		$mkdir = mkdir( $upload_dir );
		if ( ! $mkdir ) {
			add_action( 'admin_notices', 's3io_make_upload_dir_failed' );
		}
	}
	global $wpdb;
	// initialize the $attachments variable for s3 images
//	$attachments = array();
	// check to see if the user has asked to reset (empty) the optimized images table
	if ( ! empty( $_REQUEST['s3io_force_empty'] ) && wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) ) {
		s3io_table_truncate();
	}
	// check to see if we are supposed to reset the bulk operation and verify we are authorized to do so
	if ( ! empty( $_REQUEST['s3io_reset_bulk'] ) && wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) ) {
		update_option( 's3io_resume', '' );
	}
	// check the 'bulk resume' option
	$resume = get_option( 's3io_resume' );
	
        // check if there is a previous bulk operation to resume
//        if ( ! empty( $resume ) ) {
		// retrieve the attachment keys that have not been finished
//		$attachments = get_option( 's3io_attachments' );
//	} else {
	if ( empty( $resume ) ) {
		s3io_table_delete_pending();
		s3io_image_scan();
		// store the filenames we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
//		update_option( 's3io_attachments', $attachments );
	}
	if ( 'media_page_s3io_bulk-display' != $hook ) {
		// submit a couple variables to the javascript to work with
//		$attachments = json_encode( $attachments );
		wp_enqueue_script( 's3iobulkscript', plugins_url( '/s3io.js', __FILE__ ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar' ) );
		$image_count = s3io_table_count_optimized();
		wp_localize_script( 's3iobulkscript', 's3io_vars', array(
			'_wpnonce' => wp_create_nonce( 's3io-bulk' ),
			'attachments' => s3io_table_count_pending(),
			//'attachments' => $attachments_queued,
			'image_count' => $image_count,
			'count_string' => sprintf( __( '%d images', 's3-image-optimizer' ), $image_count ),
//			'scan_fail' => __( 'Operation timed out, you may need to increase the max_execution_time for PHP', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'license_exceeded' => __( 'License Exceeded', 's3-image-optimizer' ),
			'operation_stopped' => __( 'Optimization stopped, reload page to resume.', 's3-image-optimizer' ),
			'operation_interrupted' => __( 'Operation Interrupted', 's3-image-optimizer' ),
			'temporary_failure' => __( 'Temporary failure, seconds left to retry:', 's3-image-optimizer' ),
			'remove_failed' => __( 'Could not remove image from table.', 's3-image-optimizer' ),
			'optimized' => __( 'Optimized', 's3-image-optimizer' ),
		) );
		wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( 'jquery-ui-1.10.1.custom.css', __FILE__) );
	} else {
		return;
	}
}

// scan buckets for images and return them as an array
function s3io_image_scan() {
	global $wpdb;
	$images = array();
	$image_count = 0;
//	$start = microtime( true );
	$bucket_list = get_option( 's3io_bucketlist' );
	global $amazon_web_services;
	$aws = $amazon_web_services->get_client();
	$client = $aws->get( 'S3' );
	if ( empty( $bucket_list ) ) {
		$bucket_list = array();
		$buckets = $client->listBuckets();
		foreach ( $buckets['Buckets'] as $aws_bucket ) {
			$bucket_list[] = $aws_bucket['Name'];
		}
	}
	foreach ( $bucket_list as $bucket ) {
		$iterator = $client->getIterator( 'ListObjects', array(
			'Bucket' => $bucket,
		) );
		$query = "SELECT path,image_size FROM $wpdb->s3io_images WHERE bucket LIKE '$bucket'";
		$already_optimized = $wpdb->get_results( $query, ARRAY_A );
		$optimized_list = array();
		foreach ( $already_optimized as $optimized ) {
			$optimized_path = $optimized['path'];
			$optimized_list[$optimized_path] = $optimized['image_size'];
		}
		if ( ewww_image_optimizer_stl_check() ) {
			set_time_limit( 0 );
		}
		foreach ( $iterator as $object ) {
			$skip_optimized = false;
			if ( preg_match( '/\.(jpe?g|png|gif)$/i', $object['Key'] ) ) {
				$path = $object['Key'];
				$image_size = $object['Size'];
				if ( isset( $optimized_list[ $path ] ) && $optimized_list[ $path ] == $image_size ) {
					$skip_optimized = true;
				}
			} else {
				continue;
			}
			if ( ! $skip_optimized || ! empty( $_REQUEST['s3io_force'] ) ) {
		/*		$images[] = array(
					'bucket' => $bucket,
					'path' => $path, 
					'size' => $image_size,
				);*/
				$images[] = "('$bucket','$path',$image_size)";
				$image_count++;
			}
			if ( $image_count > 10000 ) {
				// let's dump what we have so far to the db
				$image_count = 0;
				$insert_query = "INSERT INTO $wpdb->s3io_images (bucket,path,orig_size) VALUES" . implode( ',', $images );
				$wpdb->query( $insert_query );
				$images = array();
			}
		}
	}
	if ( ! empty( $images ) ) {
		$insert_query = "INSERT INTO $wpdb->s3io_images (bucket,path,orig_size) VALUES" . implode( ',', $images );
		$wpdb->query( $insert_query );
	}
	return $image_count;
}

// displays the 'Optimize Everything Else' section of the Bulk Optimize page
function s3io_bulk_display() {
	global $wpdb;
	// Retrieve the value of the 'aux resume' option and set the button text for the form to use
	$s3io_resume = get_option( 's3io_resume' );
	if ( empty( $s3io_resume ) ) {
		$button_text = __( 'Start optimizing', 's3-image-optimizer' );
	} else {
		$button_text = __( 'Resume previous optimization', 's3-image-optimizer' );
	}
	$image_count = s3io_table_count_pending(); //count( get_option( 's3io_attachments' ) );
	// find out if the auxiliary image table has anything in it
	$already_optimized = s3io_table_count_optimized();
	// generate the WP spinner image for display
	$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
	// check the last time the auxiliary optimizer was run
	$last_run = get_option( 's3io_last_run' );
	// set the timezone according to the blog settings
	$site_timezone = get_option( 'timezone_string' );
	if ( empty( $site_timezone ) ) {
		$site_timezone = 'UTC';
	}
	date_default_timezone_set( $site_timezone );
	?>
	<div class="wrap">
	<h1><?php _e( 'S3 Bulk Optimize', 's3-image-optimizer' ); ?></h1>
		<div id="s3io-bulk-loading">
			<p id="s3io-loading" class="s3io-bulk-info" style="display:none">&nbsp;<img src="<?php echo $loading_image; ?>" /></p>
		</div>
		<div id="s3io-bulk-progressbar"></div>
		<div id="s3io-bulk-counter"></div>
		<form id="s3io-bulk-stop" style="display:none;" method="post" action="">
			<br /><input type="submit" class="button-secondary action" value="<?php _e( 'Stop Optimizing', 's3-image-optimizer'); ?>" />
		</form>
		<div id="s3io-bulk-status"></div>
<?php		if ( empty( $image_count ) ) {
			echo '<p>' . __( 'There is nothing left to optimize.', 's3-image-optimizer' ) . '</p>';
		} else { ?>
		<form class="s3io-bulk-form">
			<p><label for="s3io-delay" style="font-weight: bold"><?php _e( 'Choose how long to pause between images (in seconds, 0 = disabled)', 's3-image-optimizer' ); ?></label>&emsp;<input type="text" id="s3io-delay" name="s3io-delay" value="<?php if ( $delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) ) { echo $delay; } else { echo 0; } ?>"></p>
			<div id="s3io-delay-slider" style="width:50%"></div>
		</form>
		<div id="s3io-bulk-forms"><p class="s3io-bulk-info">
			<p class="s3io-media-info s3io-bulk-info"><?php printf( __( 'There are %1$d images to be optimized.', 's3-image-optimizer' ), $image_count ); ?><br />
			<?php _e( 'Previously optimized images will be skipped by default.', 's3-image-optimizer' ); ?></p>
		<?php if ( ! empty( $last_run ) ) { ?>
			<p id="s3io-last-run" class="s3io-bulk-info"><?php printf( __( 'Last optimization was completed on %1$s at %2$s and optimized %3$d images', 's3-image-optimizer' ), date( get_option( 'date_format' ), $last_run[0] ), date( get_option( 'time_format' ), $last_run[0] ), $last_run[1] ); ?></p>
		<?php } ?>
			<form id="s3io-start" class="s3io-bulk-form" method="post" action="">
				<input id="s3io-first" type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
				<input id="s3io-again" type="submit" class="button-secondary action" style="display:none" value="<?php _e( 'Optimize Again', 's3-image-optimizer' ); ?>" />
			</form>
<?php		// if the 'resume' option was not empty, offer to reset it so the user can start back from the beginning
//		if ( ! empty( $s3io_resume ) ) {
		}
		if ( false ) {
?>			<p class="s3io-bulk-info"><?php _e( 'If you would like to start over again, press the Reset Status button to reset the bulk operation status.', 's3-image-optimizer' ); ?></p>
			<form id="s3io-bulk-reset" class="s3io-bulk-form" method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-aux-images', 's3io_wpnonce' ); ?>
				<input type="hidden" name="s3io_reset_bulk" value="1">
				<button type="submit" class="button-secondary action"><?php _e( 'Reset Status', 's3-image-optimizer' ); ?></button>
			</form>
<?php		} 
		if ( empty( $already_optimized ) ) {
			$display = ' style="display:none"';
		} else {
			$display = '';
?>			<p class="s3io-bulk-info" style="margin-top: 2.5em"><?php _e( 'Force a re-optimization of all images by erasing the optimization history. This cannot be undone, as it will remove all optimization records from the database.', 's3-image-optimizer' ); ?></p>
			<form id="s3io-force-empty" class="s3io-bulk-form" style="margin-bottom: 2.5em" method="post" action="">
				<?php wp_nonce_field( 's3io-bulk', 's3io_wpnonce' ); ?>
				<input type="hidden" name="s3io_force_empty" value="1">
				<button type="submit" class="button-secondary action"><?php _e( 'Erase Optimization History', 's3-image-optimizer' ); ?></button>
			</form>
<?php		}
?>			<p id="s3io-table-info" class="s3io-bulk-info"<?php echo "$display>"; printf( __( 'The optimizer keeps track of already optimized images to prevent re-optimization. There are %d images that have been optimized so far.', 's3-image-optimizer' ), $already_optimized ); ?></p>
			<form id="s3io-show-table" class="s3io-bulk-form" method="post" action=""<?php echo $display; ?>>
				<button type="submit" class="button-secondary action"><?php _e( 'Show Optimized Images', 's3-image-optimizer' ); ?></button>
			</form>
			<div class="tablenav s3io-aux-table" style="display:none">
			<div class="tablenav-pages s3io-table">
			<span class="displaying-num s3io-table"></span>
			<span id="paginator" class="pagination-links s3io-table">
				<a id="first-images" class="first-page" style="display:none">&laquo;</a>
				<a id="prev-images" class="prev-page" style="display:none">&lsaquo;</a>
				<?php _e( 'page', 's3-image-optimizer' ); ?> <span class="current-page"></span> <?php _e( 'of', 's3-image-optimizer' ); ?> 
				<span class="total-pages"></span>
				<a id="next-images" class="next-page" style="display:none">&rsaquo;</a>
				<a id="last-images" class="last-page" style="display:none">&raquo;</a>
			</span>
			</div>
			</div>
			<div id="s3io-bulk-table" class="s3io-table"></div>
			<span id="s3io-pointer" style="display:none">0</span>
		</div>
	</div>
<?php
}

// find the number of optimized images in the s3io_images table
function s3io_table_count_optimized() {
	global $wpdb;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->s3io_images WHERE image_size IS NOT NULL" );
	if ( ! empty( $_REQUEST['s3io_inline'] ) ) {
		echo $count;
		die();
	}
	return $count;
}

// find the number of un-optimized images in the s3io_images table
function s3io_table_count_pending() {
	global $wpdb;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->s3io_images WHERE image_size IS NULL" );
	if ( ! empty( $_REQUEST['s3io_inline'] ) ) {
		echo $count;
		die();
	}
	return $count;
}

// remove all un-optimized images from the s3io_images table
function s3io_table_delete_pending() {
	global $wpdb;
	$wpdb->query( "DELETE from $wpdb->s3io_images WHERE image_size IS NULL" );
}

// wipes out the s3io_images table to allow re-optimization
function s3io_table_truncate() {
	global $wpdb;
	$wpdb->query( "TRUNCATE TABLE $wpdb->s3io_images" );
}

// displays 50 records from the auxiliary images table
function s3io_table() {
	// verify that an authorized user has called function
	if ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) ) {
		wp_die( __( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) );
	} 
	global $wpdb;
	$offset = 50 * $_POST['s3io_offset'];
	$query = "SELECT id,bucket,path,results,image_size FROM $wpdb->s3io_images WHERE image_size IS NOT NULL ORDER BY id DESC LIMIT $offset,50";
	$already_optimized = $wpdb->get_results( $query, ARRAY_A );
//	$upload_info = wp_upload_dir();
//	$upload_path = $upload_info['basedir'];
	echo '<br /><table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>' . __( 'Bucket', 's3-image-optimizer' ) . '</th><th>' . __( 'Filename', 's3-image-optimizer' ) . '</th><th>' . __( 'Image Optimizer', 's3-image-optimizer' ) . '</th></tr></thead>';
	$alternate = true;
	foreach ( $already_optimized as $optimized_image ) {
//		$image_name = $optimized_image['path'];
//		$image_url = trailingslashit( get_site_url() ) . $image_name;
//		$savings = $optimized_image['results'];
		// if the path given is not the absolute path
//		if ( file_exists( $optimized_image[0] ) ) {
			// retrieve the mimetype of the attachment
//			$type = ewww_image_optimizer_mimetype( $optimized_image[0], 'i' );
			// get a human readable filesize
			$file_size = size_format( $optimized_image['image_size'], 2 );
			$file_size = str_replace( '.00 B ', ' B', $file_size );
?>			<tr<?php if ( $alternate ) { echo " class='alternate'"; } ?> id="s3io-image-<?php echo $optimized_image['id']; ?>">
				<td class='title'><?php echo $optimized_image['bucket']; ?></td>
				<td class='title'>...<?php echo $optimized_image['path']; ?></td>
				<td><?php echo "{$optimized_image['results']} <br>" . sprintf( __( 'Image Size: %s', 's3-image-optimizer' ), $file_size ); ?><br><a class="removeimage" onclick="s3ioRemoveImage( <?php echo $optimized_image['id']; ?> )"><?php _e( 'Remove from table', 's3-image-optimizer' ); ?></a></td>
			</tr>
<?php			$alternate = ! $alternate;
//		}
	}
	echo '</table>';
	die();
}

// removes an image from the auxiliary images table
function s3io_table_remove() {
	// verify that an authorized user has called function
	if ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) ) {
		wp_die( __( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) );
	} 
	global $wpdb;
	if ( $wpdb->delete( $wpdb->s3io_images, array( 'id' => $_POST['s3io_image_id'] ) ) ) {
		echo "1";
	}
	die();
}

// receives a path, results, optimized size, and an original size to insert into ewwwwio_images table
// if this is a $new image, copy the result stored in the database
function s3io_table_update( $path, $opt_size, $orig_size, $results_msg ) {
	global $wpdb;
	$query = $wpdb->prepare("SELECT id,orig_size,results,path FROM $wpdb->s3io_images WHERE path = %s", $path);
	$optimized_query = $wpdb->get_results($query, ARRAY_A);
	if ( ! empty( $optimized_query ) ) {
		foreach ( $optimized_query as $image ) {
			if ( $image['path'] == $path ) {
				$already_optimized = $image;
			}
		}
	}
	if ( ! empty( $already_optimized['results'] ) && $opt_size === $orig_size ) {
		$results_msg = $already_optimized['results'];
	} elseif ( $opt_size >= $orig_size ) {
		$results_msg = __( 'No savings', 's3-image-optimizer' );
	} elseif ( empty( $results_msg ) ) {
		// calculate how much space was saved
		$savings = intval( $orig_size ) - intval( $opt_size );
		// convert it to human readable format
		$savings_str = size_format( $savings, 1 );
		// replace spaces and extra decimals with proper html entity encoding
		$savings_str = preg_replace( '/\.0 B /', ' B', $savings_str );
		$savings_str = str_replace( ' ', '&nbsp;', $savings_str );
		// determine the percentage savings
		$percent = 100 - ( 100 * ( $opt_size / $orig_size ) );
		// use the percentage and the savings size to output a nice message to the user
		$results_msg = sprintf( __( "Reduced by %01.1f%% (%s)", 's3-image-optimizer' ),
			$percent,
			$savings_str
		);
	}
	// store info on the current image for future reference
	$wpdb->update( $wpdb->s3io_images,
		array(
			'image_size' => $opt_size,
			'results' => $results_msg,
		),
		array(
			'id' => $already_optimized['id'],
		));
	$wpdb->flush();
	return $results_msg;
}

// called by javascript to initialize some output
function s3io_bulk_init( $auto = false ) {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) || ! current_user_can( $permissions ) ) ) {
		wp_die( __( 'Access denied.', 's3-image-optimizer' ) );
	}
	// update the 'aux resume' option to show that an operation is in progress
	// NOTE: we don't do this anymore, because every refresh is essentially a "reset", but with resume capability. Why would they want to start from the beginning, when that won't really do anything
//	update_option( 's3io_resume', 'true' );
	// store the time and number of images for later display
//	$count = count( get_option( 's3io_attachments' ) );
	update_option( 's3io_last_run', array( time(), s3io_table_count_pending() ) );
	// let the user know that we are beginning
	if ( ! $auto ) {
		// generate the WP spinner image for display
		$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
		echo "<p>" . __( 'Optimizing', 's3-image-optimizer' ) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
		die();
	}
}

// called by javascript to output filename of attachment in progress
function s3io_bulk_filename() {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) || ! current_user_can( $permissions ) ) {
		wp_die( __( 'Access denied.', 's3-image-optimizer' ) );
	}
	// generate the WP spinner image for display
	$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
	global $wpdb;
	$image_record = $wpdb->get_row( "SELECT path FROM $wpdb->s3io_images WHERE image_size IS NULL", ARRAY_A );
	// let the user know that we are beginning
//	echo "<p>" . __( 'Optimizing', 's3-image-optimizer' ) . " <b>" . preg_replace( ":\\\':", "'", $_POST['s3io_attachment'] ) . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
	echo "<p>" . __( 'Optimizing', 's3-image-optimizer' ) . " <b>" . $image_record['path'] . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}

// called by javascript to process each image in the loop
function s3io_bulk_loop( $key = null, $auto = false ) {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) || ! current_user_can( $permissions ) ) ) {
		wp_die( __( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) );
	}
	if ( ! empty( $_REQUEST['s3io_sleep'] ) ) {
		sleep( $_REQUEST['s3io_sleep'] );
	}
	// retrieve the time when the optimizer starts
	$started = microtime( true );
	if ( ini_get( 'max_execution_time' ) < 60 ) {
		set_time_limit ( 0 );
	}
	// get the path of the current attachment
/*	if ( empty( $key ) ) {
		$key = $_POST['s3io_key'];
	}*/
	global $wpdb;
	$image_record = $wpdb->get_row( "SELECT id,bucket,path,orig_size FROM $wpdb->s3io_images WHERE image_size IS NULL", ARRAY_A );
	$upload_dir = wp_upload_dir();
	$upload_dir = trailingslashit( $upload_dir['basedir'] ) . 's3io/' . sanitize_file_name( $image_record['bucket'] ) . '/';
	global $amazon_web_services;
	$aws = $amazon_web_services->get_client();
	$client = $aws->get( 'S3' );
	$filename = $upload_dir . $image_record['path'];
	$full_dir = dirname( $filename );
	if ( ! is_dir( $full_dir ) ) {
		mkdir( dirname( $filename ), 0777, true );
	}
	$fetch_result = $client->getObject( array(
		'Bucket' => $image_record['bucket'], 
		'Key' => $image_record['path'],
		'SaveAs' => $filename,
	) );
	// get the 'attachments' with a list of attachments remaining
//	$attachments = get_option( 's3io_attachments' );
	// make sure EWWW I.O. doesn't do anything weird like skipping images or generating webp
	$_REQUEST['ewww_force'] = true;
	$webp = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', false );
	// do the optimization for the current image
	$results = ewww_image_optimizer( $filename );
	unset( $_REQUEST['ewww_force'] );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', $webp );
	global $ewww_exceed;
	if ( ! empty ( $ewww_exceed ) ) {
		unlink( $filename );
		if ( ! $auto ) {
			echo '-9exceeded';
		}
		die();
	}
	$new_size = filesize( $filename );
	if ( $new_size < $fetch_result['ContentLength'] ) {
		// re-upload to S3
		$client->putObject( array(
			'Bucket' => $image_record['bucket'],
			'Key' => $image_record['path'],
			'SourceFile' => $filename,
		) );
	}
	unlink( $filename );
	s3io_table_update( $image_record['path'], $new_size, $fetch_result['ContentLength'], $results[1] );
	$query = $wpdb->prepare( "DELETE FROM $wpdb->s3io_images WHERE path = %s", $filename );
	$wpdb->query( $query );
	if ( ! $auto ) {
		// output the path
		printf( "<p>" . __( 'Optimized image:', 's3-image-optimizer' ) . " <strong>%s</strong><br>", esc_html( $image_record['path'] ) );
		// tell the user what the results were for the original image
		printf( "%s<br>", $results[1] );
		// calculate how much time has elapsed since we started
		$elapsed = microtime( true ) - $started;
		// output how much time has elapsed since we started
		printf( __( 'Elapsed: %.3f seconds', 's3-image-optimizer' ) . "</p>", $elapsed);
		die();
	}
}

// called by javascript to cleanup after ourselves
function s3io_bulk_cleanup( $auto = false ) {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) || ! current_user_can( $permissions ) ) ) {
		wp_die( __( 'Access denied.', 's3-image-optimizer' ) );
	}
	$stored_last = get_option( 's3io_last_run' );
	update_option( 's3io_last_run', array( time(), $stored_last[1] ) );
	// all done, so we can update the bulk options with empty values
//	update_option( 'ewww_image_optimizer_aux_resume', '' );
//	update_option( 'ewww_image_optimizer_aux_attachments', '' );
	if ( ! $auto ) {
		// and let the user know we are done
		echo '<p><b>' . __( 'Finished', 's3-image-optimizer' ) . '</b></p>';
		die();
	}
}

add_action( 'admin_enqueue_scripts', 's3io_bulk_script' );
add_action( 'wp_ajax_s3io_query_table', 's3io_table' );
add_action( 'wp_ajax_s3io_table_count', 's3io_table_count_optimized' );
add_action( 'wp_ajax_s3io_table_remove', 's3io_table_remove' );
add_action( 'wp_ajax_s3io_bulk_init', 's3io_bulk_init' );
add_action( 'wp_ajax_s3io_bulk_filename', 's3io_bulk_filename' );
add_action( 'wp_ajax_s3io_bulk_loop', 's3io_bulk_loop' );
add_action( 'wp_ajax_s3io_bulk_cleanup', 's3io_bulk_cleanup' );
