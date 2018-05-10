<?php

namespace S3IO\Aws2\Guzzle\Service\Command\LocationVisitor\Request;

use S3IO\Aws2\Guzzle\Http\Message\RequestInterface;
use S3IO\Aws2\Guzzle\Service\Description\Parameter;
use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
/**
 * Location visitor used to add values to different locations in a request with different behaviors as needed
 */
interface RequestVisitorInterface
{
    /**
     * Called after visiting all parameters
     *
     * @param CommandInterface $command Command being visited
     * @param RequestInterface $request Request being visited
     */
    public function after(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, \S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request);
    /**
     * Called once for each parameter being visited that matches the location type
     *
     * @param CommandInterface $command Command being visited
     * @param RequestInterface $request Request being visited
     * @param Parameter        $param   Parameter being visited
     * @param mixed            $value   Value to set
     */
    public function visit(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, \S3IO\Aws2\Guzzle\Http\Message\RequestInterface $request, \S3IO\Aws2\Guzzle\Service\Description\Parameter $param, $value);
}
