<?php

namespace S3IO\Aws3\Aws\Arn\S3;

use S3IO\Aws3\Aws\Arn\AccessPointArn as BaseAccessPointArn;
use S3IO\Aws3\Aws\Arn\ArnInterface;
use S3IO\Aws3\Aws\Arn\Exception\InvalidArnException;
/**
 * @internal
 */
class AccessPointArn extends \S3IO\Aws3\Aws\Arn\AccessPointArn implements \S3IO\Aws3\Aws\Arn\ArnInterface
{
    /**
     * Validation specific to AccessPointArn
     *
     * @param array $data
     */
    protected static function validate(array $data)
    {
        parent::validate($data);
        if ($data['service'] !== 's3') {
            throw new \S3IO\Aws3\Aws\Arn\Exception\InvalidArnException("The 3rd component of an S3 access" . " point ARN represents the region and must be 's3'.");
        }
    }
}
