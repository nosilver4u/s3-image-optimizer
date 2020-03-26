<?php

namespace S3IO\Aws3\Aws\S3;

use S3IO\Aws3\Aws\Api\Parser\AbstractParser;
use S3IO\Aws3\Aws\Api\StructureShape;
use S3IO\Aws3\Aws\CommandInterface;
use S3IO\Aws3\Aws\Exception\AwsException;
use S3IO\Aws3\Psr\Http\Message\ResponseInterface;
use S3IO\Aws3\Psr\Http\Message\StreamInterface;
/**
 * Converts errors returned with a status code of 200 to a retryable error type.
 *
 * @internal
 */
class AmbiguousSuccessParser extends \S3IO\Aws3\Aws\Api\Parser\AbstractParser
{
    private static $ambiguousSuccesses = ['UploadPartCopy' => true, 'CopyObject' => true, 'CompleteMultipartUpload' => true];
    /** @var callable */
    private $errorParser;
    /** @var string */
    private $exceptionClass;
    public function __construct(callable $parser, callable $errorParser, $exceptionClass = \S3IO\Aws3\Aws\Exception\AwsException::class)
    {
        $this->parser = $parser;
        $this->errorParser = $errorParser;
        $this->exceptionClass = $exceptionClass;
    }
    public function __invoke(\S3IO\Aws3\Aws\CommandInterface $command, \S3IO\Aws3\Psr\Http\Message\ResponseInterface $response)
    {
        if (200 === $response->getStatusCode() && isset(self::$ambiguousSuccesses[$command->getName()])) {
            $errorParser = $this->errorParser;
            $parsed = $errorParser($response);
            if (isset($parsed['code']) && isset($parsed['message'])) {
                throw new $this->exceptionClass($parsed['message'], $command, ['connection_error' => true]);
            }
        }
        $fn = $this->parser;
        return $fn($command, $response);
    }
    public function parseMemberFromStream(\S3IO\Aws3\Psr\Http\Message\StreamInterface $stream, \S3IO\Aws3\Aws\Api\StructureShape $member, $response)
    {
        return $this->parser->parseMemberFromStream($stream, $member, $response);
    }
}
