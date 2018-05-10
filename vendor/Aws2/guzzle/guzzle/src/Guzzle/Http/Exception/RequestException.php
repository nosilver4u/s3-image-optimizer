<?php

namespace S3IO\Aws2\Guzzle\Http\Exception;

use S3IO\Aws2\Guzzle\Common\Exception\RuntimeException;
use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
/**
 * Http request exception
 */
class RequestException extends \S3IO\Aws2\Guzzle\Common\Exception\RuntimeException implements \S3IO\Aws2\Guzzle\Http\Exception\HttpException
{
    /** @var RequestInterface */
    protected $request;
    /**
     * Set the request that caused the exception
     *
     * @param RequestInterface $request Request to set
     *
     * @return RequestException
     */
    public function setRequest(\S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request)
    {
        $this->request = $request;
        return $this;
    }
    /**
     * Get the request that caused the exception
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }
}
