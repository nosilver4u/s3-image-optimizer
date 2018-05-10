<?php

namespace S3IO\Aws2\Guzzle\Plugin\Cache;

use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Http\Message\Response;
/**
 * Interface used to cache HTTP requests
 */
interface CacheStorageInterface
{
    /**
     * Get a Response from the cache for a request
     *
     * @param RequestInterface $request
     *
     * @return null|Response
     */
    public function fetch(\S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request);
    /**
     * Cache an HTTP request
     *
     * @param RequestInterface $request  Request being cached
     * @param Response         $response Response to cache
     */
    public function cache(\S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Http\Message\Response $response);
    /**
     * Deletes cache entries that match a request
     *
     * @param RequestInterface $request Request to delete from cache
     */
    public function delete(\S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request);
    /**
     * Purge all cache entries for a given URL
     *
     * @param string $url
     */
    public function purge($url);
}
