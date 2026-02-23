<?php
/**
 * Low-level plugin class.
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
 * Initializes plugin and base class, sets defaults, checks compatibility, etc.
 */
final class Plugin extends Base {
	/* Singleton */

	use Utils;

	/**
	 * The one and only true S3IO\Plugin
	 *
	 * @var object|S3IO\Plugin $instance
	 */
	private static $instance;

	/**
	 * Amazon Web Services object.
	 *
	 * @var object|S3IO\Amazon_Web_Services $amazon_web_services
	 */
	public $amazon_web_services;

	/**
	 * Bulk optimizer object.
	 *
	 * @var object|S3IO\Bulk $bulk
	 */
	public $bulk;

	/**
	 * Tools object.
	 *
	 * @var object|S3IO\Tools $tools
	 */
	public $tools;

	/**
	 * A list of errors encountered during S3 operations.
	 *
	 * @var array $errors
	 */
	public $errors;

	/**
	 * Main \S3IO\Plugin instance.
	 *
	 * Ensures that only one instance of \S3IO\Plugin exists in memory at any given time.
	 *
	 * @static
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();

			if ( self::$instance->requirements_met() ) {
				global $wpdb;
				if ( ! isset( $wpdb->s3io_images ) ) {
					$wpdb->s3io_images = $wpdb->prefix . 's3io_images';
				}
				self::$instance->requires();
				self::$instance->load_children();
				self::$instance->register_hooks();
			}
		}

		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object. Therefore, we don't want the object to be cloned.
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __METHOD__, esc_html__( 'Cannot clone core object.', 's3-image-optimizer' ), esc_html( S3IO_VERSION ) );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @param array $data The data to unserialize.
	 * @return array The original data, unaltered. We don't support unserializing.
	 */
	public function __unserialize( $data ) {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __METHOD__, esc_html__( 'Cannot unserialize (wakeup) the core object.', 's3-image-optimizer' ), esc_html( S3IO_VERSION ) );
		return $data;
	}

	/**
	 * Make sure all requirements are met.
	 *
	 * @access private
	 * @return bool True if EWWW IO is present and up to date, along with necessary PHP and WP versions.
	 */
	private function requirements_met() {
		if ( ! function_exists( 'ewww_image_optimizer' ) ) {
			\add_action( 'network_admin_notices', array( $this, 'missing_ewww_plugin' ) );
			\add_action( 'admin_notices', array( $this, 'missing_ewww_plugin' ) );
			return false;
		}
		if (
			! function_exists( 'ewwwio' ) ||
			! function_exists( 'ewww_image_optimizer_filesize' ) ||
			! function_exists( 'ewww_image_optimizer_get_webp_path' )
		) {
			\add_action( 'network_admin_notices', array( $this, 'ewww_plugin_outdated' ) );
			\add_action( 'admin_notices', array( $this, 'ewww_plugin_outdated' ) );
			return false;
		}
		if ( ! $this->php_supported() || ! $this->wp_supported() ) {
			return false;
		}
		return true;
	}

	/**
	 * Make sure we are on a supported version of PHP.
	 *
	 * @access private
	 */
	private function php_supported() {
		if ( ! defined( 'PHP_VERSION_ID' ) || PHP_VERSION_ID < 80100 ) {
			add_action( 'network_admin_notices', array( $this, 'unsupported_php_notice' ) );
			add_action( 'admin_notices', array( $this, 'unsupported_php_notice' ) );
			return false;
		}
		return true;
	}

	/**
	 * Make sure we are on a supported version of WP.
	 *
	 * @access private
	 */
	private function wp_supported() {
		global $wp_version;
		if ( version_compare( $wp_version, '6.6' ) >= 0 ) {
			return true;
		}
		add_action( 'network_admin_notices', array( $this, 'unsupported_wp_notice' ) );
		add_action( 'admin_notices', array( $this, 'unsupported_wp_notice' ) );
		return false;
	}

	/**
	 * Display a notice that the PHP version is too old.
	 */
	public function unsupported_php_notice() {
		echo '<div id="s3io-warning-php" class="notice notice-error"><p><a href="https://docs.ewww.io/article/55-upgrading-php" target="_blank">' . esc_html__( 'For performance and security reasons, S3 Image Optimizer requires PHP 8.1 or greater. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 's3-image-optimizer' ) . '</a></p></div>';
	}

	/**
	 * Display a notice that the WP version is too old.
	 */
	public function unsupported_wp_notice() {
		echo '<div id="s3io-warning-wp" class="notice notice-error"><p>' . esc_html__( 'S3 Image Optimizer requires WordPress 6.6 or greater, please update your website.', 's3-image-optimizer' ) . '</p></div>';
	}

	/**
	 * Let the user know that they need the EWWW IO plugin before S3 IO can do anything.
	 */
	public function missing_ewww_plugin() {
		?>
		<div id='s3io-error-ewww' class='error'>
			<p>
				<?php /* translators: %s: EWWW Image Optimizer (link) */ ?>
				<?php printf( esc_html__( 'Could not detect the %s plugin, please install and configure it first.', 's3-image-optimizer' ), '<a href="' . esc_url( admin_url( 'plugin-install.php?s=ewww+image+optimizer&tab=search&type=term' ) ) . '">EWWW Image Optimizer</a>' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Let the user know that they need to update the EWWW IO plugin before S3 IO can do anything.
	 */
	public function ewww_plugin_outdated() {
		?>
		<div id='s3io-error-ewww' class='error'>
			<p>
				<?php esc_html_e( 'Please update EWWW Image Optimizer to the latest version.', 's3-image-optimizer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 */
	private function requires() {
		require_once S3IO_PLUGIN_DIR . 'classes/class-bulk.php';
		require_once S3IO_PLUGIN_DIR . 'classes/class-tools.php';
		require_once S3IO_PLUGIN_DIR . 'classes/class-amazon-web-services.php';
		require_once S3IO_PLUGIN_DIR . 'vendor/Aws3/aws-autoloader.php';
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once S3IO_PLUGIN_DIR . 'classes/class-s3io-cli.php';
		}
	}

	/**
	 * Load plugin classes for various functions.
	 */
	private function load_children() {
		self::$instance->amazon_web_services = new Amazon_Web_Services();
		self::$instance->bulk                = new Bulk();
		self::$instance->tools               = new Tools();
	}

	/**
	 * Setup hooks for tools page.
	 */
	public function register_hooks() {
		add_action( 'admin_action_s3io_remove_aws_keys', array( $this, 'remove_aws_keys' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 60 );
		add_filter( 'aws_get_client_args', array( $this, 'addv4_args' ), 8 );
		add_filter( 'aws_get_client_args', array( $this, 'dospaces_args' ) );
	}

	/**
	 * Register settings and perform any upgrades during admin_init hook.
	 */
	public function admin_init() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		register_setting( 's3io_options', 's3io_verion' );
		register_setting( 's3io_options', 's3io_bucketlist', array( $this, 'bucketlist_sanitize' ) );
		register_setting( 's3io_options', 's3io_dospaces', 'trim' );
		register_setting( 's3io_options', 's3io_aws_access_key_id', 'trim' );
		register_setting( 's3io_options', 's3io_aws_secret_access_key', 'trim' );
		if ( get_option( 's3io_version' ) < S3IO_VERSION ) {
			$this->install_table();
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
	}

	/**
	 * Install the s3io_images table into the db for tracking image optimization.
	 */
	protected function install_table() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;

		// See if the path column exists, and what collation it uses to determine the column index size.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->s3io_images'" ) === $wpdb->s3io_images ) {
			$current_collate = $wpdb->get_results( "SHOW FULL COLUMNS FROM $wpdb->s3io_images", ARRAY_A );
			if ( ! empty( $current_collate[1]['Field'] ) && 'path' === $current_collate[1]['Field'] && strpos( $current_collate[1]['Collation'], 'utf8mb4' ) === false ) {
				$path_index_size = 255;
			}
		}
		$charset_collate = $wpdb->get_charset_collate();

		if ( empty( $path_index_size ) && str_contains( $charset_collate, 'utf8mb4' ) ) {
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
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 's3io_optimize_urls', '', '', 'no' );
	}

	/**
	 * Adjusts the signature/version and region from the defaults.
	 *
	 * @param array $args A list of arguments sent to the AWS SDK.
	 * @return array The arguments for the AWS connection, possibly modified.
	 */
	public function addv4_args( $args ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$args['signature'] = 'v4';
		$args['region']    = 'us-east-1';
		$args['version']   = '2006-03-01';
		if ( defined( 'S3_IMAGE_OPTIMIZER_REGION' ) && S3_IMAGE_OPTIMIZER_REGION ) {
			$args['region'] = S3_IMAGE_OPTIMIZER_REGION;
		}
		if ( defined( 'S3_IMAGE_OPTIMIZER_ENDPOINT' ) && S3_IMAGE_OPTIMIZER_ENDPOINT ) {
			$args['endpoint'] = S3_IMAGE_OPTIMIZER_ENDPOINT;
		}
		if ( defined( 'S3_IMAGE_OPTIMIZER_PATH_STYLE' ) && S3_IMAGE_OPTIMIZER_PATH_STYLE ) {
			$args['use_path_style_endpoint'] = true;
		}
		return $args;
	}

	/**
	 * Adjusts the endpoint and region for DO Spaces connection.
	 *
	 * @param array $args A list of arguments sent to the AWS SDK.
	 * @return array The arguments for the AWS connection, possibly modified.
	 */
	public function dospaces_args( $args ) {
		if ( get_option( 's3io_dospaces' ) || defined( 'S3IO_DOSPACES' ) ) {
			$region           = defined( 'S3IO_DOSPACES' ) ? S3IO_DOSPACES : get_option( 's3io_dospaces' );
			$args['endpoint'] = 'https://' . $region . '.digitaloceanspaces.com';
			$args['region']   = $region;
		}
		return $args;
	}

	/**
	 * Setup the admin menu items for the plugin.
	 */
	public function admin_menu() {
		if ( ! function_exists( 'ewww_image_optimizer' ) ) {
			return;
		}
		// Add options page to the settings menu.
		add_options_page(
			esc_html__( 'S3 Image Optimizer', 's3-image-optimizer' ), // Page title.
			esc_html__( 'S3 Image Optimizer', 's3-image-optimizer' ), // Menu title.
			'manage_options',                                         // Capability required.
			's3io-options',                                           // Slug.
			array( $this, 'options_page' )                            // Function to call.
		);
	}

	/**
	 * Display settings page for the plugin.
	 */
	public function options_page() {
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
		<?php if ( $this->amazon_web_services->needs_access_keys() ) : ?>
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
					$client = $this->amazon_web_services->get_client();
				} catch ( AwsException | S3Exception | Exception $e ) {
					echo '</table><p>' . wp_kses_post( $this->format_aws_exception( $e->getMessage() ) ) . '</p>';
					echo "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 's3-image-optimizer' ) . "' /></p>";
					return;
				}
				if ( is_wp_error( $client ) ) {
					echo '</table><p>' . wp_kses_post( $client->get_error_message() ) . '</p>';
					echo "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 's3-image-optimizer' ) . "' /></p>";
					return;
				}
				try {
					$buckets = $client->listBuckets();
				} catch ( AwsException | S3Exception | Exception $e ) {
					$buckets = new \WP_Error( 's3io_exception', $this->format_aws_exception( $e->getMessage() ) );
				}
				?>
				<tr>
					<th><label for="s3io_bucketlist"><?php esc_html_e( 'Buckets to optimize', 's3-image-optimizer' ); ?></label></th>
					<td>
				<?php if ( defined( 'S3_IMAGE_OPTIMIZER_BUCKET' ) && S3_IMAGE_OPTIMIZER_BUCKET ) : ?>
						<p>
							<?php esc_html_e( 'You have currently defined the bucket constant (S3_IMAGE_OPTIMIZER_BUCKET):', 's3-image-optimizer' ); ?>
							<pre><?php echo esc_html( S3_IMAGE_OPTIMIZER_BUCKET ); ?></pre>
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
					<th><?php esc_html_e( 'Custom endpoint', 's3-image-optimizer' ); ?></th>
					<td>
				<?php if ( defined( 'S3_IMAGE_OPTIMIZER_ENDPOINT' ) && S3_IMAGE_OPTIMIZER_ENDPOINT ) : ?>
						<?php /* translators: %s: S3-compatible endpoint/URL */ ?>
						<?php printf( esc_html__( 'Using custom S3-compatible endpoint: %s', 's3-image-optimizer' ), '<pre>' . esc_url( S3_IMAGE_OPTIMIZER_ENDPOINT ) . '</pre>' ); ?>
					<?php if ( defined( 'S3_IMAGE_OPTIMIZER_REGION' ) && S3_IMAGE_OPTIMIZER_REGION ) : ?>
						<?php /* translators: %s: S3 region, like 'nyc3' */ ?>
						<?php printf( esc_html__( 'User-defined region: %s', 's3-image-optimizer' ), '<pre>' . esc_html( S3_IMAGE_OPTIMIZER_REGION ) . '</pre>' ); ?>
					<?php endif; ?>
				<?php else : ?>
						<?php esc_html_e( 'Set the S3_IMAGE_OPTIMIZER_ENDPOINT and S3_IMAGE_OPTIMIZER_REGION constants to use other S3-compatible providers.', 's3-image-optimizer' ); ?>
				<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Sub-folders', 's3-image-optimizer' ); ?></th>
					<td>
				<?php if ( defined( 'S3_IMAGE_OPTIMIZER_FOLDER' ) && S3_IMAGE_OPTIMIZER_FOLDER ) : ?>
						<?php /* translators: %s: folder or path in S3 bucket */ ?>
						<?php printf( esc_html__( 'Optimization has been restricted to this folder: %s', 's3-image-optimizer' ), '<pre>' . esc_html( ltrim( S3_IMAGE_OPTIMIZER_FOLDER, '/' ) ) . '</pre>' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'You may set the S3_IMAGE_OPTIMIZER_FOLDER constant to restrict optimization to a specific sub-directory of the bucket(s) above.', 's3-image-optimizer' ); ?>
				<?php endif; ?>
					</td>
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
	public function remove_aws_keys() {
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
	public function bucketlist_sanitize( $input ) {
		if ( empty( $input ) ) {
			return '';
		}
		try {
			$client = $this->amazon_web_services->get_client();
		} catch ( AwsException | S3Exception | Exception $e ) {
			return '';
		}
		// If there is an exception, we don't return, as the user may not have permission to list buckets.
		// In such a case we do manual sanitation of the bucket names.
		try {
			$buckets = $client->listBuckets();
		} catch ( AwsException | S3Exception | Exception $e ) {
			$buckets = new \WP_Error( 's3io_exception', $this->format_aws_exception( $e->getMessage() ) );
			$this->debug_message( 'error sanitizing bucket list: ' . $e->getMessage() );
		}
		$bucket_array = array();
		if ( is_array( $input ) ) {
			$input_buckets = $input;
		} elseif ( is_string( $input ) ) {
			$input_buckets = explode( "\n", $input );
		} else {
			// If we don't have an erray or string, then don't save it.
			return '';
		}
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
}