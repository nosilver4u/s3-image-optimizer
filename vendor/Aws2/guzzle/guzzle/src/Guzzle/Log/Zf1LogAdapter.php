<?php

namespace S3IO\Aws2\Guzzle\Log;

use S3IO\Aws2\Guzzle\Common\Version;
/**
 * Adapts a Zend Framework 1 logger object
 * @deprecated
 * @codeCoverageIgnore
 */
class Zf1LogAdapter extends \S3IO\Aws2\Guzzle\Log\AbstractLogAdapter
{
    public function __construct(\Zend_Log $logObject)
    {
        $this->log = $logObject;
        \S3IO\Aws2\Guzzle\Common\Version::warn(__CLASS__ . ' is deprecated');
    }
    public function log($message, $priority = LOG_INFO, $extras = array())
    {
        $this->log->log($message, $priority, $extras);
    }
}
