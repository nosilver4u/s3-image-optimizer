<?php

namespace S3IO\Aws3\Aws\Endpoint\UseFipsEndpoint\Exception;

use S3IO\Aws3\Aws\HasMonitoringEventsTrait;
use S3IO\Aws3\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for useFipsRegion
 */
class ConfigurationException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
