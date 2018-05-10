<?php

namespace S3IO\Aws2\Guzzle\Plugin\Cache;

use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Http\Message\Response;
/**
 * Never performs cache revalidation and just assumes the request is invalid
 */
class DenyRevalidation extends \S3IO\Aws2\Guzzle\Plugin\Cache\DefaultRevalidation
{
    public function __construct()
    {
    }
    public function revalidate(\S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Http\Message\Response $response)
    {
        return false;
    }
}
