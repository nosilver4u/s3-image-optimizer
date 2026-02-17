<?php

namespace S3IO\Aws3\Aws\S3;

use S3IO\Aws3\Aws\Api\Parser\AbstractParser;
use S3IO\Aws3\Aws\Api\StructureShape;
use S3IO\Aws3\Aws\CommandInterface;
use S3IO\Aws3\Psr\Http\Message\ResponseInterface;
use S3IO\Aws3\Psr\Http\Message\StreamInterface;
/**
 * @internal Decorates a parser for the S3 service to correctly handle the
 *           GetBucketLocation operation.
 */
class GetBucketLocationParser extends AbstractParser
{
    /**
     * @param callable $parser Parser to wrap.
     */
    public function __construct(callable $parser)
    {
        $this->parser = $parser;
    }
    public function __invoke(CommandInterface $command, ResponseInterface $response)
    {
        $fn = $this->parser;
        $result = $fn($command, $response);
        if ($command->getName() === 'GetBucketLocation') {
            $location = 'us-east-1';
            if (preg_match('/>(.+?)<\/LocationConstraint>/', $response->getBody(), $matches)) {
                $location = $matches[1] === 'EU' ? 'eu-west-1' : $matches[1];
            }
            $result['LocationConstraint'] = $location;
        }
        return $result;
    }
    public function parseMemberFromStream(StreamInterface $stream, StructureShape $member, $response)
    {
        return $this->parser->parseMemberFromStream($stream, $member, $response);
    }
}
