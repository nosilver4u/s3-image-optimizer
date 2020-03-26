<?php

namespace S3IO\Aws3\GuzzleHttp\Exception;

/**
 * Exception when a server error is encountered (5xx codes)
 */
class ServerException extends \S3IO\Aws3\GuzzleHttp\Exception\BadResponseException
{
}
