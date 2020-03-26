<?php

namespace S3IO\Aws3\Aws\ClientSideMonitoring\Exception;

use S3IO\Aws3\Aws\HasMonitoringEventsTrait;
use S3IO\Aws3\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for client-side monitoring.
 */
class ConfigurationException extends \RuntimeException implements \S3IO\Aws3\Aws\MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
