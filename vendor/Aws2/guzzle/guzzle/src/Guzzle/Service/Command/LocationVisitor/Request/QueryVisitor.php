<?php

namespace S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Request;

use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
use S3IO\Aws2\Guzzle\Service\Description\Parameter;
/**
 * Visitor used to apply a parameter to a request's query string
 */
class QueryVisitor extends \S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Request\AbstractRequestVisitor
{
    public function visit(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, \S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Service\Description\Parameter $param, $value)
    {
        $request->getQuery()->set($param->getWireName(), $this->prepareValue($value, $param));
    }
}
