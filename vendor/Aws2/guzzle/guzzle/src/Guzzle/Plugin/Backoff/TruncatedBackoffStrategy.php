<?php

namespace S3IO\Aws2\Guzzle\Plugin\Backoff;

use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Http\Message\Response;
use S3IO\Aws2\Guzzle\Http\Exception\HttpException;
/**
 * Strategy that will not retry more than a certain number of times.
 */
class TruncatedBackoffStrategy extends \S3IO\Aws2\Guzzle\Plugin\Backoff\AbstractBackoffStrategy
{
    /** @var int Maximum number of retries per request */
    protected $max;
    /**
     * @param int                      $maxRetries Maximum number of retries per request
     * @param BackoffStrategyInterface $next The optional next strategy
     */
    public function __construct($maxRetries, \S3IO\Aws2\Guzzle\Plugin\Backoff\BackoffStrategyInterface $next = null)
    {
        $this->max = $maxRetries;
        $this->next = $next;
    }
    public function makesDecision()
    {
        return true;
    }
    protected function getDelay($retries, \S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Http\Message\Response $response = null, \S3IO\Aws2\Guzzle\Http\Exception\HttpException $e = null)
    {
        return $retries < $this->max ? null : false;
    }
}
