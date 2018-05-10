<?php

namespace S3IO\Aws2\Guzzle\Log;

use S3IO\Aws2\Monolog\Logger;
/**
 * @deprecated
 * @codeCoverageIgnore
 */
class MonologLogAdapter extends \S3IO\Aws2\Guzzle\Log\AbstractLogAdapter
{
    /**
     * syslog to Monolog mappings
     */
    private static $mapping = array(LOG_DEBUG => \S3IO\Aws2\Monolog\Logger::DEBUG, LOG_INFO => \S3IO\Aws2\Monolog\Logger::INFO, LOG_WARNING => \S3IO\Aws2\Monolog\Logger::WARNING, LOG_ERR => \S3IO\Aws2\Monolog\Logger::ERROR, LOG_CRIT => \S3IO\Aws2\Monolog\Logger::CRITICAL, LOG_ALERT => \S3IO\Aws2\Monolog\Logger::ALERT);
    public function __construct(\S3IO\Aws2\Monolog\Logger $logObject)
    {
        $this->log = $logObject;
    }
    public function log($message, $priority = LOG_INFO, $extras = array())
    {
        $this->log->addRecord(self::$mapping[$priority], $message, $extras);
    }
}
