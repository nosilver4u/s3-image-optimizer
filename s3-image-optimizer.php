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
Version: 2.4.0
Author URI: https://ewww.io/
License: GPLv3
*/

/**
 * Constants
 */
define( 'S3IO_VERSION', '2.40' );
// This is the full path of the plugin file itself.
define( 'S3IO_PLUGIN_FILE', __FILE__ );
// This is the path of the plugin file relative to the plugins/ folder.
define( 'S3IO_PLUGIN_FILE_REL', 's3-image-optimizer/s3-image-optimizer.php' );

add_action( 'admin_init', 's3io_admin_init' );
add_action( 'admin_menu', 's3io_admin_menu', 60 );
add_filter( 'aws_get_client_args', 's3io_addv4_args', 8 );
add_filter( 'aws_get_client_args', 's3io_dospaces' );

require_once( plugin_dir_path( __FILE__ ) . 'vendor/Aws3/aws-autoloader.php' );
require_once( plugin_dir_path( __FILE__ ) . 'classes/class-amazon-web-services.php' );
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( plugin_dir_path( __FILE__ ) . 'classes/class-s3io-cli.php' );
}

global $wpdb;
if ( ! isset( $wpdb->s3io_images ) ) {
	$wpdb->s3io_images = $wpdb->prefix . 's3io_images';
}

/**
 * Register settings and perform any upgrades during admin_init hook.
 */
function s3io_admin_init() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	register_setting( 's3io_options', 's3io_verion' );
	register_setting( 's3io_options', 's3io_bucketlist', 's3io_bucketlist_sanitize' );
	register_setting( 's3io_options', 's3io_dospaces', 'trim' );
	register_setting( 's3io_options', 's3io_aws_access_key_id', 'trim' );
	register_setting( 's3io_options', 's3io_aws_secret_access_key', 'trim' );
	if ( get_option( 's3io_version' ) < S3IO_VERSION ) {
		s3io_install_table();
		update_option( 's3io_version', S3IO_VERSION );
	}
	$aws_settings = get_option( 'aws_settings' );
	if ( $aws_settings && is_array( $aws_settings ) ) {
		if ( ! get_option( 's3io_aws_access_key_id' ) && ! empty( $aws_settings['access_key_id'] ) ) {
			update_option( 's3io_aws_access_key_id', $aws_settings['access_key_id'] );
		}
		if ( ! get_option( 's3io_aws_secret_access_key' ) && ! empty( $aws_settings['secret_access_key'] ) ) {
			update_option( 's3io_aws_secret_access_key', $aws_settings['secret_access_key'] );
		}
	}
	global $wp_version;
	if ( substr( $wp_version, 0, 3 ) >= 3.8 ) {
		add_action( 'admin_enqueue_scripts', 's3io_progressbar_style' );
	}
	if ( ! function_exists( 'ewww_image_optimizer' ) ) {
		add_action( 'network_admin_notices', 's3io_missing_ewww_plugin' );
		add_action( 'admin_notices', 's3io_missing_ewww_plugin' );
	}
}

/**
 * Install the s3io_images table into the db for tracking image optimization.
 */
function s3io_install_table() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;

	// See if the path column exists, and what collation it uses to determine the column index size.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->s3io_images'" ) === $wpdb->s3io_images ) {
		$current_collate = $wpdb->get_results( "SHOW FULL COLUMNS FROM $wpdb->s3io_images", ARRAY_A );
		if ( ! empty( $current_collate[1]['Field'] ) && 'path' === $current_collate[1]['Field'] && strpos( $current_collate[1]['Collation'], 'utf8mb4' ) === false ) {
			$path_index_size = 255;
		}
	}
	$charset_collate = $wpdb->get_charset_collate();

	if ( empty( $path_index_size ) && strpos( $charset_collate, 'utf8mb4' ) ) {
		$path_index_size = 191;
	} else {
		$path_index_size = 255;
	}

	// Create a table with 6 columns: an id, the bucket name, the file path, the optimization results, optimized image size, and original image size.
	$sql = "CREATE TABLE $wpdb->s3io_images (
		id int(14) NOT NULL AUTO_INCREMENT,
		bucket VARCHAR(100),
		path text NOT NULL,
		results VARCHAR(75) NOT NULL,
		image_size int(10) unsigned,
		orig_size int(10) unsigned,
		UNIQUE KEY id (id),
		KEY path_image_size (path($path_index_size),image_size)
	) $charset_collate;";

	// Include the upgrade library to initialize a table.
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 's3io_optimize_urls', '', '', 'no' );
}

/**
 * Generates css include for progressbars to match admin style.
 */
function s3io_progressbar_style() {
	if ( function_exists( 'wp_add_inline_style' ) ) {
		wp_add_inline_style( 'jquery-ui-progressbar', '.ui-widget-header { background-color: ' . s3io_admin_background() . '; }' );
	}
}

/**
 * Determines the background color to use based on the selected admin theme.
 *
 * @return string The background color in hex notation.
 */
function s3io_admin_background() {
	global $s3io_admin_color;
	if ( ! empty( $s3io_admin_color ) && preg_match( '/^\#([0-9a-fA-F]){3,6}$/', $s3io_admin_color ) ) {
		return $s3io_admin_color;
	}
	if ( function_exists( 'wp_add_inline_style' ) ) {
		$user_info = wp_get_current_user();
		global $_wp_admin_css_colors;
		if (
			is_array( $_wp_admin_css_colors ) &&
			! empty( $user_info->admin_color ) &&
			isset( $_wp_admin_css_colors[ $user_info->admin_color ] ) &&
			is_object( $_wp_admin_css_colors[ $user_info->admin_color ] ) &&
			is_array( $_wp_admin_css_colors[ $user_info->admin_color ]->colors ) &&
			! empty( $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2] ) &&
			preg_match( '/^\#([0-9a-fA-F]){3,6}$/', $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2] )
		) {
			$s3io_admin_color = $_wp_admin_css_colors[ $user_info->admin_color ]->colors[2];
			return $s3io_admin_color;
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
 * Adjusts the signature/version and region from the defaults.
 *
 * @param array $args A list of arguments sent to the AWS SDK.
 * @return array The arguments for the AWS connection, possibly modified.
 */
function s3io_addv4_args( $args ) {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$args['signature'] = 'v4';
	$args['region']    = 'us-east-1';
	$args['version']   = '2006-03-01';
	if ( defined( 'S3_IMAGE_OPTIMIZER_REGION' ) && S3_IMAGE_OPTIMIZER_REGION ) {
		$args['region'] = S3_IMAGE_OPTIMIZER_REGION;
	}
	return $args;
}

/**
 * Adjusts the endpoint and region for DO Spaces connection.
 *
 * @param array $args A list of arguments sent to the AWS SDK.
 * @return array The arguments for the AWS connection, possibly modified.
 */
function s3io_dospaces( $args ) {
	if ( get_option( 's3io_dospaces' ) || defined( 'S3IO_DOSPACES' ) ) {
		$region           = defined( 'S3IO_DOSPACES' ) ? S3IO_DOSPACES : get_option( 's3io_dospaces' );
		$args['endpoint'] = 'https://' . $region . '.digitaloceanspaces.com';
		$args['region']   = $region;
	}
	return $args;
}

/**
 * Let the user know that they need the EWWW IO plugin before S3 IO can do anything.
 */
function s3io_missing_ewww_plugin() {
	?>
	<div id='s3io-error-ewww' class='error'>
		<p>
			<?php /* translators: %s: AWS error message */ ?>
			<?php printf( esc_html__( 'Could not detect the %s plugin, please install and configure it first.', 's3-image-optimizer' ), '<a href="' . esc_url( admin_url( 'plugin-install.php?s=ewww+image+optimizer&tab=search&type=term' ) ) . '">EWWW Image Optimizer</a>' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Setup the admin menu items for the plugin.
 */
function s3io_admin_menu() {
	if ( ! function_exists( 'ewww_image_optimizer' ) ) {
		return;
	}
	add_media_page( esc_html__( 'S3 Bulk Image Optimizer', 's3-image-optimizer' ), esc_html__( 'S3 Bulk Optimizer', 's3-image-optimizer' ), 'activate_plugins', 's3io-bulk-display', 's3io_bulk_display' );
	add_media_page( esc_html__( 'S3 Bulk URL Optimizer', 's3-image-optimizer' ), esc_html__( 'S3 URL Optimizer', 's3-image-optimizer' ), 'activate_plugins', 's3io-url-display', 's3io_url_display' );
	// Add options page to the settings menu.
	add_options_page(
		esc_html__( 'S3 Image Optimizer', 's3-image-optimizer' ), // Title.
		esc_html__( 'S3 Image Optimizer', 's3-image-optimizer' ), // Sub-menu title.
		'manage_options',                                         // Security.
		S3IO_PLUGIN_FILE,                                         // File to open.
		's3io_options_page'                                       // Function to call.
	);
}

/**
 * Display settings page for the plugin.
 */
function s3io_options_page() {
	global $s3io_amazon_web_services;
	$bucket_list = get_option( 's3io_bucketlist' );
	?>
<div class='wrap'>
	<h1><?php esc_html_e( 'S3 Image Optimizer', 's3-image-optimizer' ); ?></h1>
	<p>
		<a href="https://docs.ewww.io/article/22-how-to-use-s3-image-optimizer"><?php esc_html_e( 'Installation Instructions', 's3-image-optimizer' ); ?></a> |
		<a href="https://ewww.io/contact-us/"><?php esc_html_e( 'Support', 's3-image-optimizer' ); ?></a>
	</p>
	<form method='post' action='options.php'>
		<?php settings_fields( 's3io_options' ); ?>
		<table class='form-table'>
	<?php if ( $s3io_amazon_web_services->needs_access_keys() ) : ?>
			<tr>
				<th><?php esc_html_e( 'AWS Access Keys', 's3-image-optimizer' ); ?></th>
				<td>
					<i><?php esc_html_e( 'We recommend defining your Access Keys in wp-config.php so long as you don’t commit it to source control (you shouldn’t be).', 's3-image-optimizer' ); ?></i><br>
					<?php esc_html_e( 'Simply copy the following snippet and replace the stars with the keys.' ); ?>
					<a href="https://docs.ewww.io/article/61-creating-an-amazon-web-services-aws-user" target="_blank"><?php esc_html_e( 'Not sure where to find your access keys?', 's3-image-optimizer' ); ?></a><br>
					<pre>define( 'DBI_AWS_ACCESS_KEY_ID', '********************' );
define( 'DBI_AWS_SECRET_ACCESS_KEY', '****************************************' );</pre>
				</td>
			</tr>
			<tr>
				<th><label for="s3io_aws_access_key_id"><?php esc_html_e( 'AWS Access Key ID', 's3-image-optimizer' ); ?></label></th>
				<td><input type="text" id="s3io_aws_access_key_id" name="s3io_aws_access_key_id" value="<?php echo esc_attr( get_option( 's3io_aws_access_key_id' ) ); ?>" size="64" /></td>
			</tr>
			<tr>
				<th><label for="s3io_aws_secret_access_key"><?php esc_html_e( 'AWS Secret Access Key', 's3-image-optimizer' ); ?></label></th>
				<td><input type="text" id="s3io_aws_secret_access_key" name="s3io_aws_secret_access_key" value="<?php echo esc_attr( get_option( 's3io_aws_secret_access_key' ) ); ?>" size="64" /></td>
			</tr>
	<?php else : ?>
		<?php if ( get_option( 's3io_aws_access_key_id' ) ) : ?>
			<tr>
				<th><label for="s3io_aws_access_key_id"><?php esc_html_e( 'AWS Access Key ID', 's3-image-optimizer' ); ?></label></th>
				<td><input type="text" id="s3io_aws_access_key_id" name="s3io_aws_access_key_id" value="<?php echo esc_attr( get_option( 's3io_aws_access_key_id' ) ); ?>" size="64" /></td>
			</tr>
		<?php endif; ?>
		<?php if ( get_option( 's3io_aws_secret_access_key' ) ) : ?>
			<tr>
				<th><label for="s3io_fake_secret_access_key"><?php esc_html_e( 'AWS Secret Access Key', 's3-image-optimizer' ); ?></label></th>
				<td><input type="text" id="s3io_fake_secret_access_key" name="s3io_fake_secret_access_key" readonly='readonly' value="********************" size="64" />
					<a href="<?php echo esc_url( admin_url( 'admin.php?action=s3io_remove_aws_keys' ) ); ?>"><?php esc_html_e( 'Remove Access Keys', 's3-image-optimizer' ); ?></a>
					<input type="hidden" id="s3io_aws_secret_access_key" name="s3io_aws_secret_access_key" value="<?php echo esc_attr( get_option( 's3io_aws_secret_access_key' ) ); ?>" size="64" />
				</td>
			</tr>
		<?php endif; ?>
	<?php endif; ?>
			<tr>
				<th><label for="s3io_dospaces"><?php esc_html_e( 'Digital Ocean Spaces Region', 's3-image-optimizer' ); ?></label></th>
				<td>
					<input type="text" id="s3io_dospaces" name="s3io_dospaces" value="<?php echo esc_attr( get_option( 's3io_dospaces' ) ); ?>" size="10" />
					<p class='description'><?php esc_html_e( 'To use Digital Ocean Spaces, enter the region for your space, or define the S3IO_DOSPACES constant.', 's3-image-optimizer' ); ?></p>
				</td>
			</tr>
			<?php
			try {
				$client = $s3io_amazon_web_services->get_client();
			} catch ( Exception $e ) {
				echo '</table><p>' . wp_kses_post( $e->getMessage() ) . '</p>';
				echo "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 's3-image-optimizer' ) . "' /></p>";
				return;
			}
			if ( is_wp_error( $client ) ) {
				echo '</table><p>' . wp_kses_post( $aws->get_error_message() ) . '</p>';
				echo "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 's3-image-optimizer' ) . "' /></p>";
				return;
			}
			try {
				$buckets = $client->listBuckets();
			} catch ( Exception $e ) {
				$buckets = new WP_Error( 'exception', $e->getMessage() );
			}
			?>
			<tr>
				<th><label for="s3io_bucketlist"><?php esc_html_e( 'Buckets to optimize', 's3-image-optimizer' ); ?></label></th>
				<td>
			<?php if ( defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) && S3_IMAGE_OPTIMIZER_BUCKET ) : ?>
					<p>
						<?php esc_html_e( 'You have currently defined the bucket constant (S3_IMAGE_OPTIMIZER_BUCKET):', 's3-image-optimizer' ); ?><br>
						<?php echo esc_html( S3_IMAGE_OPTIMIZER_BUCKET ); ?>
					</p>
			<?php else : ?>
				<?php if ( is_wp_error( $buckets ) ) : ?>
					<?php esc_html_e( 'One bucket per line. If empty, all available buckets will be optimized.', 's3-image-optimizer' ); ?><br>
				<?php else : ?>
					<?php esc_html_e( 'One bucket per line, must match one of the buckets listed below. If empty, all available buckets will be optimized.', 's3-image-optimizer' ); ?><br>
				<?php endif; ?>
				<?php
				echo "<textarea id='s3io_bucketlist' name='s3io_bucketlist' rows='3' cols='40'>";
				if ( ! empty( $bucket_list ) && is_array( $bucket_list ) ) {
					foreach ( $bucket_list as $bucket ) {
						echo esc_html( $bucket ) . "\n";
					}
				}
				echo '</textarea>';
				?>
			<?php endif; ?>
					<p class='description'>
			<?php
			if ( is_wp_error( $buckets ) && ! defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) ) {
				/* translators: %s: AWS error message */
				printf( esc_html__( 'Could not list buckets due to AWS error: %s', 's3-image-optimizer' ), '<br>' . wp_kses_post( $buckets->get_error_message() ) );
				echo '<br>';
			} elseif ( ! is_wp_error( $buckets ) ) {
				esc_html_e( 'These are the buckets that we have access to optimize:', 's3-image-optimizer' );
				echo '<br>';
				foreach ( $buckets['Buckets'] as $bucket ) {
					echo esc_html( $bucket['Name'] ) . "<br>\n";
				}
			}
			?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Sub-folders', 's3-image-optimizer' ); ?></th>
				<td><?php esc_html_e( 'You may set the S3_IMAGE_OPTIMIZER_FOLDER constant to restrict optimization to a specific sub-directory of the bucket(s) above.', 's3-image-optimizer' ); ?></td>
			</tr>
		</table>
		<p class='submit'><input type='submit' class='button-primary' value='<?php esc_attr_e( 'Save Changes', 's3-image-optimizer' ); ?>' /></p>
	</form>
</div>
	<?php
}

/**
 * Removes AWS keys from the database.
 */
function s3io_remove_aws_keys() {
	if ( false === current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Access denied', 's3-image-optimizer' ) );
	}
	delete_option( 's3io_aws_access_key_id' );
	delete_option( 's3io_aws_secret_access_key' );
	$sendback = wp_get_referer();
	wp_safe_redirect( $sendback );
	exit;
}

/**
 * Sanitize the bucket list provided by the user.
 *
 * @param string $input A list of buckets, separated by newline characters.
 * @return array An array of buckets verified to be accurate for the user's account.
 */
function s3io_bucketlist_sanitize( $input ) {
	if ( empty( $input ) ) {
		return '';
	}
	global $s3io_amazon_web_services;
	try {
		$client = $s3io_amazon_web_services->get_client();
	} catch ( Exception $e ) {
		return false;
	}
	try {
		$buckets = $client->listBuckets();
	} catch ( Exception $e ) {
		$buckets = new WP_Error( 'exception', $e->getMessage() );
	}
	$bucket_array  = array();
	$input_buckets = explode( "\n", $input );
	foreach ( $input_buckets as $input_bucket ) {
		$input_bucket = trim( $input_bucket );
		if ( is_wp_error( $buckets ) ) {
			if ( strlen( $input_bucket ) < 3 || strlen( $input_bucket ) > 63 ) {
				continue;
			}
			if ( preg_match( '/^([a-z0-9]+(-[a-z0-9]+)*\.*)+/', $input_bucket ) ) {
				$bucket_array[] = $input_bucket;
			}
		} else {
			foreach ( $buckets['Buckets'] as $bucket ) {
				if ( $input_bucket === $bucket['Name'] ) {
					$bucket_array[] = $input_bucket;
				}
			}
		}
	}
	return $bucket_array;
}

/**
 * Alert the user if the s3io folder could not be created within the uploads folder.
 */
function s3io_make_upload_dir_remote_error() {
	echo "<div id='s3io-error-mkdir' class='error'><p>" . esc_html__( 'Unable to create the /s3io/ working directory: could not determine local upload directory path.', 's3-image-optimizer' ) . '</p></div>';
}

/**
 * Alert the user if the s3io folder could not be created within the uploads folder.
 */
function s3io_make_upload_dir_failed() {
	echo "<div id='s3io-error-mkdir' class='error'><p>" . esc_html__( 'Could not create the /s3io/ folder within the WordPress uploads folder, please adjust the permissions and try again.', 's3-image-optimizer' ) . '</p></div>';
}

/**
 * Alert the user if the s3io folder is not writable.
 */
function s3io_make_upload_dir_write_error() {
	echo "<div id='s3io-error-mkdir' class='error'><p>" . esc_html__( 'The /s3io/ working directory is not writable, please check permissions for the WordPress uploads folder and /s3io/ sub-folder.', 's3-image-optimizer' ) . '</p></div>';
}

/**
 * Attempt to create the s3io/ folder within the uploads directory.
 *
 * @return string The absolute filesystem path to the s3io/ directory.
 */
function s3io_make_upload_dir() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Unlook S3 Uploads from upload_dir.
	if ( class_exists( 'S3_Uploads' ) ) {
		s3io_debug_message( 'S3_Uploads detected, removing upload_dir filters' );
		remove_all_filters( 'upload_dir' );
	}
	$upload_dir = wp_upload_dir( null, false, true );
	if ( false !== strpos( $upload_dir['basedir'], 's3://' ) ) {
		s3io_debug_message( "upload_dir has an s3 prefix: {$upload_dir['basedir']}" );
		add_action( 'admin_notices', 's3io_make_upload_dir_remote_error' );
		return trailingslashit( $upload_dir['basedir'] ) . 's3io/';
	}
	if ( ! is_writable( $upload_dir['basedir'] ) ) {
		s3io_debug_message( 'upload_dir is not writable' );
		add_action( 'admin_notices', 's3io_make_upload_dir_failed' );
		return false;
	}
	$upload_dir = trailingslashit( $upload_dir['basedir'] ) . 's3io/';
	if ( ! is_dir( $upload_dir ) ) {
		$mkdir = wp_mkdir_p( $upload_dir );
		if ( ! $mkdir ) {
			s3io_debug_message( 'could not create /s3io/ working dir' );
			add_action( 'admin_notices', 's3io_make_upload_dir_failed' );
			return false;
		}
	}
	if ( ! is_writable( $upload_dir ) ) {
		s3io_debug_message( "$upload_dir is not writable" );
		add_action( 'admin_notices', 's3io_make_upload_dir_write_error' );
		return false;
	}
	s3io_debug_message( "using $upload_dir as working dir" );
	return $upload_dir;
}

/**
 * Prepares the bulk operation and includes the javascript functions.
 *
 * @param string $hook The hook/suffix for the current page.
 */
function s3io_bulk_script( $hook ) {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make sure we are being called from the proper page.
	if ( 's3io-auto' !== $hook && 'media_page_s3io-bulk-display' !== $hook ) {
		return;
	}
	s3io_make_upload_dir();
	// Check to see if the user has asked to reset (empty) the optimized images table.
	if ( ! empty( $_REQUEST['s3io_force_empty'] ) && ! empty( $_REQUEST['s3io_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk-empty' ) ) {
		s3io_table_truncate();
	}
	// Check to see if we are supposed to reset the bulk operation and verify we are authorized to do so.
	if ( ! empty( $_REQUEST['s3io_reset_bulk'] ) && ! empty( $_REQUEST['s3io_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk-reset' ) ) {
		update_option( 's3io_resume', '', false );
	}
	// Check the 'bulk resume' option.
	$resume = get_option( 's3io_resume' );

	update_option( 's3io_bucket_paginator', '', false );
	update_option( 's3io_buckets_scanned', '', false );
	if ( empty( $resume ) ) {
		s3io_table_delete_pending();
	}
	if ( 'media_page_s3io_bulk-display' !== $hook ) {
		// Submit a couple variables to the javascript to work with.
		wp_enqueue_script( 's3iobulkscript', plugins_url( '/s3io.js', __FILE__ ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar', 'postbox', 'dashboard' ), S3IO_VERSION );
		$image_count = s3io_table_count_optimized();
		wp_localize_script(
			's3iobulkscript',
			's3io_vars',
			array(
				'_wpnonce'              => wp_create_nonce( 's3io-bulk' ),
				'image_count'           => $image_count, // Number of images completed.
				'attachments'           => s3io_table_count_pending(), // Number of pending images, will be 0 unless resuming.
				/* translators: %s: number of items completed (includes HTML markup) */
				'completed_string'      => sprintf( esc_html__( 'Checked %s files so far', 's3-image-optimizer' ), '<span id="s3io-completed-count"></span>' ),
				/* translators: %d: number of images */
				'count_string'          => sprintf( esc_html__( '%d images', 's3-image-optimizer' ), $image_count ),
				'starting_scan'         => esc_html__( 'Scanning buckets...', 's3-image-optimizer' ),
				'operation_stopped'     => esc_html__( 'Optimization stopped, reload page to resume.', 's3-image-optimizer' ),
				'operation_interrupted' => esc_html__( 'Operation Interrupted', 's3-image-optimizer' ),
				'temporary_failure'     => esc_html__( 'Temporary failure, seconds left to retry:', 's3-image-optimizer' ),
				'remove_failed'         => esc_html__( 'Could not remove image from table.', 's3-image-optimizer' ),
				'optimized'             => esc_html__( 'Optimized', 's3-image-optimizer' ),
			)
		);
		wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( 'jquery-ui-1.10.1.custom.css', __FILE__ ), array(), S3IO_VERSION );
	} else {
		return;
	}
}

/**
 * Prepares the bulk URL operation and includes the javascript functions.
 *
 * @param string $hook The hook/suffix for the current page.
 */
function s3io_url_script( $hook ) {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make sure we are being called from the proper page.
	if ( 'media_page_s3io-url-display' !== $hook ) {
		return;
	}
	s3io_make_upload_dir();
	$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
	// Submit a couple variables to the javascript to work with.
	wp_enqueue_script( 's3iobulkscript', plugins_url( '/s3io.js', __FILE__ ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar', 'postbox', 'dashboard' ), S3IO_VERSION );
	wp_localize_script(
		's3iobulkscript',
		's3io_vars',
		array(
			'_wpnonce'              => wp_create_nonce( 's3io-url' ),
			'operation_stopped'     => esc_html__( 'Optimization stopped, reload page to optimize more images by url.', 's3-image-optimizer' ),
			'operation_interrupted' => esc_html__( 'Operation Interrupted', 's3-image-optimizer' ),
			'temporary_failure'     => esc_html__( 'Temporary failure, seconds left to retry:', 's3-image-optimizer' ),
			'optimized'             => esc_html__( 'Optimized', 's3-image-optimizer' ),
			'finished'              => esc_html__( 'Finished', 's3-image-optimizer' ),
			'optimizing'            => esc_html__( 'Optimizing', 's3-image-optimizer' ),
			'spinner'               => "<img src='$loading_image' alt='loading'/>",
		)
	);
	wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( 'jquery-ui-1.10.1.custom.css', __FILE__ ), array(), S3IO_VERSION );
}

/**
 * Get list of buckets.
 *
 * @return array A list of bucket names.
 */
function s3io_get_selected_buckets() {
	global $s3io_errors;
	if ( defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) && S3_IMAGE_OPTIMIZER_BUCKET ) {
		$bucket_list = array( S3_IMAGE_OPTIMIZER_BUCKET );
	} else {
		$bucket_list = get_option( 's3io_bucketlist' );
	}
	if ( empty( $bucket_list ) ) {
		global $s3io_amazon_web_services;
		try {
			$client = $s3io_amazon_web_services->get_client();
		} catch ( Exception $e ) {
			$s3io_errors[] = $e->getMessage();
		}
		$bucket_list = array();
		try {
			$buckets = $client->listBuckets();
		} catch ( Exception $e ) {
			$buckets = new WP_Error( 'exception', $e->getMessage() );
		}
		if ( is_wp_error( $buckets ) ) {
			/* translators: %s: AWS error message */
			$s3io_errors[] = sprintf( esc_html__( 'Could not list buckets: %s', 's3-image-optimizer' ), wp_kses_post( $buckets->get_error_message() ) );
		} else {
			foreach ( $buckets['Buckets'] as $aws_bucket ) {
				$bucket_list[] = $aws_bucket['Name'];
			}
		}
	}
	return $bucket_list;
}

/**
 * Scan buckets for images and store in database.
 *
 * @param bool $verbose Enable (true) to output WP_CLI logging. Default false.
 */
function s3io_image_scan( $verbose = false ) {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$wpcli = false;
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		$wpcli = true;
	}
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $wpcli && ( empty( $_REQUEST['s3io_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! current_user_can( $permissions ) ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
	}
	global $wpdb;
	global $s3io_errors;
	$s3io_errors   = array();
	$images        = array();
	$image_count   = 0;
	$scan_count    = 0;
	$field_formats = array(
		'%s', // bucket.
		'%s', // path.
		'%d', // image_size.
	);
	/* $start = microtime( true ); */
	global $s3io_amazon_web_services;
	try {
		$client = $s3io_amazon_web_services->get_client();
	} catch ( Exception $e ) {
		$s3io_errors[] = $e->getMessage();
		if ( $wpcli ) {
			return 0;
		}
		die( wp_json_encode( array( 'error' => $s3io_errors[0] ) ) );
	}

	$bucket_list = s3io_get_selected_buckets();
	if ( ! empty( $s3io_errors ) ) {
		/* translators: %s: AWS error message */
		if ( $wpcli ) {
			return 0;
		}
		die( wp_json_encode( array( 'error' => $s3io_errors[0] ) ) );
	}

	$completed_buckets = get_option( 's3io_buckets_scanned' ) ? get_option( 's3io_buckets_scanned' ) : array();
	$paginator         = get_option( 's3io_bucket_paginator' );
	foreach ( $bucket_list as $bucket ) {
		s3io_debug_message( "scanning $bucket" );
		if ( $verbose && $wpcli ) {
			/* translators: %s: S3 bucket name */
			WP_CLI::line( sprintf( __( 'Scanning bucket %s...', 's3-image-optimizer' ), $bucket ) );
		}
		foreach ( $completed_buckets as $completed_bucket ) {
			if ( $bucket === $completed_bucket ) {
				s3io_debug_message( "skipping $bucket, already done" );
				continue 2;
			}
		}
		try {
			$location = $client->getBucketLocation(
				array(
					'Bucket' => $bucket,
				)
			);
		} catch ( Exception $e ) {
			$location = new WP_Error( 'exception', $e->getMessage() );
		}
		$region = 'us-east-1';
		if ( is_wp_error( $location ) && ( ! defined( 'S3_IMAGE_OPTIMIZER_REGION' ) || ! S3_IMAGE_OPTIMIZER_REGION ) ) {
			/* translators: 1: bucket name 2: AWS error message */
			$s3io_errors[] = sprintf( esc_html__( 'Could not get bucket location for %1$s, error: %2$s. Will assume us-east-1 region for all buckets. You may set the region manually using the S3_IMAGE_OPTIMIZER_REGION constant in wp-config.php.', 's3-image-optimizer' ), $bucket, wp_kses_post( $location->get_error_message() ) );
			if ( ! $wpcli ) {
				die( wp_json_encode( array( 'error' => $s3io_errors[0] ) ) );
			}
		} elseif ( empty( get_option( 's3io_dospaces' ) ) ) {
			if ( ! is_wp_error( $location ) && ! empty( $location['Location'] ) ) {
				$region = $location['Location'];
			} elseif ( defined( 'S3_IMAGE_OPTIMIZER_REGION' ) && S3_IMAGE_OPTIMIZER_REGION ) {
				$region = S3_IMAGE_OPTIMIZER_REGION;
			}
		}
		$paginator_args = array( 'Bucket' => $bucket );
		if ( defined( 'S3_IMAGE_OPTIMIZER_FOLDER' ) && S3_IMAGE_OPTIMIZER_FOLDER ) {
			$paginator_args['Prefix'] = ltrim( S3_IMAGE_OPTIMIZER_FOLDER, '/' );
		}
		if ( $paginator ) {
			s3io_debug_message( "starting from $paginator" );
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
		if ( ewww_image_optimizer_stl_check() ) {
			set_time_limit( 0 );
		}
		try {
			foreach ( $results as $result ) {
				foreach ( $result['Contents'] as $object ) {
					$scan_count++;
					$skip_optimized = false;
					$path           = $object['Key'];
					s3io_debug_message( "$scan_count: checking $path" );
					if ( preg_match( '/\.(jpe?g|png|gif)$/i', $path ) ) {
						$image_size = (int) $object['Size'];
						if ( isset( $optimized_list[ $path ] ) && $optimized_list[ $path ] === $image_size ) {
							s3io_debug_message( 'size matches db, skipping' );
							$skip_optimized = true;
						}
					} else {
						s3io_debug_message( 'not an image, skipping' );
						continue;
					}
					if ( ! $skip_optimized || ! empty( $_REQUEST['s3io_force'] ) ) {
						$images[ $path ] = array(
							'bucket'    => $bucket,
							'path'      => $path,
							'orig_size' => $image_size,
						);
						if ( $verbose && $wpcli ) {
							/* translators: 1: image name 2: S3 bucket name */
							WP_CLI::line( sprintf( __( 'Queueing %1$s in %2$s.', 's3-image-optimizer' ), $path, $bucket ) );
						}
						s3io_debug_message( "queuing $path in $bucket" );
						$image_count++;
					}
					if ( $scan_count >= 4000 && count( $images ) ) {
						// let's dump what we have so far to the db.
						ewww_image_optimizer_mass_insert( $wpdb->s3io_images, $images, $field_formats );
						s3io_debug_message( "saved queue to db after checking $scan_count and finding $image_count" );
						if ( $verbose && $wpcli ) {
							WP_CLI::line( __( 'Saved queue to database.', 's3-image-optimizer' ) );
						}
						if ( ! $wpcli ) {
							s3io_debug_message( "stashing $path as last marker" );
							update_option( 's3io_bucket_paginator', $path, false );
							die(
								wp_json_encode(
									array(
										/* translators: %s: S3 bucket name */
										'current'   => sprintf( esc_html__( 'Scanning bucket %s', 's3-image-optimizer' ), "<strong>$bucket</strong>" ),
										'completed' => $scan_count, // Number of images scanned in this pass.
									)
								)
							);
						}
						$image_count = 0;
						$images      = array();
					} elseif ( $scan_count >= 4000 && ! $wpcli ) {
						s3io_debug_message( "stashing $path as last marker" );
						update_option( 's3io_bucket_paginator', $path, false );
						die(
							wp_json_encode(
								array(
									/* translators: %s: S3 bucket name */
									'current'   => sprintf( esc_html__( 'Scanning bucket %s', 's3-image-optimizer' ), "<strong>$bucket</strong>" ),
									'completed' => $scan_count, // Number of images scanned in this pass.
								)
							)
						);
					}
				}
			}
		} catch ( Exception $e ) {
			/* translators: 1: bucket name 2: AWS error message */
			$s3io_errors[] = sprintf( esc_html__( 'Incorrect region for %1$s, please set the region using the S3_IMAGE_OPTIMIZER_REGION constant in wp-config.php. Error: %2$s.', 's3-image-optimizer' ), $bucket, wp_kses_post( $e->getMessage() ) );
			if ( ! $wpcli ) {
				die( wp_json_encode( array( 'error' => $s3io_errors[0] ) ) );
			}
		}
		$paginator = '';
		update_option( 's3io_bucket_paginator', $paginator, false );
		$completed_buckets[] = $bucket;
		s3io_debug_message( "adding $bucket to the completed list" );
		update_option( 's3io_buckets_scanned', $completed_buckets, false );
	}
	if ( ! empty( $images ) ) {
		s3io_debug_message( 'saving queue to db' );
		ewww_image_optimizer_mass_insert( $wpdb->s3io_images, $images, $field_formats );
	}
	s3io_debug_message( "found $image_count images to optimize" );
	update_option( 's3io_buckets_scanned', '', false );
	if ( ! $wpcli ) {
		$pending = s3io_table_count_pending();
		/* translators: %d: number of images */
		$message = $pending ? sprintf( esc_html__( 'There are %d images to be optimized.', 's3-image-optimizer' ), $pending ) : esc_html__( 'There is nothing left to optimize.', 's3-image-optimizer' );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
			$message .= ' *' . esc_html__( 'WebP versions will be generated and uploaded in accordance with EWWW IO settings.', 's3-image-optimizer' );
		}
		die(
			wp_json_encode(
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
 * Display the bulk S3 optimization page for URLs.
 */
function s3io_url_display() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
	?>
	<div class="wrap">
	<h1><?php esc_html_e( 'S3 URL Optimizer', 's3-image-optimizer' ); ?></h1>
		<div id="s3io-bulk-loading">
			<p id="s3io-loading" class="s3io-bulk-info" style="display:none">&nbsp;<img src="<?php echo esc_url( $loading_image ); ?>" /></p>
		</div>
		<div id="s3io-bulk-progressbar"></div>
		<div id="s3io-bulk-counter"></div>
		<form id="s3io-bulk-stop" style="display:none;" method="post" action="">
			<br /><input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Stop Optimizing', 's3-image-optimizer' ); ?>" />
		</form>
		<div id="s3io-bulk-widgets" class="metabox-holder" style="display:none">
			<div class="meta-box-sortables">
				<div id="s3io-bulk-status" class="postbox">
					<button type="button" class="s3io-handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 's3-image-optimizer' ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
					<h2 class="s3io-hndle"><span><?php esc_html_e( 'Optimization Log', 's3-image-optimizer' ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
		</div>
		<form class="s3io-bulk-form">
			<p><label for="s3io-delay" style="font-weight: bold"><?php esc_html_e( 'Choose how long to pause between images (in seconds, 0 = disabled)', 's3-image-optimizer' ); ?></label>&emsp;<input type="text" id="s3io-delay" name="s3io-delay" value="<?php echo ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) ? (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) : 0 ); ?>"></p>
			<div id="s3io-delay-slider" style="width:50%"></div>
		</form>
		<div id="s3io-bulk-forms"><p class="s3io-bulk-info">
			<p class="s3io-media-info s3io-bulk-info">
				<?php esc_html_e( 'Previously optimized images will not be skipped.', 's3-image-optimizer' ); ?>
				<?php echo ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ? ' *' . esc_html__( 'WebP versions will be generated and uploaded in accordance with EWWW IO settings.', 's3-image-optimizer' ) : '' ); ?>
			</p>
			<form id="s3io-url-start" class="s3io-bulk-form" method="post" action="">
				<p><label><?php esc_html_e( 'List images to be processed by URL (1 per line), for example:', 's3-image-optimizer' ); ?> https://bucket-name.s3.amazonaws.com/uploads/<?php echo esc_html( gmdate( 'Y' ) . '/' . gmdate( 'm' ) ); ?>/test-image.jpg<br><textarea id="s3io-url-image-queue" name="s3io-url-image-queue" style="resize:both; height: 300px; width: 60%;"></textarea></label></p>
				<input id="s3io-first" type="submit" class="button-primary action" value="<?php esc_attr_e( 'Start optimizing', 's3-image-optimizer' ); ?>" />
			</form>
		</div>
	</div>
	<?php
}

/**
 * Display the bulk S3 optimization page.
 */
function s3io_bulk_display() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	global $s3io_errors;
	// Retrieve the value of the 'aux resume' option and set the button text for the form to use.
	$s3io_resume = get_option( 's3io_resume' );
	$start_text  = __( 'Start optimizing', 's3-image-optimizer' );
	if ( empty( $s3io_resume ) ) {
		$button_text = __( 'Scan for unoptimized images', 's3-image-optimizer' );
	} else {
		$button_text = __( 'Resume where you left off', 's3-image-optimizer' );
	}
	// find out if the auxiliary image table has anything in it.
	$already_optimized = s3io_table_count_optimized();
	// generate the WP spinner image for display.
	$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
	echo "\n";
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'S3 Bulk Optimizer', 's3-image-optimizer' ); ?></h1>
	<?php
	if ( ! empty( $s3io_errors ) && is_array( $s3io_errors ) ) {
		foreach ( $s3io_errors as $s3io_error ) {
			echo '<p style="color: red"><strong>' . esc_html( $s3io_error ) . '</strong></p>';
		}
	}
	$s3io_errors = array();
	?>
		<div id="s3io-bulk-loading">
			<p id="s3io-loading" class="s3io-bulk-info" style="display:none">&nbsp;<img src="<?php echo esc_url( $loading_image ); ?>" /></p>
		</div>
		<div id="s3io-bulk-progressbar"></div>
		<div id="s3io-bulk-counter"></div>
		<form id="s3io-bulk-stop" style="display:none;" method="post" action="">
			<br /><input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Stop Optimizing', 's3-image-optimizer' ); ?>" />
		</form>
		<div id="s3io-bulk-widgets" class="metabox-holder" style="display:none">
			<div class="meta-box-sortables">
				<div id="s3io-bulk-last" class="postbox">
					<button type="button" class="s3io-handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 's3-image-optimizer' ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
					<h2 class="s3io-hndle"><span><?php esc_html_e( 'Last Image Optimized', 's3-image-optimizer' ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
			<div class="meta-box-sortables">
				<div id="s3io-bulk-status" class="postbox">
					<button type="button" class="s3io-handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 's3-image-optimizer' ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
					<h2 class="s3io-hndle"><span><?php esc_html_e( 'Optimization Log', 's3-image-optimizer' ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
		</div>
	<?php
	$delay = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
	?>
		<form id="s3io-delay-slider-form" class="s3io-bulk-form">
			<p><label for="s3io-delay" style="font-weight: bold"><?php esc_html_e( 'Choose how long to pause between images (in seconds, 0 = disabled)', 's3-image-optimizer' ); ?></label>&emsp;<input type="text" id="s3io-delay" name="s3io-delay" value="<?php echo (int) $delay; ?>"></p>
			<div id="s3io-delay-slider" style="width:50%"></div>
		</form>
		<div id="s3io-bulk-forms">
			<p class="s3io-media-info s3io-bulk-info"><strong><?php	esc_html_e( 'Currently selected buckets:', 's3-image-optimizer' ); ?></strong>
				<?php
				$bucket_list = s3io_get_selected_buckets();
				if ( ! empty( $s3io_errors ) ) {
					echo '<span style="color: red"><strong>' . esc_html( $s3io_error[0] ) . '</strong></p>';
				} elseif ( empty( $bucket_list ) ) {
					echo '<strong>' . esc_html__( 'Unable to find any buckets to scan.', 's3-image-optimizer' ) . '</strong>';
				} else {
					foreach ( $bucket_list as $bucket ) {
						echo '<br>' . esc_html( $bucket );
					}
				}
				?>
			</p>
	<?php
	if ( empty( $s3io_resume ) && ! empty( $bucket_list ) ) {
		?>
			<form id="s3io-scan" class="s3io-bulk-form" method="post" action="">
				<input id="s3io-scan-button" type="submit" class="button-primary" value="<?php echo esc_attr( $button_text ); ?>" />
			</form>
			<p id="s3io-found-images" class="s3io-bulk-info" style="display:none;"></p>
			<form id="s3io-start" class="s3io-bulk-form" style="display:none;" method="post" action="">
				<input id="s3io-start-button" type="submit" class="button-primary" value="<?php echo esc_attr( $start_text ); ?>" />
			</form>
		<?php
	}
	if ( ! empty( $s3io_resume ) ) {
		?>
		<p id="s3io-found-images" class="s3io-bulk-info">
			<?php
			$pending = s3io_table_count_pending();
			/* translators: %d: number of images */
			printf( esc_html__( 'There are %d images to be optimized.', 's3-image-optimizer' ), (int) $pending );
			?>
		</p>
		<form id="s3io-start" class="s3io-bulk-form" method="post" action="">
			<input id="s3io-start-button" type="submit" class="button-primary action" value="<?php echo esc_attr( $button_text ); ?>" />
		</form>
		<p class="s3io-bulk-info"><?php esc_html_e( 'Would you like to clear the queue and rescan for images?', 's3-image-optimizer' ); ?></p>
			<form id="s3io-bulk-reset" class="s3io-bulk-form" method="post" action="">
				<?php wp_nonce_field( 's3io-bulk-reset', 's3io_wpnonce' ); ?>
				<input type="hidden" name="s3io_reset_bulk" value="1">
				<button type="submit" class="button-secondary action"><?php esc_html_e( 'Clear Queue', 's3-image-optimizer' ); ?></button>
			</form>
		<?php
	}
	if ( empty( $already_optimized ) ) {
		$display = 'display:none';
	} else {
		$display = '';
		?>
			<p class="s3io-bulk-info" style="margin-top: 2.5em"><?php esc_html_e( 'Force a re-optimization of all images by erasing the optimization history. This cannot be undone, as it will remove all optimization records from the database.', 's3-image-optimizer' ); ?></p>
			<form id="s3io-force-empty" class="s3io-bulk-form" style="margin-bottom: 2.5em" method="post" action="">
				<?php wp_nonce_field( 's3io-bulk-empty', 's3io_wpnonce' ); ?>
				<input type="hidden" name="s3io_force_empty" value="1">
				<button type="submit" class="button-secondary action"><?php esc_html_e( 'Erase Optimization History', 's3-image-optimizer' ); ?></button>
			</form>
			<?php
	}
	?>
			<p id="s3io-table-info" class="s3io-bulk-info" style="<?php echo esc_attr( $display ); ?>">
			<?php
			/* translators: %d: number of images */
			printf( esc_html__( 'The optimizer keeps track of already optimized images to prevent re-optimization. There are %d images that have been optimized so far.', 's3-image-optimizer' ), (int) $already_optimized );
			?>
			</p>
			<form id="s3io-show-table" class="s3io-bulk-form" method="post" action="" style="<?php echo esc_attr( $display ); ?>">
				<button type="submit" class="button-secondary action"><?php esc_html_e( 'Show Optimized Images', 's3-image-optimizer' ); ?></button>
			</form>
			<div class="tablenav s3io-aux-table" style="display:none">
				<div class="tablenav-pages s3io-table">
					<span class="displaying-num s3io-table"></span>
					<span id="paginator" class="pagination-links s3io-table">
						<a id="first-images" class="tablenav-pages-navspan button first-page" style="display:none">&laquo;</a>
						<a id="prev-images" class="tablenav-pages-navspan button prev-page" style="display:none">&lsaquo;</a>
						<?php esc_html_e( 'page', 's3-image-optimizer' ); ?> <span class="current-page"></span> <?php esc_html_e( 'of', 's3-image-optimizer' ); ?>
						<span class="total-pages"></span>
						<a id="next-images" class="tablenav-pages-navspan button next-page" style="display:none">&rsaquo;</a>
						<a id="last-images" class="tablenav-pages-navspan button last-page" style="display:none">&raquo;</a>
					</span>
				</div>
			</div>
			<div id="s3io-bulk-table" class="s3io-table"></div>
			<span id="s3io-pointer" style="display:none">0</span>
		</div>
	</div>
	<?php
}

/**
 * Find the number of optimized images in the s3io_images table.
 */
function s3io_table_count_optimized() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->s3io_images WHERE image_size IS NOT NULL" );
	return $count;
}

/**
 * Find the number of un-optimized images in the s3io_images table.
 */
function s3io_table_count_pending() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->s3io_images WHERE image_size IS NULL" );
	return $count;
}

/**
 * Remove all un-optimized images from the s3io_images table.
 */
function s3io_table_delete_pending() {
	global $wpdb;
	$wpdb->query( "DELETE from $wpdb->s3io_images WHERE image_size IS NULL" );
}

/**
 * Wipes out the s3io_images table to allow re-optimization.
 */
function s3io_table_truncate() {
	global $wpdb;
	$wpdb->query( "TRUNCATE TABLE $wpdb->s3io_images" );
}

/**
 * Displays 50 records from the auxiliary images table.
 */
function s3io_table() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that an authorized user has called function.
	if ( empty( $_REQUEST['s3io_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) ) {
		wp_die( esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) );
	}
	if ( empty( $_POST['s3io_offset'] ) ) {
		$_POST['s3io_offset'] = 0;
	}
	global $wpdb;
	$already_optimized = $wpdb->get_results( $wpdb->prepare( "SELECT id,bucket,path,results,image_size FROM $wpdb->s3io_images WHERE image_size IS NOT NULL ORDER BY id DESC LIMIT %d,50", 50 * (int) $_POST['s3io_offset'] ), ARRAY_A );
	echo '<br /><table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>' . esc_html__( 'Bucket', 's3-image-optimizer' ) . '</th><th>' . esc_html__( 'Filename', 's3-image-optimizer' ) . '</th><th>' . esc_html__( 'Image Optimizer', 's3-image-optimizer' ) . '</th></tr></thead>';
	$alternate = true;
	foreach ( $already_optimized as $optimized_image ) {
		$file_size = size_format( $optimized_image['image_size'], 2 );
		$file_size = str_replace( '.00 B ', ' B', $file_size );
		?>
		<tr<?php echo ( $alternate ? " class='alternate'" : '' ); ?> id="s3io-image-<?php echo (int) $optimized_image['id']; ?>">
			<td class='title'><?php echo esc_html( $optimized_image['bucket'] ); ?></td>
			<td class='title'>...<?php echo esc_html( $optimized_image['path'] ); ?></td>
			<td>
				<?php
				/* translators: %s: size of image, in bytes */
				echo esc_html( $optimized_image['results'] ) . ' <br>' . sprintf( esc_html__( 'Image Size: %s', 's3-image-optimizer' ), (int) $file_size );
				?>
				<br><a class="removeimage" onclick="s3ioRemoveImage( <?php echo (int) $optimized_image['id']; ?> )"><?php esc_html_e( 'Remove from table', 's3-image-optimizer' ); ?></a>
			</td>
		</tr>
		<?php
		$alternate = ! $alternate;
	}
	echo '</table>';
	die();
}

/**
 * Removes an image from the auxiliary images table.
 */
function s3io_table_remove() {
	// Verify that an authorized user has called function.
	if ( empty( $_REQUEST['s3io_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) ) {
		wp_die( esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) );
	}
	if ( empty( $_POST['s3io_image_id'] ) ) {
		echo '0';
		die;
	}
	global $wpdb;
	if ( $wpdb->delete( $wpdb->s3io_images, array( 'id' => (int) $_POST['s3io_image_id'] ) ) ) {
		echo '1';
	}
	die();
}

/**
 * Update a record in the database after optimization.
 *
 * @param string $path The location of the file.
 * @param int    $opt_size The filesize of the optimized image.
 * @param int    $orig_size The original filesize of the image.
 * @param string $results_msg The human-readable result of optimization.
 * @param int    $id The ID of the db record which we are about to update. Optional. Default to false.
 * @param string $bucket The name of the bucket where the image is located. Optional. Default to empty string.
 * @return string Result of the optimization.
 */
function s3io_table_update( $path, $opt_size, $orig_size, $results_msg, $id = false, $bucket = '' ) {
	global $wpdb;

	$opt_size  = (int) $opt_size;
	$orig_size = (int) $orig_size;
	$id        = $id ? (int) $id : (bool) $id;

	if ( $opt_size >= $orig_size ) {
		$results_msg = __( 'No savings', 's3-image-optimizer' );
		s3io_debug_message( 's3io: no savings' );
		if ( $id ) {
			s3io_debug_message( "s3io: looking for $id" );
			$optimized_query = $wpdb->get_row( $wpdb->prepare( "SELECT id,orig_size,results,path FROM $wpdb->s3io_images WHERE id = %d", $id ), ARRAY_A );
			if ( $optimized_query && ! empty( $optimized_query['results'] ) ) {
				s3io_debug_message( "s3io: found already optimized $id" );
				return $optimized_query['results'];
			}
			// Otherwise we need to store some stuff.
			// Store info on the current image for future reference.
			$updated = $wpdb->update(
				$wpdb->s3io_images,
				array(
					'image_size' => $opt_size,
					'results'    => $results_msg,
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
				s3io_debug_message( "s3io: updated $id" );
				$wpdb->flush();
				return $results_msg;
			}
		}
	} else {
		// Calculate how much space was saved.
		$savings = $orig_size - $opt_size;
		// Convert it to human readable format.
		$savings_str = size_format( $savings, 1 );
		// Replace spaces and extra decimals with proper html entity encoding.
		$savings_str = str_replace( '.0 B ', ' B', $savings_str );
		$savings_str = str_replace( ' ', '&nbsp;', $savings_str );
		// Determine the percentage savings.
		$percent = 100 - ( 100 * ( $opt_size / $orig_size ) );
		// Use the percentage and the savings size to output a nice message to the user.
		$results_msg = sprintf(
			/* translators: 1: percentage 2: space saved in bytes */
			esc_html__( 'Reduced by %1$01.1f%% (%2$s)', 's3-image-optimizer' ),
			$percent,
			$savings_str
		);
		if ( $id ) {
			s3io_debug_message( "s3io: updating $id" );
			// Store info on the current image for future reference.
			$updated = $wpdb->update(
				$wpdb->s3io_images,
				array(
					'image_size' => $opt_size,
					'results'    => $results_msg,
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
				s3io_debug_message( "s3io: updated $id" );
				$wpdb->flush();
				return $results_msg;
			}
		}
	}
	s3io_debug_message( "s3io: falling back to search by $path" );
	$optimized_query = $wpdb->get_results( $wpdb->prepare( "SELECT id,orig_size,results,path FROM $wpdb->s3io_images WHERE path = %s", $path ), ARRAY_A );
	if ( ! empty( $optimized_query ) ) {
		s3io_debug_message( 's3io: found results by path, checking...' );
		foreach ( $optimized_query as $image ) {
			s3io_debug_message( $image['path'] );
			if ( $image['path'] === $path ) {
				s3io_debug_message( 'found a match' );
				$already_optimized = $image;
			}
		}
	}
	if ( ! empty( $already_optimized['results'] ) && $opt_size === $orig_size ) {
		s3io_debug_message( 'returning results without update' );
		return $already_optimized['results'];
	}
	// Store info on the current image for future reference.
	$updated = $wpdb->update(
		$wpdb->s3io_images,
		array(
			'image_size' => $opt_size,
			'results'    => $results_msg,
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
		s3io_debug_message( "updated results for $path" );
		$wpdb->flush();
		return $results_msg;
	}
	s3io_debug_message( 'no existing records found, inserting new one' );
	$inserted = $wpdb->insert(
		$wpdb->s3io_images,
		array(
			'bucket'     => $bucket,
			'path'       => $path,
			'results'    => $results_msg,
			'image_size' => $opt_size,
			'orig_size'  => $orig_size,
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
		s3io_debug_message( 'successful INSERT' );
	}
	$wpdb->flush();
	return $results_msg;
}

/**
 * Called by javascript to initialize the bulk output.
 */
function s3io_bulk_init() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$output = array();
	// Verify that an authorized user has started the optimizer.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( empty( $_REQUEST['s3io_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! current_user_can( $permissions ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
	}
	session_write_close();
	// Store the time and number of images for later display.
	update_option( 's3io_last_run', array( time(), s3io_table_count_pending() ) );
	update_option( 's3io_resume', true, false );
	// Generate the WP spinner image for display.
	$loading_image = plugins_url( '/wpspin.gif', __FILE__ );
	global $wpdb;
	$image_record = $wpdb->get_row( "SELECT path FROM $wpdb->s3io_images WHERE image_size IS NULL LIMIT 1", ARRAY_A );
	// Let the user know that we are beginning.
	$output['results'] = '<p>' . esc_html__( 'Optimizing', 's3-image-optimizer' ) . ' <b>' . esc_html( $image_record['path'] ) . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
	echo wp_json_encode( $output );
	die();
}

/**
 * Called by javascript to process each image in the loop.
 *
 * @param bool $auto True if this is called from a non-JS process. Optional. Default false.
 * @param bool $verbose True to output extra information. Optional. Default false.
 */
function s3io_bulk_loop( $auto = false, $verbose = false ) {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$output = array();
	// verify that an authorized user has started the optimizer.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( empty( $_REQUEST['s3io_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! current_user_can( $permissions ) ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
	}
	if ( ! $auto ) {
		// Find out if our nonce is on it's last leg/tick.
		$tick = wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' );
		if ( 2 === (int) $tick ) {
			$output['new_nonce'] = wp_create_nonce( 's3io-bulk' );
		} else {
			$output['new_nonce'] = '';
		}
	}
	// Retrieve the time when the optimizer starts.
	$started = microtime( true );
	if ( ewww_image_optimizer_stl_check() && ini_get( 'max_execution_time' ) < 60 ) {
		set_time_limit( 0 );
	}
	global $wpdb;
	$image_record = $wpdb->get_row( "SELECT id,bucket,path,orig_size FROM $wpdb->s3io_images WHERE image_size IS NULL LIMIT 1", ARRAY_A );

	$upload_dir = s3io_make_upload_dir();
	if ( ! $upload_dir ) {
		die(
			wp_json_encode(
				array(
					'error' => esc_html__( 'Could not create the /s3io/ folder within the WordPress uploads folder, please adjust the permissions and try again.', 's3-image-optimizer' ),
				)
			)
		);
	}
	$upload_dir = trailingslashit( $upload_dir ) . sanitize_file_name( $image_record['bucket'] ) . '/';
	s3io_debug_message( "stashing files in $upload_dir" );
	if ( false !== strpos( $upload_dir, 's3://' ) ) {
		/* translators: %s: path to uploads directory */
		die( wp_json_encode( array( 'error' => sprintf( esc_html__( 'Received an unusable working directory: %s', 's3-image-optimizer' ), $upload_dir ) ) ) );
	}
	global $s3io_amazon_web_services;
	try {
		$client = $s3io_amazon_web_services->get_client();
	} catch ( Exception $e ) {
		die( wp_json_encode( array( 'error' => wp_kses_post( $e->getMessage() ) ) ) );
	}
	$filename = $upload_dir . $image_record['path'];
	$full_dir = dirname( $filename );
	if ( ! is_dir( $full_dir ) ) {
		wp_mkdir_p( $full_dir );
	}
	try {
		$fetch_result = $client->getObject(
			array(
				'Bucket' => $image_record['bucket'],
				'Key'    => $image_record['path'],
				'SaveAs' => $filename,
			)
		);
	} catch ( Exception $e ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::error( "Fetch failed for bucket: {$image_record['bucket']}, path: {$image_record['path']}, message:" . $e->getMessage() );
		} else {
			$output['error'] = esc_html( "Fetch failed for bucket: {$image_record['bucket']}, path: {$image_record['path']}, message:" . $e->getMessage() );
			echo wp_json_encode( $output );
		}
		die();
	}
	// Make sure EWWW IO doesn't skip images.
	global $ewww_force;
	$ewww_force = true;
	if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::line( "About to optimize $filename" );
	}
	// Do the optimization for the current image.
	$results = ewww_image_optimizer( $filename );
	if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::line( "Just optimized $filename, stay tuned..." );
	}
	$ewww_force  = false;
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		unlink( $filename );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::error( __( 'License Exceeded', 's3-image-optimizer' ) );
		} else {
			$output['error'] = esc_html__( 'License Exceeded', 's3-image-optimizer' );
			echo wp_json_encode( $output );
		}
		die();
	}
	$new_size = ewww_image_optimizer_filesize( $filename );
	if ( $new_size < $fetch_result['ContentLength'] ) {
		if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( "About to re-upload $filename" );
		}
		// Re-upload to S3.
		try {
			$client->putObject(
				array(
					'Bucket'       => $image_record['bucket'],
					'Key'          => $image_record['path'],
					'SourceFile'   => $filename,
					'ACL'          => 'public-read',
					'ContentType'  => $fetch_result['ContentType'],
					'CacheControl' => 'max-age=31536000',
					'Expires'      => gmdate( 'D, d M Y H:i:s O', time() + 31536000 ),
				)
			);
		} catch ( Exception $e ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::error( "Put failed for bucket: {$image_record['bucket']}, path: {$image_record['path']}, message:" . $e->getMessage() );
			} else {
				$output['error'] = wp_kses_post( "Put failed for bucket: {$image_record['bucket']}, path: {$image_record['path']}, message:" . $e->getMessage() );
				echo wp_json_encode( $output );
			}
			die();
		}
		if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( "Finished re-upload of $filename" );
		}
	}
	unlink( $filename );
	$webp_size = ewww_image_optimizer_filesize( $filename . '.webp' );
	if ( $webp_size ) {
		if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( "About to upload $filename.webp" );
		}
		// Re-upload to S3.
		try {
			$client->putObject(
				array(
					'Bucket'       => $image_record['bucket'],
					'Key'          => $image_record['path'] . '.webp',
					'SourceFile'   => $filename . '.webp',
					'ACL'          => 'public-read',
					'ContentType'  => 'image/webp',
					'CacheControl' => 'max-age=31536000',
					'Expires'      => gmdate( 'D, d M Y H:i:s O', time() + 31536000 ),
				)
			);
		} catch ( Exception $e ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::error( "Put failed for bucket: {$image_record['bucket']}, path: {$image_record['path']}.webp, message:" . $e->getMessage() );
			} else {
				$output['error'] = wp_kses_post( "Put failed for bucket: {$image_record['bucket']}, path: {$image_record['path']}.webp, message:" . $e->getMessage() );
				echo wp_json_encode( $output );
			}
			die();
		}
		if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( "Finished upload of $filename.webp" );
		}
		unlink( $filename . 'webp' );
	}
	s3io_table_update( $image_record['path'], $new_size, $fetch_result['ContentLength'], $results[1], $image_record['id'] );
	// Make sure ewww doesn't keep a record of these files.
	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->ewwwio_images WHERE path = %s", $filename ) );
	if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::line( 'Updated database records.' );
	}
	ewww_image_optimizer_debug_log();
	if ( ! $auto ) {
		// Output the path.
		$output['results']  = sprintf( '<p>' . esc_html__( 'Optimized image:', 's3-image-optimizer' ) . ' <strong>%s</strong><br>', esc_html( $image_record['path'] ) );
		$output['results'] .= $results[1] . '<br>';
		// Calculate how much time has elapsed since we started.
		$elapsed = microtime( true ) - $started;
		// Output how much time has elapsed since we started.
		/* translators: %s: time in seconds */
		$output['results'] .= sprintf( esc_html__( 'Elapsed: %s seconds', 's3-image-optimizer' ) . '</p>', number_format_i18n( $elapsed, 1 ) );

		// Lookup the next image to optimize.
		$image_record = $wpdb->get_row( "SELECT path FROM $wpdb->s3io_images WHERE image_size IS NULL LIMIT 1", ARRAY_A );
		if ( ! empty( $image_record ) ) {
			$loading_image       = plugins_url( '/wpspin.gif', __FILE__ );
			$output['next_file'] = '<p>' . esc_html__( 'Optimizing', 's3-image-optimizer' ) . ' <b>' . esc_html( $image_record['path'] ) . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
		}
		die( wp_json_encode( $output ) );
	} elseif ( defined( 'WP_CLI' ) && WP_CLI ) {
		$elapsed = microtime( true ) - $started;
		WP_CLI::line( __( 'Optimized image:', 's3-image-optimizer' ) . ' ' . $image_record['path'] );
		WP_CLI::line( str_replace( '<br>', "\n", $results[1] ) );
		/* translators: %s: time in seconds */
		WP_CLI::line( sprintf( __( 'Elapsed: %s seconds', 's3-image-optimizer' ) . '</p>', number_format_i18n( $elapsed, 2 ) ) );
	}
}

/**
 * Called by bulk process to cleanup after ourselves.
 *
 * @param bool $auto True if this is a wp-cli or automatic process (non JS). Optional. Default false.
 */
function s3io_bulk_cleanup( $auto = false ) {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that an authorized user has started the optimizer.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( empty( $_REQUEST['s3io_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-bulk' ) || ! current_user_can( $permissions ) ) ) {
		wp_die( esc_html__( 'Access denied.', 's3-image-optimizer' ) );
	}
	$stored_last = get_option( 's3io_last_run' );
	update_option( 's3io_last_run', array( time(), $stored_last[1] ) );
	update_option( 's3io_resume', '', false );
	if ( ! $auto ) {
		echo '<p><b>' . esc_html__( 'Finished', 's3-image-optimizer' ) . '</b></p>';
		die();
	}
}

/**
 * Run optimization for an image from the bulk URL process.
 */
function s3io_url_loop() {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$output = array();
	// Verify that an authorized user has started the optimizer.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( empty( $_REQUEST['s3io_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['s3io_wpnonce'] ), 's3io-url' ) || ! current_user_can( $permissions ) ) {
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 's3-image-optimizer' ) ) ) );
	}
	$started = microtime( true );
	if ( ewww_image_optimizer_stl_check() && ini_get( 'max_execution_time' ) < 60 ) {
		set_time_limit( 0 );
	}
	if ( empty( $_REQUEST['s3io_url'] ) ) {
		$output['error'] = esc_html__( 'No URL supplied', 's3-image-optimizer' );
		echo wp_json_encode( $output );
		die();
	}
	$url = esc_url_raw( wp_unslash( $_REQUEST['s3io_url'] ) );
	if ( ! empty( $url ) ) {
		$url_args = s3io_get_args_from_url( $url );
	}
	if ( empty( $url ) || empty( $url_args ) ) {
		$output['error'] = esc_html__( 'Invalid URL supplied', 's3-image-optimizer' );
		echo wp_json_encode( $output );
		die();
	}
	$url_args['path'] = ltrim( $url_args['path'], '/' );

	$upload_dir = s3io_make_upload_dir();
	if ( ! $upload_dir ) {
		die(
			wp_json_encode(
				array(
					'error' => esc_html__( 'Could not create the /s3io/ folder within the WordPress uploads folder, please adjust the permissions and try again.', 's3-image-optimizer' ),
				)
			)
		);
	}
	$upload_dir = trailingslashit( $upload_dir ) . sanitize_file_name( $url_args['bucket'] ) . '/';
	s3io_debug_message( "stashing files in $upload_dir" );
	if ( false !== strpos( $upload_dir, 's3://' ) ) {
		/* translators: %s: path to uploads directory */
		die( wp_json_encode( array( 'error' => sprintf( esc_html__( 'Received an unusable working directory: %s', 's3-image-optimizer' ), $upload_dir ) ) ) );
	}
	global $s3io_amazon_web_services;
	try {
		$client = $s3io_amazon_web_services->get_client();
	} catch ( Exception $e ) {
		die( wp_json_encode( array( 'error' => wp_kses_post( $e->getMessage() ) ) ) );
	}
	try {
		$location = $client->getBucketLocation(
			array(
				'Bucket' => $url_args['bucket'],
			)
		);
	} catch ( Exception $e ) {
		$location = new WP_Error( 'exception', $e->getMessage() );
	}
	$filename = $upload_dir . $url_args['path'];
	$full_dir = dirname( $filename );
	if ( ! is_dir( $full_dir ) ) {
		wp_mkdir_p( $full_dir );
	}
	$fetch_result = $client->getObject(
		array(
			'Bucket' => $url_args['bucket'],
			'Key'    => $url_args['path'],
			'SaveAs' => $filename,
		)
	);
	// Make sure EWWW IO doesn't skip images.
	global $ewww_force;
	$ewww_force = true;
	// Do the optimization for the current image.
	$results     = ewww_image_optimizer( $filename );
	$ewww_force  = false;
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		unlink( $filename );
		$output['error'] = esc_html__( 'License Exceeded', 's3-image-optimizer' );
		echo wp_json_encode( $output );
		die();
	}
	$new_size = filesize( $filename );
	if ( $new_size < $fetch_result['ContentLength'] ) {
		// Re-upload to S3.
		$client->putObject(
			array(
				'Bucket'       => $url_args['bucket'],
				'Key'          => $url_args['path'],
				'SourceFile'   => $filename,
				'ACL'          => 'public-read',
				'ContentType'  => $fetch_result['ContentType'],
				'CacheControl' => 'max-age=31536000',
				'Expires'      => gmdate( 'D, d M Y H:i:s O', time() + 31536000 ),
			)
		);
	}
	unlink( $filename );
	$webp_size = ewww_image_optimizer_filesize( $filename . '.webp' );
	if ( $webp_size ) {
		if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( "About to upload $filename.webp" );
		}
		// Upload to S3.
		try {
			$client->putObject(
				array(
					'Bucket'       => $url_args['bucket'],
					'Key'          => $url_args['path'] . '.webp',
					'SourceFile'   => $filename . '.webp',
					'ACL'          => 'public-read',
					'ContentType'  => 'image/webp',
					'CacheControl' => 'max-age=31536000',
					'Expires'      => gmdate( 'D, d M Y H:i:s O', time() + 31536000 ),
				)
			);
		} catch ( Exception $e ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::error( "Put failed for bucket: {$image_record['bucket']}, path: {$image_record['path']}.webp, message:" . $e->getMessage() );
			} else {
				$output['error'] = wp_kses_post( "Put failed for bucket: {$image_record['bucket']}, path: {$image_record['path']}.webp, message:" . $e->getMessage() );
				echo wp_json_encode( $output );
			}
			die();
		}
		if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( "Finished upload of $filename.webp" );
		}
		unlink( $filename . 'webp' );
	}
	s3io_table_update( $url_args['path'], $new_size, $fetch_result['ContentLength'], $results[1], false, $url_args['bucket'] );
	// Make sure ewww doesn't keep a record of these files.
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->ewwwio_images WHERE path = %s", $filename ) );
	ewww_image_optimizer_debug_log();
	$elapsed            = microtime( true ) - $started;
	$output['results']  = sprintf( '<p>' . esc_html__( 'Optimized image:', 's3-image-optimizer' ) . ' <strong>%s</strong><br>', esc_html( $url_args['path'] ) );
	$output['results'] .= $results[1] . '<br>';
	/* translators: %f: time in seconds */
	$output['results'] .= sprintf( esc_html__( 'Elapsed: %.3f seconds', 's3-image-optimizer' ) . '</p>', $elapsed );

	die( wp_json_encode( $output ) );
}

/**
 * Takes an S3/Spaces URL and gets the bucket and path for an object.
 *
 * @param string $url The url of the image on S3/Spaces.
 * @return array The bucket and path of the object.
 */
function s3io_get_args_from_url( $url ) {
	s3io_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$url     = urldecode( $url );
	$urlinfo = parse_url( $url );
	if ( ! $urlinfo ) {
		s3io_debug_message( "failed to parse $url" );
		return false;
	}
	if ( defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) && S3_IMAGE_OPTIMIZER_BUCKET ) {
		if ( strpos( $urlinfo['host'], S3_IMAGE_OPTIMIZER_BUCKET ) !== false ) {
			s3io_debug_message( 'found ' . S3_IMAGE_OPTIMIZER_BUCKET . ' and ' . $urlinfo['path'] );
			return array(
				'bucket' => S3_IMAGE_OPTIMIZER_BUCKET,
				'path'   => $urlinfo['path'],
			);
		}
		if ( strpos( $urlinfo['path'], S3_IMAGE_OPTIMIZER_BUCKET ) !== false ) {
			$path = str_replace( '/' . S3_IMAGE_OPTIMIZER_BUCKET, '', $urlinfo['path'] );
			s3io_debug_message( 'found ' . S3_IMAGE_OPTIMIZER_BUCKET . ' and ' . $path );
			return array(
				'bucket' => S3_IMAGE_OPTIMIZER_BUCKET,
				'path'   => $path,
			);
		}
	}
	global $s3io_amazon_web_services;
	try {
		$client = $s3io_amazon_web_services->get_client();
	} catch ( Exception $e ) {
		s3io_debug_message( 'unable to initialize AWS client lib' );
		return false;
	}
	try {
		$buckets = $client->listBuckets();
	} catch ( Exception $e ) {
		$buckets = new WP_Error( 'exception', $e->getMessage() );
	}

	// If retrieving buckets from AWS failed, then we use the bucketlist option.
	if ( is_wp_error( $buckets ) ) {
		$bucket_list = get_option( 's3io_bucketlist' );
	} else {
		$bucket_list = array();
		foreach ( $buckets['Buckets'] as $aws_bucket ) {
			$bucket_list[] = $aws_bucket['Name'];
		}
	}

	// If we don't have a list of buckets, we can't do much more here.
	if ( empty( $bucket_list ) || ! is_array( $bucket_list ) ) {
		s3io_debug_message( 'could not retrieve list of buckets' );
		return false;
	}

	foreach ( $bucket_list as $aws_bucket ) {
		if ( strpos( $urlinfo['host'], $aws_bucket ) !== false ) {
			s3io_debug_message( 'found ' . $aws_bucket . ' and ' . $urlinfo['path'] );
			return array(
				'bucket' => $aws_bucket,
				'path'   => $urlinfo['path'],
			);
		}
		if ( strpos( $urlinfo['path'], $aws_bucket ) !== false ) {
			$path = str_replace( '/' . $aws_bucket, '', $urlinfo['path'] );
			s3io_debug_message( 'found ' . $aws_bucket . ' and ' . $path );
			return array(
				'bucket' => $aws_bucket,
				'path'   => $path,
			);
		}
	}

	// Otherwise, we must have a custom domain, so lets do a quick search for the attachment in all buckets.
	// Doing it in a separate foreach, in case there are performance implications of switching the region in accounts with lots of buckets.
	$key = ltrim( $urlinfo['path'], '/' );
	foreach ( $bucket_list as $aws_bucket ) {
		try {
			$location = $client->getBucketLocation(
				array(
					'Bucket' => $aws_bucket,
				)
			);
		} catch ( Exception $e ) {
			$location = new WP_Error( 'exception', $e->getMessage() );
		}
		try {
			$exists = $client->headObject(
				array(
					'Bucket' => $aws_bucket,
					'Key'    => $key,
				)
			);
		} catch ( Exception $e ) {
			// Do nothing, because we don't have a way to throw errors here.
		}
		if ( $exists ) {
			s3io_debug_message( 'found ' . $aws_bucket . ' and ' . $urlinfo['path'] );
			return array(
				'bucket' => $aws_bucket,
				'path'   => $urlinfo['path'],
			);
		}
	}
	s3io_debug_message( "failed to find $url" );
	return false;
}

/**
 * Send a message to the debug memory buffer (global).
 *
 * @param string $message A debugging message to add to the buffer.
 */
function s3io_debug_message( $message ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::debug( $message );
		return;
	}
	if ( function_exists( 'ewwwio_debug_message' ) ) {
		ewwwio_debug_message( $message );
	}
}

add_action( 'admin_enqueue_scripts', 's3io_bulk_script' );
add_action( 'admin_enqueue_scripts', 's3io_url_script' );
add_action( 'admin_action_s3io_remove_aws_keys', 's3io_remove_aws_keys' );
add_action( 'wp_ajax_s3io_query_table', 's3io_table' );
add_action( 'wp_ajax_s3io_table_count', 's3io_table_count_optimized' );
add_action( 'wp_ajax_s3io_table_remove', 's3io_table_remove' );
add_action( 'wp_ajax_s3io_image_scan', 's3io_image_scan' );
add_action( 'wp_ajax_s3io_bulk_init', 's3io_bulk_init' );
add_action( 'wp_ajax_s3io_bulk_loop', 's3io_bulk_loop' );
add_action( 'wp_ajax_s3io_bulk_cleanup', 's3io_bulk_cleanup' );
add_action( 'wp_ajax_s3io_url_images_loop', 's3io_url_loop' );
