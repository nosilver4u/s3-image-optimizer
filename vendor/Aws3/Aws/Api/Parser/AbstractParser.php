<?php

namespace S3IO\Aws3\Aws\Api\Parser;

use S3IO\Aws3\Aws\Api\Service;
use S3IO\Aws3\Aws\Api\StructureShape;
use S3IO\Aws3\Aws\CommandInterface;
use S3IO\Aws3\Aws\ResultInterface;
use S3IO\Aws3\Psr\Http\Message\ResponseInterface;
use S3IO\Aws3\Psr\Http\Message\StreamInterface;
/**
 * @internal
 */
abstract class AbstractParser
{
    /** @var \Aws\Api\Service Representation of the service API*/
    protected $api;
    /** @var callable */
    protected $parser;
    /**
     * @param Service $api Service description.
     */
    public function __construct(Service $api)
    {
        $this->api = $api;
    }
    /**
     * @param CommandInterface  $command  Command that was executed.
     * @param ResponseInterface $response Response that was received.
     *
     * @return ResultInterface
     */
    abstract public function __invoke(CommandInterface $command, ResponseInterface $response);
    abstract public function parseMemberFromStream(StreamInterface $stream, StructureShape $member, $response);
}
