<?php
/*
Plugin Name: S3 Image Optimizer
Description: Reduce file sizes for images in S3 buckets using lossless and lossy optimization methods via the EWWW Image Optimizer.
Author: Shane Bishop
Text Domain: s3-image-optimizer
Version: 1.1
Author URI: https://ewww.io/
*/

// Constants
define( 'S3IO_VERSION', '1.1' );
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
add_filter( 'aws_get_client_args', 's3io_addv4_args', 8 );
add_filter( 'aws_get_client_args', 's3io_eucentral_args' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( plugin_dir_path( __FILE__ ) . 's3cli.php' );
}

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
	register_setting( 's3io_options', 's3io_eucentral' );
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

	if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
		include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
	}
	$license_key = trim( get_option( 's3io_license_key' ) );
	$edd_updater = new EDD_SL_Plugin_Updater( S3IO_SL_STORE_URL, __FILE__, array(
		'version'	=> '1.1',
		'license'	=> $license_key,
		'item_name'	=> S3IO_SL_ITEM_NAME,
		'author'	=> 'Shane Bishop',
		'url'		=> home_url(),
	) );
}

function s3io_install_table() {
	global $wpdb;

	//see if the path column exists, and what collation it uses to determine the column index size
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->s3io_images'" ) == $wpdb->s3io_images ) {
                $current_collate = $wpdb->get_results( "SHOW FULL COLUMNS FROM $wpdb->s3io_images", ARRAY_A );
                if ( ! empty( $current_collate[1]['Field'] ) && $current_collate[1]['Field'] === 'path' && strpos( $current_collate[1]['Collation'], 'utf8mb4' ) === false ) {
                        $path_index_size = 255;
                }
	}
	$charset_collate = $wpdb->get_charset_collate();

	if ( empty( $path_index_size ) && strpos( $charset_collate, 'utf8mb4' ) ) {
		$path_index_size = 191;
	} else {
		$path_index_size = 255;
	}
	
	// create a table with 6 columns: an id, the bucket name, the file path, the optimization results, optimized image size, and original image size
	$sql = "CREATE TABLE $wpdb->s3io_images (
		id int(14) NOT NULL AUTO_INCREMENT,
		bucket VARCHAR(100),
		path text NOT NULL,
		results VARCHAR(55) NOT NULL,
		image_size int(10) unsigned,
		orig_size int(10) unsigned,
		UNIQUE KEY id (id),
		KEY path_image_size (path($path_index_size),image_size)
	) $charset_collate;";

	// include the upgrade library to initialize a table
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	// no need to autoload this option (even if it is small) since we only use it on manual activation	
	add_option( 's3io_license_status', '', 'no' );
	add_option( 's3io_optimize_urls', '', 'no' );
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

function s3io_addv4_args( $args ) {
	$args['signature'] = 'v4';
	$args['region'] = 'us-east-1';
	return $args;
}

function s3io_eucentral_args( $args ) {
	if ( get_option( 's3io_eucentral' ) ) {
		$args['signature'] = 'v4';
		$args['region'] = 'eu-central-1';
	}
	return $args;
}

function s3io_missing_aws_plugin() {
	echo "<div id='s3io-error-aws' class='error'><p>" . esc_html__( 'Could not detect the Amazon Web Services plugin, please install and configure it first.', 's3-image-optimizer' ) . "</p></div>";
}

function s3io_missing_ewww_plugin() {
	echo "<div id='s3io-error-ewww' class='error'><p>" . esc_html__( 'Could not detect the EWWW Image Optimizer plugin, please install and configure it first.', 's3-image-optimizer' ) . "</p></div>";
}

function s3io_admin_menu() {
	add_media_page( esc_html__( 'S3 Bulk Image Optimizer', 's3-image-optimizer' ), esc_html__( 'S3 Bulk Optimizer', 's3-image-optimizer' ), 'activate_plugins', 's3io-bulk-display', 's3io_bulk_display' );
	add_media_page( esc_html__( 'S3 Bulk URL Optimizer', 's3-image-optimizer' ), esc_html__( 'S3 URL Optimizer', 's3-image-optimizer' ), 'activate_plugins', 's3io-url-display', 's3io_url_display' );
	// add options page to the settings menu
	add_options_page(
		esc_html__( 'S3 Image Optimizer', 's3-image-optimizer' ),	//Title
		esc_html__( 'S3 Image Optimizer', 's3-image-optimizer' ),	//Sub-menu title
		'activate_plugins',						//Security
		S3IO_PLUGIN_FILE,						//File to open
		's3io_options_page'						//Function to call
	);
}

function s3io_options_page() {
	if ( class_exists( 'Amazon_Web_Services' ) ) {
		global $amazon_web_services;
		$aws = $amazon_web_services->get_client();
		$client = $aws->get( 'S3' );
		$buckets = $client->listBuckets();
		$license_status = get_option( 's3io_license_status' );
//	if ( get_option( 's3io_eucentral' ) ) {
?>
		<div class='wrap'>
			<h1><?php esc_html_e( 'S3 Image Optimizer', 's3-image-optimizer' ); ?></h1>
			<form method='post' action='options.php'>
<?php				settings_fields( 's3io_options' ); ?>
				<table class='form-table'>
					<tr><th><label for='s3io_license_key'><?php esc_html_e( 'License Key', 's3-image-optimizer' ) ?></label></th><td><input type="text" id="s3io_license_key" name="s3io_license_key" value="<?php echo get_option( 's3io_license_key' ) ?>" size="32" /> <?php esc_html_e( 'Enter your license key to activate automatic update checking', 's3-image-optimizer' ) ?></td></tr>
<?php		if ( false !== get_option( 's3io_license_key' ) ) { ?>
					<tr valign="top"><th scope="row" valign="top">
						<?php esc_html_e( 'Activate License', 's3-image-optimizer' ); ?>
					</th>
					<td>
<?php 			if ( $license_status !== false && $license_status == 'valid' ) { ?>
						<span style="color:green;"><?php esc_html_e( 'active', 's3-image-optimizer' ); ?></span>
<?php			} else {
				wp_nonce_field( 's3io_activation_nonce', 's3io_activation_nonce' ) ?>
						<input type="submit" class="button-secondary" name="s3io_license_activate" value="<?php esc_attr_e( 'Activate License', 's3-image-optimizer' ); ?>"/>
<?php			} ?>
					</td></tr>
<?php		} ?>
					<tr><th><label for='s3io_bucketlist'><?php esc_html_e( 'Buckets to optimize', 's3-image-optimizer' ) ?></label></th><td><?php esc_html_e( 'One bucket per line, must match one of the buckets listed below. If empty, all available buckets will be optimized.', 's3-image-optimizer' ) ?><br>
					<textarea id='s3io_bucketlist' name='s3io_bucketlist' rows='3' cols='40'>
<?php 						$bucket_list = get_option( 's3io_bucketlist' );
						if ( ! empty( $bucket_list ) ) {
							foreach ( $bucket_list as $bucket ) {
								echo "$bucket\n";
							}
						}
					?></textarea>
					<p class='description'>
					<?php if ( defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) && S3_IMAGE_OPTIMIZER_BUCKET ) {
						esc_html_e( 'You have currently defined the bucket constant (S3_IMAGE_OPTIMIZER_BUCKET) which will override any buckets entered above:', 's3-image-optimizer' );
						echo ' ' . esc_html( S3_IMAGE_OPTIMIZER_BUCKET ) . '<br><br>';
					}
					esc_html_e( 'These are the buckets that we have access to optimize:', 's3-image-optimizer' ) ?><br>
<?php					foreach ( $buckets['Buckets'] as $bucket ) {
						echo "{$bucket['Name']}<br>\n";
		//echo "<br>";
		//$location = $client->getBucketLocation( array(
		//	'Bucket' => $bucket['Name'],
		//) );
		//print_r( $location );
		//echo "<br>";
					}?>
					</p>
					</td></tr>
					<tr><th><?php esc_html_e( 'Sub-folders', 's3-image-optimizer' ) ?></th>
					<td><?php esc_html_e( 'You may set the S3_IMAGE_OPTIMIZER_FOLDER constant to restrict optimization to a specific sub-directory of the bucket(s) above.', 's3-image-optimizer' ); ?></td></tr>
					<tr><th><label for='s3io_eucentral'><?php esc_html_e( 'S3 Frankfurt', 's3-image-optimizer' ); ?></label></th><td><span><input type='checkbox' id='s3io_eucentral' name='s3io_eucentral' value='true' <?php if ( get_option( 's3io_eucentral' ) == TRUE ) { echo "checked='true' />"; } else { echo "/>"; } esc_html_e( 'DEPRECATED: Enable this only if you encounter a fatal error, and please let us know at https://ewww.io/contact-us/. Use this option if your buckets are hosted in the Frankfurt S3 region.', 's3-image-optimizer' ); ?></span>
					</td></tr>
				</table>
				<p class='submit'><input type='submit' class='button-primary' value='<?php esc_attr_e( 'Save Changes', 's3-image-optimizer' ) ?>' /></p>
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
	echo "<div id='s3io-error-mkdir' class='error'><p>" . esc_html__( 'Could not create the s3io folder within the WordPress uploads folder, please adjust the permissions and try again.', 's3-image-optimizer' ) . "</p></div>";
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
	// check to see if the user has asked to reset (empty) the optimized images table
	if ( ! empty( $_REQUEST['s3io_force_empty'] ) && wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk-empty' ) ) {
		s3io_table_truncate();
	}
	// check to see if we are supposed to reset the bulk operation and verify we are authorized to do so
	if ( ! empty( $_REQUEST['s3io_reset_bulk'] ) && wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk-reset' ) ) {
		update_option( 's3io_resume', '' );
	}
	// check the 'bulk resume' option
	$resume = get_option( 's3io_resume' );
	
	if ( empty( $resume ) ) {
		s3io_table_delete_pending();
		s3io_image_scan();
	}
	if ( 'media_page_s3io_bulk-display' != $hook ) {
		// submit a couple variables to the javascript to work with
		wp_enqueue_script( 's3iobulkscript', plugins_url( '/s3io.js', __FILE__ ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar', 'postbox', 'dashboard' ) );
		$image_count = s3io_table_count_optimized();
		wp_localize_script( 's3iobulkscript', 's3io_vars', array(
			'_wpnonce' => wp_create_nonce( 's3io-bulk' ),
			'attachments' => s3io_table_count_pending(), // number of images to do
			'image_count' => $image_count, // number of images completed
			'count_string' => sprintf( esc_html__( '%d images', 's3-image-optimizer' ), $image_count ),
//			'scan_fail' => __( 'Operation timed out, you may need to increase the max_execution_time for PHP', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
//			'license_exceeded' => esc_html__( 'License Exceeded', 's3-image-optimizer' ),
			'operation_stopped' => esc_html__( 'Optimization stopped, reload page to resume.', 's3-image-optimizer' ),
			'operation_interrupted' => esc_html__( 'Operation Interrupted', 's3-image-optimizer' ),
			'temporary_failure' => esc_html__( 'Temporary failure, seconds left to retry:', 's3-image-optimizer' ),
			'remove_failed' => esc_html__( 'Could not remove image from table.', 's3-image-optimizer' ),
			'optimized' => esc_html__( 'Optimized', 's3-image-optimizer' ),
		) );
		wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( 'jquery-ui-1.10.1.custom.css', __FILE__) );
	} else {
		return;
	}
}

// prepares the bulk operation and includes the javascript functions
function s3io_url_script( $hook ) {
	// make sure we are being called from the proper page
	if ( 'media_page_s3io-url-display' != $hook ) {
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
	$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
	// submit a couple variables to the javascript to work with
	wp_enqueue_script( 's3iobulkscript', plugins_url( '/s3io.js', __FILE__ ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar', 'postbox', 'dashboard' ) );
	wp_localize_script( 's3iobulkscript', 's3io_vars', array(
		'_wpnonce' => wp_create_nonce( 's3io-url' ),
		'operation_stopped' => esc_html__( 'Optimization stopped, reload page to optimize more images by url.', 's3-image-optimizer' ),
		'operation_interrupted' => esc_html__( 'Operation Interrupted', 's3-image-optimizer' ),
		'temporary_failure' => esc_html__( 'Temporary failure, seconds left to retry:', 's3-image-optimizer' ),
		'optimized' => esc_html__( 'Optimized', 's3-image-optimizer' ),
		'finished' => esc_html__( 'Finished', 's3-image-optimizer' ),
		'optimizing' => esc_html__( 'Optimizing', 's3-image-optimizer' ),
		'spinner' => "<img src='$loading_image' alt='loading'/>",
	) );
	wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( 'jquery-ui-1.10.1.custom.css', __FILE__) );
}

// scan buckets for images and store in database
function s3io_image_scan( $verbose = false ) {
	global $wpdb;
	$images = array();
	$image_count = 0;
//	$start = microtime( true );
	if ( defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) && S3_IMAGE_OPTIMIZER_BUCKET ) {
		$bucket_list = array( S3_IMAGE_OPTIMIZER_BUCKET );
	} else {
		$bucket_list = get_option( 's3io_bucketlist' );
	}
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
		$location = $client->getBucketLocation( array(
			'Bucket' => $bucket,
		) );
		if ( ! empty( $location['Location'] ) ) {
			$client->setRegion( $location['Location'] );
		} else {
			$client->setRegion( 'us-east-1' );
		}
		$iterator_args = array(	'Bucket' => $bucket );
		if ( defined( 'S3_IMAGE_OPTIMIZER_FOLDER' ) && S3_IMAGE_OPTIMIZER_FOLDER ) {
			$iterator_args['Prefix'] = ltrim( S3_IMAGE_OPTIMIZER_FOLDER, '/' );
		}

		// in case you need to modify the arguments to the $client->getIterator() call before they are used
		$iterator_args = apply_filters( 's3io_scan_iterator_args', $iterator_args );
			
		$iterator = $client->getIterator( 'ListObjects', $iterator_args );
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
				$images[] = "('$bucket','$path',$image_size)";
				if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
					WP_CLI::line( sprintf( __( 'Queueing %1$s in %2$s.', 's3-image-optimizer' ), $path, $bucket ) );
				}
				$image_count++;
			}
			if ( $image_count > 5000 ) {
				// let's dump what we have so far to the db
				$image_count = 0;
				$insert_query = "INSERT INTO $wpdb->s3io_images (bucket,path,orig_size) VALUES" . implode( ',', $images );
				$wpdb->query( $insert_query );
				if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
					WP_CLI::line( __( 'Saved queue to database.', 's3-image-optimizer' ) );
				}
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

function s3io_url_display() {
	global $wpdb;
	// generate the WP spinner image for display
	$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
	?>
	<div class="wrap">
	<h1><?php esc_html_e( 'S3 URL Optimizer', 's3-image-optimizer' ); ?></h1>
		<div id="s3io-bulk-loading">
			<p id="s3io-loading" class="s3io-bulk-info" style="display:none">&nbsp;<img src="<?php echo $loading_image; ?>" /></p>
		</div>
		<div id="s3io-bulk-progressbar"></div>
		<div id="s3io-bulk-counter"></div>
		<form id="s3io-bulk-stop" style="display:none;" method="post" action="">
			<br /><input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Stop Optimizing', 's3-image-optimizer' ); ?>" />
		</form>
		<div id="s3io-bulk-widgets" class="metabox-holder" style="display:none">
			<div class="meta-box-sortables">
				<div id="s3io-bulk-status" class="postbox">
					<button type="button" class="handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 's3-image-optimizer' ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
					<h2 class="hndle"><span><?php esc_html_e( 'Optimization Log', 's3-image-optimizer' ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
		</div>
		<form class="s3io-bulk-form">
			<p><label for="s3io-delay" style="font-weight: bold"><?php esc_html_e( 'Choose how long to pause between images (in seconds, 0 = disabled)', 's3-image-optimizer' ); ?></label>&emsp;<input type="text" id="s3io-delay" name="s3io-delay" value="<?php if ( $delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) ) { echo (int) $delay; } else { echo 0; } ?>"></p>
			<div id="s3io-delay-slider" style="width:50%"></div>
		</form>
		<div id="s3io-bulk-forms"><p class="s3io-bulk-info">
			<p class="s3io-media-info s3io-bulk-info"><?php esc_html_e( 'Previously optimized images will not be skipped.', 's3-image-optimizer' ); ?></p>
			<form id="s3io-url-start" class="s3io-bulk-form" method="post" action="">
				<p><label><?php esc_html_e( 'List images to be processed by URL (1 per line), for example:', 's3-image-optimizer' ); ?> https://ewww-sample.s3.amazonaws.com/test-bucket/uploads/2020/08/test-image.jpg<br><textarea id="s3io-url-image-queue" name="s3io-url-image-queue" style="resize:both; height: 300px; width: 60%;"></textarea></label></p>
				<input id="s3io-first" type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Start optimizing', 's3-image-optimizer' ); ?>" />
			</form>
		</div>
	</div>
<?php
}

function s3io_bulk_display() {
	global $wpdb;
	// Retrieve the value of the 'aux resume' option and set the button text for the form to use
	$s3io_resume = get_option( 's3io_resume' );
	if ( empty( $s3io_resume ) ) {
		$button_text = esc_attr__( 'Start optimizing', 's3-image-optimizer' );
	} else {
		$button_text = esc_attr__( 'Resume previous optimization', 's3-image-optimizer' );
	}
	$image_count = s3io_table_count_pending();
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
	<h1><?php esc_html_e( 'S3 Bulk Optimizer', 's3-image-optimizer' ); ?></h1>
		<div id="s3io-bulk-loading">
			<p id="s3io-loading" class="s3io-bulk-info" style="display:none">&nbsp;<img src="<?php echo $loading_image; ?>" /></p>
		</div>
		<div id="s3io-bulk-progressbar"></div>
		<div id="s3io-bulk-counter"></div>
		<form id="s3io-bulk-stop" style="display:none;" method="post" action="">
			<br /><input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Stop Optimizing', 's3-image-optimizer' ); ?>" />
		</form>
		<div id="s3io-bulk-widgets" class="metabox-holder" style="display:none">
			<div class="meta-box-sortables">
				<div id="s3io-bulk-last" class="postbox">
					<button type="button" class="handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 's3-image-optimizer' ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
					<h2 class="hndle"><span><?php esc_html_e( 'Last Image Optimized', 's3-image-optimizer' ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
			<div class="meta-box-sortables">
				<div id="s3io-bulk-status" class="postbox">
					<button type="button" class="handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 's3-image-optimizer' ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
					<h2 class="hndle"><span><?php esc_html_e( 'Optimization Log', 's3-image-optimizer' ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
		</div>
<?php		if ( empty( $image_count ) ) {
			echo '<p>' . esc_html__( 'There is nothing left to optimize.', 's3-image-optimizer' ) . '</p>';
		} else { ?>
		<form class="s3io-bulk-form">
			<p><label for="s3io-delay" style="font-weight: bold"><?php esc_html_e( 'Choose how long to pause between images (in seconds, 0 = disabled)', 's3-image-optimizer' ); ?></label>&emsp;<input type="text" id="s3io-delay" name="s3io-delay" value="<?php if ( $delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) ) { echo (int) $delay; } else { echo 0; } ?>"></p>
			<div id="s3io-delay-slider" style="width:50%"></div>
		</form>
		<div id="s3io-bulk-forms"><p class="s3io-bulk-info">
			<p class="s3io-media-info s3io-bulk-info"><?php printf( esc_html__( 'There are %1$d images to be optimized.', 's3-image-optimizer' ), $image_count ); ?><br />
			<?php esc_html_e( 'Previously optimized images will be skipped by default.', 's3-image-optimizer' ); ?></p>
		<?php if ( ! empty( $last_run ) ) { ?>
			<p id="s3io-last-run" class="s3io-bulk-info"><?php printf( esc_html__( 'Last optimization was completed on %1$s at %2$s and optimized %3$d images', 's3-image-optimizer' ), date( get_option( 'date_format' ), $last_run[0] ), date( get_option( 'time_format' ), $last_run[0] ), (int) $last_run[1] ); ?></p>
		<?php } ?>
			<form id="s3io-start" class="s3io-bulk-form" method="post" action="">
				<input id="s3io-first" type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
				<input id="s3io-again" type="submit" class="button-secondary action" style="display:none" value="<?php esc_attr_e( 'Optimize Again', 's3-image-optimizer' ); ?>" />
			</form>
<?php		} 
		if ( ! empty( $s3io_resume ) ) {
?>			<p class="s3io-bulk-info"><?php esc_html_e( 'If you would like to start over again, press the Reset Status button to reset the bulk operation status.', 's3-image-optimizer' ); ?></p>
			<form id="s3io-bulk-reset" class="s3io-bulk-form" method="post" action="">
				<?php wp_nonce_field( 's3io-bulk-reset', 's3io_wpnonce' ); ?>
				<input type="hidden" name="s3io_reset_bulk" value="1">
				<button type="submit" class="button-secondary action"><?php esc_html_e( 'Reset Status', 's3-image-optimizer' ); ?></button>
			</form>
<?php		} 
		if ( empty( $already_optimized ) ) {
			$display = ' style="display:none"';
		} else {
			$display = '';
?>			<p class="s3io-bulk-info" style="margin-top: 2.5em"><?php esc_html_e( 'Force a re-optimization of all images by erasing the optimization history. This cannot be undone, as it will remove all optimization records from the database.', 's3-image-optimizer' ); ?></p>
			<form id="s3io-force-empty" class="s3io-bulk-form" style="margin-bottom: 2.5em" method="post" action="">
				<?php wp_nonce_field( 's3io-bulk-empty', 's3io_wpnonce' ); ?>
				<input type="hidden" name="s3io_force_empty" value="1">
				<button type="submit" class="button-secondary action"><?php esc_html_e( 'Erase Optimization History', 's3-image-optimizer' ); ?></button>
			</form>
<?php		}
?>			<p id="s3io-table-info" class="s3io-bulk-info"<?php echo "$display>"; printf( esc_html__( 'The optimizer keeps track of already optimized images to prevent re-optimization. There are %d images that have been optimized so far.', 's3-image-optimizer' ), $already_optimized ); ?></p>
			<form id="s3io-show-table" class="s3io-bulk-form" method="post" action=""<?php echo $display; ?>>
				<button type="submit" class="button-secondary action"><?php esc_html_e( 'Show Optimized Images', 's3-image-optimizer' ); ?></button>
			</form>
			<div class="tablenav s3io-aux-table" style="display:none">
			<div class="tablenav-pages s3io-table">
			<span class="displaying-num s3io-table"></span>
			<span id="paginator" class="pagination-links s3io-table">
				<a id="first-images" class="first-page" style="display:none">&laquo;</a>
				<a id="prev-images" class="prev-page" style="display:none">&lsaquo;</a>
				<?php esc_html_e( 'page', 's3-image-optimizer' ); ?> <span class="current-page"></span> <?php esc_html_e( 'of', 's3-image-optimizer' ); ?> 
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
		wp_die( esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) );
	} 
	global $wpdb;
	$offset = 50 * $_POST['s3io_offset'];
	$query = "SELECT id,bucket,path,results,image_size FROM $wpdb->s3io_images WHERE image_size IS NOT NULL ORDER BY id DESC LIMIT $offset,50";
	$already_optimized = $wpdb->get_results( $query, ARRAY_A );
	echo '<br /><table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>' . esc_html__( 'Bucket', 's3-image-optimizer' ) . '</th><th>' . esc_html__( 'Filename', 's3-image-optimizer' ) . '</th><th>' . esc_html__( 'Image Optimizer', 's3-image-optimizer' ) . '</th></tr></thead>';
	$alternate = true;
	foreach ( $already_optimized as $optimized_image ) {
		$file_size = size_format( $optimized_image['image_size'], 2 );
		$file_size = str_replace( '.00 B ', ' B', $file_size );
?>		<tr<?php if ( $alternate ) { echo " class='alternate'"; } ?> id="s3io-image-<?php echo $optimized_image['id']; ?>">
			<td class='title'><?php echo esc_html( $optimized_image['bucket'] ); ?></td>
			<td class='title'>...<?php echo esc_html( $optimized_image['path'] ); ?></td>
			<td><?php echo esc_html( $optimized_image['results'] ) . ' <br>' . sprintf( esc_html__( 'Image Size: %s', 's3-image-optimizer' ), $file_size ); ?><br><a class="removeimage" onclick="s3ioRemoveImage( <?php echo (int) $optimized_image['id']; ?> )"><?php esc_html_e( 'Remove from table', 's3-image-optimizer' ); ?></a></td>
		</tr>
<?php		$alternate = ! $alternate;
	}
	echo '</table>';
	die();
}

// removes an image from the auxiliary images table
function s3io_table_remove() {
	// verify that an authorized user has called function
	if ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) ) {
		wp_die( esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) );
	} 
	global $wpdb;
	if ( $wpdb->delete( $wpdb->s3io_images, array( 'id' => $_POST['s3io_image_id'] ) ) ) {
		echo "1";
	}
	die();
}

// receives a path, results, optimized size, and an original size to insert into ewwwwio_images table
// if this is a $new image, copy the result stored in the database
function s3io_table_update( $path, $opt_size, $orig_size, $results_msg, $id = false, $bucket = '' ) {
	global $wpdb;
	$opt_size = (int) $opt_size;
	$orig_size = (int) $orig_size;
	$id = $id ? (int) $id : (bool) $id;
	if ( $opt_size >= $orig_size ) {
		$results_msg = __( 'No savings', 's3-image-optimizer' );
		ewwwio_debug_message( 's3io: no savings' );
		if ( $id ) {
			ewwwio_debug_message( "s3io: looking for $id" );
			$optimized_query = $wpdb->get_row("SELECT id,orig_size,results,path FROM $wpdb->s3io_images WHERE id = $id", ARRAY_A);
			if ( $optimized_query && ! empty( $optimized_query['results'] ) ) {
				ewwwio_debug_message( "s3io: found already optimized $id" );
				return $optimized_query['results'];
			}
			// otherwise we need to store some stuff
			// store info on the current image for future reference
			$updated = $wpdb->update( $wpdb->s3io_images,
				array(
					'image_size' => $opt_size,
					'results' => $results_msg,
				),
				array(
					'id' => $id,
				),
				array(
					'%d',
					'%s',
				),
				array(
					'%d',
				)
			);
			if ( $updated ) {
				ewwwio_debug_message( "s3io: updated $id" );
				$wpdb->flush();
				return $results_msg;
			}
		}
	} else {
		// calculate how much space was saved
		$savings = $orig_size - $opt_size;
		// convert it to human readable format
		$savings_str = size_format( $savings, 1 );
		// replace spaces and extra decimals with proper html entity encoding
		$savings_str = str_replace( '.0 B ', ' B', $savings_str );
		$savings_str = str_replace( ' ', '&nbsp;', $savings_str );
		// determine the percentage savings
		$percent = 100 - ( 100 * ( $opt_size / $orig_size ) );
		// use the percentage and the savings size to output a nice message to the user
		$results_msg = sprintf( __( "Reduced by %01.1f%% (%s)", 's3-image-optimizer' ),
			$percent,
			$savings_str
		);
		if ( $id ) {
			ewwwio_debug_message( "s3io: updating $id" );
			// store info on the current image for future reference
			$updated = $wpdb->update( $wpdb->s3io_images,
				array(
					'image_size' => $opt_size,
					'results' => $results_msg,
				),
				array(
					'id' => $id,
				),
				array(
					'%d',
					'%s',
				),
				array(
					'%d',
				)
			);
			if ( $updated ) {
				ewwwio_debug_message( "s3io: updated $id" );
				$wpdb->flush();
				return $results_msg;
			}
		}
	}
	ewwwio_debug_message( "s3io: falling back to search by $path" );
	$query = $wpdb->prepare("SELECT id,orig_size,results,path FROM $wpdb->s3io_images WHERE path = %s", $path);
	$optimized_query = $wpdb->get_results( $query, ARRAY_A );
	if ( ! empty( $optimized_query ) ) {
		ewwwio_debug_message( 's3io: found results by path, checking...' );
		foreach ( $optimized_query as $image ) {
			ewwwio_debug_message( $image['path'] );
			if ( $image['path'] == $path ) {
				ewwwio_debug_message( 'found a match' );
				$already_optimized = $image;
			}
		}
	}
	if ( ! empty( $already_optimized['results'] ) && $opt_size === $orig_size ) {
		ewwwio_debug_message( 'returning results without update' );
		return $already_optimized['results'];
	}
	// store info on the current image for future reference
	$updated = $wpdb->update( $wpdb->s3io_images,
		array(
			'image_size' => $opt_size,
			'results' => $results_msg,
		),
		array(
			'id' => $already_optimized['id'],
		),
		array(
			'%d',
			'%s',
		),
		array(
			'%d',
		)
	);
	if ( $updated ) {
		ewwwio_debug_message( "updated results for $path" );
		$wpdb->flush();
		return $results_msg;
	}
	ewwwio_debug_message( 'no existing records found, inserting new one' );
	$inserted = $wpdb->insert( $wpdb->s3io_images,
		array(
			'bucket' => $bucket,
			'path' => $path,
			'results' => $results_msg,
			'image_size' => $opt_size,
			'orig_size' => $orig_size,
		),
		array(
			'%s',
			'%s',
			'%s',
			'%d',
			'%d',
		)
	);
	if ( $inserted ) {
		ewwwio_debug_message( 'successful INSERT' );
	}
	$wpdb->flush();
	return $results_msg;
}

// called by javascript to initialize some output
function s3io_bulk_init( ) {
	$output = array();
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) || ! current_user_can( $permissions ) ) {
		$output['error'] = esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' );
		echo json_encode( $output );
		die();
	}
	session_write_close();
	// store the time and number of images for later display
	update_option( 's3io_last_run', array( time(), s3io_table_count_pending() ) );
	update_option( 's3io_resume', true );
	// let the user know that we are beginning
	// generate the WP spinner image for display
	$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
	global $wpdb;
	$image_record = $wpdb->get_row( "SELECT path FROM $wpdb->s3io_images WHERE image_size IS NULL LIMIT 1", ARRAY_A );
	// let the user know that we are beginning
	$output['results'] = "<p>" . esc_html__( 'Optimizing', 's3-image-optimizer' ) . " <b>" . esc_html( $image_record['path'] ) . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
	echo json_encode( $output );
	die();
}

// called by javascript to process each image in the loop
function s3io_bulk_loop( $auto = false, $verbose = false ) {
	$output = array();
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) || ! current_user_can( $permissions ) ) ) {
		$output['error'] = esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' );
		echo json_encode( $output );
		die();
	}
	// find out if our nonce is on it's last leg/tick
	$tick = wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' );
	if ( $tick == 2 ) {
		$output['new_nonce'] = wp_create_nonce( 's3io-bulk' );
	} else {
		$output['new_nonce'] = '';
	}
	// retrieve the time when the optimizer starts
	$started = microtime( true );
	if ( ewww_image_optimizer_stl_check() && ini_get( 'max_execution_time' ) < 60 ) {
		set_time_limit ( 0 );
	}
	global $wpdb;
	$image_record = $wpdb->get_row( "SELECT id,bucket,path,orig_size FROM $wpdb->s3io_images WHERE image_size IS NULL LIMIT 1", ARRAY_A );
	$upload_dir = wp_upload_dir();
	$upload_dir = trailingslashit( $upload_dir['basedir'] ) . 's3io/' . sanitize_file_name( $image_record['bucket'] ) . '/';
	global $amazon_web_services;
	$aws = $amazon_web_services->get_client();
	$client = $aws->get( 'S3' );
	$location = $client->getBucketLocation( array(
		'Bucket' => $image_record['bucket'],
	) );
	if ( ! empty( $location['Location'] ) ) {
		$client->setRegion( $location['Location'] );
	}
	$filename = $upload_dir . $image_record['path'];
	$full_dir = dirname( $filename );
	if ( ! is_dir( $full_dir ) ) {
		mkdir( $full_dir, 0777, true );
	}
	$fetch_result = $client->getObject( array(
		'Bucket' => $image_record['bucket'], 
		'Key' => $image_record['path'],
		'SaveAs' => $filename,
	) );
	// make sure EWWW I.O. doesn't do anything weird like skipping images or generating webp
	$_REQUEST['ewww_force'] = true;
	$webp = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', false );
	if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::line( "About to optimize $filename" );
	}
	// do the optimization for the current image
	$results = ewww_image_optimizer( $filename );
	if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::line( "Just optimized $filename, stay tuned..." );
	}
	unset( $_REQUEST['ewww_force'] );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', $webp );
//	global $ewww_exceed;
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty ( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		unlink( $filename );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::error( __( 'License Exceeded', 's3-image-optimizer' ) );
		} else {
			$output['error'] = esc_html__( 'License Exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			echo json_encode( $output );
		}
		die();
	}
	$new_size = filesize( $filename );
	if ( $new_size < $fetch_result['ContentLength'] ) {
		if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( "About to re-upload $filename" );
		}
		// re-upload to S3
		$client->putObject( array(
			'Bucket' => $image_record['bucket'],
			'Key' => $image_record['path'],
			'SourceFile' => $filename,
			'ACL' => 'public-read',
			'ContentType' => $fetch_result['ContentType'],
			'CacheControl' => 'max-age=31536000',
			'Expires' => date( 'D, d M Y H:i:s O', time() + 31536000 ),
		) );
		if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( "Finished re-upload of $filename" );
		}
	}
	unlink( $filename );
	s3io_table_update( $image_record['path'], $new_size, $fetch_result['ContentLength'], $results[1], $image_record['id'] );
	// make sure ewww doesn't keep a record of these files
	$query = $wpdb->prepare( "DELETE FROM $wpdb->ewwwio_images WHERE path = %s", $filename );
	$wpdb->query( $query );
	if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::line( "Updated database records." );
	}
	ewww_image_optimizer_debug_log();
	if ( ! $auto ) {
		// output the path
		$output['results'] = sprintf( "<p>" . esc_html__( 'Optimized image:', 's3-image-optimizer' ) . " <strong>%s</strong><br>", esc_html( $image_record['path'] ) );
		// tell the user what the results were for the original image
		$output['results'] .=  esc_html( $results[1] ) . '<br>';
		// calculate how much time has elapsed since we started
		$elapsed = microtime( true ) - $started;
		// output how much time has elapsed since we started
		$output['results'] .= sprintf( esc_html__( 'Elapsed: %.3f seconds', 's3-image-optimizer' ) . "</p>", $elapsed);

		// lookup the next image to optimize
		$image_record = $wpdb->get_row( "SELECT path FROM $wpdb->s3io_images WHERE image_size IS NULL LIMIT 1", ARRAY_A );
		if ( ! empty( $image_record ) ) {
			$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
			$output['next_file'] = "<p>" . esc_html__( 'Optimizing', 's3-image-optimizer' ) . " <b>" . esc_html( $image_record['path'] ) . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
		}
		echo json_encode( $output );
		die();
	} elseif ( defined( 'WP_CLI' ) && WP_CLI ) {
		// calculate how much time has elapsed since we started
		$elapsed = microtime( true ) - $started;
		WP_CLI::line( __( 'Optimized image:', 's3-image-optimizer' ) . " " . $image_record['path'] );
		WP_CLI::line( $results[1] );
		WP_CLI::line( sprintf( __( 'Elapsed: %.3f seconds', 's3-image-optimizer' ) . "</p>", $elapsed) );
	}
}

// called by javascript to cleanup after ourselves
function s3io_bulk_cleanup( $auto = false ) {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-bulk' ) || ! current_user_can( $permissions ) ) ) {
		wp_die( esc_html__( 'Access denied.', 's3-image-optimizer' ) );
	}
	$stored_last = get_option( 's3io_last_run' );
	update_option( 's3io_last_run', array( time(), $stored_last[1] ) );
	if ( ! $auto ) {
		// and let the user know we are done
		echo '<p><b>' . esc_html__( 'Finished', 's3-image-optimizer' ) . '</b></p>';
		die();
	}
}

function s3io_url_loop() {
	$output = array();
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['s3io_wpnonce'], 's3io-url' ) || ! current_user_can( $permissions ) ) {
		$output['error'] = esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' );
		echo json_encode( $output );
		die();
	}
	$started = microtime( true );
	if ( ewww_image_optimizer_stl_check() && ini_get( 'max_execution_time' ) < 60 ) {
		set_time_limit ( 0 );
	}
	if ( empty( $_REQUEST['s3io_url'] ) ) {
		$output['error'] = esc_html__( 'No URL supplied', 's3io-image-optimizer' );
		echo json_encode( $output );
		die();
	}
	$url = filter_var( $_REQUEST['s3io_url'], FILTER_SANITIZE_URL);
	$url = filter_var( $url, FILTER_VALIDATE_URL, array( FILTER_FLAG_HOST_REQUIRED, FILTER_FLAG_PATH_REQUIRED ) );
	if ( ! empty( $url ) ) {
		$url_args = s3io_get_args_from_url( $_REQUEST['s3io_url'] );
	}
	if ( empty( $url ) || empty( $url_args ) ) {
		$output['error'] = esc_html__( 'Invalid URL supplied', 's3io-image-optimizer' );
		echo json_encode( $output );
		die();
	}
	$url_args['path'] = ltrim( $url_args['path'], '/' );
	$upload_dir = wp_upload_dir();
	$upload_dir = trailingslashit( $upload_dir['basedir'] ) . 's3io/' . sanitize_file_name( $image_record['bucket'] ) . '/';
	global $amazon_web_services;
	$aws = $amazon_web_services->get_client();
	$client = $aws->get( 'S3' );
	$location = $client->getBucketLocation( array(
		'Bucket' => $url_args['bucket'],
	) );
	if ( ! empty( $location['Location'] ) ) {
		$client->setRegion( $location['Location'] );
	}
	$filename = $upload_dir . $url_args['path'];
	$full_dir = dirname( $filename );
	if ( ! is_dir( $full_dir ) ) {
		mkdir( $full_dir, 0777, true );
	}
	$fetch_result = $client->getObject( array(
		'Bucket' => $url_args['bucket'], 
		'Key' => $url_args['path'],
		'SaveAs' => $filename,
	) );
	// make sure EWWW I.O. doesn't do anything weird like skipping images or generating webp
	$_REQUEST['ewww_force'] = true;
	$webp = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', false );
	// do the optimization for the current image
	$results = ewww_image_optimizer( $filename );
	unset( $_REQUEST['ewww_force'] );
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', $webp );
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty ( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		unlink( $filename );
		$output['error'] = esc_html__( 'License Exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		echo json_encode( $output );
		die();
	}
	$new_size = filesize( $filename );
	if ( $new_size < $fetch_result['ContentLength'] ) {
		// re-upload to S3
		$client->putObject( array(
			'Bucket' => $url_args['bucket'],
			'Key' => $url_args['path'],
			'SourceFile' => $filename,
			'ACL' => 'public-read',
			'ContentType' => $fetch_result['ContentType'],
			'CacheControl' => 'max-age=31536000',
			'Expires' => date( 'D, d M Y H:i:s O', time() + 31536000 ),
		) );
	}
	unlink( $filename );
	s3io_table_update( $url_args['path'], $new_size, $fetch_result['ContentLength'], $results[1], false, $url_args['bucket'] );
	// make sure ewww doesn't keep a record of these files
	global $wpdb;
	$query = $wpdb->prepare( "DELETE FROM $wpdb->ewwwio_images WHERE path = %s", $filename );
	$wpdb->query( $query );
	ewww_image_optimizer_debug_log();
	// output the path
	$output['results'] = sprintf( "<p>" . esc_html__( 'Optimized image:', 's3-image-optimizer' ) . " <strong>%s</strong><br>", esc_html( $url_args['path'] ) );
	// tell the user what the results were for the original image
	$output['results'] .=  esc_html( $results[1] ) . '<br>';
	// calculate how much time has elapsed since we started
	$elapsed = microtime( true ) - $started;
	// output how much time has elapsed since we started
	$output['results'] .= sprintf( esc_html__( 'Elapsed: %.3f seconds', 's3-image-optimizer' ) . "</p>", $elapsed);
	
	echo json_encode( $output );
	die();
}

function s3io_get_args_from_url( $url ) {
	$urlinfo = parse_url( $url );
	if ( ! $urlinfo ) {
		return false;
	}
	if ( defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) && S3_IMAGE_OPTIMIZER_BUCKET ) {
		if ( strpos( $urlinfo['host'], S3_IMAGE_OPTIMIZER_BUCKET ) !== false ) {
			return array( 'bucket' => S3_IMAGE_OPTIMIZER_BUCKET, 'path' => $urlinfo['path'] );
		}
		if ( strpos( $urlinfo['path'], S3_IMAGE_OPTIMIZER_BUCKET ) !== false ) {
			$path = str_replace( '/' . S3_IMAGE_OPTIMIZER_BUCKET, '', $urlinfo['path'] );
			return array( 'bucket' => S3_IMAGE_OPTIMIZER_BUCKET, 'path' => $path );
		}
	}
	global $amazon_web_services;
	$aws = $amazon_web_services->get_client();
	$client = $aws->get( 'S3' );
	$buckets = $client->listBuckets();
	foreach ( $buckets['Buckets'] as $aws_bucket ) {
		if ( strpos( $urlinfo['host'], $aws_bucket['Name'] ) !== false ) {
			return array( 'bucket' => $aws_bucket['Name'], 'path' => $urlinfo['path'] );
		}
		if ( strpos( $urlinfo['path'], $aws_bucket['Name'] ) !== false ) {
			$path = str_replace( '/' . $aws_bucket['Name'], '', $urlinfo['path'] );
			return array( 'bucket' => $aws_bucket['Name'], 'path' => $path );
		}
	}
	// otherwise, we must have a custom domain, so lets do a quick search for the attachment in all buckets
	// doing it in a separate foreach, in case there are performance implications of switching the region in accounts with lots of buckets
	$key = ltrim( $urlinfo['path'], '/' );
	foreach ( $buckets['Buckets'] as $aws_bucket ) {
		$location = $client->getBucketLocation( array(
			'Bucket' => $aws_bucket['Name'],
		) );
		if ( ! empty( $location['Location'] ) ) {
			$client->setRegion( $location['Location'] );
		} else {
			$client->setRegion( 'us-east-1' );
		}
		try {
			$exists = $client->headObject( array( 'Bucket' => $aws_bucket['Name'], 'Key' => $key ) );
		} catch( Exception $e ) {
		}
		if ( $exists ) {
			return array( 'bucket' => $aws_bucket['Name'], 'path' => $urlinfo['path'] );
		}
	}
	return false;
}

add_action( 'admin_enqueue_scripts', 's3io_bulk_script' );
add_action( 'admin_enqueue_scripts', 's3io_url_script' );
add_action( 'wp_ajax_s3io_query_table', 's3io_table' );
add_action( 'wp_ajax_s3io_table_count', 's3io_table_count_optimized' );
add_action( 'wp_ajax_s3io_table_remove', 's3io_table_remove' );
add_action( 'wp_ajax_s3io_bulk_init', 's3io_bulk_init' );
add_action( 'wp_ajax_s3io_bulk_loop', 's3io_bulk_loop' );
add_action( 'wp_ajax_s3io_bulk_cleanup', 's3io_bulk_cleanup' );
add_action( 'wp_ajax_s3io_url_images_loop', 's3io_url_loop' );
