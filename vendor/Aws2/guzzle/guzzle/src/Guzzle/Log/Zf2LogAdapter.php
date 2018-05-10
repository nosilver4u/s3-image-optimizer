<?php

namespace S3IO\Aws2\Guzzle\Log;

use S3IO\Aws2\Zend\Log\Logger;
/**
 * Adapts a Zend Framework 2 logger object
 */
class Zf2LogAdapter extends \S3IO\Aws2\Guzzle\Log\AbstractLogAdapter
{
    public function __construct(\S3IO\Aws2\Zend\Log\Logger $logObject)
    {
        $this->log = $logObject;
    }
    public function log($message, $priority = LOG_INFO, $extras = array())
    {
        $this->log->log($priority, $message, $extras);
    }
}
