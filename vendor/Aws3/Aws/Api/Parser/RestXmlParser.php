<?php

namespace S3IO\Aws3\Aws\Api\Parser;

use S3IO\Aws3\Aws\Api\StructureShape;
use S3IO\Aws3\Aws\Api\Service;
use S3IO\Aws3\Psr\Http\Message\ResponseInterface;
use S3IO\Aws3\Psr\Http\Message\StreamInterface;
/**
 * @internal Implements REST-XML parsing (e.g., S3, CloudFront, etc...)
 */
class RestXmlParser extends \S3IO\Aws3\Aws\Api\Parser\AbstractRestParser
{
    use PayloadParserTrait;
    /**
     * @param Service   $api    Service description
     * @param XmlParser $parser XML body parser
     */
    public function __construct(\S3IO\Aws3\Aws\Api\Service $api, \S3IO\Aws3\Aws\Api\Parser\XmlParser $parser = null)
    {
        parent::__construct($api);
        $this->parser = $parser ?: new \S3IO\Aws3\Aws\Api\Parser\XmlParser();
    }
    protected function payload(\S3IO\Aws3\Psr\Http\Message\ResponseInterface $response, \S3IO\Aws3\Aws\Api\StructureShape $member, array &$result)
    {
        $result += $this->parseMemberFromStream($response->getBody(), $member, $response);
    }
    public function parseMemberFromStream(\S3IO\Aws3\Psr\Http\Message\StreamInterface $stream, \S3IO\Aws3\Aws\Api\StructureShape $member, $response)
    {
        $xml = $this->parseXml($stream, $response);
        return $this->parser->parse($member, $xml);
    }
}
