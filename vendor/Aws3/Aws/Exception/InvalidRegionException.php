<?php

namespace S3IO\Aws3\Aws\Exception;

use S3IO\Aws3\Aws\HasMonitoringEventsTrait;
use S3IO\Aws3\Aws\MonitoringEventsInterface;
class InvalidRegionException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
