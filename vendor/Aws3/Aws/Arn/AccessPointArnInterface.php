<?php

namespace S3IO\Aws3\Aws\Arn;

/**
 * @internal
 */
interface AccessPointArnInterface extends ArnInterface
{
    public function getAccesspointName();
}
