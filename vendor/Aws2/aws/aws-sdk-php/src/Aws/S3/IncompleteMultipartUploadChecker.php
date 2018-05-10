<?php

namespace S3IO\Aws2\Aws\S3;

use S3IO\Aws2\Guzzle\Http\Exception\HttpException;
use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Http\Message\EntityEnclosingRequestInterface;
use S3IO\Aws2\Guzzle\Http\Message\Response;
use S3IO\Aws2\Guzzle\Plugin\Backoff\BackoffStrategyInterface;
use S3IO\Aws2\Guzzle\Plugin\Backoff\AbstractBackoffStrategy;
/**
 * Retries CompleteMultipartUpload requests in the case of failure.
 *
 * From the S3 API Documentation:
 *
 *     Processing of a Complete Multipart Upload request could take several
 *     minutes to complete. After Amazon S3 begins processing the request, it
 *     sends an HTTP response header that specifies a 200 OK response. While
 *     processing is in progress, Amazon S3 periodically sends whitespace
 *     characters to keep the connection from timing out. Because a request
 *     could fail after the initial 200 OK response has been sent, it is
 *     important that you check the response body to determine whether the
 *     request succeeded. Note that if Complete Multipart Upload fails,
 *     applications should be prepared to retry the failed requests.
 */
class IncompleteMultipartUploadChecker extends \S3IO\Aws2\Guzzle\Plugin\Backoff\AbstractBackoffStrategy
{
    public function __construct(\S3IO\Aws2\Guzzle\Plugin\Backoff\BackoffStrategyInterface $next = null)
    {
        if ($next) {
            $this->setNext($next);
        }
    }
    public function makesDecision()
    {
        return true;
    }
    protected function getDelay($retries, \S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Http\Message\Response $response = null, \S3IO\Aws2\Guzzle\Http\Exception\HttpException $e = null)
    {
        if ($response && $request->getMethod() === 'POST' && $request instanceof EntityEnclosingRequestInterface && $response->getStatusCode() == 200 && strpos($request->getBody(), '<CompleteMultipartUpload xmlns') !== false && strpos($response->getBody(), '<CompleteMultipartUploadResult xmlns') === false) {
            return true;
        }
    }
}
