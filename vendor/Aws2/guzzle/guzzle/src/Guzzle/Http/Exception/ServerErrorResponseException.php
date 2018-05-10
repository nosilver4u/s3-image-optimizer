<?php

namespace S3IO\Aws2\Guzzle\Http\Exception;

/**
 * Exception when a server error is encountered (5xx codes)
 */
class ServerErrorResponseException extends \S3IO\Aws2\Guzzle\Http\Exception\BadResponseException
{
}
