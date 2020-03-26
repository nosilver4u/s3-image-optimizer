<?php

namespace S3IO\Aws3\Aws\Api\Parser\Exception;

use S3IO\Aws3\Aws\HasMonitoringEventsTrait;
use S3IO\Aws3\Aws\MonitoringEventsInterface;
use S3IO\Aws3\Aws\ResponseContainerInterface;
class ParserException extends \RuntimeException implements \S3IO\Aws3\Aws\MonitoringEventsInterface, \S3IO\Aws3\Aws\ResponseContainerInterface
{
    use HasMonitoringEventsTrait;
    private $response;
    public function __construct($message = '', $code = 0, $previous = null, array $context = [])
    {
        $this->response = isset($context['response']) ? $context['response'] : null;
        parent::__construct($message, $code, $previous);
    }
    /**
     * Get the received HTTP response if any.
     *
     * @return ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->response;
    }
}
