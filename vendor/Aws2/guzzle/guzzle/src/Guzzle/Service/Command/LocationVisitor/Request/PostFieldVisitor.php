<?php

namespace S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Request;

use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
use S3IO\Aws2\Guzzle\Service\Description\Parameter;
/**
 * Visitor used to apply a parameter to a POST field
 */
class PostFieldVisitor extends \S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Request\AbstractRequestVisitor
{
    public function visit(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, \S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Service\Description\Parameter $param, $value)
    {
        $request->setPostField($param->getWireName(), $this->prepareValue($value, $param));
    }
}
