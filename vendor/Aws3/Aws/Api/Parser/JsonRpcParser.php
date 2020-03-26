<?php

namespace S3IO\Aws3\Aws\Api\Parser;

use S3IO\Aws3\Aws\Api\StructureShape;
use S3IO\Aws3\Aws\Api\Service;
use S3IO\Aws3\Aws\Result;
use S3IO\Aws3\Aws\CommandInterface;
use S3IO\Aws3\Psr\Http\Message\ResponseInterface;
use S3IO\Aws3\Psr\Http\Message\StreamInterface;
/**
 * @internal Implements JSON-RPC parsing (e.g., DynamoDB)
 */
class JsonRpcParser extends \S3IO\Aws3\Aws\Api\Parser\AbstractParser
{
    use PayloadParserTrait;
    /**
     * @param Service    $api    Service description
     * @param JsonParser $parser JSON body builder
     */
    public function __construct(\S3IO\Aws3\Aws\Api\Service $api, \S3IO\Aws3\Aws\Api\Parser\JsonParser $parser = null)
    {
        parent::__construct($api);
        $this->parser = $parser ?: new \S3IO\Aws3\Aws\Api\Parser\JsonParser();
    }
    public function __invoke(\S3IO\Aws3\Aws\CommandInterface $command, \S3IO\Aws3\Psr\Http\Message\ResponseInterface $response)
    {
        $operation = $this->api->getOperation($command->getName());
        $result = null === $operation['output'] ? null : $this->parseMemberFromStream($response->getBody(), $operation->getOutput(), $response);
        return new \S3IO\Aws3\Aws\Result($result ?: []);
    }
    public function parseMemberFromStream(\S3IO\Aws3\Psr\Http\Message\StreamInterface $stream, \S3IO\Aws3\Aws\Api\StructureShape $member, $response)
    {
        return $this->parser->parse($member, $this->parseJson($stream, $response));
    }
}
