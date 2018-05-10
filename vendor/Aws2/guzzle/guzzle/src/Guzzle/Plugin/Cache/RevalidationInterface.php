<?php

namespace S3IO\Aws2\Guzzle\Plugin\Cache;

use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Http\Message\Response;
/**
 * Cache revalidation interface
 */
interface RevalidationInterface
{
    /**
     * Performs a cache revalidation
     *
     * @param RequestInterface $request    Request to revalidate
     * @param Response         $response   Response that was received
     *
     * @return bool Returns true if the request can be cached
     */
    public function revalidate(\S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Http\Message\Response $response);
    /**
     * Returns true if the response should be revalidated
     *
     * @param RequestInterface $request  Request to check
     * @param Response         $response Response to check
     *
     * @return bool
     */
    public function shouldRevalidate(\S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Http\Message\Response $response);
}
