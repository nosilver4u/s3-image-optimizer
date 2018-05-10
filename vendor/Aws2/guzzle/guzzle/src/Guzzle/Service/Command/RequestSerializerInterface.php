<?php

namespace S3IO\Aws2\Guzzle\Service\Command;

use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
/**
 * Translates command options and operation parameters into a request object
 */
interface RequestSerializerInterface
{
    /**
     * Create a request for a command
     *
     * @param CommandInterface $command Command that will own the request
     *
     * @return RequestInterface
     */
    public function prepare(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command);
}
