<?php
/**
 * Class file for Amazon_Web_Services
 *
 * Implements AWS client connection via AWS PHP library.
 *
 * @package S3_Image_optimizer
 */

namespace S3IO;

use S3IO\Aws3\Aws\S3\S3Client;
use S3IO\Aws3\Aws\Sdk;

use Exception;

/**
 * Gathers access keys and connects to AWS.
 */
class Amazon_Web_Services {

	/**
	 * The client object that stores the connection details.
	 *
	 * @access private
	 * @var object $client
	 */
	private $client;

	/**
	 * The default region for S3 connections.
	 *
	 * @access protected
	 * @var string $default_region
	 */
	protected $default_region = 'us-east-1';

	/**
	 * Whether or not IAM access keys are needed.
	 *
	 * Keys are needed if we are not using EC2 roles or not defined/set yet.
	 *
	 * @return bool
	 */
	public function needs_access_keys() {
		if ( $this->use_ec2_iam_roles() ) {
			return false;
		}

		return ! $this->are_access_keys_set();
	}

	/**
	 * Check if both access key id & secret are present.
	 *
	 * @return bool
	 */
	function are_access_keys_set() {
		return $this->get_access_key_id() && $this->get_secret_access_key();
	}

	/**
	 * Get the AWS key from a constant or the settings.
	 *
	 * Falls back to settings only if neither constant is defined.
	 *
	 * @return string
	 */
	public function get_access_key_id() {
		if ( $this->is_any_access_key_constant_defined() ) {
			$constant = $this->access_key_id_constant();

			return $constant ? constant( $constant ) : '';
		}

		return get_option( 's3io_aws_access_key_id' );
	}

	/**
	 * Get the AWS secret from a constant or the settings
	 *
	 * Falls back to settings only if neither constant is defined.
	 *
	 * @return string
	 */
	public function get_secret_access_key() {
		if ( $this->is_any_access_key_constant_defined() ) {
			$constant = $this->secret_access_key_constant();

			return $constant ? constant( $constant ) : '';
		}

		return get_option( 's3io_aws_secret_access_key' );
	}

	/**
	 * Check if any access key (id or secret, prefixed or not) is defined.
	 *
	 * @return bool
	 */
	public static function is_any_access_key_constant_defined() {
		return static::access_key_id_constant() || static::secret_access_key_constant();
	}

	/**
	 * Allows the AWS client factory to use the IAM role for EC2 instances
	 * instead of key/secret for credentials
	 * http://docs.aws.amazon.com/aws-sdk-php/guide/latest/credentials.html#instance-profile-credentials
	 *
	 * @return bool
	 */
	public function use_ec2_iam_roles() {
		$constant = $this->use_ec2_iam_role_constant();

		return $constant && constant( $constant );
	}

	/**
	 * Get the constant used to define the aws access key id.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function access_key_id_constant() {
		return self::get_first_defined_constant(
			array(
				'AS3CF_AWS_ACCESS_KEY_ID',
				'DBI_AWS_ACCESS_KEY_ID',
				'AWS_ACCESS_KEY_ID',
			)
		);
	}

	/**
	 * Get the constant used to define the aws secret access key.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function secret_access_key_constant() {
		return self::get_first_defined_constant(
			array(
				'AS3CF_AWS_SECRET_ACCESS_KEY',
				'DBI_AWS_SECRET_ACCESS_KEY',
				'AWS_SECRET_ACCESS_KEY',
			)
		);
	}

	/**
	 * Get the constant used to enable the use of EC2 IAM roles.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function use_ec2_iam_role_constant() {
		return self::get_first_defined_constant(
			array(
				'AS3CF_AWS_USE_EC2_IAM_ROLE',
				'DBI_AWS_USE_EC2_IAM_ROLE',
				'AWS_USE_EC2_IAM_ROLE',
			)
		);
	}

	/**
	 * Instantiate a new AWS service client for the AWS SDK
	 * using the defined AWS key and secret
	 *
	 * @return Aws An AWS object with an established connection.
	 * @throws Exception AWS configuration/connection error.
	 */
	function get_client() {
		if ( $this->needs_access_keys() ) {
			throw new Exception( __( 'You must first set your AWS access keys to use S3 Image Optimizer.', 's3-image-optimizer' ) );
		}

		if ( is_null( $this->client ) ) {
			$args = array();

			if ( ! $this->use_ec2_iam_roles() ) {
				$args['credentials'] = array(
					'key'    => $this->get_access_key_id(),
					'secret' => $this->get_secret_access_key(),
				);
			}

			$args             = apply_filters( 'aws_get_client_args', $args );
			$this->aws_client = new Sdk( $args );

			if ( empty( $args['region'] ) || $this->default_region === $args['region'] ) {
				$this->client = $this->aws_client->createMultiRegionS3( $args );
			} else {
				$this->client = $this->aws_client->createS3( $args );
			}
		}

		return $this->client;
	}

	/**
	 * Get the first defined constant from the given list of constant names.
	 *
	 * @param array $constants A list of constant names to check.
	 *
	 * @return string|false string constant name if defined, otherwise false if none are defined
	 */
	public static function get_first_defined_constant( $constants ) {
		foreach ( (array) $constants as $constant ) {
			if ( defined( $constant ) ) {
				return $constant;
			}
		}
		return false;
	}
}
global $s3io_amazon_web_services;
$s3io_amazon_web_services = new Amazon_Web_Services();
