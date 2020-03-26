<?php

namespace S3IO\Aws3\GuzzleHttp\Promise;

/**
 * Exception that is set as the reason for a promise that has been cancelled.
 */
class CancellationException extends \S3IO\Aws3\GuzzleHttp\Promise\RejectionException
{
}
