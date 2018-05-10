<?php

namespace S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Response;

use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
use S3IO\Aws2\Guzzle\Http\Message\Response;
use S3IO\Aws2\Guzzle\Service\Description\Parameter;
/**
 * {@inheritdoc}
 * @codeCoverageIgnore
 */
abstract class AbstractResponseVisitor implements \S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Response\ResponseVisitorInterface
{
    public function before(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, array &$result)
    {
    }
    public function after(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command)
    {
    }
    public function visit(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, \S3IO\Aws2\Guzzle\Http\Message\Response $response, \S3IO\Aws2\Guzzle\Service\Description\Parameter $param, &$value, $context = null)
    {
    }
}
