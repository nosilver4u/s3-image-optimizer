<?php

namespace S3IO\Aws3\Aws\Exception;

use S3IO\Aws3\Aws\HasMonitoringEventsTrait;
use S3IO\Aws3\Aws\MonitoringEventsInterface;
class CredentialsException extends \RuntimeException implements \S3IO\Aws3\Aws\MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
