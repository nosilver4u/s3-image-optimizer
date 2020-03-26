<?php

namespace S3IO\Aws3\Aws\S3;

use S3IO\Aws3\Aws\Api\Parser\AbstractParser;
use S3IO\Aws3\Aws\Api\StructureShape;
use S3IO\Aws3\Aws\Api\Parser\Exception\ParserException;
use S3IO\Aws3\Aws\CommandInterface;
use S3IO\Aws3\Aws\Exception\AwsException;
use S3IO\Aws3\Psr\Http\Message\ResponseInterface;
use S3IO\Aws3\Psr\Http\Message\StreamInterface;
/**
 * Converts malformed responses to a retryable error type.
 *
 * @internal
 */
class RetryableMalformedResponseParser extends \S3IO\Aws3\Aws\Api\Parser\AbstractParser
{
    /** @var string */
    private $exceptionClass;
    public function __construct(callable $parser, $exceptionClass = \S3IO\Aws3\Aws\Exception\AwsException::class)
    {
        $this->parser = $parser;
        $this->exceptionClass = $exceptionClass;
    }
    public function __invoke(\S3IO\Aws3\Aws\CommandInterface $command, \S3IO\Aws3\Psr\Http\Message\ResponseInterface $response)
    {
        $fn = $this->parser;
        try {
            return $fn($command, $response);
        } catch (ParserException $e) {
            throw new $this->exceptionClass("Error parsing response for {$command->getName()}:" . " AWS parsing error: {$e->getMessage()}", $command, ['connection_error' => true, 'exception' => $e], $e);
        }
    }
    public function parseMemberFromStream(\S3IO\Aws3\Psr\Http\Message\StreamInterface $stream, \S3IO\Aws3\Aws\Api\StructureShape $member, $response)
    {
        return $this->parser->parseMemberFromStream($stream, $member, $response);
    }
}
