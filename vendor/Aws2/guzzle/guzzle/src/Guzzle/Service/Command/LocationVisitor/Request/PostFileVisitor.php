<?php

namespace S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Request;

use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Http\Message\PostFileInterface;
use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
use S3IO\Aws2\Guzzle\Service\Description\Parameter;
/**
 * Visitor used to apply a parameter to a POST file
 */
class PostFileVisitor extends \S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Request\AbstractRequestVisitor
{
    public function visit(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, \S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Service\Description\Parameter $param, $value)
    {
        $value = $param->filter($value);
        if ($value instanceof PostFileInterface) {
            $request->addPostFile($value);
        } else {
            $request->addPostFile($param->getWireName(), $value);
        }
    }
}
