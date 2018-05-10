<?php

namespace S3IO\Aws2\Guzzle\Log;

use S3IO\Aws2\Psr\Log\LogLevel;
use S3IO\Aws2\Psr\Log\LoggerInterface;
/**
 * PSR-3 log adapter
 *
 * @link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
 */
class PsrLogAdapter extends \S3IO\Aws2\Guzzle\Log\AbstractLogAdapter
{
    /**
     * syslog to PSR-3 mappings
     */
    private static $mapping = array(LOG_DEBUG => \S3IO\Aws2\Psr\Log\LogLevel::DEBUG, LOG_INFO => \S3IO\Aws2\Psr\Log\LogLevel::INFO, LOG_WARNING => \S3IO\Aws2\Psr\Log\LogLevel::WARNING, LOG_ERR => \S3IO\Aws2\Psr\Log\LogLevel::ERROR, LOG_CRIT => \S3IO\Aws2\Psr\Log\LogLevel::CRITICAL, LOG_ALERT => \S3IO\Aws2\Psr\Log\LogLevel::ALERT);
    public function __construct(\S3IO\Aws2\Psr\Log\LoggerInterface $logObject)
    {
        $this->log = $logObject;
    }
    public function log($message, $priority = LOG_INFO, $extras = array())
    {
        $this->log->log(self::$mapping[$priority], $message, $extras);
    }
}
