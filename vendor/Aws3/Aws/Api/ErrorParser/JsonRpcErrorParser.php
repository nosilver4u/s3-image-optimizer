<?php

namespace S3IO\Aws3\Aws\Api\ErrorParser;

use S3IO\Aws3\Aws\Api\Parser\JsonParser;
use S3IO\Aws3\Aws\Api\Service;
use S3IO\Aws3\Aws\CommandInterface;
use S3IO\Aws3\Psr\Http\Message\ResponseInterface;
/**
 * Parsers JSON-RPC errors.
 */
class JsonRpcErrorParser extends \S3IO\Aws3\Aws\Api\ErrorParser\AbstractErrorParser
{
    use JsonParserTrait;
    private $parser;
    public function __construct(\S3IO\Aws3\Aws\Api\Service $api = null, \S3IO\Aws3\Aws\Api\Parser\JsonParser $parser = null)
    {
        parent::__construct($api);
        $this->parser = $parser ?: new \S3IO\Aws3\Aws\Api\Parser\JsonParser();
    }
    public function __invoke(\S3IO\Aws3\Psr\Http\Message\ResponseInterface $response, \S3IO\Aws3\Aws\CommandInterface $command = null)
    {
        $data = $this->genericHandler($response);
        // Make the casing consistent across services.
        if ($data['parsed']) {
            $data['parsed'] = array_change_key_case($data['parsed']);
        }
        if (isset($data['parsed']['__type'])) {
            $parts = explode('#', $data['parsed']['__type']);
            $data['code'] = isset($parts[1]) ? $parts[1] : $parts[0];
            $data['message'] = isset($data['parsed']['message']) ? $data['parsed']['message'] : null;
        }
        $this->populateShape($data, $response, $command);
        return $data;
    }
}
