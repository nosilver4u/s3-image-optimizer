<?php

namespace S3IO\Aws3\Aws\Api\Parser;

use S3IO\Aws3\Aws\Api\StructureShape;
use S3IO\Aws3\Aws\CommandInterface;
use S3IO\Aws3\Aws\Exception\AwsException;
use S3IO\Aws3\Psr\Http\Message\ResponseInterface;
use S3IO\Aws3\Psr\Http\Message\StreamInterface;
use S3IO\Aws3\GuzzleHttp\Psr7;
/**
 * @internal Decorates a parser and validates the x-amz-crc32 header.
 */
class Crc32ValidatingParser extends \S3IO\Aws3\Aws\Api\Parser\AbstractParser
{
    /**
     * @param callable $parser Parser to wrap.
     */
    public function __construct(callable $parser)
    {
        $this->parser = $parser;
    }
    public function __invoke(\S3IO\Aws3\Aws\CommandInterface $command, \S3IO\Aws3\Psr\Http\Message\ResponseInterface $response)
    {
        if ($expected = $response->getHeaderLine('x-amz-crc32')) {
            $hash = hexdec(\S3IO\Aws3\GuzzleHttp\Psr7\hash($response->getBody(), 'crc32b'));
            if ($expected != $hash) {
                throw new \S3IO\Aws3\Aws\Exception\AwsException("crc32 mismatch. Expected {$expected}, found {$hash}.", $command, ['code' => 'ClientChecksumMismatch', 'connection_error' => true, 'response' => $response]);
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
