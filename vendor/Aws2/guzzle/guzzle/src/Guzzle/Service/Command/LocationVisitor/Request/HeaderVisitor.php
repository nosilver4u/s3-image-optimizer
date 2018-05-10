<?php

namespace S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Request;

use S3IO\Aws2\Guzzle\Common\Exception\InvalidArgumentException;
use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
use S3IO\Aws2\Guzzle\Service\Description\Parameter;
/**
 * Visitor used to apply a parameter to a header value
 */
class HeaderVisitor extends \S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Request\AbstractRequestVisitor
{
    public function visit(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, \S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Service\Description\Parameter $param, $value)
    {
        $value = $param->filter($value);
        if ($param->getType() == 'object' && $param->getAdditionalProperties() instanceof Parameter) {
            $this->addPrefixedHeaders($request, $param, $value);
        } else {
            $request->setHeader($param->getWireName(), $value);
        }
    }
    /**
     * Add a prefixed array of headers to the request
     *
     * @param RequestInterface $request Request to update
     * @param Parameter        $param   Parameter object
     * @param array            $value   Header array to add
     *
     * @throws InvalidArgumentException
     */
    protected function addPrefixedHeaders(\S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Service\Description\Parameter $param, $value)
    {
        if (!is_array($value)) {
            throw new \S3IO\Aws2\Guzzle\Common\Exception\InvalidArgumentException('An array of mapped headers expected, but received a single value');
        }
        $prefix = $param->getSentAs();
        foreach ($value as $headerName => $headerValue) {
            $request->setHeader($prefix . $headerName, $headerValue);
        }
    }
}
