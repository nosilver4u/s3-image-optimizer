<?php

namespace S3IO\Aws3\Aws\ClientSideMonitoring;

use S3IO\Aws3\Aws\CommandInterface;
use S3IO\Aws3\Aws\Exception\AwsException;
use S3IO\Aws3\Aws\ResultInterface;
use S3IO\Aws3\GuzzleHttp\Psr7\Request;
use S3IO\Aws3\Psr\Http\Message\RequestInterface;
/**
 * @internal
 */
interface MonitoringMiddlewareInterface
{
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param RequestInterface $request
     * @return array
     */
    public static function getRequestData(\S3IO\Aws3\Psr\Http\Message\RequestInterface $request);
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param ResultInterface|AwsException|\Exception $klass
     * @return array
     */
    public static function getResponseData($klass);
    public function __invoke(\S3IO\Aws3\Aws\CommandInterface $cmd, \S3IO\Aws3\Psr\Http\Message\RequestInterface $request);
}
