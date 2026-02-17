<?php

namespace S3IO\Aws3\Aws\Auth\Exception;

use S3IO\Aws3\Aws\HasMonitoringEventsTrait;
use S3IO\Aws3\Aws\MonitoringEventsInterface;
/**
 * Represents an error when attempting to resolve authentication.
 */
class UnresolvedAuthSchemeException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
