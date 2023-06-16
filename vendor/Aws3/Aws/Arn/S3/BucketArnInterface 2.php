<?php

namespace S3IO\Aws3\Aws\Arn\S3;

use S3IO\Aws3\Aws\Arn\ArnInterface;
/**
 * @internal
 */
interface BucketArnInterface extends ArnInterface
{
    public function getBucketName();
}
