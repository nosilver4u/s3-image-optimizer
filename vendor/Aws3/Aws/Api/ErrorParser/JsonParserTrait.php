<?php

namespace S3IO\Aws3\Aws\Api\ErrorParser;

use S3IO\Aws3\Aws\Api\Parser\PayloadParserTrait;
use S3IO\Aws3\Aws\Api\StructureShape;
use S3IO\Aws3\Psr\Http\Message\ResponseInterface;
/**
 * Provides basic JSON error parsing functionality.
 */
trait JsonParserTrait
{
    use PayloadParserTrait;
    private function genericHandler(\S3IO\Aws3\Psr\Http\Message\ResponseInterface $response)
    {
        $code = (string) $response->getStatusCode();
        return ['request_id' => (string) $response->getHeaderLine('x-amzn-requestid'), 'code' => null, 'message' => null, 'type' => $code[0] == '4' ? 'client' : 'server', 'parsed' => $this->parseJson($response->getBody(), $response)];
    }
    protected function payload(\S3IO\Aws3\Psr\Http\Message\ResponseInterface $response, \S3IO\Aws3\Aws\Api\StructureShape $member)
    {
        $jsonBody = $this->parseJson($response->getBody(), $response);
        if ($jsonBody) {
            return $this->parser->parse($member, $jsonBody);
        }
    }
}
