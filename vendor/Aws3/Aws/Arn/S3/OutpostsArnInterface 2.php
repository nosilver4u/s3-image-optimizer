<?php

namespace S3IO\Aws3\Aws\Arn\S3;

use S3IO\Aws3\Aws\Arn\ArnInterface;
/**
 * @internal
 */
interface OutpostsArnInterface extends ArnInterface
{
    public function getOutpostId();
}
