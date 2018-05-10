<?php

namespace S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Response;

use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
use S3IO\Aws2\Guzzle\Http\Message\Response;
use S3IO\Aws2\Guzzle\Service\Description\Parameter;
/**
 * Visitor used to add the body of a response to a particular key
 */
class BodyVisitor extends \S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Response\AbstractResponseVisitor
{
    public function visit(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, \S3IO\Aws2\Guzzle\Http\Message\Response $response, \S3IO\Aws2\Guzzle\Service\Description\Parameter $param, &$value, $context = null)
    {
        $value[$param->getName()] = $param->filter($response->getBody());
    }
}
